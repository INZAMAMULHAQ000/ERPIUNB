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
                    $lineNum = 1;
                    $lineStart = 0;
                    foreach (explode("\n", $contents) as $line) {
                        if ($match[1] >= $lineStart && $match[1] < $lineStart + strlen($line)) {
                            break;
                        }
                        $lineStart += strlen($line) + 1;
                        $lineNum++;
                    }
                    $tables[] = [
                        'table' => strtolower($table),
                        'file' => $file,
                        'line' => $lineNum,
                        'context' => trim($line),
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
            if (!in_array($r['table'], ['permissions', 'role_permissions', 'user_roles', 'roles', 'ai_agent_definitions', 'ai_conversations'])) {
                echo "File: " . basename($r['file']) . " Line: " . $r['line'] . "\n";
                echo "  Table: " . $r['table'] . "\n";
                echo "  Context: " . $r['context'] . "\n\n";
            }
        }
    }
}
