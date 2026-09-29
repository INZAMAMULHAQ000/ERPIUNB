<?php
$pageTitle = 'Knowledge Base';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('knowledgebase.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (a.title LIKE ? OR a.content LIKE ? OR a.tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category) { $where .= " AND a.category_id = ?"; $params[] = $category; }
if ($status) { $where .= " AND a.status = ?"; $params[] = $status; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM knowledge_base_articles a $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT a.*, kbc.name as category_name, kbc.slug as category_slug, u.name as author_name
    FROM knowledge_base_articles a
    LEFT JOIN knowledge_base_categories kbc ON a.category_id = kbc.id
    LEFT JOIN users u ON a.created_by = u.id
    $where ORDER BY a.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$articles = $stmt->fetchAll();

$categories = $db->query("SELECT id, name FROM knowledge_base_categories WHERE status = 'active' ORDER BY name")->fetchAll();

$totalArticles = $db->query("SELECT COUNT(*) as c FROM knowledge_base_articles")->fetch()['c'];
$publishedArticles = $db->query("SELECT COUNT(*) as c FROM knowledge_base_articles WHERE status = 'published'")->fetch()['c'];
$draftArticles = $db->query("SELECT COUNT(*) as c FROM knowledge_base_articles WHERE status = 'draft'")->fetch()['c'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Knowledge Base</h1>
        <p class="text-gray-600">Manage help articles and documentation</p>
    </div>
    <?php if (hasPermission('knowledgebase.create')): ?>
    <a href="<?= url('modules/knowledge_base/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Article</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Articles</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalArticles ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Published</div>
        <div class="text-2xl font-bold text-green-600"><?= $publishedArticles ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Drafts</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $draftArticles ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search articles..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
                <a href="<?= url('modules/knowledge_base/index.php') ?>" class="ml-2 text-gray-600 px-4 py-2 border border-gray-200 hover:text-gray-900 px-3 py-2">Clear</a>
            </div>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Author</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Views</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($articles as $article): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($article['title']) ?></div>
                    <?php if ($article['tags']): ?>
                    <div class="text-xs text-gray-400 mt-1">
                        <?php foreach (explode(',', $article['tags']) as $tag): ?>
                        <span class="bg-gray-100 px-1.5 py-0.5 rounded mr-1"><?= sanitize(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= sanitize($article['category_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= sanitize($article['author_name']) ?></td>
                <td class="px-6 py-4">
                    <?php
                    $statusColors = ['published' => 'bg-green-100 text-green-800', 'draft' => 'bg-yellow-100 text-yellow-800', 'archived' => 'bg-gray-100 text-gray-800'];
                    $sClass = $statusColors[$article['status']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <span class="px-2 py-1 text-xs rounded <?= $sClass ?>"><?= ucfirst($article['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><i class="fas fa-eye text-gray-400 mr-1"></i><?= $article['views'] ?></td>
                <td class="px-6 py-4 text-sm text-gray-500"><?= date('M d, Y', strtotime($article['updated_at'])) ?></td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/knowledge_base/view.php?id=' . $article['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('knowledgebase.edit')): ?>
                    <a href="<?= url('modules/knowledge_base/edit.php?id=' . $article['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                    <?php if (hasPermission('knowledgebase.delete')): ?>
                    <a href="<?= url('modules/knowledge_base/delete.php?id=' . $article['id'] . '') ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete this article?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($articles)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No articles found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?' . http_build_query(array_filter(['search' => $search, 'category' => $category, 'status' => $status]))) ?>
<?php require_once '../../includes/footer.php'; ?>


