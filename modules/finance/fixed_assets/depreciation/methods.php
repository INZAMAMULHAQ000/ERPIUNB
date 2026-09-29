<?php
$pageTitle = 'Depreciation Methods';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();
$methods = $db->query("SELECT * FROM depreciation_methods WHERE status = 'active' ORDER BY method_code")->fetchAll();
?>
<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/categories/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Categories</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Depreciation Methods</h1>
    
    <?php if (hasPermission('fixedasset.create')): ?>
    <a href="<?= url('modules/finance/fixed_assets/acquisitions/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition mb-6"><i class="fas fa-plus mr-1"></i>New Depreciation Setup</a>
    <?php endif; ?>

    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($methods as $method): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($method['method_name']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($method['method_code']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($method['description'] ?? '') ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded <?= $method['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($method['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($methods)): ?>
            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No depreciation methods found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../includes/footer.php'; ?>