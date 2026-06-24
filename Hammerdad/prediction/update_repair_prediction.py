import os
import sys
import joblib
import pymysql
import pandas as pd
import numpy as np
from datetime import timedelta


# =========================
# 0. CONFIG
# =========================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_DIR = os.path.join(BASE_DIR, "models")

MODEL_PATH = os.path.join(MODEL_DIR, "repair_model_days.pkl")
MODEL_VERSION = "repair_days_v1"

DB = dict(
    host="localhost",
    user="root",
    password="",
    db="hammerdad",
    charset="utf8mb4"
)


# =========================
# 1. GET PLATE NUMBER
# =========================

if len(sys.argv) < 2:
    print("Usage: python update_repair_prediction.py PLATE_NO")
    sys.exit(1)

plate_no = sys.argv[1]


# =========================
# 2. LOAD MODEL
# =========================

model_days = joblib.load(MODEL_PATH)


# =========================
# 3. CONNECT DATABASE
# =========================

conn = pymysql.connect(**DB)


# =========================
# 4. BUILD LATEST REPAIR FEATURES
# =========================

query = """
WITH repair_parts AS (
    SELECT
        rt.transaction_id,
        rt.plate_no,
        rt.date,
        rt.labor_cost,

        COALESCE(SUM(CASE WHEN r.repair_type_id = 'ES'  THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS engine_system_cost,
        COALESCE(SUM(CASE WHEN r.repair_type_id = 'DT'  THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS transmission_cost,
        COALESCE(SUM(CASE WHEN r.repair_type_id = 'BS'  THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS brake_system_cost,
        COALESCE(SUM(CASE WHEN r.repair_type_id = 'ELS' THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS electrical_system_cost,
        COALESCE(SUM(CASE WHEN r.repair_type_id = 'CSW' THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS chassis_cost,
        COALESCE(SUM(CASE WHEN r.repair_type_id = 'FF'  THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS fuel_cost,
        COALESCE(SUM(CASE WHEN r.repair_type_id = 'BE'  THEN tp.unit_price * tp.quantity ELSE 0 END), 0) AS exterior_cost,

        COALESCE(SUM(tp.unit_price * tp.quantity), 0) AS parts_cost

    FROM repair_transactions rt
    LEFT JOIN transaction_parts tp
        ON rt.transaction_id = tp.transaction_id
    LEFT JOIN repair r
        ON tp.repair_id = r.repair_id
    WHERE rt.type_id = 'REP'
      AND rt.plate_no = %s
    GROUP BY
        rt.transaction_id,
        rt.plate_no,
        rt.date,
        rt.labor_cost
),

repair_totals AS (
    SELECT
        *,
        COALESCE(labor_cost, 0) + COALESCE(parts_cost, 0) AS total_repair_cost
    FROM repair_parts
),

repair_sequence AS (
    SELECT
        *,
        MONTH(date) AS repair_month,

        ROW_NUMBER() OVER (
            PARTITION BY plate_no
            ORDER BY date
        ) AS repair_no,

        MIN(date) OVER (
            PARTITION BY plate_no
        ) AS first_repair_date,

        LAG(date) OVER (
            PARTITION BY plate_no
            ORDER BY date
        ) AS prev_repair_date

    FROM repair_totals
),

repair_features AS (
    SELECT
        transaction_id,
        plate_no,
        date AS last_repair_date,

        repair_month,

        CASE
            WHEN prev_repair_date IS NULL THEN 0
            ELSE DATEDIFF(date, prev_repair_date)
        END AS repair_interval_days,

        AVG(
            CASE
                WHEN prev_repair_date IS NULL THEN NULL
                ELSE DATEDIFF(date, prev_repair_date)
            END
        ) OVER (
            PARTITION BY plate_no
            ORDER BY date
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS avg_service_interval,

        CASE
            WHEN repair_no = 1 THEN 1
            ELSE 0
        END AS is_first_repair,

        repair_no AS total_repairs,

        DATEDIFF(date, first_repair_date) AS days_since_first_repair,

        engine_system_cost,
        transmission_cost,
        brake_system_cost,
        electrical_system_cost,
        chassis_cost,
        fuel_cost,
        exterior_cost

    FROM repair_sequence
)

SELECT
    plate_no,
    last_repair_date,

    repair_month,
    repair_interval_days,
    COALESCE(avg_service_interval, 0) AS avg_service_interval,
    is_first_repair,
    total_repairs,
    days_since_first_repair,

    engine_system_cost,
    transmission_cost,
    brake_system_cost,
    electrical_system_cost,
    chassis_cost,
    fuel_cost,
    exterior_cost

FROM repair_features
ORDER BY last_repair_date DESC
LIMIT 1;
"""

df = pd.read_sql(query, conn, params=[plate_no])

if df.empty:
    with conn.cursor() as cursor:
        cursor.execute(
            "DELETE FROM predictions_repair WHERE plate_no = %s",
            (plate_no,)
        )

    conn.commit()
    conn.close()

    print(f"No repair record found for {plate_no}. Repair prediction deleted.")
    sys.exit(0)

# =========================
# 5. FEATURE ENGINEERING
# =========================

system_cols = [
    "engine_system_cost",
    "transmission_cost",
    "brake_system_cost",
    "electrical_system_cost",
    "chassis_cost",
    "fuel_cost",
    "exterior_cost"
]

for col in system_cols:
    df[col] = pd.to_numeric(df[col], errors="coerce").fillna(0)

df["systems_repaired_count"] = (df[system_cols] > 0).sum(axis=1)

df["repair_frequency"] = np.where(
    df["avg_service_interval"] > 0,
    df["total_repairs"] / df["avg_service_interval"],
    0
)

df["dominant_repair_category"] = df[system_cols].idxmax(axis=1)

df.loc[
    df[system_cols].sum(axis=1) == 0,
    "dominant_repair_category"
] = "none"

category_map = {
    "none": 0,
    "engine_system_cost": 1,
    "transmission_cost": 2,
    "brake_system_cost": 3,
    "electrical_system_cost": 4,
    "chassis_cost": 5,
    "fuel_cost": 6,
    "exterior_cost": 7
}

df["dominant_repair_category_code"] = (
    df["dominant_repair_category"]
    .map(category_map)
    .fillna(0)
)

df["repair_quarter"] = ((df["repair_month"] - 1) // 3) + 1

df["is_rainy_season"] = df["repair_month"].isin([6, 7, 8, 9, 10]).astype(int)
df["is_dry_season"] = df["repair_month"].isin([11, 12, 1, 2, 3, 4, 5]).astype(int)


# =========================
# 6. PREDICT
# =========================

features = [
    "repair_interval_days",
    "avg_service_interval",
    "days_since_first_repair",
    "repair_frequency",
    "total_repairs",
    "is_first_repair",
    "systems_repaired_count",
    "dominant_repair_category_code",
    "repair_month",
    "repair_quarter",
    "is_rainy_season",
    "is_dry_season"
]

for col in features:
    df[col] = pd.to_numeric(df[col], errors="coerce").fillna(0)

X = df[features]

pred_days = model_days.predict(X)
pred_days = int(round(pred_days[0]))

if pred_days < 1:
    pred_days = 1

last_repair_date = pd.to_datetime(df.loc[0, "last_repair_date"])
next_repair_date = last_repair_date + timedelta(days=pred_days)


# =========================
# 7. STORE PREDICTION
# =========================

insert_sql = """
INSERT INTO predictions_repair (
    plate_no,
    last_repair_date,
    days_until_next_repair,
    next_repair_date,
    model_version
)
VALUES (%s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
    last_repair_date = VALUES(last_repair_date),
    days_until_next_repair = VALUES(days_until_next_repair),
    next_repair_date = VALUES(next_repair_date),
    model_version = VALUES(model_version),
    date_created = CURRENT_TIMESTAMP;
"""

with conn.cursor() as cursor:
    cursor.execute(insert_sql, (
        plate_no,
        last_repair_date.strftime("%Y-%m-%d %H:%M:%S"),
        pred_days,
        next_repair_date.strftime("%Y-%m-%d"),
        MODEL_VERSION
    ))

conn.commit()
conn.close()

print(f"Repair prediction updated for {plate_no}")
print(f"Last Repair Date: {last_repair_date.strftime('%Y-%m-%d')}")
print(f"Next Repair Days: {pred_days}")
print(f"Next Repair Date: {next_repair_date.strftime('%Y-%m-%d')}")