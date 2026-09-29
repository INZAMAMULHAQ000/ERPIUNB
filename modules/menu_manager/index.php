<?php
$pageTitle = 'Sidebar Menu Manager';
require_once '../../includes/header.php';

if (!hasPermission('settings.view')) {
    die('Access Denied');
}

$db = getDB();

// Handle toggle active status
if (isset($_GET['toggle']) && isset($_GET['active'])) {
    $stmt = $db->prepare("UPDATE sidebar_menu SET is_active = ? WHERE id = ?");
    $stmt->execute([(int)$_GET['active'], (int)$_GET['toggle']]);
    header("Location: index.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Set children's parent_id to NULL first
    $db->prepare("UPDATE sidebar_menu SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM sidebar_menu WHERE id = ?")->execute([$id]);
    header("Location: index.php");
    exit;
}

$stmt = $db->query("
    SELECT id, parent_id, section, section_order, label, icon, route,
           permission, sort_order, is_active, is_indented
    FROM sidebar_menu
    ORDER BY section_order ASC, sort_order ASC, id ASC
");
$items = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Sidebar Menu Manager</h1>
        <p class="text-gray-600">Add, edit, reorder, or disable sidebar navigation items</p>
    </div>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Menu Item
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Route</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permission</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Order</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($items)): ?>
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    No menu items yet. <a href="create.php" class="text-blue-600 hover:underline">Add the first one.</a>
                </td>
            </tr>
            <?php endif; ?>
            <?php
            $lastSection = null;
            foreach ($items as $item):
                $section = $item['section'] ?? '(Top)';
                $isNewSection = ($section !== $lastSection);
                $lastSection = $section;
            ?>
            <?php if ($isNewSection): ?>
            <tr class="bg-gray-100">
                <td colspan="7" class="px-4 py-2 text-sm font-semibold text-gray-700">
                    <?php if ($item['section'] === null): ?>
                        <em>Top Level (no section header)</em>
                    <?php else: ?>
                        Section: <?= sanitize($item['section']) ?> (order: <?= (int)$item['section_order'] ?>)
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-500">
                    <?= $item['section'] ? sanitize($item['section']) : '<span class="text-gray-400">—</span>' ?>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2 <?= $item['is_indented'] ? 'ml-6' : '' ?>">
                        <i class="<?= sanitize($item['icon']) ?> w-5 text-gray-500"></i>
                        <span class="font-medium text-gray-900"><?= sanitize($item['label']) ?></span>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($item['route']) ?></td>
                <td class="px-4 py-3">
                    <?php if ($item['permission']): ?>
                        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-0.5 rounded"><?= sanitize($item['permission']) ?></span>
                    <?php else: ?>
                        <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center text-sm text-gray-600"><?= (int)$item['sort_order'] ?></td>
                <td class="px-4 py-3 text-center">
                    <a href="?toggle=<?= $item['id'] ?>&active=<?= $item['is_active'] ? 0 : 1 ?>"
                       class="inline-block px-2 py-1 text-xs font-medium rounded <?= $item['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $item['is_active'] ? 'Active' : 'Inactive' ?>
                    </a>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="edit.php?id=<?= $item['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-3">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $item['id'] ?>"
                       onclick="return confirm('Delete this menu item?')"
                       class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once '../../includes/footer.php'; ?>
