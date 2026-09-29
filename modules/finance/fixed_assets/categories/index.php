<?php
$pageTitle = 'Fixed Asset Categories';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();

// Get all financial categories with their mappings
$categories = $db->query("
    SELECT 
        fc.id,
        ac.category_name as asset_category,
        ac.category_code,
        ca.account_code as asset_gl_code,
        ca.account_name as asset_gl_name,
        ca2.account_code as dep_gl_code,
        ca2.account_name as dep_gl_name,
        ca3.account_code as expense_gl_code,
        ca3.account_name as expense_gl_name,
        dm.method_name as depreciation_method,
        dm.method_code,
        fc.useful_life_months,
        fc.residual_value_percentage,
        fc.status
    FROM fixed_asset_categories fc
    LEFT JOIN asset_categories ac ON fc.asset_category_id = ac.id
    LEFT JOIN chart_of_accounts ca ON fc.asset_gl_account_id = ca.id
    LEFT JOIN chart_of_accounts ca2 ON fc.accumulated_depreciation_gl_account_id = ca2.id
    LEFT JOIN chart_of_accounts ca3 ON fc.depreciation_expense_gl_account_id = ca3.id
    LEFT JOIN depreciation_methods dm ON fc.depreciation_method_id = dm.id
    ORDER BY ac.category_name
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Fixed Asset Categories</h1>
        <p class="text-gray-600">Configure financial categories with GL mappings and depreciation defaults</p>
    </div>
    <?php if (hasPermission('fixedasset.create')): ?>
    <a href="<?= url('modules/finance/fixed_assets/categories/setup.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Setup Category</a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset GL Account</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depreciation GL</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expense GL</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depreciation Method</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Useful Life (Months)</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Residual %</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($categories as $cat): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($cat['asset_category'] ?? '-') ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= sanitize($cat['asset_gl_code'] ?? '-') . ' - ' . sanitize($cat['asset_gl_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= sanitize($cat['dep_gl_code'] ?? '-') . ' - ' . sanitize($cat['dep_gl_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= sanitize($cat['expense_gl_code'] ?? '-') . ' - ' . sanitize($cat['expense_gl_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-sm text-gray-600">
                    <?= sanitize($cat['method_name'] ?? '-') ?> (<?= sanitize($cat['method_code'] ?? '-') ?>)
                </td>
                <td class="px-6 py-4 text-sm text-center text-gray-600"><?= $cat['default_useful_life_months'] ?? 0 ?></td>
                <td class="px-6 py-4 text-sm text-center text-gray-600"><?= $cat['default_residual_value_percentage'] ?? 0 ?>%</td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium 
                        <?= $cat['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                        <?= ucfirst($cat['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('fixedasset.edit')): ?>
                    <a href="<?= url('modules/finance/fixed_assets/categories/setup.php?id=' . $cat['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
            <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">No categories configured. <a href="<?= url('modules/finance/fixed_assets/categories/setup.php') ?>" class="text-blue-600 hover:text-blue-900">Setup one now</a>.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
