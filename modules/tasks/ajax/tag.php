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
            $task_id  = $_POST['task_id']  ?? null;
            $tag_name = $_POST['tag_name'] ?? null;
            $tag_color = $_POST['tag_color'] ?? '#6366f1';

            if (!$task_id || !$tag_name) {
                throw new Exception('Missing required parameters');
            }

            $stmt = $db->prepare("INSERT INTO task_tags (task_id, tag_name, tag_color) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, $tag_name, $tag_color]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'delete':
            $tag_id = $_POST['tag_id'] ?? null;

            if (!$tag_id) {
                throw new Exception('Missing tag_id');
            }

            $stmt = $db->prepare("DELETE FROM task_tags WHERE id = ?");
            $stmt->execute([$tag_id]);

            echo json_encode(['success' => true]);
            break;

        case 'list':
            $task_id = $_POST['task_id'] ?? null;

            if (!$task_id) {
                throw new Exception('Missing task_id');
            }

            $stmt = $db->prepare("SELECT * FROM task_tags WHERE task_id = ? ORDER BY tag_name");
            $stmt->execute([$task_id]);
            $tags = $stmt->fetchAll();

            // Sanitize output for display
            foreach ($tags as &$t) {
                $t['tag_name']  = htmlspecialchars($t['tag_name'], ENT_QUOTES, 'UTF-8');
                $t['tag_color'] = htmlspecialchars($t['tag_color'], ENT_QUOTES, 'UTF-8');
            }
            unset($t);

            echo json_encode($tags);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
