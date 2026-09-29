<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();
$sql = file_get_contents(__DIR__ . '/config/migrations/035_ai_integrations_f22.sql');

if ($sql === false || trim($sql) === '') {
    die("Migration file is empty or unreadable.\n");
}

try {
    $db->exec($sql);
    echo "Migration 035 completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration 035 failed: " . $e->getMessage() . "\n";
    exit(1);
}

$tables = [
    'ai_connectors',
    'ai_connector_capabilities',
    'ai_integration_connections',
    'ai_integration_credentials',
    'ai_connector_rest_endpoints',
    'ai_webhook_definitions',
    'ai_webhook_deliveries',
    'ai_sync_mappings',
    'ai_sync_jobs',
    'ai_sync_job_rows',
    'ai_integration_activity',
    'ai_integration_health',
    'ai_integration_policies',
    'ai_incoming_webhook_events',
];

foreach ($tables as $table) {
    $exists = $db->query("SHOW TABLES LIKE '{$table}'")->rowCount() > 0;
    echo $table . ': ' . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}
