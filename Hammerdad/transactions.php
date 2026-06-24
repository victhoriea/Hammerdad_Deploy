<?php 
require_once 'auth.php';
$pageTitle = "Transactions";
$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

require 'db.php';

// Fetch transactions
$result = $conn->query("
    SELECT DISTINCT
        rt.transaction_id,
        rt.date,
        m.plate_no,
        mo.model_id,
        h.hub_name,
        rt.type_id,
        rt.labor_cost,
        COALESCE((
            SELECT SUM(tp.unit_price * tp.quantity)
            FROM transaction_parts tp
            JOIN repair r ON tp.repair_id = r.repair_id
            WHERE tp.transaction_id = rt.transaction_id
        ), 0) AS repair_cost,
        COALESCE((
            SELECT SUM(tp.unit_price * tp.quantity)
            FROM transaction_parts tp
            JOIN repair r ON tp.repair_id = r.repair_id
            WHERE tp.transaction_id = rt.transaction_id
        ), 0) + rt.labor_cost AS total_cost
    FROM repair_transactions rt
    JOIN motorcycles m ON rt.plate_no = m.plate_no
    JOIN hubs h ON m.hub_id = h.hub_id
    JOIN models mo ON m.model_id = mo.model_id
    ORDER BY rt.transaction_id DESC
");
$transactions = $result->fetch_all(MYSQLI_ASSOC);

// ALL motorcycles for modelMap
$all_motors = $conn->query("
    SELECT m.plate_no, m.model_id FROM motorcycles m ORDER BY m.plate_no
")->fetch_all(MYSQLI_ASSOC);

// Dropdown queries
$plates    = $conn->query("SELECT plate_no FROM motorcycles ORDER BY plate_no ASC")->fetch_all(MYSQLI_ASSOC);
$mechanics = $conn->query("SELECT mechanic_id, first_name, last_name FROM mechanics ORDER BY mechanic_id ASC")->fetch_all(MYSQLI_ASSOC);
$parts     = $conn->query("SELECT repair_id, part_name, price FROM repair ORDER BY part_name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Transaction | Hammerdad</title>
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700" />
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #d9d9d9; display: flex; flex-direction: column; color: #212328; }
        .main-panel { min-height: unset; height: 467px; margin-top: 0; border-radius: 0 0 5px 5px;}

        .upper-panel {
            position: relative;
            z-index: 10;
        }
        
        .main-transac-table { width: 100%; text-align: center; border-collapse: collapse;}
        .main-transac-table thead th {
            font-weight: bold; letter-spacing: .9px; text-transform: uppercase; background-color: #fff;
            color: #b71513; padding: 0 10px 10px; border-bottom: 1px solid #e2e2e2; white-space: nowrap;
            position: sticky; top: 0; z-index: 1; -webkit-text-stroke: 0.1px #b71513;
        }
        .main-transac-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .main-transac-table tbody tr:last-child { border-bottom: none; }
        .main-transac-table tbody tr:hover { background: #f7f7f5; }
        .main-transac-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }

        .transac-table { border-collapse: collapse; text-align: center; overflow-y: scroll; }
        .addtransac-wrapper .transac-table { max-height: 390px; overflow-y: auto; }
        .pms-table, .repair-table { width: 100%; display: none; }
        .pms-table.show, .repair-table.show { display: table; }
        .transac-table th { background-color: #b71513; color: #fff; font-weight: bold; padding: 4px 2px; }
        .transac-table td { background-color: #f4f4f4; padding: 7px 2px; }
        .transac-table tbody tr:nth-child(even) td { background-color: #f0f0f0; }
        
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined';
            font-variation-settings:
                'FILL' 0,
                'wght' 200,
                'GRAD' 0,
                'opsz' 24;
        }
        .view-btn { border: none; background-color: transparent; color: #44444490; display: flex; flex-direction: column; justify-self: center; align-self: flex-end; cursor: pointer; }
        .view-btn:hover { color: #1e1f22; }
        .add-container { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; justify-content: center; align-items: center; gap: 10px; z-index: 101; background: rgba(0,0,0,0.35); }
        .add-container.show { display: flex; }
        .addtransac-popup-box { background: #f1f1f1; width: 900px; padding: 25px; border: 1px solid #DDDDDD; border-radius: 10px; text-align: center; box-shadow: 0px 4px 15px rgba(0,0,0,0.2); }
        .addtransac-wrapper { background-color: #fff; border: 1px solid #DDDDDD; border-radius: 7px; padding: 20px; display: flex; flex-direction: column; }
        .addtransac-wrapper form { display: flex; flex-direction: row; justify-content: center; gap: 30px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .header-2 { font-size: 30px; font-weight: bold; color: #b71513; margin: 0; padding-bottom: 15px; }
        .addtransac-popup-box label { font-size: 15px; margin-right: 5px; }
        .addtransac-popup-box input[type="date"] { width: 150px; height: 35px; padding: 2px 15px; border: 1px solid #DDDDDD; border-radius: 7px; margin-bottom: 10px; }
        .addtransac-popup-box select { width: 180px; height: 40px; padding: 2px 10px; border: 1px solid #DDDDDD; border-radius: 7px; margin-bottom: 10px; }
        .addtransac-popup-box select:disabled { color: #B3B3B3; }
        .addtransac-btn-row { display: flex; flex-direction: row; justify-content: right; gap: 8px; margin-top: 30px; }
        .addtransac-popup-btn { width: 20%; height: 45px; border-radius: 5px; border: none; font-size: 20px; cursor: pointer; }
        .addtransac-save-btn { background-color: #b71513; color: white; }
        .addtransac-save-btn:hover { background-color: #d31512; }
        .addtransac-cancel-btn { background-color: #CBCBCB; color: #000; }
        .addtransac-cancel-btn:hover { background-color: rgb(189, 189, 189); }
        .section-divider { border: none; height: 0.5px; background-color: #DDDDDD; width: 100%; margin: 15px auto 10px auto; flex-shrink: 0; }
        .addrow-popup-box { display: none; background: #f1f1f1; width: 310px; padding: 20px 0; border: 1px solid #DDDDDD; border-radius: 10px; text-align: center; box-shadow: 0px 4px 15px rgba(0,0,0,0.2); }
        .addrow-popup-box.show { display: flex; flex-direction: column; }
        .addrow-wrapper { background-color: #fff; }
        .pms-addrow, .repair-addrow { max-height: 0; overflow: hidden; padding: 0 20px; border: none; transition: max-height 0.4s ease, padding 0.4s ease; }
        .pms-addrow.show { max-height: 400px; border-top: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; padding: 20px; }
        .repair-addrow.show { max-height: 480px; border-top: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; padding: 20px; }
        .header-3 { font-size: 25px; font-weight: bold; color: #b71513; margin: 0; padding-bottom: 15px; }
        .addrow-wrapper form { display: flex; flex-direction: column; gap: 15px; }
        .form-row { display: flex; flex-direction: row; justify-content: space-between; align-items: center; }
        .addrow-popup-box label { font-size: 15px; margin-right: 5px; }
        .addrow-popup-box input[type="text"], .addrow-popup-box select.addrow-select { width: 160px; height: 30px; padding: 2px 10px; border: 1px solid #DDDDDD; border-radius: 7px; }
        .addrow-popup-box input[type="number"].qty-input { width: 38px; height: 30px; padding: 2px 5px; border-width: 0; border-bottom: 1px solid #DDDDDD; border-style: solid; }
        .addrow-popup-box input[type="number"].labor-input { width: 55px; height: 30px; padding: 2px 5px; border-width: 0; border-bottom: 1px solid #DDDDDD; border-style: solid; }
        .pms-checkboxes-wrapper, .repair-work-wrapper { display: flex; flex-direction: column; text-align: left; }
        .addrow-section-divider { border: none; height: 0.5px; background-color: #DDDDDD; width: 100%; margin: 5px 0; flex-shrink: 0; }
        .pms-checkboxes { display: flex; flex-direction: row; align-items: center; gap: 5px; }
        .pms-checkboxes input[type="checkbox"] { width: 18px; height: 18px; padding: 0; margin: 1px; cursor: not-allowed; }
        .totalcost-row { display: flex; flex-direction: row; justify-content: space-between; margin: -15px 0 0 0; }
        .totalcost-row p { font-weight: bold; }
        .addrow-btn-row { display: flex; flex-direction: row; justify-content: center; gap: 8px; margin-top: 5px; }
        .addrow-popup-box input[type="button"] { width: 45%; height: 35px; border-radius: 5px; border: none; font-size: 15px; cursor: pointer; background-color: #CBCBCB; color: #000; }
        .addrow-popup-box input[type="button"]:hover { background-color: rgb(189, 189, 189); }
        .addrow-popup-box input[type="reset"] { width: 45%; height: 35px; border-radius: 5px; border: none; font-size: 15px; cursor: pointer; background-color: #CBCBCB; color: #000; }
        .addrow-popup-box input[type="reset"]:hover { background-color: rgb(189, 189, 189); }
        .addrow-popup-box input[type="submit"] { width: 45%; height: 35px; border-radius: 5px; border: none; font-size: 15px; cursor: pointer; background-color: #b71513; color: #fff; }
        .addrow-popup-box input[type="submit"]:hover { background-color: #d31512; }
        #workTags { display: flex; flex-direction: column; gap: 8px; margin-top: 3px; max-height: 100px; overflow-y: auto; }
        .work-tag { display: flex; justify-content: space-between; align-items: center; color: #575757; gap: 3px; font-size: 13px; }
        .work-tag button { background: none; border: none; cursor: pointer; color: #888; font-size: 10px; }
        .work-tag button:hover { color: #b71513; }

        /* ── FILTER BAR ── */
        .filter-bar {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            align-self: center;
            width: clamp(400px, 75.1%, 1200px);
            gap: 10px;
            padding: 8px 10px;
            padding-left: 35px;
            background: #f1f1f1;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
            position: relative;
            transition: transform 0.5s;  
        }

        .filter-bar.move {
            transform: translateX(calc(var(--sidebar-width) / 2));
        }

        .filter-bar input[type="text"],
        .filter-bar select,
        .filter-bar input[type="date"] {
            height: 32px;
            padding: 2px 10px;
            border: 1px solid #ddd;
            border-radius: 7px;
        }
        .filter-bar input[type="text"] { width: 180px; }
        .filter-bar select { width: 130px; }
        .filter-bar input[type="date"] { width: 140px; }
        .filter-bar label { color: #555; }
        .filter-btn {
            height: 32px;
            padding: 0 14px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            background-color: #b71513;
            color: #fff;
        }
        .filter-btn:hover { background-color: #d31512; }
        .clear-btn {
            height: 32px;
            padding: 0 14px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            background-color: #CBCBCB;
            color: #000;
        }
        .clear-btn:hover { background-color: #bbb; }
        .print-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 40px;
            width: 130px;
            gap: 10px;
            border-radius: 5px;
            border: none;
            background-color: #b71513;
            color: white;
            cursor: pointer;

            position: absolute;
            right: 50px;
        }

        .print-btn:hover {
            background-color: #d31512;
        }

        .print-btn .material-symbols-outlined {
            font-size: 24px;
            font-variation-settings:
                'FILL' 1,
                'wght' 400,
                'GRAD' 0,
                'opsz' 24;
        }

        /* ── PRINT STYLES ── */
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea {
                position: absolute;
                left: 0; top: 0;
                width: 100%;
                font-family: Tahoma, sans-serif;
                font-size: 12px;
            }
            #printArea table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            #printArea th { background-color: #b71513 !important; color: #fff !important; padding: 6px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #printArea td { padding: 5px; border-bottom: 1px solid #ddd; }
            #printArea .print-header { text-align: center; margin-bottom: 15px; }
            #printArea .print-header h2 { margin: 0; font-size: 18px; color: #b71513; }
            #printArea .print-header p { margin: 2px 0; font-size: 12px; color: #555; }
            #printArea .print-total-row td { font-weight: bold; background-color: #f0f0f0 !important; }
            #printArea .print-grand-total { margin-top: 15px; text-align: right; font-size: 14px; font-weight: bold; }
        }
    </style>
</head>
<body>

<div class="loader-wrapper"><div class="loader"></div></div>
<?php include 'page-essentials.php'; ?>

<!-- UPPER PANEL -->
<div class="upper-panel">
    <search>
        <form class="search-wrapper" onsubmit="return false;">
            <input class="search-input" type="text" id="searchInput" placeholder="Search Plate Number" oninput="applyFilters()">
            <button class="x-btn" type="reset" onclick="setTimeout(applyFilters, 0)">✕</button>
            <button class="search-btn" onclick="applyFilters()">🔍︎</button>
        </form>
    </search>
    <button class="add-btn" onclick="openAddTransacPopup()">+ Add Transaction</button>
</div>

<!-- FILTER BAR -->
<div class="filter-bar">
    <label>Type:</label>
    <select id="filterType" onchange="applyFilters()">
        <option value="">All</option>
        <option value="PMS">PMS</option>
        <option value="REP">Repair</option>
    </select>

    <label>Hub:</label>
    <select id="hubFilter" onchange="applyFilters()">
        <option value="">All</option>
        <?php
            $hubs_filter = $conn->query("SELECT hub_id, hub_name FROM hubs ORDER BY hub_name");
            while($row = $hubs_filter->fetch_assoc()):
            ?>
            <option value="<?= htmlspecialchars($row['hub_name']) ?>"><?= htmlspecialchars($row['hub_name']) ?></option>
        <?php endwhile; ?>
    </select>

    <label>From:</label>
    <input type="date" id="filterDateFrom" onchange="applyFilters()">

    <label>To:</label>
    <input type="date" id="filterDateTo" onchange="applyFilters()">

    <button class="clear-btn" onclick="clearFilters()">Clear</button>

    <button class="print-btn" onclick="printStatement()">
        <span class="material-symbols-outlined">print</span>
        Print
    </button>
</div>

<!-- ADD TRANSACTION POPUP -->
<div class="add-container" id="AddTransacPopup" onclick="closeAddTransacPopup()">
    <div class="addtransac-popup-box" onclick="event.stopPropagation()">
        <div class="header-row">
            <p class="header-2">Add Transaction</p>
            <button class="x-btn" onclick="closeAddTransacPopup()">✕</button>
        </div>
        <div class="addtransac-wrapper">
            <form id="addTransacForm">
                <div>
                    <label>Date</label>
                    <input type="date" id="transacDate" required>
                </div>
                <div>
                    <label>Type</label>
                    <select name="type" id="type" required>
                        <option value="" disabled selected>-- Type --</option>
                        <option value="PMS">PMS</option>
                        <option value="Repair">Repair</option>
                    </select>
                </div>
            </form>

            <hr class="section-divider">

            <div class="transac-table">
                <table class="pms-table" id="pmsTable">
                    <thead>
                        <tr><th>PLATE NO.</th><th>MODEL</th><th>WORK</th><th>COST</th><th>MECHANIC</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <table class="repair-table" id="repairTable">
                    <thead>
                        <tr><th>PLATE NO.</th><th>MODEL</th><th>WORK</th><th>COST</th><th>LABOR</th><th>TOTAL</th><th>MECHANIC</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="addtransac-btn-row">
                <button class="addtransac-popup-btn addtransac-cancel-btn" onclick="closeAddTransacPopup()">Cancel</button>
                <button id="addTransacBtn" class="addtransac-popup-btn addtransac-save-btn">Save</button>
            </div>
        </div>
    </div>

    <div class="addrow-popup-box" id="AddRowPopup" onclick="event.stopPropagation()">
        <p class="header-3">Add Row</p>

        <!-- PMS FORM -->
        <div class="pms-addrow addrow-wrapper">
            <form id="addPMSForm">
                <div class="form-row">
                    <label>Plate no.</label>
                    <input required type="text" name="plate_no" id="pmsPlateNo" 
                        list="pmsPlateList" placeholder="ex. ABC123" autocomplete="off">

                    <datalist id="pmsPlateList">
                        <?php foreach ($plates as $row): ?>
                            <option value="<?= htmlspecialchars($row['plate_no']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-row">
                    <label>Mechanic</label>
                    <input required type="text" name="mechanic" id="pmsMechanicName"
                        list="pmsMechanicList" placeholder="ex. Juan Cruz" autocomplete="off">

                    <datalist id="pmsMechanicList">
                        <?php foreach ($mechanics as $row): ?>
                            <option value="<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="pms-checkboxes-wrapper">
                    <label style="color:#757575">Work done</label>
                    <hr class="addrow-section-divider">
                    <div class="pms-checkboxes">
                        <input type="checkbox" id="clean-carb" checked disabled>
                        <label for="clean-carb">Clean Carburetor</label>
                    </div>
                    <div class="pms-checkboxes">
                        <input type="checkbox" id="change-oil" checked disabled>
                        <label for="change-oil">Change Oil</label>
                    </div>
                    <div class="pms-checkboxes">
                        <input type="checkbox" id="tune-up" checked disabled>
                        <label for="tune-up">Tune Up</label>
                    </div>
                    <hr class="addrow-section-divider">
                </div>
                <div class="totalcost-row">
                    <p>Total Cost</p>
                    <p>₱ 900.00</p>
                </div>
                <div class="addrow-btn-row">
                    <input type="button" value="Clear" onclick="clearPMSForm()">
                    <input type="submit" value="Add">
                </div>
            </form>
        </div>

        <!-- REPAIR FORM -->
        <div class="repair-addrow addrow-wrapper">
            <form id="addRepairForm">
                <div class="form-row">
                    <label>Plate no.</label>
                    <input required type="text" name="plate_no" id="repairPlateNo" 
                        list="repairPlateList" placeholder="ex. ABC123" autocomplete="off">

                    <datalist id="repairPlateList">
                        <?php foreach ($plates as $row): ?>
                            <option value="<?= htmlspecialchars($row['plate_no']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-row">
                    <label>Mechanic</label>
                    <input required type="text" name="mechanic" id="repairMechanicName"
                        list="repairMechanicList" placeholder="ex. Juan Cruz" autocomplete="off">

                    <datalist id="repairMechanicList">
                        <?php foreach ($mechanics as $row): ?>
                            <option value="<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="repair-work-wrapper">
                    <label style="color:#757575">Work done</label>
                    <hr class="addrow-section-divider">
                    <div class="form-row">
                        <input type="text" id="workInput" style="width:120px;"
                        list="partList" placeholder="ex. Battery" autocomplete="off">
                            
                            <datalist id="partList">
                                <?php foreach ($parts as $row): ?>
                                <option value="<?= htmlspecialchars($row['part_name']) ?>" data-price="<?= $row['price'] ?>"><?= htmlspecialchars($row['part_name']) ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        <input class="qty-input" type="number" id="qtyInput" placeholder="qty" min="1" step="1">
                        <input class="labor-input" type="number" id="itemLaborInput" placeholder="labor" min="0" step="1">
                    </div>
                    <button type="button" onclick="addWorkTag()" style="margin-top:5px;background:#f0f0f0;border:1px solid #ddd;border-radius:5px;padding:4px 10px;font-size:12px;cursor:pointer;align-self:flex-end;">+ Add Part</button>
                </div>

                <div id="workTags"></div>
                <hr class="addrow-section-divider">
                <div class="totalcost-row">
                    <p>Total Cost</p>
                    <p id="totalCostDisplay">₱ 0.00</p>
                </div>
                <div class="addrow-btn-row">
                    <input type="reset" value="Clear">
                    <input type="submit" value="Add">
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MAIN TABLE -->
<div class="main-panel">
    <div style="overflow-y:auto;">
        <table class="main-transac-table" id="mainTransacTable">
            <thead>
                <tr>
                    <th><button class="sort-btn"id="sortBtn" onclick="toggleSort()">
                            🡱</button>
                            DATE
                        </th><th style="width:185px;">PLATE NO.</th><th>HUB</th><th>TYPE</th><th>LABOR COST</th><th>REPAIR COST</th><th>TOTAL COST</th><th></th>
                </tr>
            </thead>
            <tbody id="transacTableBody">
                <?php if (empty($transactions)): ?>
                <tr><td colspan="8">No transactions found.</td></tr>
                <?php else: foreach ($transactions as $txn): ?>
                <tr
                    data-plate="<?= htmlspecialchars($txn['plate_no']) ?>"
                    data-hub="<?= htmlspecialchars($txn['hub_name']) ?>"
                    data-type="<?= htmlspecialchars($txn['type_id']) ?>"
                    data-date="<?= $txn['date'] ?>"
                    data-id="<?= $txn['transaction_id'] ?>"
                    data-labor="<?= $txn['labor_cost'] ?>"
                    data-repair="<?= $txn['repair_cost'] ?>"
                    data-total="<?= $txn['total_cost'] ?>"
                >
                    <td><?= date('m/d/y', strtotime($txn['date'])) ?></td>
                    <td><?= htmlspecialchars($txn['plate_no']) ?></td>
                    <td><?= htmlspecialchars($txn['hub_name']) ?></td>
                    <td><?= $txn['type_id'] === 'PMS' ? 'PMS' : 'Repair' ?></td>
                    <td>₱<?= number_format($txn['labor_cost'], 2) ?></td>
                    <td>₱<?= number_format($txn['repair_cost'], 2) ?></td>
                    <td>₱<?= number_format($txn['total_cost'], 2) ?></td>
                    <td>
                        <button class="view-btn" onclick="window.location.href='transac-details.php?id=<?= $txn['transaction_id'] ?>'">
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

<!-- PRINT AREA (hidden, shown on print) -->
<div id="printArea" style="display:none;"></div>

<script src="layout.js"></script>
<script>
const modelMap     = <?= json_encode(array_column($all_motors, 'model_id', 'plate_no')) ?>;
const partPriceMap = <?= json_encode(array_column($parts, 'price', 'part_name')) ?>;

// Valid sets for validation
const validPlates    = new Set(<?= json_encode(array_column($plates, 'plate_no')) ?>);
const validMechanics = new Set(<?= json_encode(array_map(fn($m) => $m['first_name'] . ' ' . $m['last_name'], $mechanics)) ?>);
const validParts     = new Set(<?= json_encode(array_column($parts, 'part_name')) ?>);

// ── FILTERS ───────────────────────────────────────────────────
let sortAsc = true;

function toggleSort() {
    sortAsc = !sortAsc;
    document.getElementById("sortBtn").textContent = sortAsc ? "🡱" : "🡳";
    applyFilters();
}

const ROWS_PER_PAGE = 15;
let currentPage = 1;

function applyFilters() {
    const search   = document.getElementById("searchInput").value.toLowerCase().trim();
    const type     = document.getElementById("filterType").value;
    const hub      = document.getElementById("hubFilter").value.toLowerCase().trim();
    const dateFrom = document.getElementById("filterDateFrom").value;
    const dateTo   = document.getElementById("filterDateTo").value;

    const rows = document.querySelectorAll("#transacTableBody tr[data-plate]");
    rows.forEach(row => {
        const plate    = row.dataset.plate.toLowerCase();
        const rowHub    = row.dataset.hub.toLowerCase();
        const rowType  = row.dataset.type;
        const rowDate  = row.dataset.date;

        const matchSearch = !search || plate.includes(search);
        const matchType   = !type   || rowType === type;
        const matchHub    = !hub    || rowHub === hub;
        const matchFrom   = !dateFrom || rowDate >= dateFrom;
        const matchTo     = !dateTo   || rowDate <= dateTo;

        row._filtered = (matchSearch && matchType && matchHub && matchFrom && matchTo);
        row.style.display = 'none';
    });

    // Sort
    const tbody = document.getElementById("transacTableBody");
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
    const rows = Array.from(document.querySelectorAll("#transacTableBody  tr[data-plate]"))
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
    document.getElementById("filterType").value     = '';
    document.getElementById("filterDateFrom").value = '';
    document.getElementById("filterDateTo").value   = '';
    applyFilters();
}

applyFilters();


// ── PRINT ─────────────────────────────────────────────────────
function printStatement() {
    const rows = document.querySelectorAll("#transacTableBody tr[data-plate]");
    const visibleRows = Array.from(rows).filter(r => r._filtered !== false);

    if (visibleRows.length === 0) {
        alert("No transactions to print.");
        return;
    }

    const plateGroups = {};
    visibleRows.forEach(row => {
        const plate  = row.dataset.plate;
        const txnId  = row.dataset.id;
        const labor  = parseFloat(row.dataset.labor) || 0;
        const repair = parseFloat(row.dataset.repair) || 0;
        const total  = parseFloat(row.dataset.total) || 0;
        const type   = row.dataset.type;
        const date   = row.querySelector("td").textContent.trim();

        if (!plateGroups[plate]) plateGroups[plate] = { transactions: [], laborTotal: 0, repairTotal: 0, grandTotal: 0 };
        plateGroups[plate].transactions.push({ txnId, date, type, labor, repair, total });
        plateGroups[plate].laborTotal  += labor;
        plateGroups[plate].repairTotal += repair;
        plateGroups[plate].grandTotal  += total;
    });

    const filterType = document.getElementById("filterType").value;
    const filterFrom = document.getElementById("filterDateFrom").value;
    const filterTo   = document.getElementById("filterDateTo").value;
    let filterLabel  = [];
    if (filterType) filterLabel.push(`Type: ${filterType}`);
    if (filterFrom) filterLabel.push(`From: ${filterFrom}`);
    if (filterTo)   filterLabel.push(`To: ${filterTo}`);

    let grandLaborTotal  = 0;
    let grandRepairTotal = 0;
    let grandTotalAll    = 0;

    let tableRows = '';
    Object.entries(plateGroups).forEach(([plate, data]) => {
        data.transactions.forEach((txn, i) => {
            tableRows += `
                <tr>
                    <td>${txn.txnId}</td>
                    <td>${i === 0 ? plate : ''}</td>
                    <td>${txn.date}</td>
                    <td>${txn.type === 'PMS' ? 'PMS' : 'Repair'}</td>
                    <td>₱${txn.labor.toFixed(2)}</td>
                    <td>₱${txn.repair.toFixed(2)}</td>
                    <td>₱${txn.total.toFixed(2)}</td>
                </tr>
            `;
        });
        grandLaborTotal  += data.laborTotal;
        grandRepairTotal += data.repairTotal;
        grandTotalAll    += data.grandTotal;

        tableRows += `
            <tr class="print-total-row">
                <td colspan="4" style="text-align:right;">Subtotal for ${plate}</td>
                <td>₱${data.laborTotal.toFixed(2)}</td>
                <td>₱${data.repairTotal.toFixed(2)}</td>
                <td>₱${data.grandTotal.toFixed(2)}</td>
            </tr>
        `;
    });

    const printArea = document.getElementById("printArea");
    printArea.style.display = 'block';
    printArea.innerHTML = `
        <div class="print-header">
            <h2>Hammerdad Service Center</h2>
            <p>Statement of Accounts</p>
            ${filterLabel.length > 0 ? `<p style="color:#555;">${filterLabel.join(' &nbsp;|&nbsp; ')}</p>` : ''}
            <p style="color:#888; font-size:11px;">Generated: ${new Date().toLocaleString()}</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>TXN ID</th>
                    <th>PLATE NO.</th>
                    <th>DATE</th>
                    <th>TYPE</th>
                    <th>LABOR COST</th>
                    <th>REPAIR COST</th>
                    <th>TOTAL COST</th>
                </tr>
            </thead>
            <tbody>
                ${tableRows}
            </tbody>
        </table>
        <div class="print-grand-total">
            <p>Total Labor: ₱${grandLaborTotal.toFixed(2)} &nbsp;|&nbsp; Total Repair: ₱${grandRepairTotal.toFixed(2)} &nbsp;|&nbsp; <span style="color:#b71513;">Total Revenue: ₱${grandTotalAll.toFixed(2)}</span></p>
        </div>
    `;

    window.print();
    printArea.style.display = 'none';
}

// ── CLEAR PMS FORM ────────────────────────────────────────────
function clearPMSForm() {
    document.getElementById("pmsPlateNo").value      = "";
    document.getElementById("pmsMechanicName").value = "";
}

// ── OPEN / CLOSE POPUP ────────────────────────────────────────
function openAddTransacPopup() {
    document.getElementById("AddTransacPopup").classList.add("show");
    resetAddTransacForms();
}

function closeAddTransacPopup() {
    document.getElementById("AddTransacPopup").classList.remove("show");
    document.getElementById("AddRowPopup").classList.remove("show");
    resetAddTransacForms();
    clearPopupMessage();
}

function resetAddTransacForms() {
    document.getElementById("addTransacForm").reset();
    document.getElementById("addRepairForm").reset();
    clearPMSForm();
    document.querySelector(".pms-table tbody").innerHTML    = '';
    document.querySelector(".repair-table tbody").innerHTML = '';
    document.querySelector(".pms-table").classList.remove("show");
    document.querySelector(".repair-table").classList.remove("show");
    document.querySelector(".pms-addrow").classList.remove("show");
    document.querySelector(".repair-addrow").classList.remove("show");
    document.getElementById("workTags").innerHTML = '';
    document.getElementById("totalCostDisplay").textContent = '₱ 0.00';
    Object.keys(repairRows).forEach(k => delete repairRows[k]);
}

// ── TOTAL COST ────────────────────────────────────────────────
function updateTotalCost() {
    let total = 0;
    document.querySelectorAll(".work-tag").forEach(tag => {
        total += parseFloat(tag.dataset.price) * parseInt(tag.dataset.qty);
        total += parseFloat(tag.dataset.labor) || 0;
    });
    document.getElementById("totalCostDisplay").textContent = `₱ ${total.toFixed(2)}`;
}

// ── PREVENT NEGATIVE LABOR ────────────────────────────────────
document.getElementById("itemLaborInput").addEventListener("input", function () {
    if (this.value < 0) this.value = 0;
});

// ── ADD WORK TAG ──────────────────────────────────────────────
function addWorkTag() {
    const workSel    = document.getElementById("workInput");
    const qtyInput   = document.getElementById("qtyInput");
    const laborInput = document.getElementById("itemLaborInput");

    const val       = workSel.value.trim();
    const qtyText   = qtyInput.value.trim();
    const itemLabor = parseFloat(laborInput.value) || 0;

    if (!val) {
        alert("Please select a part.");
        return;
    }

    if (!validParts.has(val)) {
        alert(`"${val}" is not a recognized part. Please select a valid part from the list.`);
        workSel.focus();
        return;
    }

    if (!/^[1-9]\d*$/.test(qtyText)) {
        alert("Quantity must be a whole number greater than 0.");
        qtyInput.focus();
        return;
    }

    if (itemLabor < 0) {
        alert("Labor cost cannot be negative.");
        laborInput.focus();
        return;
    }

    const qty   = parseInt(qtyText, 10);
    const price = parseFloat(partPriceMap[val] ?? 0);

    const tag = document.createElement("div");
    tag.className     = "work-tag";
    tag.dataset.price = price;
    tag.dataset.qty   = qty;
    tag.dataset.name  = val;
    tag.dataset.labor = itemLabor;

    tag.innerHTML = `
        <span>${val} x${qty} — ₱${(price * qty).toFixed(2)} + Labor ₱${itemLabor.toFixed(2)}</span>
        <button type="button" onclick="this.parentElement.remove(); updateTotalCost()">✕</button>
    `;

    document.getElementById("workTags").appendChild(tag);
    updateTotalCost();

    workSel.value    = "";
    qtyInput.value   = "";
    laborInput.value = "";
}

// ── TYPE CHANGE ───────────────────────────────────────────────
document.getElementById("type").addEventListener("change", function () {
    document.querySelector(".pms-addrow").classList.remove("show");
    document.querySelector(".repair-addrow").classList.remove("show");
    document.querySelector(".pms-table").classList.remove("show");
    document.querySelector(".repair-table").classList.remove("show");
    document.getElementById("AddRowPopup").classList.add("show");

    if (this.value === "PMS") {
        document.querySelector(".pms-addrow").classList.add("show");
        document.querySelector(".pms-table").classList.add("show");
    } else if (this.value === "Repair") {
        document.querySelector(".repair-addrow").classList.add("show");
        document.querySelector(".repair-table").classList.add("show");
    }
});

// ── REPAIR: ADD ROW ───────────────────────────────────────────
const repairRows = {};

document.querySelector(".repair-addrow input[type='submit']").addEventListener("click", function (e) {
    e.preventDefault();

    const plateNo  = document.getElementById("repairPlateNo").value.trim();
    const mechanic = document.getElementById("repairMechanicName").value.trim();

    if (!plateNo) { alert("Please select a Plate No."); return; }

    if (!validPlates.has(plateNo)) {
        alert(`Plate number "${plateNo}" does not exist. Please select a valid plate number.`);
        document.getElementById("repairPlateNo").focus();
        return;
    }

    const tags = document.querySelectorAll(".work-tag");
    if (tags.length === 0) { alert("Please add at least one part using '+ Add Part'."); return; }

    const model = modelMap[plateNo] ?? '—';
    const works = [];
    let laborSum = 0, partsSum = 0;
    tags.forEach(tag => {
        const price = parseFloat(tag.dataset.price) || 0;
        const qty   = parseInt(tag.dataset.qty) || 1;
        const labor = parseFloat(tag.dataset.labor) || 0;
        laborSum += labor;
        partsSum += price * qty;
        works.push({ name: tag.dataset.name, qty, price, labor, subtotal: price * qty });
    });

    const totalCost = partsSum + laborSum;
    const tbody = document.querySelector(".repair-table tbody");

    works.forEach((work, index) => {
        const tr = document.createElement("tr");
        tr.dataset.plate = plateNo;
        tr.innerHTML = `
            <td>${index === 0 ? plateNo : ''}</td>
            <td>${index === 0 ? model : ''}</td>
            <td>${work.name} x${work.qty}</td>
            <td>₱${work.subtotal.toFixed(2)}</td>
            <td>₱${work.labor.toFixed(2)}</td>
            <td>${index === 0 ? '₱' + totalCost.toFixed(2) : ''}</td>
            <td>${index === 0 ? mechanic || '—' : ''}</td>
        `;
        tbody.appendChild(tr);
    });

    repairRows[plateNo] = {
        count:    (repairRows[plateNo]?.count ?? 0) + works.length,
        labor:    (repairRows[plateNo]?.labor ?? 0) + laborSum,
        parts:    (repairRows[plateNo]?.parts ?? 0) + partsSum,
        total:    (repairRows[plateNo]?.total ?? 0) + totalCost,
        mechanic: mechanic
    };

    document.getElementById("addRepairForm").reset();
    document.getElementById("workTags").innerHTML = "";
    document.getElementById("totalCostDisplay").textContent = "₱ 0.00";
});

// ── PMS: ADD ROW ──────────────────────────────────────────────
document.querySelector(".pms-addrow input[type='submit']").addEventListener("click", function (e) {
    e.preventDefault();

    const plateNo  = document.getElementById("pmsPlateNo").value.trim();
    const mechanic = document.getElementById("pmsMechanicName").value.trim();

    if (!plateNo) { alert("Please select a Plate No."); return; }

    if (!validPlates.has(plateNo)) {
        alert(`Plate number "${plateNo}" does not exist. Please select a valid plate number.`);
        document.getElementById("pmsPlateNo").focus();
        return;
    }

    const model = modelMap[plateNo] ?? '—';
    const works = ['Clean Carburetor', 'Change Oil', 'Tune Up'];
    const tbody = document.querySelector(".pms-table tbody");

    works.forEach((work, index) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>${index === 0 ? plateNo : ''}</td>
            <td>${index === 0 ? model : ''}</td>
            <td>${work}</td>
            <td>${index === 0 ? '₱ 900.00' : ''}</td>
            <td>${index === 0 ? mechanic || '—' : ''}</td>
        `;
        tbody.appendChild(tr);
    });

    clearPMSForm();
});

// ── CLEAR (Repair) ────────────────────────────────────────────
document.querySelector(".repair-addrow input[type='reset']").addEventListener("click", function () {
    document.getElementById("workTags").innerHTML = "";
    document.getElementById("totalCostDisplay").textContent = "₱ 0.00";
});

// ── SAVE TRANSACTION ──────────────────────────────────────────
document.querySelector(".addtransac-save-btn").addEventListener("click", async function () {
    const date = document.getElementById("transacDate").value;
    const type = document.getElementById("type").value;

    if (!date || !type) {
        showPopupMessage("Please fill in date and type.", "error");
        return;
    }

    const saveBtn = document.getElementById("addTransacBtn");
    setButtonLoading(saveBtn, true, "Saving...");

    try {
        let payload = { type, date, transactions: [] };

        if (type === "Repair") {
            const plateGroups = {};

            document.querySelectorAll(".repair-table tbody tr").forEach(tr => {
                const cells = tr.querySelectorAll("td");
                const plate = tr.dataset.plate;
                const work  = cells[2].textContent.trim();
                const cost  = cells[3].textContent.replace('₱','').trim();

                if (!plate || !work) return;

                if (!plateGroups[plate]) {
                    plateGroups[plate] = {
                        plate_no: plate,
                        mechanic: repairRows[plate]?.mechanic || '',
                        labor:    repairRows[plate]?.labor ?? 0,
                        rows:     []
                    };
                }

                const match = work.match(/^(.+?) x(\d+)$/);
                const name  = match ? match[1] : work;
                const qty   = match ? parseInt(match[2]) : 1;
                const price = parseFloat(cost) / qty || 0;

                plateGroups[plate].rows.push({ work_name: name, qty, price });
            });

            payload.transactions = Object.values(plateGroups);

        } else if (type === "PMS") {
            const plateGroups = {};

            document.querySelectorAll(".pms-table tbody tr").forEach(tr => {
                const cells = tr.querySelectorAll("td");
                const plate = cells[0].textContent.trim();

                if (!plate) return;

                if (!plateGroups[plate]) {
                    plateGroups[plate] = {
                        plate_no: plate,
                        mechanic: cells[4].textContent.trim() === '—' ? '' : cells[4].textContent.trim(),
                        rows: [
                            { work_name: 'Clean Carburetor' },
                            { work_name: 'Change Oil' },
                            { work_name: 'Tune Up' }
                        ]
                    };
                }
            });

            payload.transactions = Object.values(plateGroups);
        }

        if (payload.transactions.length === 0) {
            showPopupMessage("Please add at least one row.", "error");
            return;
        }

        const res  = await fetch('save-transaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = JSON.parse(await res.text());

        if (data.success) {
            closeAddTransacPopup();
            location.reload();
        } else {
            showPopupMessage(data.error || "Failed to save. Please try again.", "error");
        }
    } catch (err) {
        console.error(err);
        showPopupMessage("Something went wrong.", "error");
    } finally {
        setButtonLoading(saveBtn, false, "Save");
    }
});

function showPopupMessage(msg, type) {
    let el = document.getElementById("addTransacMessage");
    if (!el) {
        el = document.createElement("p");
        el.id = "addTransacMessage";
        el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
        const btnRow = document.querySelector("#AddTransacPopup .addtransac-btn-row");
        if (btnRow) btnRow.parentNode.insertBefore(el, btnRow);
    }
    el.textContent = msg;
    el.style.color = type === "success" ? "#2e7d32" : "#c62828";
}

function clearPopupMessage() {
    const el = document.getElementById("addTransacMessage");
    if (el) el.remove();
}

function setButtonLoading(btn, isLoading, label) {
    if (!btn) return;
    btn.disabled = isLoading;
    btn.textContent = label;
}
</script>
</body>
</html>