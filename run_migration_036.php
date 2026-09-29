<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();
$sql = file_get_contents(__DIR__ . '/config/migrations/036_ai_observability_f23.sql');

if ($sql === false || trim($sql) === '') {
    die("Migration file is empty or unreadable.\n");
}

try {
    $db->exec($sql);
    echo "Migration 036 completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration 036 failed: " . $e->getMessage() . "\n";
    exit(1);
}

$tables = [
    'ai_observability_requests',
    'ai_observability_spans',
    'ai_observability_feedback',
    'ai_observability_feedback_reviews',
    'ai_observability_eval_datasets',
    'ai_observability_eval_cases',
    'ai_observability_eval_runs',
    'ai_observability_eval_results',
    'ai_observability_experiments',
    'ai_observability_benchmarks',
    'ai_observability_benchmark_results',
    'ai_prompt_versions',
    'ai_observability_findings',
    'ai_observability_reports',
    'ai_observability_daily_aggregates',
];

foreach ($tables as $table) {
    $exists = $db->query("SHOW TABLES LIKE '{$table}'")->rowCount() > 0;
    echo $table . ': ' . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}
