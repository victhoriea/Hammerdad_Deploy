import os
import sys
import subprocess
import pymysql

DB = dict(
    host="localhost",
    user="root",
    password="",
    db="hammerdad",
    charset="utf8mb4"
)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPDATE_SCRIPT = os.path.join(BASE_DIR, "update_pms_prediction.py")

conn = pymysql.connect(**DB)

with conn.cursor() as cursor:
    cursor.execute("""
        SELECT DISTINCT plate_no
        FROM repair_transactions
        WHERE type_id = 'PMS'
    """)
    plates = [row[0] for row in cursor.fetchall()]

conn.close()

print(f"Total motorcycles with PMS records: {len(plates)}")

success_count = 0
failed_count = 0

for plate_no in plates:
    print(f"\nUpdating PMS prediction for {plate_no}...")

    result = subprocess.run(
        [sys.executable, UPDATE_SCRIPT, plate_no],
        capture_output=True,
        text=True
    )

    print(result.stdout)

    if result.returncode == 0:
        success_count += 1
    else:
        failed_count += 1
        print(result.stderr)

print("\n=== PMS BATCH PREDICTION SUMMARY ===")
print("Successful:", success_count)
print("Failed:", failed_count)
print("Done.")