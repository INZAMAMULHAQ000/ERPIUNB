<?php
$pageTitle = 'Edit Ticket Category';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('ticket_categories.edit')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM ticket_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();
if (!$category) { flash('error', 'Category not found'); to('http://localhost/ERP/modules/ticket_categories/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF token'); }

    $data = [
        'category_name' => trim($_POST['category_name'] ?? ''),
        'category_code' => trim($_POST['category_code'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'priority_default' => $_POST['priority_default'] ?? 'medium',
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'category_name' => 'required|min:2|max:100',
        'category_code' => 'required|min:2|max:50'
    ]);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM ticket_categories WHERE category_name = ? AND id != ?");
        $checkStmt->execute([$data['category_name'], $id]);
        if ($checkStmt->fetch()) { $errors['category_name'] = 'Category name already exists'; }
    }

    if (empty($errors)) {
        $checkCodeStmt = $db->prepare("SELECT id FROM ticket_categories WHERE category_code = ? AND id != ?");
        $checkCodeStmt->execute([$data['category_code'], $id]);
        if ($checkCodeStmt->fetch()) { $errors['category_code'] = 'Category code already exists'; }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/ticket_categories/edit.php?id=' . $id);
    }

    
        $stmt = $db->prepare("UPDATE ticket_categories SET category_name = ?, category_code = ?, description = ?, priority_default = ?, status = ? WHERE id = ?");
        $stmt->execute([$data['category_name'], $data['category_code'], $data['description'], $data['priority_default'], $data['status'], $id]);
    
        logAudit($_SESSION['user_id'], 'update_ticket_category', 'ticket_categories');
        flash('success', 'Ticket category updated successfully');
        to('http://localhost/ERP/modules/ticket_categories/index.php');
    

} else {
    $_POST = $category;
}
?>

<div class="mb-6">
    <a href="<?= url('modules/ticket_categories/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Categories</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit Ticket Category</h2>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category Name <span class="text-red-600">*</span></labe>
                <input type="text" name="category_name" value="<?= old('category_name') ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category Code <span class="text-red-600">*</span></labe>
                <input type="text" name="category_code" value="<?= old('category_code') ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Priority</label>
                <select name="priority_default" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="low" <?= old('priority_default') === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= old('priority_default') === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= old('priority_default') === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="critical" <?= old('priority_default') === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="active" <?= old('status') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
        </div>
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Update Category</button>
            <a href="<?= url('modules/ticket_categories/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>

