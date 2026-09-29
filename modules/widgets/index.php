<?php
$pageTitle = 'Widget Management';
require_once '../../includes/header.php';

if (!hasPermission('widgets.manage')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();

// Handle toggles/bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $widgetKey = $_POST['widget_key'] ?? '';

    if ($action === 'toggle' && $widgetKey) {
        $stmt = $db->prepare("UPDATE widget_definitions SET status = IF(status='active','inactive','active') WHERE widget_key = ?");
        $stmt->execute([$widgetKey]);
        logAudit($_SESSION['user_id'], 'toggled_widget', $widgetKey);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Widget status updated'];
    } elseif ($action === 'update' && $widgetKey) {
        $stmt = $db->prepare("UPDATE widget_definitions SET name=?, description=?, icon=?, category=?, default_width=?, default_height=?, min_width=?, min_height=?, permission_required=? WHERE widget_key=?");
        $stmt->execute([
            $_POST['name'], $_POST['description'], $_POST['icon'],
            $_POST['category'], (int)$_POST['default_width'], (int)$_POST['default_height'],
            (int)$_POST['min_width'], (int)$_POST['min_height'],
            $_POST['permission_required'] ?: null, $widgetKey
        ]);
        logAudit($_SESSION['user_id'], 'updated_widget', $widgetKey);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Widget updated'];
    } elseif ($action === 'reset_layouts') {
        $key = $_POST['dashboard_key'] ?? '';
        if ($key) {
            $db->prepare("DELETE FROM user_dashboard_layouts WHERE dashboard_key = ?")->execute([$key]);
        } else {
            $db->exec("DELETE FROM user_dashboard_layouts");
        }
        logAudit($_SESSION['user_id'], 'reset_all_layouts', $key ?: 'all');
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User layouts reset for ' . ($key ?: 'all dashboards')];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all widgets grouped by module
$allWidgets = $db->query("SELECT w.* FROM widget_definitions w ORDER BY w.module, w.category, w.name")->fetchAll();

// Widget usage count from user layouts (simple LIKE matching on JSON string)
$layoutWidgets = $db->query("SELECT layout_json FROM user_dashboard_layouts")->fetchAll(PDO::FETCH_COLUMN);
$usageCounts = [];
foreach ($layoutWidgets as $json) {
    $layout = json_decode($json, true);
    if (!empty($layout['widgets'])) {
        foreach (array_keys($layout['widgets']) as $key) {
            $usageCounts[$key] = ($usageCounts[$key] ?? 0) + 1;
        }
    }
}

$modules = [];
foreach ($allWidgets as $w) {
    $modules[$w['module']][] = $w;
}
?>
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Widget Management</h1>
            <p class="text-gray-500 text-sm mt-1">Manage dashboard widget definitions across all modules</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('resetModal').classList.remove('hidden')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm transition">
                <i class="fas fa-undo mr-1"></i>Reset Layouts
            </button>
        </div>
    </div>
</div>

<?php if (empty($modules)): ?>
<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
    <strong>No widget definitions found.</strong> Run the migration SQL to seed widget definitions.
</div>
<?php endif; ?>

<div class="space-y-6">
    <?php foreach ($modules as $module => $widgets): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-semibold text-slate-700 capitalize flex items-center gap-2">
                <i class="fas fa-puzzle-piece text-indigo-500 text-sm"></i>
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $module))) ?>
                <span class="text-xs text-slate-400 font-normal bg-slate-100 px-2 py-0.5 rounded-full"><?= count($widgets) ?> widgets</span>
            </h3>
            <a href="<?= url("api/widgets/definitions.php?module=$module") ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">
                <i class="fas fa-external-link-alt mr-1"></i>API
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase bg-gray-50">
                        <th class="px-6 py-3 font-medium">Widget</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium text-center">Size</th>
                        <th class="px-4 py-3 font-medium">Render</th>
                        <th class="px-4 py-3 font-medium">Permission</th>
                        <th class="px-4 py-3 font-medium text-center">Usage</th>
                        <th class="px-4 py-3 font-medium text-center">Status</th>
                        <th class="px-4 py-3 font-medium text-center w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($widgets as $w): ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <i class="<?= htmlspecialchars($w['icon']) ?> text-indigo-500 w-5 text-center text-sm"></i>
                                <div>
                                    <div class="font-medium text-gray-800">
                                        <span class="edit-inline cursor-pointer hover:text-indigo-600" data-key="<?= $w['widget_key'] ?>" data-field="name"><?= htmlspecialchars($w['name']) ?></span>
                                    </div>
                                    <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($w['widget_key']) ?></div>
                                    <div class="text-xs text-gray-400 truncate max-w-xs"><?= htmlspecialchars($w['description'] ?: '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-600"><?= htmlspecialchars($w['category']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            <?= $w['default_width'] ?>x<?= $w['default_height'] ?>
                            <span class="text-xs text-gray-400">(min: <?= $w['min_width'] ?>x<?= $w['min_height'] ?>)</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded <?= $w['render_type'] === 'chartjs' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                <?= $w['render_type'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($w['permission_required']): ?>
                            <span class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($w['permission_required']) ?></span>
                            <?php else: ?>
                            <span class="text-xs text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs <?= ($usageCounts[$w['widget_key']] ?? 0) > 0 ? 'text-indigo-600 font-medium' : 'text-gray-400' ?>"><?= $usageCounts[$w['widget_key']] ?? 0 ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="widget_key" value="<?= $w['widget_key'] ?>">
                                <button type="submit" class="px-2 py-1 text-xs rounded-full font-medium border transition <?= $w['status'] === 'active' ? 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100' : 'bg-red-50 border-red-200 text-red-600 hover:bg-red-100' ?>">
                                    <?= $w['status'] ?>
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="editWidget('<?= $w['widget_key'] ?>')" class="text-indigo-600 hover:text-indigo-800 text-sm p-1" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($w['widget_options']): ?>
                            <button onclick="alert(JSON.stringify(<?= htmlspecialchars($w['widget_options'], ENT_QUOTES) ?>, null, 2))" class="text-slate-400 hover:text-slate-600 text-sm p-1" title="Widget Options JSON">
                                <i class="fas fa-code"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Edit Widget</h3>
            <button onclick="closeEdit()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="widget_key" id="edit-key">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="edit-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="edit-desc" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon (Font Awesome class)</label>
                    <input type="text" name="icon" id="edit-icon" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" name="category" id="edit-category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Width</label>
                    <input type="number" name="default_width" id="edit-dw" min="1" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Height</label>
                    <input type="number" name="default_height" id="edit-dh" min="1" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Width</label>
                    <input type="number" name="min_width" id="edit-minw" min="1" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Height</label>
                    <input type="number" name="min_height" id="edit-minh" min="1" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Required Permission</label>
                <input type="text" name="permission_required" id="edit-perm" placeholder="e.g. dashboard.view" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeEdit()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Modal -->
<div id="resetModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Reset User Layouts</h3>
        <p class="text-sm text-gray-600 mb-4">This will delete all user-customized dashboard layouts for the selected dashboard. Users will see the default layout on their next visit.</p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_layouts">
            <div class="mb-4">
                <select name="dashboard_key" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All Dashboards</option>
                    <option value="main">Home Dashboard</option>
                    <option value="executive">Executive Dashboard</option>
                    <option value="helpdesk">Helpdesk Dashboard</option>
                    <option value="finance">Finance Dashboard</option>
                    <option value="leave">Leave Management Dashboard</option>
                    <option value="analytics">Analytics Dashboard</option>
                    <option value="assets">Asset Dashboard</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('resetModal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-lg" onclick="return confirm('Are you sure? This cannot be undone.')">Reset Layouts</button>
            </div>
        </form>
    </div>
</div>

<script>
function editWidget(key) {
    var rows = document.querySelectorAll('tbody tr');
    for (var i = 0; i < rows.length; i++) {
        var firstTd = rows[i].querySelector('td');
        if (!firstTd) continue;
        var nameEl = firstTd.querySelector('.edit-inline');
        if (nameEl && nameEl.dataset.key === key) {
            document.getElementById('edit-key').value = key;
            document.getElementById('edit-name').value = nameEl.textContent.trim();
            document.getElementById('edit-desc').value = rows[i].querySelector('.text-xs.text-gray-400.truncate')?.textContent.trim() || '';
            document.getElementById('edit-icon').value = firstTd.querySelector('i')?.className || 'fas fa-chart-bar';
            document.getElementById('edit-category').value = rows[i].querySelector('td:nth-child(2) span')?.textContent.trim() || '';

            var sizeText = rows[i].querySelector('td:nth-child(3)')?.textContent.trim() || '1x1';
            var parts = sizeText.split('x');
            document.getElementById('edit-dw').value = parseInt(parts[0]) || 1;
            document.getElementById('edit-dh').value = parseInt((parts[1] || '1').split('(min')[0]) || 1;

            var permText = rows[i].querySelector('td:nth-child(5)')?.textContent.trim() || '';
            document.getElementById('edit-perm').value = permText === '—' ? '' : permText;

            // Get min values from display
            var minMatch = sizeText.match(/min:\s*(\d+)x(\d+)/);
            document.getElementById('edit-minw').value = minMatch ? parseInt(minMatch[1]) : 1;
            document.getElementById('edit-minh').value = minMatch ? parseInt(minMatch[2]) : 1;

            document.getElementById('editModal').classList.remove('hidden');
            return;
        }
    }
}

function closeEdit() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
