<?php 
require_once 'auth.php';
require_once 'db.php';

$pageTitle = "Hubs";

$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

$result = $conn->query("SELECT h.hub_id, h.hub_name, CONCAT(m.first_name, ' ', m.last_name) AS manager_name, m.email, COUNT(mc.plate_no) AS motorcycle_count FROM hubs h LEFT JOIN managers m ON h.manager_id = m.manager_id LEFT JOIN motorcycles mc ON h.hub_id = mc.hub_id GROUP BY h.hub_id");

$hubs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Hubs | Hammerdad</title>

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

        .hub-table { width: 100%; border-collapse: collapse; text-align: center;}
        .hub-table thead th {
            font-weight: 700; letter-spacing: .9px; text-transform: uppercase; background-color: #fff;
            color: #b71513; padding: 0 10px 10px; border-bottom: 1px solid #e2e2e2; white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .hub-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .hub-table tbody tr:last-child { border-bottom: none; }
        .hub-table tbody tr:hover { background: #f7f7f5; }
        .hub-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }


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

        .add-container {
            position: relative;
            display: inline-block;
            overflow: visible;
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

            top: calc(100% + 10px); 
            right: 0;              
            z-index: 9999;
        }

        .addhub-popup-box.show {
            display: block;
        }

        .addhub-wrapper {
            background-color: #fff;
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

        .hubdetails-box {
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

        .hubdetails-box.show {
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

        .hub-details {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            gap: 10px;
            
            font-size: 20px; 
            color: #212328;
        }

        .hub-details p {
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
                <input class="search-input" type="text" id="searchInput" placeholder="Search Hub Name" oninput="applyFilters()">
                <button class="x-btn" type="reset" onclick="setTimeout(applyFilters, 0)">✕</button>
                <button class="search-btn" onclick="applyFilters()">🔍︎</button>
            </form>
        </search>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="add-container">
            <button class="add-btn" onclick="openAddHubPopup()">+ Add Hub</button>

            <div class="addhub-popup-box" id="AddhubPopup" onclick="event.stopPropagation()">
                <div class="header-row">
                    <p class="header-2">Add Hub</p>
                    <button class="x-btn" onclick="closeAddHubPopup()">✕</button>
                </div>

                <div class="addhub-wrapper">
                    <form id="addHubForm">
                        <div class="addhub-row">
                            <label>Location</label>
                            <input id="hubLocation" name="hub_name" required placeholder="ex. Balagtas">
                        </div>

                        <p>MANAGER INFO</p>
                        <div class="addhub-row">
                            <label>First Name</label>
                            <input id="firstName" name="first_name" required placeholder="ex. Juan">
                        </div>
                        <div class="addhub-row">
                            <label>Last Name</label>
                            <input id="lastName" name="last_name" placeholder="ex. Dela Cruz">
                        </div>
                        <div class="addhub-row">
                            <label>E-mail</label>
                            <input id="email" name="email" placeholder="ex. juan@gmail.com">
                        </div>

                        <div class="addhub-btn-row">
                            <button class="addhub-popup-btn addhub-cancel-btn" onclick="closeAddHubPopup()">Cancel</button>
                            <button type="button" id="addHubBtn" onclick="saveHub(event)" class="addhub-popup-btn addhub-save-btn">Add</button>
                        </div>
                    </form>

                    
                </div>
            </div>
        
        </div>
        <?php endif; ?>
    </div>

    <div class = "main-panel">

        <table class = "hub-table">
            <thead>
                <tr>
                    <th>HUB</th>
                    <th>MANAGER</th>
                    <th>E-MAIL</th>
                    <th>NO. OF MOTORCYCLES</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="hubTableBody">
                <?php foreach($hubs as $hub): ?>
                <tr
                    data-hub="<?= htmlspecialchars($hub['hub_name']) ?>"
                >
                    <td><?= htmlspecialchars($hub['hub_name']) ?></td>
                    <td><?= htmlspecialchars($hub['manager_name']) ?></td>
                    <td><?= htmlspecialchars($hub['email'] ?? '—') ?></td>
                    <td><?= $hub['motorcycle_count'] ?></td>
                    <td>
                        <button class="view-btn"
                                onclick="window.location.href='hub-details.php?id=<?= urlencode($hub['hub_id']) ?>'">
                            <span class="material-symbols-outlined">
                                visibility
                            </span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                
            </tbody>
        </table>
    </div>

    <script src="layout.js"></script>

<script>

    function openAddHubPopup() {
        document.getElementById("AddhubPopup").classList.toggle("show");
        document.getElementById("addHubForm").reset();
        clearPopupMessage("addHubMessage");
    }

    function closeAddHubPopup() {
        document.getElementById("AddhubPopup").classList.remove("show");
        document.getElementById("addHubForm").reset();
        clearPopupMessage("addHubMessage");
    }

    // ─── ADD HUB ────────────────────────────────────────────────────────────────
    
    function saveHub(event) {
    event.preventDefault();

    const hub       = document.getElementById("hubLocation").value.trim();
    const firstName = document.getElementById("firstName").value.trim();
    const lastName  = document.getElementById("lastName").value.trim();
    const email     = document.getElementById("email").value.trim();

    if (!hub || !firstName) {
        showPopupMessage("addHubMessage", "Please fill in all required fields.", "error");
        return;
    }

    if (email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showPopupMessage("addHubMessage", "Please enter a valid e-mail address.", "error");
            return;
        }
    }

    const addBtn = document.getElementById("addHubBtn");
    setButtonLoading(addBtn, true, "Adding...");         

    fetch("add-hub.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ hub_name: hub, first_name: firstName, last_name: lastName, email }),
    })
        .then((res) => { if (!res.ok) throw new Error("Server error: " + res.status); return res.json(); })
        .then((data) => {
        if (data.success) {
            // Append new row to table
            const tbody = document.getElementById("hubTableBody");
            const row = document.createElement("tr");
            row.innerHTML = `
            <td>${hub}</td>
            <td>${firstName} ${lastName}</td>
            <td>${email || '—'}</td>
            <td>0</td>
            <td>
                <button class="view-btn" onclick="window.location.href='hub-details.php?id=${data.hub_id}'">
                <span class="material-symbols-outlined">visibility</span>
                </button>
            </td>`;
            tbody.appendChild(row);

            showPopupMessage("addHubMessage", "Hub added successfully!", "success");
            document.getElementById("addHubForm").reset();

        } else {
            showPopupMessage("addHubMessage", data.message || "Failed to add hub.", "error");
        }
        })
        .catch((err) => {
        console.error(err);
        showPopupMessage("addHubMessage", "An error occurred. Please try again.", "error");
        })
        .finally(() => setButtonLoading(addBtn, false, "Add"));
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────────

    function showPopupMessage(id, msg, type) {
    let el = document.getElementById(id);
    if (!el) {
        el = document.createElement("p");
        el.id = id;
        el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
        const btnRow = document.querySelector("#addHubForm .addhub-btn-row");
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

    function applyFilters() {
    const search   = document.getElementById("searchInput").value.toLowerCase().trim();

        const rows = document.querySelectorAll("#hubTableBody tr[data-hub]");
        rows.forEach(row => {
            const hub    = row.dataset.hub.toLowerCase();
            const matchSearch = !search || hub.includes(search);
            row.style.display = (matchSearch) ? '' : 'none';
        });
    }
    
</script>

</body>
</html>