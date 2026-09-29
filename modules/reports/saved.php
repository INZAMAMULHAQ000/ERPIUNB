<?php
$pageTitle = 'Saved Reports';
require_once '../../includes/header.php';

if (!hasPermission('reports.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM saved_reports WHERE id=? AND created_by=?");
    $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
    logAudit($_SESSION['user_id'], 'deleted_saved_report', 'reports');
    header('Location: saved.php'); exit;
}

$stmt = $db->prepare("
    SELECT sr.*, u.name AS created_by_name
    FROM saved_reports sr
    LEFT JOIN users u ON sr.created_by=u.id
    ORDER BY sr.created_at DESC
");
$stmt->execute();
$reports = $stmt->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="index.php" class="text-blue-600 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i>Reports Center</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1">Saved Reports</h1>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">Report saved successfully.</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saved By</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($reports)): ?>
            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No saved reports yet</td></tr>
            <?php else: ?>
            <?php foreach ($reports as $r):
                $filters = json_decode($r['filters'] ?? '{}', true) ?: [];
                $qs = http_build_query(array_merge(['type' => $r['report_type']], $filters));
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-800"><?= sanitize($r['name']) ?></td>
                <td class="px-5 py-3 text-gray-600"><?= sanitize(ucwords(str_replace('_',' ',$r['report_type']))) ?></td>
                <td class="px-5 py-3 text-gray-600"><?= sanitize($r['created_by_name'] ?? '-') ?></td>
                <td class="px-5 py-3 text-gray-500"><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                <td class="px-5 py-3 text-right">
                    <a href="view.php?<?= $qs ?>" class="text-blue-600 hover:text-blue-800 mr-3" title="Open">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <a href="saved.php?delete=<?= $r['id'] ?>" class="text-red-600 hover:text-red-800"
                       onclick="return confirm('Delete this saved report?')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>
