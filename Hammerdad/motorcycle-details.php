<?php
require_once 'auth.php';
require 'db.php';

$plateNo = $_GET['id'] ?? '';

// Fetch motorcycle details
$stmt = $conn->prepare("
    SELECT 
        m.plate_no, 
        m.model_id, 
        m.health_score,
        h.hub_name,
        h.hub_id,
        mg.first_name,
        mg.last_name,
        mg.email,
        MAX(CASE WHEN rt.type_id = 'PMS' THEN rt.date END) AS last_pms,
        MAX(CASE WHEN rt.type_id = 'REP' THEN rt.date END) AS last_repair
    FROM motorcycles m
    JOIN hubs h ON m.hub_id = h.hub_id
    LEFT JOIN managers mg ON h.manager_id = mg.manager_id
    LEFT JOIN repair_transactions rt ON m.plate_no = rt.plate_no
    WHERE m.plate_no = ?
    GROUP BY m.plate_no, m.model_id, m.health_score, h.hub_name, h.hub_id, mg.first_name, mg.last_name, mg.email
");
$stmt->bind_param('s', $plateNo);
$stmt->execute();
$result = $stmt->get_result();
$motorcycle = $result->fetch_assoc();

if (!$motorcycle) {
    header('Location: motorcycles.php');
    exit;
}

// Fetch prediction for this motorcycle
$predStmt = $conn->prepare("
    SELECT days_until_next_pms, next_pms_date, next_total_cost, last_pms_date, date_created
    FROM predictions_pms
    WHERE plate_no = ?
    LIMIT 1
");
$predStmt->bind_param('s', $plateNo);
$predStmt->execute();
$prediction = $predStmt->get_result()->fetch_assoc();

// Calculate days_left dynamically from next_pms_date vs today
if ($prediction && $prediction['next_pms_date']) {
    $today     = new DateTime('today');
    $next_pms  = new DateTime($prediction['next_pms_date']);
    $diff      = $today->diff($next_pms);
    $days_left = $next_pms >= $today
                 ? (int) $diff->days        // future — positive
                 : -(int) $diff->days;      // past — negative (overdue)
}

// Fetch repair prediction
$repairPredStmt = $conn->prepare("
    SELECT days_until_next_repair, next_repair_date, last_repair_date, date_created
    FROM predictions_repair
    WHERE plate_no = ?
    LIMIT 1
");
$repairPredStmt->bind_param('s', $plateNo);
$repairPredStmt->execute();
$repairPrediction = $repairPredStmt->get_result()->fetch_assoc();

// Calculate days_left dynamically
if ($repairPrediction && $repairPrediction['next_repair_date']) {
    $today            = new DateTime('today');
    $next_repair      = new DateTime($repairPrediction['next_repair_date']);
    $diff             = $today->diff($next_repair);
    $repair_days_left = $next_repair >= $today
                        ? (int) $diff->days
                        : -(int) $diff->days;
}

// Fetch transaction history
$txnStmt = $conn->prepare("
    SELECT rt.date, rt.type_id, m.first_name, m.last_name
    FROM repair_transactions rt
    LEFT JOIN mechanics m ON rt.mechanic_id = m.mechanic_id
    WHERE rt.plate_no = ?
    ORDER BY rt.date DESC
");
$txnStmt->bind_param('s', $plateNo);
$txnStmt->execute();
$transactions = $txnStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$health = isset($motorcycle['health_score'])
    ? round((float)$motorcycle['health_score'], 2)
    : 100;

if ($health >= 80) {
    $healthColor = '#2a7a2a'; // green
    $healthLabel = 'Excellent';
} elseif ($health >= 60) {
    $healthColor = '#2a7a2a'; // green
    $healthLabel = 'Good';
} elseif ($health >= 40) {
    $healthColor = '#e67e22'; // orange
    $healthLabel = 'Fair';
} else {
    $healthColor = '#b71513'; // red
    $healthLabel = 'Poor';
}

$pageTitle = $motorcycle['plate_no'] . " Details"; 
$pageName_sc = $motorcycle['plate_no'];
$pageName_lc = "motorcycle";

$headerBtnClass = "back-btn";
$headerBtnAction = "history.back()";
$headerBtnIcon = "く";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Motorcycle Details | Hammerdad</title>

    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=delete,edit,filter_alt,visibility"/>
    
    <style>
        body {
            font-family: Tahoma, sans-serif; 
            background-color: #d9d9d9;
            display: flex;
            flex-direction: column;
            color: #212328;
        } 

        .main-panel {
            height: 1190px;
        }

        .section-divider {
            border: none;
            height: 1px;
            background-color: #d1d1d1;
            width: 100%;
            margin: 15px auto 10px auto;
            flex-shrink: 0;
        }
        
        .motorcycle-table { max-height: 500px; width: 100%; border-collapse: collapse; text-align: center; }
        .motorcycle-table thead th {
            font-weight: bold; letter-spacing: .9px; text-transform: uppercase; background-color: #b71513;
            color: #fff; padding: 7px; border-bottom: 1px solid #e2e2e2; white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .motorcycle-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .motorcycle-table tbody tr:last-child { border-bottom: none; }
        .motorcycle-table tbody tr:hover { background: #f7f7f5; }
        .motorcycle-table tbody td  { padding: 7px; color: #3d3d3d; vertical-align: middle; }

        .material-symbols-outlined {
            font-size: 26px;
            font-variation-settings:
            'FILL' 0,
            'wght' 300,
            'GRAD' 0,
            'opsz' 24
        }

        .motorcycle-table .material-symbols-outlined {
            font-variation-settings:
            'FILL' 0,
            'wght' 200,
            'GRAD' 0,
            'opsz' 24
        }

        .actions-wrapper {
            display: flex;
            flex-direction: row;
            margin-left: auto;
            margin-right: 10px;
            gap: 5px;
        }

        .edit-container {
            position: relative;
            display: inline-block;
            overflow: visible;
        }

        .action-btn {
            border: none;
            background-color: #ffffff00;
            color: #a4a4a4;
            display: flex;
            flex-direction: column;
            justify-self: center;
            align-self: flex-start;
            padding: 5px 5px;
        }

        .action-btn:hover {
            color: #1e1f22;
            cursor: pointer;
        }

        .view-btn {
            border: none;
            background-color: #ffffff00;
            color: #44444490;
            display: flex;
            flex-direction: column;
            justify-self: center;
            align-self: flex-end;
        }

        .view-btn:hover {
            color: #1e1f22;
            cursor: pointer;
        }

        .table-actions-wrapper {         
            margin-left: auto;
            margin-top: 10px;
        }

        .table-actions-wrapper form {
            display: flex;
            align-items: center;  
            margin-left: auto;
            gap: 8px;
        }

        .main-panel input[type="month"] {
            width: 215px;
            height: 35px;
            padding: 0 10px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
        }

        .filter-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 10px;          
            width: 230px;
            height: 35px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
        }

        .filter-wrapper .material-symbols-outlined {
            font-variation-settings:
            'FILL' 1,
            'wght' 400,
            'GRAD' 0,
            'opsz' 24
        }

        .checkbox-wrapper {
            display: flex;
            padding: 0;
        }

        .checkbox-wrapper input {
            margin-left: 20px;
            transform: scale(1.3);
        }

        .table-actions-wrapper input[type="submit"] {
            color: #212328;
            height: 35px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            vertical-align: middle;
            padding: 0 20px;
        }

        .table-actions-wrapper input[type="submit"]:active {
            background-color: #dcdcdc;
        }

        .motorcycle-details {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            gap: 10px;
            font-size: 20px; 
            margin: 20px 0 25px;
            color: #212328;
        }

        .details-block-1 {
            display: flex;
            flex-direction: column;
            margin-left: 20px;
        }

        .details-block-2 {
            display: flex;
            flex-direction: row;
            gap: 10px;
        }

        .heading-block {
            display: flex;
            flex-direction: row;
            align-items: baseline;
            gap: 10px;
        }

        .plate-num {
            font-size: 35px;
            font-weight: bold;
        }

        .model {
            font-size: 17px;
        }

        .status {
            align-self: flex-start;
            width: 120px;
            height: 20px;
            padding: 7px 0;
            margin-left: 100px;
            border-style: solid;
            border-radius: 5px;
            border-width: 1px;
            text-align: center;
            vertical-align: middle;
            font-size: 16px;
            letter-spacing: 1px;
            zoom: 0.75;
        }

        .in-progress {
            background-color: #FFF4C5;
            color: #c6b059;
        }

        .in-progress::before {
            content: "In Progress";
        }

        .ready {
            background-color: #ddffcf;
            color: #73b35a;
        }

        .ready::before {
            content: "Ready";
        }

        .offline {
            background-color: #ebebeb;
            color: #9e9e9e;
        }

        .offline::before {
            content: "Offline";
        }

        .motorcycle-details p {
            margin: 0;
            padding-bottom: 10px;
        }

        .info-wrapper-1 {
            display: flex;
            flex-direction: column;
            text-align: left;
            font-weight: bold;
            padding-top: 8px;
        }

        .info-wrapper-2 {
            display: flex;
            flex-direction: column;
            text-align: left;
            padding-top: 8px;
        }   

        .addmotor-popup-box {
            display: none;
            position: absolute;
            background: #f8f8f8;
            width: 310px;
            padding: 10px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 10px;
            right: 0;              
            z-index: 9999;
        }

        .addmotor-popup-box.show {
            display: block;
        }

        .addmotor-wrapper {
            background-color: #fff;
            font-size: 16px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
        }

        .addmotor-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-2 {
            font-size: 25px;
            font-weight: bold;
            color: #b71513;
            margin: 0;
            padding: 5px 0 10px 20px;
        }

        .x-btn {
            font-size: 22px;
            color: #b0b0b0;
            border: none;
            padding: 0 2px;
            margin-right: 8px;
            margin-bottom: auto;
        }

        .x-btn:hover {
            color: #575757;
        }

        .addmotor-popup-box input, select {
            width: 170px;
            box-sizing: border-box;
            height: 35px;
            padding: 2px 15px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin: 2px 0 7px 0;
        }

        .addmotor-popup-box select:invalid {
            color: #B3B3B3;
        }

        .addmotor-btn-row {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .addmotor-popup-btn {
            width: 35%;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .addmotor-save-btn { background-color: #b71513; color: white; }
        .addmotor-save-btn:hover { background-color: #d31512; }
        .addmotor-cancel-btn { background-color: #CBCBCB; color: #000; }
        .addmotor-cancel-btn:hover { background-color: rgb(189, 189, 189); }

        .condition-block {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            align-items: center;
            margin: 15px 0;
            background-color: #f8f8f8;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 50px;
            padding: 0 30px;
            gap: 10px;
        }

        .condition-block p {
            text-align: left;
            font-weight: bold;
        }
        
        .condition-bar {
            width: 770px;
            height: 10px;
            border-radius: 50px;
            background-color: #ddd;
        }

        .condition-bar-progress {
            height: 100%;
            border-radius: 50px;
            background-color: #b71513;
        }

        .grade-block {
            flex: 1;
            display: flex;
        }
    </style>
    
</head>

<body>

    <!-- ESSENTIALS -->

    <div class="loader-wrapper">
        <div class="loader"></div>
    </div>

    <?php include 'page-essentials.php'; ?>

    <!-- MAIN BODY -->

    <div class="main-panel">
        <div class="motorcycle-details">

            <img src="images/motorcycle-profile.png" style="height: 125px; border-style: solid; border-color: #d1d1d1; border-width: 1px; border-radius: 100px; margin: 0 0 0 10px;">

            <div class="details-block-1">
                <div class="heading-block">
                    <p class="plate-num"><?= htmlspecialchars($motorcycle['plate_no']) ?></p>
                    <p class="model"><?= htmlspecialchars($motorcycle['model_id']) ?></p>
                </div>

                <div class="details-block-2">
                    <div class="info-wrapper-1">
                        <p>Hub</p>
                        <p>Manager</p>
                    </div>

                    <div class="info-wrapper-1">
                        <p>:</p>
                        <p>:</p>
                    </div>

                    <div class="info-wrapper-2">
                        <p><?= htmlspecialchars($motorcycle['hub_name']) ?></p>
                        <p><?= htmlspecialchars($motorcycle['first_name'] . ' ' . $motorcycle['last_name'] 
                        . ' (' . ($motorcycle['email'] ?? '—') . ')') ?></p>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="actions-wrapper">
                <div class="edit-container">
                    <button class="action-btn" onclick="openEditMotorPopup()">
                        <span class="material-symbols-outlined">edit</span>
                    </button>

                    <div class="addmotor-popup-box" id="EditMotorPopup" onclick="event.stopPropagation()">
                        <div class="header-row">
                            <p class="header-2">Edit Motor</p>
                            <button class="x-btn" onclick="closeEditMotorPopup()">✕</button>
                        </div>

                        <div class="addmotor-wrapper">
                            <form id="editMotorForm">
                                <div class="addmotor-row">
                                    <label>Plate no.</label>
                                    <input disabled id="editPlateNo" value="<?= htmlspecialchars($motorcycle['plate_no']) ?>">
                                </div>
                            
                                <div class="addmotor-row">
                                    <label>Hub</label>
                                    <select name="hub_id" id="editHubId" required>
                                        <?php
                                        $hubs = $conn->query("SELECT hub_id, hub_name FROM hubs");
                                        while($row = $hubs->fetch_assoc()):
                                        ?>
                                        <option value="<?= htmlspecialchars($row['hub_id']) ?>"
                                            <?= $motorcycle['hub_id'] == $row['hub_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($row['hub_name']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </form>

                            <div class="addmotor-btn-row">
                                <button class="addmotor-popup-btn addmotor-cancel-btn" onclick="closeEditMotorPopup()">Cancel</button>
                                <button id="editMotorBtn" onclick="saveMotorEdit()" class="addmotor-popup-btn addmotor-save-btn">Save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="action-btn" onclick="openDelPopup('<?= $plateNo ?>', 'delete-motorcycle.php', 'Motorcycle', 'motorcycles.php')">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($prediction): ?>
        <?php
            $is_overdue = $days_left < 0;
            $days_abs   = abs($days_left);

            if ($is_overdue) {
                $bg_color   = '#6b6b6b';   // gray — overdue
                $badge_text = 'OVERDUE';
                $days_label = $days_abs . ' day' . ($days_abs != 1 ? 's' : '') . ' ago';
                $status_color = '#6b6b6b';
                $status_text  = 'PMS overdue';
            } elseif ($days_left === 0) {
                $bg_color   = '#b71513';   // red — due today
                $badge_text = 'TODAY';
                $days_label = 'Due today';
                $status_color = '#b71513';
                $status_text  = 'PMS due today!';
            } elseif ($days_left <= 7) {
                $bg_color   = '#b71513';   // red — very soon
                $badge_text = null;
                $days_label = $days_left . ' day' . ($days_left != 1 ? 's' : '');
                $status_color = '#b71513';
                $status_text  = 'PMS due very soon';
            } elseif ($days_left <= 30) {
                $bg_color   = '#e67e22';   // orange — coming up
                $badge_text = null;
                $days_label = $days_left . ' day' . ($days_left != 1 ? 's' : '');
                $status_color = '#e67e22';
                $status_text  = 'PMS coming up';
            } else {
                $bg_color   = '#2a7a2a';   // green — fine
                $badge_text = null;
                $days_label = $days_left . ' day' . ($days_left != 1 ? 's' : '');
                $status_color = '#2a7a2a';
                $status_text  = 'PMS on track';
            }
        ?>
        <div style="
            display:flex; flex-direction:row; align-items:center; gap:20px;
            background:#fff; border:1px solid #d1d1d1; border-radius:8px;
            padding:14px 20px; margin:10px 0;
        ">
            <!-- Days badge -->
            <div style="
                display:flex; flex-direction:column; align-items:center;
                justify-content:center; background:<?= $bg_color ?>;
                color:#fff; border-radius:8px; width:90px; height:75px;
                font-weight:bold; text-align:center; flex-shrink:0;
                <?= $is_overdue ? 'opacity:0.75;' : '' ?>
            ">
                <?php if ($badge_text): ?>
                    <span style="font-size:15px;line-height:1;letter-spacing:1px;"><?= $badge_text ?></span>
                    <?php if ($is_overdue): ?>
                    <span style="font-size:11px;margin-top:4px;font-weight:normal;"><?= $days_abs ?> day<?= $days_abs != 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="font-size:24px;line-height:1;"><?= $days_left ?></span>
                    <span style="font-size:11px;margin-top:2px;font-weight:normal;">days</span>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="display:flex;flex-direction:column;gap:5px;">
                <p style="margin:0;font-weight:bold;font-size:15px;color:<?= $status_color ?>;">
                    <?= $status_text ?>
                </p>
                <p style="margin:0;font-size:13px;color:#555;">
                    <?= $is_overdue ? 'Was due' : 'Predicted date' ?>:
                    <strong><?= date('M j, Y', strtotime($prediction['next_pms_date'])) ?></strong>
                </p>
                <p style="margin:0;font-size:13px;color:#555;">
                    Estimated cost: <strong>₱<?= number_format($prediction['next_total_cost'], 2) ?></strong>
                </p>
            </div>

            <!-- Meta -->
            <div style="margin-left:auto;text-align:right;">
                <p style="margin:0;font-size:11px;color:#aaa;">
                    Last PMS: <?= $prediction['last_pms_date'] ? date('m/d/y', strtotime($prediction['last_pms_date'])) : '—' ?>
                </p>
                <p style="margin:0;font-size:11px;color:#aaa;">
                    Updated: <?= date('m/d/y', strtotime($prediction['date_created'])) ?>
                </p>
            </div>
        </div>
        <?php else: ?>
        <div style="background:#f8f8f8;border:1px solid #ddd;border-radius:8px;padding:12px 20px;margin:10px 0;color:#aaa;font-size:13px;">
            No PMS prediction available yet.
        </div>
        <?php endif; ?>

        <?php if ($repairPrediction): ?>
        <?php
            $r_is_overdue = $repair_days_left < 0;
            $r_days_abs   = abs($repair_days_left);

            if ($r_is_overdue) {
                $r_bg_color     = '#6b6b6b';
                $r_badge_text   = 'OVERDUE';
                $r_status_color = '#6b6b6b';
                $r_status_text  = 'Repair overdue';
            } elseif ($repair_days_left === 0) {
                $r_bg_color     = '#b71513';
                $r_badge_text   = 'TODAY';
                $r_status_color = '#b71513';
                $r_status_text  = 'Repair due today!';
            } elseif ($repair_days_left <= 7) {
                $r_bg_color     = '#b71513';
                $r_badge_text   = null;
                $r_status_color = '#b71513';
                $r_status_text  = 'Repair due very soon';
            } elseif ($repair_days_left <= 30) {
                $r_bg_color     = '#e67e22';
                $r_badge_text   = null;
                $r_status_color = '#e67e22';
                $r_status_text  = 'Repair coming up';
            } else {
                $r_bg_color     = '#2a7a2a';
                $r_badge_text   = null;
                $r_status_color = '#2a7a2a';
                $r_status_text  = 'Repair on track';
            }
        ?>
        <div style="
            display:flex; flex-direction:row; align-items:center; gap:20px;
            background:#fff; border:1px solid #d1d1d1; border-radius:8px;
            padding:14px 20px; margin:10px 0;
        ">
            <!-- Days badge -->
            <div style="
                display:flex; flex-direction:column; align-items:center;
                justify-content:center; background:<?= $r_bg_color ?>;
                color:#fff; border-radius:8px; width:90px; height:75px;
                font-weight:bold; text-align:center; flex-shrink:0;
                <?= $r_is_overdue ? 'opacity:0.75;' : '' ?>
            ">
                <?php if ($r_badge_text): ?>
                    <span style="font-size:15px;line-height:1;letter-spacing:1px;"><?= $r_badge_text ?></span>
                    <?php if ($r_is_overdue): ?>
                    <span style="font-size:11px;margin-top:4px;font-weight:normal;">
                        <?= $r_days_abs ?> day<?= $r_days_abs != 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="font-size:24px;line-height:1;"><?= $repair_days_left ?></span>
                    <span style="font-size:11px;margin-top:2px;font-weight:normal;">days</span>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="display:flex;flex-direction:column;gap:5px;">
                <p style="margin:0;font-weight:bold;font-size:15px;color:<?= $r_status_color ?>;">
                    <?= $r_status_text ?>
                </p>
                <p style="margin:0;font-size:13px;color:#555;">
                    <?= $r_is_overdue ? 'Was due' : 'Predicted date' ?>:
                    <strong><?= date('M j, Y', strtotime($repairPrediction['next_repair_date'])) ?></strong>
                </p>
                <p style="margin:0;font-size:13px;color:#555;">
                    Last repair:
                    <strong>
                        <?= $repairPrediction['last_repair_date']
                            ? date('M j, Y', strtotime($repairPrediction['last_repair_date']))
                            : '—' ?>
                    </strong>
                </p>
            </div>

            <!-- Meta -->
            <div style="margin-left:auto;text-align:right;">
                <p style="margin:0;font-size:12px;color:#999;font-weight:bold;">NEXT REPAIR</p>
                <p style="margin:0;font-size:11px;color:#aaa;margin-top:4px;">
                    Updated: <?= date('m/d/y', strtotime($repairPrediction['date_created'])) ?>
                </p>
            </div>
        </div>
        <?php else: ?>
        <div style="background:#f8f8f8;border:1px solid #ddd;border-radius:8px;padding:12px 20px;margin:10px 0;color:#aaa;font-size:13px;">
            No repair prediction available yet.
        </div>
        <?php endif; ?>

        <div class="condition-block">
            <p>Condition:</p>
            <div class="condition-bar">
                <div class="condition-bar-progress" style="width: <?= $health ?>%; background-color: <?= $healthColor ?>;"></div>
            </div>
            <div class="grade-block">
                <p><?= $health ?>%</p>
                <hr style="border: none; width: 30px; height: 1px; background-color: #d1d1d1; margin-top: 25.5px; transform: rotate(90deg);">
                <p><?= $healthLabel ?></p>
            </div>
        </div>

        <hr class="section-divider">

        <div class="table-actions-wrapper">
            <form method="GET" action="">
                <input type="hidden" name="id" value="<?= htmlspecialchars($plateNo) ?>">
                <input type="month" name="month" value="<?= $_GET['month'] ?? '' ?>">

                <div class="filter-wrapper">
                    <div style="display: flex; align-items: center;">
                        <span class="material-symbols-outlined">filter_alt</span>
                        <p>Filter</p>
                    </div>
                    <div class="checkbox-wrapper">
                        <div>
                            <input type="checkbox" id="pms-filter" name="type[]" value="PMS" <?= isset($_GET['type']) && in_array('PMS', $_GET['type']) ? 'checked' : '' ?>>
                            <label for="pms-filter">PMS</label>
                        </div>
                        <div>
                            <input type="checkbox" id="repair-filter" name="type[]" value="REP" <?= isset($_GET['type']) && in_array('REP', $_GET['type']) ? 'checked' : '' ?>>
                            <label for="repair-filter">Repair</label>
                        </div>
                    </div>
                </div>

                <input type="submit" value="Apply">
            </form>
        </div>

        <?php
        // Build filtered transaction query
        $where = ["rt.plate_no = ?"];
        $params = [$plateNo];
        $types = 's';

        if (!empty($_GET['month'])) {
            $where[] = "DATE_FORMAT(rt.date, '%Y-%m') = ?";
            $params[] = $_GET['month'];
            $types .= 's';
        }

        if (!empty($_GET['type'])) {
            $placeholders = implode(',', array_fill(0, count($_GET['type']), '?'));
            $where[] = "rt.type_id IN ($placeholders)";
            foreach ($_GET['type'] as $t) {
                $params[] = $t;
                $types .= 's';
            }
        }

        $whereClause = implode(' AND ', $where);
        $txnFiltered = $conn->prepare("
            SELECT rt.transaction_id, rt.date, rt.type_id, m.first_name, m.last_name
            FROM repair_transactions rt
            LEFT JOIN mechanics m ON rt.mechanic_id = m.mechanic_id
            WHERE $whereClause
            ORDER BY rt.date DESC
        ");
        $txnFiltered->bind_param($types, ...$params);
        $txnFiltered->execute();
        $filteredTxns = $txnFiltered->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>

        <div style="overflow-y:auto; margin-top: 15px;">
            <table class="motorcycle-table" style="padding: 0 50px;">
                <thead>
                    <tr>
                        <th style="width:205px;"><button class="sort-btn white"id="sortBtn" onclick="toggleSort()">
                            🡱</button>
                            DATE
                        </th>
                        <th>REPAIR TYPE</th>
                        <th>MECHANIC</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="mDetailsTableBody">
                    <?php if (empty($filteredTxns)): ?>
                    <tr>
                        <td colspan="4">No transactions found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($filteredTxns as $txn): ?>
                    <tr>
                        <td><?= date('m/d/y', strtotime($txn['date'])) ?></td>
                        <td><?= htmlspecialchars($txn['type_id']) ?></td>
                        <td><?= $txn['first_name'] ? htmlspecialchars($txn['first_name'] . ' ' . $txn['last_name']) : '—' ?></td>
                        <td>
                            <button class="view-btn" onclick="window.location.href='transac-details.php?id=<?= urlencode($txn['transaction_id']) ?>'">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="layout.js"></script>

    <script>
        function openEditMotorPopup() {
            document.getElementById("EditMotorPopup").classList.toggle("show");
            clearPopupMessage("editMotorMessage");
        }

        function closeEditMotorPopup() {
            document.getElementById("EditMotorPopup").classList.remove("show");
            clearPopupMessage("editMotorMessage");
        }

        function saveMotorEdit() {
            const plateNo = document.getElementById("editPlateNo").value.trim();
            const hub     = document.getElementById("editHubId").value;

            if (!hub) {
                showPopupMessage("editMotorMessage", "Please fill in field.", "error");
                return;
            }

            const saveBtn = document.getElementById("editMotorBtn");
            setButtonLoading(saveBtn, true, "Saving...");

            fetch('update-motorcycle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plate_no: plateNo, model_id: model, hub_id: hub })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showPopupMessage("editMotorMessage", "Changes saved!", "success");
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showPopupMessage("editMotorMessage", data.message, "error");
                }
            })
            .catch(() => showPopupMessage("editMotorMessage", "Something went wrong.", "error"))
            .finally(() => setButtonLoading(saveBtn, false, "Save"));
        }

        function showPopupMessage(id, msg, type) {
            let el = document.getElementById(id);
            if (!el) {
                el = document.createElement("p");
                el.id = id;
                el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
                const btnRow = document.querySelector("#EditMotorPopup .addmotor-btn-row");
                if (btnRow) btnRow.parentNode.insertBefore(el, btnRow);
            }
            el.textContent = msg;
            el.style.color = type === "success" ? "#2e7d32" : "#c62828";
        }

        function clearPopupMessage(id) {
            const el = document.getElementById(id);
            if (el) el.remove();
        }

        function setButtonLoading(btn, isLoading, label) {
            if (!btn) return;
            btn.disabled = isLoading;
            btn.textContent = label;
        }

        let sortAsc = true;

        function toggleSort() {
            sortAsc = !sortAsc;
            document.getElementById("sortBtn").textContent = sortAsc ? "🡱" : "🡳";
            applyFilters();
        }

        function applyFilters() {
            const tbody = document.getElementById("mDetailsTableBody");
            const allRows = Array.from(tbody.querySelectorAll("tr"));

            allRows.sort((a, b) => {
                const aVal = a.cells[0]?.textContent.trim() ?? '';
                const bVal = b.cells[0]?.textContent.trim() ?? '';
                return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });

            allRows.forEach(row => tbody.appendChild(row));
        }
    </script>

</body>
</html>