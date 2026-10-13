<?php
/**
 * Carbon Charts Catalog
 *
 * Demonstrates every IBM Carbon Charts visualization type available in the
 * pinned @carbon/charts@1.27.18 CDN build, wired to live ERP data wherever a
 * real source exists (representative shapes otherwise). Each card is a single
 * call to the shared bridge (window.renderCarbonChart), so dark-theme toggling,
 * USD tooltips and the empty-data guard come for free.
 *
 * Choropleth (needs a TopoJSON map) and InOn (not shipped in the UMD bundle)
 * are shown as info cards rather than live charts.
 */
$pageTitle = 'Carbon Charts Catalog';
require_once '../../includes/header.php';

if (!hasPermission('analytics.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();

// ── Budget totals (gauge, bullet, combo) via the F7 helpers ──────────────
require_once '../finance/helpers/budget_helpers.php';
$budgets = $db->query("SELECT * FROM budgets WHERE status NOT IN ('closed','rejected') ORDER BY budget_code")->fetchAll();
$budTotals = ['allocated' => 0.0, 'spent' => 0.0, 'pct' => 0.0];
$budMonthly = array_fill(1, 12, ['spent' => 0.0, 'budget' => 0.0]);
foreach ($budgets as $b) {
    $version = budget_active_version($db, $b['id']);
    if (!$version) continue;
    $bFy = ['id' => $b['financial_year_id'], 'start_date' => null, 'end_date' => null];
    $fyRow = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
    $fyRow->execute([$b['financial_year_id']]);
    if ($f = $fyRow->fetch()) { $bFy['start_date'] = $f['start_date']; $bFy['end_date'] = $f['end_date']; }
    $lines = budget_lines_for_version($db, $version['id'], $bFy);
    $bt = budget_lines_totals($lines);
    $budTotals['allocated'] += $bt['allocated'];
    $budTotals['spent']     += $bt['spent'];
    foreach ($lines as $l) {
        $m = (int)$l['month'];
        if ($m >= 1 && $m <= 12) {
            $budMonthly[$m]['spent']  += $l['spent'];
            $budMonthly[$m]['budget'] += (float)$l['budget_amount'];
        }
    }
}
$budTotals['pct'] = $budTotals['allocated'] > 0
    ? min(100.0, round($budTotals['spent'] / $budTotals['allocated'] * 100, 1)) : 0.0;

// Combo labels/values: months (Jul–Jun) that carry a budget figure
$budMonthLabels = []; $budMonthBudget = []; $budMonthSpent = [];
for ($m = 1; $m <= 12; $m++) {
    if ($budMonthly[$m]['budget'] > 0) {
        $budMonthLabels[] = budget_month_name($m);
        $budMonthBudget[] = round($budMonthly[$m]['budget'], 2);
        $budMonthSpent[]  = round($budMonthly[$m]['spent'], 2);
    }
}
$budBulletTarget = $budTotals['allocated'] > 0 ? round($budTotals['allocated'], 2) : 50000;
$budBulletRanges = [$budTotals['allocated'] > 0 ? round($budTotals['allocated'] * 0.5)  : 25000,
                    $budTotals['allocated'] > 0 ? round($budTotals['allocated'] * 1.0)  : 50000,
                    $budTotals['allocated'] > 0 ? round($budTotals['allocated'] * 1.25) : 62500];

// ── Revenue & expenses, last 6 months (grouped bar, area, stacked area) ──
$revRows = $db->query("SELECT DATE_FORMAT(invoice_date,'%b') AS month, SUM(paid_amount) AS revenue
                       FROM invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                       GROUP BY DATE_FORMAT(invoice_date,'%Y-%m') ORDER BY invoice_date ASC")->fetchAll();
$expRows = $db->query("SELECT DATE_FORMAT(expense_date,'%b') AS month, SUM(amount) AS expenses
                       FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status='approved'
                       GROUP BY DATE_FORMAT(expense_date,'%Y-%m') ORDER BY expense_date ASC")->fetchAll();
$months6 = []; $revByMonth = []; $expByMonth = [];
for ($i = 5; $i >= 0; $i--) { $m = date('M', strtotime("-$i months")); $months6[] = $m; $revByMonth[$m] = 0.0; $expByMonth[$m] = 0.0; }
foreach ($revRows as $r) if (isset($revByMonth[$r['month']])) $revByMonth[$r['month']] = round((float)$r['revenue'], 2);
foreach ($expRows as $e) if (isset($expByMonth[$e['month']])) $expByMonth[$e['month']] = round((float)$e['expenses'], 2);
$revArr  = array_values($revByMonth);
$expArr  = array_values($expByMonth);

// ── Attendance trend, last 90 days (line) ────────────────────────────────
$attTrend = $db->query("SELECT attendance_date, ROUND(SUM(status='present')/COUNT(*)*100,1) AS pct
                        FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                        GROUP BY attendance_date ORDER BY attendance_date ASC")->fetchAll();

// ── Attendance by status, last 90 days (stacked bar) ─────────────────────
$attStack = $db->query("SELECT attendance_date, status, COUNT(*) AS n
                        FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                        GROUP BY attendance_date, status ORDER BY attendance_date ASC")->fetchAll();
$attDays = []; $attStatus = []; $attSeries = [];
foreach ($attStack as $a) {
    $attDays[$a['attendance_date']] = true;
    $attStatus[$a['status']] = true;
}
$attDays = array_keys($attDays);
foreach (array_keys($attStatus) as $s) $attSeries[$s] = array_fill(0, count($attDays), 0);
foreach ($attStack as $a) {
    $attSeries[$a['status']][array_search($a['attendance_date'], $attDays)] = (int)$a['n'];
}

// ── Department headcount (simple bar, horizontal bar) ────────────────────
$deptHead = $db->query("SELECT d.department_name, COUNT(e.id) AS cnt
                        FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active'
                        GROUP BY d.id ORDER BY cnt DESC, d.department_name ASC LIMIT 8")->fetchAll();

// ── Top expense categories (treemap, horizontal) ─────────────────────────
$expCats = $db->query("SELECT ec.name AS cat, SUM(e.amount) AS total
                       FROM expenses e JOIN expense_categories ec ON ec.id=e.category_id
                       WHERE e.status='approved' GROUP BY ec.name ORDER BY total DESC LIMIT 10")->fetchAll();

// ── Expense amount observations (histogram) ──────────────────────────────
$expAmts = $db->query("SELECT amount FROM expenses WHERE status='approved' ORDER BY amount DESC LIMIT 60")
                ->fetchAll(PDO::FETCH_COLUMN);
$expAmts = array_map('floatval', $expAmts);

// ── Attendance present-count per dept per day (boxplot) ──────────────────
$boxRows = $db->query("SELECT d.department_name AS dept, SUM(a.status='present') AS present
                       FROM attendance a JOIN employees e ON a.employee_id=e.id
                       LEFT JOIN departments d ON e.department_id=d.id
                       WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                       GROUP BY d.id, d.department_name, a.attendance_date")->fetchAll();
$boxObs = [];
foreach ($boxRows as $br) {
    if (!$br['dept']) continue;
    $boxObs[] = ['group' => $br['dept'], 'value' => (int)$br['present']];
}
$boxGroups = [];
foreach ($boxObs as $bo) $boxGroups[$bo['group']] = true;
$boxLabels = array_keys($boxGroups);

// ── Invoice balance vs days outstanding (scatter, bubble) ────────────────
$invScat = $db->query("SELECT i.balance_amount AS bal, DATEDIFF(CURDATE(), COALESCE(i.due_date, i.invoice_date)) AS days, i.total_amount AS total
                       FROM invoices i WHERE i.balance_amount > 0 ORDER BY days DESC LIMIT 40")->fetchAll();
$scatPts = []; $bubPts = [];
foreach ($invScat as $pt) {
    $scatPts[] = ['x' => (int)$pt['days'], 'y' => round((float)$pt['bal'], 2)];
    $bubPts[]  = ['x' => (int)$pt['days'], 'y' => round((float)$pt['bal'], 2), 'value' => round((float)$pt['total'], 2)];
}

// ── Ticket status (donut), source→status (alluvial) ──────────────────────
$ticketStatus = $db->query("SELECT status, COUNT(*) AS n FROM tickets GROUP BY status")->fetchAll();
$ticketFlow   = $db->query("SELECT source, status, COUNT(*) AS n FROM tickets GROUP BY source, status")->fetchAll();
$allNodes = []; $nodeIdx = []; $allLinks = [];
$srcNames = ['web'=>'Web','email'=>'Email','phone'=>'Phone','internal'=>'Internal'];
$statusNames = ['open'=>'Open','in_progress'=>'In progress','waiting_client'=>'Waiting','resolved'=>'Resolved','closed'=>'Closed','reopened'=>'Reopened'];
foreach ($ticketFlow as $tf) {
    $s = $srcNames[$tf['source']] ?? ucfirst($tf['source']);
    $t = $statusNames[$tf['status']] ?? ucfirst($tf['status']);
    if (!isset($nodeIdx[$s])) { $nodeIdx[$s] = count($allNodes); $allNodes[] = ['name'=>$s,'category'=>'Source']; }
    if (!isset($nodeIdx[$t])) { $nodeIdx[$t] = count($allNodes); $allNodes[] = ['name'=>$t,'category'=>'Status']; }
    $allLinks[] = ['source'=>$s, 'target'=>$t, 'value'=>(int)$tf['n']];
}

// ── Lead sources (pie) ───────────────────────────────────────────────────
$leadSources = $db->query("SELECT source, COUNT(*) AS n FROM leads GROUP BY source ORDER BY n DESC LIMIT 6")->fetchAll();

// ── Average attendance % (meter) ─────────────────────────────────────────
$avgAtt = (float)$db->query("SELECT ROUND(SUM(status='present')/COUNT(*)*100,1)
                             FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();

// ── Ticket subject keywords (wordcloud) ──────────────────────────────────
$subjects = $db->query("SELECT subject FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
$stop = ['the','and','for','with','from','that','this','your','our','not','are','was','were','will','have','has','had','can','all','per','new','please','issue','into','over','after','about','out','more','when','what','which','while','who','would','could','should','may','might','must','like','than','then','there','these','those','due','able'];
$wc = [];
foreach ($subjects as $s) {
    foreach (preg_split('/[^A-Za-z0-9]+/', strtolower((string)$s)) as $w) {
        if (strlen($w) < 3 || in_array($w, $stop, true)) continue;
        $wc[$w] = ($wc[$w] ?? 0) + 1;
    }
}
arsort($wc);
$wcWords  = array_slice(array_keys($wc), 0, 24);
$wcCounts = array_slice(array_values($wc), 0, 24);

// ── Top clients by outstanding balance (lollipop) ────────────────────────
$topClients = $db->query("SELECT c.company_name AS client, SUM(i.balance_amount) AS bal
                          FROM invoices i JOIN clients c ON c.id=i.client_id
                          WHERE i.balance_amount > 0 GROUP BY c.company_name ORDER BY bal DESC LIMIT 8")->fetchAll();

// ── Department performance (radar) ───────────────────────────────────────
$radarDepts = $db->query("SELECT id, department_name FROM departments ORDER BY department_name LIMIT 5")->fetchAll();
$radarMetrics = ['Headcount', 'Attendance %', 'Open tickets', 'Resolved'];
$radarDatasets = [];
foreach ($radarDepts as $d) {
    $hc  = $db->prepare("SELECT COUNT(*) FROM employees WHERE department_id=? AND status='active'"); $hc->execute([$d['id']]); $hc = (int)$hc->fetchColumn();
    $att = $db->prepare("SELECT ROUND(SUM(a.status='present')/COUNT(*)*100,1) FROM attendance a JOIN employees e ON a.employee_id=e.id
                         WHERE e.department_id=? AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"); $att->execute([$d['id']]); $att = (float)$att->fetchColumn();
    $op  = $db->prepare("SELECT COUNT(*) FROM tickets t JOIN employees e ON t.employee_id=e.id
                         WHERE e.department_id=? AND t.status IN ('open','in_progress')"); $op->execute([$d['id']]); $op = (int)$op->fetchColumn();
    $rs  = $db->prepare("SELECT COUNT(*) FROM tickets t JOIN employees e ON t.employee_id=e.id
                         WHERE e.department_id=? AND t.status IN ('resolved','closed')"); $rs->execute([$d['id']]); $rs = (int)$rs->fetchColumn();
    $radarDatasets[] = ['label' => $d['department_name'], 'data' => [$hc, $att, $op, $rs]];
}

// ── Department → designations (tree) ─────────────────────────────────────
$treeRows = $db->query("SELECT d.department_name, desig.designation_name
                        FROM departments d LEFT JOIN designations desig ON desig.department_id=d.id
                        ORDER BY d.department_name, desig.designation_name")->fetchAll();
$treeChildren = [];
foreach ($treeRows as $tr) {
    if (!isset($treeChildren[$tr['department_name']])) $treeChildren[$tr['department_name']] = [];
    if ($tr['designation_name']) $treeChildren[$tr['department_name']][] = ['name' => $tr['designation_name']];
}
$treeNodes = [];
foreach ($treeChildren as $dept => $kids) {
    $treeNodes[] = ['name' => $dept, 'children' => array_slice($kids, 0, 6)];
}

// ── Department → employees (circlepack / pack) ───────────────────────────
$deptEmp = $db->query("SELECT d.department_name, COUNT(e.id) AS cnt
                       FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active'
                       GROUP BY d.id ORDER BY cnt DESC LIMIT 6")->fetchAll();
$packNodes = [];
foreach ($deptEmp as $de) {
    $packNodes[] = ['name' => $de['department_name'], 'value' => max((int)$de['cnt'], 1)];
}

// ── Attendance dept × day (heatmap) ──────────────────────────────────────
$attHeatRows = $db->query("SELECT d.department_name AS dept, a.attendance_date AS day, ROUND(SUM(a.status='present')/COUNT(*)*100,1) AS pct
                           FROM attendance a JOIN employees e ON a.employee_id=e.id
                           LEFT JOIN departments d ON e.department_id=d.id
                           WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                           GROUP BY d.id, d.department_name, a.attendance_date")->fetchAll();
$heatDays = [];
for ($i = 89; $i >= 0; $i--) $heatDays[] = date('Y-m-d', strtotime("-$i days"));
$heatByDept = [];
foreach ($attHeatRows as $ah) {
    if (!$ah['dept']) continue;
    if (!isset($heatByDept[$ah['dept']])) $heatByDept[$ah['dept']] = [];
    $heatByDept[$ah['dept']][$ah['day']] = (float)$ah['pct'];
}
?>
<style>
  .cc-box { height: 265px; position: relative; }
  .cc-card { background: var(--cds-layer,#fff); border: 1px solid var(--cds-border-subtle,#e5e7eb);
             border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .cc-card h3 { font-size: .95rem; font-weight: 600; color: var(--cds-text-primary,#1f2937); margin: 0 0 2px; }
  .cc-card .type { font-size: .7rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                   color: var(--cds-text-secondary,#6b7280); margin-bottom: 10px; }
  .cc-card p.desc { font-size: .78rem; color: var(--cds-text-secondary,#6b7280); margin: 8px 0 0; line-height: 1.45; }
  .cc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 18px; }
  .cc-section { margin-top: 28px; }
  .cc-section h2 { font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; font-weight: 700;
                   color: var(--cds-text-secondary,#6b7280); border-bottom: 1px solid var(--cds-border-subtle,#e5e7eb); padding-bottom: 6px; margin-bottom: 14px; }
  .cc-note { background: rgba(59,130,246,.08); border: 1px dashed rgba(59,130,246,.4); border-radius: 12px; padding: 16px; }
  .cc-note h3 { font-size: .95rem; font-weight: 600; margin: 0 0 2px; }
</style>

<div id="dashboard-old-content">
<div class="mb-6">
    <h1 class="text-2xl font-bold" style="color:var(--cds-text-primary,#111827)">Carbon Charts Catalog</h1>
    <p class="mt-1" style="color:var(--cds-text-secondary,#6b7280)">
        Every IBM Carbon Charts visualization available in this ERP (<code>@carbon/charts@1.27.18</code>), rendered live from app data.
        Flip the theme (top-right) to watch every chart re-render — each card is one call to <code>window.renderCarbonChart</code>.
    </p>
</div>

<!-- ── Comparisons ─────────────────────────────────────────────────────── -->
<div class="cc-section"><h2>Comparisons — bars</h2><div class="cc-grid">

  <div class="cc-card">
    <h3>Simple Bar</h3><div class="type">type: 'bar' (1 dataset)</div>
    <div id="cc-bar" class="cc-box"></div>
    <p class="desc">Active headcount per department.</p>
  </div>

  <div class="cc-card">
    <h3>Grouped Bar</h3><div class="type">type: 'bar' (2 datasets)</div>
    <div id="cc-grouped" class="cc-box"></div>
    <p class="desc">Revenue vs approved expenses, last 6 months.</p>
  </div>

  <div class="cc-card">
    <h3>Stacked Bar</h3><div class="type">type: 'stacked'</div>
    <div id="cc-stacked" class="cc-box"></div>
    <p class="desc">Daily attendance records by status, last 90 days.</p>
  </div>

  <div class="cc-card">
    <h3>Horizontal Bar</h3><div class="type">type: 'horizontal'</div>
    <div id="cc-hbar" class="cc-box"></div>
    <p class="desc">Top expense categories by approved spend.</p>
  </div>

  <div class="cc-card">
    <h3>Lollipop</h3><div class="type">type: 'lollipop'</div>
    <div id="cc-lollipop" class="cc-box"></div>
    <p class="desc">Top clients by outstanding invoice balance.</p>
  </div>

  <div class="cc-card">
    <h3>Bullet</h3><div class="type">type: 'bullet'</div>
    <div id="cc-bullet" class="cc-box"></div>
    <p class="desc">Budget spend against target (marker) and quality bands.</p>
  </div>
</div></div>

<!-- ── Trends ──────────────────────────────────────────────────────────── -->
<div class="cc-section"><h2>Trends — line &amp; area</h2><div class="cc-grid">

  <div class="cc-card">
    <h3>Line</h3><div class="type">type: 'line'</div>
    <div id="cc-line" class="cc-box"></div>
    <p class="desc">Daily attendance percentage, last 90 days.</p>
  </div>

  <div class="cc-card">
    <h3>Area</h3><div class="type">type: 'area'</div>
    <div id="cc-area" class="cc-box"></div>
    <p class="desc">Monthly revenue, last 6 months.</p>
  </div>

  <div class="cc-card">
    <h3>Stacked Area</h3><div class="type">type: 'stacked-area'</div>
    <div id="cc-stackarea" class="cc-box"></div>
    <p class="desc">Revenue and expenses stacked, last 6 months.</p>
  </div>

  <div class="cc-card">
    <h3>Combo</h3><div class="type">type: 'combo' (bar + line)</div>
    <div id="cc-combo" class="cc-box"></div>
    <p class="desc">Budget allocated (bars) vs actual spend (line) by month.</p>
  </div>

  <div class="cc-card">
    <h3>Radar</h3><div class="type">type: 'radar'</div>
    <div id="cc-radar" class="cc-box"></div>
    <p class="desc">Department performance across four live metrics.</p>
  </div>
</div></div>

<!-- ── Distributions ───────────────────────────────────────────────────── -->
<div class="cc-section"><h2>Distributions — scatter, bubble &amp; stats</h2><div class="cc-grid">

  <div class="cc-card">
    <h3>Scatter</h3><div class="type">type: 'scatter'</div>
    <div id="cc-scatter" class="cc-box"></div>
    <p class="desc">Invoice balance vs days outstanding.</p>
  </div>

  <div class="cc-card">
    <h3>Bubble</h3><div class="type">type: 'bubble' (radius = value)</div>
    <div id="cc-bubble" class="cc-box"></div>
    <p class="desc">Same invoices; bubble size = total invoice value.</p>
  </div>

  <div class="cc-card">
    <h3>Histogram</h3><div class="type">type: 'histogram'</div>
    <div id="cc-hist" class="cc-box"></div>
    <p class="desc">Distribution of approved expense amounts.</p>
  </div>

  <div class="cc-card">
    <h3>Boxplot</h3><div class="type">type: 'boxplot'</div>
    <div id="cc-box" class="cc-box"></div>
    <p class="desc">Daily present-count distribution per department (90 days).</p>
  </div>
</div></div>

<!-- ── Parts of a whole ────────────────────────────────────────────────── -->
<div class="cc-section"><h2>Parts of a whole — pie, donut &amp; gauges</h2><div class="cc-grid">

  <div class="cc-card">
    <h3>Pie</h3><div class="type">type: 'pie'</div>
    <div id="cc-pie" class="cc-box"></div>
    <p class="desc">Lead sources.</p>
  </div>

  <div class="cc-card">
    <h3>Donut</h3><div class="type">type: 'donut'</div>
    <div id="cc-donut" class="cc-box"></div>
    <p class="desc">Ticket status breakdown.</p>
  </div>

  <div class="cc-card">
    <h3>Gauge</h3><div class="type">type: 'gauge'</div>
    <div id="cc-gauge" class="cc-box"></div>
    <p class="desc">Budget utilization percentage (0–100).</p>
  </div>

  <div class="cc-card">
    <h3>Meter</h3><div class="type">type: 'meter'</div>
    <div id="cc-meter" class="cc-box"></div>
    <p class="desc">Average attendance percentage (90 days).</p>
  </div>
</div></div>

<!-- ── Hierarchy &amp; flow ─────────────────────────────────────────────── -->
<div class="cc-section"><h2>Hierarchy &amp; flow</h2><div class="cc-grid">

  <div class="cc-card">
    <h3>Treemap</h3><div class="type">type: 'treemap'</div>
    <div id="cc-treemap" class="cc-box"></div>
    <p class="desc">Approved expenses by category, sized by amount.</p>
  </div>

  <div class="cc-card">
    <h3>Tree</h3><div class="type">type: 'tree'</div>
    <div id="cc-tree" class="cc-box"></div>
    <p class="desc">Department → designation hierarchy.</p>
  </div>

  <div class="cc-card">
    <h3>Circle Pack</h3><div class="type">type: 'circlepack'</div>
    <div id="cc-circlepack" class="cc-box"></div>
    <p class="desc">Departments sized by active headcount.</p>
  </div>

  <div class="cc-card">
    <h3>Pack</h3><div class="type">type: 'pack' (→ CirclePack)</div>
    <div id="cc-pack" class="cc-box"></div>
    <p class="desc">Same hierarchy rendered as a flat d3 pack layout.</p>
  </div>

  <div class="cc-card">
    <h3>Alluvial</h3><div class="type">type: 'alluvial'</div>
    <div id="cc-alluvial" class="cc-box"></div>
    <p class="desc">Ticket source → status flow.</p>
  </div>

  <div class="cc-card">
    <h3>Word Cloud</h3><div class="type">type: 'wordcloud'</div>
    <div id="cc-cloud" class="cc-box"></div>
    <p class="desc">Frequent keywords across ticket subjects.</p>
  </div>
</div></div>

<!-- ── Matrix ──────────────────────────────────────────────────────────── -->
<div class="cc-section"><h2>Matrix</h2><div class="cc-grid">

  <div class="cc-card">
    <h3>Heatmap</h3><div class="type">type: 'heatmap'</div>
    <div id="cc-heatmap" class="cc-box"></div>
    <p class="desc">Attendance percentage, department × day (90 days).</p>
  </div>
</div></div>

<!-- ── Not in this build ───────────────────────────────────────────────── -->
<div class="cc-section"><h2>Not available in the CDN build</h2><div class="cc-grid">

  <div class="cc-note cc-card">
    <h3>Choropleth</h3><div class="type">type: 'choropleth'</div>
    <p class="desc">Geographic map that requires a TopoJSON feature set per region. Not wired up here; the bridge maps the type to <code>Charts.ChoroplethChart</code> if you supply map data.</p>
  </div>

  <div class="cc-note cc-card">
    <h3>InOn</h3><div class="type">type: 'inon'</div>
    <p class="desc">In/On (institute vs online) split is an experimental Carbon chart not shipped in the <code>@carbon/charts@1.27.18</code> UMD bundle. The bridge keeps the alias for forward compatibility.</p>
  </div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const render = window.renderCarbonChart;
    if (!render) return;
    const USD = { tooltip: {} }; // currency handled by bridge's default formatter when present

    // ── Comparisons ──
    const barEl = document.getElementById('cc-bar');
    if (barEl) render(barEl, 'bar', {
        labels: <?= json_encode(array_column($deptHead, 'department_name') ?: ['No data']) ?>,
        datasets: [{ label: 'Employees', data: <?= json_encode(array_map('intval', array_column($deptHead, 'cnt')) ?: [0]) ?>, backgroundColor: 'rgba(99,102,241,0.75)' }]
    }, { legend: { enabled: false } });

    const groupedEl = document.getElementById('cc-grouped');
    if (groupedEl) render(groupedEl, 'bar', {
        labels: <?= json_encode($months6) ?>,
        datasets: [
            { label: 'Revenue',  data: <?= json_encode($revArr) ?>, backgroundColor: 'rgba(16,185,129,0.7)' },
            { label: 'Expenses', data: <?= json_encode($expArr) ?>, backgroundColor: 'rgba(239,68,68,0.7)' }
        ]
    }, { legend: { position: 'bottom' } });

    const stackedEl = document.getElementById('cc-stacked');
    if (stackedEl) {
        const stSeries = [];
        <?php foreach ($attSeries as $st => $vals): ?>
        stSeries.push({ label: <?= json_encode($st) ?>, data: <?= json_encode(array_map('intval', $vals)) ?> });
        <?php endforeach; ?>
        render(stackedEl, 'stacked', {
            labels: <?= json_encode($attDays ?: ['No data']) ?>,
            datasets: stSeries.length ? stSeries : [{ label: 'No data', data: [1] }]
        }, { legend: { position: 'bottom' }, axes: { left: { stacked: true }, bottom: { stacked: true } } });
    }

    const hbarEl = document.getElementById('cc-hbar');
    if (hbarEl) render(hbarEl, 'horizontal', {
        labels: <?= json_encode(array_column($expCats, 'cat') ?: ['No data']) ?>,
        datasets: [{ label: 'Amount', data: <?= json_encode(array_map('floatval', array_column($expCats, 'total')) ?: [0]) ?>, backgroundColor: 'rgba(245,158,11,0.75)' }]
    }, { legend: { enabled: false } });

    const lolliEl = document.getElementById('cc-lollipop');
    if (lolliEl) render(lolliEl, 'lollipop', {
        labels: <?= json_encode(array_column($topClients, 'client') ?: ['No data']) ?>,
        datasets: [{ label: 'Outstanding', data: <?= json_encode(array_map('floatval', array_column($topClients, 'bal')) ?: [0]) ?> }]
    }, { legend: { enabled: false } });

    const bulletEl = document.getElementById('cc-bullet');
    if (bulletEl) render(bulletEl, 'bullet', {
        labels: ['Budget'],
        datasets: [{ label: 'Budget', data: [{ title: 'Budget', group: 'Budget', value: <?= round($budTotals['spent'], 2) ?>, marker: <?= $budBulletTarget ?>, ranges: <?= json_encode($budBulletRanges) ?> }] }]
    }, { legend: { enabled: false } });

    // ── Trends ──
    const lineEl = document.getElementById('cc-line');
    if (lineEl) render(lineEl, 'line', {
        labels: <?= json_encode(array_column($attTrend, 'attendance_date') ?: ['No data']) ?>,
        datasets: [{ label: 'Attendance %', data: <?= json_encode(array_map('floatval', array_column($attTrend, 'pct')) ?: [0]) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true }]
    }, { legend: { enabled: false }, curve: 'curveMonotoneX', getAreaOpacity: function () { return 0.18; } });

    const areaEl = document.getElementById('cc-area');
    if (areaEl) render(areaEl, 'area', {
        labels: <?= json_encode($months6) ?>,
        datasets: [{ label: 'Revenue', data: <?= json_encode($revArr) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.15)' }]
    }, { legend: { enabled: false }, curve: 'curveMonotoneX', getAreaOpacity: function () { return 0.2; } });

    const stackAreaEl = document.getElementById('cc-stackarea');
    if (stackAreaEl) render(stackAreaEl, 'stacked-area', {
        labels: <?= json_encode($months6) ?>,
        datasets: [
            { label: 'Revenue',  data: <?= json_encode($revArr) ?>, backgroundColor: 'rgba(16,185,129,0.6)' },
            { label: 'Expenses', data: <?= json_encode($expArr) ?>, backgroundColor: 'rgba(239,68,68,0.6)' }
        ]
    }, { legend: { position: 'bottom' } });

    const comboEl = document.getElementById('cc-combo');
    if (comboEl) render(comboEl, 'combo', {
        labels: <?= json_encode($budMonthLabels ?: ['No data']) ?>,
        datasets: [
            { label: 'Budget', data: <?= json_encode($budMonthBudget ?: [0]) ?>, backgroundColor: 'rgba(59,130,246,0.7)' },
            { label: 'Spent',  data: <?= json_encode($budMonthSpent ?: [0]) ?>,  borderColor: '#10b981' }
        ]
    }, { chartTypes: ['bar', 'line'], legend: { position: 'bottom' } });

    const radarEl = document.getElementById('cc-radar');
    if (radarEl) render(radarEl, 'radar', {
        labels: <?= json_encode($radarMetrics) ?>,
        datasets: <?= json_encode($radarDatasets) ?>
    }, { legend: { position: 'bottom' } });

    // ── Distributions ──
    const scatEl = document.getElementById('cc-scatter');
    if (scatEl) render(scatEl, 'scatter', {
        labels: ['Outstanding'],
        datasets: [{ label: 'Invoices', data: <?= json_encode($scatPts) ?> }]
    }, { legend: { enabled: false } });

    const bubbleEl = document.getElementById('cc-bubble');
    if (bubbleEl) render(bubbleEl, 'bubble', {
        labels: ['Outstanding'],
        datasets: [{ label: 'Invoices', data: <?= json_encode($bubPts) ?> }]
    }, { legend: { enabled: false } });

    const histEl = document.getElementById('cc-hist');
    if (histEl) render(histEl, 'histogram', {
        labels: [], datasets: [{ label: 'Expenses', data: <?= json_encode($expAmts) ?> }]
    }, { legend: { enabled: false } });

    const boxEl = document.getElementById('cc-box');
    if (boxEl) render(boxEl, 'boxplot', {
        labels: <?= json_encode($boxLabels) ?>,
        datasets: [{ label: 'Present', data: <?= json_encode($boxObs) ?> }]
    }, { axes: { bottom: { scaleType: 'labels', mapsTo: 'group' }, left: { scaleType: 'linear', mapsTo: 'value' } }, legend: { enabled: false } });

    // ── Parts of a whole ──
    const pieEl = document.getElementById('cc-pie');
    if (pieEl) render(pieEl, 'pie', {
        labels: <?= json_encode(array_column($leadSources, 'source') ?: ['No data']) ?>,
        datasets: [{ data: <?= json_encode(array_map('intval', array_column($leadSources, 'n')) ?: [1]) ?>, backgroundColor: ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#ec4899'] }]
    }, { legend: { position: 'bottom' } });

    const donutEl = document.getElementById('cc-donut');
    if (donutEl) render(donutEl, 'donut', {
        labels: <?= json_encode(array_column($ticketStatus, 'status') ?: ['No data']) ?>,
        datasets: [{ data: <?= json_encode(array_map('intval', array_column($ticketStatus, 'n')) ?: [1]) ?>, backgroundColor: ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6'] }]
    }, { legend: { position: 'bottom' } });

    const gaugeEl = document.getElementById('cc-gauge');
    if (gaugeEl) render(gaugeEl, 'gauge', {
        labels: ['Budget utilization'], datasets: [{ label: 'Budget utilization', data: [<?= $budTotals['pct'] ?>] }]
    }, { legend: { enabled: false } });

    const meterEl = document.getElementById('cc-meter');
    if (meterEl) render(meterEl, 'meter', {
        labels: ['Avg attendance'], datasets: [{ label: 'Avg attendance', data: [<?= min(100, $avgAtt) ?>] }]
    }, { legend: { enabled: false } });

    // ── Hierarchy & flow ──
    const treemapEl = document.getElementById('cc-treemap');
    if (treemapEl) render(treemapEl, 'treemap', {
        labels: [], datasets: [{ data: <?= json_encode(array_map(function ($c) { return ['name' => $c['cat'], 'value' => round((float)$c['total'], 2)]; }, $expCats)) ?> }]
    }, { legend: { enabled: false } });

    const treeEl = document.getElementById('cc-tree');
    if (treeEl) render(treeEl, 'tree', <?= json_encode($treeNodes) ?>, { tree: { rootTitle: 'ERP' }, legend: { enabled: false } });

    const circleEl = document.getElementById('cc-circlepack');
    if (circleEl) render(circleEl, 'circlepack', <?= json_encode($packNodes) ?>, { legend: { enabled: false } });

    const packEl = document.getElementById('cc-pack');
    if (packEl) render(packEl, 'pack', <?= json_encode($packNodes) ?>, { legend: { enabled: false } });

    const alluvialEl = document.getElementById('cc-alluvial');
    if (alluvialEl) render(alluvialEl, 'alluvial', <?= json_encode($allLinks) ?>, { alluvial: { nodes: <?= json_encode($allNodes) ?> }, legend: { enabled: false } });

    const cloudEl = document.getElementById('cc-cloud');
    if (cloudEl) render(cloudEl, 'wordcloud', {
        labels: <?= json_encode($wcWords ?: ['data']) ?>,
        datasets: [{ label: 'Keywords', data: <?= json_encode(array_map('intval', $wcCounts) ?: [1]) ?>, backgroundColor: '#6366f1' }]
    }, { legend: { enabled: false } });

    // ── Matrix ──
    const heatEl = document.getElementById('cc-heatmap');
    if (heatEl) {
        const depts = Object.keys(<?= json_encode($heatByDept) ?>);
        render(heatEl, 'heatmap', {
            labels: <?= json_encode($heatDays) ?>,
            datasets: depts.map(function (d) {
                const map = <?= json_encode($heatByDept) ?>;
                return { label: d, data: <?= json_encode($heatDays) ?>.map(function (day) { return { value: map[d][day] !== undefined ? map[d][day] : 0 }; }) };
            })
        }, { legend: { position: 'bottom' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->
<?php
require_once '../../includes/footer.php';
?>
