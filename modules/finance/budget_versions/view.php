<?php
$pageTitle = 'Version Detail';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error', 'Version not found.', 'error'); redirect('index.php'); }

$version = $db->prepare("SELECT v.*, b.budget_code, b.budget_name, b.budget_type, b.status AS budget_status,
                                fy.id AS financial_year_id, fy.year_name, fy.start_date, fy.end_date
                         FROM budget_versions v
                         JOIN budgets b ON b.id = v.budget_id
                         JOIN financial_years fy ON fy.id = b.financial_year_id
                         WHERE v.id = ?");
$version->execute([$id]);
$version = $version->fetch();
if (!$version) { flash('error', 'Version not found.', 'error'); redirect('index.php'); }

// Budget's own fiscal year drives monthly actual alignment.
$fy = [
    'id'         => $version['financial_year_id'],
    'start_date' => $version['start_date'],
    'end_date'   => $version['end_date'],
];
$lines  = budget_lines_for_version($db, $id, $fy);
$totals = budget_lines_totals($lines);

$prev = $db->prepare("SELECT id, version_label, total_amount, version_no FROM budget_versions
                      WHERE budget_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
$prev->execute([$version['budget_id'], $id]);
$prev = $prev->fetch();

$createdBy = $db->prepare("SELECT name FROM users WHERE id = ?");
$createdBy->execute([$version['created_by']]);
$createdBy = $createdBy->fetch();
$creatorName = $createdBy ? $createdBy['name'] : '—';
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($version['version_label']) ?></h1>
        <p class="text-gray-600"><?= sanitize($version['budget_code']) ?> — <?= sanitize($version['budget_name']) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php?budget_id=<?= (int)$version['budget_id'] ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Versions</a>
        <a href="../budgets/view.php?id=<?= (int)$version['budget_id'] ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-eye mr-2"></i>Budget</a>
    </div>
</div>

<!-- Header / status -->
<div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-6 items-center">
    <div>
        <div class="text-xs text-gray-500 uppercase">Version</div>
        <div class="text-lg font-semibold text-gray-900"><?= sanitize($version['version_label']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Type</div>
        <div class="text-sm text-gray-700 capitalize"><?= sanitize($version['version_type']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Status</div>
        <span class="px-2 py-1 text-xs rounded font-medium <?= version_status_badge($version['status']) ?>"><?= ucfirst($version['status']) ?></span>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Fiscal Year</div>
        <div class="text-sm text-gray-700"><?= sanitize($version['year_name']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Total</div>
        <div class="text-lg font-mono font-semibold text-gray-900"><?= formatMoney((float)$version['total_amount']) ?></div>
    </div>
    <?php if ($prev): ?>
    <div>
        <div class="text-xs text-gray-500 uppercase">vs <?= sanitize($prev['version_label']) ?></div>
        <div class="text-sm font-mono <?= (float)$version['total_amount'] >= (float)$prev['total_amount'] ? 'text-green-600' : 'text-red-600' ?>">
            <?= (float)$version['total_amount'] >= (float)$prev['total_amount'] ? '+' : '' ?><?= formatMoney((float)$version['total_amount'] - (float)$prev['total_amount']) ?>
        </div>
    </div>
    <?php endif; ?>
    <div>
        <div class="text-xs text-gray-500 uppercase">Created by</div>
        <div class="text-sm text-gray-700"><?= sanitize($creatorName) ?></div>
    </div>
    <?php if ($version['status'] === 'draft' && $version['budget_status'] === 'active' && hasPermission('budget.approve')): ?>
    <form method="POST" action="activate.php" class="ml-auto">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="version_id" value="<?= (int)$version['id'] ?>">
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check-circle mr-2"></i>Activate Version</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($version['notes'])): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded mb-6">
    <span class="font-medium"><i class="fas fa-sticky-note mr-2"></i>Notes:</span>
    <?= sanitize($version['notes']) ?>
</div>
<?php endif; ?>

<!-- KPI cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Allocated</div>
        <div class="text-xl font-bold font-mono text-gray-900"><?= formatMoney($totals['allocated']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Spent (GL actual)</div>
        <div class="text-xl font-bold font-mono text-gray-900"><?= formatMoney($totals['spent']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Available</div>
        <div class="text-xl font-bold font-mono text-emerald-600"><?= formatMoney($totals['available']) ?></div>
    </div>
</div>

<!-- Version lines table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center">
        <h2 class="font-semibold text-gray-800">Version Lines (<?= count($lines) ?>)</h2>
        <?php if ($version['budget_status'] === 'active' && ($version['status'] === 'draft' || $version['status'] === 'active') && hasPermission('budget.edit')): ?>
        <a href="../budgets/matrix.php?id=<?= (int)$version['budget_id'] ?>" class="text-blue-600 hover:text-blue-900 text-sm"><i class="fas fa-th mr-1"></i>Allocation Matrix</a>
        <?php endif; ?>
    </div>
    <?php if (empty($lines)): ?>
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-table text-4xl mb-3 text-gray-300"></i>
        <p>This version has no lines yet. Use the Allocation Matrix to enter line items.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Spent</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Available</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($lines as $line): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($line['account_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($line['account_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($line['department_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($line['project_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize(budget_month_name($line['month'])) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$line['budget_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($line['spent']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-emerald-600"><?= formatMoney($line['available']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= budget_variance_badge($line['pct']) ?>" title="<?= budget_variance_label($line['pct']) ?>">
                            <?= $line['pct'] ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-right text-sm text-gray-700">Totals</td>
                    <td class="px-4 py-3 text-right font-mono text-gray-900"><?= formatMoney($totals['allocated']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-gray-900"><?= formatMoney($totals['spent']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-emerald-700"><?= formatMoney($totals['available']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= budget_variance_badge($totals['pct']) ?>"><?= $totals['pct'] ?>%</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>