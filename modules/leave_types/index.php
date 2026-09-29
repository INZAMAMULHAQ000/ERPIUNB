<?php
$pageTitle = 'Leave Types';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('leaves.view')) {
    die('Access Denied');
}

$db = getDB();
$types = $db->query("SELECT * FROM leave_types ORDER BY leave_name")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Leave Types</h1>
        <p class="text-gray-600">Manage leave categories</p>
    </div>
    <?php if (hasPermission('leaves.create')): ?>
    <a href="<?= url('modules/leave_types/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Leave Type
    </a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leave Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Days</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Carry Forward</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($types as $type): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($type['leave_name']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($type['leave_code']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= $type['total_days'] ?> days</td>
                <td class="px-6 py-4">
                    <?php if ($type['carry_forward']): ?>
                    <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Yes</span>
                    <?php else: ?>
                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">No</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <?php if ($type['status'] === 'active'): ?>
                    <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Active</span>
                    <?php else: ?>
                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('leaves.edit')): ?>
                    <a href="<?= url('modules/leave_types/edit.php?id=' . $type['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($types)): ?>
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No leave types found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>

