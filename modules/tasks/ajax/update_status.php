<?php
session_start();
if (!isset($_SESSION['user_id'])) { die(json_encode(['success' => false, 'error' => 'Unauthorized'])); }
require_once dirname(__DIR__, 3) . '/config/database.php';
$db = getDB();
header('Content-Type: application/json');

try {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $field   = $_POST['field'] ?? '';
    $value   = $_POST['value'] ?? '';

    if (!$task_id || !$field) {
        throw new Exception('Missing required parameters');
    }

    // Allowed fields for update
    $allowedFields = ['status', 'priority', 'start_date', 'due_date', 'estimated_hours', 'task_description'];
    if (!in_array($field, $allowedFields)) {
        throw new Exception('Invalid field: ' . $field);
    }

    // Validate status values
    if ($field === 'status') {
        $validStatuses = ['todo', 'in_progress', 'review', 'testing', 'completed', 'blocked'];
        if (!in_array($value, $validStatuses)) {
            throw new Exception('Invalid status value');
        }
    }

    // Validate priority values
    if ($field === 'priority') {
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($value, $validPriorities)) {
            throw new Exception('Invalid priority value');
        }
    }

    // Get old value before updating
    $stmt = $db->prepare("SELECT $field FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $oldRow = $stmt->fetch();

    if (!$oldRow) {
        throw new Exception('Task not found');
    }

    $oldValue = $oldRow[$field];

    // Update the field
    $stmt = $db->prepare("UPDATE tasks SET $field = ? WHERE id = ?");
    $stmt->execute([$value ?: null, $task_id]);

    // Log activity
    $activityType = $field === 'status' ? 'status_change'
                  : ($field === 'priority' ? 'priority_change'
                  : 'field_update');
    $stmt = $db->prepare("INSERT INTO task_activities (task_id, user_id, activity_type, field_name, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$task_id, $_SESSION['user_id'], $activityType, $field, $oldValue, $value]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
