<?php 
require_once 'auth.php';
require 'db.php';

$transactionId = $_GET['id'] ?? 0;

$stmt = $conn->prepare("
    SELECT 
        rt.transaction_id,
        rt.date,
        rt.type_id,
        rt.plate_no,
        rt.labor_cost,
        h.hub_name,
        mc.model_id,
        m.first_name,
        m.last_name,
        st.type
    FROM repair_transactions rt
    JOIN motorcycles mc ON rt.plate_no = mc.plate_no
    JOIN hubs h ON mc.hub_id = h.hub_id
    LEFT JOIN mechanics m ON rt.mechanic_id = m.mechanic_id
    JOIN service_types st ON rt.type_id = st.type_id
    WHERE rt.transaction_id = ?
");
$stmt->bind_param('i', $transactionId);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    header('Location: transactions.php');
    exit;
}

// Fetch parts used
$partsStmt = $conn->prepare("
    SELECT 
        tp.quantity,
        tp.unit_price,
        r.repair_id,
        r.part_name
    FROM transaction_parts tp
    JOIN repair r ON tp.repair_id = r.repair_id
    WHERE tp.transaction_id = ?
");
$partsStmt->bind_param('i', $transactionId);
$partsStmt->execute();
$parts = $partsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all repair parts for dropdown
$allParts = $conn->query("SELECT repair_id, part_name, price FROM repair ORDER BY part_name ASC")->fetch_all(MYSQLI_ASSOC);

$isRepair = $transaction['type_id'] !== 'PMS';

$parts_total = array_sum(array_map(fn($p) => $p['unit_price'] * $p['quantity'], $parts));
$actual_labor  = floatval($transaction['labor_cost']);
$overall_total = $parts_total + $actual_labor;

if (!$isRepair) {
    $actual_labor  = 900;
    $overall_total = 900;
}

$pageTitle = "Transaction no. " . $transaction['transaction_id']; 
$pageName_sc = "Transaction no. " . $transaction['transaction_id'];
$pageName_lc = "transaction";

$headerBtnClass = "back-btn";
$headerBtnAction = "history.back()";
$headerBtnIcon = "く";
$isRepair = $transaction['type_id'] !== 'PMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>Transaction Details | Hammerdad</title>
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=delete,edit,print" />
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #d9d9d9; display: flex; flex-direction: column; color: #212328; }
        .main-panel { max-height: 609px; }
        .section-divider { border: none; height: 1px; background-color: #d1d1d1; width: 100%; margin: 15px auto 10px auto; flex-shrink: 0; }
        
        .main-transac-table { width: 100%; border-collapse: collapse; text-align: center;}
        .main-transac-table thead th {
            font-weight: bold; letter-spacing: .9px; text-transform: uppercase; background-color: #b71513;
            color: #fff; padding: 10px; border-bottom: 1px solid #e2e2e2; white-space: nowrap;
            position: sticky; top: 0; z-index: 1; 
        }
        .main-transac-table tbody tr  { border-bottom: 1px solid #e2e2e2; transition: background .12s; }
        .main-transac-table tbody tr:last-child { border-bottom: none; }
        .main-transac-table tbody tr:hover { background: #f7f7f5; }
        .main-transac-table tbody td  { padding: 11px 10px; color: #3d3d3d; vertical-align: middle; }

        .material-symbols-outlined { font-size: 26px; font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; }
        .actions-wrapper { display: flex; flex-direction: row; margin-left: auto; margin-right: 10px; gap: 3px; }
        .print-btn { display: flex; justify-content: center; align-items: center; height: 40px; width: 130px; margin-right: 20px; gap: 10px; border-radius: 5px; border: none; background-color: #b71513; color: white; cursor: pointer; }
        .print-btn:hover { background-color: #d31512; }
        .print-btn .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .action-btn { border: none; background-color: transparent; color: #a4a4a4; display: flex; flex-direction: column; justify-self: center; align-self: flex-start; padding: 5px; cursor: pointer; }
        .action-btn:hover { color: #1e1f22; }
        .details { display: flex; flex-direction: row; gap: 10px; font-size: 18px; color: #212328; }
        .transac { justify-content: flex-start; }
        .plateno-btn { font-size: 35px; font-family: Tahoma, sans-serif; font-weight: bold; color: #212328; background:none; border:none;}
        .plateno-btn:hover { cursor:pointer; }
        .total-price { display: flex; flex-direction: column; font-size: 18px; margin: 20px 0 0 auto; }
        .details p { margin: 0; padding: 2px 0; }
        .info-wrapper { gap: 20px; }
        .info-wrapper p { text-align: left; margin: 0; padding: 3px;}
        .info-wrapper.b { font-weight: bold; }
        .wrap-separator { width: 60px; }

        .badge { display: inline-block; font-weight: 700; padding: 2px 8px; border-radius: 20px; letter-spacing: .4px; }
        .badge-gray  { background: #f0f0f0; color: #6b6b6b; }
        .badge-blue  { background: #e8f0fe; color: #1a56c4; }

        /* ── OVERLAY ── */
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.35); justify-content: center; align-items: center; z-index: 101; }
        .overlay.show { display: flex; }

        /* ── EDIT POPUP ── */
        .edit-popup { background: #f1f1f1; width: 700px; max-height: 90vh; overflow-y: auto; padding: 22px; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .edit-inner { background: #fff; border: 1px solid #ddd; border-radius: 7px; padding: 18px; display: flex; flex-direction: column; gap: 12px; max-height: 75vh; overflow-y: auto;}
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .header-2 { font-size: 26px; font-weight: bold; color: #b71513; margin: 0; padding-bottom: 10px; }
        .x-btn { font-size: 22px; color: #b0b0b0; border: none; background: none; cursor: pointer; padding: 0 4px; }
        .x-btn:hover { color: #575757; }

        .edit-table { width: 100%; border-collapse: collapse; text-align: center; margin-bottom: 6px; }
        .edit-table th { background-color: #b71513; color: #fff; font-weight: bold; padding: 4px 4px; font-size: 13px; }
        .edit-table td { background-color: #f8f8f8; padding: 5px 4px; font-size: 13px; }
        .edit-table tbody tr:nth-child(even) td { background-color: #f0f0f0; }

        .add-part-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .add-part-row select, .add-part-row input { height: 32px; padding: 2px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        .add-part-row select { flex: 1; min-width: 180px; }
        .add-part-row input[type="number"] { width: 60px; }
        .add-part-row button { height: 32px; padding: 0 14px; border: none; border-radius: 6px; background: #b71513; color: #fff; cursor: pointer; font-size: 13px; }
        .add-part-row button:hover { background: #d31512; }

        .labor-row { display: flex; justify-content: space-between; align-items: center; font-size: 15px; font-weight: bold; padding-top: 4px; }
        .labor-row input { width: 120px; height: 32px; padding: 2px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; text-align: right; }

        .edit-total-row { display: flex; justify-content: flex-end; gap: 30px; font-size: 15px; font-weight: bold; padding-top: 4px; border-top: 1px solid #ddd; }

        .edit-btn-row { display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px; }
        .edit-save-btn { height: 38px; padding: 0 24px; border: none; border-radius: 5px; background: #b71513; color: #fff; font-size: 16px; cursor: pointer; }
        .edit-save-btn:hover { background: #d31512; }
        .edit-cancel-btn { height: 38px; padding: 0 24px; border: none; border-radius: 5px; background: #cbcbcb; color: #000; font-size: 16px; cursor: pointer; }
        .edit-cancel-btn:hover { background: #bdbdbd; }

        /* toast */
        #toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 9px 22px; border-radius: 6px; font-size: 13px; z-index: 9999; display: none; opacity: 0; transition: opacity 0.3s; }
        #toast.show { display: block; opacity: 1; }
        #toast.error { background: #b71513; }
        #toast.success { background: #2a7a2a; }
    </style>
</head>
<body>

<div class="loader-wrapper"><div class="loader"></div></div>
<?php include 'page-essentials.php'; ?>

<div class="main-panel">

    <div class="details transac">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img src="images/transac-profile.png" style="height:125px;border:1px solid #d1d1d1;border-radius:100px; margin-right:20px;">

            <div style="padding: 0">
                
            <p style="color:#757575; margin-bottom: 20px;">
                <span>
                    <button class="plateno-btn"
                        onclick="window.location.href='motorcycle-details.php?id=<?= urlencode($transaction['plate_no']) ?>'">
                        <?= htmlspecialchars($transaction['plate_no']) ?>
                    </button> 
                </span>
                <?= htmlspecialchars($transaction['model_id'] . ' — ' . ($transaction['hub_name'])) ?>
            </p>

                <?php $tClass = $isRepair ? 'badge-gray' : 'badge-blue'; ?>

                <div class="details">
                    <p style="font-size:20px;"><?= date('F j, Y', strtotime($transaction['date'])) ?></p>
                    <p><span class="badge <?= $tClass ?>"><?= strtoupper($transaction['type']) ?></span></p>
                </div>

                <p style="font-size:20px;"><span style="font-weight: bold;">Mechanic : </span>
                        <?= ($transaction['first_name'] ?? '') ? htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']) : '—' ?>
            </div>
        </div>

        <div class="actions-wrapper">
            <button class="print-btn" onclick="printReceipt()"><span class="material-symbols-outlined">print</span> Print</button>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <?php if ($isRepair): ?>
            <button class="action-btn" onclick="openEditPopup()"><span class="material-symbols-outlined">edit</span></button>
            <?php endif; ?>
            <button class="action-btn" onclick="openDelPopup('<?= $transactionId ?>', 'delete-transaction.php', 'Transaction', 'transactions.php')">
                <span class="material-symbols-outlined">delete</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <hr class="section-divider">

    <div style="overflow-y: auto;">
        <table class="main-transac-table" id="partsTable">
            <thead>
                <tr>
                    <th>WORK</th>
                    <?php if ($isRepair): ?>
                    <th>QTY</th>
                    <th>UNIT PRICE</th>
                    <th>TOTAL PRICE</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="partsTbody">
                <?php if (!$isRepair): ?>
                    <!-- PMS: fixed 3 work items, no qty/price columns -->
                    <tr><td>Clean Carburetor</td></tr>
                    <tr><td>Change Oil</td></tr>
                    <tr><td>Tune Up</td></tr>
                <?php elseif (empty($parts)): ?>
                    <tr id="noPartsRow"><td colspan="4">No parts used.</td></tr>
                <?php else: ?>
                    <?php foreach($parts as $part): ?>
                    <tr data-repairid="<?= $part['repair_id'] ?>"
                        data-price="<?= $part['unit_price'] ?>"
                        data-qty="<?= $part['quantity'] ?>">
                        <td><?= htmlspecialchars($part['part_name']) ?></td>
                        <td><?= $part['quantity'] ?></td>
                        <td>₱<?= number_format($part['unit_price'], 2) ?></td>
                        <td>₱<?= number_format($part['unit_price'] * $part['quantity'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="total-price">
        <?php if ($isRepair): ?>
        <div style="display: flex; flex-direction: row; gap: 50px;">
            <div class="info-wrapper">
                <p>Parts Subtotal</p>
                <p>Labor Subtotal</p>
            </div>
            <div class="info-wrapper">
                <p id="displayParts">₱<?= number_format($parts_total, 2) ?></p>
                <p id="displayLabor">₱<?= number_format($actual_labor, 2) ?></p>
            </div>
        </div>  
        
        <hr class="section-divider" style="margin: 5px 0;">
        <?php endif; ?>

        <div style="display: flex; flex-direction: row; justify-content: space-between;">
            <div class="info-wrapper b">
                <p>Total</p>
            </div>
            <div class="info-wrapper b">
                <p id="displayTotal">₱<?= number_format($overall_total, 2) ?></p>
            </div>
        </div>  

        
    </div>
</div>

<!-- ── EDIT POPUP ── -->
<div class="overlay" id="editOverlay" onclick="closeEditPopup()">
    <div class="edit-popup" onclick="event.stopPropagation()">
        <div class="header-row">
            <p class="header-2">Edit Transaction</p>
            <button class="x-btn" onclick="closeEditPopup()">✕</button>
        </div>
        <div class="edit-inner">

            <!-- existing + new parts table -->
            <table class="edit-table">
                <thead>
                    <tr><th>WORK</th><th>QTY</th><th>UNIT PRICE</th><th>TOTAL</th><th></th></tr>
                </thead>
                <tbody id="editTbody">
                    <?php foreach($parts as $part): ?>
                    <tr data-repairid="<?= $part['repair_id'] ?>"
                        data-price="<?= $part['unit_price'] ?>"
                        data-qty="<?= $part['quantity'] ?>"
                        data-name="<?= htmlspecialchars($part['part_name']) ?>">
                        <td><?= htmlspecialchars($part['part_name']) ?></td>
                        <td><?= $part['quantity'] ?></td>
                        <td>₱<?= number_format($part['unit_price'], 2) ?></td>
                        <td>₱<?= number_format($part['unit_price'] * $part['quantity'], 2) ?></td>
                        <td><button class="del-part-btn" onclick="removeEditRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- add part row -->
            <div class="add-part-row">
                <select id="newPartSelect">
                    <option value="" disabled selected>-- Add Part --</option>
                    <?php foreach ($allParts as $p): ?>
                    <option value="<?= $p['repair_id'] ?>"
                            data-name="<?= htmlspecialchars($p['part_name']) ?>"
                            data-price="<?= $p['price'] ?>">
                        <?= htmlspecialchars($p['part_name']) ?> — ₱<?= number_format($p['price'], 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" id="newPartQty" value="1" min="1" placeholder="qty">
                <button onclick="addEditPart()">+ Add</button>
            </div>

            <hr style="border:none;height:0.5px;background:#ddd;">

            <!-- labor input -->
            <div class="labor-row">
                <label>Labor Cost</label>
                <input type="number" id="editLaborInput" value="<?= $actual_labor ?>" min="0" step="0.01" oninput="recalcEditTotal()">
            </div>

            <!-- totals -->
            <div class="edit-total-row">
                <span>Parts: <span id="editPartsTotal">₱<?= number_format($parts_total, 2) ?></span></span>
                <span>Labor: <span id="editLaborTotal">₱<?= number_format($actual_labor, 2) ?></span></span>
                <span>Total: <span id="editOverallTotal">₱<?= number_format($overall_total, 2) ?></span></span>
            </div>

            <div class="edit-btn-row">
                <button class="edit-cancel-btn" onclick="closeEditPopup()">Cancel</button>
                <button class="edit-save-btn" onclick="saveEdit()">Save</button>
            </div>
        </div>
    </div>
</div>

<div id="toast"></div>
<script src="layout.js"></script>
<script>
const TRANSACTION_ID = <?= $transactionId ?>;

// rows pending deletion (existing transaction_parts_ids)
let toDelete = [];
// rows pending insertion {repair_id, name, price, qty}
let toAdd    = [];

// ── EDIT POPUP ────────────────────────────────────────────
function openEditPopup() {
    toDelete = [];
    toAdd    = [];
    document.getElementById('editOverlay').classList.add('show');
    recalcEditTotal();
}

function closeEditPopup() {
    document.getElementById('editOverlay').classList.remove('show');
}

// Remove row from edit table (mark for deletion if existing, just remove if new)
function removeEditRow(btn) {
    const tr = btn.closest('tr');
    const repairid = tr.dataset.repairid;
    if (repairid) {
        toDelete.push(repairid);  // store repair_id, not tpid
    } else {
        const idx = parseInt(tr.dataset.addIdx);
        toAdd.splice(idx, 1);
    }
    tr.remove();
    recalcEditTotal();
}

// Add new part to edit table
function addEditPart() {
    const sel   = document.getElementById('newPartSelect');
    const opt   = sel.selectedOptions[0];
    const qty   = parseInt(document.getElementById('newPartQty').value) || 1;

    if (!opt || !opt.value) { showToast('Please select a part.', 'error'); return; }

    const repair_id = opt.value;
    const name      = opt.dataset.name;
    const price     = parseFloat(opt.dataset.price);
    const idx       = toAdd.length;

    toAdd.push({ repair_id, name, price, qty });

    const tbody = document.getElementById('editTbody');
    const tr    = document.createElement('tr');
    tr.dataset.addIdx = idx;
    tr.dataset.price  = price;
    tr.dataset.qty    = qty;
    tr.innerHTML = `
        <td>${name}</td>
        <td>${qty}</td>
        <td>₱${price.toFixed(2)}</td>
        <td>₱${(price * qty).toFixed(2)}</td>
        <td><button class="del-part-btn" onclick="removeEditRow(this)">✕</button></td>
    `;
    tbody.appendChild(tr);

    sel.value = '';
    document.getElementById('newPartQty').value = 1;
    recalcEditTotal();
}

// Recalculate totals in edit popup
function recalcEditTotal() {
    let partsSum = 0;
    document.querySelectorAll('#editTbody tr').forEach(tr => {
        partsSum += parseFloat(tr.dataset.price || 0) * parseInt(tr.dataset.qty || 0);
    });
    const labor = parseFloat(document.getElementById('editLaborInput').value) || 0;
    const total = partsSum + labor;

    document.getElementById('editPartsTotal').textContent  = '₱' + partsSum.toFixed(2);
    document.getElementById('editLaborTotal').textContent  = '₱' + labor.toFixed(2);
    document.getElementById('editOverallTotal').textContent = '₱' + total.toFixed(2);
}

// Save edits via AJAX
function saveEdit() {
    const labor = parseFloat(document.getElementById('editLaborInput').value) || 0;

    fetch('update-transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            transaction_id: TRANSACTION_ID,
            labor_cost:     labor,
            to_delete:      toDelete,
            to_add:         toAdd
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Saved successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.error || 'Save failed.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
}

// ── TOAST ─────────────────────────────────────────────────
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    setTimeout(() => { t.className = ''; }, 3000);
}

function printReceipt() {
    const w = window.open('', '_blank', 'width=800,height=900');
    w.document.write(`
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt #<?= str_pad($transactionId, 5, '0', STR_PAD_LEFT) ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; color:#1a1a1a; background:#fff; padding:40px 50px; font-size:14px; }
    .brand-name { font-size:28px; font-weight:900; color:#b71513; letter-spacing:1px; }
    .brand-sub { font-size:12px; color:#666; margin-top:2px; }
    .receipt-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; }
    .receipt-no { font-size:18px; font-weight:bold; color:#b71513; }
    .receipt-meta { text-align:right; font-size:13px; color:#444; line-height:1.7; }
    .divider-red { border:none; border-top:3px solid #b71513; margin:18px 0; }
    .divider-gray { border:none; border-top:1px solid #ddd; margin:14px 0; }
    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 30px; margin-bottom:24px; font-size:13.5px; }
    .info-grid .label { color:#777; font-weight:600; }
    .info-grid .value { color:#1a1a1a; font-weight:500; }
    table { width:100%; border-collapse:collapse; margin-bottom:6px; }
    thead tr { background-color:#b71513; color:#fff; }
    thead th { padding:9px 10px; text-align:left; font-size:13px; font-weight:600; }
    thead th:last-child { text-align:right; }
    tbody tr:nth-child(odd) { background:#fafafa; }
    tbody tr:nth-child(even) { background:#f3f3f3; }
    tbody td { padding:8px 10px; font-size:13px; border-bottom:1px solid #ececec; }
    tbody td:last-child { text-align:right; font-weight:500; }
    tbody td:nth-child(2), tbody td:nth-child(3) { text-align:center; }
    .totals-block { margin-top:10px; display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
    .tot-row { display:flex; justify-content:space-between; width:240px; font-size:13.5px; }
    .tot-row span:first-child { color:#555; font-weight:600; }
    .tot-row.final { font-size:16px; font-weight:bold; border-top:2px solid #b71513; padding-top:6px; margin-top:4px; }
    .tot-row.final span { color:#b71513; }
    .service-badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:bold; }
    .badge-pms { background:#e8f0fe; color:#1a56c4; border:1px solid #1a56c4; }
    .badge-repair { background:#f0f0f0; color:#6b6b6b; border:1px solid #6b6b6b; }
    .mechanic-block { margin-top:40px; display:flex; justify-content:flex-end; }
    .mechanic-sign { text-align:center; width:180px; }
    .sig-line { border-top:1px solid #555; margin-bottom:5px; }
    .sig-label { font-size:12px; color:#666; }
    .footer { margin-top:36px; text-align:center; font-size:12px; color:#999; line-height:1.8; }
    @media print { body { padding:20px 30px; } @page { size:A4; margin:15mm; } }
</style>
</head>
<body>

<div class="receipt-header">
    <div>
        <div class="brand-name">HAMMERDAD</div>
        <div class="brand-sub">Motorcycle Services & Parts</div>
        <div class="brand-sub" style="margin-top:6px;">Hub: <?= htmlspecialchars($transaction['hub_name']) ?></div>
    </div>
    <div class="receipt-meta">
        <div class="receipt-no">Receipt #<?= str_pad($transactionId, 5, '0', STR_PAD_LEFT) ?></div>
        <div>Date: <?= date('F j, Y', strtotime($transaction['date'])) ?></div>
        <div><span class="service-badge <?= $isRepair ? 'badge-repair' : 'badge-pms' ?>"><?= $isRepair ? 'REPAIR' : 'PMS' ?></span></div>
    </div>
</div>

<hr class="divider-red">

<div class="info-grid">
    <span class="label">Plate No.</span><span class="value"><?= htmlspecialchars($transaction['plate_no']) ?></span>
    <span class="label">Model</span><span class="value"><?= htmlspecialchars($transaction['model_id']) ?></span>
    <span class="label">Hub</span><span class="value"><?= htmlspecialchars($transaction['hub_name']) ?></span>
    <span class="label">Mechanic</span><span class="value"><?= ($transaction['first_name'] ?? '') ? htmlspecialchars($transaction['first_name'].' '.$transaction['last_name']) : '—' ?></span>
</div>

<hr class="divider-gray">

<?php if (!$isRepair): ?>
<table>
    <thead><tr><th>WORK PERFORMED</th><th style="text-align:center;">STATUS</th></tr></thead>
    <tbody>
        <tr><td>Clean Carburetor</td><td style="text-align:center;color:#2a7a2a;font-weight:bold;">✓ Done</td></tr>
        <tr><td>Change Oil</td><td style="text-align:center;color:#2a7a2a;font-weight:bold;">✓ Done</td></tr>
        <tr><td>Tune Up</td><td style="text-align:center;color:#2a7a2a;font-weight:bold;">✓ Done</td></tr>
    </tbody>
</table>
<div class="totals-block">
    <div class="tot-row final"><span>TOTAL</span><span>₱900.00</span></div>
</div>
<?php else: ?>
<table>
    <thead><tr><th>PARTS / WORK</th><th style="text-align:center;">QTY</th><th style="text-align:center;">UNIT PRICE</th><th>AMOUNT</th></tr></thead>
    <tbody>
        <?php foreach($parts as $part): ?>
        <tr>
            <td><?= htmlspecialchars($part['part_name']) ?></td>
            <td><?= $part['quantity'] ?></td>
            <td>₱<?= number_format($part['unit_price'], 2) ?></td>
            <td>₱<?= number_format($part['unit_price'] * $part['quantity'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="totals-block">
    <div class="tot-row"><span>Parts Subtotal</span><span>₱<?= number_format($parts_total, 2) ?></span></div>
    <div class="tot-row"><span>Labor</span><span>₱<?= number_format($actual_labor, 2) ?></span></div>
    <div class="tot-row final"><span>TOTAL</span><span>₱<?= number_format($overall_total, 2) ?></span></div>
</div>
<?php endif; ?>

<div class="mechanic-block">
    <div class="mechanic-sign">
        <div class="sig-line"></div>
        <div class="sig-label"><?= ($transaction['first_name'] ?? '') ? htmlspecialchars($transaction['first_name'].' '.$transaction['last_name']) : 'Mechanic' ?></div>
        <div class="sig-label" style="color:#aaa;">Signature over Printed Name</div>
    </div>
</div>

<div class="footer">
    <strong>Thank you for trusting Hammerdad!</strong><br>
    Please keep this receipt for your records.<br>
    For concerns, please contact your hub manager.
</div>

</body></html>
    `);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 400);
}

</script>
</body>
</html>