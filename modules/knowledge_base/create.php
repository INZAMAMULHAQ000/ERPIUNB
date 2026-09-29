<?php
$pageTitle = 'Create Knowledge Base Article';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('knowledgebase.create')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$categories = $db->query("SELECT id, name FROM knowledge_base_categories WHERE status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF token'); }

    $data = [
        'category_id' => $_POST['category_id'] ?? null,
        'title' => trim($_POST['title'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
        'status' => $_POST['status'] ?? 'draft'
    ];

    $errors = validate($data, [
        'title' => 'required|min:5|max:255',
        'content' => 'required|min:20'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/knowledge_base/create.php');
    }

    
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title']), '-'));
        $checkStmt = $db->prepare("SELECT id FROM knowledge_base_articles WHERE slug = ?");
        $checkStmt->execute([$slug]);
        if ($checkStmt->fetch()) { $slug .= '-' . time(); }
    
        $stmt = $db->prepare("INSERT INTO knowledge_base_articles (category_id, title, slug, content, tags, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['category_id'] ?: null, $data['title'], $slug, $data['content'], $data['tags'], $data['status'], $_SESSION['user_id']]);
    
        logAudit($_SESSION['user_id'], 'create_kb_article', 'knowledgebase');
        flash('success', 'Article created successfully');
        to('http://localhost/ERP/modules/knowledge_base/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/knowledge_base/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Articles</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Article</h2>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="knowledgeBaseForm" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-600">*</span></labe>
                <input type="text" name="title" value="<?= old('title') ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Article title">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">No Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="' . $cat['id'] . '" <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="draft" <?= old('status', 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= old('status') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= old('status') === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Content <span class="text-red-600">*</span></labe>
                <textarea name="content" rows="12" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 font-mono text-sm" placeholder="Write your article content here... HTML is supported"><?= old('content') ?></textarea>
                <p class="text-xs text-gray-400 mt-1">HTML content is supported for rich formatting</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                <input type="text" name="tags" value="<?= old('tags') ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="comma, separated, tags">
            </div>
        </div>
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Create Article</button>
            <a href="<?= url('modules/knowledge_base/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    $("#knowledgeBaseForm").validate({

        rules: {

            title: {
                required: true,
                minlength: 5,
                maxlength: 255
            },

            content: {
                required: true,
                minlength: 20
            }

        },

        messages: {

            title: {
                required: "Title is required",
                minlength: "Title must be at least 5 characters",
                maxlength: "Title cannot exceed 255 characters"
            },

            content: {
                required: "Content is required",
                minlength: "Content must be at least 20 characters"
            }

        },

        errorElement: "p",

        errorClass: "text-red-600 text-sm mt-1",

        highlight: function (element) {
            $(element).addClass("border-red-500");
        },

        unhighlight: function (element) {
            $(element).removeClass("border-red-500");
        },

        errorPlacement: function (error, element) {
            error.insertAfter(element);
        }

    });

});
</script>

<?php require_once '../../includes/footer.php'; ?>