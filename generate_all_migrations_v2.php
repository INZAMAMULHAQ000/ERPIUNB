<?php
/**
 * Generator v2: Creates idempotent SQL files from ALL migration files
 * Fixed regex to match CREATE TABLE IF NOT EXISTS patterns properly
 */

// ── Safety ───────────────────────────────────────────────────────────
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (php_sapi_name() === 'cli')
    || ($serverName !== '' && in_array($serverName, ['localhost', '127.0.0.1', '::1']))
    || ($httpHost !== '' && in_array($httpHost, ['localhost', '127.0.0.1', '::1']));
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
    
    // ── Extract CREATE TABLE IF NOT EXISTS statements (fixed regex) ────
    // Match: CREATE TABLE IF NOT EXISTS `table_name` or CREATE TABLE IF NOT EXISTS table_name
    preg_match_all("/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(`?[a-z_][a-z0-9_]*`?)?\s*\(/i", $content, $matches);
    
    if (empty($matches[0])) {
        echo "  ⚠️  No CREATE TABLE IF NOT EXISTS statements found with pattern 1\n";
        // Try alternative pattern
        preg_match_all("/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+/i", $content, $altMatches);
        if (empty($altMatches[0])) {
            echo "  ⚠️  No CREATE TABLE IF NOT EXISTS statements at all\n";
            continue;
        }
        $matchCount = count($altMatches[0]);
        echo "  Found $matchCount CREATE TABLE IF NOT EXISTS keywords\n";
        
        // Extract table names manually for each match
        for ($i = 0; $i < $matchCount; $i++) {
            // Find the table name after IF NOT EXISTS
            $keywordPos = strpos($content, $altMatches[0][$i]);
            $afterKeyword = substr($content, $keywordPos + strlen($altMatches[0][$i]));
            // Get table name - letters, numbers, underscores, backticks
            if (preg_match("/`([a-z_][a-z0-9_]*)`\s*\(/i", $afterKeyword, $tblMatch)) {
                $tableName = strtolower($tblMatch[1]);
            } elseif (preg_match("/([a-z_][a-z0-9_]*)\s*\(/i", $afterKeyword, $tblMatch)) {
                $tableName = strtolower($tblMatch[1]);
            } else {
                continue;
            }
            
            // Find the full CREATE TABLE statement
            $startPos = strpos($content, $altMatches[0][$i]);
            $endPos = strpos($content, 'CREATE TABLE', $startPos + 1);
            if ($endPos === false) {
                $statement = substr($content, $startPos);
            } else {
                $statement = substr($content, $startPos, $endPos - $startPos);
            }
            
            // Clean up
            $statement = preg_replace('/^\s*--.*$/m', '', $statement);
            $statement = preg_replace('/\/\*.*?\*\//s', '', $statement);
            $statement = preg_replace('/^USE\s+\w+;/mi', '', $statement);
            $statement = trim($statement);
            
            if (empty($statement)) continue;
            
            $safeTableName = preg_replace('/[^a-z0-9]/', '_', $tableName);
            $safeTableName = ltrim($safeTableName, '_');
            $outputFile = $outputDir . '/' . $fileName . '.' . $safeTableName . '.sql';
            
            if (file_exists($outputFile)) {
                echo "  📄 File already exists: " . basename($outputFile) . " - skipping\n";
                continue;
            }
            
            $header = "-- Auto-generated from: $fileName\n";
            $header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $header .= "-- Table: $tableName\n";
            $header .= "-- Idempotent: Uses CREATE TABLE IF NOT EXISTS pattern\n\n";
            
            $fullContent = $header . $statement . "\n";
            
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
        continue;
    }
    
    $matchCount = count($matches[0]);
    echo "  Found $matchCount CREATE TABLE IF NOT EXISTS statements\n";
    
    // Extract table names and full statements for each match
    for ($i = 0; $i < $matchCount; $i++) {
        $keywordPos = strpos($content, $matches[0][$i]);
        $afterKeyword = substr($content, $keywordPos + strlen($matches[0][$i]));
        
        // Get table name
        if (preg_match("/`([a-z_][a-z0-9_]*)`\s*\(/i", $afterKeyword, $tblMatch)) {
            $tableName = strtolower($tblMatch[1]);
        } elseif (preg_match("/([a-z_][a-z0-9_]*)\s*\(/i", $afterKeyword, $tblMatch)) {
            $tableName = strtolower($tblMatch[1]);
        } else {
            continue;
        }
        
        // Find the full CREATE TABLE statement
        $searchStart = $keywordPos;
        $searchEnd = strpos($content, 'CREATE TABLE', $keywordPos + 1);
        if ($searchEnd === false) {
            $statement = substr($content, $searchStart);
        } else {
            $statement = substr($content, $searchStart, $searchEnd - $searchStart);
        }
        
        // Clean up
        $statement = preg_replace('/^\s*--.*$/m', '', $statement);
        $statement = preg_replace('/\/\*.*?\*\//s', '', $statement);
        $statement = preg_replace('/^USE\s+\w+;/mi', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) continue;
        
        $safeTableName = preg_replace('/[^a-z0-9]/', '_', $tableName);
        $safeTableName = ltrim($safeTableName, '_');
        $outputFile = $outputDir . '/' . $fileName . '.' . $safeTableName . '.sql';
        
        if (file_exists($outputFile)) {
            echo "  📄 File already exists: " . basename($outputFile) . " - skipping\n";
            continue;
        }
        
        $header = "-- Auto-generated from: $fileName\n";
        $header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- Table: $tableName\n";
        $header .= "-- Idempotent: Uses CREATE TABLE IF NOT EXISTS pattern\n\n";
        
        $fullContent = $header . $statement . "\n";
        
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
echo "Original migrations scanned: " . count($migrationFiles) . "\n";

// ── List generated files ─────────────────────────────────────────────
echo "\nGenerated files:\n";
foreach (glob($outputDir . '/*.sql') as $f) {
    echo "  - " . basename($f) . "\n";
}