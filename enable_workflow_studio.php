<?php
/**
 * Enable Workflow Studio Kill Switch
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/modules/ai/bootstrap.php';

$db = getDB();

// Initialize kill switches
AIKillSwitchService::initialize($db);

// Check current status
$stmt = $db->prepare("SELECT * FROM ai_kill_switches WHERE switch_key = 'workflow_studio'");
$stmt->execute();
$switch = $stmt->fetch();

if ($switch) {
    echo "Current status of workflow_studio: " . ($switch['enabled'] ? 'ENABLED' : 'DISABLED') . "\n";

    if (!$switch['enabled']) {
        // Enable it (using user_id 1 as admin)
        $result = AIKillSwitchService::toggle($db, 'workflow_studio', true, 1, 'Enabled for testing');
        echo "Toggle result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    }
} else {
    echo "workflow_studio kill switch not found\n";
}

// Verify
$stmt->execute();
$switch = $stmt->fetch();
echo "Final status: " . ($switch['enabled'] ? 'ENABLED' : 'DISABLED') . "\n";
