<?php
$pageTitle = 'Scheduled Reports';
require_once '../../includes/header.php';

if (!hasPermission('scheduled_reports.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();

// Toggle active
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $stmt = $db->prepare("UPDATE scheduled_reports SET is_active = NOT is_active WHERE id=?");
    $stmt->execute([$_GET['toggle']]);
    logAudit($_SESSION['user_id'], 'toggled_scheduled_report', 'scheduled_reports');
    header('Location: index.php'); exit;
}

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (hasPermission('scheduled_reports.delete')) {
        $db->prepare("DELETE FROM scheduled_reports WHERE id=?")->execute([$_GET['delete']]);
        logAudit($_SESSION['user_id'], 'deleted_scheduled_report', 'scheduled_reports');
    }
    header('Location: index.php'); exit;
}

$reports = $db->query("
    SELECT sr.*, u.name AS created_by_name
    FROM scheduled_reports sr
    LEFT JOIN users u ON sr.created_by=u.id
    ORDER BY sr.created_at DESC
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Scheduled Reports</h1>
        <p class="text-gray-600">Automate report generation</p>
    </div>
    <?php if (hasPermission('scheduled_reports.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm">
        <i class="fas fa-plus mr-1"></i>New Schedule
    </a>
    <?php endif; ?>
</div>

<?php $flash = getFlash('success'); if ($flash): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Report Type</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequency</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipients</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Run</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($reports)): ?>
            <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No scheduled reports yet</td></tr>
            <?php else: ?>
            <?php foreach ($reports as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-800"><?= sanitize($r['name']) ?></td>
                <td class="px-5 py-3 text-gray-600"><?= sanitize(ucwords(str_replace('_',' ',$r['report_type']))) ?></td>
                <td class="px-5 py-3">
                    <?php
                    $freqColors = ['daily'=>'bg-blue-100 text-blue-800','weekly'=>'bg-purple-100 text-purple-800','monthly'=>'bg-green-100 text-green-800'];
                    ?>
                    <span class="px-2 py-1 text-xs rounded <?= $freqColors[$r['frequency']] ?? 'bg-gray-100' ?>">
                        <?= ucfirst($r['frequency']) ?>
                    </span>
                </td>
                <td class="px-5 py-3 text-gray-600 max-w-xs truncate"><?= sanitize($r['recipients'] ?? '-') ?></td>
                <td class="px-5 py-3 text-gray-600"><?= $r['next_run'] ? date('Y-m-d', strtotime($r['next_run'])) : '-' ?></td>
                <td class="px-5 py-3">
                    <span class="px-2 py-1 text-xs rounded <?= $r['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                        <?= $r['is_active'] ? 'Active' : 'Paused' ?>
                    </span>
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="index.php?toggle=<?= $r['id'] ?>" class="text-yellow-600 hover:text-yellow-800 mr-3" title="Toggle">
                        <i class="fas fa-<?= $r['is_active'] ? 'pause' : 'play' ?>"></i>
                    </a>
                    <?php if (hasPermission('scheduled_reports.delete')): ?>
                    <a href="index.php?delete=<?= $r['id'] ?>" class="text-red-600 hover:text-red-800"
                       onclick="return confirm('Delete this schedule?')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>
