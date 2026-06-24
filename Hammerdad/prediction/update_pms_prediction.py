import os
import sys
import joblib
import pymysql
import pandas as pd
from datetime import timedelta

# =========================
# CONFIG
# =========================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_DIR = os.path.join(BASE_DIR, "models")

MODEL_DAYS_PATH = os.path.join(MODEL_DIR, "pms_model_days.pkl")
MODEL_COST_PATH = os.path.join(MODEL_DIR, "pms_model_cost.pkl")

MODEL_VERSION = "pms_v1"

DB = dict(
    host="localhost",
    user="root",
    password="",
    db="hammerdad",
    charset="utf8mb4"
)


# =========================
# GET PLATE NUMBER
# =========================

if len(sys.argv) < 2:
    print("Usage: python update_pms_prediction.py PLATE_NO")
    sys.exit(1)

plate_no = sys.argv[1]


# =========================
# LOAD MODELS
# =========================

model_days = joblib.load(MODEL_DAYS_PATH)
model_cost = joblib.load(MODEL_COST_PATH)


# =========================
# CONNECT DB
# =========================

conn = pymysql.connect(**DB)


# =========================
# GET LATEST PMS FEATURES
# =========================

query = """
WITH pms_base AS (
    SELECT
        pms.transaction_id,
        pms.plate_no,
        DATE(pms.date) AS service_date,
        pms.date AS service_datetime,
        COALESCE(pms.labor_cost, 0) AS pms_labor_cost,

        COALESCE((
            SELECT SUM(
                COALESCE(rep.labor_cost, 0) +
                COALESCE(parts.part_total, 0)
            )
            FROM repair_transactions rep
            LEFT JOIN (
                SELECT
                    tp.transaction_id,
                    SUM(tp.unit_price * tp.quantity) AS part_total
                FROM transaction_parts tp
                JOIN repair r ON tp.repair_id = r.repair_id
                GROUP BY tp.transaction_id
            ) parts ON rep.transaction_id = parts.transaction_id
            WHERE rep.plate_no = pms.plate_no
              AND DATE(rep.date) = DATE(pms.date)
              AND rep.type_id = 'REP'
        ), 0) AS additional_repair_cost

    FROM repair_transactions pms
    WHERE pms.type_id = 'PMS'
      AND pms.plate_no = %s
),

pms_with_total AS (
    SELECT
        *,
        CASE 
            WHEN additional_repair_cost > 0 THEN 1 
            ELSE 0 
        END AS with_additional,
        pms_labor_cost + additional_repair_cost AS total_cost
    FROM pms_base
),

pms_sequence AS (
    SELECT
        *,
        ROW_NUMBER() OVER (
            PARTITION BY plate_no 
            ORDER BY service_datetime
        ) AS service_no,

        LAG(service_datetime) OVER (
            PARTITION BY plate_no 
            ORDER BY service_datetime
        ) AS prev_service_datetime
    FROM pms_with_total
),

pms_features AS (
    SELECT
        transaction_id,
        plate_no,
        service_datetime,

        CASE
            WHEN prev_service_datetime IS NULL THEN 0
            ELSE DATEDIFF(service_datetime, prev_service_datetime)
        END AS service_interval_days,

        CASE
            WHEN service_no = 1 THEN 1
            ELSE 0
        END AS is_first_service,

        with_additional,
        total_cost,

        AVG(total_cost) OVER (
            PARTITION BY plate_no
            ORDER BY service_datetime
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS running_avg_total_cost,

        AVG(
            CASE
                WHEN prev_service_datetime IS NULL THEN NULL
                ELSE DATEDIFF(service_datetime, prev_service_datetime)
            END
        ) OVER (
            PARTITION BY plate_no
            ORDER BY service_datetime
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS running_avg_service_interval

    FROM pms_sequence
)

SELECT
    transaction_id,
    plate_no,
    service_datetime AS last_pms_date,
    service_interval_days,
    is_first_service,
    with_additional,
    total_cost,
    COALESCE(running_avg_total_cost, 0) AS running_avg_total_cost,
    COALESCE(running_avg_service_interval, 0) AS running_avg_service_interval
FROM pms_features
ORDER BY service_datetime DESC
LIMIT 1;
"""

df = pd.read_sql(query, conn, params=[plate_no])

if df.empty:
    with conn.cursor() as cursor:
        cursor.execute(
            "DELETE FROM predictions_pms WHERE plate_no = %s",
            (plate_no,)
        )

    conn.commit()
    conn.close()

    print(f"No PMS record found for {plate_no}. PMS prediction deleted.")
    sys.exit(0)


# =========================
# PREPARE FEATURES
# =========================

features = [
    "service_interval_days",
    "is_first_service",
    "with_additional",
    "running_avg_total_cost",
    "running_avg_service_interval"
]

X = df[features].fillna(0)

last_pms_date = pd.to_datetime(df.loc[0, "last_pms_date"])

pred_days = int(round(model_days.predict(X)[0]))
pred_cost = float(model_cost.predict(X)[0])

if pred_days < 1:
    pred_days = 1

next_pms_date = last_pms_date + timedelta(days=pred_days)


# =========================
# SAVE PREDICTION
# =========================

insert_sql = """
INSERT INTO predictions_pms (
    plate_no,
    last_pms_date,
    days_until_next_pms,
    next_pms_date,
    next_total_cost,
    model_version
)
VALUES (%s, %s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
    last_pms_date = VALUES(last_pms_date),
    days_until_next_pms = VALUES(days_until_next_pms),
    next_pms_date = VALUES(next_pms_date),
    next_total_cost = VALUES(next_total_cost),
    model_version = VALUES(model_version),
    date_created = CURRENT_TIMESTAMP;
"""

with conn.cursor() as cursor:
    cursor.execute(insert_sql, (
        plate_no,
        last_pms_date.strftime("%Y-%m-%d %H:%M:%S"),
        pred_days,
        next_pms_date.strftime("%Y-%m-%d"),
        round(pred_cost, 2),
        MODEL_VERSION
    ))

conn.commit()
conn.close()

print(f"PMS prediction updated for {plate_no}")
print(f"Next PMS Days: {pred_days}")
print(f"Next PMS Date: {next_pms_date.strftime('%Y-%m-%d')}")
print(f"Next Total Cost: {round(pred_cost, 2)}")