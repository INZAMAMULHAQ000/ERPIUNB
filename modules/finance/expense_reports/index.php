<?php
$pageTitle = 'Expense Reports';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('expense_reports.view')) { die('Access Denied'); }

$db = getDB();

$report_type = $_GET['report_type'] ?? 'category';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Validate date format — dates are interpolated directly into SQL below, so reject anything that isn't a plain date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $date_from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) { $date_to = date('Y-m-t'); }
$employee_id = $_GET['employee_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$page = getPageNumber();
$perPage = 20;

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$categories = $db->query("SELECT id, name FROM expense_categories ORDER BY name")->fetchAll();

// Build report based on type
$reportTitle = '';
$reportHeaders = [];
$reportRows = [];
$reportTotal = 0;

switch ($report_type) {
    case 'category':
        $reportTitle = 'Category-wise Expense Report';
        $reportHeaders = ['Category', 'Company Expenses', 'Employee Claims', 'Total'];
        $rows = $db->query("
            SELECT ec.name,
                COALESCE((SELECT SUM(ce.total_amount) FROM company_expenses ce WHERE ce.category_id = ec.id AND ce.expense_date BETWEEN '$date_from' AND '$date_to' AND ce.status NOT IN ('cancelled','draft')), 0) AS company_total,
                COALESCE((SELECT SUM(eec.total_amount) FROM employee_expense_claims eec WHERE eec.category_id = ec.id AND eec.expense_date BETWEEN '$date_from' AND '$date_to' AND eec.status NOT IN ('cancelled','draft')), 0) AS claim_total
            FROM expense_categories ec
            ORDER BY ec.name
        ")->fetchAll();
        foreach ($rows as $r) {
            $total = (float)$r['company_total'] + (float)$r['claim_total'];
            $reportRows[] = [
                sanitize($r['name']),
                formatMoney((float)$r['company_total']),
                formatMoney((float)$r['claim_total']),
                formatMoney($total),
            ];
            $reportTotal += $total;
        }
        break;

    case 'employee':
        $reportTitle = 'Employee-wise Expense Report';
        $reportHeaders = ['Employee', 'Total Claims', 'Total Reimbursed', 'Pending'];
        $empWhere = $employee_id ? "AND eec.employee_id = " . (int)$employee_id : "";
        $rows = $db->query("
            SELECT e.id, e.first_name, e.last_name,
                COALESCE(SUM(eec.total_amount), 0) AS total_claims,
                COALESCE(SUM(CASE WHEN eec.status = 'reimbursed' THEN eec.total_amount ELSE 0 END), 0) AS total_reimbursed,
                COALESCE(SUM(CASE WHEN eec.status IN ('pending_manager','pending_finance','approved') THEN eec.total_amount ELSE 0 END), 0) AS total_pending
            FROM employees e
            LEFT JOIN employee_expense_claims eec ON eec.employee_id = e.id AND eec.expense_date BETWEEN '$date_from' AND '$date_to' AND eec.status NOT IN ('cancelled','draft')
            WHERE e.status = 'active' $empWhere
            GROUP BY e.id, e.first_name, e.last_name
            ORDER BY total_claims DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            $reportRows[] = [
                sanitize($r['first_name'] . ' ' . $r['last_name']),
                formatMoney((float)$r['total_claims']),
                formatMoney((float)$r['total_reimbursed']),
                formatMoney((float)$r['total_pending']),
            ];
            $reportTotal += (float)$r['total_claims'];
        }
        break;

    case 'department':
        $reportTitle = 'Department-wise Expense Report';
        $reportHeaders = ['Department', 'Total Expenses', 'Count'];
        $deptWhere = $department_id ? "AND ce.department_id = " . (int)$department_id : "";
        $rows = $db->query("
            SELECT d.department_name,
                COALESCE(SUM(ce.total_amount), 0) AS total,
                COUNT(ce.id) AS cnt
            FROM departments d
            LEFT JOIN company_expenses ce ON ce.department_id = d.id AND ce.expense_date BETWEEN '$date_from' AND '$date_to' AND ce.status NOT IN ('cancelled','draft')
            WHERE d.status = 'active' $deptWhere
            GROUP BY d.id, d.department_name
            ORDER BY total DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            $reportRows[] = [
                sanitize($r['department_name']),
                formatMoney((float)$r['total']),
                (int)$r['cnt'],
            ];
            $reportTotal += (float)$r['total'];
        }
        break;

    case 'project':
        $reportTitle = 'Project-wise Expense Report';
        $reportHeaders = ['Project', 'Company Expenses', 'Employee Claims', 'Total'];
        $projWhereCe = $project_id ? "AND ce.project_id = " . (int)$project_id : "";
        $projWhereEec = $project_id ? "AND eec.project_id = " . (int)$project_id : "";
        $rows = $db->query("
            SELECT p.project_name,
                COALESCE((SELECT SUM(ce.total_amount) FROM company_expenses ce WHERE ce.project_id = p.id AND ce.expense_date BETWEEN '$date_from' AND '$date_to' AND ce.status NOT IN ('cancelled','draft') $projWhereCe), 0) AS ce_total,
                COALESCE((SELECT SUM(eec.total_amount) FROM employee_expense_claims eec WHERE eec.project_id = p.id AND eec.expense_date BETWEEN '$date_from' AND '$date_to' AND eec.status NOT IN ('cancelled','draft') $projWhereEec), 0) AS eec_total
            FROM projects p
            WHERE p.status = 'active'
            HAVING ce_total > 0 OR eec_total > 0
            ORDER BY (ce_total + eec_total) DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            $total = (float)$r['ce_total'] + (float)$r['eec_total'];
            $reportRows[] = [
                sanitize($r['project_name']),
                formatMoney((float)$r['ce_total']),
                formatMoney((float)$r['eec_total']),
                formatMoney($total),
            ];
            $reportTotal += $total;
        }
        break;

    case 'monthly':
        $reportTitle = 'Monthly Expense Summary';
        $reportHeaders = ['Month', 'Company Expenses', 'Employee Claims', 'Total'];
        // Build the month list from the selected date range (in PHP), then query each month
        // with prepared statements. This respects the date filter and avoids the fixed
        // 6-month UNION that ignored the range and mislabeled the first month.
        $monthStmt = $db->prepare("
            SELECT COALESCE(SUM(ce.total_amount), 0) AS ce_total
            FROM company_expenses ce
            WHERE ce.expense_date BETWEEN ? AND ? AND ce.status NOT IN ('cancelled','draft')
              AND DATE_FORMAT(ce.expense_date, '%Y-%m') = ?
        ");
        $claimMonthStmt = $db->prepare("
            SELECT COALESCE(SUM(eec.total_amount), 0) AS eec_total
            FROM employee_expense_claims eec
            WHERE eec.expense_date BETWEEN ? AND ? AND eec.status NOT IN ('cancelled','draft')
              AND DATE_FORMAT(eec.expense_date, '%Y-%m') = ?
        ");
        $months = [];
        $startMonth = new DateTime($date_from);
        $endMonth = new DateTime($date_to);
        for ($d = new DateTime($startMonth->format('Y-m-01')); $d <= $endMonth; $d->modify('+1 month')) {
            $months[] = $d->format('Y-m');
        }
        // Cap at 24 months to bound the query count
        if (count($months) > 24) {
            $months = array_slice($months, count($months) - 24);
        }
        foreach ($months as $month) {
            $monthStmt->execute([$date_from, $date_to, $month]);
            $ceTotal = (float)$monthStmt->fetchColumn();
            $claimMonthStmt->execute([$date_from, $date_to, $month]);
            $eecTotal = (float)$claimMonthStmt->fetchColumn();
            $total = $ceTotal + $eecTotal;
            $reportRows[] = [
                date('F Y', strtotime($month . '-01')),
                formatMoney($ceTotal),
                formatMoney($eecTotal),
                formatMoney($total),
            ];
            $reportTotal += $total;
        }
        break;

    case 'yearly':
        $reportTitle = 'Yearly Expense Summary';
        $reportHeaders = ['Year', 'Company Expenses', 'Employee Claims', 'Total'];
        $years = $db->query("
            SELECT YEAR(ce.expense_date) AS yr, COALESCE(SUM(ce.total_amount), 0) AS ce_total
            FROM company_expenses ce
            WHERE ce.expense_date BETWEEN '$date_from' AND '$date_to' AND ce.status NOT IN ('cancelled','draft')
            GROUP BY YEAR(ce.expense_date)
            ORDER BY yr DESC
        ")->fetchAll();
        $claimYears = $db->query("
            SELECT YEAR(eec.expense_date) AS yr, COALESCE(SUM(eec.total_amount), 0) AS eec_total
            FROM employee_expense_claims eec
            WHERE eec.expense_date BETWEEN '$date_from' AND '$date_to' AND eec.status NOT IN ('cancelled','draft')
            GROUP BY YEAR(eec.expense_date)
            ORDER BY yr DESC
        ")->fetchAll();
        // Merge
        $yearMap = [];
        foreach ($years as $y) { $yearMap[$y['yr']] = ['ce' => (float)$y['ce_total'], 'eec' => 0]; }
        foreach ($claimYears as $cy) {
            if (!isset($yearMap[$cy['yr']])) { $yearMap[$cy['yr']] = ['ce' => 0, 'eec' => 0]; }
            $yearMap[$cy['yr']]['eec'] = (float)$cy['eec_total'];
        }
        krsort($yearMap);
        foreach ($yearMap as $yr => $vals) {
            $total = $vals['ce'] + $vals['eec'];
            $reportRows[] = [(string)$yr, formatMoney($vals['ce']), formatMoney($vals['eec']), formatMoney($total)];
            $reportTotal += $total;
        }
        break;
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense Reports</h1>
        <p class="text-gray-600">Generate and view expense reports</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition"><i class="fas fa-chart-pie mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Report Controls -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
            <select name="report_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="category" <?= $report_type === 'category' ? 'selected' : '' ?>>Category-wise</option>
                <option value="employee" <?= $report_type === 'employee' ? 'selected' : '' ?>>Employee-wise</option>
                <option value="department" <?= $report_type === 'department' ? 'selected' : '' ?>>Department-wise</option>
                <option value="project" <?= $report_type === 'project' ? 'selected' : '' ?>>Project-wise</option>
                <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="yearly" <?= $report_type === 'yearly' ? 'selected' : '' ?>>Yearly</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" id="dateFrom" value="<?= sanitize($date_from) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" id="dateTo" value="<?= sanitize($date_to) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <?php if ($report_type === 'employee'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $employee_id == $e['id'] ? 'selected' : '' ?>><?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($report_type === 'department'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select name="department_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $department_id == $d['id'] ? 'selected' : '' ?>><?= sanitize($d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($report_type === 'project'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
            <select name="project_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<!-- Report Results -->
<div class="bg-white rounded-lg shadow">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800"><?= sanitize($reportTitle) ?></h3>
        <span class="text-sm text-gray-500"><?= date('M d, Y', strtotime($date_from)) ?> - <?= date('M d, Y', strtotime($date_to)) ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach ($reportHeaders as $h): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= sanitize($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($reportRows as $row): ?>
                <tr class="hover:bg-gray-50">
                    <?php foreach ($row as $cell): ?>
                    <td class="px-4 py-3 text-sm <?= is_numeric(str_replace(['$','₹','€'], '', $cell)) ? 'text-right font-mono' : 'text-gray-900' ?>"><?= $cell ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reportRows)): ?>
                <tr><td colspan="<?= count($reportHeaders) ?>" class="px-4 py-8 text-center text-gray-500">No data found for the selected criteria</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($reportRows)): ?>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="<?= count($reportHeaders) - 1 ?>" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Total:</td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-bold"><?= formatMoney($reportTotal) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');

    function updateToDateMin() {

        if (!dateFrom.value) {
            dateTo.removeAttribute('min');
            return;
        }

        const fromDate = new Date(dateFrom.value + 'T00:00:00');

        // From Date + 1 day
        fromDate.setDate(fromDate.getDate() + 1);

        const year = fromDate.getFullYear();
        const month = String(fromDate.getMonth() + 1).padStart(2, '0');
        const day = String(fromDate.getDate()).padStart(2, '0');

        const minToDate = `${year}-${month}-${day}`;

        // Set minimum To Date
        dateTo.setAttribute('min', minToDate);

        // If existing To Date is before minimum date,
        // automatically clear it.
        if (dateTo.value && dateTo.value < minToDate) {
            dateTo.value = '';
        }
    }

    // Initial page load
    updateToDateMin();

    // When From Date changes
    dateFrom.addEventListener('change', function () {
        updateToDateMin();
    });

});
</script>
<?php require_once '../../../includes/footer.php'; ?>
