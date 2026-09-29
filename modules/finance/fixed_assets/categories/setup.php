<?php
$pageTitle = 'Setup Fixed Asset Category';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';

if (!hasPermission('fixedasset.create') && !hasPermission('fixedasset.edit')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? '';
$category = null;

if ($id) {
    $stmt = $db->prepare("
        SELECT fc.*, ac.category_name as asset_category
        FROM fixed_asset_categories fc
        LEFT JOIN asset_categories ac ON fc.asset_category_id = ac.id
        WHERE fc.id = ?
    ");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        flash('error', 'Category not found');
        to('modules/finance/fixed_assets/categories/index.php');
    }
}

$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

// Get asset categories
$assetCategories = $db->query("SELECT id, category_name, category_code FROM asset_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();

// Get GL accounts
$glAccounts = $db->query("SELECT coa.id, coa.account_code, coa.account_name, at.type_name as account_type FROM chart_of_accounts coa LEFT JOIN account_types at ON coa.type_id = at.id WHERE coa.status = 'active' ORDER BY coa.account_code")->fetchAll();

// Get depreciation methods
$methods = $db->query("SELECT id, method_name, method_code FROM depreciation_methods WHERE status = 'active' ORDER BY method_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'asset_category_id' => $_POST['asset_category_id'] ?? '',
        'asset_gl_account_id' => $_POST['asset_gl_account_id'] ?? '',
        'accumulated_dep_gl_account_id' => $_POST['accumulated_dep_gl_account_id'] ?? '',
        'depreciation_expense_gl_account_id' => $_POST['depreciation_expense_gl_account_id'] ?? '',
        'depreciation_method_id' => $_POST['depreciation_method_id'] ?? '',
        'default_useful_life_months' => $_POST['default_useful_life_months'] ?? '',
        'default_residual_value_percentage' => $_POST['default_residual_value_percentage'] ?? '',
        'status' => $_POST['status'] ?? 'active',
    ];

    $errors = validate($data, [
        'asset_category_id' => 'required',
        'asset_gl_account_id' => 'required',
        'accumulated_dep_gl_account_id' => 'required',
        'depreciation_expense_gl_account_id' => 'required',
        'depreciation_method_id' => 'required',
        'default_useful_life_months' => 'required|numeric',
        'default_residual_value_percentage' => 'required|numeric',
    ]);

    if (empty($errors)) {
        if ($id) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE fixed_asset_categories SET
                    asset_category_id = ?,
                    asset_gl_account_id = ?,
                    accumulated_dep_gl_account_id = ?,
                    depreciation_expense_gl_account_id = ?,
                    depreciation_method_id = ?,
                    default_useful_life_months = ?,
                    default_residual_value_percentage = ?,
                    status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['asset_category_id'],
                $data['asset_gl_account_id'],
                $data['accumulated_dep_gl_account_id'],
                $data['depreciation_expense_gl_account_id'],
                $data['depreciation_method_id'],
                $data['default_useful_life_months'],
                $data['default_residual_value_percentage'],
                $data['status'],
                $id
            ]);
            
            logAudit($_SESSION['user_id'], 'Updated fixed asset category', 'fixed_assets');
            flash('success', 'Category updated successfully');
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO fixed_asset_categories (
                    asset_category_id, asset_gl_account_id, accumulated_dep_gl_account_id,
                    depreciation_expense_gl_account_id, depreciation_method_id,
                    default_useful_life_months, default_residual_value_percentage, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['asset_category_id'],
                $data['asset_gl_account_id'],
                $data['accumulated_dep_gl_account_id'],
                $data['depreciation_expense_gl_account_id'],
                $data['depreciation_method_id'],
                $data['default_useful_life_months'],
                $data['default_residual_value_percentage'],
                $data['status']
            ]);
            
            logAudit($_SESSION['user_id'], 'Created fixed asset category', 'fixed_assets');
            flash('success', 'Category created successfully');
        }
        to('modules/finance/fixed_assets/categories/index.php');
    }
}
?>

<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/categories/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Categories</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-4xl">
    <h2 class="text-xl font-semibold mb-6">
        <?= $id ? 'Edit Fixed Asset Category' : 'Setup New Fixed Asset Category' ?>
    </h2>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Asset Category <span class="text-red-600">*</span></labe>
                <select name="asset_category_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Select Asset Category</option>
                    <?php foreach ($assetCategories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= old('asset_category_id') == $cat['id'] ? 'selected' : '' ?>>
                        <?= sanitize($cat['category_code'] ?? '') ?> - <?= sanitize($cat['category_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">GL Account Mappings</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fixed Asset GL Account <span class="text-red-600">*</span></labe>
                    <select name="asset_gl_account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Account</option>
                        <?php foreach ($glAccounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= old('asset_gl_account_id') == $acc['id'] ? 'selected' : '' ?>>
                            <?= sanitize($acc['account_code']) ?> - <?= sanitize($acc['account_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Fixed Asset account (Balance Sheet - Asset)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Accumulated Depreciation GL <span class="text-red-600">*</span></labe>
                    <select name="accumulated_dep_gl_account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Account</option>
                        <?php foreach ($glAccounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= old('accumulated_dep_gl_account_id') == $acc['id'] ? 'selected' : '' ?>>
                            <?= sanitize($acc['account_code']) ?> - <?= sanitize($acc['account_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Contra-asset account (Accumulated Depreciation)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Depreciation Expense GL <span class="text-red-600">*</span></labe>
                    <select name="depreciation_expense_gl_account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Account</option>
                        <?php foreach ($glAccounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= old('depreciation_expense_gl_account_id') == $acc['id'] ? 'selected' : '' ?>>
                            <?= sanitize($acc['account_code']) ?> - <?= sanitize($acc['account_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Expense account (P&L - Depreciation Expense)</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Depreciation Method <span class="text-red-600">*</span></labe>
                <select name="depreciation_method_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Select Method</option>
                    <?php foreach ($methods as $method): ?>
                    <option value="<?= $method['id'] ?>" <?= old('depreciation_method_id') == $method['id'] ? 'selected' : '' ?>>
                        <?= sanitize($method['method_name']) ?> (<?= sanitize($method['method_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Useful Life (Months) <span class="text-red-600">*</span></labe>
                <input type="number" name="default_useful_life_months" value="<?= old('default_useful_life_months', 60) ?>" min="1" max="1200" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                <p class="text-xs text-gray-500 mt-1">Example: 5 years = 60 months, 10 years = 120 months</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Default Residual Value (%)</label>
            <input type="number" name="default_residual_value_percentage" value="<?= old('default_residual_value_percentage', 5) ?>" min="0" max="100" step="0.1" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Percentage of asset cost remaining at end of useful life</p>
        </div>

        <div class="pt-4 flex gap-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                <?= $id ? 'Update Category' : 'Create Category' ?>
            </button>
            <a href="<?= url('modules/finance/fixed_assets/categories/index.php') ?>" class="text-gray-600 hover:text-gray-900 px-6 py-2 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
