<?php
session_start();
if (!isset($_SESSION['user_id'])) { die(json_encode(['success' => false, 'error' => 'Unauthorized'])); }
require_once dirname(__DIR__, 3) . '/config/database.php';
$db = getDB();
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? null;

    if (!$action) {
        throw new Exception('Missing action parameter');
    }

    switch ($action) {
        case 'add':
            $task_id = $_POST['task_id'] ?? null;
            $comment = $_POST['comment'] ?? null;

            if (!$task_id || !$comment) {
                throw new Exception('Missing required parameters');
            }

            $stmt = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, $_SESSION['user_id'], $comment]);

            $commentId = $db->lastInsertId();

            // Fetch the newly created comment with user name
            $stmt = $db->prepare("SELECT tc.*, u.name AS user_name FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.id = ?");
            $stmt->execute([$commentId]);
            $newComment = $stmt->fetch();

            if ($newComment) {
                $newComment['comment'] = htmlspecialchars($newComment['comment'], ENT_QUOTES, 'UTF-8');
                $newComment['user_name'] = htmlspecialchars($newComment['user_name'], ENT_QUOTES, 'UTF-8');
            }

            echo json_encode(['success' => true, 'comment' => $newComment]);
            break;

        case 'list':
            $task_id = $_POST['task_id'] ?? null;

            if (!$task_id) {
                throw new Exception('Missing task_id');
            }

            $stmt = $db->prepare("SELECT tc.*, u.name AS user_name FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.created_at ASC");
            $stmt->execute([$task_id]);
            $comments = $stmt->fetchAll();

            // Sanitize output for display
            foreach ($comments as &$c) {
                $c['comment']    = htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8');
                $c['user_name']  = htmlspecialchars($c['user_name'], ENT_QUOTES, 'UTF-8');
            }
            unset($c);

            echo json_encode($comments);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
