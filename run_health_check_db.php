<?php
require_once __DIR__ . '/config/database.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function listModuleDirectories(string $root): array
{
    $modules = [];
    foreach (glob($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if ($name !== '' && $name[0] !== '.') {
            $modules[] = $dir;
        }
    }
    $modules = array_values(array_unique($modules));
    sort($modules, SORT_NATURAL | SORT_FLAG_CASE);
    return $modules;
}

function isIgnoredPath(string $path): bool
{
    $needle = str_replace('\\', '/', strtolower($path));
    foreach (['/vendor/', '/node_modules/', '/storage/', '/tmp/', '/cache/', '/tests/', '/test-results/'] as $segment) {
        if (str_contains($needle, $segment)) {
            return true;
        }
    }
    return false;
}

function collectFiles(string $moduleDir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $path = $fileInfo->getPathname();
        if (isIgnoredPath($path)) {
            continue;
        }
        $files[] = $path;
    }

    return $files;
}

function extractSqlTablesFromFile(string $file): array
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'sql', 'txt', 'md'], true)) {
        return [];
    }

    $contents = @file_get_contents($file);
    if ($contents === false || $contents === '') {
        return [];
    }

    $tables = [];
    $patterns = [
        '/\b(?:FROM|JOIN|INTO|UPDATE|DELETE\s+FROM|TABLE)\s+[`"\[]?([a-zA-Z0-9_]+)[`"\]]?/i',
        '/\b(?:CREATE\s+TABLE|ALTER\s+TABLE)\s+[`"\[]?([a-zA-Z0-9_]+)[`"\]]?/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $table) {
                $table = trim($table, "`\"[]");
                if ($table !== '' && strlen($table) > 1) {
                    $tables[] = strtolower($table);
                }
            }
        }
    }

    return array_values(array_unique($tables));
}

function collectModuleTables(array $files): array
{
    $tables = [];
    foreach ($files as $file) {
        foreach (extractSqlTablesFromFile($file) as $table) {
            $tables[] = $table;
        }
    }
    return array_values(array_unique($tables));
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function summarizeModule(string $moduleDir, ?PDO $db): array
{
    $module = basename($moduleDir);
    $files = collectFiles($moduleDir);
    $referencedTables = collectModuleTables($files);

    $existingTables = [];
    $missingTables = [];
    if ($db instanceof PDO && $referencedTables) {
        foreach ($referencedTables as $table) {
            if (tableExists($db, $table)) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }
    }

    $databaseOk = null;
    if ($db instanceof PDO) {
        $databaseOk = !empty($referencedTables) ? empty($missingTables) : null;
    }

    return [
        'module' => $module,
        'path' => str_replace('\\', '/', $moduleDir),
        'database_ok' => $databaseOk,
        'referenced_tables' => $referencedTables,
        'existing_tables' => $existingTables,
        'missing_tables' => $missingTables,
    ];
}

$db = getDB();
$moduleDirs = listModuleDirectories(__DIR__);
$modules = [];

foreach ($moduleDirs as $moduleDir) {
    $modules[] = summarizeModule($moduleDir, $db);
}

$missingModules = array_filter($modules, fn($m) => $m['database_ok'] === false);

echo "Database missing modules: " . count($missingModules) . "\n\n";

foreach ($missingModules as $m) {
    echo "Module: " . $m['module'] . "\n";
    echo "  Missing tables: " . implode(', ', $m['missing_tables']) . "\n\n";
}

// Also show all unique missing tables
$allMissing = [];
foreach ($missingModules as $m) {
    foreach ($m['missing_tables'] as $t) {
        $allMissing[$t] = true;
    }
}
echo "\n=== ALL UNIQUE MISSING TABLES ===\n";
echo count($allMissing) . " tables\n";
foreach (array_keys($allMissing) as $table) {
    echo "  - $table\n";
}
