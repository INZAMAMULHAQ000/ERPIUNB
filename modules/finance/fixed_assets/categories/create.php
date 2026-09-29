<?php
$pageTitle = 'Create Asset Category';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/validation.php';

if (!hasPermission('fixedasset.create')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'category_name' => $_POST['category_name'] ?? '',
        'category_code' => $_POST['category_code'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'active',
    ];

    $errors = validate($data, ['category_name' => 'required']);

    if (empty($errors)) {
        // Check unique category_code
        $checkStmt = $db->prepare("SELECT id FROM asset_categories WHERE category_code = ?");
        $checkStmt->execute([$data['category_code']]);
        if ($checkStmt->fetch()) {
            $errors['category_code'] = 'Category code already exists';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO asset_categories (category_name, category_code, description, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['category_name'],
            $data['category_code'],
            $data['description'],
            $data['status'],
        ]);
        
        logAudit($_SESSION['user_id'], 'Created asset category: ' . $data['category_name'], 'fixed_assets');
        flash('success', 'Category created successfully');
        to('modules/fixed_assets/categories/index.php');
    }
}
?>
<div class="mb-6">
    <a href="<?= url('modules/fixed_assets/categories/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Categories</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-6">Add New Category</h2>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category Name <span class="text-red-600">*</span></labe>
                <input type="text" name="category_name" value="<?= sanitize($_POST['category_name'] ?? '') ?>" required placeholder="e.g. Buildings" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category Code</label>
                <input type="text" name="category_code" value="<?= sanitize($_POST['category_code'] ?? '') ?>" placeholder="e.g. BLD" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize($_POST['description'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Create Category</button>
            <a href="<?= url('modules/fixed_assets/categories/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../../includes/footer.php'; ?>