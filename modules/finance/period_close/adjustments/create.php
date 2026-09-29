<?php
/**
 * F12 — New Closing Adjustment (creates a DRAFT journal; posted only after approval)
 */
$pageTitle = 'New Closing Adjustment';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('closing.adjust')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to('modules/finance/period_close/adjustments/create.php'); }

    $fyId   = (int)($_POST['financial_year_id'] ?? 0);
    $periodId = (int)($_POST['period_id'] ?? 0) ?: null;
    $type   = in_array($_POST['adjustment_type'] ?? '', ['accrual','prepayment','provision','correction','depreciation','tax','other']) ? $_POST['adjustment_type'] : 'other';
    $title  = trim($_POST['title'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $lines  = $_POST['lines'] ?? [];

    // Build & validate lines
    $clean = [];
    foreach ($lines as $l) {
        $acct = (int)($l['account_id'] ?? 0);
        $d = (float)($l['debit'] ?? 0);
        $c = (float)($l['credit'] ?? 0);
        if (!$acct || ($d <= 0 && $c <= 0)) continue;
        if ($d > 0 && $c > 0) continue;
        $clean[] = ['account_id'=>$acct, 'debit'=>round($d,2), 'credit'=>round($c,2)];
    }
    $td = round(array_sum(array_column($clean,'debit')),2);
    $tc = round(array_sum(array_column($clean,'credit')),2);

    if (!$fyId || !$title || !$reason || empty($clean)) {
        flash('error', 'Year, title, reason and at least one valid line are required.');
        to('modules/finance/period_close/adjustments/create.php?fy_id='.$fyId);
    }
    if (abs($td-$tc) > 0.001) {
        flash('error', "Journal not balanced: Dr {$td} ≠ Cr {$tc}.");
        to('modules/finance/period_close/adjustments/create.php?fy_id='.$fyId);
    }

    try {
        $db->beginTransaction();
        require_once __DIR__ . '/../../helpers/finance_helpers.php';
        $vtId = (int)$db->query("SELECT id FROM voucher_types WHERE voucher_name='Journal Voucher' LIMIT 1")->fetchColumn();
        $voucherNo = generateVoucherNo($vtId, $db);
        $insJ = $db->prepare("
            INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'draft', ?)
        ");
        $insJ->execute([$voucherNo, $vtId, date('Y-m-d'), 'ADJ', 'Closing adjustment: '.$title, $_SESSION['user_id']]);
        $jid = (int)$db->lastInsertId();

        $insL = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($clean as $l) $insL->execute([$jid, $l['account_id'], $l['debit'], $l['credit'], $title]);

        $insA = $db->prepare("
            INSERT INTO closing_adjustments (financial_year_id, period_id, adjustment_type, title, reason, journal_entry_id, amount, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        $insA->execute([$fyId, $periodId, $type, $title, $reason, $jid, max($td,$tc), $_SESSION['user_id']]);
        $aid = (int)$db->lastInsertId();

        PeriodCloseService::log($db, 'adjustment_created', $fyId, $periodId, $aid, "$title (".$voucherNo.")", null, $_SESSION['user_id']);
        logAudit($_SESSION['user_id'], 'Created closing adjustment: '.$title, 'finance');
        $db->commit();
        flash('success', 'Adjustment draft created (' . $voucherNo . '). Submit it for approval.');
        to('modules/finance/period_close/adjustments/index.php?fy_id='.$fyId);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Failed: ' . $e->getMessage());
        to('modules/finance/period_close/adjustments/create.php?fy_id='.$fyId);
    }
}

$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0); if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }
$accounts = $db->query("
    SELECT coa.id, coa.account_code, coa.account_name FROM chart_of_accounts coa
    WHERE coa.status='active' AND coa.allow_manual_entry=1 ORDER BY coa.account_code
")->fetchAll();
$types = ['accrual'=>'Accrual','prepayment'=>'Prepayment','provision'=>'Provision','correction'=>'Correction',
          'depreciation'=>'Depreciation Adjustment','tax'=>'Tax Adjustment','other'=>'Other Closing Adjustment'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">New Closing Adjustment</h1>
        <p class="text-gray-600">Accrual · Prepayment · Provision · Correction — balanced journals only</p>
    </div>
    <a href="index.php?fy_id=<?= $fyId ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="post" class="bg-white rounded-lg shadow p-6 space-y-5 max-w-4xl">
     <input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Financial Year <span class="text-red-600">*</span></labe>
            <select name="financial_year_id" required class="w-full border rounded px-3 py-2 text-sm" onchange="window.location='create.php?fy_id='+this.value">
                <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Period (optional)</label>
            <select name="period_id" class="w-full border rounded px-3 py-2 text-sm">
                <option value="">— year-level —</option>
                <?php
                if ($fyId) {
                    $ps = $db->prepare("SELECT id, period_name FROM accounting_periods WHERE financial_year_id=? ORDER BY start_date");
                    $ps->execute([$fyId]);
                    foreach ($ps->fetchAll() as $p) echo '<option value="'.$p['id'].'">'.sanitize($p['period_name']).'</option>';
                } ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Adjustment Type <span class="text-red-600">*</span></labe>
            <select name="adjustment_type" required class="w-full border rounded px-3 py-2 text-sm">
                <?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium mb-1">Title <span class="text-red-600">*</span></labe>
            <input name="title" required placeholder="e.g. Accrue December utilities" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium mb-1">Reason <span class="text-red-600">*</span></labe>
            <input name="reason" required placeholder="Why is this adjustment required?" class="w-full border rounded px-3 py-2 text-sm"></div>
    </div>

    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="text-sm font-medium">Journal Lines * (must balance)</label>
            <button type="button" onclick="addLine()" class="text-blue-600 hover:underline text-sm"><i class="fas fa-plus mr-1"></i>Add Line</button>
        </div>
        <table class="w-full text-sm" id="linesTable">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-2 py-2 text-left w-1/2">Account</th>
                <th class="px-2 py-2 text-right">Debit</th><th class="px-2 py-2 text-right">Credit</th><th></th>
            </tr></thead>
            <tbody></tbody>
        </table>
        <p class="text-xs text-gray-400 mt-1">Totals: <span id="totDr" class="font-mono">0.00</span> Dr / <span id="totCr" class="font-mono">0.00</span> Cr</p>
    </div>

    <button class="bg-blue-600 text-white px-6 py-2.5 rounded hover:bg-blue-700 text-sm">Create Draft Adjustment</button>
</form>

<script>
const accounts = <?= json_encode(array_map(fn($a) => ['id'=>$a['id'],'t'=>$a['account_code'].' — '.$a['account_name']], $accounts)) ?>;
function addLine() {
    const tb = document.querySelector('#linesTable tbody');
    const row = document.createElement('tr');
    let opts = '<option value="">— account —</option>' + accounts.map(a => `<option value="${a.id}">${a.t}</option>`).join('');
    row.innerHTML = `
        <td class="px-2 py-1"><select name="lines[][account_id]" required class="w-full border rounded px-2 py-1 text-sm">${opts}</select></td>
        <td class="px-2 py-1"><input type="number" step="0.01" min="0" name="lines[][debit]" class="w-full border rounded px-2 py-1 text-sm text-right lineDr"></td>
        <td class="px-2 py-1"><input type="number" step="0.01" min="0" name="lines[][credit]" class="w-full border rounded px-2 py-1 text-sm text-right lineCr"></td>
        <td class="px-2 py-1"><button type="button" onclick="this.closest('tr').remove();recalc()" class="text-red-500">&times;</button></td>`;
    tb.appendChild(row);
    recalc();
}
document.addEventListener('input', e => { if (e.target.classList.contains('lineDr') || e.target.classList.contains('lineCr')) recalc(); });
function recalc() {
    let d = 0, c = 0;
    document.querySelectorAll('.lineDr').forEach(i => d += parseFloat(i.value)||0);
    document.querySelectorAll('.lineCr').forEach(i => c += parseFloat(i.value)||0);
    document.getElementById('totDr').textContent = d.toFixed(2);
    document.getElementById('totCr').textContent = c.toFixed(2);
}
addLine(); addLine();
</script>

<?php require_once '../../../../includes/footer.php'; ?>
