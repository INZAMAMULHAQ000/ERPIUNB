<?php
$pageTitle = 'View Article';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('knowledgebase.view')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, kbc.name as category_name, u.name as author_name
    FROM knowledge_base_articles a
    LEFT JOIN knowledge_base_categories kbc ON a.category_id = kbc.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) { flash('error', 'Article not found'); to('http://localhost/ERP/modules/knowledge_base/index.php'); }

$db->prepare("UPDATE knowledge_base_articles SET views = views + 1 WHERE id = ?")->execute([$id]);
?>

<div class="mb-6">
    <a href="<?= url('modules/knowledge_base/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Articles</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <?php if ($article['category_name']): ?>
            <span class="text-sm text-blue-600 bg-blue-50 px-2 py-1 rounded"><?= sanitize($article['category_name']) ?></span>
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-gray-800 mt-2"><?= sanitize($article['title']) ?></h1>
        </div>
        <?php if (hasPermission('knowledgebase.edit')): ?>
        <a href="<?= url('modules/knowledge_base/edit.php?id=' . $article['id'] . '') ?>" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition"><i class="fas fa-edit mr-1"></i>Edit</a>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-4 text-sm text-gray-500 mb-6 pb-4 border-b">
        <span><i class="fas fa-user mr-1"></i><?= sanitize($article['author_name']) ?></span>
        <span><i class="fas fa-calendar mr-1"></i><?= date('M d, Y', strtotime($article['created_at'])) ?></span>
        <span><i class="fas fa-eye mr-1"></i><?= $article['views'] ?> views</span>
        <span class="px-2 py-0.5 rounded text-xs <?= $article['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>"><?= ucfirst($article['status']) ?></span>
    </div>

    <div class="prose max-w-none text-gray-700">
        <?= $article['content'] ?>
    </div>

    <?php if ($article['tags']): ?>
    <div class="mt-6 pt-4 border-t">
        <span class="text-sm text-gray-500 mr-2">Tags:</span>
        <?php foreach (explode(',', $article['tags']) as $tag): ?>
        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-sm mr-1"><?= sanitize(trim($tag)) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>


