<?php
/**
 * Run migration 029 - AI Workflows F18
 */
require_once __DIR__ . '/config/database.php';

$sql = file_get_contents(__DIR__ . '/config/migrations/029_ai_workflows_f18.sql');
$db = getDB();

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !str_starts_with($s, '--')
);

$count = 0;
foreach ($statements as $statement) {
    if (!empty(trim($statement))) {
        try {
            $db->exec($statement);
            $count++;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "Statement: $statement\n";
        }
    }
}

echo "Migration 029 completed: $count statements executed\n";

// Verify tables exist
$tables = ['ai_workflows', 'ai_workflow_versions', 'ai_workflow_runs', 'ai_workflow_run_nodes', 'ai_workflow_approvals', 'ai_workflow_templates'];
foreach ($tables as $table) {
    $exists = $db->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
    echo "  Table $table: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}
