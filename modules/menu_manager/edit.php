<?php
$pageTitle = 'Edit Menu Item';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('settings.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$item = $db->prepare("SELECT * FROM sidebar_menu WHERE id = ?");
$item->execute([$id]);
$item = $item->fetch();

if (!$item) {
    die('Menu item not found.');
}

$sections = $db->query("
    SELECT DISTINCT section, section_order
    FROM sidebar_menu
    WHERE is_active = 1
    ORDER BY section_order ASC
")->fetchAll();

$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section       = $_POST['section'] ?? '';
    $section_order = (int)($_POST['section_order'] ?? 0);
    $label         = trim($_POST['label'] ?? '');
    $icon          = trim($_POST['icon'] ?? 'fas fa-link');
    $route         = trim($_POST['route'] ?? '');
    $permission    = trim($_POST['permission'] ?? '');
    $sort_order    = (int)($_POST['sort_order'] ?? 0);
    $is_active     = isset($_POST['is_active']) ? 1 : 0;
    $is_indented   = isset($_POST['is_indented']) ? 1 : 0;
    $parent_id     = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    // Prevent self-referencing parent
    if ($parent_id === $id) {
        $parent_id = null;
    }

    if (empty($label)) {
        $errors[] = 'Label is required.';
    }
    if (empty($route)) {
        $errors[] = 'Route is required.';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/menu_manager/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("
        UPDATE sidebar_menu
        SET parent_id = ?, section = ?, section_order = ?, label = ?, icon = ?,
            route = ?, permission = ?, sort_order = ?, is_active = ?, is_indented = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $parent_id,
        $section ?: null,
        $section_order,
        $label,
        $icon,
        $route,
        $permission ?: null,
        $sort_order,
        $is_active,
        $is_indented,
        $id,
    ]);
    logAudit($_SESSION['user_id'], 'update', 'sidebar_menu');
    flash('success', 'Menu item updated successfully');
    to('modules/menu_manager/index.php');
}
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Edit Menu Item</h1>
    <p class="text-gray-600">Update sidebar navigation entry</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php foreach ($errors as $e): ?>
            <p><?= sanitize($e) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Label <span class="text-red-600">*</span></labe>
                <input type="text" name="label" value="<?= sanitize(old('label', $item['label'])) ?>" required
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Icon (FontAwesome class)</label>
                <input type="text" name="icon" value="<?= sanitize(old('icon', $item['icon'])) ?>"
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">e.g. <code>fas fa-users</code></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                <input type="text" name="section" value="<?= sanitize(old('section', $item['section'] ?? '')) ?>"
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                       list="section-list">
                <datalist id="section-list">
                    <?php foreach ($sections as $s): ?>
                        <?php if ($s['section']): ?>
                            <option value="<?= sanitize($s['section']) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </datalist>
                <p class="text-xs text-gray-400 mt-1">Leave empty for top-level (no section header)</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Route <span class="text-red-600">*</span></labe>
                <input type="text" name="route" value="<?= sanitize(old('route', $item['route'])) ?>" required
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">e.g. <code>modules/users/index.php</code></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Permission (optional)</label>
                <input type="text" name="permission" value="<?= sanitize(old('permission', $item['permission'] ?? '')) ?>"
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">e.g. <code>users.view</code> — leave empty for public</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Section Order</label>
                <input type="number" name="section_order" value="<?= old('section_order', $item['section_order']) ?>"
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order (within section)</label>
                <input type="number" name="sort_order" value="<?= old('sort_order', $item['sort_order']) ?>"
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Parent Item (for nesting)</label>
                <select name="parent_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">— None —</option>
                    <?php
                    $topItems = $db->query("SELECT id, label, section FROM sidebar_menu WHERE id != $id AND parent_id IS NULL ORDER BY section_order, sort_order")->fetchAll();
                    foreach ($topItems as $ti) {
                        $sec = $ti['section'] ? ('[' . $ti['section'] . '] ') : '';
                        $selected = ((int)old('parent_id', $item['parent_id']) === (int)$ti['id']) ? 'selected' : '';
                        echo '<option value="' . $ti['id'] . '" ' . $selected . '>' . sanitize($sec . $ti['label']) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-6">
            <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" value="1"
                    <?= old('is_active', $item['is_active']) ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Active</span>
            </label>
            <label class="inline-flex items-center">
                <input type="checkbox" name="is_indented" value="1"
                    <?= old('is_indented', $item['is_indented']) ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Indented (sub-item appearance)</span>
            </label>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-save mr-2"></i>Update
            </button>
            <a href="index.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition">
                Cancel
            </a>
        </div>
    </form>
</div>
<?php require_once '../../includes/footer.php'; ?>
