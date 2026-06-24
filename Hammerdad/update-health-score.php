<?php
date_default_timezone_set('Asia/Manila');
require_once 'db.php';
require_once 'send-manager-notification.php';
require_once __DIR__ . '/log-notification.php';

function updateHealthScore($plateNo, $sendEmail = true) {
    global $conn;

    $serviceRules = [
        'PMS' => ['max_days' => 31, 'max_penalty' => 20],
        'ES'  => ['max_days' => 75, 'max_penalty' => 15],
        'DT'  => ['max_days' => 45, 'max_penalty' => 10],
        'BS'  => ['max_days' => 45, 'max_penalty' => 10],
        'ELS' => ['max_days' => 75, 'max_penalty' => 10],
        'CSW' => ['max_days' => 40, 'max_penalty' => 10],
        'FF'  => ['max_days' => 35, 'max_penalty' => 15],
        'BE'  => ['max_days' => 75, 'max_penalty' => 10]
    ];

    $totalPenalty = 0;
    $today = new DateTime();

    // PMS penalty
    $pmsStmt = $conn->prepare("
        SELECT MAX(date) AS last_service
        FROM repair_transactions
        WHERE plate_no = ?
          AND type_id = 'PMS'
    ");
    $pmsStmt->bind_param("s", $plateNo);
    $pmsStmt->execute();
    $pmsResult = $pmsStmt->get_result()->fetch_assoc();

    if (!empty($pmsResult['last_service'])) {
        $elapsedDays = $today->diff(new DateTime($pmsResult['last_service']))->days;

        $totalPenalty += min(
            $elapsedDays / $serviceRules['PMS']['max_days'],
            1
        ) * $serviceRules['PMS']['max_penalty'];
    }

    // Repair category penalties
    $repairStmt = $conn->prepare("
        SELECT
            r.repair_type_id,
            MAX(rt.date) AS last_service
        FROM repair_transactions rt
        INNER JOIN transaction_parts tp
            ON rt.transaction_id = tp.transaction_id
        INNER JOIN repair r
            ON tp.repair_id = r.repair_id
        WHERE rt.plate_no = ?
          AND rt.type_id = 'REP'
        GROUP BY r.repair_type_id
    ");
    $repairStmt->bind_param("s", $plateNo);
    $repairStmt->execute();

    $latestServices = [];
    $result = $repairStmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $latestServices[$row['repair_type_id']] = $row['last_service'];
    }

    foreach ($serviceRules as $type => $rule) {
        if ($type === 'PMS') {
            continue;
        }

        if (isset($latestServices[$type])) {
            $elapsedDays = $today->diff(new DateTime($latestServices[$type]))->days;

            $penalty = min(
                $elapsedDays / $rule['max_days'],
                1
            ) * $rule['max_penalty'];
        } else {
            $penalty = 0;
        }

        $totalPenalty += $penalty;
    }

    $health = round(max(0, 100 - $totalPenalty), 2);

    if ($health >= 80) {
        $healthLabel = 'Excellent';
    } elseif ($health >= 60) {
        $healthLabel = 'Good';
    } elseif ($health >= 40) {
        $healthLabel = 'Fair';
    } else {
        $healthLabel = 'Poor';
    }

    // Get current notification flag
    $motorStmt = $conn->prepare("
        SELECT poor_health_notified
        FROM motorcycles
        WHERE plate_no = ?
        LIMIT 1
    ");
    $motorStmt->bind_param("s", $plateNo);
    $motorStmt->execute();
    $motor = $motorStmt->get_result()->fetch_assoc();

    if (!$motor) {
        return false;
    }

    $alreadyNotified = (int)$motor['poor_health_notified'] === 1;

    // Update health score
    $updateStmt = $conn->prepare("
        UPDATE motorcycles
        SET health_score = ?
        WHERE plate_no = ?
    ");
    $updateStmt->bind_param("ds", $health, $plateNo);
    $updateStmt->execute();

    // If health is poor and email was not sent yet
    if ($sendEmail && $healthLabel === 'Poor' && !$alreadyNotified) {
        $subject = "Poor Motorcycle Health Alert - $plateNo";

        $body = "
        <div style='font-family: Arial, sans-serif; color:#333;'>

            <h2 style='color:#b71513;'>
                Motorcycle Health Alert
            </h2>

            <p>Dear Manager,</p>

            <p>
                This is to inform you that one of the motorcycles under your hub has reached a
                <strong style='color:#b71513;'>Poor</strong> health condition based on its service history.
            </p>

            <table cellpadding='6' cellspacing='0' style='border-collapse:collapse;'>
                <tr>
                    <td><strong>Plate Number</strong></td>
                    <td>$plateNo</td>
                </tr>

                <tr>
                    <td><strong>Health Score</strong></td>
                    <td>{$health}%</td>
                </tr>

                <tr>
                    <td><strong>Status</strong></td>
                    <td>$healthLabel</td>
                </tr>

                <tr>
                    <td><strong>Date Generated</strong></td>
                    <td>" . date('F d, Y h:i A') . "</td>
                </tr>
            </table>

            <br>

            <p>
                We recommend scheduling an inspection and performing the necessary maintenance
                as soon as possible to help prevent further deterioration.
            </p>

            <hr>

            <small>
                This is an automated notification generated by the
                <strong>MANAGE MOTO</strong> system.
            </small>

        </div>
        ";

        $mailSent = sendManagerNotification($plateNo, $subject, $body);

        if ($mailSent) {

            // Store notification in database
            $message = "Poor motorcycle health detected ({$health}%).";
            logNotification($plateNo, $message);

            // Prevent duplicate emails
            $notifyStmt = $conn->prepare("
                UPDATE motorcycles
                SET poor_health_notified = 1
                WHERE plate_no = ?
            ");
            $notifyStmt->bind_param("s", $plateNo);
            $notifyStmt->execute();

        } else {
            error_log("Poor health email failed for plate: " . $plateNo);
        }
    }

    // If health improved, reset notification flag
    if ($healthLabel !== 'Poor' && $alreadyNotified) {
        $resetStmt = $conn->prepare("
            UPDATE motorcycles
            SET poor_health_notified = 0
            WHERE plate_no = ?
        ");
        $resetStmt->bind_param("s", $plateNo);
        $resetStmt->execute();
    }

    error_log("DEBUG HEALTH: plate=$plateNo health=$health label=$healthLabel sendEmail=" . ($sendEmail ? 'true' : 'false') . " alreadyNotified=" . ($alreadyNotified ? 'true' : 'false'));

    return [
        'health' => $health,
        'label' => $healthLabel
    ];
}
?>