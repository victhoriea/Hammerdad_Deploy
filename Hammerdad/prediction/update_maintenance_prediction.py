import os
import sys
import joblib
import pymysql
import pandas as pd
from datetime import datetime


# =========================
# 0. CONFIG
# =========================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "models", "maintenance_model.pkl")

MODEL_VERSION = "maintenance_v1"

DB = dict(
    host="localhost",
    user="root",
    password="",
    db="hammerdad",
    charset="utf8mb4"
)


# =========================
# 1. GET CHECK NUMBER
# =========================

if len(sys.argv) < 2:
    print("Usage: python update_maintenance_prediction.py CHECK_NO")
    sys.exit(1)

check_no = int(sys.argv[1])


# =========================
# 2. LOAD MODEL
# =========================

model = joblib.load(MODEL_PATH)


# =========================
# 3. CONNECT DATABASE
# =========================

conn = pymysql.connect(**DB)


# =========================
# 4. FETCH MAINTENANCE CHECK DATA
# =========================

query = """
WITH selected_check AS (
    SELECT *
    FROM maintenance_check
    WHERE check_no = %s
),

part_conditions AS (
    SELECT
        cp.check_no,
        MAX(CASE WHEN cp.parts_id = 'TRE' THEN cp.condition_id END) AS tire_condition,
        MAX(CASE WHEN cp.parts_id = 'BRK' THEN cp.condition_id END) AS brake_condition,
        MAX(CASE WHEN cp.parts_id = 'BTY' THEN cp.condition_id END) AS battery_status
    FROM check_parts cp
    GROUP BY cp.check_no
),

pms_history AS (
    SELECT
        plate_no,
        COUNT(*) AS service_history,
        MAX(date) AS last_pms_date
    FROM repair_transactions
    WHERE type_id = 'PMS'
    GROUP BY plate_no
)

SELECT
    mc.check_no,
    mc.plate_no,
    mc.check_date,
    mc.mileage,
    COALESCE(mc.no_issues, 0) AS reported_issues,
    mc.fuel_type_id,
    mc.transmission_type_id,

    pc.tire_condition,
    pc.brake_condition,
    pc.battery_status,

    COALESCE(ph.service_history, 0) AS service_history,
    ph.last_pms_date

FROM selected_check mc
LEFT JOIN part_conditions pc
    ON mc.check_no = pc.check_no
LEFT JOIN pms_history ph
    ON mc.plate_no = ph.plate_no;
"""

df = pd.read_sql(query, conn, params=[check_no])

if df.empty:
    print(f"No maintenance check found for check_no: {check_no}")
    conn.close()
    sys.exit(0)

row = df.iloc[0]
plate_no = row["plate_no"]


# =========================
# 5. DERIVE FEATURES
# =========================

today = pd.Timestamp.today()

if pd.isna(row["last_pms_date"]):
    days_since_last_service = 0
    maintenance_history = "Average"
else:
    days_since_last_service = (
        today - pd.to_datetime(row["last_pms_date"])
    ).days

    if days_since_last_service <= 30:
        maintenance_history = "Good"
    elif days_since_last_service <= 90:
        maintenance_history = "Average"
    else:
        maintenance_history = "Poor"


# =========================
# 6. MANUAL MAPPINGS
# =========================

maintenance_map = {
    "Poor": 0,
    "Average": 1,
    "Good": 2
}

fuel_map = {
    "DSL": 0,
    "PTL": 1,
    "ELC": 2
}

transmission_map = {
    "MNL": 0,
    "ATC": 1
}


def condition_to_score(condition_id, part_type):
    if part_type in ["tire", "brake"]:
        if condition_id in ["NW", "GD"]:
            return 2
        if condition_id == "WO":
            return 0

    if part_type == "battery":
        if condition_id in ["NW", "GD"]:
            return 2
        if condition_id == "WK":
            return 0

    return 1


# =========================
# 7. PREPARE MODEL INPUT
# =========================

features = [
    "Mileage",
    "Maintenance_History",
    "Reported_Issues",
    "Fuel_Type",
    "Transmission_Type",
    "Service_History",
    "Tire_Condition",
    "Brake_Condition",
    "Battery_Status",
    "Days_Since_Last_Service"
]

X = pd.DataFrame([{
    "Mileage": float(row["mileage"]) if not pd.isna(row["mileage"]) else 0,
    "Maintenance_History": maintenance_map[maintenance_history],
    "Reported_Issues": float(row["reported_issues"]) if not pd.isna(row["reported_issues"]) else 0,
    "Fuel_Type": fuel_map.get(row["fuel_type_id"], 1),
    "Transmission_Type": transmission_map.get(row["transmission_type_id"], 0),
    "Service_History": float(row["service_history"]) if not pd.isna(row["service_history"]) else 0,
    "Tire_Condition": condition_to_score(row["tire_condition"], "tire"),
    "Brake_Condition": condition_to_score(row["brake_condition"], "brake"),
    "Battery_Status": condition_to_score(row["battery_status"], "battery"),
    "Days_Since_Last_Service": days_since_last_service
}])[features]


# =========================
# 8. PREDICT
# =========================

prediction = int(model.predict(X)[0])
confidence = float(model.predict_proba(X).max() * 100)


# =========================
# 9. STORE RESULT
# =========================

insert_sql = """
INSERT INTO predictions_maintenance (
    check_no,
    plate_no,
    prediction_date,
    needs_maintenance,
    confidence,
    model_version
)
VALUES (%s, %s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
    plate_no = VALUES(plate_no),
    prediction_date = VALUES(prediction_date),
    needs_maintenance = VALUES(needs_maintenance),
    confidence = VALUES(confidence),
    model_version = VALUES(model_version),
    date_created = CURRENT_TIMESTAMP;
"""

with conn.cursor() as cursor:
    cursor.execute(insert_sql, (
        check_no,
        plate_no,
        datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        prediction,
        round(confidence, 2),
        MODEL_VERSION
    ))

conn.commit()
conn.close()


# =========================
# 10. OUTPUT
# =========================

print(f"Maintenance prediction updated for check_no: {check_no}")
print(f"Plate No: {plate_no}")
print(f"Needs Maintenance: {prediction}")
print(f"Confidence: {round(confidence, 2)}%")
