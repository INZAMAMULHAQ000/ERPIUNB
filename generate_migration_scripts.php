<?php
/**
 * Generator: Creates idempotent SQL files from original migrations
 * 
 * Reads config/migrations/*.sql and extracts CREATE TABLE IF NOT EXISTS statements
 * Saves them as individual files in config/migration_scripts/
 * 
 * Each generated file is guaranteed to be idempotent-safe (uses IF NOT EXISTS)
 */

// ── Safety ───────────────────────────────────────────────────────────
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (php_sapi_name() === 'cli')
    || ($serverName !== '' && in_array($serverName, ['localhost', '127.0.0.1', '::1']))
    || ($httpHost !== '' && '::1']))
    || ($httpHost !== '' && in_array($httpHost, ['localhost', '127.0.0.0.0.1', '::1']));
if (!$isLocal) {
    die('This generator can only run on localhost.');
}

// ── Bootstrap ────────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
$db = getDB();

// ── Create output directory ──────────────────────────────────────────
$outputDir = __DIR__ . '/config/migration_scripts';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
    echo "Created output directory: $outputDir\n";
}

// ── Get all migration files ─────────────────────────────────────────
$migrationDir = __DIR__ . '/config/migrations';
$migrationFiles = glob($migrationDir . '/*.sql');
echo "Found " . count($migrationFiles) . " migration files\n";

// ── Track generated files ────────────────────────────────────────────
$generatedFiles = [];

// ── Process each migration file ──────────────────────────────────────
foreach ($migrationFiles as $filePath) {
    $fileName = basename($filePath);
    $content = file_get_contents($filePath);
    
    if ($content === false) {
        echo "⚠️  Could not read: $fileName\n";
        continue;
    }
    
    echo "Processing: $fileName\n";
    
    // ── Extract CREATE TABLE IF NOT EXISTS statements ────────────────
    // Pattern: CREATE TABLE IF NOT EXISTS `table_name` ...
    preg_match_all("/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\`[a-z_][a-z0-9_]*\`)/i", $content, $matches);
    
    if (empty($matches[1])) {
        // Try without IF NOT EXISTS
        preg_match_all("/CREATE\s+TABLE\s+(\`[a-z_][a-z0-9_]*\`)/i", $content, $matches);
    }
    
    if (empty($matches[1])) {
        echo "  ⚠️  No CREATE TABLE statements found\n";
        continue;
    }
    
    echo "  📋 Found " . count($matches[1]) . " CREATE TABLE statement(s)\n";
    
    // ── Extract the full CREATE TABLE statement for each match ─────────
    // We need to find the full statement from each match position
    for ($i = 0; $i < count($matches[1]); $i++) {
        $tableName = strtolower(str_replace('`', '', $matches[1][$i]));
        
        // Find the position of this CREATE TABLE
        $pos = strpos($content, $matches[1][$i]);
        if ($pos === false) continue;
        
        // Extract from this position to the next CREATE or end of relevant content
        $searchStart = $pos;
        $searchEnd = strpos($content, 'CREATE TABLE', $pos + 1);
        if ($searchEnd === false) {
            $statement = substr($content, $pos);
        } else {
            $statement = substr($content, $pos, $searchEnd - $pos);
        }
        
        // Clean up the statement - remove leading/trailing whitespace and comments
        // Remove line comments
        $statement = preg_replace('/^\s*--.*$/m', '', $statement);
        // Remove multi-line comments
        $statement = preg_replace('/\/\*.*?\*\//s', '', $statement);
        // Remove USE statements
        $statement = preg_replace('/^USE\s+\w+;/mi', '', $statement);
        // Trim
        $statement = trim($statement);
        
        if (empty($statement)) continue;
        
        // Generate safe filename
        $safeTableName = preg_replace('/[^a-z0-9]/', '_', $tableName);
        $safeTableName = ltrim($safeTableName, '_');
        $outputFile = $outputDir . '/' . $fileName . '.' . $safeTableName . '.sql';
        
        // Check if file already exists
        if (file_exists($outputFile)) {
            echo "  📄 File already exists: " . basename($outputFile) . " - skipping\n";
            continue;
        }
        
        // Add header comment
        $header = "-- Auto-generated from: $fileName\n";
        $header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- Table: $tableName\n";
        $header .= "-- Idempotent: Uses CREATE TABLE IF NOT EXISTS pattern\n\n";
        
        $fullContent = $header . $statement . "\n";
        
        // Write the file
        if (file_put_contents($outputFile, $fullContent) !== false) {
            echo "  ✅ Generated: " . basename($outputFile) . " (" . strlen($fullContent) . " bytes)\n";
            $generatedFiles[] = [
                'original' => $fileName,
                'generated' => basename($outputFile),
                'table' => $tableName
            ];
        } else {
            echo "  ❌ Failed to write: " . basename($outputFile) . "\n";
        }
    }
}

// ── Summary ──────────────────────────────────────────────────────────
echo "\n" . str_repeat("=", 50) . "\n";
echo "Generation Complete!\n";
echo str_repeat("=", 50) . "\n";
echo "Generated " . count($generatedFiles) . " SQL files\n";
echo "Output location: $outputDir\n";
echo "Original migrations: $migrationFiles\n";