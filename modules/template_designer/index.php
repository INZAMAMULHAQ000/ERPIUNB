<?php
/**
 * Template Designer — List Templates
 */
$pageTitle = 'Template Designer';
require_once '../../includes/header.php';

if (!hasPermission('template_designer.view')) {
    die('Access Denied');
}

$db = getDB();

// Handle filters
$search = $_GET['search'] ?? '';
$categoryId = $_GET['category_id'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (t.template_name LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId) {
    $where .= " AND t.category_id = ?";
    $params[] = (int)$categoryId;
}
if ($type) {
    $where .= " AND t.template_type = ?";
    $params[] = $type;
}
if ($status) {
    $where .= " AND t.status = ?";
    $params[] = $status;
}

$stmt = $db->prepare("
    SELECT t.*, tc.name AS category_name,
           u.name AS creator_name,
           (SELECT COUNT(*) FROM template_versions WHERE template_id = t.id) AS version_count,
           (SELECT COUNT(*) FROM template_module_mappings WHERE template_id = t.id) AS mapping_count
    FROM templates t
    LEFT JOIN template_categories tc ON t.category_id = tc.id
    LEFT JOIN users u ON t.created_by = u.id
    $where
    ORDER BY t.updated_at DESC
");
$stmt->execute($params);
$templates = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT id, name FROM template_categories ORDER BY sort_order")->fetchAll();

// Get distinct template types that exist
$types = $db->query("SELECT DISTINCT template_type FROM templates WHERE template_type IS NOT NULL ORDER BY template_type")->fetchAll();
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Template Designer</h1>
            <p class="text-sm text-slate-500 mt-1">Create and manage reusable document templates</p>
        </div>
        <?php if (hasPermission('template_designer.create')): ?>
        <a href="create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <i class="fas fa-plus"></i>
            <span>New Template</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search templates..."
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
                <select name="category_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Type</label>
                <select name="type" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Types</option>
                    <?php foreach (['invoice', 'leave_report', 'employee_report', 'purchase_report', 'salary_slip', 'quotation', 'offer_letter', 'experience_letter', 'purchase_order', 'delivery_note', 'stock_report', 'attendance_report', 'report', 'custom'] as $t): ?>
                    <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <?php if ($search || $categoryId || $type || $status): ?>
            <a href="index.php" class="px-4 py-2 text-slate-500 hover:text-slate-700 text-sm">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Template List -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if (empty($templates)): ?>
        <div class="text-center py-16">
            <div class="text-5xl text-slate-300 mb-4"><i class="fas fa-file-alt"></i></div>
            <h3 class="text-lg font-medium text-slate-600 mb-2">No templates yet</h3>
            <p class="text-slate-400 mb-6">Create your first document template to get started</p>
            <?php if (hasPermission('template_designer.create')): ?>
            <a href="create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus"></i> Create Template
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Name</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Category</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Type</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Version</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Mappings</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Created By</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($templates as $tpl): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3">
                        <a href="edit.php?id=<?= $tpl['id'] ?>" class="font-medium text-slate-800 hover:text-indigo-600">
                            <?= sanitize($tpl['template_name']) ?>
                        </a>
                        <?php if ($tpl['description']): ?>
                        <p class="text-xs text-slate-400 mt-0.5"><?= sanitize(mb_substr($tpl['description'], 0, 80)) ?><?= mb_strlen($tpl['description'] ?? '') > 80 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600"><?= sanitize($tpl['category_name'] ?? '-') ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700 rounded-full">
                            <?= ucwords(str_replace('_', ' ', $tpl['template_type'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-sm text-slate-600">v<?= (int)$tpl['version'] ?></td>
                    <td class="px-4 py-3 text-center text-sm text-slate-600"><?= (int)$tpl['mapping_count'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php $statusColors = ['draft' => 'bg-yellow-100 text-yellow-700', 'published' => 'bg-green-100 text-green-700', 'archived' => 'bg-slate-100 text-slate-500']; ?>
                        <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full <?= $statusColors[$tpl['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?= ucfirst($tpl['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600"><?= sanitize($tpl['creator_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="edit.php?id=<?= $tpl['id'] ?>" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded hover:bg-slate-100" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="preview.php?id=<?= $tpl['id'] ?>" class="p-1.5 text-slate-400 hover:text-blue-600 rounded hover:bg-slate-100" title="Preview" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (hasPermission('template_designer.create')): ?>
                            <a href="duplicate.php?id=<?= $tpl['id'] ?>" class="p-1.5 text-slate-400 hover:text-green-600 rounded hover:bg-slate-100" title="Duplicate" onclick="return confirm('Duplicate this template?')">
                                <i class="fas fa-copy"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('template_designer.delete')): ?>
                            <a href="delete.php?id=<?= $tpl['id'] ?>" class="p-1.5 text-slate-400 hover:text-red-600 rounded hover:bg-slate-100" title="Delete" onclick="return confirm('Are you sure you want to delete this template? This cannot be undone.')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>