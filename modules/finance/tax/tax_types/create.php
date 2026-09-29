<?php
$pageTitle = 'New Tax Type';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.create')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('create.php'); }
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if ($code === '') $errors[] = 'Code is required.';

    if (!$errors && $code !== '') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM tax_types WHERE code = ?");
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() > 0) $errors[] = 'A tax type with code "' . $code . '" already exists.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('create.php');
    }

    $db->prepare("INSERT INTO tax_types (name, code, description, status)
                  VALUES (?, ?, ?, ?)")
       ->execute([$name, $code, $description, $status]);
    $id = (int)$db->lastInsertId();
    logAudit($_SESSION['user_id'], 'Created tax type #' . $id . ' (' . $code . ')', 'tax');
    flash('success', 'Tax type "' . $name . '" created. Add its rates next.', 'success');
    redirect('../tax_rates/create.php?tax_type_id=' . $id);
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Types</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">New Tax Type</h1>
    <p class="text-gray-600">Define a tax regime (VAT, GST, sales tax, withholding…)</p>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="create.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="100" placeholder="e.g. Value Added Tax"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" required maxlength="20" placeholder="e.g. VAT" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Short unique code used on invoices and reports (auto-uppercased).</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" placeholder="Optional — what this tax regime covers"
                      class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"></textarea>
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
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Tax Type</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>