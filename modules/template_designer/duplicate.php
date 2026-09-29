<?php
/**
 * Template Designer — Duplicate Template
 */
$pageTitle = 'Duplicate Template';
require_once '../../includes/header.php';

if (!hasPermission('template_designer.create')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    flash('error', 'Source template not found.', 'error');
    to('index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['template_name'] ?? '');
    if (!$newName) {
        flash('error', 'Template name is required.', 'error');
    } else {
        try {
            $db->beginTransaction();

            // Create new template
            $stmt = $db->prepare("
                INSERT INTO templates (category_id, template_name, template_type, description, page_size, page_orientation, status, version, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', 1, ?)
            ");
            $stmt->execute([
                $source['category_id'], $newName, $source['template_type'],
                $source['description'], $source['page_size'], $source['page_orientation'],
                $_SESSION['user_id']
            ]);
            $newId = $db->lastInsertId();

            // Copy latest published version
            $stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? AND status = 'published' ORDER BY version_number DESC LIMIT 1");
            $stmt->execute([$id]);
            $version = $stmt->fetch();

            if ($version) {
                $stmt = $db->prepare("
                    INSERT INTO template_versions (template_id, version_number, template_data, placeholder_definitions, status, created_by)
                    VALUES (?, 1, ?, ?, 'published', ?)
                ");
                $stmt->execute([$newId, $version['template_data'], $version['placeholder_definitions'], $_SESSION['user_id']]);
            }

            // Audit log
            $stmt = $db->prepare("INSERT INTO template_history (template_id, action, new_data, performed_by) VALUES (?, 'duplicated', ?, ?)");
            $stmt->execute([$newId, json_encode(['duplicated_from' => $source['template_name'], 'source_id' => $id]), $_SESSION['user_id']]);
            logAudit($_SESSION['user_id'], 'duplicate_template', 'template_designer');

            $db->commit();

            flash('success', 'Template duplicated successfully.', 'success');
            to("edit.php?id=$newId");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            flash('error', 'Database error: ' . $e->getMessage(), 'error');
        }
    }
}
?>

<div class="max-w-lg mx-auto mt-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-xl font-bold text-slate-800 mb-2">Duplicate Template</h2>
        <p class="text-sm text-slate-500 mb-6">
            Creating a copy of: <strong><?= sanitize($source['template_name']) ?></strong>
        </p>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">New Template Name <span class="text-red-500">*</span></label>
                <input type="text" name="template_name" value="<?= sanitize($source['template_name']) ?> (Copy)" required
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    <i class="fas fa-copy mr-2"></i> Duplicate
                </button>
                <a href="index.php" class="px-4 py-2.5 text-slate-600 hover:text-slate-800">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>