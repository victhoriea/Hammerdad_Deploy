<?php
require_once 'auth.php';
date_default_timezone_set('Asia/Manila');
require_once 'db.php';

$pageTitle = "Dashboard";
$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";

// ── PERIOD DETECTION (supports ?period=month|year|custom&from=&to=) ──
$period    = $_GET['period'] ?? 'month';
$customFrom= $_GET['from']   ?? '';
$customTo  = $_GET['to']     ?? '';

switch ($period) {
    case 'year':
        $curStart  = date('Y') . '-01-01';
        $curEnd    = date('Y') . '-12-31';
        $prevStart = (date('Y')-1) . '-01-01';
        $prevEnd   = (date('Y')-1) . '-12-31';
        $curLabel  = 'This year (' . date('Y') . ')';
        $prevLabel = 'Last year (' . (date('Y')-1) . ')';
        break;
    case 'custom':
        $curStart  = $customFrom ?: date('Y-m-01');
        $curEnd    = $customTo   ?: date('Y-m-d');
        // mirror same duration before curStart
        $dur = (strtotime($curEnd) - strtotime($curStart));
        $prevEnd   = date('Y-m-d', strtotime($curStart) - 86400);
        $prevStart = date('Y-m-d', strtotime($prevEnd)  - $dur);
        $curLabel  = date('M j, Y', strtotime($curStart)) . ' – ' . date('M j, Y', strtotime($curEnd));
        $prevLabel = date('M j, Y', strtotime($prevStart)) . ' – ' . date('M j, Y', strtotime($prevEnd));
        break;
    default: // month
        $period    = 'month';
        $curStart  = date('Y-m-01');
        $curEnd    = date('Y-m-t');
        $prevStart = date('Y-m-01', strtotime('first day of last month'));
        $prevEnd   = date('Y-m-t',  strtotime('first day of last month'));
        $curLabel  = date('F Y');
        $prevLabel = date('F Y', strtotime('first day of last month'));
        break;
}

// ── HELPER: escape dates ──────────────────────────────────────────
$cs = $conn->real_escape_string($curStart);
$ce = $conn->real_escape_string($curEnd);
$ps = $conn->real_escape_string($prevStart);
$pe = $conn->real_escape_string($prevEnd);

// ─────────────────────────────────────────────────────────────────
// SECTION A: KPI QUERIES (existing)
// ─────────────────────────────────────────────────────────────────
$currentMonth = date('Y-m');
$prevMonth    = date('Y-m', strtotime('first day of last month'));

$totalRes     = $conn->query("SELECT COUNT(*) AS cnt FROM repair_transactions WHERE status_id='COM'");
$totalRepairs = $totalRes->fetch_assoc()['cnt'];

$monthRes     = $conn->query("SELECT COUNT(*) AS cnt FROM repair_transactions WHERE DATE_FORMAT(date,'%Y-%m')='$currentMonth'");
$monthRepairs = $monthRes->fetch_assoc()['cnt'];

$prevRes      = $conn->query("SELECT COUNT(*) AS cnt FROM repair_transactions WHERE DATE_FORMAT(date,'%Y-%m')='$prevMonth'");
$prevRepairs  = $prevRes->fetch_assoc()['cnt'];

$momChange = $prevRepairs > 0 ? round((($monthRepairs - $prevRepairs) / $prevRepairs) * 100, 1) : 0;

$revRes = $conn->query("
    SELECT COALESCE(SUM(rt.labor_cost),0) + COALESCE(SUM(tp.parts_cost),0) AS total
    FROM repair_transactions rt
    LEFT JOIN (SELECT transaction_id, SUM(tp.unit_price * tp.quantity) AS parts_cost
               FROM transaction_parts tp JOIN repair r ON r.repair_id=tp.repair_id GROUP BY transaction_id) tp
           ON tp.transaction_id=rt.transaction_id
    WHERE rt.status_id='COM'");
$totalRevenue = $revRes->fetch_assoc()['total'];

$monthRevRes = $conn->query("
    SELECT COALESCE(SUM(rt.labor_cost),0) + COALESCE(SUM(tp.parts_cost),0) AS total
    FROM repair_transactions rt
    LEFT JOIN (SELECT transaction_id, SUM(tp.unit_price * tp.quantity) AS parts_cost
               FROM transaction_parts tp JOIN repair r ON r.repair_id=tp.repair_id GROUP BY transaction_id) tp
           ON tp.transaction_id=rt.transaction_id
    WHERE rt.status_id='COM' AND DATE_FORMAT(rt.date,'%Y-%m')='$currentMonth'");
$monthRevenue = $monthRevRes->fetch_assoc()['total'];

$ongoingRes    = $conn->query("SELECT COUNT(*) AS cnt FROM repair_transactions WHERE status_id='ONG'");
$ongoingRepairs= $ongoingRes->fetch_assoc()['cnt'];

$motoRes    = $conn->query("SELECT COUNT(*) AS cnt FROM motorcycles");
$totalMotos = $motoRes->fetch_assoc()['cnt'];

// ─────────────────────────────────────────────────────────────────
// SECTION B: COMPARATIVE ANALYTICS QUERIES
// ─────────────────────────────────────────────────────────────────

// B1. Period KPIs – current vs previous
function periodKpi($conn, $start, $end) {
    $s = $conn->real_escape_string($start);
    $e = $conn->real_escape_string($end);
    $r = $conn->query("
        SELECT
            COUNT(*) AS total_jobs,
            SUM(type_id='REP') AS repairs,
            SUM(type_id='PMS') AS pms,
            COALESCE(SUM(rt.labor_cost),0) AS labor,
            COALESCE(SUM(tp.parts_cost),0) AS parts_rev
        FROM repair_transactions rt
        LEFT JOIN (SELECT transaction_id, SUM(r.price*tp.quantity) AS parts_cost
                   FROM transaction_parts tp JOIN repair r ON r.repair_id=tp.repair_id GROUP BY transaction_id) tp
               ON tp.transaction_id=rt.transaction_id
        WHERE rt.date BETWEEN '$s' AND '$e 23:59:59' AND rt.status_id='COM'
    ");
    return $r->fetch_assoc();
}
$curKpi  = periodKpi($conn, $curStart, $curEnd);
$prevKpi = periodKpi($conn, $prevStart, $prevEnd);

function pctChange($cur, $prev) {
    if ($prev == 0) return $cur > 0 ? 100 : 0;
    return round((($cur - $prev) / $prev) * 100, 1);
}

// B2. YoY monthly breakdown (12 months each year for line chart)
$curYear  = date('Y', strtotime($curStart));
$prevYear = date('Y', strtotime($prevStart));

$yoyRes = $conn->query("
    SELECT YEAR(date) AS yr, MONTH(date) AS mo,
           COUNT(*) AS jobs,
           COALESCE(SUM(labor_cost),0) AS labor
    FROM repair_transactions
    WHERE YEAR(date) IN ($curYear, $prevYear) AND status_id='COM'
    GROUP BY yr, mo
    ORDER BY yr, mo
");
$yoyData = [];
while ($row = $yoyRes->fetch_assoc()) {
    $yoyData[(int)$row['yr']][(int)$row['mo']] = $row;
}
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$yoyCurJobs = $yoyPrevJobs = $yoyCurRev = $yoyPrevRev = [];
for ($m = 1; $m <= 12; $m++) {
    $yoyCurJobs[]  = (int)($yoyData[$curYear][$m]['jobs']   ?? 0);
    $yoyPrevJobs[] = (int)($yoyData[$prevYear][$m]['jobs']  ?? 0);
    $yoyCurRev[]   = (float)($yoyData[$curYear][$m]['labor']  ?? 0);
    $yoyPrevRev[]  = (float)($yoyData[$prevYear][$m]['labor'] ?? 0);
}

// B3. Hub comparison – current vs previous period
$hubCmpRes = $conn->query("
    SELECT h.hub_name,
           SUM(rt.date BETWEEN '$cs' AND '$ce 23:59:59') AS cur_jobs,
           SUM(rt.date BETWEEN '$ps' AND '$pe 23:59:59') AS prev_jobs,
           SUM(CASE WHEN rt.date BETWEEN '$cs' AND '$ce 23:59:59' THEN rt.labor_cost ELSE 0 END) AS cur_rev,
           SUM(CASE WHEN rt.date BETWEEN '$ps' AND '$pe 23:59:59' THEN rt.labor_cost ELSE 0 END) AS prev_rev,
           SUM(CASE WHEN rt.date BETWEEN '$cs' AND '$ce 23:59:59' AND rt.type_id='PMS' THEN 1 ELSE 0 END) AS cur_pms,
           SUM(CASE WHEN rt.date BETWEEN '$cs' AND '$ce 23:59:59' AND rt.type_id='REP' THEN 1 ELSE 0 END) AS cur_rep
    FROM repair_transactions rt
    JOIN motorcycles m ON m.plate_no = rt.plate_no
    JOIN hubs h ON h.hub_id = m.hub_id
    WHERE (rt.date BETWEEN '$cs' AND '$ce 23:59:59')
       OR (rt.date BETWEEN '$ps' AND '$pe 23:59:59')
    GROUP BY h.hub_id, h.hub_name
    ORDER BY cur_jobs DESC
");
$hubCmp = [];
while ($row = $hubCmpRes->fetch_assoc()) $hubCmp[] = $row;

// B4. Repair type split: current vs previous (for grouped bar)
$typeSplitRes = $conn->query("
    SELECT rt2.type_name,
           SUM(rt.date BETWEEN '$cs' AND '$ce 23:59:59') AS cur_cnt,
           SUM(rt.date BETWEEN '$ps' AND '$pe 23:59:59') AS prev_cnt
    FROM transaction_parts tp
    JOIN repair r ON r.repair_id = tp.repair_id
    JOIN repair_type rt2 ON rt2.repair_type_id = r.repair_type_id
    JOIN repair_transactions rt ON rt.transaction_id = tp.transaction_id
    WHERE (rt.date BETWEEN '$cs' AND '$ce 23:59:59')
       OR (rt.date BETWEEN '$ps' AND '$pe 23:59:59')
    GROUP BY rt2.repair_type_id, rt2.type_name
    ORDER BY cur_cnt DESC
");
$typeSplitLabels = $typeSplitCur = $typeSplitPrev = [];
while ($row = $typeSplitRes->fetch_assoc()) {
    $typeSplitLabels[] = $row['type_name'];
    $typeSplitCur[]    = (int)$row['cur_cnt'];
    $typeSplitPrev[]   = (int)$row['prev_cnt'];
}

// B5. Average cost per job: current vs previous per hub
$avgCostRes = $conn->query("
    SELECT h.hub_name,
           AVG(CASE WHEN rt.date BETWEEN '$cs' AND '$ce 23:59:59' THEN rt.labor_cost END) AS cur_avg,
           AVG(CASE WHEN rt.date BETWEEN '$ps' AND '$pe 23:59:59' THEN rt.labor_cost END) AS prev_avg
    FROM repair_transactions rt
    JOIN motorcycles m ON m.plate_no = rt.plate_no
    JOIN hubs h ON h.hub_id = m.hub_id
    WHERE (rt.date BETWEEN '$cs' AND '$ce 23:59:59')
       OR (rt.date BETWEEN '$ps' AND '$pe 23:59:59')
    GROUP BY h.hub_id, h.hub_name
");
$avgCostLabels = $avgCostCur = $avgCostPrev = [];
while ($row = $avgCostRes->fetch_assoc()) {
    $avgCostLabels[] = $row['hub_name'];
    $avgCostCur[]    = round((float)$row['cur_avg'], 2);
    $avgCostPrev[]   = round((float)$row['prev_avg'], 2);
}

// ─────────────────────────────────────────────────────────────────
// SECTION C: EXISTING CHARTS DATA
// ─────────────────────────────────────────────────────────────────
$monthlyRes = $conn->query("
    SELECT DATE_FORMAT(date,'%Y-%m') AS ym, DATE_FORMAT(date,'%b %Y') AS label,
           SUM(type_id='REP') AS repairs, SUM(type_id='PMS') AS pms
    FROM repair_transactions
    WHERE date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY ym, label ORDER BY ym ASC");
$monthlyLabels = $monthlyRepairs_data = $monthlyPMS_data = [];
while ($row = $monthlyRes->fetch_assoc()) {
    $monthlyLabels[]       = $row['label'];
    $monthlyRepairs_data[] = (int)$row['repairs'];
    $monthlyPMS_data[]     = (int)$row['pms'];
}

$topPartsRes = $conn->query("
    SELECT r.part_name, SUM(tp.quantity) AS usage_count
    FROM transaction_parts tp JOIN repair r ON r.repair_id=tp.repair_id
    GROUP BY r.repair_id, r.part_name ORDER BY usage_count DESC LIMIT 10");
$topPartsLabels = $topPartsData = [];
while ($row = $topPartsRes->fetch_assoc()) {
    $topPartsLabels[] = $row['part_name'];
    $topPartsData[]   = (int)$row['usage_count'];
}

$categoryRes = $conn->query("
    SELECT rt2.type_name, COUNT(*) AS cnt
    FROM transaction_parts tp JOIN repair r ON r.repair_id=tp.repair_id
    JOIN repair_type rt2 ON rt2.repair_type_id=r.repair_type_id
    JOIN repair_transactions rt ON rt.transaction_id=tp.transaction_id
    GROUP BY rt2.repair_type_id, rt2.type_name ORDER BY cnt DESC");
$catLabels = $catData = [];
while ($row = $categoryRes->fetch_assoc()) {
    $catLabels[] = $row['type_name'];
    $catData[]   = (int)$row['cnt'];
}

$hubRes = $conn->query("
    SELECT h.hub_name, COUNT(rt.transaction_id) AS cnt
    FROM repair_transactions rt JOIN motorcycles m ON m.plate_no=rt.plate_no
    JOIN hubs h ON h.hub_id=m.hub_id GROUP BY h.hub_id, h.hub_name ORDER BY cnt DESC");
$hubLabels = $hubData = [];
while ($row = $hubRes->fetch_assoc()) {
    $hubLabels[] = $row['hub_name'];
    $hubData[]   = (int)$row['cnt'];
}

$recentRes = $conn->query("
    SELECT rt.transaction_id, rt.plate_no, rt.date,
           s.status_desc, st.type, mc.first_name, mc.last_name, rt.labor_cost, h.hub_name
    FROM repair_transactions rt
    LEFT JOIN status s ON s.status_id=rt.status_id
    LEFT JOIN service_types st ON st.type_id=rt.type_id
    LEFT JOIN mechanics mc ON mc.mechanic_id=rt.mechanic_id
    LEFT JOIN motorcycles m ON m.plate_no=rt.plate_no
    LEFT JOIN hubs h ON h.hub_id=m.hub_id
    ORDER BY rt.date DESC, rt.transaction_id DESC
    LIMIT 10
");
$recentRows = [];
while ($row = $recentRes->fetch_assoc()) $recentRows[] = $row;

$lowHealthRes = $conn->query("
    SELECT m.plate_no, m.health_score, h.hub_name, mo.model_id
    FROM motorcycles m JOIN hubs h ON h.hub_id=m.hub_id JOIN models mo ON mo.model_id=m.model_id
    WHERE m.health_score < 60 ORDER BY m.health_score ASC LIMIT 8");
$lowHealthRows = [];
while ($row = $lowHealthRes->fetch_assoc()) $lowHealthRows[] = $row;

$mechRes = $conn->query("
    SELECT mc.first_name, mc.last_name, COUNT(rt.transaction_id) AS jobs,
           COALESCE(SUM(rt.labor_cost),0) AS earned
    FROM mechanics mc
    LEFT JOIN repair_transactions rt ON rt.mechanic_id=mc.mechanic_id AND rt.status_id='COM'
    GROUP BY mc.mechanic_id ORDER BY jobs DESC");
$mechRows = [];
while ($row = $mechRes->fetch_assoc()) $mechRows[] = $row;

// P1. PMS predictions — this week
$pmsWeekRes = $conn->query("
    SELECT p.plate_no, p.next_pms_date, p.next_total_cost, h.hub_name,
           DATEDIFF(p.next_pms_date, CURDATE()) AS days_left
    FROM predictions_pms p
    LEFT JOIN motorcycles m ON m.plate_no = p.plate_no
    LEFT JOIN hubs h ON h.hub_id = m.hub_id
    WHERE p.next_pms_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY p.next_pms_date ASC
");
$pmsWeek = $pmsWeekRes->fetch_all(MYSQLI_ASSOC);
 
// P2. PMS predictions — this month (beyond this week)
$pmsMonthRes = $conn->query("
    SELECT p.plate_no, p.next_pms_date, p.next_total_cost, h.hub_name,
           DATEDIFF(p.next_pms_date, CURDATE()) AS days_left
    FROM predictions_pms p
    LEFT JOIN motorcycles m ON m.plate_no = p.plate_no
    LEFT JOIN hubs h ON h.hub_id = m.hub_id
    WHERE p.next_pms_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND p.next_pms_date <= LAST_DAY(CURDATE())
    ORDER BY p.next_pms_date ASC
    LIMIT 10
");
$pmsMonth = $pmsMonthRes->fetch_all(MYSQLI_ASSOC);
 
// P3. PMS overdue
$pmsOverdueRes = $conn->query("
    SELECT p.plate_no, p.next_pms_date, p.next_total_cost, h.hub_name,
           DATEDIFF(CURDATE(), p.next_pms_date) AS days_overdue
    FROM predictions_pms p
    LEFT JOIN motorcycles m ON m.plate_no = p.plate_no
    LEFT JOIN hubs h ON h.hub_id = m.hub_id
    WHERE p.next_pms_date < CURDATE()
    ORDER BY p.next_pms_date ASC
    LIMIT 8
");
$pmsOverdue = $pmsOverdueRes->fetch_all(MYSQLI_ASSOC);
 
// P4. Repair predictions — this week
$repairWeekRes = $conn->query("
    SELECT p.plate_no, p.next_repair_date, h.hub_name,
           DATEDIFF(p.next_repair_date, CURDATE()) AS days_left
    FROM predictions_repair p
    LEFT JOIN motorcycles m ON m.plate_no = p.plate_no
    LEFT JOIN hubs h ON h.hub_id = m.hub_id
    WHERE p.next_repair_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY p.next_repair_date ASC
");
$repairWeek = $repairWeekRes->fetch_all(MYSQLI_ASSOC);
 
// P5. Repair predictions — this month (beyond this week)
$repairMonthRes = $conn->query("
    SELECT p.plate_no, p.next_repair_date, h.hub_name,
           DATEDIFF(p.next_repair_date, CURDATE()) AS days_left
    FROM predictions_repair p
    LEFT JOIN motorcycles m ON m.plate_no = p.plate_no
    LEFT JOIN hubs h ON h.hub_id = m.hub_id
    WHERE p.next_repair_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND p.next_repair_date <= LAST_DAY(CURDATE())
    ORDER BY p.next_repair_date ASC
    LIMIT 10
");
$repairMonth = $repairMonthRes->fetch_all(MYSQLI_ASSOC);
 
// P6. Repair overdue
$repairOverdueRes = $conn->query("
    SELECT p.plate_no, p.next_repair_date, h.hub_name,
           DATEDIFF(CURDATE(), p.next_repair_date) AS days_overdue
    FROM predictions_repair p
    LEFT JOIN motorcycles m ON m.plate_no = p.plate_no
    LEFT JOIN hubs h ON h.hub_id = m.hub_id
    WHERE p.next_repair_date < CURDATE()
    ORDER BY p.next_repair_date ASC
    LIMIT 8
");
$repairOverdue = $repairOverdueRes->fetch_all(MYSQLI_ASSOC);
 
// P7. Summary counts for badges
$pmsSummaryRes = $conn->query("
    SELECT
        SUM(next_pms_date < CURDATE()) AS overdue,
        SUM(next_pms_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS this_week,
        SUM(next_pms_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND next_pms_date <= LAST_DAY(CURDATE())) AS this_month
    FROM predictions_pms WHERE next_pms_date IS NOT NULL
");
$pmsSummary = $pmsSummaryRes->fetch_assoc();
 
$repairSummaryRes = $conn->query("
    SELECT
        SUM(next_repair_date < CURDATE()) AS overdue,
        SUM(next_repair_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS this_week,
        SUM(next_repair_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND next_repair_date <= LAST_DAY(CURDATE())) AS this_month
    FROM predictions_repair WHERE next_repair_date IS NOT NULL
");
$repairSummary = $repairSummaryRes->fetch_assoc();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard | Hammerdad</title>
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        :root {
            --red:       #b71513;
            --red-dark:  #8f0f0d;
            --red-muted: #d94644;
            --red-faint: #fdf2f2;
            --ink:       #1a1a1a;
            --ink-2:     #3d3d3d;
            --ink-3:     #6b6b6b;
            --rule:      #e2e2e2;
            --surface:   #f7f7f5;
            --white:     #ffffff;
            --green:     #1a7f4b;
            --amber:     #c47a0f;
            --blue:      #1a56c4;
            --radius:    8px;
            --shadow:    0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.04);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            color: var(--ink);
            min-height: 100vh;
        }
        .main-panel { padding: 28px 32px 56px; max-width: 1400px; }

        /* PAGE HEADER */
        .dash-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
        .dash-header-left h1 { font-size: 26px; font-weight: 700; letter-spacing: -.4px; line-height: 1; }
        .dash-header-left p  { font-size: 13px; color: var(--ink-3); margin-top: 5px; }
        .dash-date-badge { font-size: 12px; background: var(--white); border: 1px solid var(--rule); border-radius: 20px; padding: 5px 14px; color: var(--ink-3); }

        /* SECTION LABEL */
        .section-label { font-size: 11px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: var(--ink-3); margin: 0 0 14px; }
        .section-gap { height: 28px; }

        /* PERIOD SWITCHER */
        .period-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--white);
            border: 1px solid var(--rule);
            border-radius: var(--radius);
            padding: 10px 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .period-bar label { font-size: 11px; font-weight: 700; color: var(--ink-3); text-transform: uppercase; letter-spacing: .8px; margin-right: 4px; }
        .period-btn {
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            border: 1px solid var(--rule);
            background: transparent;
            color: var(--ink-2);
            cursor: pointer;
            text-decoration: none;
            transition: all .15s;
        }
        .period-btn:hover  { border-color: var(--red); color: var(--red); }
        .period-btn.active { background: var(--red); border-color: var(--red); color: #fff; }
        .period-divider    { width: 1px; height: 20px; background: var(--rule); margin: 0 4px; }
        .period-custom     { display: flex; align-items: center; gap: 6px; }
        .period-custom input[type="date"] {
            font-size: 12px; padding: 4px 8px; border: 1px solid var(--rule);
            border-radius: 5px; background: var(--surface); color: var(--ink-2);
            font-family: Tahoma, sans-serif;
        }
        .period-custom button {
            font-size: 12px; font-weight: 700; padding: 4px 12px;
            background: var(--ink); color: #fff; border: none;
            border-radius: 5px; cursor: pointer;
        }

        /* KPI STRIP */
        .kpi-strip { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
        .kpi-card  { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px 22px; border-top: 3px solid transparent; }
        .kpi-card.accent-red   { border-top-color: var(--red); }
        .kpi-card.accent-green { border-top-color: var(--green); }
        .kpi-card.accent-amber { border-top-color: var(--amber); }
        .kpi-card.accent-ink   { border-top-color: var(--ink-2); }
        .kpi-label { font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--ink-3); margin-bottom: 8px; }
        .kpi-value { font-size: 36px; font-weight: 200; line-height: 1; color: var(--ink); letter-spacing: -1px; }
        .kpi-sub   { font-size: 12px; color: var(--ink-3); margin-top: 8px; }
        .kpi-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 20px; margin-left: 4px; }
        .kpi-badge.up      { background: #e6f4ee; color: var(--green); }
        .kpi-badge.down    { background: #fceaea; color: var(--red); }
        .kpi-badge.neutral { background: #f0f0f0; color: var(--ink-3); }

        /* CARDS / CHARTS */
        .chart-grid   { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px; }
        .chart-grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 16px; }
        .grid-2       { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); padding: 22px 24px; }
        .card-header  { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; }
        .card-title   { font-size: 14px; font-weight: 700; color: var(--ink); letter-spacing: -.1px; }
        .card-subtitle{ font-size: 12px; color: var(--ink-3); margin-top: 2px; }
        .card-tag     { font-size: 11px; padding: 3px 9px; border-radius: 20px; background: var(--red-faint); color: var(--red); font-weight: 700; white-space: nowrap; }
        .chart-wrap   { position: relative; width: 100%; }

        /* ── COMPARATIVE KPI GRID ── */
        .cmp-kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 20px; }
        .cmp-kpi {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px 20px;
            border-left: 4px solid var(--rule);
        }
        .cmp-kpi.c-red   { border-left-color: var(--red); }
        .cmp-kpi.c-blue  { border-left-color: var(--blue); }
        .cmp-kpi.c-green { border-left-color: var(--green); }
        .cmp-kpi.c-amber { border-left-color: var(--amber); }
        .cmp-kpi-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--ink-3); margin-bottom: 10px; }
        .cmp-row { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 4px; }
        .cmp-period-label { font-size: 11px; color: var(--ink-3); }
        .cmp-val-cur  { font-size: 22px; font-weight: 700; color: var(--ink); letter-spacing: -.5px; }
        .cmp-val-prev { font-size: 14px; color: var(--ink-3); }
        .cmp-divider  { height: 1px; background: var(--rule); margin: 8px 0; }
        .cmp-delta {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 12px; font-weight: 700;
            padding: 3px 9px; border-radius: 20px;
        }
        .cmp-delta.up   { background: #e6f4ee; color: var(--green); }
        .cmp-delta.down { background: #fceaea; color: var(--red); }
        .cmp-delta.flat { background: #f0f0f0; color: var(--ink-3); }

        /* ── HUB COMPARISON TABLE ── */
        .hub-cmp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .hub-cmp-table thead th {
            font-size: 10px; font-weight: 700; letter-spacing: .9px; text-transform: uppercase;
            color: var(--ink-3); padding: 0 12px 10px; border-bottom: 1px solid var(--rule); text-align: left;
        }
        .hub-cmp-table thead th.num { text-align: right; }
        .hub-cmp-table tbody tr { border-bottom: 1px solid var(--rule); }
        .hub-cmp-table tbody tr:last-child { border-bottom: none; }
        .hub-cmp-table tbody tr:hover { background: var(--surface); }
        .hub-cmp-table tbody td { padding: 12px 12px; vertical-align: middle; color: var(--ink-2); }
        .hub-cmp-table tbody td.num { text-align: right; }
        .hub-name-pill {
            display: inline-block; font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px; background: var(--red-faint); color: var(--red);
        }
        .inline-delta { font-size: 11px; font-weight: 700; margin-left: 5px; }
        .inline-delta.up   { color: var(--green); }
        .inline-delta.down { color: var(--red); }
        .inline-delta.flat { color: var(--ink-3); }

        /* PMS/REP SPLIT BARS */
        .split-bar { display: flex; height: 8px; border-radius: 4px; overflow: hidden; width: 100px; }
        .split-bar-pms { background: var(--blue); }
        .split-bar-rep { background: var(--red-muted); }

        /* TABLES */
        .bottom-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead th {
            font-size: 10px; font-weight: 700; letter-spacing: .9px; text-transform: uppercase;
            color: var(--ink-3); padding: 0 10px 10px; border-bottom: 1px solid var(--rule); text-align: left; white-space: nowrap;
        }
        .data-table tbody tr  { border-bottom: 1px solid var(--rule); transition: background .12s; }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background: var(--surface); }
        .data-table tbody td  { padding: 11px 10px; color: var(--ink-2); vertical-align: middle; }
        .badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; letter-spacing: .4px; }
        .badge-green { background: #e6f4ee; color: #1a7f4b; }
        .badge-amber { background: #fef3e2; color: #c47a0f; }
        .badge-red   { background: #fceaea; color: var(--red); }
        .badge-gray  { background: #f0f0f0; color: var(--ink-3); }
        .badge-blue  { background: #e8f0fe; color: #1a56c4; }

        /* HEALTH BAR */
        .health-bar-wrap { display: flex; align-items: center; gap: 10px; }
        .health-bar-bg   { flex: 1; height: 6px; background: var(--rule); border-radius: 3px; overflow: hidden; }
        .health-bar-fill { height: 100%; border-radius: 3px; }
        .health-pct      { font-size: 12px; font-weight: 700; min-width: 30px; text-align: right; }

        /* MECHANIC BOARD */
        .mech-row { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--rule); gap: 12px; }
        .mech-row:last-child { border-bottom: none; }
        .mech-rank   { font-size: 11px; font-weight: 700; color: var(--ink-3); width: 18px; text-align: center; }
        .mech-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--red); color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .mech-info   { flex: 1; min-width: 0; }
        .mech-name   { font-size: 13px; font-weight: 700; color: var(--ink); }
        .mech-jobs   { font-size: 11px; color: var(--ink-3); }
        .mech-earned { font-size: 13px; font-weight: 700; color: var(--ink-2); text-align: right; }

        /* PARTS ROWS */
        .parts-row  { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid var(--rule); }
        .parts-row:last-child { border-bottom: none; }
        .parts-rank  { font-size: 11px; color: var(--ink-3); width: 16px; text-align: center; }
        .parts-name  { font-size: 12px; color: var(--ink); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .parts-bar-bg   { width: 80px; height: 5px; background: var(--rule); border-radius: 3px; flex-shrink: 0; }
        .parts-bar-fill { height: 100%; border-radius: 3px; background: var(--red-muted); }
        .parts-count { font-size: 12px; font-weight: 700; color: var(--ink-2); min-width: 24px; text-align: right; }

        /* RESPONSIVE */
        @media (max-width: 1100px) {
            .kpi-strip, .cmp-kpi-grid { grid-template-columns: repeat(2,1fr); }
            .chart-grid, .chart-grid-3, .grid-2 { grid-template-columns: 1fr; }
            .bottom-grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .main-panel { padding: 16px; }
            .kpi-strip, .cmp-kpi-grid { grid-template-columns: 1fr 1fr; }
            .kpi-value { font-size: 28px; }
            .cmp-val-cur { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="loader-wrapper"><div class="loader"></div></div>
<?php include 'page-essentials.php'; ?>

<div class="main-panel">

    <!-- PAGE HEADER -->
    <div class="dash-header">
        <div class="dash-header-left">
            <h1>Dashboard</h1>
            <p>Overview of Motorcycle Services</p>
        </div>
        <span class="dash-date-badge"><?= date('l, F j Y') ?></span>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 1 — AT A GLANCE KPIs
    ═══════════════════════════════════════════════════════════ -->
    <p class="section-label">Summary</p>
    <div class="kpi-strip">
        <div class="kpi-card accent-red">
            <p class="kpi-label">Repairs this month</p>
            <p class="kpi-value"><?= number_format($monthRepairs) ?></p>
            <p class="kpi-sub">vs <?= $prevRepairs ?> last month
                <?php $dir = $momChange >= 0 ? 'up' : 'down'; $sym = $momChange >= 0 ? '▲' : '▼'; ?>
                <span class="kpi-badge <?= $dir ?>"><?= $sym ?> <?= abs($momChange) ?>%</span>
            </p>
        </div>
        <div class="kpi-card accent-green">
            <p class="kpi-label">Total completed repairs</p>
            <p class="kpi-value"><?= number_format($totalRepairs) ?></p>
            <p class="kpi-sub">All time, all hubs</p>
        </div>
        <div class="kpi-card accent-amber">
            <p class="kpi-label">Revenue this month</p>
            <p class="kpi-value">₱<?= number_format($monthRevenue, 0) ?></p>
            <p class="kpi-sub">All time: ₱<?= number_format($totalRevenue, 0) ?></p>
        </div>
        <div class="kpi-card accent-ink">
            <p class="kpi-label">Number of Fleets</p>
            <p class="kpi-value"><?= number_format($totalMotos) ?></p>
            <p class="kpi-sub">Motorcycles &nbsp;&nbsp;
            </p>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 2 — COMPARATIVE ANALYTICS
    ═══════════════════════════════════════════════════════════ -->
    <p class="section-label">Comparative analytics</p>

    <!-- PERIOD SWITCHER -->
    <div class="period-bar">
        <label>Compare</label>
        <a href="?period=month" class="period-btn <?= $period==='month' ? 'active' : '' ?>">Month over month</a>
        <a href="?period=year"  class="period-btn <?= $period==='year'  ? 'active' : '' ?>">Year over year</a>
        <div class="period-divider"></div>
        <form method="get" class="period-custom">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" value="<?= htmlspecialchars($customFrom) ?>" placeholder="From">
            <span style="font-size:12px;color:var(--ink-3)">→</span>
            <input type="date" name="to"   value="<?= htmlspecialchars($customTo) ?>"   placeholder="To">
            <button type="submit">Apply</button>
        </form>
        <?php if ($period==='custom'): ?>
            <a href="?period=month" style="font-size:11px;color:var(--ink-3);text-decoration:none;margin-left:4px">✕ clear</a>
        <?php endif; ?>
    </div>

    <!-- COMPARISON PERIOD LABELS -->
    <div style="display:flex;align-items:center;gap:18px;margin-bottom:16px;font-size:12px;">
        <span style="display:flex;align-items:center;gap:6px;">
            <span style="width:12px;height:12px;border-radius:50%;background:var(--red);display:inline-block;"></span>
            <strong><?= htmlspecialchars($curLabel) ?></strong> (current)
        </span>
        <span style="display:flex;align-items:center;gap:6px;">
            <span style="width:12px;height:12px;border-radius:50%;background:var(--ink-3);display:inline-block;"></span>
            <strong><?= htmlspecialchars($prevLabel) ?></strong> (previous)
        </span>
    </div>

    <!-- COMPARATIVE KPI CARDS -->
    <div class="cmp-kpi-grid">
        <?php
        // Helper to render one comparative KPI card
        function cmpCard($label, $curVal, $prevVal, $format, $accent) {
            $delta = $prevVal > 0 ? round((($curVal - $prevVal) / $prevVal) * 100, 1) : ($curVal > 0 ? 100 : 0);
            $dir   = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
            $sym   = $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '—');
            $cur   = $format === 'money' ? '₱' . number_format($curVal, 0)  : number_format($curVal);
            $prev  = $format === 'money' ? '₱' . number_format($prevVal, 0) : number_format($prevVal);
            echo "<div class='cmp-kpi c-$accent'>
                    <p class='cmp-kpi-label'>$label</p>
                    <p class='cmp-val-cur'>$cur</p>
                    <div class='cmp-divider'></div>
                    <div class='cmp-row'>
                        <span class='cmp-period-label'>vs $prev</span>
                        <span class='cmp-delta $dir'>$sym " . abs($delta) . "%</span>
                    </div>
                  </div>";
        }
        $curRevTotal  = ($curKpi['labor']  ?? 0) + ($curKpi['parts_rev']  ?? 0);
        $prevRevTotal = ($prevKpi['labor'] ?? 0) + ($prevKpi['parts_rev'] ?? 0);
        cmpCard('Total jobs',    $curKpi['total_jobs'] ?? 0,  $prevKpi['total_jobs'] ?? 0,  'num',   'red');
        cmpCard('Repairs (REP)', $curKpi['repairs']    ?? 0,  $prevKpi['repairs']    ?? 0,  'num',   'blue');
        cmpCard('PMS jobs',      $curKpi['pms']        ?? 0,  $prevKpi['pms']        ?? 0,  'num',   'green');
        cmpCard('Revenue',       $curRevTotal,                $prevRevTotal,                'money', 'amber');
        ?>
    </div>

    <!-- YoY LINE CHART + TYPE GROUPED BAR -->
    <div class="grid-2" style="margin-bottom:16px;">
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Jobs per month (<?= $curYear ?> vs <?= $prevYear ?>)</p>
                    <p class="card-subtitle">Year-over-year transaction volume by month</p>
                </div>
            </div>
            <div class="chart-wrap" style="height:230px;">
                <canvas id="chartYoY"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Repair categories (current vs previous)</p>
                    <p class="card-subtitle">Part usage count by repair type</p>
                </div>
            </div>
            <div class="chart-wrap" style="height:230px;">
                <canvas id="chartTypeSplit"></canvas>
            </div>
        </div>
    </div>

    <!-- HUB COMPARISON TABLE + AVG COST CHART -->
    <div class="grid-2" style="margin-bottom:16px;">
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Hub performance comparison</p>
                    <p class="card-subtitle"><?= htmlspecialchars($curLabel) ?> vs <?= htmlspecialchars($prevLabel) ?></p>
                </div>
            </div>
            <table class="hub-cmp-table">
                <thead>
                    <tr>
                        <th>Hub</th>
                        <th class="num">Jobs (cur)</th>
                        <th class="num">Jobs (prev)</th>
                        <th class="num">Revenue (cur)</th>
                        <th>PMS / Rep split</th>
                        <th class="num">Δ Jobs</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hubCmp as $h):
                    $curJ  = (int)$h['cur_jobs'];
                    $prevJ = (int)$h['prev_jobs'];
                    $dj    = $prevJ > 0 ? round((($curJ - $prevJ) / $prevJ) * 100, 1) : ($curJ > 0 ? 100 : 0);
                    $djDir = $dj > 0 ? 'up' : ($dj < 0 ? 'down' : 'flat');
                    $djSym = $dj > 0 ? '▲' : ($dj < 0 ? '▼' : '—');
                    $total = $curJ ?: 1;
                    $pmsPct= round($h['cur_pms'] / $total * 100);
                    $repPct= 100 - $pmsPct;
                ?>
                <tr>
                    <td><span class="hub-name-pill"><?= htmlspecialchars($h['hub_name']) ?></span></td>
                    <td class="num" style="font-weight:700"><?= number_format($curJ) ?></td>
                    <td class="num" style="color:var(--ink-3)"><?= number_format($prevJ) ?></td>
                    <td class="num">₱<?= number_format($h['cur_rev'], 0) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="split-bar">
                                <div class="split-bar-pms" style="width:<?= $pmsPct ?>%"></div>
                                <div class="split-bar-rep" style="width:<?= $repPct ?>%"></div>
                            </div>
                            <span style="font-size:11px;color:var(--ink-3)"><?= $pmsPct ?>% PMS</span>
                        </div>
                    </td>
                    <td class="num">
                        <span class="inline-delta <?= $djDir ?>"><?= $djSym ?> <?= abs($dj) ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Avg. labor cost per job for all hubs</p>
                    <p class="card-subtitle">Current vs previous period (₱)</p>
                </div>
            </div>
            <div class="chart-wrap" style="height:230px;">
                <canvas id="chartAvgCost"></canvas>
            </div>
        </div>
    </div>

    <!-- YoY REVENUE LINE CHART -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <div>
                <p class="card-title">Monthly labor revenue (<?= $curYear ?> vs <?= $prevYear ?>)</p>
                <p class="card-subtitle">Labor cost earned per month, year-over-year</p>
            </div>
            <span class="card-tag">₱ REVENUE</span>
        </div>
        <div class="chart-wrap" style="height:200px;">
            <canvas id="chartYoYRev"></canvas>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 3 — EXISTING: ACTIVITY TRENDS
    ═══════════════════════════════════════════════════════════ -->
    <div class="section-gap"></div>
    <p class="section-label">Activity trends</p>
    <div class="chart-grid">
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Repairs & PMS per month</p>
                    <p class="card-subtitle">Last 12 months across all hubs</p>
                </div>
            </div>
            <div class="chart-wrap" style="height:240px">
                <canvas id="chartMonthly"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Repairs by hub</p>
                    <p class="card-subtitle">Distribution across locations</p>
                </div>
            </div>
            <div class="chart-wrap" style="height:240px">
                <canvas id="chartHub"></canvas>
            </div>
        </div>
    </div>

    <div class="chart-grid-3">
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Repair categories</p>
                    <p class="card-subtitle">By repair type</p>
                </div>
            </div>
            <div class="chart-wrap" style="height:200px">
                <canvas id="chartCategory"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Top parts used</p>
                    <p class="card-subtitle">By total quantity across all jobs</p>
                </div>
                <span class="card-tag">TOP 10</span>
            </div>
            <?php $maxPart = !empty($topPartsData) ? max($topPartsData) : 1;
            foreach ($topPartsData as $i => $count):
                $pct = round(($count / $maxPart) * 100); ?>
            <div class="parts-row">
                <span class="parts-rank"><?= $i+1 ?></span>
                <span class="parts-name" title="<?= htmlspecialchars($topPartsLabels[$i]) ?>"><?= htmlspecialchars($topPartsLabels[$i]) ?></span>
                <div class="parts-bar-bg"><div class="parts-bar-fill" style="width:<?= $pct ?>%"></div></div>
                <span class="parts-count"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- PREDICTIONS CARD -->
        <div class="card" style="grid-column: span 1; overflow: hidden;">
            <div class="card-header" style="margin-bottom: 12px;">
                <div>
                    <p class="card-title">Upcoming service predictions</p>
                    <p class="card-subtitle">Predicted PMS &amp; repairs as of <?= date('F Y') ?></p>
                </div>
            </div>
        
            <!-- Summary badges -->
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
                <div style="background:#fceaea;border-radius:6px;padding:8px 12px;flex:1;min-width:80px;text-align:center;">
                    <p style="font-size:20px;font-weight:700;color:#b71513;margin:0;"><?= (int)($pmsSummary['overdue'] ?? 0) + (int)($repairSummary['overdue'] ?? 0) ?></p>
                    <p style="font-size:10px;color:#b71513;font-weight:700;margin:0;letter-spacing:.5px;">OVERDUE</p>
                </div>
                <div style="background:#fff4e6;border-radius:6px;padding:8px 12px;flex:1;min-width:80px;text-align:center;">
                    <p style="font-size:20px;font-weight:700;color:#c47a0f;margin:0;"><?= (int)($pmsSummary['this_week'] ?? 0) + (int)($repairSummary['this_week'] ?? 0) ?></p>
                    <p style="font-size:10px;color:#c47a0f;font-weight:700;margin:0;letter-spacing:.5px;">THIS WEEK</p>
                </div>
                <div style="background:#e6f4ee;border-radius:6px;padding:8px 12px;flex:1;min-width:80px;text-align:center;">
                    <p style="font-size:20px;font-weight:700;color:#1a7f4b;margin:0;"><?= (int)($pmsSummary['this_month'] ?? 0) + (int)($repairSummary['this_month'] ?? 0) ?></p>
                    <p style="font-size:10px;color:#1a7f4b;font-weight:700;margin:0;letter-spacing:.5px;">THIS MONTH</p>
                </div>
            </div>
        
            <!-- TABS -->
            <div style="display:flex;gap:0;border-bottom:2px solid #f0f0f0;margin-bottom:12px;" id="predTabs">
                <button onclick="switchPredTab('pms')"   id="tab-pms"    class="pred-tab pred-tab-active">PMS</button>
                <button onclick="switchPredTab('repair')" id="tab-repair" class="pred-tab">Repair</button>
            </div>
        
            <style>
                .pred-tab {
                    font-size: 12px; font-weight: 700; padding: 6px 16px;
                    border: none; background: none; cursor: pointer;
                    color: #9b9b9b; border-bottom: 2px solid transparent;
                    margin-bottom: -2px; transition: all .15s;
                }
                .pred-tab-active { color: #b71513; border-bottom-color: #b71513; }
                .pred-panel { display: none; max-height: 280px; overflow-y: auto; }
                .pred-panel.active { display: block; }
                .pred-group-label {
                    font-size: 10px; font-weight: 700; letter-spacing: 1px;
                    text-transform: uppercase; color: #9b9b9b;
                    padding: 8px 0 4px; border-top: 1px solid #f0f0f0; margin-top: 4px;
                }
                .pred-group-label:first-child { border-top: none; margin-top: 0; }
                .pred-row {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 6px 0; border-bottom: 1px solid #f8f8f8; gap: 8px;
                }
                .pred-row:last-child { border-bottom: none; }
                .pred-plate { font-size: 12px; font-weight: 700; color: #1a1a1a; min-width: 80px; }
                .pred-hub   { font-size: 11px; color: #9b9b9b; flex: 1; }
                .pred-date  { font-size: 11px; color: #6b6b6b; white-space: nowrap; }
                .pred-badge {
                    font-size: 10px; font-weight: 700; padding: 2px 8px;
                    border-radius: 20px; white-space: nowrap; flex-shrink: 0;
                }
                .pred-empty { font-size: 12px; color: #9b9b9b; padding: 12px 0; text-align: center; }
            </style>
        
            <!-- PMS PANEL -->
            <div class="pred-panel active" id="panel-pms">
        
                <?php if (!empty($pmsOverdue)): ?>
                <p class="pred-group-label">⚠ Overdue (<?= count($pmsOverdue) ?>)</p>
                <?php foreach ($pmsOverdue as $r): ?>
                <div class="pred-row">
                    <span class="pred-plate"><?= htmlspecialchars($r['plate_no']) ?></span>
                    <span class="pred-hub"><?= htmlspecialchars($r['hub_name'] ?? '—') ?></span>
                    <span class="pred-date"><?= date('M j', strtotime($r['next_pms_date'])) ?></span>
                    <span class="pred-badge" style="background:#f0f0f0;color:#6b6b6b;">
                        <?= (int)$r['days_overdue'] ?>d ago
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
        
                <?php if (!empty($pmsWeek)): ?>
                <p class="pred-group-label">📅 This week (<?= count($pmsWeek) ?>)</p>
                <?php foreach ($pmsWeek as $r): ?>
                <div class="pred-row">
                    <span class="pred-plate"><?= htmlspecialchars($r['plate_no']) ?></span>
                    <span class="pred-hub"><?= htmlspecialchars($r['hub_name'] ?? '—') ?></span>
                    <span class="pred-date"><?= date('M j', strtotime($r['next_pms_date'])) ?></span>
                    <span class="pred-badge" style="background:#fceaea;color:#b71513;">
                        <?= (int)$r['days_left'] ?>d
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
        
                <?php if (!empty($pmsMonth)): ?>
                <p class="pred-group-label">📆 This month (<?= count($pmsMonth) ?>)</p>
                <?php foreach ($pmsMonth as $r): ?>
                <div class="pred-row">
                    <span class="pred-plate"><?= htmlspecialchars($r['plate_no']) ?></span>
                    <span class="pred-hub"><?= htmlspecialchars($r['hub_name'] ?? '—') ?></span>
                    <span class="pred-date"><?= date('M j', strtotime($r['next_pms_date'])) ?></span>
                    <span class="pred-badge" style="background:#fff4e6;color:#c47a0f;">
                        <?= (int)$r['days_left'] ?>d
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
        
                <?php if (empty($pmsOverdue) && empty($pmsWeek) && empty($pmsMonth)): ?>
                <p class="pred-empty">No PMS predictions available this month.</p>
                <?php endif; ?>
            </div>
        
            <!-- REPAIR PANEL -->
            <div class="pred-panel" id="panel-repair">
        
                <?php if (!empty($repairOverdue)): ?>
                <p class="pred-group-label">⚠ Overdue (<?= count($repairOverdue) ?>)</p>
                <?php foreach ($repairOverdue as $r): ?>
                <div class="pred-row">
                    <span class="pred-plate"><?= htmlspecialchars($r['plate_no']) ?></span>
                    <span class="pred-hub"><?= htmlspecialchars($r['hub_name'] ?? '—') ?></span>
                    <span class="pred-date"><?= date('M j', strtotime($r['next_repair_date'])) ?></span>
                    <span class="pred-badge" style="background:#f0f0f0;color:#6b6b6b;">
                        <?= (int)$r['days_overdue'] ?>d ago
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
        
                <?php if (!empty($repairWeek)): ?>
                <p class="pred-group-label">📅 This week (<?= count($repairWeek) ?>)</p>
                <?php foreach ($repairWeek as $r): ?>
                <div class="pred-row">
                    <span class="pred-plate"><?= htmlspecialchars($r['plate_no']) ?></span>
                    <span class="pred-hub"><?= htmlspecialchars($r['hub_name'] ?? '—') ?></span>
                    <span class="pred-date"><?= date('M j', strtotime($r['next_repair_date'])) ?></span>
                    <span class="pred-badge" style="background:#fceaea;color:#b71513;">
                        <?= (int)$r['days_left'] ?>d
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
        
                <?php if (!empty($repairMonth)): ?>
                <p class="pred-group-label">📆 This month (<?= count($repairMonth) ?>)</p>
                <?php foreach ($repairMonth as $r): ?>
                <div class="pred-row">
                    <span class="pred-plate"><?= htmlspecialchars($r['plate_no']) ?></span>
                    <span class="pred-hub"><?= htmlspecialchars($r['hub_name'] ?? '—') ?></span>
                    <span class="pred-date"><?= date('M j', strtotime($r['next_repair_date'])) ?></span>
                    <span class="pred-badge" style="background:#fff4e6;color:#c47a0f;">
                        <?= (int)$r['days_left'] ?>d
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
        
                <?php if (empty($repairOverdue) && empty($repairWeek) && empty($repairMonth)): ?>
                <p class="pred-empty">No repair predictions available this month.</p>
                <?php endif; ?>
            </div>
        
        </div>
        <!-- END PREDICTIONS CARD -->
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 4 — RECENT TRANSACTIONS
    ═══════════════════════════════════════════════════════════ -->
    <div class="section-gap"></div>
    <p class="section-label">Recent transactions</p>
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <div>
                <p class="card-title">Latest 10 repair records</p>
                <p class="card-subtitle">Sorted by most recent date</p>
            </div>
            <a href="transactions.php" style="font-size:12px;color:var(--red);text-decoration:none;font-weight:700;">View all →</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Plate no.</th><th>Hub</th><th>Type</th>
                    <th>Mechanic</th><th>Labor cost</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentRows as $r):
                $stClass = $r['status_desc'] === 'completed' ? 'badge-green' : 'badge-amber';
                $tClass  = $r['type'] === 'PMS' ? 'badge-blue' : 'badge-gray';
                $mech    = $r['first_name'] ? htmlspecialchars($r['first_name'].' '.$r['last_name']) : '<span style="color:var(--ink-3)">—</span>';
            ?>
            <tr>
                <td style="color:var(--ink-3);font-size:12px"><?= $r['transaction_id'] ?></td>
                <td style="font-weight:700"><?= htmlspecialchars($r['plate_no']) ?></td>
                <td><?= htmlspecialchars($r['hub_name'] ?? '—') ?></td>
                <td><span class="badge <?= $tClass ?>"><?= strtoupper($r['type']) ?></span></td>
                <td><?= $mech ?></td>
                <td style="font-weight:700">₱<?= number_format($r['labor_cost'], 2) ?></td>
                <td style="color:var(--ink-3);font-size:12px"><?= date('M j, Y', strtotime($r['date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.main-panel -->

<script src="layout.js"></script>
<script>
Chart.defaults.font.family = 'Tahoma, sans-serif';
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6b6b6b';
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.padding  = 14;

const RED   = '#b71513';
const RSOFT = 'rgba(183,21,19,.15)';
const BLUE  = '#1a56c4';
const BSOFT = 'rgba(26,86,196,.15)';
const GREEN = '#1a7f4b';
const AMBER = '#c47a0f';
const GRAY  = '#9b9b9b';
const GSOFT = 'rgba(100,100,100,.12)';

const scaleOpts = {
    x: { grid: { display: false }, border: { display: false } },
    y: { grid: { color: '#f0f0f0' }, border: { display: false } }
};

// ── YoY JOBS LINE ───────────────────────────────────────────────
new Chart(document.getElementById('chartYoY'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthNames) ?>,
        datasets: [
            {
                label: '<?= $curYear ?>',
                data: <?= json_encode($yoyCurJobs) ?>,
                borderColor: RED, backgroundColor: RSOFT,
                borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                fill: true, tension: .35
            },
            {
                label: '<?= $prevYear ?>',
                data: <?= json_encode($yoyPrevJobs) ?>,
                borderColor: GRAY, backgroundColor: GSOFT,
                borderWidth: 1.5, borderDash: [5,3],
                pointRadius: 3, pointHoverRadius: 5,
                fill: true, tension: .35
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end' }, tooltip: { padding: 10, cornerRadius: 6 } },
        scales: scaleOpts
    }
});

// ── YoY REVENUE LINE ─────────────────────────────────────────────
new Chart(document.getElementById('chartYoYRev'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthNames) ?>,
        datasets: [
            {
                label: '₱ <?= $curYear ?>',
                data: <?= json_encode($yoyCurRev) ?>,
                borderColor: AMBER, backgroundColor: 'rgba(196,122,15,.12)',
                borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6,
                fill: true, tension: .35
            },
            {
                label: '₱ <?= $prevYear ?>',
                data: <?= json_encode($yoyPrevRev) ?>,
                borderColor: GRAY, backgroundColor: GSOFT,
                borderWidth: 1.5, borderDash: [5,3],
                pointRadius: 3, fill: true, tension: .35
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end' },
            tooltip: { padding: 10, cornerRadius: 6,
                callbacks: { label: ctx => ` ₱${ctx.parsed.y.toLocaleString()}` }
            }
        },
        scales: {
            x: scaleOpts.x,
            y: { ...scaleOpts.y, ticks: { callback: v => '₱' + (v/1000).toFixed(0) + 'k' } }
        }
    }
});

// ── REPAIR TYPE GROUPED BAR ───────────────────────────────────────
new Chart(document.getElementById('chartTypeSplit'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($typeSplitLabels) ?>,
        datasets: [
            {
                label: 'Current',
                data: <?= json_encode($typeSplitCur) ?>,
                backgroundColor: RED, borderRadius: 4, borderSkipped: false
            },
            {
                label: 'Previous',
                data: <?= json_encode($typeSplitPrev) ?>,
                backgroundColor: GSOFT, borderColor: GRAY,
                borderWidth: 1, borderRadius: 4, borderSkipped: false
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end' } },
        scales: {
            x: { ...scaleOpts.x, ticks: { maxRotation: 35, font: { size: 10 } } },
            y: scaleOpts.y
        }
    }
});

// ── AVG COST GROUPED BAR ─────────────────────────────────────────
new Chart(document.getElementById('chartAvgCost'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($avgCostLabels) ?>,
        datasets: [
            {
                label: 'Current avg (₱)',
                data: <?= json_encode($avgCostCur) ?>,
                backgroundColor: BLUE, borderRadius: 4, borderSkipped: false
            },
            {
                label: 'Previous avg (₱)',
                data: <?= json_encode($avgCostPrev) ?>,
                backgroundColor: GSOFT, borderColor: GRAY,
                borderWidth: 1, borderRadius: 4, borderSkipped: false
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end' },
            tooltip: { callbacks: { label: ctx => ` ₱${ctx.parsed.y.toLocaleString()}` } }
        },
        scales: {
            x: scaleOpts.x,
            y: { ...scaleOpts.y, ticks: { callback: v => '₱' + v.toLocaleString() } }
        }
    }
});

// ── EXISTING: MONTHLY BAR ────────────────────────────────────────
new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthlyLabels) ?>,
        datasets: [
            { label: 'Repairs', data: <?= json_encode($monthlyRepairs_data) ?>, backgroundColor: RED, borderRadius: 4, borderSkipped: false, order: 2 },
            { label: 'PMS', data: <?= json_encode($monthlyPMS_data) ?>, backgroundColor: BSOFT, borderColor: BLUE, borderWidth: 1.5, borderRadius: 4, borderSkipped: false, order: 1 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end' } },
        scales: { x: { ...scaleOpts.x, ticks: { maxRotation: 40 } }, y: scaleOpts.y }
    }
});

// ── EXISTING: HUB DOUGHNUT ───────────────────────────────────────
new Chart(document.getElementById('chartHub'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($hubLabels) ?>,
        datasets: [{ data: <?= json_encode($hubData) ?>, backgroundColor: [RED, BLUE, GREEN, AMBER], borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed} (${Math.round(ctx.parsed/ctx.dataset.data.reduce((a,b)=>a+b,0)*100)}%)` } }
        }
    }
});

// ── EXISTING: CATEGORY DOUGHNUT ──────────────────────────────────
new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{ data: <?= json_encode($catData) ?>, backgroundColor: [RED,'#e87c3e',AMBER,BLUE,GREEN,'#6b2fa0','#d94644'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '58%',
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});

function switchPredTab(tab) {
    document.querySelectorAll('.pred-tab').forEach(t => t.classList.remove('pred-tab-active'));
    document.querySelectorAll('.pred-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('pred-tab-active');
    document.getElementById('panel-' + tab).classList.add('active');
}

</script>
</body>
</html>