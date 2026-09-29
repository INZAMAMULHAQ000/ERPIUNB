<?php
/**
 * Template Designer — Create Template
 */
$pageTitle = 'Create Template';
require_once '../../includes/header.php';

if (!hasPermission('template_designer.create')) {
    die('Access Denied');
}

$db = getDB();

// Get categories
$categories = $db->query("SELECT id, name FROM template_categories ORDER BY sort_order")->fetchAll();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateName = trim($_POST['template_name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $templateType = $_POST['template_type'] ?? 'custom';
    $description = trim($_POST['description'] ?? '');
    $pageSize = $_POST['page_size'] ?? 'A4';
    $pageOrientation = $_POST['page_orientation'] ?? 'portrait';

    // CSRF check
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // Validation
    if (!$templateName) {
        $errors[] = 'Template name is required.';
    }
    if (!in_array($pageSize, ['A4', 'Letter', 'Legal'])) {
        $pageSize = 'A4';
    }
    if (!in_array($pageOrientation, ['portrait', 'landscape'])) {
        $pageOrientation = 'portrait';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/template_designer/create.php');
    }

        try {
            $db->beginTransaction();
    
            // Create template record
            $stmt = $db->prepare("
                INSERT INTO templates (category_id, template_name, template_type, description, page_size, page_orientation, status, version, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', 1, ?)
            ");
            $stmt->execute([$categoryId, $templateName, $templateType, $description, $pageSize, $pageOrientation, $_SESSION['user_id']]);
            $templateId = $db->lastInsertId();
    
            // Create initial version with empty template data
            $defaultTemplate = [
                'page' => [
                    'size' => $pageSize,
                    'orientation' => $pageOrientation,
                    'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
                ],
                'header' => ['enabled' => false, 'content' => [], 'different_first_page' => false],
                'footer' => ['enabled' => false, 'content' => [], 'different_first_page' => false],
                'body' => ['sections' => []],
                'styles' => [
                    'font_family' => 'DejaVu Sans',
                    'font_size' => 11,
                    'colors' => ['primary' => '#4f46e5', 'text' => '#1e293b', 'background' => '#ffffff'],
                    'table_styles' => ['header_bg' => '#f3f4f6', 'border_color' => '#e5e7eb', 'alternate_rows' => true],
                ],
            ];
    
            $stmt = $db->prepare("
                INSERT INTO template_versions (template_id, version_number, template_data, status, created_by)
                VALUES (?, 1, ?, 'published', ?)
            ");
            $stmt->execute([$templateId, json_encode($defaultTemplate), $_SESSION['user_id']]);
    
            // Audit log
            $stmt = $db->prepare("INSERT INTO template_history (template_id, action, new_data, performed_by) VALUES (?, 'created', ?, ?)");
            $stmt->execute([$templateId, json_encode(['template_name' => $templateName, 'template_type' => $templateType]), $_SESSION['user_id']]);
            logAudit($_SESSION['user_id'], 'create_template', 'template_designer');
    
            $db->commit();
    
            flash('success', 'Template created successfully. Now design your template.', 'success');
            to("edit.php?id=$templateId");
            exit;
    
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    


    if (!empty($errors)) {
        flash('error', implode(' ', $errors), 'error');
    }
}
?>

<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Create Template</h1>
            <p class="text-sm text-slate-500 mt-1">Create a new document template and start designing</p>
        </div>
        <a href="index.php" class="text-sm text-slate-500 hover:text-slate-700">
            <i class="fas fa-arrow-left mr-1"></i> Back to Templates
        </a>
    </div>

    <!-- Form -->
    <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <!-- Template Name -->
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" name="template_name" value="<?= sanitize($old['template_name'] ?? '') ?>" required
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="e.g., Standard Invoice Template">
        </div>

        <!-- Category & Type row -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                <select name="category_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($old['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Template Type <span class="text-red-500">*</span></label>
                <select name="template_type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <?php foreach (['invoice', 'leave_report', 'employee_report', 'purchase_report', 'salary_slip', 'quotation', 'offer_letter', 'experience_letter', 'purchase_order', 'delivery_note', 'stock_report', 'attendance_report', 'report', 'custom'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($old['template_type'] ?? 'custom') === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Brief description of this template's purpose..."><?= sanitize($old['description'] ?? '') ?></textarea>
        </div>

        <!-- Page Settings -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Page Size</label>
                <select name="page_size" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="A4" <?= ($old['page_size'] ?? 'A4') === 'A4' ? 'selected' : '' ?>>A4 (210 × 297 mm)</option>
                    <option value="Letter" <?= ($old['page_size'] ?? '') === 'Letter' ? 'selected' : '' ?>>Letter (216 × 279 mm)</option>
                    <option value="Legal" <?= ($old['page_size'] ?? '') === 'Legal' ? 'selected' : '' ?>>Legal (216 × 356 mm)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Orientation</label>
                <select name="page_orientation" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="portrait" <?= ($old['page_orientation'] ?? 'portrait') === 'portrait' ? 'selected' : '' ?>>Portrait</option>
                    <option value="landscape" <?= ($old['page_orientation'] ?? '') === 'landscape' ? 'selected' : '' ?>>Landscape</option>
                </select>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center gap-3 pt-3 border-t border-slate-200">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors shadow-sm font-medium">
                <i class="fas fa-save mr-2"></i> Create Template
            </button>
            <a href="index.php" class="px-4 py-2.5 text-slate-600 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>