<?php
require_once __DIR__ . '/config/database.php';

function extractSqlTablesFromFile(string $file): array
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'sql', 'txt', 'md'], true)) return [];

    $contents = @file_get_contents($file);
    if ($contents === false || $contents === '') return [];

    $tables = [];
    $patterns = [
        '/\b(?:FROM|JOIN|INTO|UPDATE|DELETE\s+FROM|TABLE)\s+[`"\[]?([a-zA-Z0-9_]+)[`"\]]?/i',
        '/\b(?:CREATE\s+TABLE|ALTER\s+TABLE)\s+[`"\[]?([a-zA-Z0-9_]+)[`"\]]?/i',
    ];

    $skip = [
        'class', 'style', 'table', 'database', 'sql', 'php', 'mysql', 'ui', 'ai',
        'thead', 'tbody', 'tr', 'td', 'th', 'html', 'css', 'js', 'json',
        'the', 'and', 'for', 'with', 'not', 'or', 'but', 'from', 'this', 'that',
        'have', 'has', 'had', 'can', 'will', 'should', 'would', 'could', 'may',
        'is', 'am', 'are', 'was', 'were', 'be', 'been', 'do', 'does', 'did',
        'user', 'date', 'time', 'text', 'data', 'file', 'status', 'name', 'type',
        'config', 'setting', 'settings', 'event', 'events', 'action', 'actions',
        'log', 'logs', 'source', 'sources', 'version', 'versions', 'field', 'fields',
        'title', 'titles', 'review', 'reviews', 'result', 'results', 'content',
        'form', 'forms', 'input', 'inputs', 'button', 'buttons', 'link', 'links',
        'header', 'headers', 'footer', 'footers', 'section', 'sections', 'item',
        'items', 'group', 'groups', 'account', 'accounts', 'bank', 'cash', 'credit',
        'debit', 'receipt', 'budget', 'transfer', 'line', 'project', 'location',
        'vendor', 'client', 'customer', 'supplier', 'sales', 'tax', 'voucher',
        'leave', 'role', 'template', 'column', 'row', 'rows', 'pixel', 'pixels',
        'controlled', 'actual', 'maximum', 'minimum', 'average', 'total',
        'count', 'sum', 'avg', 'min', 'max', 'distinct', 'order',
        'where', 'having', 'limit', 'offset', 'join', 'left', 'right', 'inner', 'outer',
        'on', 'as', 'select', 'insert', 'update', 'delete', 'create', 'alter', 'drop',
        'index', 'constraint', 'foreign', 'primary', 'unique', 'default', 'null',
        'exists', 'union', 'intersect', 'except',
        'information_schema', 'performance_schema', 'sys',
        'company', 'companies', 'domains', 'authorized', 'multiple', 'subject',
        'authenticated', 'frontend', 'exactly', 'retrieval', 'execution',
        'search', 'session', 'classification', 'rule', 'native', 'word', 'csv',
        'spreadsheet', 'per', 'documents', 'same', 'insight', 'condition',
        'capability', 'connector', 'unanswered', 'frequency', 'internal', 'cache',
        'calculating', 'meaningful', 'reviewed', 'actor', 'expected', 'entity',
        'severity', 'summary', 'operations', 'uniqueness', 'private', 'preference',
        'depth', 'allowlisted', 'common', 'preferred', 'target', 'config_data',
        'audio', 'goal', 'workspace', 'post', 'cost', 'safety', 'individual',
        'kill', 'inline', 'system', 'url', 'provider', 'suggestions', 'step',
        'steps', 'command', 'configuration', 'registry', 'intent', 'supported',
        'pdf', 'overlapping', 'chunks', 'progress', 'checkpoint', 'pause',
        'natural', 'context', 'contains', 'text', 'negative', 'baseline', 'anomaly',
        'refresh', 'schedule', 'now', 'description', 'workflow', 'verified',
        'critical', 'products', 'customers', 'real', 'exists', 'inferring',
        'generic', 'conversation', 'routing', 'follow', 'emotional', 'quotations',
        'support_tickets', 'suppliers', 'sales_orders', 'structured',
        'company_domains', 'connection', 'server', 'agent', 'discovery',
        'observability', 'retention', 'policy', 'filename', 'being', 'is_active',
        'finding', 'request_count', 'section', 'setting_value', 'which', 'must',
        'raw', 'approved', 'via', 'th', 'td', 'tr', 'rendered', 'html_renderer',
        'json', 'styles', 'pixels', 'header', 'rows', 'objectives', 'urls',
        'providers', 'runs', 'commands', 'configurations', 'registries', 'model',
        'connections', 'servers', 'checkpoints', 'pauses', 'contexts',
        'discoveries', 'schedules', 'descriptions', 'workflows', 'findings',
        'insights', 'conditions', 'capabilities', 'connectors', 'titles',
        'frequencies', 'sources', 'reviews', 'actions', 'preferences', 'depths',
        'goals', 'workspaces', 'posts', 'costs', 'safeties', 'kills', 'systems',
        'accounts', 'banks', 'budgets', 'transfers', 'lines', 'projects',
        'locations', 'vendors', 'clients', 'customers', 'suppliers', 'sales',
        'taxes', 'vouchers', 'roles', 'templates', 'columns', 'headers',
        'f26', 'f25', 'f24', 'f27', 'llm',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $table = trim($match[0], "`\"[]");
                $lower = strtolower($table);
                if (
                    $table !== ''
                    && strlen($table) > 3
                    && !in_array($lower, $skip, true)
                ) {
                    // Find the line number
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
                        'table' => $lower,
                        'file' => basename($file),
                        'line' => $lineNum,
                        'context' => trim($line),
                    ];
                }
            }
        }
    }

    return $tables;
}

// Check ai and finance modules
foreach (['ai', 'finance'] as $module) {
    $moduleDir = __DIR__ . '/modules/' . $module;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir));
    foreach ($files as $file) {
        if ($file->isFile()) {
            $results = extractSqlTablesFromFile($file->getPathname());
            foreach ($results as $r) {
                echo basename($r['file']) . ":" . $r['line'] . " => " . $r['table'] . "\n";
                echo "  " . trim($r['context']) . "\n\n";
            }
        }
    }
}
