<?php
$pageTitle = 'View Ticket';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('tickets.view')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT t.*, tc.category_name, tc.category_code,
           e.first_name, e.last_name, e.email as emp_email,
           cl.company_name as client_name, cl.email as client_email,
           sla.sla_name, sla.response_time_hours, sla.resolution_time_hours
    FROM tickets t
    LEFT JOIN ticket_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    LEFT JOIN clients cl ON t.client_id = cl.id
    LEFT JOIN slas sla ON t.sla_id = sla.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { flash('error', 'Ticket not found'); to('http://localhost/ERP/modules/tickets/index.php'); }

$replies = $db->prepare("
    SELECT tr.*, u.name as user_name, u.email as user_email
    FROM ticket_replies tr
    LEFT JOIN users u ON tr.replied_by = u.id
    WHERE tr.ticket_id = ?
    ORDER BY tr.created_at ASC
");
$replies->execute([$id]);
$replies = $replies->fetchAll();

$attachments = $db->prepare("
    SELECT ta.*, u.name as uploaded_by_name
    FROM ticket_attachments ta
    LEFT JOIN users u ON ta.uploaded_by = u.id
    WHERE ta.ticket_id = ?
    ORDER BY ta.created_at DESC
");
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();

$internalNotes = $db->prepare("
    SELECT tin.*, u.name as user_name
    FROM ticket_internal_notes tin
    LEFT JOIN users u ON tin.user_id = u.id
    WHERE tin.ticket_id = ?
    ORDER BY tin.created_at DESC
");
$internalNotes->execute([$id]);
$internalNotes = $internalNotes->fetchAll();

$assignmentHistory = $db->prepare("
    SELECT ta.*, ef.first_name as from_first, ef.last_name as from_last,
           et.first_name as to_first, et.last_name as to_last,
           u.name as assigned_by_name
    FROM ticket_assignments ta
    LEFT JOIN employees ef ON ta.assigned_from = ef.id
    LEFT JOIN employees et ON ta.assigned_to = et.id
    LEFT JOIN users u ON ta.assigned_by = u.id
    WHERE ta.ticket_id = ?
    ORDER BY ta.created_at ASC
");
$assignmentHistory->execute([$id]);
$assignmentHistory = $assignmentHistory->fetchAll();

$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$allUsers = $db->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

$slaHoursRemaining = null;
$slaBreached = false;
if ($ticket['resolution_time_hours'] && !in_array($ticket['status'], ['resolved', 'closed'])) {
    $created = new DateTime($ticket['created_at']);
    $now = new DateTime();
    $elapsed = ($now->getTimestamp() - $created->getTimestamp()) / 3600;
    $slaHoursRemaining = max(0, $ticket['resolution_time_hours'] - $elapsed);
    $slaBreached = $elapsed > $ticket['resolution_time_hours'];
}

$priorityColors = ['low' => 'bg-gray-100 text-gray-700', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
$statusColors = ['open' => 'bg-blue-100 text-blue-800', 'in_progress' => 'bg-yellow-100 text-yellow-800', 'waiting_client' => 'bg-purple-100 text-purple-800', 'resolved' => 'bg-green-100 text-green-800', 'closed' => 'bg-gray-100 text-gray-800', 'reopened' => 'bg-red-100 text-red-800'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF token'); }

    if ($_POST['action'] === 'reply') {
        if (!hasPermission('tickets.reply')) { die('Access Denied'); }
        $message = trim($_POST['message'] ?? '');
        $replyType = $_POST['reply_type'] ?? 'public';
        if (empty($message)) { flash('error', 'Reply message cannot be empty'); }
        else {
            $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, replied_by, reply_type, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $_SESSION['user_id'], $replyType, $message]);

            if ($ticket['status'] === 'open' && $replyType === 'public') {
                $upd = $db->prepare("UPDATE tickets SET status = 'in_progress', first_response_at = NOW() WHERE id = ?");
                $upd->execute([$id]);
            }

            if ($_FILES['attachments']['name'][0] ?? false) {
                $replyId = $db->lastInsertId();
                $uploadDir = dirname(dirname(__DIR__)) . '/uploads/tickets/';
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/zip', 'text/plain', 'text/csv', 'application/octet-stream'];
                $maxSize = 10 * 1024 * 1024;

                foreach ($_FILES['attachments']['name'] as $key => $fileName) {
                    if ($_FILES['attachments']['error'][$key] === 0) {
                        $fileType = $_FILES['attachments']['type'][$key];
                        $fileSize = $_FILES['attachments']['size'][$key];
                        $tmpName = $_FILES['attachments']['tmp_name'][$key];

                        if ($fileSize <= $maxSize) {
                            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                            $safeName = uniqid('att_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
                            $destPath = $uploadDir . $safeName;

                            if (move_uploaded_file($tmpName, $destPath)) {
                                $attStmt = $db->prepare("INSERT INTO ticket_attachments (ticket_id, reply_id, uploaded_by, file_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $attStmt->execute([$id, $replyId, $_SESSION['user_id'], $fileName, 'uploads/tickets/' . $safeName, $fileSize, $fileType]);
                            }
                        }
                    }
                }
            }

            logAudit($_SESSION['user_id'], 'reply_ticket', 'tickets');
            flash('success', 'Reply added');
        }
    }

    if ($_POST['action'] === 'internal_note') {
        $note = trim($_POST['note'] ?? '');
        if (empty($note)) { flash('error', 'Note cannot be empty'); }
        else {
            $mentions = $_POST['mentioned_users'] ?? [];
            $mentionedIds = !empty($mentions) ? implode(',', $mentions) : null;
            $stmt = $db->prepare("INSERT INTO ticket_internal_notes (ticket_id, user_id, note, mentioned_user_ids) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $_SESSION['user_id'], $note, $mentionedIds]);
            logAudit($_SESSION['user_id'], 'add_internal_note', 'tickets');
            flash('success', 'Internal note added');
        }
    }

    if ($_POST['action'] === 'assign') {
        if (!hasPermission('tickets.assign')) { die('Access Denied'); }
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
        $reason = trim($_POST['assign_reason'] ?? '');
        if ($assignedTo) {
            $oldAssignee = $ticket['assigned_to'];
            $stmt = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$assignedTo, $id]);

            $assignStmt = $db->prepare("INSERT INTO ticket_assignments (ticket_id, assigned_from, assigned_to, assigned_by, reason) VALUES (?, ?, ?, ?, ?)");
            $assignStmt->execute([$id, $oldAssignee, $assignedTo, $_SESSION['user_id'], $reason]);

            logAudit($_SESSION['user_id'], 'assign_ticket', 'tickets');
            flash('success', 'Ticket reassigned');
        }
    }

    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['new_status'] ?? '';
        $validStatuses = ['open', 'in_progress', 'waiting_client', 'resolved', 'closed', 'reopened'];
        if (in_array($newStatus, $validStatuses)) {
            $updateFields = "status = ?";
            $updateParams = [$newStatus];
            if ($newStatus === 'resolved') { $updateFields .= ", resolved_at = NOW()"; }
            if ($newStatus === 'closed') { $updateFields .= ", closed_at = NOW()"; }
            $updateParams[] = $id;

            $stmt = $db->prepare("UPDATE tickets SET $updateFields WHERE id = ?");
            $stmt->execute($updateParams);

            if ($newStatus === 'closed' && !hasPermission('tickets.close')) { die('Access Denied'); }

            logAudit($_SESSION['user_id'], 'status_change_' . $newStatus, 'tickets');
            flash('success', 'Ticket status updated to ' . str_replace('_', ' ', ucfirst($newStatus)));
        }
    }

    redirect(url("modules/tickets/view.php?id=$id"));
    exit;
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="<?= url('modules/tickets/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Tickets</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-2">
            <span class="font-mono text-blue-600"><?= sanitize($ticket['ticket_number']) ?></span>
            - <?= sanitize($ticket['subject']) ?>
        </h1>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('tickets.edit')): ?>
        <a href="<?= url('modules/tickets/edit.php?id=' . $ticket['id'] . '') ?>" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition"><i class="fas fa-edit mr-1"></i>Edit</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-gray-600"><?= nl2br(sanitize($ticket['description'])) ?></p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-4">
                <span class="px-2 py-1 text-xs rounded font-medium <?= $priorityColors[$ticket['priority']] ?>"><?= ucfirst($ticket['priority']) ?></span>
                <span class="px-2 py-1 text-xs rounded font-medium <?= $statusColors[$ticket['status']] ?>"><?= str_replace('_', ' ', ucfirst($ticket['status'])) ?></span>
                <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700"><?= sanitize($ticket['category_name'] ?? 'Uncategorized') ?></span>
                <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700"><i class="fas fa-<?= $ticket['source'] === 'email' ? 'envelope' : ($ticket['source'] === 'phone' ? 'phone' : ($ticket['source'] === 'internal' ? 'user' : 'globe')) ?> mr-1"></i><?= ucfirst($ticket['source']) ?></span>
                <?php if ($ticket['sla_breached']): ?>
                <span class="px-2 py-1 text-xs rounded bg-red-600 text-white"><i class="fas fa-exclamation-triangle mr-1"></i>SLA Breached</span>
                <?php endif; ?>
            </div>
            <div class="text-xs text-gray-400 mt-3">
                Created: <?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?>
                <?php if ($ticket['client_name']): ?> | Client: <?= sanitize($ticket['client_name']) ?><?php endif; ?>
            </div>
        </div>

        <?php if ($slaHoursRemaining !== null): ?>
        <div class="bg-white rounded-lg shadow p-4 mb-6 <?= $slaBreached ? 'border-l-4 border-red-500 bg-red-50' : 'border-l-4 border-green-500' ?>">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-700">SLA: <?= sanitize($ticket['sla_name'] ?? 'N/A') ?></span>
                    <p class="text-xs text-gray-500">Resolution: <?= $ticket['resolution_time_hours'] ?>h | Response: <?= $ticket['response_time_hours'] ?>h</p>
                </div>
                <div class="text-right">
                    <?php if ($slaBreached): ?>
                    <span class="text-red-600 font-bold"><i class="fas fa-exclamation-circle"></i> BREACHED</span>
                    <?php else: ?>
                    <span class="text-green-600 font-bold"><?= number_format($slaHoursRemaining, 1) ?>h remaining</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4"><i class="fas fa-comments mr-2"></i>Conversation</h3>

            <?php if (empty($replies)): ?>
            <p class="text-gray-500 text-center py-4">No replies yet</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($replies as $reply): ?>
                <div class="border rounded-lg p-4 <?= $reply['reply_type'] === 'internal' ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-bold">
                                <?= strtoupper(substr($reply['user_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <span class="font-medium text-sm"><?= sanitize($reply['user_name']) ?></span>
                                <?php if ($reply['reply_type'] === 'internal'): ?>
                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded bg-yellow-200 text-yellow-800">Internal</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-xs text-gray-400"><?= date('M d, Y H:i', strtotime($reply['created_at'])) ?></span>
                    </div>
                    <div class="text-gray-700 text-sm"><?= nl2br(sanitize($reply['message'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (hasPermission('tickets.reply')): ?>
            <form method="POST" enctype="multipart/form-data" class="mt-6 pt-4 border-t">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="reply">
                <div class="mb-3">
                    <label class="flex items-center gap-4">
                        <label class="flex items-center gap-1">
                            <input type="radio" name="reply_type" value="public" checked class="text-blue-600">
                            <span class="text-sm">Public Reply</span>
                        </label>
                        <label class="flex items-center gap-1">
                            <input type="radio" name="reply_type" value="internal" class="text-yellow-600">
                            <span class="text-sm">Internal Note</span>
                        </label>
                    </label>
                </div>
                <textarea name="message" rows="4" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Type your reply..."></textarea>
                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Attachments</label>
                    <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.zip,.txt,.csv,.log" class="w-full text-sm">
                    <p class="text-xs text-gray-400 mt-1">Images, PDFs, ZIP, TXT, CSV, LOG (max 10MB each)</p>
                </div>
                <button type="submit" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-paper-plane mr-1"></i>Send Reply</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($internalNotes)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4"><i class="fas fa-sticky-note mr-2 text-yellow-500"></i>Internal Notes</h3>
            <div class="space-y-3">
                <?php foreach ($internalNotes as $note): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium"><?= sanitize($note['user_name']) ?></span>
                        <span class="text-xs text-gray-400"><?= date('M d, Y H:i', strtotime($note['created_at'])) ?></span>
                    </div>
                    <p class="text-sm text-gray-700"><?= nl2br(sanitize($note['note'])) ?></p>
                    <?php if ($note['mentioned_user_ids']): ?>
                    <div class="mt-1 text-xs text-blue-600">
                        <i class="fas fa-at mr-1"></i>Mentioned: <?= sanitize($note['mentioned_user_ids']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($attachments)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4"><i class="fas fa-paperclip mr-2"></i>Attachments (<?= count($attachments) ?>)</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach ($attachments as $att): ?>
                <div class="border rounded p-3 flex items-center gap-2">
                    <i class="fas fa-<?= in_array(pathinfo($att['file_name'], PATHINFO_EXTENSION), ['jpg','jpeg','png','gif','webp']) ? 'file-image text-green-500' : (pathinfo($att['file_name'], PATHINFO_EXTENSION) === 'pdf' ? 'file-pdf text-red-500' : 'file text-gray-500') ?> text-xl"></i>
                    <div class="flex-1 min-w-0">
                        <a href="<?= url('modules/tickets/download.php?file=' . $att['id'] . '') ?>" class="text-sm text-blue-600 hover:text-blue-900 truncate block"><?= sanitize($att['file_name']) ?></a>
                        <span class="text-xs text-gray-400"><?= number_format($att['file_size'] / 1024, 1) ?> KB</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Ticket Details</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Status</span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="update_status">
                        <select name="new_status" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                            <?php foreach (['open', 'in_progress', 'waiting_client', 'resolved', 'closed', 'reopened'] as $s): ?>
                            <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">Priority</span><span class="px-2 py-0.5 rounded text-xs font-medium <?= $priorityColors[$ticket['priority']] ?>"><?= ucfirst($ticket['priority']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Category</span><span><?= sanitize($ticket['category_name'] ?? '-') ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Source</span><span><?= ucfirst($ticket['source']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Client</span><span><?= sanitize($ticket['client_name'] ?? '-') ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Assigned To</span><span><?= sanitize(trim($ticket['first_name'] . ' ' . $ticket['last_name'])) ?: '<span class="text-red-500">Unassigned</span>' ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Due Date</span><span><?= $ticket['due_date'] ? date('M d, Y H:i', strtotime($ticket['due_date'])) : '-' ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Created</span><span><?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></span></div>
                <?php if ($ticket['resolved_at']): ?><div class="flex justify-between"><span class="text-gray-500">Resolved</span><span><?= date('M d, Y H:i', strtotime($ticket['resolved_at'])) ?></span></div><?php endif; ?>
            </div>
        </div>

        <?php if (hasPermission('tickets.assign')): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Reassign Ticket</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="assign">
                <select name="assigned_to" class="w-full px-3 py-2 border rounded mb-2 text-sm">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $ticket['assigned_to'] == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="assign_reason" rows="2" placeholder="Reason for reassignment..." class="w-full px-3 py-2 border rounded mb-2 text-sm"></textarea>
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm"><i class="fas fa-user-plus mr-1"></i>Reassign</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($assignmentHistory)): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Assignment History</h3>
            <div class="space-y-2 text-sm">
                <?php foreach ($assignmentHistory as $ah): ?>
                <div class="border-l-2 border-blue-300 pl-3 py-1">
                    <p class="text-gray-700">
                        <?= sanitize($ah['assigned_by_name']) ?> assigned to
                        <strong><?= sanitize($ah['to_first'] . ' ' . $ah['to_last']) ?></strong>
                    </p>
                    <?php if ($ah['reason']): ?><p class="text-xs text-gray-500"><?= sanitize($ah['reason']) ?></p><?php endif; ?>
                    <p class="text-xs text-gray-400"><?= date('M d, H:i', strtotime($ah['created_at'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Add Internal Note</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="internal_note">
                <textarea name="note" rows="3" required class="w-full px-3 py-2 border rounded mb-2 text-sm" placeholder="Staff-only note..."></textarea>
                <div class="mb-2">
                    <label class="text-xs text-gray-500">Mention users</label>
                    <select name="mentioned_users[]" multiple class="w-full px-2 py-1 border rounded text-sm">
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition text-sm"><i class="fas fa-sticky-note mr-1"></i>Add Note</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>


