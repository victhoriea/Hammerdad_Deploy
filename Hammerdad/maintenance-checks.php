<?php 
require_once 'auth.php';
$pageTitle = "Maintenance Check";
$pageName_sc = "Record";
$pageName_lc = "record";

$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

require 'db.php';

// Fetch maintenance checks with full details
$result = $conn->query("
    SELECT 
        mc.check_no,
        mc.plate_no,
        mc.check_date,
        mc.mileage,
        mc.no_issues,
        mc.fuel_type_id,
        mc.transmission_type_id,
        ft.type_name AS fuel_type_name,
        tt.transmission_type AS transmission_type_name,
        m.model_id,
        h.hub_name,
        cb.condition_name AS battery_condition,
        ct.condition_name AS tire_condition,
        cbr.condition_name AS brake_condition
    FROM maintenance_check mc
    LEFT JOIN motorcycles m ON mc.plate_no = m.plate_no
    LEFT JOIN hubs h ON m.hub_id = h.hub_id
    LEFT JOIN fuel_type ft ON mc.fuel_type_id = ft.fuel_type_id
    LEFT JOIN transmission_type tt ON mc.transmission_type_id = tt.transmission_type_id
    LEFT JOIN check_parts cp_battery ON mc.check_no = cp_battery.check_no AND cp_battery.parts_id = 'BTY'
    LEFT JOIN conditions cb ON cp_battery.condition_id = cb.condition_id
    LEFT JOIN check_parts cp_tire ON mc.check_no = cp_tire.check_no AND cp_tire.parts_id = 'TRE'
    LEFT JOIN conditions ct ON cp_tire.condition_id = ct.condition_id
    LEFT JOIN check_parts cp_brake ON mc.check_no = cp_brake.check_no AND cp_brake.parts_id = 'BRK'
    LEFT JOIN conditions cbr ON cp_brake.condition_id = cbr.condition_id
    ORDER BY mc.check_date DESC
");
$result = $result->fetch_all(MYSQLI_ASSOC);

// Dropdown queries
$plates      = $conn->query("SELECT plate_no FROM motorcycles ORDER BY plate_no ASC")->fetch_all(MYSQLI_ASSOC);
$fuel_types  = $conn->query("SELECT fuel_type_id, type_name FROM fuel_type ORDER BY fuel_type_id ASC")->fetch_all(MYSQLI_ASSOC);
$trans_types = $conn->query("SELECT transmission_type_id, transmission_type FROM transmission_type ORDER BY transmission_type_id ASC")->fetch_all(MYSQLI_ASSOC);
$conditions  = $conn->query("SELECT condition_id, condition_name FROM conditions ORDER BY condition_id ASC")->fetch_all(MYSQLI_ASSOC);
// Fetch maintenance predictions per check_no
$maintPredRes = $conn->query("
    SELECT check_no, plate_no, needs_maintenance, confidence, prediction_date, date_created
    FROM predictions_maintenance
");

$maintPredictions = [];

while ($row = $maintPredRes->fetch_assoc()) {
    $maintPredictions[$row['check_no']] = $row;
}

$maintPredictionsJson = json_encode($maintPredictions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Maintenance Check | Hammerdad</title>

    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=delete,visibility"/>
    
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #d9d9d9; display: flex; flex-direction: column; color: #212328; }

        .main-panel { min-height: unset; height: 520px; margin-top: 0; }
        .upper-panel { position: relative; z-index: 10; }

        .mcheckcycle-table { width: 100%; border-collapse: collapse; text-align: center;}
        .mcheckcycle-table thead th {
            font-weight: 700; letter-spacing: .9px; text-transform: uppercase; background-color: #fff;
            color: #b71513; padding: 0 10px 10px; border-bottom: 1px solid #e2e2e2;  white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .mcheckcycle-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .mcheckcycle-table tbody tr:last-child { border-bottom: none; }
        .mcheckcycle-table tbody tr:hover { background: #f7f7f5; }
        .mcheckcycle-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }

        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 200, 'GRAD' 0, 'opsz' 24; }

        .view-btn { border: none; background-color: transparent; color: #44444490; display: flex; flex-direction: column; justify-self: center; align-self: flex-end; }
        .view-btn:hover { color: #1e1f22; cursor: pointer; }

        .view-container { position: relative; display: inline-block; overflow: visible; }

        .addmcheck-popup-box { display: none; position: absolute; background: #f8f8f8; width: 340px; padding: 10px; border: 1px solid #DDDDDD; border-radius: 10px; top: calc(100% + 10px); right: 0; z-index: 40; }
        .addmcheck-popup-box.show { display: block; }

        .addmcheck-wrapper { background-color: #fff; border: 1px solid #DDDDDD; border-radius: 7px; padding: 20px; }
        .addmcheck-wrapper form { display: flex; flex-direction: column; gap: 3px; }
        .addmcheck-row { display: flex; justify-content: space-between; align-items: center; }

        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .header-2 { font-size: 25px; font-weight: bold; color: #b71513; margin: 0; padding: 5px 0 10px 20px; }

        .addmcheck-popup-box input, .addmcheck-popup-box select { width: 170px; box-sizing: border-box; height: 30px; padding: 2px 10px; border: 1px solid #DDDDDD; border-radius: 7px; margin: 3px 0; }
        .addmcheck-popup-box select:disabled { color: #B3B3B3; }

        .addmcheck-btn-row { display: flex; flex-direction: row; justify-content: center; gap: 8px; margin-top: 12px; }
        .addmcheck-popup-btn { width: 35%; height: 35px; border-radius: 5px; border: none; cursor: pointer; }
        .addmcheck-save-btn { background-color: #b71513; color: white; }
        .addmcheck-save-btn:hover { background-color: #d31512; }
        .addmcheck-cancel-btn { background-color: #CBCBCB; color: #000; }
        .addmcheck-cancel-btn:hover { background-color: rgb(189, 189, 189); }

        .section-divider { border: none; height: 0.5px; background-color: #DDDDDD; width: 100%; margin: 5px auto; flex-shrink: 0; }
        .section-label { margin: 0; padding-top: 5px; color: #757575; zoom: 0.85; }

        /* DETAILS POPUP */
        .viewdetails-popup-box { display: none; background: #f8f8f8; width: 600px; padding: 10px; border: 1px solid #DDDDDD; border-radius: 10px; text-align: center; z-index: 100; position: fixed; }
        .viewdetails-popup-box.show { display: block; }
        .viewdetails-wrapper { background-color: #fff; border: 1px solid #DDDDDD; border-radius: 7px; padding: 20px; display: flex; flex-direction: column; }

        .details { display: flex; flex-direction: row; gap: 10px; color: #212328; }
        .transac { justify-content: flex-start; }
        .plate-no { font-size: 30px; font-family: Tahoma, sans-serif; font-weight: bold; color: #212328; background:none; border:none; margin:0;}
        .plate-no:hover { cursor:pointer; }
        .details p { margin: 0; padding-bottom: 10px; }

        .actions-wrapper { display: flex; flex-direction: row; margin-left: auto; margin-right: 10px; gap: 3px; }
        .action-btn { border: none; background-color: transparent; color: #a4a4a4; display: flex; flex-direction: column; justify-self: center; align-self: flex-start; padding: 5px; cursor: pointer; }
        .action-btn:hover { color: #b71513; }

        .info-wrapper-1 { display: flex; flex-direction: column; text-align: left; font-weight: bold; padding-top: 8px; }
        .info-wrapper-2 { display: flex; flex-direction: column; text-align: left; padding-top: 8px; }
        .wrap-separator { width: 40px; }

        .kpi-wrapper { display: flex; width: 100%; gap: 20px; }
        .kpi-wrapper > div:first-child { flex-shrink: 0; }
        .kpi-wrapper > div:last-child { flex: 1; }
        .section-title { font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; margin: 10px 0 8px; }
        .kpi-strip { display: grid; grid-template-columns: repeat(3,1fr); gap: 15px; }
        .kpi-card { display: flex; flex-direction: column; justify-content: center; align-items: center; background: #fff; height: 80px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.04); padding: 5px; border-top: 3px solid transparent; gap: 7px; }
        .kpi-card.accent-red   { border-top-color: #b71513; }
        .kpi-card.accent-green { border-top-color: #1a7f4b; }
        .kpi-card.accent-yellow { border-top-color: #c47a0f; }
        .kpi-value { font-size: 20px; font-weight: 200; color: #1a1a1a; margin: 0; }
        .kpi-issue-num { font-size: 30px; font-weight: 200; color: #1a1a1a; margin: 0; letter-spacing: -1px; }
        .kpi-sub { margin: 0; }
        .kpi-badge { display: inline-block; font-size: 18px; font-weight: 700; padding: 5px 10px; border-radius: 20px; }
        .kpi-badge.red    { background: #fceaea; color: #b71513; }
        .kpi-badge.green  { background: #e6f4ee; color: #1a7f4b; }
        .kpi-badge.yellow { background: #f5fad3; color: #c47a0f; }
    </style>
</head>
<body>

<div class="loader-wrapper"><div class="loader"></div></div>
<?php include 'page-essentials.php'; ?>

<div class="upper-panel">
    <search>
        <form class="search-wrapper" onsubmit="return false;">
            <input class="search-input" type="text" id="searchInput" placeholder="Search Plate Number" oninput="filterTable()">
            <button class="x-btn" type="reset" onclick="setTimeout(filterTable, 0)">✕</button>
            <button class="search-btn" onclick="filterTable()">🔍︎</button>
        </form>
    </search>

    <div class="view-container">
        <button class="add-btn" onclick="openAddMCheckPopup()">+ Add Record</button>
    
        <div class="addmcheck-popup-box" id="AddMCheckPopup" onclick="event.stopPropagation()">
            <div class="header-row">
                <p class="header-2">Add Record</p>
                <button class="x-btn" onclick="closeAddMCheckPopup()">✕</button>
            </div>
            <div class="addmcheck-wrapper">
                <form id="addMCheckForm">
                    <div class="addmcheck-row">
                        <label>Date</label>
                        <input type="date" id="checkDate" required>
                    </div>
                    <div class="addmcheck-row">
                        <label>Plate no.</label>
                        <input required type="text" name="plate_no" id="checkPlateNo"
                            list="plateList" placeholder="ex. ABC123" autocomplete="off">
                        <datalist id="plateList">
                            <?php foreach ($plates as $row): ?>
                                <option value="<?= htmlspecialchars($row['plate_no']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="addmcheck-row">
                        <label>Mileage</label>
                        <input type="number" id="checkMileage" placeholder="ex. 10500">
                    </div>
                    <div class="addmcheck-row">
                        <label>No. of Issues</label>
                        <input type="number" id="checkNoIssues" min="0" value="0" placeholder="0">
                    </div>
                    <div class="addmcheck-row">
                        <label>Fuel</label>
                        <select id="checkFuelType" required>
                            <option value="" disabled selected>-- Type --</option>
                            <?php foreach ($fuel_types as $row): ?>
                            <option value="<?= htmlspecialchars($row['fuel_type_id']) ?>"><?= htmlspecialchars($row['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="addmcheck-row">
                        <label>Transmission</label>
                        <select id="checkTransType" required>
                            <option value="" disabled selected>-- Type --</option>
                            <?php foreach ($trans_types as $row): ?>
                            <option value="<?= htmlspecialchars($row['transmission_type_id']) ?>"><?= htmlspecialchars($row['transmission_type']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="section-label">PART CONDITIONS</p>
                    <div class="addmcheck-row">
                        <label>Battery</label>
                        <select id="checkBattery" required>
                            <option value="" disabled selected>-- Condition --</option>
                            <?php foreach ($conditions as $row): ?>
                                <?php if (in_array($row['condition_id'], ['NW', 'GD', 'WK'])): ?>
                                <option value="<?= htmlspecialchars($row['condition_id']) ?>"><?= htmlspecialchars($row['condition_name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="addmcheck-row">
                        <label>Tire</label>
                        <select id="checkTire" required>
                            <option value="" disabled selected>-- Condition --</option>
                            <?php foreach ($conditions as $row): ?>
                                <?php if (in_array($row['condition_id'], ['NW', 'GD', 'WO'])): ?>
                                <option value="<?= htmlspecialchars($row['condition_id']) ?>"><?= htmlspecialchars($row['condition_name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="addmcheck-row">
                        <label>Brake</label>
                        <select id="checkBrake" required>
                            <option value="" disabled selected>-- Condition --</option>
                            <?php foreach ($conditions as $row): ?>
                                <?php if (in_array($row['condition_id'], ['NW', 'GD', 'WO'])): ?>
                                <option value="<?= htmlspecialchars($row['condition_id']) ?>"><?= htmlspecialchars($row['condition_name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="addmcheck-btn-row">
                        <button type="button" class="addmcheck-popup-btn addmcheck-cancel-btn" onclick="closeAddMCheckPopup()">Cancel</button>
                        <button type="button" id="addMCheckBtn" onclick="saveMCheck()" class="addmcheck-popup-btn addmcheck-save-btn">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="main-panel">
    <div style="overflow-y:auto;">
        <table class="mcheckcycle-table">
            <thead>
                <tr>
                    <th><button class="sort-btn"id="sortBtn" onclick="toggleSort()">
                        🡱</button>
                        DATE
                    </th>
                    <th>PLATE NO.</th>
                    <th>MILEAGE</th>
                    <th>ISSUES</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="mcheckTableBody">
                <?php if (empty($result)): ?>
                <tr><td colspan="5">No records found.</td></tr>
                <?php else: foreach($result as $mcheck):
                    $checkDateDisplay = $mcheck['check_date'] ? date('m/d/y', strtotime($mcheck['check_date'])) : '—';
                    $mileageDisplay   = $mcheck['mileage'] ? number_format($mcheck['mileage']) . ' km' : '—';
                    $issueCount       = (int)($mcheck['no_issues'] ?? 0);
                    $motorInfo        = trim(($mcheck['model_id'] ?? '—') . ' — ' . ($mcheck['hub_name'] ?? '—'));
                ?>
                <tr data-check-no="<?= $mcheck['check_no'] ?>">
                    <td><?= $checkDateDisplay ?></td>
                    <td><?= htmlspecialchars($mcheck['plate_no']) ?></td>
                    <td><?= $mileageDisplay ?></td>
                    <td><?= $issueCount ?></td>
                    <td>
                        <button class="view-btn"
                            onclick="openMCheckDetailsPopup(this)"
                            data-check-no="<?= $mcheck['check_no'] ?>"
                            data-plate-no="<?= htmlspecialchars($mcheck['plate_no']) ?>"
                            data-motor-info="<?= htmlspecialchars($motorInfo) ?>"
                            data-check-date="<?= $checkDateDisplay ?>"
                            data-mileage="<?= $mileageDisplay ?>"
                            data-fuel-type="<?= htmlspecialchars($mcheck['fuel_type_name'] ?? '—') ?>"
                            data-transmission-type="<?= htmlspecialchars($mcheck['transmission_type_name'] ?? '—') ?>"
                            data-issues="<?= $issueCount ?>"
                            data-battery="<?= htmlspecialchars($mcheck['battery_condition'] ?? '—') ?>"
                            data-tire="<?= htmlspecialchars($mcheck['tire_condition'] ?? '—') ?>"
                            data-brake="<?= htmlspecialchars($mcheck['brake_condition'] ?? '—') ?>">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <button id="prevPage" onclick="changePage(-1)">&#8249;</button>
        <span id="pageIndicator">Page 1</span>
        <button id="nextPage" onclick="changePage(1)">&#8250;</button>
    </div>
</div>

<!-- DETAILS POPUP -->
<div class="viewdetails-popup-box" id="MCheckDetailsPopup" onclick="event.stopPropagation()">
    <div class="header-row">
        <p class="header-2" id="popupCheckNo">Maintenance Check no. —</p>
        <button class="x-btn" onclick="closeMCheckDetailsPopup()">✕</button>
    </div>
    <div class="viewdetails-wrapper">
        <div class="details transac">
            <div class="details">
                <p style="color:#757575;">
                    <span>
                        <button class="plate-no" id="popupPlateLink" onclick="">
                            <span id="popupPlateNo">—</span>
                        </button>
                    </span>
                    <span id="popupMotorInfo">—</span>
                </p>
            </div>
            <div class="actions-wrapper">
                <button class="action-btn" onclick="openDelPopup(selectedCheckNo, 'delete-record.php', 'Maintenance Check')">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>
 
        <div class="details transac">
            <div class="info-wrapper-1"><p>Date</p><p>Mileage</p></div>
            <div class="info-wrapper-1"><p>:</p><p>:</p></div>
            <div class="info-wrapper-2">
                <p id="popupDate">—</p>
                <p id="popupMileage">—</p>
            </div>
            <div class="wrap-separator"></div>
            <div class="info-wrapper-1"><p>Fuel Type</p><p>Transmission Type</p></div>
            <div class="info-wrapper-1"><p>:</p><p>:</p></div>
            <div class="info-wrapper-2">
                <p id="popupFuelType">—</p>
                <p id="popupTransmissionType">—</p>
            </div>
        </div>
 
        <hr class="section-divider">
 
        <div class="kpi-wrapper">
            <div>
                <p class="section-title">ISSUE COUNT</p>
                <div class="kpi-card accent-red">
                    <p class="kpi-issue-num" id="popupIssues">0</p>
                    <p class="kpi-sub">Issues</p>
                </div>
            </div>
            <div>
                <p class="section-title">PART CONDITIONS</p>
                <div class="kpi-strip">
                    <div class="kpi-card" id="popupBatteryCard">
                        <p class="kpi-value">Battery</p>
                        <p class="kpi-sub"><span class="kpi-badge" id="popupBattery">—</span></p>
                    </div>
                    <div class="kpi-card" id="popupTireCard">
                        <p class="kpi-value">Tires</p>
                        <p class="kpi-sub"><span class="kpi-badge" id="popupTire">—</span></p>
                    </div>
                    <div class="kpi-card" id="popupBrakeCard">
                        <p class="kpi-value">Brake</p>
                        <p class="kpi-sub"><span class="kpi-badge" id="popupBrake">—</span></p>
                    </div>
                </div>
            </div>
        </div>
 
        <hr class="section-divider" style="margin-top:12px;">
 
        <!-- ── MAINTENANCE PREDICTION BLOCK ── -->
        <p class="section-title" style="margin-top:10px;">MAINTENANCE PREDICTION</p>
        <div id="popupPredictionBlock" style="
            display:flex; align-items:center; gap:14px;
            background:#f8f8f8; border:1px solid #e8e8e8;
            border-radius:8px; padding:12px 16px; margin-top:4px;
        ">
            <!-- Status badge -->
            <div id="popupPredBadge" style="
                width:64px; height:64px; border-radius:8px; flex-shrink:0;
                display:flex; flex-direction:column; align-items:center;
                justify-content:center; font-weight:bold; text-align:center;
                font-size:11px; letter-spacing:.5px; gap:3px;
            ">
                <span id="popupPredIcon" style="font-size:22px;">—</span>
                <span id="popupPredLabel" style="font-size:10px;">—</span>
            </div>
 
            <!-- Details -->
            <div style="display:flex;flex-direction:column;gap:4px;flex:1;">
                <p id="popupPredStatus" style="margin:0;font-size:14px;font-weight:bold;color:#1a1a1a;">No prediction available</p>
                <p id="popupPredConf"   style="margin:0;font-size:12px;color:#6b6b6b;">Run the prediction model to get a forecast.</p>
                <p id="popupPredDate"   style="margin:0;font-size:11px;color:#aaa;"></p>
            </div>
        </div>
 
    </div>
</div>

<script src="layout.js"></script>
<script>
    let selectedCheckNo    = null;
    let selectedPlateNo    = null;

    // ── CONDITION DISPLAY ────────────────────────────────────
    function setConditionDisplay(badgeId, cardId, condition) {
        const badge = document.getElementById(badgeId);
        const card  = document.getElementById(cardId);
        const value = condition || '—';
        const norm  = value.toLowerCase();

        badge.textContent = value;
        badge.className   = 'kpi-badge';
        card.className    = 'kpi-card';

        if (norm === 'new') {
            badge.classList.add('green');
            card.classList.add('accent-green');
        } else if (norm === 'good') {
            badge.classList.add('yellow');
            card.classList.add('accent-yellow');
        } else if (norm === 'weak' || norm === 'worn out') {
            badge.classList.add('red');
            card.classList.add('accent-red');
        } else {
            badge.classList.add('yellow');
            card.classList.add('accent-yellow');
        }
    }

    const maintPredictions = <?= $maintPredictionsJson ?? '{}' ?>;
    
    function setPredictionDisplay(checkNo) {
        const pred = maintPredictions[checkNo];
        const badge  = document.getElementById('popupPredBadge');
        const icon   = document.getElementById('popupPredIcon');
        const label  = document.getElementById('popupPredLabel');
        const status = document.getElementById('popupPredStatus');
        const conf   = document.getElementById('popupPredConf');
        const date   = document.getElementById('popupPredDate');
    
        if (!pred) {
            badge.style.background = '#f0f0f0';
            badge.style.color      = '#aaa';
            icon.textContent       = '—';
            label.textContent      = 'N/A';
            status.textContent     = 'No prediction available';
            status.style.color     = '#aaa';
            conf.textContent       = 'Run the prediction model to get a forecast.';
            date.textContent       = '';
            return;
        }
    
        const needsMaint  = parseInt(pred.needs_maintenance) === 1;
        const predDate    = pred.prediction_date || pred.date_created || '';
    
        if (needsMaint) {
            badge.style.background = '#fceaea';
            badge.style.color      = '#b71513';
            icon.textContent       = '⚠';
            label.textContent      = 'NEEDS';
            status.textContent     = 'Maintenance recommended';
            status.style.color     = '#b71513';
        } else {
            badge.style.background = '#e6f4ee';
            badge.style.color      = '#1a7f4b';
            icon.textContent       = '✓';
            label.textContent      = 'GOOD';
            status.textContent     = 'No maintenance needed';
            status.style.color     = '#1a7f4b';
        }
    
        conf.textContent = '';
        date.textContent = predDate ? `Predicted on: ${predDate}` : '';
    }

    // ── DETAILS POPUP ────────────────────────────────────────
    function openMCheckDetailsPopup(btn) {
        // Store for delete
        selectedCheckNo = btn.dataset.checkNo || null;
        selectedPlateNo = btn.dataset.plateNo || null;

        // Populate fields from data attributes
        document.getElementById('popupCheckNo').textContent          = 'Maintenance Check no. ' + (selectedCheckNo || '—');
        document.getElementById('popupPlateNo').textContent          = btn.dataset.plateNo || '—';
        document.getElementById('popupMotorInfo').textContent        = btn.dataset.motorInfo || '—';
        document.getElementById('popupPlateLink').onclick = function() {
            window.location.href = 'motorcycle-details.php?id=' + encodeURIComponent(btn.dataset.plateNo);
        };
        document.getElementById('popupDate').textContent             = btn.dataset.checkDate || '—';
        document.getElementById('popupMileage').textContent          = btn.dataset.mileage || '—';
        document.getElementById('popupFuelType').textContent         = btn.dataset.fuelType || '—';
        document.getElementById('popupTransmissionType').textContent = btn.dataset.transmissionType || '—';
        document.getElementById('popupIssues').textContent           = btn.dataset.issues || '0';

        setConditionDisplay('popupBattery', 'popupBatteryCard', btn.dataset.battery);
        setConditionDisplay('popupTire',    'popupTireCard',    btn.dataset.tire);
        setConditionDisplay('popupBrake',   'popupBrakeCard',   btn.dataset.brake);
        setPredictionDisplay(btn.dataset.checkNo);

        // Position popup near the button
        const popup = document.getElementById('MCheckDetailsPopup');
        popup.style.display = 'block';
        const popupH = popup.offsetHeight;
        const popupW = popup.offsetWidth;
        popup.style.display = '';

        const rect       = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;

        let top  = spaceBelow >= popupH + 8 ? rect.bottom + 8 : rect.top - popupH - 8;
        let left = rect.right - popupW;
        if (left < 8) left = 8;
        if (top  < 8) top  = 8;

        popup.style.position = 'fixed';
        popup.style.top      = top  + 'px';
        popup.style.left     = left + 'px';
        popup.style.right    = 'unset';
        popup.classList.add('show');
    }

    function closeMCheckDetailsPopup() {
        document.getElementById('MCheckDetailsPopup').classList.remove('show');
    }

    // ── ADD MCHECK ───────────────────────────────────────────
    function openAddMCheckPopup() {
        document.getElementById('AddMCheckPopup').classList.toggle('show');
        document.getElementById('addMCheckForm').reset();
        clearPopupMessage('addMCheckMessage');
    }

    function closeAddMCheckPopup() {
        document.getElementById('AddMCheckPopup').classList.remove('show');
        document.getElementById('addMCheckForm').reset();
        clearPopupMessage('addMCheckMessage');
    }

    function saveMCheck() {
        const date      = document.getElementById('checkDate').value;
        const plateNo   = document.getElementById('checkPlateNo').value;
        const mileage   = document.getElementById('checkMileage').value;
        const noIssues  = document.getElementById('checkNoIssues').value;
        const fuelType  = document.getElementById('checkFuelType').value;
        const transType = document.getElementById('checkTransType').value;
        const battery   = document.getElementById('checkBattery').value;
        const tire      = document.getElementById('checkTire').value;
        const brake     = document.getElementById('checkBrake').value;

        if (!date || !plateNo || !fuelType || !transType || !battery || !tire || !brake) {
            showPopupMessage('addMCheckMessage', 'Please fill in all required fields.', 'error');
            return;
        }

        const addBtn = document.getElementById('addMCheckBtn');
        setButtonLoading(addBtn, true, 'Adding...');

        fetch('add-mcheck.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                check_date: date, plate_no: plateNo,
                mileage: mileage || null, no_issues: noIssues || 0,
                fuel_type_id: fuelType, transmission_type_id: transType,
                battery, tire, brake
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showPopupMessage('addMCheckMessage', 'Record added!', 'success');
                document.getElementById('addMCheckForm').reset();
                setTimeout(() => location.reload(), 1000);
            } else {
                showPopupMessage('addMCheckMessage', data.message || 'Failed to add record.', 'error');
            }
        })
        .catch(() => showPopupMessage('addMCheckMessage', 'Something went wrong.', 'error'))
        .finally(() => setButtonLoading(addBtn, false, 'Add'));
    }

    // ── SEARCH ───────────────────────────────────────────────
    let sortAsc = true;

    function toggleSort() {
        sortAsc = !sortAsc;
        document.getElementById("sortBtn").textContent = sortAsc ? "🡱" : "🡳";
        filterTable();
    }
    
    const ROWS_PER_PAGE = 15;
    let currentPage = 1;

    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        
        const rows = document.querySelectorAll("#mcheckTableBody tr[data-check-no]");
        rows.forEach(row => {
            const plate = row.cells[1]?.textContent.toLowerCase() ?? '';
            row._filtered = (!search || plate.includes(search));
            row.style.display = 'none';
        });

        // Sort
        const tbody = document.getElementById("mcheckTableBody");
        const allRows = Array.from(rows);
        allRows.sort((a, b) => {
            const aVal = a.cells[0]?.textContent.trim() ?? '';
            const bVal = b.cells[0]?.textContent.trim() ?? '';
            return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        allRows.forEach(row => tbody.appendChild(row));

        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const rows = Array.from(document.querySelectorAll("#mcheckTableBody tr[data-check-no]"))
                        .filter(r => r._filtered !== false);

        const totalPages = Math.max(1, Math.ceil(rows.length / ROWS_PER_PAGE));
        currentPage = Math.min(currentPage, totalPages);

        const start = (currentPage - 1) * ROWS_PER_PAGE;
        const end   = start + ROWS_PER_PAGE;

        rows.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });

        document.getElementById("pageIndicator").textContent = `Page ${currentPage} of ${totalPages}`;
        document.getElementById("prevPage").disabled = currentPage === 1;
        document.getElementById("nextPage").disabled = currentPage === totalPages;
    }

    function changePage(dir) {
        currentPage += dir;
        renderPage();
    }

    function clearFilters() {
        document.getElementById("searchInput").value    = '';
        filterTable();
    }

    filterTable();

    // ── HELPERS ──────────────────────────────────────────────
    function showPopupMessage(id, msg, type) {
        let el = document.getElementById(id);
        if (!el) {
            el = document.createElement('p');
            el.id = id;
            el.style.cssText = 'margin:8px 0 0;font-size:13px;text-align:center;font-weight:500;';
            const btnRow = document.querySelector('#AddMCheckPopup .addmcheck-btn-row');
            if (btnRow) btnRow.parentNode.insertBefore(el, btnRow);
        }
        el.textContent = msg;
        el.style.color = type === 'success' ? '#2e7d32' : '#c62828';
    }

    function clearPopupMessage(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function setButtonLoading(btn, isLoading, label) {
        if (!btn) return;
        btn.disabled    = isLoading;
        btn.textContent = label;
    }
</script>
</body>
</html>