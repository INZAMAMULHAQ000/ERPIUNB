<?php
$pageTitle = 'Edit Tax Registration';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT r.*, t.name AS type_name FROM tax_registrations r JOIN tax_types t ON t.id = r.tax_type_id WHERE r.id = ?");
$stmt->execute([$id]);
$reg = $stmt->fetch();
if (!$reg) { flash('error', 'Tax registration not found.', 'error'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('edit.php?id=' . $id);
    }
    $company_name = trim($_POST['company_name'] ?? '');
    $tax_number = trim($_POST['tax_number'] ?? '');
    $tax_type_id = (int)($_POST['tax_type_id'] ?? 0);
    $country = trim($_POST['country'] ?? '');
    $registration_date = trim($_POST['registration_date'] ?? '') ?: null;
    $effective_from = trim($_POST['effective_from'] ?? '') ?: null;
    $effective_to = trim($_POST['effective_to'] ?? '') ?: null;
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $errors = [];
    if ($company_name === '') $errors[] = 'Company name is required.';
    if ($tax_number === '') $errors[] = 'Tax number is required.';
    if (!$tax_type_id) $errors[] = 'Please choose a tax type.';

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('edit.php?id=' . $id);
    }

    $db->prepare("UPDATE tax_registrations SET company_name = ?, tax_number = ?, tax_type_id = ?, country = ?, registration_date = ?, effective_from = ?, effective_to = ?, status = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$company_name, $tax_number, $tax_type_id, $country, $registration_date, $effective_from, $effective_to, $status, $id]);
    logAudit($_SESSION['user_id'], 'Updated tax registration #' . $id . ' (' . $company_name . ')', 'tax');
    flash('success', 'Tax registration updated.');
    redirect('index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Registrations</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Tax Registration</h1>
    <p class="text-gray-600">
        <span class="px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($reg['type_name']) ?></span>
    </p>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="edit.php?id=<?= $id ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
            <input type="text" name="company_name" required maxlength="200" value="<?= sanitize($reg['company_name']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Number <span class="text-red-500">*</span></label>
            <input type="text" name="tax_number" required maxlength="100" value="<?= sanitize($reg['tax_number']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">The registration / TRN number issued by the tax authority.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type <span class="text-red-500">*</span></label>
            <?= taxui_types_select($reg['tax_type_id'], 'tax_type_id', false) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
            <input type="text" name="country" maxlength="100" value="<?= sanitize($reg['country']) ?>" placeholder="e.g. AE"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registration Date</label>
                <input type="date" name="registration_date" value="<?= sanitize($reg['registration_date']) ?>"
                       class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective From</label>
                <input type="date" name="effective_from" value="<?= sanitize($reg['effective_from']) ?>"
                       class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective To</label>
                <input type="date" name="effective_to" value="<?= sanitize($reg['effective_to']) ?>"
                       class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= $reg['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $reg['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>