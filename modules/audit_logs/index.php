<?php
$pageTitle = 'Audit Logs';
require_once '../../includes/header.php';

// This page exposes every user's actions, IPs and timestamps, so it was never
// meant to be readable without the audit permission.
if (!hasPermission('auditlogs.view')) { die('Access Denied'); }

$db = getDB();
$logs = $db->query("
    SELECT al.*, u.name as user_name 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 200
")->fetchAll();
?>
<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800">Audit Logs</h1>
    <p class="text-gray-600">System activity log</p>
</div>
<div class="bg-white rounded-lg shadow">
    <div class="p-6">
        <table class="w-full">
            <thead>
                <tr class="text-left text-gray-500 text-sm border-b">
                    <th class="pb-3">User</th>
                    <th class="pb-3">Action</th>
                    <th class="pb-3">Module</th>
                    <th class="pb-3">IP Address</th>
                    <th class="pb-3">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="border-b">
                    <td class="py-3"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                    <td class="py-3"><?= htmlspecialchars($log['action']) ?></td>
                    <td class="py-3"><?= htmlspecialchars($log['module']) ?></td>
                    <td class="py-3 text-gray-500"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                    <td class="py-3 text-gray-500"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>