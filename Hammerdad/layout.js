
function openProfile() { 
    window.location.href = "user-profile.php"; 
}
function openSidePanel() {
    document.querySelector(".side-panel")?.classList.toggle("open");
    document.querySelector(".upper-panel")?.classList.toggle("move");
    document.querySelector(".main-panel")?.classList.toggle("move");
    document.querySelector(".leftside-header")?.classList.toggle("move");
    document.querySelector(".bottom-panel")?.classList.toggle("move");
    document.querySelector(".bottom2-panel")?.classList.toggle("move");
    document.querySelector(".filter-bar")?.classList.toggle("move");

    const isOpen = document.querySelector(".side-panel").classList.contains("open");
    localStorage.setItem("sidePanelOpen", isOpen);
}

/* TABS */

function openDashboard() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "dashboard.php"; 
}

function openHubs() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "hubs.php"; 
}

function openHubDetails() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "hub-details.php"; 
}

function openMotorcycles() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "motorcycles.php"; 
}

function openMotorcycleDetails() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "motorcycle-details.php"; 
}

function openTransac() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "transactions.php"; 
}

function openTransacDetails() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "transac-details.php"; 
}

function openPrices() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "prices.php"; 
}

function openMChecks() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "maintenance-checks.php"; 
}

function openOutbox() { 
    localStorage.setItem("sidePanelOpen", "true");
    window.location.href = "outbox.php"; 
}

function setActiveBtn(clickedBtn) {
    document.querySelectorAll(".sidepanel-btn").forEach(btn => {
        btn.classList.remove("active");
    });
    clickedBtn.classList.add("active");
    localStorage.setItem("activeBtn", clickedBtn.textContent.trim());
}

const PAGE_PARENT_MAP = {
    "dashboard.php":          "Dashboard",
    "hubs.php":               "Hubs",
    "hub-details.php":        "Hubs",
    "motorcycles.php":        "Motorcycles",
    "motorcycle-details.php": "Motorcycles",
    "transactions.php":       "Transactions",
    "transac-details.php":    "Transactions",
    "prices.php":             "Stock & Prices",
    "maintenance-checks.php": "Maintenance Check",
    "outbox.php":             "Outbox",
};

window.addEventListener("DOMContentLoaded", () => {
    if (localStorage.getItem("sidePanelOpen") === "true") {
        document.querySelector(".side-panel")?.classList.add("open");
        document.querySelector(".upper-panel")?.classList.add("move");
        document.querySelector(".main-panel")?.classList.add("move");
        document.querySelector(".leftside-header")?.classList.add("move");
        document.querySelector(".bottom-panel")?.classList.add("move");
        document.querySelector(".bottom2-panel")?.classList.toggle("move");
        document.querySelector(".filter-bar")?.classList.add("move");
    }

    if (window.location.pathname.includes("user-profile.php")) {
        localStorage.removeItem("activeBtn");
    }

    const currentPage = window.location.pathname.split("/").pop();
    const parentLabel = PAGE_PARENT_MAP[currentPage];
    const activeLabel = parentLabel || localStorage.getItem("activeBtn");

    if (activeLabel) {
        document.querySelectorAll(".sidepanel-btn").forEach(btn => {
            if (btn.textContent.trim() === activeLabel) {
                btn.classList.add("active");
            }
        });
    }
});

function closePopup(overlay) {
    overlay.classList.remove("show");
}

function openLogoutPopup() {
    document.getElementById("LogoutPopup").classList.add("show");
}

function cancelPopup() {
    document.getElementById("LogoutPopup").classList.remove("show");
}

function confirmLogout() {
    const wrapper = document.querySelector('.loader-wrapper');
    wrapper.classList.add('show');
    
    setTimeout(() => {
        wrapper.classList.remove('show');
        
        setTimeout(() => {
        localStorage.clear();
        window.location.href = "log-in.php";
        }, 300); 
    }, 1500);  
}
