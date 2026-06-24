import pandas as pd
import numpy as np
import joblib
import pymysql
import os
import matplotlib.pyplot as plt

from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error


# =========================
# 0. DIRECTORIES
# =========================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

PLOT_DIR = os.path.join(BASE_DIR, "plots")
MODEL_DIR = os.path.join(BASE_DIR, "models")

os.makedirs(PLOT_DIR, exist_ok=True)
os.makedirs(MODEL_DIR, exist_ok=True)


# =========================
# 1. LOAD DATA FROM DATABASE
# =========================

DB = dict(
    host="localhost",
    user="root",
    password="",
    db="hammerdad",
    charset="utf8mb4"
)

conn = pymysql.connect(**DB)

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
        ) AS prev_repair_date,

        LEAD(date) OVER (
            PARTITION BY plate_no
            ORDER BY date
        ) AS next_repair_date

    FROM repair_totals
),

repair_features AS (
    SELECT
        transaction_id,
        plate_no,
        date AS repair_date,

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
        exterior_cost,

        CASE
            WHEN next_repair_date IS NULL THEN NULL
            ELSE DATEDIFF(next_repair_date, date)
        END AS next_repair_days

    FROM repair_sequence
)

SELECT
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
    exterior_cost,

    next_repair_days
FROM repair_features
WHERE next_repair_days IS NOT NULL
ORDER BY plate_no, repair_month;
"""

df = pd.read_sql(query, conn)
conn.close()

print("Rows loaded from database:", len(df))
print(df.head())


# =========================
# 2. CLEAN DATA
# =========================

df = df.dropna(subset=["next_repair_days"])

numeric_cols = [
    "repair_month",
    "repair_interval_days",
    "avg_service_interval",
    "is_first_repair",
    "total_repairs",
    "days_since_first_repair",
    "engine_system_cost",
    "transmission_cost",
    "brake_system_cost",
    "electrical_system_cost",
    "chassis_cost",
    "fuel_cost",
    "exterior_cost",
    "next_repair_days"
]

for col in numeric_cols:
    df[col] = pd.to_numeric(df[col], errors="coerce")

df = df.dropna(subset=numeric_cols)

df = df[df["next_repair_days"].between(5, 60)]

for col in numeric_cols:
    df[col] = df[col].clip(lower=0)

df["next_repair_days"] = df["next_repair_days"].clip(lower=1)

print("Rows after cleaning:", len(df))


# =========================
# 3. FEATURE ENGINEERING
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
# 4. FEATURES
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

X = df[features].fillna(0)
y_days = df["next_repair_days"]


# =========================
# 5. SPLIT
# =========================

X_train, X_test, y_train, y_test = train_test_split(
    X,
    y_days,
    test_size=0.2,
    random_state=42
)


# =========================
# 6. TRAIN MODEL
# =========================

model_days = RandomForestRegressor(
    n_estimators=300,
    max_depth=10,
    min_samples_split=5,
    min_samples_leaf=2,
    random_state=42
)

model_days.fit(X_train, y_train)


# =========================
# 7. PREDICT
# =========================

pred_days = model_days.predict(X_test)


# =========================
# 8. EVALUATE
# =========================

mae_days = mean_absolute_error(y_test, pred_days)
rmse_days = np.sqrt(mean_squared_error(y_test, pred_days))

print("\n=== NEXT REPAIR DAYS MODEL ===")
print("MAE:", round(mae_days, 2))
print("RMSE:", round(rmse_days, 2))


# =========================
# 9. EVALUATION PLOTS
# =========================

days_errors = y_test - pred_days
abs_days_errors = np.abs(days_errors)


# Actual vs Predicted
plt.figure(figsize=(7, 6))
plt.scatter(y_test, pred_days, alpha=0.7)
plt.plot(
    [y_test.min(), y_test.max()],
    [y_test.min(), y_test.max()],
    linestyle="--"
)
plt.xlabel("Actual Next Repair Days")
plt.ylabel("Predicted Next Repair Days")
plt.title("Actual vs Predicted - Next Repair Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "repair_actual_vs_predicted_days.png"), dpi=300)
plt.show()


# Residual Plot
plt.figure(figsize=(7, 6))
plt.scatter(pred_days, days_errors, alpha=0.7)
plt.axhline(y=0, linestyle="--")
plt.xlabel("Predicted Next Repair Days")
plt.ylabel("Residual Error (Actual - Predicted)")
plt.title("Residual Plot - Next Repair Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "repair_residual_plot_days.png"), dpi=300)
plt.show()


# Error Distribution
plt.figure(figsize=(7, 6))
plt.hist(abs_days_errors, bins=10, edgecolor="black")
plt.xlabel("Absolute Error in Days")
plt.ylabel("Number of Predictions")
plt.title("Error Distribution - Next Repair Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "repair_error_distribution_days.png"), dpi=300)
plt.show()


# Feature Importance Top 10
importance = pd.Series(
    model_days.feature_importances_,
    index=features
).sort_values(ascending=False)

top10_importance = importance.head(10).sort_values()

plt.figure(figsize=(9, 6))
top10_importance.plot(kind="barh")
plt.xlabel("Importance Score")
plt.ylabel("Feature")
plt.title("Top 10 Feature Importance - Next Repair Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "repair_feature_importance_days_top10.png"), dpi=300)
plt.show()


# Prediction Accuracy Range
within_7_days = np.mean(abs_days_errors <= 7) * 100
within_14_days = np.mean(abs_days_errors <= 14) * 100
within_21_days = np.mean(abs_days_errors <= 21) * 100

day_labels = ["Within ±7 days", "Within ±14 days", "Within ±21 days"]
day_values = [within_7_days, within_14_days, within_21_days]

plt.figure(figsize=(7, 6))
bars = plt.bar(day_labels, day_values)
plt.ylabel("Percentage of Predictions")
plt.title("Prediction Accuracy Range - Next Repair Days")
plt.ylim(0, 100)
plt.grid(axis="y")

for bar in bars:
    plt.text(
        bar.get_x() + bar.get_width() / 2,
        bar.get_height() + 1,
        f"{bar.get_height():.1f}%",
        ha="center"
    )

plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "repair_accuracy_range_days.png"), dpi=300)
plt.show()


print("\n=== NEXT REPAIR DAYS ERROR RANGE ===")
print("Within ±7 days:", round(within_7_days, 2), "%")
print("Within ±14 days:", round(within_14_days, 2), "%")
print("Within ±21 days:", round(within_21_days, 2), "%")


# =========================
# 10. SAVE MODEL
# =========================

joblib.dump(
    model_days,
    os.path.join(MODEL_DIR, "repair_model_days.pkl")
)

print("\nRepair days model saved successfully.")