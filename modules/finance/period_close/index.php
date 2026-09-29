<?php
/**
 * F12 — Period & Year-End Close Dashboard
 */
$pageTitle = 'Period & Year-End Close';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/finance_helpers.php';
require_once __DIR__ . '/PeriodCloseService.php';
require_once __DIR__ . '/YearEndClosingService.php';

if (!hasPermission('period.view')) { die('Access Denied'); }

$db = getDB();
$settings = PeriodCloseService::getSettings($db);
$activeFY = getActiveFinancialYear($db);
$fyId = $activeFY ? (int)$activeFY['id'] : 0;

// Current period
$currentPeriod = $fyId ? PeriodCloseService::findPeriodForDate($db, $fyId, date('Y-m-d')) : null;

// Period stats
$periodStats = ['total' => 0, 'closed' => 0];
if ($fyId) {
    $s = $db->prepare("SELECT COUNT(*) total, SUM(status='closed') closed FROM accounting_periods WHERE financial_year_id = ?");
    $s->execute([$fyId]);
    $periodStats = $s->fetch() ?: $periodStats;
}
$periodsRemaining = max(0, (int)$periodStats['total'] - (int)$periodStats['closed']);

// Checklist progress (year level)
if ($fyId) PeriodCloseService::ensureChecklistItems($db, $fyId, 0, 'year');
list($chkTotal, $chkDone, $chkExc) = $fyId ? PeriodCloseService::checklistProgress($db, $fyId, 0) : [0, 0, 0];
$chkPct = $chkTotal ? round($chkDone / $chkTotal * 100) : 0;

// Pending adjustments
$pendingAdj = 0;
if ($fyId) {
    $s = $db->prepare("SELECT COUNT(*) FROM closing_adjustments WHERE financial_year_id = ? AND status IN ('draft','pending_approval')");
    $s->execute([$fyId]);
    $pendingAdj = (int)$s->fetchColumn();
}

// Net profit YTD
$netProfit = 0;
if ($fyId) {
    $balances = YearEndClosingService::fyAccountBalances($db, $fyId);
    $netProfit = YearEndClosingService::computeNetProfit(YearEndClosingService::classifyTemporary($balances));
}

// Closing status
$closingStatus = 'Open';
$closingStatusColor = 'bg-green-100 text-green-800';
if ($fyId) {
    if (in_array($activeFY['status'], ['closing', 'closed'], true)) {
        $closingStatus = ucfirst($activeFY['status']);
        $closingStatusColor = $activeFY['status'] === 'closing' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-200 text-gray-700';
    }
}

// Unreconciled modules = year checklist items not completed
$unreconciled = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT ci.item_text, ci.status FROM period_checklist_items ci
        JOIN period_checklist_templates ct ON ct.id = ci.template_id
        WHERE ci.financial_year_id = ? AND ci.period_id = 0 AND ct.module_area IN ('banking','ar','ap','tax','payroll','fixed_assets','inventory')
          AND ci.status <> 'completed'
        ORDER BY ct.sort_order
    ");
    $s->execute([$fyId]);
    $unreconciled = $s->fetchAll();
}

// RE account label
$reName = 'Not configured';
if ($settings['retained_earnings_account_id']) {
    $s = $db->prepare("SELECT CONCAT(account_code,' — ',account_name) FROM chart_of_accounts WHERE id = ?");
    $s->execute([$settings['retained_earnings_account_id']]);
    $reName = $s->fetchColumn() ?: 'Unknown';
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Period &amp; Year-End Close</h1>
        <p class="text-gray-600">Accounting period control and financial year closing</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="financial_years/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-calendar-alt mr-1"></i> Financial Years</a>
        <a href="closing/index.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-flag-checkered mr-2"></i>Year-End Close</a>
    </div>
</div>

<!-- KPI row -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Current FY</p>
        <p class="font-bold text-gray-800"><?= $activeFY ? sanitize($activeFY['year_name']) : 'None' ?></p>
        <?php if ($activeFY): ?><p class="text-xs text-gray-400"><?= date('M d, Y', strtotime($activeFY['start_date'])) ?> – <?= date('M d, Y', strtotime($activeFY['end_date'])) ?></p><?php endif; ?>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-cyan-500">
        <p class="text-xs text-gray-500 uppercase">Current Period</p>
        <p class="font-bold text-gray-800"><?= $currentPeriod ? sanitize($currentPeriod['period_name']) : '—' ?></p>
        <p class="text-xs <?= $currentPeriod && $currentPeriod['status'] === 'open' ? 'text-green-600' : 'text-red-500' ?>">
            <?= $currentPeriod ? strtoupper($currentPeriod['status']) : '' ?>
        </p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <p class="text-xs text-gray-500 uppercase">Periods Closed / Remaining</p>
        <p class="font-bold text-gray-800"><?= (int)$periodStats['closed'] ?> / <?= $periodsRemaining ?></p>
        <p class="text-xs text-gray-400">of <?= (int)$periodStats['total'] ?> periods</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Checklist Progress</p>
        <p class="font-bold text-gray-800"><?= $chkPct ?>%</p>
        <div class="w-full bg-gray-200 rounded h-2 mt-1"><div class="bg-purple-500 h-2 rounded" style="width:<?= $chkPct ?>%"></div></div>
        <p class="text-xs <?= $chkExc ? 'text-red-500' : 'text-gray-400' ?>"><?= $chkDone ?>/<?= $chkTotal ?> done<?= $chkExc ? ", {$chkExc} exception(s)" : '' ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
        <p class="text-xs text-gray-500 uppercase">Pending Adjustments</p>
        <p class="font-bold <?= $pendingAdj ? 'text-orange-600' : 'text-gray-800' ?>"><?= $pendingAdj ?></p>
        <a class="text-xs text-blue-600 hover:underline" href="adjustments/index.php">Manage →</a>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $netProfit >= 0 ? 'border-emerald-500' : 'border-red-500' ?>">
        <p class="text-xs text-gray-500 uppercase">Net <?= $netProfit >= 0 ? 'Profit' : 'Loss' ?> (YTD)</p>
        <p class="font-bold <?= $netProfit >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= formatMoney(abs($netProfit)) ?></p>
        <p class="text-xs text-gray-400">to be closed to RE</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Closing status -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Closing Status</h3></div>
        <div class="p-4 space-y-3 text-sm">
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Financial year state</span>
                <span class="px-2 py-1 rounded text-xs font-semibold <?= $closingStatusColor ?>"><?= sanitize($closingStatus) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Retained earnings a/c</span>
                <span class="<?= $settings['retained_earnings_account_id'] ? 'text-gray-800' : 'text-red-500' ?> font-medium text-right"><?= sanitize($reName) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Closing method</span>
                <span class="font-medium"><?= $settings['closing_method'] === 'via_income_summary' ? 'Via Income Summary' : 'Direct to Retained Earnings' ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Year reopening allowed</span>
                <span class="font-medium"><?= !empty($settings['allow_year_reopen']) ? 'Yes' : 'No' ?></span>
            </div>
            <a href="#" id="settingsLink" class="inline-block text-blue-600 hover:underline text-sm"
               onclick="return false;" data-modal-open="settingsModal"><i class="fas fa-gear mr-1"></i>Closing Settings</a>
        </div>
    </div>

    <!-- Unreconciled -->
    <div class="bg-white rounded-lg shadow lg:col-span-2">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Unreconciled Modules</h3>
            <a href="checklist/index.php" class="text-sm text-blue-600 hover:underline">Checklist →</a>
        </div>
        <div class="p-4">
            <?php if (empty($unreconciled)): ?>
                <p class="text-sm text-green-600"><i class="fas fa-circle-check mr-1"></i>All reconciliation checkpoints completed.</p>
            <?php else: ?>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($unreconciled as $u): ?>
                    <li class="flex justify-between items-center bg-gray-50 rounded px-3 py-2">
                        <span class="text-gray-700"><?= sanitize($u['item_text']) ?></span>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $u['status']==='exception' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= strtoupper(str_replace('_',' ',$u['status'])) ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $actions = [
        ['periods/index.php', 'fa-lock', 'Close / Reopen Periods', 'period.view'],
        ['reconciliation/index.php', 'fa-scale-balanced', 'Run Validation', 'period.view'],
        ['adjustments/create.php', 'fa-pen-to-square', 'New Adjustment', 'closing.adjust'],
        ['reports/index.php', 'fa-file-invoice', 'Closing Reports', 'closing.report'],
    ];
    foreach ($actions as $a): if (!hasPermission($a[3])) continue; ?>
    <a href="<?= $a[0] ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <span class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fas <?= $a[1] ?>"></i></span>
        <span class="font-medium text-gray-800"><?= $a[2] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Settings modal -->
<?php if (hasPermission('year.close')): ?>
<div id="settingsModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-40 flex items-start justify-center pt-20">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold">Closing Settings</h3>
            <button data-modal-close class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="post" action="settings_save.php" class="p-4 space-y-4">
            <?= csrfToken() ?>
            <?php
            $accounts = $db->query("
                SELECT coa.id, coa.account_code, coa.account_name, ag.nature
                FROM chart_of_accounts coa JOIN account_groups ag ON ag.id = coa.group_id
                WHERE coa.status='active' AND ag.nature IN ('equity','income','expense')
                ORDER BY ag.nature, coa.account_code
            ")->fetchAll();
            ?>
            <div>
                <label class="block text-sm font-medium mb-1">Retained Earnings Account <span class="text-red-600">*</span></labe>
                <select name="retained_earnings_account_id" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— select equity account —</option>
                    <?php foreach ($accounts as $a): if ($a['nature'] !== 'equity') continue; ?>
                    <option value="<?= $a['id'] ?>" <?= $settings['retained_earnings_account_id']==$a['id']?'selected':'' ?>><?= sanitize($a['account_code'].' — '.$a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Closing Method</label>
                <select name="closing_method" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="direct_to_retained" <?= $settings['closing_method']==='direct_to_retained'?'selected':'' ?>>Direct to Retained Earnings</option>
                    <option value="via_income_summary" <?= $settings['closing_method']==='via_income_summary'?'selected':'' ?>>Via Income Summary Account</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Income Summary Account (if used)</label>
                <select name="income_summary_account_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— none —</option>
                    <?php foreach ($accounts as $a): if ($a['nature'] !== 'expense') continue; ?>
                    <option value="<?= $a['id'] ?>" <?= $settings['income_summary_account_id']==$a['id']?'selected':'' ?>><?= sanitize($a['account_code'].' — '.$a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_checklist_before_period_close" <?= !empty($settings['require_checklist_before_period_close'])?'checked':'' ?>> Require checklist before period close</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_checklist_before_year_close" <?= !empty($settings['require_checklist_before_year_close'])?'checked':'' ?>> Require checklist before year-end close</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_year_reopen" <?= !empty($settings['allow_year_reopen'])?'checked':'' ?>> Allow authorized year reopening</label>
            <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 text-sm">Save Settings</button>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('[data-modal-open]').forEach(function(el){
    el.addEventListener('click', function(e){
        e.preventDefault();
        document.getElementById(el.getAttribute('data-modal-open')).classList.remove('hidden');
    });
});
document.querySelectorAll('[data-modal-close]').forEach(function(el){
    el.addEventListener('click', function(){ el.closest('.fixed').classList.add('hidden'); });
});
</script>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
