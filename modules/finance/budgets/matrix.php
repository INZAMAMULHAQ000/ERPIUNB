<?php
$pageTitle = 'Monthly Allocation Matrix';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$budget = budget_find($db, $id);
if (!$budget) { flash('error', 'Budget not found.', 'error'); redirect('index.php'); }
if (!in_array($budget['status'], ['draft', 'rejected'])) {
    flash('error', 'Budget is locked (' . str_replace('_',' ',$budget['status']) . ').', 'error');
    redirect('view.php?id=' . $id);
}
$version = budget_active_version($db, $id);
if (!$version) { flash('error', 'Budget has no version.', 'error'); redirect('view.php?id=' . $id); }

// Distinct dimension combos within the current version
$st = $db->prepare("SELECT bl.department_id, bl.project_id, bl.chart_of_accounts_id,
                           acct.account_code, acct.account_name, d.department_name, p.project_name
                    FROM budget_lines bl
                    JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                    LEFT JOIN departments d ON d.id = bl.department_id
                    LEFT JOIN projects p ON p.id = bl.project_id
                    WHERE bl.budget_version_id = ?
                    GROUP BY bl.department_id, bl.project_id, bl.chart_of_accounts_id
                    ORDER BY acct.account_code");
$st->execute([$version['id']]);
$combos = $st->fetchAll();

// Existing monthly lines for pre-filling
$st = $db->prepare("SELECT department_id, project_id, chart_of_accounts_id, month, budget_amount
                    FROM budget_lines WHERE budget_version_id = ? AND month IS NOT NULL");
$st->execute([$version['id']]);
$monthLines = [];
foreach ($st->fetchAll() as $ml) {
    $k = (($ml['department_id'] ?? 0)) . '_' . (($ml['project_id'] ?? 0)) . '_' . $ml['chart_of_accounts_id'];
    $monthLines[$k][(int)$ml['month']] = (float)$ml['budget_amount'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('matrix.php?id=' . $id); }
    $cells = $_POST['cells'] ?? [];
    $dims  = $_POST['dim'] ?? [];

    try {
        $db->beginTransaction();
        $upsert = $db->prepare("INSERT INTO budget_lines (budget_id, budget_version_id, department_id, project_id, chart_of_accounts_id, month, budget_amount, notes)
                                VALUES (?,?,?,?,?,?,?, '')
                                ON DUPLICATE KEY UPDATE budget_amount = VALUES(budget_amount)");
        $find = $db->prepare("SELECT id FROM budget_lines WHERE budget_version_id = ? AND chart_of_accounts_id = ?
                              AND department_id <=> ? AND project_id <=> ? AND month <=> ?");

        foreach ($cells as $key => $monthMap) {
            $d = $dims[$key] ?? [];
            $dept = ((int)($d['department_id'] ?? 0)) ?: null;
            $proj = ((int)($d['project_id'] ?? 0)) ?: null;
            $acct = (int)($d['chart_of_accounts_id'] ?? 0);
            if (!$acct) continue;

            $hasMonthly = false;
            foreach ($monthMap as $m => $amount) {
                $m = (int)$m;
                if ($m < 1 || $m > 12) continue;
                $v = round((float)$amount, 2);

                $find->execute([$version['id'], $acct, $dept, $proj, $m]);
                $existing = $find->fetchColumn();

                if ($v > 0) {
                    $upsert->execute([$id, $version['id'], $dept, $proj, $acct, $m, $v]);
                    $hasMonthly = true;
                } elseif ($existing) {
                    $db->prepare("DELETE FROM budget_lines WHERE id = ?")->execute([$existing]);
                }
            }
            // Monthly allocation supersedes an annual line for the same combo
            if ($hasMonthly) {
                $find->execute([$version['id'], $acct, $dept, $proj, null]);
                $annual = $find->fetchColumn();
                if ($annual) { $db->prepare("DELETE FROM budget_lines WHERE id = ?")->execute([$annual]); }
            }
        }
        recompute_version_total($db, $version['id']);
        $db->commit();
        logAudit($_SESSION['user_id'], 'Updated monthly allocations for budget: ' . $budget['budget_code'], 'budget');
        flash('success', 'Monthly allocation matrix saved.', 'success');
        redirect('matrix.php?id=' . $id);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Matrix save error: ' . $e->getMessage());
        flash('error', 'Could not save matrix: ' . $e->getMessage(), 'error');
        redirect('matrix.php?id=' . $id);
    }
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Monthly Allocation Matrix</h1>
        <p class="text-gray-600"><?= sanitize($budget['budget_code']) ?> — <?= sanitize($budget['budget_name']) ?> (<?= sanitize($budget['year_name']) ?>, <?= ucwords(str_replace('_',' ',$budget['budget_type'])) ?>)</p>
    </div>
    <div class="flex gap-2">
        <a href="view.php?id=<?= $id ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-eye mr-2"></i>View Budget</a>
        <a href="edit.php?id=<?= $id ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-edit mr-2"></i>Edit Lines</a>
    </div>
</div>

<div class="bg-blue-50 border-l-4 border-blue-500 text-blue-800 px-4 py-3 rounded mb-6 text-sm">
    <i class="fas fa-info-circle mr-2"></i>Months run July → June matching the financial year. A non-zero monthly allocation replaces any annual (whole-year) line for the same department/project/account. Leave a cell empty or 0 to remove that month's line.
</div>

<?php if (empty($combos)): ?>
<div class="bg-white rounded-lg shadow p-10 text-center text-gray-500">
    <i class="fas fa-th text-4xl mb-3 text-gray-300"></i>
    <p>No budget lines yet. <a href="edit.php?id=<?= $id ?>" class="text-blue-600 underline">Add lines first</a>.</p>
</div>
<?php else: ?>
<form method="POST" action="matrix.php?id=<?= $id ?>">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dimension</th>
                        <?php for ($m=1;$m<=12;$m++): ?>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase min-w-[90px]"><?= substr(budget_month_name($m), 0, 3) ?></th>
                        <?php endfor; ?>
                        <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($combos as $i => $c): $key = (($c['department_id'] ?? 0)) . '_' . (($c['project_id'] ?? 0)) . '_' . $c['chart_of_accounts_id']; ?>
                    <tr class="hover:bg-gray-50 combo-row">
                        <td class="px-3 py-2">
                            <input type="hidden" name="dim[<?= $key ?>][department_id]" value="<?= (int)($c['department_id'] ?? 0) ?>">
                            <input type="hidden" name="dim[<?= $key ?>][project_id]" value="<?= (int)($c['project_id'] ?? 0) ?>">
                            <input type="hidden" name="dim[<?= $key ?>][chart_of_accounts_id]" value="<?= (int)$c['chart_of_accounts_id'] ?>">
                            <span class="text-sm font-medium text-gray-800"><?= sanitize($c['account_code']) ?></span>
                            <div class="text-xs text-gray-500"><?= sanitize($c['account_name']) ?><?= $c['department_name'] ? ' · ' . sanitize($c['department_name']) : '' ?><?= $c['project_name'] ? ' · ' . sanitize($c['project_name']) : '' ?></div>
                        </td>
                        <?php for ($m=1;$m<=12;$m++): $val = $monthLines[$key][$m] ?? 0; ?>
                        <td class="px-2 py-2">
                            <input type="number" name="cells[<?= $key ?>][<?= $m ?>]" step="0.01" min="0" value="<?= $val > 0 ? number_format($val,2,'.','') : '' ?>" placeholder="—" class="cell w-full px-1 py-1 border rounded text-right text-xs font-mono focus:ring-1 focus:ring-blue-400">
                        </td>
                        <?php endfor; ?>
                        <td class="px-2 py-2 text-right font-mono text-sm text-gray-900 row-total">0.00</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-100 font-semibold">
                    <tr>
                        <td class="px-3 py-2 text-sm text-gray-600">Totals</td>
                        <?php for ($m=1;$m<=12;$m++): ?>
                        <td class="px-2 py-2 text-right font-mono text-sm text-gray-900 col-total-<?= $m ?>">0.00</td>
                        <?php endfor; ?>
                        <td class="px-2 py-2 text-right font-mono text-sm text-gray-900" id="grandMatrixTotal">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="flex justify-end gap-4 mt-4 mb-8">
        <a href="view.php?id=<?= $id ?>" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">Cancel</a>
        <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition"><i class="fas fa-save mr-2"></i>Save Matrix</button>
    </div>
</form>

<script>
function matrixCalc() {
    const rows = document.querySelectorAll('.combo-row');
    const colTot = {};
    let grand = 0;
    rows.forEach(row => {
        let rt = 0;
        row.querySelectorAll('.cell').forEach((inp, idx) => {
            const v = parseFloat(inp.value) || 0;
            rt += v;
            colTot[idx + 1] = (colTot[idx + 1] || 0) + v;
        });
        row.querySelector('.row-total').textContent = rt.toLocaleString('en-US',{minimumFractionDigits:2});
        grand += rt;
    });
    for (let m = 1; m <= 12; m++) {
        const el = document.querySelector('.col-total-' + m);
        if (el) el.textContent = (colTot[m] || 0).toLocaleString('en-US',{minimumFractionDigits:2});
    }
    document.getElementById('grandMatrixTotal').textContent = grand.toLocaleString('en-US',{minimumFractionDigits:2});
}
document.addEventListener('input', e => { if (e.target.classList.contains('cell')) matrixCalc(); });
matrixCalc();
</script>
<?php endif; ?>
<?php require_once '../../../includes/footer.php'; ?>