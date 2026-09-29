<?php
$pageTitle = 'New Tax Rate';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.create')) { die('Access Denied'); }

$db = getDB();
$selType = (int)($_GET['tax_type_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
    }
    $taxTypeId = (int)($_POST['tax_type_id'] ?? 0);
    $rateName = trim($_POST['rate_name'] ?? '');
    $ratePercentage = trim($_POST['rate_percentage'] ?? '');
    $effectiveFrom = trim($_POST['effective_from'] ?? '');
    $effectiveTo = trim($_POST['effective_to'] ?? '');
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    $status = (($_POST['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';

    $errors = [];
    if ($rateName === '') $errors[] = 'Rate name is required.';
    if ($ratePercentage === '' || !is_numeric($ratePercentage) || (float)$ratePercentage < 0) $errors[] = 'Percentage must be a non-negative number.';
    if ($taxTypeId <= 0) $errors[] = 'Tax type is required.';
    if ($effectiveFrom !== '' && $effectiveTo !== '' && $effectiveFrom > $effectiveTo) $errors[] = 'Effective from must not be after effective to.';

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('create.php');
    }

    // Non-blocking overlap warning, computed BEFORE saving.
    $conflict = TaxService::rateWindowConflicts(
        $taxTypeId,
        $effectiveFrom !== '' ? $effectiveFrom : null,
        $effectiveTo !== '' ? $effectiveTo : null
    );

    $effFrom = $effectiveFrom !== '' ? $effectiveFrom : null;
    $effTo = $effectiveTo !== '' ? $effectiveTo : null;

    if ($isDefault) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE tax_rates SET is_default = 0 WHERE tax_type_id = ?")->execute([$taxTypeId]);
            $db->prepare("INSERT INTO tax_rates (tax_type_id, rate_name, rate_percentage, effective_from, effective_to, is_default, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$taxTypeId, $rateName, $ratePercentage, $effFrom, $effTo, 1, $status]);
            $id = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    } else {
        $db->prepare("INSERT INTO tax_rates (tax_type_id, rate_name, rate_percentage, effective_from, effective_to, is_default, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$taxTypeId, $rateName, $ratePercentage, $effFrom, $effTo, 0, $status]);
        $id = (int)$db->lastInsertId();
    }

    logAudit($_SESSION['user_id'], 'Created tax rate #' . $id . ' (' . $rateName . ')', 'tax');
    flash('success', 'Tax rate created.');
    if ($conflict) {
        flash('warning', 'Overlapping effective windows exist for this type. The rate intended to apply should be flagged as the default.');
    }
    redirect('index.php?tax_type_id=' . $taxTypeId);
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Rates</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">New Tax Rate</h1>
    <p class="text-gray-600">Define a percentage rate for a tax type, optionally bounded by an effective window</p>
</div>

<?= taxui_tabs('rates') ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="create.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type <span class="text-red-500">*</span></label>
            <?= taxui_types_select((string)$selType, 'tax_type_id', false) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rate Name <span class="text-red-500">*</span></label>
            <input type="text" name="rate_name" required maxlength="100" placeholder="e.g. Standard Rating"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Percentage <span class="text-red-500">*</span></label>
            <input type="number" name="rate_percentage" step="0.0001" min="0" required placeholder="e.g. 5"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Percent charged on taxable amounts. Accepts up to 4 decimal places.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective From</label>
                <input type="date" name="effective_from"
                       class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective To</label>
                <input type="date" name="effective_to"
                       class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_default" value="1" class="rounded">
                Default rate for this type
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Tax Rate</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>