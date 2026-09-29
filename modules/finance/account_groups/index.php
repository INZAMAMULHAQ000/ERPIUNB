<?php
$pageTitle = 'Account Groups';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();
$stmt = $db->query("SELECT * FROM account_groups ORDER BY parent_group_id IS NULL DESC, display_order ASC");
$allGroups = $stmt->fetchAll();

// Build tree index
$tree = [];
foreach ($allGroups as $g) {
    $tree[$g['parent_group_id'] ?? 0][] = $g;
}

// Flatten tree depth-first so children follow parents
$flatGroups = [];
function flattenTree($tree, $parentId = 0, $depth = 0, &$result = []) {
    if (!isset($tree[$parentId])) return;
    foreach ($tree[$parentId] as $node) {
        $node['_depth'] = $depth;
        $result[] = $node;
        flattenTree($tree, $node['id'], $depth + 1, $result);
    }
    return $result;
}
flattenTree($tree, 0, 0, $flatGroups);

$natureColors = [
    'asset'     => 'bg-blue-100 text-blue-800',
    'liability' => 'bg-orange-100 text-orange-800',
    'equity'    => 'bg-purple-100 text-purple-800',
    'income'    => 'bg-green-100 text-green-800',
    'expense'   => 'bg-red-100 text-red-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Account Groups</h1>
        <p class="text-gray-600">Manage hierarchical account group structure</p>
    </div>
    <?php if (hasPermission('finance.group.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Group
    </a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nature</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($flatGroups)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No account groups found</td>
            </tr>
            <?php else: ?>
            <?php foreach ($flatGroups as $group):
                $hasChildren = isset($tree[$group['id']]);
            ?>
            <tr data-parent="<?= $group['parent_group_id'] ?? 0 ?>"
                style="--depth: <?= $group['_depth']; ?>; <?= $group['_depth'] > 0 ? 'display: none;' : '' ?>"
                class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <span class="inline-flex items-center" style="padding-left: calc(1.5rem * var(--depth, 0))">
                        <?php if ($hasChildren): ?>
                        <span class="tree-toggle cursor-pointer select-none mr-1 inline-block w-4 text-center"
                              onclick="toggleChildren(this, <?= $group['id'] ?>)">
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </span>
                        <?php else: ?>
                        <span class="inline-block w-5"></span>
                        <?php endif; ?>
                        <span class="font-medium text-gray-900"><?= sanitize($group['group_name']) ?></span>
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $natureColors[$group['nature']] ?? 'bg-gray-100 text-gray-800' ?>">
                        <?= ucfirst($group['nature']) ?>
                    </span>
                </td>
                <td class="px-6 py-4"><?= (int)$group['display_order'] ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $group['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                        <?= ucfirst($group['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('finance.group.create')): ?>
                    <a href="create.php?parent_id=<?= $group['id'] ?>" class="text-green-600 hover:text-green-900 mr-2" title="Add Child">
                        <i class="fas fa-plus"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('finance.view')): ?>
                    <a href="edit.php?id=<?= $group['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-2" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('finance.view') && !$hasChildren): ?>
                    <a href="delete.php?id=<?= $group['id'] ?>" class="text-red-600 hover:text-red-900" title="Delete"
                       onclick="return confirm('Are you sure you want to delete this group?')">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleChildren(btn, groupId) {
    var rows = document.querySelectorAll('tr[data-parent="' + groupId + '"]');
    if (rows.length === 0) return;
    var hidden = rows[0].style.display === 'none';
    rows.forEach(function(r) {
        r.style.display = hidden ? '' : 'none';
    });
    var icon = btn.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-chevron-right');
        icon.classList.toggle('fa-chevron-down');
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
