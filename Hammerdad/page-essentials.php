<?php 
require_once 'db.php';
?>

<div class="header">

    <div class="leftside-header">

        <button class="<?php echo $headerBtnClass; ?>"
                onclick="<?php echo $headerBtnAction; ?>">

            <?php echo $headerBtnIcon; ?>

        </button>

        <div class="text-leftside-header">
            <p style="font-family: Segoe UI;">
                HAMMERDAD SERVICE CENTER
            </p>

            <p style="font-size: 22px; font-weight: bold;">
                <?php echo $pageTitle; ?>
            </p>
        </div>

    </div>

    <div class="rightside-header">
        <p style="font-size: 19px;">Hello, <?= htmlspecialchars($_SESSION['first_name']) ?>!</p>

        <button class="user-profile-btn" onclick="openProfile()">
            <img src="images/user-icon.png"
                 style="border-radius: 50px; height: 40px;">
        </button>
    </div>

</div>

<div class = "side-panel">
    <button class="sidepanel-logo-btn" onclick="openDashboard()">
    <img src = "images/Hammerdad-Logo.png" style = "width: 180px; align-self: center;">
    </button>
    
    <br>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openDashboard()">Dashboard</button>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openHubs()">Hubs</button>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openMotorcycles()">Motorcycles</button>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openTransac()">Transactions</button>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openPrices()">Stock & Prices</button>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openMChecks()">Maintenance Check</button>
    <button class = "sidepanel-btn" onclick="setActiveBtn(this); openOutbox()">Outbox</button>

    <hr style="border: none; width: 90%; height: 1px; background-color: #d1d1d1; margin-top: 65px;">
    <button class = "sidepanel-btn" style="margin-top: auto; justify-content: center "onclick="openLogoutPopup()">Log out<img src="images/log-out-icon.png" style="width: 20px; margin-bottom: -4px; margin-left: 130px;"></button>
</div>

<div class="popup-overlay" id="LogoutPopup" onclick="closePopup(this)">
    <div class="logout-box" onclick="event.stopPropagation()">
        <img src="images/Hammerdad-Logo.png" alt="Logo" class="logout-logo">
        <h3>Confirm Log Out</h3>
        <p>You can always log back in at any time. Are you sure you want to log out?</p>

        <button class="popup-logout-btn logout-btn" style="margin-right: 5px;" onclick="confirmLogout()">Log out</button>
        <button class="popup-logout-btn cancel-btn" onclick="cancelPopup()">Cancel</button>
    </div>
</div>

<div class="popup-overlay" id="DelPopup" onclick="closePopup(this)">
    <div class="msg-box" onclick="event.stopPropagation()">
        <img src="images/trash-icon.png" class="msg-box-trash-img">

        <h3>Delete <?php echo $pageName_sc; ?></h3>
        <p>All data of this <?php echo $pageName_lc; ?> will be deleted. Changes cannot be undone once confirmed. Are you sure you want to delete this <?php echo $pageName_lc; ?>?</p>

        <button class="popup-msg-btn gray-btn" onclick="cancelDelPopup()">Cancel</button>
        <button class="popup-msg-btn red-btn" id="confirmDelBtn" onclick="confirmDelete()">Confirm</button>
    </div>
</div>

<script>
    
let pendingDeleteId = null;
let pendingDeletePath = null;
let pendingDeleteRedirect = null;

function openDelPopup(id, path = '', label = 'item', redirect = null) {
    pendingDeleteId       = id;
    pendingDeletePath     = path;
    pendingDeleteRedirect = redirect;
    document.getElementById('DelPopup').style.display = 'flex';
}

function cancelDelPopup() {
    pendingDeleteId = null;
    document.getElementById('DelPopup').style.display = 'none';
}

function closePopup(overlay) {
    pendingDeleteId = null;
    overlay.style.display = 'none';
}

function confirmDelete() {
    if (!pendingDeleteId || !pendingDeletePath) return;

    const btn = document.getElementById("confirmDelBtn");
    setButtonLoading(btn, true, "Deleting...");

    fetch(pendingDeletePath, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: pendingDeleteId }),  // ← here
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            cancelDelPopup();
            if (pendingDeleteRedirect) {
                window.location.href = pendingDeleteRedirect;
            } else {
                location.reload();
            }
        } else {
        alert(data.message || 'Delete failed.');
    }
    })
    .catch(() => alert('Something went wrong.'))
    .finally(() => setButtonLoading(btn, false, 'Confirm'));
}
    
function setButtonLoading(btn, isLoading, label) {
    if (!btn) return;
    btn.disabled    = isLoading;
    btn.textContent = label;
}
</script>