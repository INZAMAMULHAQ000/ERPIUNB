<?php
$pageTitle = 'Edit Tax Rate';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_rates WHERE id = ?");
$stmt->execute([$id]);
$rate = $stmt->fetch();
if (!$rate) { flash('error', 'Tax rate not found.', 'error'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('edit.php?id=' . $id);
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
        redirect('edit.php?id=' . $id);
    }

    $effFrom = $effectiveFrom !== '' ? $effectiveFrom : null;
    $effTo = $effectiveTo !== '' ? $effectiveTo : null;

    if ($isDefault) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE tax_rates SET is_default = 0 WHERE tax_type_id = ?")->execute([$taxTypeId]);
            $db->prepare("UPDATE tax_rates SET tax_type_id = ?, rate_name = ?, rate_percentage = ?, effective_from = ?, effective_to = ?, is_default = ?, status = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$taxTypeId, $rateName, $ratePercentage, $effFrom, $effTo, 1, $status, $id]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    } else {
        $db->prepare("UPDATE tax_rates SET tax_type_id = ?, rate_name = ?, rate_percentage = ?, effective_from = ?, effective_to = ?, is_default = ?, status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$taxTypeId, $rateName, $ratePercentage, $effFrom, $effTo, 0, $status, $id]);
    }

    logAudit($_SESSION['user_id'], 'Updated tax rate #' . $id, 'tax');
    flash('success', 'Tax rate updated.');
    redirect('index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Rates</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Tax Rate</h1>
</div>

<?= taxui_tabs('rates') ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="edit.php?id=<?= $id ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type <span class="text-red-500">*</span></label>
            <?= taxui_types_select((string)$rate['tax_type_id'], 'tax_type_id', false) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rate Name <span class="text-red-500">*</span></label>
            <input type="text" name="rate_name" required maxlength="100" value="<?= sanitize($rate['rate_name']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Percentage <span class="text-red-500">*</span></label>
            <input type="number" name="rate_percentage" step="0.0001" min="0" required
                   value="<?= rtrim(rtrim(number_format((float)$rate['rate_percentage'], 4), '0'), '.') ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective From</label>
                <input type="date" name="effective_from" value="<?= sanitize($rate['effective_from']) ?>"
                       class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective To</label>
                <input type="date" name="effective_to" value="<?= sanitize($rate['effective_to']) ?>"
                       class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_default" value="1" <?= $rate['is_default'] ? 'checked' : '' ?> class="rounded">
                Default rate for this type
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= $rate['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $rate['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>