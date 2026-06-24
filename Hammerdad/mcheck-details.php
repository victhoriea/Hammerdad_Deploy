<?php 
require 'db.php';

$mcheckId = $_GET['id'] ?? 0;

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
        m.last_name
    FROM repair_transactions rt
    JOIN motorcycles mc ON rt.plate_no = mc.plate_no
    JOIN hubs h ON mc.hub_id = h.hub_id
    LEFT JOIN mechanics m ON rt.mechanic_id = m.mechanic_id
    WHERE rt.transaction_id = ?
");
$stmt->bind_param('i', $mcheckId);
$stmt->execute();
$mcheck = $stmt->get_result()->fetch_assoc();

if (!$mcheck) {
    header('Location: transactions.php');
    exit;
}

// Fetch parts used
$partsStmt = $conn->prepare("
    SELECT tp.quantity, r.repair_id, r.part_name, r.price
    FROM transaction_parts tp
    JOIN repair r ON tp.repair_id = r.repair_id
    WHERE tp.transaction_id = ?
");
$partsStmt->bind_param('i', $mcheckId);
$partsStmt->execute();
$parts = $partsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all repair parts for dropdown
$allParts = $conn->query("SELECT repair_id, part_name, price FROM repair ORDER BY part_name ASC")->fetch_all(MYSQLI_ASSOC);

$isRepair = $mcheck['type_id'] !== 'PMS';

$parts_total   = array_sum(array_map(fn($p) => $p['price'] * $p['quantity'], $parts));
$actual_labor  = floatval($mcheck['labor_cost']);
$overall_total = $parts_total + $actual_labor;

if (!$isRepair) {
    $actual_labor  = 900;
    $overall_total = 900;
}

$pageTitle = "Transaction no. " . $mcheck['transaction_id']; 
$headerBtnClass = "back-btn";
$headerBtnAction = "window.location.href='transactions.php'";
$headerBtnIcon = "く";
$isRepair = $mcheck['type_id'] !== 'PMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Transaction Details | Hammerdad</title>
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=delete,edit,print" />
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #d9d9d9; display: flex; flex-direction: column; color: #212328; }
        .main-panel { max-height: 609px; }
        .section-divider { border: none; height: 1px; background-color: #d1d1d1; width: 100%; margin: 15px auto 10px auto; flex-shrink: 0; }
        .main-transac-table { width: 100%; border-collapse: collapse; text-align: center; }
        .main-transac-table th { background-color: #b71513; color: #fff; font-weight: bold; padding: 4px 2px; }
        .main-transac-table td { background-color: #f4f4f4; padding: 7px 2px; }
        .main-transac-table tbody tr:nth-child(even) td { background-color: #f0f0f0; }
        .material-symbols-outlined { font-size: 26px; font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; }
        .actions-wrapper { display: flex; flex-direction: row; margin-left: auto; margin-right: 10px; gap: 3px; }
        .print-btn { display: flex; justify-content: center; align-items: center; height: 40px; width: 130px; margin-right: 20px; gap: 10px; border-radius: 5px; border: none; background-color: #b71513; color: white; cursor: pointer; }
        .print-btn:hover { background-color: #d31512; }
        .print-btn .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .action-btn { border: none; background-color: transparent; color: #a4a4a4; display: flex; flex-direction: column; justify-self: center; align-self: flex-start; padding: 5px; cursor: pointer; }
        .action-btn:hover { color: #1e1f22; }
        .details { display: flex; flex-direction: row; gap: 10px; font-size: 20px; color: #212328; }
        .transac { justify-content: flex-start; }
        .total-price { margin-top: 20px; justify-content: flex-end; }
        .details p { margin: 0; padding-bottom: 10px; }
        .details img { height:110px;border:1px solid #d1d1d1;border-radius:100px;margin-right:10px; }
        .info-wrapper-1 { display: flex; flex-direction: column; text-align: left; font-weight: bold; padding-top: 8px; }
        .info-wrapper-2 { display: flex; flex-direction: column; text-align: left; padding-top: 8px; }
        .wrap-separator { width: 60px; }

        /* delete row btn in table */
        .del-part-btn { border: none; background: none; color: #ccc; cursor: pointer; font-size: 13px; padding: 0 4px; }
        .del-part-btn:hover { color: #b71513; }

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

    </style>
</head>
<body>

<div class="loader-wrapper">
    <div class="loader"></div>
</div>

<?php include 'page-essentials.php'; ?>

<div class="main-panel">

    <div class="details transac">
        <img src="images/transac-profile.png">

        <div class="info-wrapper-1">
            <p>Plate no.</p><p>Model</p><p>Hub</p>
        </div>
        <div class="info-wrapper-1">
            <p>:</p><p>:</p><p>:</p>
        </div>
        <div class="info-wrapper-2">
            <p><?= htmlspecialchars($mcheck['plate_no']) ?></p>
            <p><?= htmlspecialchars($mcheck['model_id']) ?></p>
            <p><?= htmlspecialchars($mcheck['hub_name']) ?></p>
        </div>

        <div class="wrap-separator"></div>

        <div class="info-wrapper-1">
            <p>Date</p><p>Type</p>
        </div>
        <div class="info-wrapper-1">
            <p>:</p><p>:</p>
        </div>
        <div class="info-wrapper-2">
            <p><?= date('m/d/y', strtotime($mcheck['date'])) ?></p>
            <p><?= htmlspecialchars($mcheck['mileage']) ?></p>
        </div>

        <div class="actions-wrapper">
            <button class="print-btn" onclick="printReceipt()"><span class="material-symbols-outlined">print</span> Print</button>
            <button class="action-btn" onclick="openDelPopup()"><span class="material-symbols-outlined">delete</span></button>
        </div>
    </div>

    <hr class="section-divider">

    <div style="overflow-y:auto;">
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
                        data-price="<?= $part['price'] ?>"
                        data-qty="<?= $part['quantity'] ?>">
                        <td><?= htmlspecialchars($part['part_name']) ?></td>
                        <td><?= $part['quantity'] ?></td>
                        <td>₱<?= number_format($part['price'], 2) ?></td>
                        <td>₱<?= number_format($part['price'] * $part['quantity'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="details total-price">
        <div class="info-wrapper-1">
            <?php if ($isRepair): ?>
            <p>Total Labor Price</p>
            <?php endif; ?>
            <p>Total Price</p>
        </div>
        <div class="info-wrapper-1">
            <?php if ($isRepair): ?>
            <p>:</p>
            <?php endif; ?>
            <p>:</p>
        </div>
        <div class="info-wrapper-2">
            <?php if ($isRepair): ?>
            <p id="displayLabor">₱<?= number_format($actual_labor, 2) ?></p>
            <?php endif; ?>
            <p id="displayTotal">₱<?= number_format($overall_total, 2) ?></p>
        </div>
    </div>
</div>


<script src="layout.js"></script>

<script>


</script>
</body>
</html>