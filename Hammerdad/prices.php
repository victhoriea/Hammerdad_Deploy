<?php 
require_once 'auth.php';
date_default_timezone_set('Asia/Manila');
require 'db.php';

$repairs = $conn->query("
    SELECT r.repair_id, r.part_name, r.price, r.amount AS qty, rt.type_name 
    FROM repair r
    LEFT JOIN repair_type rt ON r.repair_type_id = rt.repair_type_id
    ORDER BY r.part_name ASC
")->fetch_all(MYSQLI_ASSOC);

$totalParts      = count($repairs);
$totalQty        = array_sum(array_column($repairs, 'qty'));
$totalValue      = array_sum(array_map(fn($r) => $r['price'] * $r['qty'], $repairs));
$avgPartPrice = $totalParts > 0 ? array_sum(array_column($repairs, 'price')) / $totalParts : 0;

$pageTitle = "Stock & Prices";
$pageName_sc = "Part";
$pageName_lc = "part";

$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Stock & Prices | Hammerdad</title>

    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=delete,edit" />
    
    <style>
        body {
            font-family: Tahoma, sans-serif; 
            background-color: #d9d9d9;
            display: flex;
            flex-direction: column;
            color: #212328;
        } 

        .main-panel {
            max-height: 850px;
        }

        .dash-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; gap: 16px; flex-wrap: wrap; }
        .dash-header-left h1 { font-size: 26px; font-weight: 700; letter-spacing: -.4px; line-height: 1; margin: 0; }
        .dash-header-left p  { font-size: 13px; color: #6b6b6b; margin-top: 5px; }
        .dash-date-badge { font-size: 12px; background: #fff; border: 1px solid #e2e2e2; border-radius: 20px; padding: 5px 14px; color: #6b6b6b; }

        .kpi-strip { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
        .kpi-strip p { margin: 0; }
        .kpi-card  { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px;
        background: #fff; height: 100px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.04); padding: 15px; border-top: 3px solid transparent; }
        .kpi-card.accent-red   { border-top-color: #b71513; }
        .kpi-card.accent-green { border-top-color: #1a7f4b; }
        .kpi-card.accent-yellow { border-top-color: #c47a0f; }
        .kpi-card.accent-black   { border-top-color: #3d3d3d; }
        .kpi-label { font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #6b6b6b; }
        .kpi-value { font-size: 35px; font-weight: 200; color: #1a1a1a; }
        .kpi-sub   { font-size: 12px; color: #6b6b6b;  }
        .kpi-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 20px; margin-left: 4px; }
    
        .search-row {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 10px;
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper .x-btn {
            position: absolute;
            left: 635px;
        }

        .repair-table { width: 100%; border-collapse: collapse; text-align: center;}
        .repair-table thead th {
            font-weight: 700; letter-spacing: .9px; text-transform: uppercase; background-color: #fff;
            color: #b71513; padding: 0 10px 10px; border-bottom: 1px solid #e2e2e2;  white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .repair-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .repair-table tbody tr:last-child { border-bottom: none; }
        .repair-table tbody tr:hover { background: #f7f7f5; }
        .repair-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }

        .material-symbols-outlined {
            font-variation-settings:
            'FILL' 0,
            'wght' 200,
            'GRAD' 0,
            'opsz' 24
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

        .add-container, .actions-wrapper {
            position: relative;
            display: inline-block;
            overflow: visible;
        }

        .actions-wrapper {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 3px;
        }

        .addrepair-popup-box {
            display: none;
            position: absolute;
            background: #f8f8f8;
            width: 310px;
            padding: 10px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 10px;

            top: calc(100% + 10px); 
            right: 0;              
            z-index: 9999;
        }

        .editrepair-popup-box {
            display: none;
            background: #f8f8f8;
            width: 310px;
            padding: 10px;
            border: 1px solid #DDDDDD;
            border-radius: 10px;
            z-index: 9999;
        }

        .addrepair-popup-box.show, .editrepair-popup-box.show {
            display: block;
        }

        .addrepair-wrapper, .editrepair-wrapper {
            background-color: #fff;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
        }

        .addrepair-row, .editrepair-row {
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

        .addrepair-wrapper p, .editrepair-wrapper p {
            margin: 0;
            padding-top: 15px;
            color: #757575;
            zoom: 0.85;
        }

        .addrepair-wrapper input, .editrepair-wrapper input {
            width: 190px;
            box-sizing: border-box;
            height: 35px;
            padding: 2px 15px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin: 2px 0 7px 0;
        }

        .addrepair-btn-row, .editrepair-btn-row {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .addrepair-popup-btn, .editrepair-popup-btn {
            width: 35%;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .addrepair-save-btn, .editrepair-save-btn { background-color: #b71513; color: white; }
        .addrepair-save-btn:hover, .editrepair-save-btn:hover { background-color: #d31512; }
        .addrepair-cancel-btn, .editrepair-cancel-btn { background-color: #CBCBCB; color: #000; }
        .addrepair-cancel-btn:hover, .editrepair-cancel-btn:hover { background-color: rgb(189, 189, 189); }

        .repairdetails-box {
            display: none;
            background: #f1f1f1;
            width: 850px;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 4px 15px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .repairdetails-box.show {
            display: flex;
        }

        .white-box {
            background-color: #fff;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
        }

        .header-3 {
            font-size: 30px;
            font-weight: bold;
            color: #b71513;
            margin: 0;
            padding: 0;
        }

        .repair-details {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            gap: 10px;
            
            font-size: 20px; 
            color: #212328;
        }

        .repair-details p {
            margin: 0;
            padding-bottom: 10px;
        }

        .info-wrapper {
            display: flex;
            flex-direction: column;
            text-align: left;
            font-weight: bold;
        }

        .info-input-wrapper {
            display: flex;
            flex-direction: column;
            text-align: left;
        }   

        .qty-control {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .qty-control input {
            width: 80px !important;
            text-align: center;
            margin: 0 !important;
        }

        .qty-btn {
            width: 35px;
            height: 35px;
            border: 1px solid #DDDDDD;
            border-radius: 7px;
            background: #fff;
            font-size: 18px;
            font-weight: 300;
            color: #444;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background .15s;
        }

        .qty-btn:hover {
            background: #f0f0f0;
        }

        .qty-btn:active {
            background: #e2e2e2;
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

    <div class = "main-panel">

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="dash-header">
            <div class="dash-header-left">
                <h1>Stock and Price Management</h1>
                <p>Inventory Overview</p>
            </div>
            <span class="dash-date-badge"><?= date('l, F j Y') ?></span>
        </div>

        <div class="kpi-strip">
            <div class="kpi-card accent-red">
                <p class="kpi-label">Total Parts</p>
                <p class="kpi-value"><?= $totalParts ?></p>
            </div>
            <div class="kpi-card accent-green">
                <p class="kpi-label">Total Parts Quantity</p>
                <p class="kpi-value"><?= number_format($totalQty) ?></p>
            </div>
            <div class="kpi-card accent-yellow">
                <p class="kpi-label">Total Parts Value</p>
                <p class="kpi-value">₱<?= number_format($totalValue, 2) ?></p>
            </div>
            <div class="kpi-card accent-black">
                <p class="kpi-label">Avg. Part Price</p>
                <p class="kpi-value">₱<?= number_format($avgPartPrice, 2) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="search-row">
            <search>
                <form class="search-wrapper" onsubmit="return false;">
                    <input class="search-input" type="text" id="searchInput" placeholder="Search Part" oninput="applyFilters()">
                    <button class="x-btn" type="reset" onclick="setTimeout(applyFilters, 0)">✕</button>
                    <button class="search-btn" onclick="applyFilters()">🔍︎</button>
                </form>
            </search>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="add-container">
                <button class="add-btn" onclick="openAddRepairPopup()">+ Add Part</button>

                <div class="addrepair-popup-box" id="AddRepairPopup" onclick="event.stopPropagation()">
                    <div class="header-row">
                        <p class="header-2">Add Repair</p>
                        <button class="x-btn" onclick="closeAddRepairPopup()">✕</button>
                    </div>

                    <div class="addrepair-wrapper">
                        <form id="addRepairForm">
                            <div class="addrepair-row">
                                <label>Part</label>
                                <input id="repairName" name="repair_name" required placeholder="ex. Battery">
                            </div>
                            <div class="addrepair-row">
                                <label>Price</label>
                                <input id="price" name="price" required placeholder="ex. 1,000">
                            </div>

                            <div class="addrepair-row">
                                <label>Quantity</label>
                                <input id="qty" name="qty" type="number" required placeholder="ex. 100">
                            </div>

                            <div class="addrepair-row">
                                <label>Type</label>
                                <select id="repairType" name="repair_type_id" style="width: 190px; height: 35px; padding: 2px 15px; border: 1px solid #DDDDDD; border-radius: 7px; margin: 2px 0 7px 0; background-color: #fff;">
                                    <option value="">-- Select Type --</option>
                                    <option value="BE">Body / Exterior</option>
                                    <option value="BS">Brake System</option>
                                    <option value="CSW">Chassis / Suspension / Wheels</option>
                                    <option value="DT">Drivetrain & Transmission</option>
                                    <option value="ELS">Electrical System</option>
                                    <option value="ES">Engine System</option>
                                    <option value="FF">Fuel & Fluids</option>
                                </select>
                            </div>

                            <div class="addrepair-btn-row">
                                <button class="addrepair-popup-btn addrepair-cancel-btn" onclick="closeAddRepairPopup()">Cancel</button>
                                <button type="button" id="addRepairBtn" onclick="saveRepair(event)" class="addrepair-popup-btn addrepair-save-btn">Add</button>
                            </div>
                            
                        </form>

                        
                    </div>
                </div>
            
            </div>
            <?php endif; ?>
        </div>

        <div style="overflow-y: auto; margin-top: 15px;">
            <table class = "repair-table">
                <thead>
                    <tr>
                        <th style="width:320px;">PART</th>
                        <th>QTY</th>
                        <th>PRICE</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="repairTableBody">
                    <?php foreach($repairs as $repair): ?>
                    <tr 
                    data-id="<?= htmlspecialchars($repair['repair_id']) ?>"
                    data-name="<?= htmlspecialchars($repair['part_name']) ?>"
                    >
                        <td><?= htmlspecialchars($repair['part_name']) ?></td>
                        <td><?= htmlspecialchars($repair['qty']) ?></td>
                        <td>₱<?= number_format($repair['price'], 2) ?></td>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <td>
                            <div class="actions-wrapper">
                                <button class="view-btn" onclick="openEditRepairPopup(this)">
                                    <span class="material-symbols-outlined">edit</span>
                                </button>

                                <button class="view-btn" onclick="openDelPopup('<?= $repair['repair_id'] ?>', 'delete-repair.php', 'Repair')">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <button id="prevPage" onclick="changePage(-1)">&#8249;</button>
            <span id="pageIndicator">Page 1</span>
            <button id="nextPage" onclick="changePage(1)">&#8250;</button>
        </div>
    </div>
    
    <div class="editrepair-popup-box" id="EditRepairPopup" onclick="event.stopPropagation()">
        <div class="header-row">
            <p class="header-2">Edit Repair</p>
            <button class="x-btn" onclick="closeEditRepairPopup()">✕</button>
        </div>

        <div class="editrepair-wrapper">
            <form>
                <div class="editrepair-row">
                    <label>Repair</label>
                    <input id="editRepairName" value="">
                </div>
                <div class="editrepair-row">
                    <label>Price</label>
                    <input id="editPrice" value="">
                </div>
                <div class="editrepair-row">
                    <label>Quantity</label>
                    <input id="editQty" type="number" min="0" value="">
                </div>

                <p>ADD/DEDUCT QUANTITY</p>
                <div class="editrepair-row" style="gap:8px; align-items: center;">
                    <input id="qtyChangeInput" type="number" min="0" placeholder="0"
                    style="margin-top: 7px;">
                    <button type="button" class="qty-btn" onclick="applyQtyChange(1)">+</button>
                    <button type="button" class="qty-btn" onclick="applyQtyChange(-1)">−</button>
                </div>
            </form>

            <div class="editrepair-btn-row">
                <button onclick="closeEditRepairPopup()" class="editrepair-popup-btn editrepair-cancel-btn">Cancel</button>
                <button id="editRepairBtn" onclick="saveRepairEdit()" class="editrepair-popup-btn editrepair-save-btn">Save</button>
            </div>
        </div>
    </div>
    
    <script src="layout.js"></script>

<script>

    let currentRow = null; 

    function openAddRepairPopup() {
        document.getElementById("AddRepairPopup").classList.toggle("show");
        document.getElementById("addRepairForm").reset();
        clearPopupMessage("addRepairMessage");
    }

    function closeAddRepairPopup() {
        document.getElementById("AddRepairPopup").classList.remove("show");
        document.getElementById("addRepairForm").reset();
        clearPopupMessage("addRepairMessage");
    }

    // ─── ADD REPAIR ────────────────────────────────────────────────────────────────

    function saveRepair(event) {
    event.preventDefault();
    const repair      = document.getElementById("repairName").value.trim();
    const price       = document.getElementById("price").value.trim();
    const qty         = document.getElementById("qty").value.trim();
    const repairType  = document.getElementById("repairType").value;

        if (!repair || !price || !repairType) {
            showPopupMessage("addRepairMessage", "Please fill in all required fields.", "error");
            return;
        }

        const addBtn = document.getElementById("addRepairBtn");
        setButtonLoading(addBtn, true, "Adding...");

        fetch("add-repair.php", {
            method:  "POST",
            headers: { "Content-Type": "application/json" },
            body:    JSON.stringify({ repair, price, qty, repair_type_id: repairType }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById("repairTableBody");
                const row   = document.createElement("tr");
                row.dataset.id   = data.repair_id;
                row.dataset.name = repair.toUpperCase();
                row.innerHTML = `
                    <td>${repair.toUpperCase()}</td>
                    <td>${qty}</td>
                    <td>₱${parseFloat(price).toFixed(2)}</td>
                    <td>
                        <div class="actions-wrapper">
                            <button class="view-btn" onclick="openEditRepairPopup(this)">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <button class="view-btn" onclick="openDelPopup('${data.repair_id}', 'delete-repair.php', 'Repair')">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </div>
                    </td>`;

                const newName = repair.toUpperCase();
                const rows = Array.from(tbody.querySelectorAll("tr"));
                let inserted = false;
                for (const existingRow of rows) {
                    const existingName = existingRow.cells[0].textContent.trim().toUpperCase();
                    if (newName.localeCompare(existingName) < 0) {
                        tbody.insertBefore(row, existingRow);
                        inserted = true;
                        break;
                    }
                }
                if (!inserted) tbody.appendChild(row);

                showPopupMessage("addRepairMessage", "Repair added!", "success");
                document.getElementById("addRepairForm").reset();
            } else {
                showPopupMessage("addRepairMessage", data.message || "Failed to add repair.", "error");
            }
        })
        .catch(err => {
            console.error(err);
            showPopupMessage("addRepairMessage", "An error occurred.", "error");
        })
        .finally(() => setButtonLoading(addBtn, false, "Add"));
    }

    // ─── EDIT REPAIR ───────────────────────────────────────────────────────────────

    function openEditRepairPopup(btn) {
        currentRow = btn.closest("tr");
        document.getElementById("editRepairName").value = currentRow.cells[0].textContent.trim();
        document.getElementById("editQty").value        = currentRow.cells[1].textContent.trim();
        document.getElementById("editPrice").value      = currentRow.cells[2].textContent.replace('₱','').trim();

        const popup   = document.getElementById("EditRepairPopup");
        popup.style.display = "block"; // temporarily show to measure height
        const popupH  = popup.offsetHeight;
        const popupW  = popup.offsetWidth;
        popup.style.display = "";

        const rect    = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;

        let top  = spaceBelow >= popupH + 8
            ? rect.bottom + 8          // enough space below
            : rect.top - popupH - 8;  // not enough — show above

        let left = rect.right - popupW;
        if (left < 8) left = 8;
        if (top < 8)  top  = 8;

        popup.style.position = "fixed";
        popup.style.top      = top + "px";
        popup.style.left     = left + "px";
        popup.style.right    = "unset";

        popup.classList.toggle("show");
    }

    function closeEditRepairPopup() {
        document.getElementById("EditRepairPopup").classList.remove("show");
        document.getElementById("qtyChangeInput").value = "";
        clearPopupMessage("editRepairMessage");
    }

    function saveRepairEdit() {
    const repairName = document.getElementById("editRepairName").value.trim();
    const price      = document.getElementById("editPrice").value.trim();
    const qty        = document.getElementById("editQty").value.trim();
    const repair_id  = currentRow ? currentRow.dataset.id : null;

        if (!repairName || !price) {
            showPopupMessage("editRepairMessage", "Please fill in all fields.", "error");
            return;
        }

        const saveBtn = document.getElementById("editRepairBtn");
        setButtonLoading(saveBtn, true, "Saving...");

        fetch("edit-repair.php", {
            method:  "POST",
            headers: { "Content-Type": "application/json" },
            body:    JSON.stringify({ repair_id, repair: repairName, price, qty }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentRow.cells[0].textContent = repairName;
                currentRow.cells[1].textContent = qty;
                currentRow.cells[2].textContent = `₱${parseFloat(price).toFixed(2)}`;
                closeEditRepairPopup();
            } else {
            showPopupMessage("editRepairMessage", data.message || "Failed to save.", "error");
            }
        })
        .catch(err => {
            console.error(err);
            showPopupMessage("editRepairMessage", "An error occurred.", "error");
        })
        .finally(() => setButtonLoading(saveBtn, false, "Save"));
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────────

    function showPopupMessage(id, msg, type) {
        let el = document.getElementById(id);
        if (!el) {
            el = document.createElement("p");
            el.id = id;
            el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
            const btnRow = (id === "addRepairMessage")
                ? document.querySelector("#addRepairForm .addrepair-btn-row")
                : document.querySelector("#EditRepairPopup .editrepair-btn-row");
            if (btnRow) btnRow.parentNode.insertBefore(el, btnRow); // ← this line was missing
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

    const ROWS_PER_PAGE = 15;
    let currentPage = 1;

    function applyFilters() {
    const search   = document.getElementById("searchInput").value.toLowerCase().trim();

        const rows = document.querySelectorAll("#repairTableBody tr[data-name]");
        rows.forEach(row => {
            const name    = row.dataset.name.toLowerCase();
            const matchSearch = !search || name.includes(search);
            
            row._filtered = (matchSearch);
            row.style.display = 'none';
        });

        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const rows = Array.from(document.querySelectorAll("#repairTableBody tr[data-name]"))
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
        applyFilters();
    }

    applyFilters();

    function applyQtyChange(direction) {
    const qtyInput    = document.getElementById("editQty");
    const changeInput = document.getElementById("qtyChangeInput");

    const current = parseInt(qtyInput.value) || 0;
    const amount  = parseInt(changeInput.value) || 0;
    const result  = current + (direction * amount);

    if (result < 0) {
        showPopupMessage("editRepairMessage", "Quantity cannot go below 0.", "error");
        return;
    }

    qtyInput.value = result;
    changeInput.value = "";
    clearPopupMessage("editRepairMessage");
    }
    
</script>

</body>
</html>