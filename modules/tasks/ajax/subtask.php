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
            $title   = $_POST['title']   ?? null;

            if (!$task_id || !$title) {
                throw new Exception('Missing required parameters');
            }

            // Determine position
            $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 AS next_pos FROM task_subtasks WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $row = $stmt->fetch();
            $position = $row['next_pos'];

            $stmt = $db->prepare("INSERT INTO task_subtasks (task_id, title, position) VALUES (?, ?, ?)");
            $stmt->execute([$task_id, $title, $position]);

            // Return updated counts
            $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(is_completed) AS done FROM task_subtasks WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $counts = $stmt->fetch();

            echo json_encode([
                'success'  => true,
                'subtotal'  => (int)$counts['total'],
                'subdone'   => (int)$counts['done']
            ]);
            break;

        case 'toggle':
            $subtask_id   = $_POST['subtask_id'] ?? $_POST['id'] ?? null;
            $is_completed = $_POST['is_completed'] ?? null;

            if (!$subtask_id || $is_completed === null) {
                throw new Exception('Missing required parameters');
            }

            if ($is_completed == 1) {
                $stmt = $db->prepare("UPDATE task_subtasks SET is_completed = 1, completed_at = NOW() WHERE id = ?");
            } else {
                $stmt = $db->prepare("UPDATE task_subtasks SET is_completed = 0, completed_at = NULL WHERE id = ?");
            }
            $stmt->execute([$subtask_id]);

            // Get task_id for counts
            $stmt = $db->prepare("SELECT task_id FROM task_subtasks WHERE id = ?");
            $stmt->execute([$subtask_id]);
            $sub = $stmt->fetch();

            if ($sub) {
                $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(is_completed) AS done FROM task_subtasks WHERE task_id = ?");
                $stmt->execute([$sub['task_id']]);
                $counts = $stmt->fetch();

                echo json_encode([
                    'success'  => true,
                    'subtotal'  => (int)$counts['total'],
                    'subdone'   => (int)$counts['done']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Subtask not found']);
            }
            break;

        case 'delete':
            $subtask_id = $_POST['subtask_id'] ?? $_POST['id'] ?? null;

            if (!$subtask_id) {
                throw new Exception('Missing subtask_id');
            }

            $stmt = $db->prepare("DELETE FROM task_subtasks WHERE id = ?");
            $stmt->execute([$subtask_id]);

            echo json_encode(['success' => true]);
            break;

        case 'reorder':
            $ids = $_POST['ids'] ?? null;

            if (!$ids) {
                throw new Exception('Missing ids parameter');
            }

            $idArray = explode(',', $ids);
            foreach ($idArray as $index => $id) {
                $id = trim($id);
                if ($id !== '') {
                    $stmt = $db->prepare("UPDATE task_subtasks SET position = ? WHERE id = ?");
                    $stmt->execute([$index, $id]);
                }
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
