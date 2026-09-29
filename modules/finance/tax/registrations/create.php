<?php
$pageTitle = 'New Tax Registration';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.create')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
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
        redirect('create.php');
    }

    $db->prepare("INSERT INTO tax_registrations (company_name, tax_number, tax_type_id, country, registration_date, effective_from, effective_to, status)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$company_name, $tax_number, $tax_type_id, $country, $registration_date, $effective_from, $effective_to, $status]);
    $id = (int)$db->lastInsertId();
    logAudit($_SESSION['user_id'], 'Created tax registration #' . $id . ' (' . $company_name . ')', 'tax');
    flash('success', 'Tax registration for "' . $company_name . '" created.');
    redirect('index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Registrations</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">New Tax Registration</h1>
    <p class="text-gray-600">Register a tax ID your company holds in a given tax jurisdiction</p>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="create.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
            <input type="text" name="company_name" required maxlength="200" placeholder="e.g. Acme Trading LLC"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Number <span class="text-red-500">*</span></label>
            <input type="text" name="tax_number" required maxlength="100" placeholder="e.g. AA9876543210001"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">The registration / TRN number issued by the tax authority.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type <span class="text-red-500">*</span></label>
            <?= taxui_types_select('', 'tax_type_id', false) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
            <input type="text" name="country" maxlength="100" placeholder="e.g. AE" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registration Date</label>
                <input type="date" name="registration_date" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective From</label>
                <input type="date" name="effective_from" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective To</label>
                <input type="date" name="effective_to" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
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
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Registration</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>