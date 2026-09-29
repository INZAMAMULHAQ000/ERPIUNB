<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

require_once __DIR__ . '/config/migrations/runner.php';

$migrationDir = __DIR__ . '/config/migrations';
$files = scandir($migrationDir);
$sqlFiles = [];

foreach ($files as $file) {
    if (is_file($migrationDir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        $sqlFiles[] = $file;
    }
}

sort($sqlFiles);

echo "Running " . count($sqlFiles) . " migration files...\n\n";

$totalStatements = 0;
$skipped = 0;

foreach ($sqlFiles as $file) {
    $filePath = $migrationDir . '/' . $file;
    echo "Processing: $file ... ";
    $result = applySql($db, $filePath, true);
    if ($result > 0) {
        echo "OK ($result statements executed)\n";
        $totalStatements += $result;
    } else {
        echo "SKIP (all statements already applied or empty)\n";
        $skipped++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total migration files: " . count($sqlFiles) . "\n";
echo "Files with new statements: " . (count($sqlFiles) - $skipped) . "\n";
echo "Files skipped (already applied): $skipped\n";
echo "Total SQL statements executed: $totalStatements\n";
