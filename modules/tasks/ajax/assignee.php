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
            $task_id     = $_POST['task_id']     ?? null;
            $employee_id = $_POST['employee_id'] ?? null;

            if (!$task_id || !$employee_id) {
                throw new Exception('Missing required parameters');
            }

            $stmt = $db->prepare("INSERT IGNORE INTO task_assignees (task_id, employee_id) VALUES (?, ?)");
            $stmt->execute([$task_id, $employee_id]);

            echo json_encode(['success' => true]);
            break;

        case 'remove':
            $task_id     = $_POST['task_id']     ?? null;
            $employee_id = $_POST['employee_id'] ?? null;

            if (!$task_id || !$employee_id) {
                throw new Exception('Missing required parameters');
            }

            $stmt = $db->prepare("DELETE FROM task_assignees WHERE task_id = ? AND employee_id = ?");
            $stmt->execute([$task_id, $employee_id]);

            echo json_encode(['success' => true]);
            break;

        case 'list':
            $task_id = $_POST['task_id'] ?? null;

            if (!$task_id) {
                throw new Exception('Missing task_id');
            }

            $stmt = $db->prepare("SELECT e.id, e.first_name, e.last_name FROM task_assignees ta JOIN employees e ON ta.employee_id = e.id WHERE ta.task_id = ?");
            $stmt->execute([$task_id]);
            $assignees = $stmt->fetchAll();

            // Sanitize output for display
            foreach ($assignees as &$a) {
                $a['first_name'] = htmlspecialchars($a['first_name'], ENT_QUOTES, 'UTF-8');
                $a['last_name']  = htmlspecialchars($a['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
            }
            unset($a);

            echo json_encode($assignees);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
