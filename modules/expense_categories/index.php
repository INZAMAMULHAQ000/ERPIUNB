<?php
$pageTitle = 'Expense Categories';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('expenses.view')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('expenses.create')) {
    $category_name = $_POST['category_name'] ?? '';
    $category_code = $_POST['category_code'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($category_name) {
        try {
            $stmt = $db->prepare("INSERT INTO expense_categories (category_name, category_code, description) VALUES (?, ?, ?)");
            $stmt->execute([$category_name, $category_code, $description]);
            flash('success', 'Category added successfully');
        } catch (Exception $e) {
            flash('error', 'Category already exists or invalid');
        }
        to('http://localhost/ERP/modules/expense_categories/index.php');
    }
}

if (isset($_GET['toggle']) && hasPermission('expenses.edit')) {
    $id = $_GET['toggle'];
    $cat = $db->prepare("SELECT status FROM expense_categories WHERE id = ?");
    $cat->execute([$id]);
    $cat = $cat->fetch();
    if ($cat) {
        $newStatus = $cat['status'] == 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE expense_categories SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        to('http://localhost/ERP/modules/expense_categories/index.php');
    }
}

$categories = $db->query("SELECT * FROM expense_categories ORDER BY category_name")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense Categories</h1>
        <p class="text-gray-600">Manage expense categories</p>
    </div>
</div>

<?php if (hasPermission('expenses.create')): ?>
<div class="bg-white rounded-lg shadow mb-6 p-6">
    <h2 class="text-lg font-semibold mb-4">Add New Category</h2>
    <form method="POST" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Category Name <span class="text-red-600">*</span></labe>
            <input type="text" name="category_name" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-32">
            <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
            <input type="text" name="category_code" maxlength="20" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <input type="text" name="description" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add</button>
    </form>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($categories as $cat): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($cat['category_code']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($cat['category_name']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($cat['description'] ?? '-') ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $cat['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= ucfirst($cat['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('expenses.edit')): ?>
                    <a href="<?= url('modules/expense_categories/index.php?toggle=' . $cat['id'] . '') ?>" class="text-gray-600 hover:text-gray-900 mr-2" title="<?= $cat['status'] == 'active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-toggle-<?= $cat['status'] == 'active' ? 'on' : 'off' ?>"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>

