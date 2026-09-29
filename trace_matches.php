<?php
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
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $table = trim($match[0], "`\"[]");
                if ($table !== '' && strlen($table) > 1) {
                    $tables[] = [
                        'table' => strtolower($table),
                        'file' => $file,
                        'offset' => $match[1],
                        'context' => substr($contents, max(0, $match[1] - 40), 80),
                    ];
                }
            }
        }
    }

    return $tables;
}

// Check a specific module
$moduleDir = __DIR__ . '/modules/ai';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir));
foreach ($files as $file) {
    if ($file->isFile()) {
        $results = extractSqlTablesFromFile($file->getPathname());
        foreach ($results as $r) {
            echo "File: " . basename($r['file']) . "\n";
            echo "  Table: " . $r['table'] . "\n";
            echo "  Context: " . trim($r['context']) . "\n\n";
        }
    }
}
