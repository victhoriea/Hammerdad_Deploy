import pandas as pd
import numpy as np
import joblib
import pymysql
import os
import sys
import io

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

PLOT_DIR = os.path.join(BASE_DIR, "plots")
MODEL_DIR = os.path.join(BASE_DIR, "models")

os.makedirs(PLOT_DIR, exist_ok=True)
os.makedirs(MODEL_DIR, exist_ok=True)

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error


# =========================
# 1. CONNECT TO DATABASE
# =========================

DB = dict(
    host="localhost",
    user="root",
    password="",
    db="hammerdad",
    charset="utf8mb4"
)

conn = pymysql.connect(**DB)


# =========================
# 2. FETCH PMS DATASET
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
        ) AS prev_service_datetime,

        LEAD(service_datetime) OVER (
            PARTITION BY plate_no 
            ORDER BY service_datetime
        ) AS next_service_datetime,

        LEAD(total_cost) OVER (
            PARTITION BY plate_no 
            ORDER BY service_datetime
        ) AS next_total_cost
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
        ) AS running_avg_service_interval,

        CASE
            WHEN next_service_datetime IS NULL THEN NULL
            ELSE DATEDIFF(next_service_datetime, service_datetime)
        END AS next_days,

        next_total_cost

    FROM pms_sequence
)

SELECT
    service_interval_days,
    is_first_service,
    with_additional,
    total_cost,
    COALESCE(running_avg_total_cost, 0) AS running_avg_total_cost,
    COALESCE(running_avg_service_interval, 0) AS running_avg_service_interval,
    next_days,
    next_total_cost
FROM pms_features
WHERE next_days IS NOT NULL
  AND next_total_cost IS NOT NULL
ORDER BY plate_no, service_datetime;
"""

df = pd.read_sql(query, conn)

conn.close()

print("Rows loaded from database:", len(df))
print(df.head())

# =========================
# 2. CLEAN DATA
# =========================

df = df.dropna(subset=[
    "next_days",
    "next_total_cost"
])

numeric_cols = [
    "service_interval_days",
    "is_first_service",
    "with_additional",
    "total_cost",
    "running_avg_total_cost",
    "running_avg_service_interval",
    "next_days",
    "next_total_cost"
]

for col in numeric_cols:
    df[col] = pd.to_numeric(df[col], errors="coerce")

df = df.dropna(subset=numeric_cols)

# Remove extreme outliers
df = df[df["next_total_cost"] <= 2000]
df = df[df["next_days"] <= 50]

print("Rows after cleaning:", len(df))


# =========================
# 3. DEFINE FEATURES
# =========================

features = [
    "service_interval_days",
    "is_first_service",
    "with_additional",
    "running_avg_total_cost",
    "running_avg_service_interval"
]

X = df[features].fillna(0)

y_days = df["next_days"]
y_cost = df["next_total_cost"]


# =========================
# 4. TRAIN/TEST SPLIT
# =========================

X_train, X_test, y_days_train, y_days_test = train_test_split(
    X, y_days, test_size=0.2, random_state=42
)

_, _, y_cost_train, y_cost_test = train_test_split(
    X, y_cost, test_size=0.2, random_state=42
)


# =========================
# 5. PARAMETER GRID
# =========================

param_grid = {
    "n_estimators": [100, 200],
    "max_depth": [5, 10, None],
    "min_samples_split": [2, 5],
    "min_samples_leaf": [1, 2]
}


# =========================
# 6. TRAIN DAYS MODEL
# =========================

days_grid = GridSearchCV(
    RandomForestRegressor(random_state=42),
    param_grid,
    cv=5,
    scoring="neg_mean_absolute_error",
    n_jobs=-1
)

days_grid.fit(X_train, y_days_train)
model_days = days_grid.best_estimator_

print("\nBest Days Parameters:")
print(days_grid.best_params_)


# =========================
# 7. TRAIN COST MODEL (DIRECT)
# =========================

cost_grid = GridSearchCV(
    RandomForestRegressor(random_state=42),
    param_grid,
    cv=5,
    scoring="neg_mean_absolute_error",
    n_jobs=-1
)

cost_grid.fit(X_train, y_cost_train)
model_cost = cost_grid.best_estimator_

print("\nBest Cost Parameters:")
print(cost_grid.best_params_)


# =========================
# 8. PREDICT
# =========================

pred_days = model_days.predict(X_test)
pred_cost = model_cost.predict(X_test)


# =========================
# 9. EVALUATE
# =========================

mae_days = mean_absolute_error(y_days_test, pred_days)
rmse_days = np.sqrt(mean_squared_error(y_days_test, pred_days))

mae_cost = mean_absolute_error(y_cost_test, pred_cost)
rmse_cost = np.sqrt(mean_squared_error(y_cost_test, pred_cost))

print("\n=== PMS NEXT DAYS MODEL ===")
print("MAE:", round(mae_days, 2))
print("RMSE:", round(rmse_days, 2))

print("\n=== PMS NEXT TOTAL COST MODEL ===")
print("MAE:", round(mae_cost, 2))
print("RMSE:", round(rmse_cost, 2))

# =========================
# 10. EVALUATION PLOTS
# =========================

import matplotlib.pyplot as plt

# Compute errors
days_errors = y_days_test - pred_days
cost_errors = y_cost_test - pred_cost

abs_days_errors = np.abs(days_errors)
abs_cost_errors = np.abs(cost_errors)


# -------------------------
# 10.1 ACTUAL VS PREDICTED - DAYS
# -------------------------

plt.figure(figsize=(7, 6))
plt.scatter(y_days_test, pred_days, alpha=0.7)
plt.plot(
    [y_days_test.min(), y_days_test.max()],
    [y_days_test.min(), y_days_test.max()],
    linestyle="--"
)
plt.xlabel("Actual Next PMS Days")
plt.ylabel("Predicted Next PMS Days")
plt.title("Actual vs Predicted - Next PMS Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_actual_vs_predicted_days.png"), dpi=300)
plt.show()


# -------------------------
# 10.2 ACTUAL VS PREDICTED - COST
# -------------------------

plt.figure(figsize=(7, 6))
plt.scatter(y_cost_test, pred_cost, alpha=0.7)
plt.plot(
    [y_cost_test.min(), y_cost_test.max()],
    [y_cost_test.min(), y_cost_test.max()],
    linestyle="--"
)
plt.xlabel("Actual Next PMS Total Cost")
plt.ylabel("Predicted Next PMS Total Cost")
plt.title("Actual vs Predicted - Next PMS Total Cost")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_actual_vs_predicted_cost.png.png"), dpi=300)
plt.show()


# -------------------------
# 10.3 RESIDUAL PLOT - DAYS
# -------------------------

plt.figure(figsize=(7, 6))
plt.scatter(pred_days, days_errors, alpha=0.7)
plt.axhline(y=0, linestyle="--")
plt.xlabel("Predicted Next PMS Days")
plt.ylabel("Residual Error (Actual - Predicted)")
plt.title("Residual Plot - Next PMS Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_residual_plot_days.png"), dpi=300)
plt.show()


# -------------------------
# 10.4 RESIDUAL PLOT - COST
# -------------------------

plt.figure(figsize=(7, 6))
plt.scatter(pred_cost, cost_errors, alpha=0.7)
plt.axhline(y=0, linestyle="--")
plt.xlabel("Predicted Next PMS Total Cost")
plt.ylabel("Residual Error (Actual - Predicted)")
plt.title("Residual Plot - Next PMS Total Cost")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_residual_plot_cost.png"), dpi=300)
plt.show()


# -------------------------
# 10.5 ERROR HISTOGRAM - DAYS
# -------------------------

plt.figure(figsize=(7, 6))
plt.hist(abs_days_errors, bins=10, edgecolor="black")
plt.xlabel("Absolute Error in Days")
plt.ylabel("Number of Predictions")
plt.title("Error Distribution - Next PMS Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_error_histogram_days.png"), dpi=300)
plt.show()


# -------------------------
# 10.6 ERROR HISTOGRAM - COST
# -------------------------

plt.figure(figsize=(7, 6))
plt.hist(abs_cost_errors, bins=10, edgecolor="black")
plt.xlabel("Absolute Error in Cost")
plt.ylabel("Number of Predictions")
plt.title("Error Distribution - Next PMS Total Cost")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_error_histogram_cost.png"), dpi=300)
plt.show()


# -------------------------
# 10.7 FEATURE IMPORTANCE - DAYS
# -------------------------

days_importance = pd.Series(
    model_days.feature_importances_,
    index=features
).sort_values()

plt.figure(figsize=(8, 6))
days_importance.plot(kind="barh")
plt.xlabel("Importance Score")
plt.ylabel("Feature")
plt.title("Feature Importance - Next PMS Days")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_feature_importance_days.png"), dpi=300)
plt.show()


# -------------------------
# 10.8 FEATURE IMPORTANCE - COST
# -------------------------

cost_importance = pd.Series(
    model_cost.feature_importances_,
    index=features
).sort_values()

plt.figure(figsize=(8, 6))
cost_importance.plot(kind="barh")
plt.xlabel("Importance Score")
plt.ylabel("Feature")
plt.title("Feature Importance - Next PMS Total Cost")
plt.grid(True)
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_feature_importance_cost.png"), dpi=300)
plt.show()


# -------------------------
# 10.9 ERROR RANGE SUMMARY - DAYS
# -------------------------

within_1_day = np.mean(abs_days_errors <= 1) * 100
within_3_days = np.mean(abs_days_errors <= 3) * 100
within_5_days = np.mean(abs_days_errors <= 5) * 100

plt.figure(figsize=(7, 6))
plt.bar(
    ["Within +/-1 day", "Within +/-3 days", "Within +/-5 days"],
    [within_1_day, within_3_days, within_5_days]
)
plt.ylabel("Percentage of Predictions")
plt.title("Prediction Accuracy Range - Next PMS Days")
plt.ylim(0, 100)
plt.grid(axis="y")
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_error_range_days.png"), dpi=300)
plt.show()


# -------------------------
# 10.10 ERROR RANGE SUMMARY - COST
# -------------------------

within_100 = np.mean(abs_cost_errors <= 100) * 100
within_250 = np.mean(abs_cost_errors <= 250) * 100
within_500 = np.mean(abs_cost_errors <= 500) * 100

plt.figure(figsize=(7, 6))
plt.bar(
    ["Within +/-P100", "Within +/-P250", "Within +/-P500"],
    [within_100, within_250, within_500]
)
plt.ylabel("Percentage of Predictions")
plt.title("Prediction Accuracy Range - Next PMS Total Cost")
plt.ylim(0, 100)
plt.grid(axis="y")
plt.tight_layout()
plt.savefig(os.path.join(PLOT_DIR, "pms_error_range_cost.png"), dpi=300)
plt.show()


# -------------------------
# 10.11 PRINT ERROR RANGE SUMMARY
# -------------------------

print("\n=== PMS DAYS ERROR RANGE ===")
print("Within +/-1 day:", round(within_1_day, 2), "%")
print("Within +/-3 days:", round(within_3_days, 2), "%")
print("Within +/-5 days:", round(within_5_days, 2), "%")

print("\n=== PMS COST ERROR RANGE ===")
print("Within +/-P100:", round(within_100, 2), "%")
print("Within +/-P250:", round(within_250, 2), "%")
print("Within +/-P500:", round(within_500, 2), "%")

# =========================
# 11. SAVE MODELS
# =========================

joblib.dump(
    model_days,
    os.path.join(MODEL_DIR, "pms_model_days.pkl")
)

joblib.dump(
    model_cost,
    os.path.join(MODEL_DIR, "pms_model_cost.pkl")
)

print("\nCleaned PMS models saved successfully.")