<?php
/**
 * F12 — Year-End Close (STEP 24 workflow screen)
 * Validate → Approve → Close Year → Carry Forward. Idempotent.
 */
$pageTitle = 'Year-End Close';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';
require_once __DIR__ . '/../YearEndClosingService.php';

if (!hasPermission('year.close')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_year') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to('modules/finance/period_close/closing/index.php'); }
    $fyId = (int)($_POST['fy_id'] ?? 0);
    $confirm = trim($_POST['confirmation'] ?? '');
    if ($confirm !== 'CLOSE YEAR') {
        flash('error', 'Type CLOSE YEAR exactly to confirm.');
        to('modules/finance/period_close/closing/index.php?fy_id=' . $fyId);
    }
    $res = YearEndClosingService::closeYear($db, $fyId, $userId);
    if (!$res['success']) {
        flash('error', $res['message']);
        if (!empty($res['validation']['checks'])) {
            foreach ($res['validation']['checks'] as $c) if ($c['status']==='fail') flash('error', '✗ ' . $c['name'] . ': ' . $c['detail']);
        }
        to('modules/finance/period_close/closing/index.php?fy_id=' . $fyId);
    }
    flash('success', $res['message']);
    if (!empty($res['carry_forward'])) {
        flash($res['carry_forward']['verification_passed'] ? 'success' : 'error',
            $res['carry_forward']['verification_passed']
                ? 'Opening balances carried forward to ' . $res['carry_forward']['next_fy_name'] . ' and verified.'
                : 'WARNING: opening balance verification FAILED — check the Opening Balances report.');
    }
    to('modules/finance/period_close/opening_balances/index.php');
}

$fys = $db->query("
    SELECT f.*, yec.status yec_status, yec.closed_at, yec.net_profit
    FROM financial_years f LEFT JOIN year_end_closings yec ON yec.financial_year_id = f.id
    ORDER BY f.start_date DESC
")->fetchAll();

$selected = null;
foreach ($fys as $f) if ($f['id'] == ($_GET['fy_id'] ?? -1)) $selected = $f;
if (!$selected) foreach ($fys as $f) if ($f['status'] === 'closing') { $selected = $f; break; }

$validation = null; $preview = null;
if ($selected && in_array($selected['status'], ['active','closing']) && !$selected['yec_status']) {
    $validation = YearEndClosingService::validateYearEnd($db, (int)$selected['id'], $userId);
    // Preview of what will be closed
    $balances = YearEndClosingService::fyAccountBalances($db, (int)$selected['id']);
    $temp = YearEndClosingService::classifyTemporary($balances);
    $netProfit = YearEndClosingService::computeNetProfit($temp);
    $settings = PeriodCloseService::getSettings($db);
    $preview = ['temp'=>$temp, 'net_profit'=>$netProfit, 'settings'=>$settings];
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Year-End Close</h1>
        <p class="text-gray-600">Validate → Checklist → Adjustments → Final TB/P&L/BS → Approval → Close → Carry Forward</p>
    </div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="get" class="mb-4">
    <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <?php foreach ($fys as $f): ?>
        <option value="<?= $f['id'] ?>" <?= $selected && $selected['id']==$f['id']?'selected':'' ?>>
            <?= sanitize($f['year_name']) ?> (<?= strtoupper($f['status']) ?><?= $f['yec_status'] ? ' · '.strtoupper($f['yec_status']) : '' ?>)
        </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$selected): ?>
<p class="text-center text-gray-400 py-10">No financial year selected.</p>
<?php elseif ($selected['yec_status'] === 'completed'): ?>
<!-- Already closed -->
<div class="bg-white rounded-lg shadow p-8 text-center max-w-xl mx-auto">
    <i class="fas fa-circle-check text-green-500 text-5xl mb-3"></i>
    <h3 class="font-bold text-lg"><?= sanitize($selected['year_name']) ?> is CLOSED</h3>
    <p class="text-gray-500 text-sm mt-1">Net P/L: <span class="font-mono <?= $selected['net_profit']>=0?'text-green-600':'text-red-600' ?>"><?= formatMoney($selected['net_profit']) ?></span> · Closed <?= date('d M Y H:i', strtotime($selected['closed_at'])) ?></p>
    <p class="text-xs text-gray-400 mt-2"><i class="fas fa-shield-halved mr-1"></i>Idempotent guard active — this year cannot be closed twice.</p>
    <div class="flex gap-2 justify-center mt-4">
        <a href="../reports/year_end.php?fy_id=<?= $selected['id'] ?>" class="border px-4 py-2 rounded text-sm hover:bg-gray-50">Year-End Report</a>
        <?php if (hasPermission('year.reopen') && !in_array($selected['status'],['archived'])): ?>
        <a href="../financial_years/index.php" class="bg-orange-600 text-white px-4 py-2 rounded text-sm hover:bg-orange-700">Reopen Year (audited)</a>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($validation): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Validation -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Validation — <?= sanitize($selected['year_name']) ?></h3>
            <span class="px-3 py-1 rounded text-sm font-semibold <?= $validation['passed']?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= $validation['passed']?'ALL CHECKS PASSED':'CRITICAL FAILURES' ?></span>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y">
            <?php foreach ($validation['checks'] as $c): ?>
                <tr>
                    <td class="px-4 py-2 w-10 text-center">
                        <?php if ($c['status']==='pass'): ?><i class="fas fa-circle-check text-green-500"></i>
                        <?php else: ?><i class="fas fa-circle-xmark text-red-500"></i><?php endif; ?>
                    </td>
                    <td class="px-4 py-2 font-medium w-80"><?= sanitize($c['name']) ?></td>
                    <td class="px-4 py-2 text-gray-600"><?= sanitize($c['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Preview + action -->
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm uppercase text-gray-500">What Will Happen</h3>
            <ul class="text-sm space-y-2 text-gray-600">
                <li><i class="fas fa-1 text-blue-500 mr-2"></i>Closing journal posted via Posting Engine (voucher dated <?= date('d M Y', strtotime($selected['end_date'])) ?>)</li>
                <li><i class="fas fa-2 text-blue-500 mr-2"></i><?= count(array_filter($preview['temp'], fn($t) => abs($t['credit']-$t['debit']) >= 0.005)) ?> temporary account(s) closed to zero</li>
                <li><i class="fas fa-3 text-blue-500 mr-2"></i>Net <b class="<?= $preview['net_profit']>=0?'text-green-600':'text-red-600' ?>">P/L <?= formatMoney(abs($preview['net_profit'])) ?></b> → Retained Earnings</li>
                <li><i class="fas fa-4 text-blue-500 mr-2"></i>Permanent balances carried to the next FY (auto-created if needed)</li>
                <li><i class="fas fa-5 text-blue-500 mr-2"></i>Opening balances verified &amp; audited</li>
            </ul>
        </div>

        <?php if ($validation['passed']): ?>
        <div class="bg-white rounded-lg shadow p-4 border-2 border-red-200">
            <p class="text-xs text-red-600 font-semibold mb-2"><i class="fas fa-triangle-exclamation mr-1"></i>IRREVERSIBLE WITHOUT AUDITED REOPEN</p>
            <form method="post" onsubmit="return confirmFinal(this);">
                <?= csrfToken() ?>
                <input type="hidden" name="action" value="close_year"><input type="hidden" name="fy_id" value="<?= $selected['id'] ?>">
                <input name="confirmation" id="confirmInput" placeholder='Type "CLOSE YEAR"' class="w-full border rounded px-3 py-2 text-sm mb-2" autocomplete="off" required>
                <button id="finalBtn" class="w-full bg-red-600 text-white py-2.5 rounded hover:bg-red-700 text-sm font-semibold disabled:opacity-40" disabled>Execute Year-End Closing</button>
            </form>
            <script>
            function confirmFinal(f) {
                return f.confirmation.value.trim() === 'CLOSE YEAR';
            }
            document.getElementById('confirmInput').addEventListener('input', function(){
                document.getElementById('finalBtn').disabled = this.value.trim() !== 'CLOSE YEAR';
            });
            </script>
        </div>
        <?php else: ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
            <p class="font-semibold mb-1"><i class="fas fa-ban mr-1"></i>Closing blocked</p>
            <p>Fix every failed check above. Nothing has been closed — no partial year-end is possible.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow p-8 text-center max-w-xl mx-auto text-gray-500 text-sm">
    This year's state (<b><?= strtoupper($selected['status']) ?></b>) doesn't allow a fresh closing run.<br>
    Years must be ACTIVE or CLOSING with no prior closing record.
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
