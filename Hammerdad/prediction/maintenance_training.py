import os
import joblib
import pandas as pd
import matplotlib.pyplot as plt

from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    precision_score,
    recall_score,
    f1_score,
    classification_report,
    confusion_matrix,
    ConfusionMatrixDisplay
)


# =========================
# 0. DIRECTORIES
# =========================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

DATASET_PATH = os.path.join(BASE_DIR, "vehicle_maintenance_data.csv")
PLOT_DIR = os.path.join(BASE_DIR, "plots")
MODEL_DIR = os.path.join(BASE_DIR, "models")

os.makedirs(PLOT_DIR, exist_ok=True)
os.makedirs(MODEL_DIR, exist_ok=True)


# =========================
# 1. LOAD DATASET
# =========================

df = pd.read_csv(DATASET_PATH)

print("Original rows:", len(df))


# =========================
# 2. KEEP ONLY MOTORCYCLES
# =========================

if "Vehicle_Model" in df.columns:
    df = df[df["Vehicle_Model"] == "Motorcycle"].copy()

print("Rows after filtering motorcycles:", len(df))


# =========================
# 3. KEEP ONLY NEEDED COLUMNS
# =========================

needed_cols = [
    "Mileage",
    "Maintenance_History",
    "Reported_Issues",
    "Fuel_Type",
    "Transmission_Type",
    "Last_Service_Date",
    "Service_History",
    "Tire_Condition",
    "Brake_Condition",
    "Battery_Status",
    "Need_Maintenance"
]

df = df[needed_cols].copy()


# =========================
# 4. PROCESS DATE COLUMN
# =========================

df["Last_Service_Date"] = pd.to_datetime(
    df["Last_Service_Date"],
    errors="coerce"
)

df["Days_Since_Last_Service"] = (
    pd.Timestamp.today() - df["Last_Service_Date"]
).dt.days

df = df.drop(columns=["Last_Service_Date"])


# =========================
# 5. MANUAL ENCODING
# =========================

maintenance_map = {
    "Poor": 0,
    "Average": 1,
    "Good": 2
}

fuel_map = {
    "DSL": 0,
    "Diesel": 0,

    "PTL": 1,
    "Petrol": 1,

    "ELC": 2,
    "Electric": 2
}

transmission_map = {
    "MNL": 0,
    "Manual": 0,

    "ATC": 1,
    "Automatic": 1
}

condition_map = {
    "Poor": 0,
    "Worn Out": 0,
    "Weak": 0,

    "Average": 1,

    "Good": 2,
    "New": 2
}

df["Maintenance_History"] = df["Maintenance_History"].map(maintenance_map)
df["Fuel_Type"] = df["Fuel_Type"].map(fuel_map)
df["Transmission_Type"] = df["Transmission_Type"].map(transmission_map)

df["Tire_Condition"] = df["Tire_Condition"].map(condition_map)
df["Brake_Condition"] = df["Brake_Condition"].map(condition_map)
df["Battery_Status"] = df["Battery_Status"].map(condition_map)


# =========================
# 6. ENCODE TARGET
# =========================

target_map = {
    "No": 0,
    "Yes": 1,
    "False": 0,
    "True": 1,
    "Not Needed": 0,
    "Needed": 1,
    "No Maintenance": 0,
    "Needs Maintenance": 1
}

if df["Need_Maintenance"].dtype == "object":
    df["Need_Maintenance"] = df["Need_Maintenance"].map(target_map)


# =========================
# 7. CLEAN DATA
# =========================

numeric_cols = [
    "Mileage",
    "Maintenance_History",
    "Reported_Issues",
    "Fuel_Type",
    "Transmission_Type",
    "Service_History",
    "Tire_Condition",
    "Brake_Condition",
    "Battery_Status",
    "Days_Since_Last_Service",
    "Need_Maintenance"
]

for col in numeric_cols:
    df[col] = pd.to_numeric(df[col], errors="coerce")

df = df.dropna(subset=numeric_cols)

print("Rows after cleaning:", len(df))


# =========================
# 8. FEATURES AND TARGET
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

X = df[features]
y = df["Need_Maintenance"].astype(int)


# =========================
# 9. TRAIN-TEST SPLIT
# =========================

X_train, X_test, y_train, y_test = train_test_split(
    X,
    y,
    test_size=0.2,
    random_state=42,
    stratify=y
)


# =========================
# 10. TRAIN MODEL
# =========================

model = RandomForestClassifier(
    n_estimators=300,
    max_depth=10,
    min_samples_split=5,
    min_samples_leaf=2,
    random_state=42
)

model.fit(X_train, y_train)


# =========================
# 11. PREDICT
# =========================

y_pred = model.predict(X_test)


# =========================
# 12. EVALUATE
# =========================

accuracy = accuracy_score(y_test, y_pred)
precision = precision_score(y_test, y_pred)
recall = recall_score(y_test, y_pred)
f1 = f1_score(y_test, y_pred)

print("\n=== MAINTENANCE PREDICTION MODEL ===")
print("Accuracy :", round(accuracy, 4))
print("Precision:", round(precision, 4))
print("Recall   :", round(recall, 4))
print("F1-Score :", round(f1, 4))

print("\nClassification Report:")
print(classification_report(y_test, y_pred))

print("\nConfusion Matrix:")
print(confusion_matrix(y_test, y_pred))


# =========================
# 13. CONFUSION MATRIX PLOT
# =========================

ConfusionMatrixDisplay.from_estimator(
    model,
    X_test,
    y_test,
    display_labels=["No Maintenance", "Needs Maintenance"]
)

plt.title("Confusion Matrix - Maintenance Prediction")
plt.tight_layout()
plt.savefig(
    os.path.join(PLOT_DIR, "maintenance_confusion_matrix.png"),
    dpi=300
)
plt.show()


# =========================
# 14. PERFORMANCE METRICS PLOT
# =========================

# =========================
# 14. PERFORMANCE METRICS PLOT
# =========================

metrics = {
    "Accuracy": accuracy,
    "Precision": precision,
    "Recall": recall,
    "F1-Score": f1
}

plt.figure(figsize=(9, 6.5))

bars = plt.bar(
    metrics.keys(),
    metrics.values(),
    width=0.55
)

plt.ylabel("Score", fontsize=12)
plt.ylim(0, 1.08)          

plt.title(
    "Maintenance Prediction Performance Metrics",
    fontsize=18,
    pad=25            
)

plt.grid(axis="y", linestyle="--", alpha=0.5)

for bar in bars:
    value = bar.get_height()

    plt.text(
        bar.get_x() + bar.get_width()/2,
        value + 0.015,
        f"{value:.3f}",
        ha="center",
        va="bottom",
        fontsize=12,
        fontweight="bold"
    )

plt.tight_layout()

plt.savefig(
    os.path.join(
        PLOT_DIR,
        "maintenance_performance_metrics.png"
    ),
    dpi=300,
    bbox_inches="tight"
)

plt.show()

# =========================
# 15. FEATURE IMPORTANCE
# =========================

feature_importance = pd.Series(
    model.feature_importances_,
    index=features
).sort_values()

print("\nFeature Importance:")
print(feature_importance.sort_values(ascending=False))

plt.figure(figsize=(9, 6))
feature_importance.plot(kind="barh")
plt.title("Feature Importance - Maintenance Prediction")
plt.xlabel("Importance Score")
plt.ylabel("Feature")
plt.grid(True)
plt.tight_layout()
plt.savefig(
    os.path.join(PLOT_DIR, "maintenance_feature_importance.png"),
    dpi=300
)
plt.show()


# =========================
# 16. PREDICTION CONFIDENCE DISTRIBUTION
# =========================

confidence_scores = model.predict_proba(X_test).max(axis=1) * 100

plt.figure(figsize=(8, 5))
plt.hist(confidence_scores, bins=20, edgecolor="black")
plt.xlabel("Prediction Confidence (%)")
plt.ylabel("Number of Predictions")
plt.title("Prediction Confidence Distribution")
plt.grid(True)
plt.tight_layout()
plt.savefig(
    os.path.join(PLOT_DIR, "maintenance_confidence_distribution.png"),
    dpi=300
)
plt.show()


# =========================
# 17. SAVE MODEL
# =========================

joblib.dump(
    model,
    os.path.join(MODEL_DIR, "maintenance_model.pkl")
)

print("\nMaintenance model saved successfully.")