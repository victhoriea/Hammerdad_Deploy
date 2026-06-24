<?php 
require_once 'auth.php';
$pageTitle = "Motorcycles";

$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

require 'db.php';

$result = $conn->query("
    SELECT 
        m.plate_no, 
        m.model_id, 
        h.hub_name,
        MAX(CASE WHEN rt.type_id = 'PMS' THEN rt.date END) AS last_pms,
        MAX(CASE WHEN rt.type_id = 'REP' THEN rt.date END) AS last_repair
    FROM motorcycles m
    JOIN hubs h ON m.hub_id = h.hub_id
    LEFT JOIN repair_transactions rt ON m.plate_no = rt.plate_no
    GROUP BY m.plate_no, m.model_id, h.hub_name
    ORDER BY m.plate_no ASC
");
$motorcycles = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Motorcycles | Hammerdad</title>

    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=visibility" rel="stylesheet" />
    
    <style>
        body {
            font-family: Tahoma, sans-serif; 
            background-color: #d9d9d9;
            display: flex;
            flex-direction: column;
            color: #212328;
        } 

        .main-panel {
            min-height: unset;
            height: 520px;
            margin-top: 0;
        }

        .upper-panel {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .upper-panel .add-container {
            margin-left: auto;
        }

        .motorcycle-table { width: 100%; border-collapse: collapse; text-align: center;}
        .motorcycle-table thead th {
            font-weight: bold; letter-spacing: .9px; text-transform: uppercase; background-color: #fff;
            color: #b71513; padding: 0 10px 10px; border-bottom: 1px solid #e2e2e2;  white-space: nowrap;
            position: sticky; top: 0; z-index: 1; -webkit-text-stroke: 0.1px #b71513;
        }
        .motorcycle-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .motorcycle-table tbody tr:last-child { border-bottom: none; }
        .motorcycle-table tbody tr:hover { background: #f7f7f5; }
        .motorcycle-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }

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

        .hub-filter {
            height: 35px;
            width: 130px;
            padding: 2px 10px;
            margin-bottom: 4px;
            border: 1px solid #DDD;
            border-radius: 7px;
            font-size: 13px;
            background: #fff;
            cursor: pointer;
            color: #212328;
        }

        .hub-filter:focus {
            outline: none;
            border-color: #b71513;
        }

        .add-container {
            position: relative;
            display: inline-block;
            overflow: visible;
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
            top: calc(100% + 10px); 
            right: 0;              
            z-index: 9999;
        }

        .addmotor-popup-box.show {
            display: block;
        }

        .addmotor-wrapper {
            background-color: #fff;
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

        .addmotor-popup-box input, select {
            width: 190px;
            box-sizing: border-box;
            height: 35px;
            padding: 2px 15px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin: 5px 0 7px 0;
        }

        .addmotor-popup-box select:disabled {
            color: #B3B3B3;
        }

        .addmotor-btn-row {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
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
    </style>
    
</head>

<body>

    <div class="loader-wrapper">
        <div class="loader"></div>
    </div>

    <?php include 'page-essentials.php'; ?>

    <div class="upper-panel">
        <search>
            <form class="search-wrapper" onsubmit="return false;">
                <input class="search-input" type="text" id="searchInput" placeholder="Search Plate Number" oninput="applyFilters()">
                <button class="x-btn" type="reset" onclick="setTimeout(applyFilters, 0)">✕</button>
                <button class="search-btn" onclick="applyFilters()">🔍︎</button>
            </form>
        </search>

        <select id="hubFilter" class="hub-filter" onchange="applyFilters()">
            <option value="">All Hubs</option>
            <?php
            $hubs_filter = $conn->query("SELECT hub_id, hub_name FROM hubs ORDER BY hub_name");
            while($row = $hubs_filter->fetch_assoc()):
            ?>
            <option value="<?= htmlspecialchars($row['hub_name']) ?>"><?= htmlspecialchars($row['hub_name']) ?></option>
            <?php endwhile; ?>
        </select>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="add-container">
            <button class="add-btn" onclick="openAddMotorcyclePopup()">+ Add Motorcycle</button>
        
            <div class="addmotor-popup-box" id="AddMotorPopup" onclick="event.stopPropagation()">
                <div class="header-row">
                    <p class="header-2">Add Motorcycle</p>
                    <button class="x-btn" onclick="closeAddMotorcyclePopup()">✕</button>
                </div>

                <div class="addmotor-wrapper">
                    <form id="addMotorForm">
                        <div class="addmotor-row">
                            <label>Plate no.</label>
                            <input id="plateNo" name="plate_no" required placeholder="ex. ABC 123">
                        </div>
                        <div class="addmotor-row">
                            <label>Model</label>
                            <select id="model" name="model_id" required>
                                <option disabled selected value="">-- Model --</option>
                                <?php
                                $models = $conn->query("SELECT model_id FROM models");
                                while($row = $models->fetch_assoc()):
                                ?>
                                <option value="<?= htmlspecialchars($row['model_id']) ?>"><?= htmlspecialchars($row['model_id']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="addmotor-row">
                            <label>Hub</label>
                            <select id="hubLocation" name="hub_id" required>
                                <option disabled selected value="">-- Hub --</option>
                                <?php
                                $hubs = $conn->query("SELECT hub_id, hub_name FROM hubs");
                                while($row = $hubs->fetch_assoc()):
                                ?>
                                <option value="<?= htmlspecialchars($row['hub_id']) ?>"><?= htmlspecialchars($row['hub_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="addmotor-btn-row">
                            <button type="button" class="addmotor-popup-btn addmotor-cancel-btn" onclick="closeAddMotorcyclePopup()">Cancel</button>
                            <button type="button" id="addMotorBtn" onclick="saveMotor(event)" class="addmotor-popup-btn addmotor-save-btn">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="main-panel">

        <div style="overflow-y:auto;">
            <table class="motorcycle-table">
                <thead>
                    <tr>
                        <th style="width:205px;"><button class="sort-btn"id="sortBtn" onclick="toggleSort()">
                            🡱</button>
                            PLATE NO.
                        </th>
                        <th>MODEL</th>
                        <th>HUB</th>
                        <th>LAST PMS</th>
                        <th>LAST REPAIR</th>
                        <th> </th>
                    </tr>
                </thead>
                <tbody id="motorTableBody">
                    <?php foreach($motorcycles as $motor): ?>
                    <tr
                        data-plate="<?= htmlspecialchars($motor['plate_no']) ?>"
                        data-hub="<?= htmlspecialchars($motor['hub_name']) ?>"
                    >
                        <td><?= htmlspecialchars($motor['plate_no']) ?></td>
                        <td><?= htmlspecialchars($motor['model_id']) ?></td>
                        <td><?= htmlspecialchars($motor['hub_name']) ?></td>
                        <td><?= $motor['last_pms'] ? date('m/d/y', strtotime($motor['last_pms'])) : '—' ?></td>
                        <td><?= $motor['last_repair'] ? date('m/d/y', strtotime($motor['last_repair'])) : '—' ?></td>
                        <td>
                            <button class="view-btn" onclick="window.location.href='motorcycle-details.php?id=<?= urlencode($motor['plate_no']) ?>'">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </td>
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

    <script src="layout.js"></script>

<script>
    function openAddMotorcyclePopup() {
        document.getElementById("AddMotorPopup").classList.toggle("show");
        document.getElementById("addMotorForm").reset();
        clearPopupMessage("addMotorMessage");
    }

    function closeAddMotorcyclePopup() {
        document.getElementById("AddMotorPopup").classList.remove("show");
        document.getElementById("addMotorForm").reset();
        clearPopupMessage("addMotorMessage");
    }

    function saveMotor(event) {
        event.preventDefault();

        const plateNo = document.getElementById("plateNo").value.trim();
        const model   = document.getElementById("model").value;
        const hub     = document.getElementById("hubLocation").value;

        if (!plateNo || !model || !hub) {
            showPopupMessage("addMotorMessage", "Please fill in all fields.", "error");
            return;
        }

        const addBtn = document.getElementById("addMotorBtn");
        setButtonLoading(addBtn, true, "Adding...");

        fetch('add-motorcycle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plate_no: plateNo, model_id: model, hub_id: hub })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showPopupMessage("addMotorMessage", "Motorcycle added!", "success");
                document.getElementById("addMotorForm").reset();
                setTimeout(() => location.reload(), 1000);
            } else {
                showPopupMessage("addMotorMessage", data.message, "error");
            }
        })
        .catch(() => showPopupMessage("addMotorMessage", "Something went wrong.", "error"))
        .finally(() => setButtonLoading(addBtn, false, "Add"));
    }

    function showPopupMessage(id, msg, type) {
        let el = document.getElementById(id);
        if (!el) {
            el = document.createElement("p");
            el.id = id;
            el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
            const btnRow = document.querySelector("#AddMotorPopup .addmotor-btn-row");
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

    const ROWS_PER_PAGE = 15;
    let currentPage = 1;

    function applyFilters() {
    const search = document.getElementById("searchInput").value.toLowerCase().trim();
    const hub    = document.getElementById("hubFilter").value.toLowerCase().trim();

        const rows = document.querySelectorAll("#motorTableBody tr[data-plate]");
        rows.forEach(row => {
            const plate  = row.dataset.plate.toLowerCase();
            const rowHub = row.dataset.hub.toLowerCase();

            row._filtered = (!search || plate.includes(search))
                        && (!hub    || rowHub === hub);
            row.style.display = 'none';
        });

        // Sort
        const tbody = document.getElementById("motorTableBody");
        const allRows = Array.from(rows);
        allRows.sort((a, b) => {
            const aVal = a.dataset.plate.toLowerCase();
            const bVal = b.dataset.plate.toLowerCase();
            return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        allRows.forEach(row => tbody.appendChild(row));

        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const rows = Array.from(document.querySelectorAll("#motorTableBody tr[data-plate]"))
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
        document.getElementById("hubFilter").value      = '';
        applyFilters();
    }

    applyFilters();

    </script>

</body>
</html>