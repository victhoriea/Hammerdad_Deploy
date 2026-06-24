<?php 
require_once 'auth.php';
require_once 'db.php';

$pageTitle = "Outbox";

$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

$outbox = $conn->query("
    SELECT
        n.notif_id,
        n.date,
        n.plate_no,
        n.message,
        h.hub_name,
        CONCAT(mg.first_name, ' ', mg.last_name) AS recipient
    FROM notifications n
    JOIN motorcycles mc
        ON n.plate_no = mc.plate_no
    JOIN hubs h
        ON mc.hub_id = h.hub_id
    JOIN managers mg
        ON h.manager_id = mg.manager_id
    ORDER BY n.date DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Outbox | Hammerdad</title>

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
        }

        .outbox-table { width: 100%; border-collapse: collapse; text-align: center;}
        .outbox-table thead th {
            font-weight: 700; letter-spacing: .9px; text-transform: uppercase; background-color: #fff;
            color: #b71513; padding: 0 10px 10px; border-bottom: 1px solid #e2e2e2;  white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .outbox-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .outbox-table tbody tr:last-child { border-bottom: none; }
        .outbox-table tbody tr:hover { background: #f7f7f5; }
        .outbox-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }

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
            margin: 0 auto 0 8px;
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

        .addoutbox-popup-box {
            display: none;
            position: absolute;
            background: #f1f1f1;
            width: 310px;
            padding: 10px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 10px;

            top: calc(100% + 10px); 
            right: 0;              
            z-index: 9999;
        }

        .addoutbox-popup-box.show {
            display: block;
        }

        .addoutbox-wrapper {
            background-color: #fff;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
        }

        .addoutbox-row {
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

        .addoutbox-wrapper p {
            margin: 0;
            padding-top: 15px;
            color: #757575;
            zoom: 0.85;
        }

        .addoutbox-wrapper input {
            width: 170px;
            box-sizing: border-box;
            height: 35px;
            padding: 2px 15px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin: 2px 0 7px 0;
        }

        .addoutbox-btn-row {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .addoutbox-popup-btn {
            width: 35%;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .addoutbox-save-btn { background-color: #b71513; color: white; }
        .addoutbox-save-btn:hover { background-color: #d31512; }
        .addoutbox-cancel-btn { background-color: #CBCBCB; color: #000; }
        .addoutbox-cancel-btn:hover { background-color: rgb(189, 189, 189); }

        .outboxdetails-box {
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

        .outboxdetails-box.show {
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

        .outbox-details {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            gap: 10px;
            
            font-size: 20px; 
            color: #212328;
        }

        .outbox-details p {
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
        
    </style>
    
</head>

<body>

    <!-- ESSENTIALS -->

    <div class="loader-wrapper">
        <div class="loader"></div>
    </div>

    <?php include 'page-essentials.php'; ?>

    <!-- MAIN BODY -->

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
    </div>

    <div class = "main-panel">

        <div style="overflow-y:auto;">
            <table class = "outbox-table">
                <thead>
                    <tr>
                        <th><button class="sort-btn"id="sortBtn" onclick="toggleSort()">
                        🡱</button>
                        SENT
                    </th>
                        <th>RECIPIENT</th>
                        <th>HUB</th>
                        <th>PLATE NO.</th>
                        <th>MESSAGE</th>
                    </tr>
                </thead>
                <tbody id="outboxTableBody">
                    <?php foreach($outbox as $row): ?>
                    <tr 
                        data-plate="<?= htmlspecialchars($row['plate_no']) ?>"
                        data-hub="<?= htmlspecialchars($row['hub_name']) ?>"
                    >
                        <td><?= date('M d, Y h:i A', strtotime($row['date'])) ?></td>
                        <td><?= htmlspecialchars($row['recipient']) ?></td>
                        <td><?= htmlspecialchars($row['hub_name']) ?></td>
                        <td><?= htmlspecialchars($row['plate_no']) ?></td>
                        <td><?= htmlspecialchars($row['message']) ?></td>
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

    function openAddOutboxPopup() {
        document.getElementById("AddoutboxPopup").classList.toggle("show");
        document.getElementById("addOutboxForm").reset();
        clearPopupMessage("addOutboxMessage");
    }

    function closeAddOutboxPopup() {
        document.getElementById("AddoutboxPopup").classList.remove("show");
        document.getElementById("addOutboxForm").reset();
        clearPopupMessage("addOutboxMessage");
    }

    // ── SEARCH ───────────────────────────────────────────────
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

        const rows = document.querySelectorAll("#outboxTableBody tr[data-plate]");
        rows.forEach(row => {
            const plate       = row.dataset.plate.toLowerCase();
            const rowHub      = row.dataset.hub.toLowerCase();
            const matchSearch = !search || plate.includes(search);
            const matchHub    = !hub    || rowHub === hub;
            
            row._filtered = (matchSearch && matchHub);
            row.style.display = 'none';
        });

        // Sort
        const tbody = document.getElementById("outboxTableBody");
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
        const rows = Array.from(document.querySelectorAll("#outboxTableBody tr[data-plate]"))
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