<?php
$pageTitle = 'New Tax Adjustment';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('tax.adjust')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
    }

    $direction = ($_POST['direction'] ?? 'output') === 'input' ? 'input' : 'output';
    $taxTypeId = (int)($_POST['tax_type_id'] ?? 0);
    $adjustmentDate = trim($_POST['adjustment_date'] ?? '');
    $taxableAmount = (float)str_replace(',', '', $_POST['taxable_amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $reference = trim($_POST['reference'] ?? '');

    $errors = [];
    if ($taxTypeId <= 0) $errors[] = 'Please select a tax type.';
    if ($adjustmentDate === '') $errors[] = 'Adjustment date is required.';
    if ($taxableAmount < 0) $errors[] = 'Taxable amount cannot be negative.';
    if ($reason === '') $errors[] = 'Reason is required for an adjustment.';

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('create.php');
    }

    // Resolve the applicable rate for the tax type as of the adjustment date.
    $rate = TaxService::getApplicableTaxRate($taxTypeId, $adjustmentDate);
    $calc = TaxService::calculateLineTax($taxableAmount, ['tax_rate_id' => $rate['id'] ?? null]);
    $taxAmount = $calc['tax_amount'];
    $ratePct = $rate['rate_percentage'] ?? 0;

    // Adjustment number via voucher numbering.
    $vtStmt = $db->prepare("SELECT id FROM voucher_types WHERE voucher_name = 'Tax Adjustment' LIMIT 1");
    $vtStmt->execute();
    $vtId = $vtStmt->fetchColumn();
    if (!$vtId) {
        flash('error', 'Tax Adjustment voucher type not found.', 'error');
        redirect('create.php');
    }
    $no = generateVoucherNo($vtId, $db);
    if (!$no) {
        flash('error', 'Failed to generate the adjustment number.', 'error');
        redirect('create.php');
    }

    // generateVoucherNo scans journal_entries, so guard against a number already
    // taken by a saved draft in tax_adjustments.
    $uniq = $db->prepare("SELECT COUNT(*) FROM tax_adjustments WHERE adjustment_no = ?");
    $attempts = 0;
    while ($attempts < 5) {
        $uniq->execute([$no]);
        if ((int)$uniq->fetchColumn() === 0) break;
        $no = preg_replace_callback('/(\d+)$/', function ($m) {
            return str_pad((int)$m[1] + 1, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $no);
        $attempts++;
    }

    $periodId = TaxService::getPeriodForDate($taxTypeId, $adjustmentDate)['id'] ?? null;

    $db->prepare("INSERT INTO tax_adjustments (adjustment_no, tax_type_id, tax_period_id, adjustment_date, direction, taxable_amount, tax_amount, reason, reference, status, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)")
       ->execute([$no, $taxTypeId, $periodId, $adjustmentDate, $direction, $taxableAmount, $taxAmount, $reason, $reference !== '' ? $reference : null, $_SESSION['user_id']]);
    $id = (int)$db->lastInsertId();

    logAudit($_SESSION['user_id'], 'Created tax adjustment #' . $no, 'tax');
    flash('success', 'Adjustment saved as draft. Review then approve to post to the ledger.');
    redirect('view.php?id=' . $id);
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Adjustments</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">New Tax Adjustment</h1>
    <p class="text-gray-600">Manually correct output or input tax — saved as a draft until approved</p>
</div>

<?= taxui_tabs('adjustments') ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="create.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Direction <span class="text-red-500">*</span></label>
            <select name="direction" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="output">Output Tax</option>
                <option value="input">Input Tax</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type <span class="text-red-500">*</span></label>
            <?= taxui_types_select('', 'tax_type_id', false) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Adjustment Date <span class="text-red-500">*</span></label>
            <input type="date" name="adjustment_date" value="<?= date('Y-m-d') ?>" required
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Taxable Amount <span class="text-red-500">*</span></label>
            <input type="number" name="taxable_amount" step="0.01" min="0" required
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Base amount the tax is computed on; tax is calculated at the rate effective on the adjustment date.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-red-500">*</span></label>
            <textarea name="reason" rows="3" required placeholder="Why is this adjustment being made?"
                      class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
            <input type="text" name="reference" maxlength="200" placeholder="Optional — source document or note"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Draft</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>