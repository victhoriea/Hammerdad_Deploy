<?php 
require_once 'auth.php';
require_once 'db.php';

$hubId = $_GET['id'] ?? '';

$stmt = $conn->prepare("SELECT h.hub_id, h.hub_name, m.first_name, m.last_name, m.email FROM hubs h LEFT JOIN managers m ON h.manager_id = m.manager_id WHERE h.hub_id = ?");
$stmt->bind_param("s", $hubId);
$stmt->execute();
$hub = $stmt->get_result()->fetch_assoc();

if (!$hub) {
    header('Location: hubs.php');
    exit;
}

$pageTitle = $hub['hub_name'] . " Details";
$pageName_sc = $hub['hub_name'] . " Hub";
$pageName_lc = "hub";

$headerBtnClass = "back-btn";
$headerBtnAction = "history.back()";
$headerBtnIcon = "く";

$motorResult = $conn->prepare("
    SELECT 
        mc.plate_no, 
        mo.model_id,
        MAX(CASE WHEN rt.type_id = 'PMS' THEN rt.date END) AS last_pms,
        MAX(CASE WHEN rt.type_id = 'REP' THEN rt.date END) AS last_repair
    FROM motorcycles mc
    LEFT JOIN models mo ON mc.model_id = mo.model_id
    LEFT JOIN repair_transactions rt ON mc.plate_no = rt.plate_no
    WHERE mc.hub_id = ?
    GROUP BY mc.plate_no, mo.model_id
");
$motorResult->bind_param("s", $hubId);
$motorResult->execute();
$motorcycles = $motorResult->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Hub Details | Hammerdad</title>

    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=delete,edit,visibility" />
    
    <style>
        body {
            font-family: Tahoma, sans-serif; 
            background-color: #d9d9d9;
            display: flex;
            flex-direction: column;
            color: #212328;
        } 

        .section-divider {
            border: none;
            height: 1px;
            background-color: #d1d1d1;
            width: 100%;
            margin: 15px auto 10px auto;
            flex-shrink: 0;
        }

        .main-panel {
            min-height: unset;
            max-height: 609px;
        }

        .main-panel search {  
            position: relative;       
            margin-left: auto;
            margin-top: 10px;
        }

        .search-input {
            width: 300px;
        }

        .search-wrapper .x-btn {
            position: absolute;
            left: 285px;
        }

        .hub-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: center; }
        .hub-table thead th {
            font-size: 10px; font-weight: 700; letter-spacing: .9px; text-transform: uppercase; background-color: #b71513;
            color: #fff; padding: 7px; border-bottom: 1px solid #e2e2e2; white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .hub-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .hub-table tbody tr:last-child { border-bottom: none; }
        .hub-table tbody tr:hover { background: #f7f7f5; }
        .hub-table tbody td  { padding: 7px; color: #3d3d3d; vertical-align: middle; }

        .material-symbols-outlined {
            font-variation-settings:
            'FILL' 0,
            'wght' 300,
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

        .actions-wrapper {
            display: flex;
            flex-direction: row;
            margin-left: auto;
            margin-right: 10px;
            gap: 3px;
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

        .hub-details {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            gap: 10px;
            
            font-size: 18px; 
            color: #212328;
        }

        .hub-details p {
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

        .small-text {
            font-weight: normal;
            margin: 0;
            padding-top: 15px;
            color: #757575;
            zoom: 0.85;
        }

        .separator {
            color: #ffffff00;
            padding-top: 15px;
            zoom: 0.85;
        }

        .info-wrapper-2 {
            display: flex;
            flex-direction: column;
            text-align: left;
            padding-top: 8px;
        }   

        .addhub-popup-box {
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

        .addhub-popup-box.show {
            display: block;
        }

        .addhub-wrapper {
            background-color: #fff;
            font-size: 16px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
        }

        .addhub-row {
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

        .addhub-wrapper p {
            margin: 0;
            padding-top: 15px;
            padding-bottom: 0;
            color: #757575;
            zoom: 0.85;
        }

        .addhub-wrapper input {
            width: 170px;
            box-sizing: border-box;
            height: 35px;
            padding: 2px 15px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin: 2px 0 7px 0;
        }

        .optional-label {
            font-size: 11px;
            color: #aaa;
            margin-left: 3px;
        }

        .addhub-btn-row {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .addhub-popup-btn {
            width: 35%;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .addhub-save-btn { background-color: #b71513; color: white; }
        .addhub-save-btn:hover { background-color: #d31512; }
        .addhub-cancel-btn { background-color: #CBCBCB; color: #000; }
        .addhub-cancel-btn:hover { background-color: rgb(189, 189, 189); }
        
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

        <div class="hub-details">
            <img src="images/hub-profile.png" style="height: 115px; border-style: solid; border-color: #d1d1d1; border-width: 1px; border-radius: 100px; margin-right: 10px;">

            <div style="display: flex; flex-direction: column;">
            <p style="font-size: 35px; font-weight: bold;"
            id="displayHubName"><?= htmlspecialchars($hub['hub_name']) ?> Hub</p>

            <p class="small-text">MANAGER INFO</p>
                <div style="display: flex; gap:10px;">
                    <p style="font-weight: bold;">Name: </p>
                    <p id="displayFullName"><?= htmlspecialchars($hub['first_name'] . ' ' . $hub['last_name']) 
                    . ' (' . ($hub['email'] ?? '—') . ')' ?></p>
                </div>
            </div>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="actions-wrapper">
                <div class="edit-container">
                    <button class="action-btn" onclick="openEditHubPopup()">
                        <span class="material-symbols-outlined">edit</span>
                    </button>

                    <div class="addhub-popup-box" id="EditHubPopup" onclick="event.stopPropagation()">
                        <div class="header-row">
                            <p class="header-2">Edit Hub</p>
                            <button class="x-btn" onclick="closeEditHubPopup()">✕</button>
                        </div>

                        <div class="addhub-wrapper">
                            <form onsubmit="return false;">
                                <div class="addhub-row">
                                    <label>Location</label>
                                    <!-- ✅ removed disabled so the name is editable -->
                                    <input id="editHubName" value="<?= htmlspecialchars($hub['hub_name']) ?>">
                                </div>

                                <p>MANAGER INFO</p>
                                <div class="addhub-row">
                                    <label>First Name</label>
                                    <input id="editFirstName" value="<?= htmlspecialchars($hub['first_name']) ?>">
                                </div>
                                <div class="addhub-row">
                                    <label>Last Name</label>
                                    <input id="editLastName" value="<?= htmlspecialchars($hub['last_name']) ?>">
                                </div>
                                <div class="addhub-row">
                                    <!-- ✅ labelled as optional -->
                                    <label>E-mail</label>
                                    <input id="editEmail" value="<?= htmlspecialchars($hub['email'] ?? '') ?>" placeholder="(Optional)">
                                </div>
                            </form>

                            <div class="addhub-btn-row">
                                <button class="addhub-popup-btn addhub-cancel-btn" onclick="closeEditHubPopup()">Cancel</button>
                                <button id="editHubBtn" onclick="saveHubEdit()" class="addhub-popup-btn addhub-save-btn">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button class="action-btn" onclick="openDelPopup()">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <hr class="section-divider">

        <search>
            <form class="search-wrapper" onsubmit="return false;">
                <input class="search-input" type="text" id="searchInput" placeholder="Search Plate Number" oninput="applyFilters()">
                <button class="x-btn" type="reset" onclick="setTimeout(applyFilters, 0)">✕</button>
                <button class="search-btn" onclick="applyFilters()">🔍︎</button>
            </form>
        </search>

        <div style="overflow-y: auto; margin-top: 15px;">
            <table class="hub-table">
                <thead>
                    <tr>
                        <th style="width:205px;"><button class="sort-btn white"id="sortBtn" onclick="toggleSort()">
                            🡱</button>
                            PLATE NO.
                        </th>
                        <th>MODEL</th>
                        <th>LAST PMS</th>
                        <th>LAST REPAIR</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="hubDetailsTableBody">
                    <?php foreach($motorcycles as $motor): ?>
                    <tr
                        data-plate="<?= htmlspecialchars($motor['plate_no']) ?>"
                    >
                        <td><?= htmlspecialchars($motor['plate_no']) ?></td>
                        <td><?= htmlspecialchars($motor['model_id']) ?></td>
                        <td><?= $motor['last_pms'] ? date('m/d/y', strtotime($motor['last_pms'])) : '—' ?></td>
                        <td><?= $motor['last_repair'] ? date('m/d/y', strtotime($motor['last_repair'])) : '—' ?></td>
                        <td>
                            <button class="view-btn" onclick="window.location.href='motorcycle-details.php?id=<?= urlencode($motor['plate_no']) ?>'">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($motorcycles)): ?>
                    <tr>
                        <td colspan="5" style="color:#aaa; padding: 15px;">No motorcycles in this hub.</td>
                    </tr>
                    <?php endif; ?>
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

    function openEditHubPopup() {
        document.getElementById("EditHubPopup").classList.toggle("show");
    }

    function closeEditHubPopup() {
        document.getElementById("EditHubPopup").classList.remove("show");
        clearPopupMessage("editHubMessage");
    }

    function saveHubEdit() {
        const hubName   = document.getElementById("editHubName").value.trim();
        const firstName = document.getElementById("editFirstName").value.trim();
        const lastName  = document.getElementById("editLastName").value.trim();
        const email     = document.getElementById("editEmail").value.trim();

        // ✅ email no longer required
        if (!hubName || !firstName || !lastName) {
            showPopupMessage("editHubMessage", "Please fill in Location, First Name, and Last Name.", "error");
            return;
        }

        const saveBtn = document.getElementById("editHubBtn");
        setButtonLoading(saveBtn, true, "Saving...");

        fetch("update-hub.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            // ✅ hub_name now included in the request body
            body: JSON.stringify({
                hub_id: "<?= $hubId ?>",
                hub_name: hubName,
                first_name: firstName,
                last_name: lastName,
                email: email
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // ✅ update all display values by ID (more reliable than querySelectorAll index)
                document.getElementById("displayHubName").textContent = hubName;
                document.getElementById("displayFullName").textContent = firstName + " " + lastName + " (" + (email || "—") + ")";
                showPopupMessage("editHubMessage", "Changes saved successfully!", "success");
            } else {
                showPopupMessage("editHubMessage", data.message || "Save failed.", "error");
            }
        })
        .catch(() => showPopupMessage("editHubMessage", "An error occurred.", "error"))
        .finally(() => setButtonLoading(saveBtn, false, "Save"));
    }

    // ─── DELETE HUB ─────────────────────────────────────────────────────────

    function openDelPopup() {
        document.getElementById("DelPopup").classList.add("show");
    }

    function cancelDelPopup() {
        document.getElementById("DelPopup").classList.remove("show");
    }

    function confirmDelete() {
        fetch("delete-hub.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ hub_id: "<?= $hubId ?>" }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = "hubs.php";
            } else {
                alert(data.message || "Failed to delete hub.");
                cancelDelPopup();
            }
        })
        .catch(() => {
            alert("An error occurred while deleting.");
            cancelDelPopup();
        });
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────

    function showPopupMessage(id, msg, type) {
        let el = document.getElementById(id);
        if (!el) {
            el = document.createElement("p");
            el.id = id;
            el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
            const btnRow = document.querySelector("#EditHubPopup .addhub-btn-row");
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
    const search   = document.getElementById("searchInput").value.toLowerCase().trim();

        const rows = document.querySelectorAll("#hubDetailsTableBody tr[data-plate]");
        rows.forEach(row => {
            const plate    = row.dataset.plate.toLowerCase();
            const matchSearch = !search || plate.includes(search);
            
            row._filtered = (matchSearch);
            row.style.display = 'none';
        });

        // Sort
        const tbody = document.getElementById("hubDetailsTableBody");
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
        const rows = Array.from(document.querySelectorAll("#hubDetailsTableBody tr[data-plate]"))
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