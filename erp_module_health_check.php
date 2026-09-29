<?php
declare(strict_types=1);

$pageTitle = 'ERP Module Health Check';
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

function detectFrontendFiles(array $files, string $moduleDir): array
{
    $frontend = [];
    $frontendNames = [
        'index.php', 'dashboard.php', 'create.php', 'edit.php', 'view.php',
        'list.php', 'details.php', 'report.php', 'reports.php', 'allocate.php',
        'login.php', 'logout.php', 'manage.php',
    ];
    $backendDirs = ['includes', 'services', 'config', 'tools', 'api', 'lib', 'classes', 'controllers', 'jobs'];

    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file, strlen($moduleDir) + 1));
        $parts = explode('/', $relative);
        $base = strtolower(basename($file));
        $dir = strtolower($parts[0] ?? '');
        $pathLower = strtolower($relative);

        $looksBackend = in_array($dir, $backendDirs, true)
            || str_contains($pathLower, '/includes/')
            || str_contains($pathLower, '/services/')
            || str_contains($pathLower, '/config/')
            || str_contains($pathLower, '/tools/')
            || str_contains($pathLower, '/api/')
            || $base === 'bootstrap.php'
            || str_ends_with($base, 'service.php')
            || str_ends_with($base, 'helper.php')
            || str_ends_with($base, 'registry.php')
            || str_ends_with($base, 'validator.php');

        $isClearlyFrontend = in_array($base, $frontendNames, true)
            || preg_match('/\/(pages?|views?|ui|public|dashboard|reports?)\//i', $relative)
            || preg_match('/\/(index|dashboard|create|edit|view|list|details|report|allocate|login|logout|manage)\.php$/i', $relative);

        if (!$looksBackend && $isClearlyFrontend) {
            $frontend[] = $relative;
        }
    }

    return array_values(array_unique($frontend));
}

function detectBackendFiles(array $files, string $moduleDir): array
{
    $backend = [];
    $backendMarkers = ['includes', 'services', 'config', 'tools', 'api', 'lib', 'classes', 'controllers'];

    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file, strlen($moduleDir) + 1));
        $parts = explode('/', $relative);
        $base = strtolower(basename($file));
        $dir = strtolower($parts[0] ?? '');
        $pathLower = strtolower($relative);

        if (in_array($dir, $backendMarkers, true)
            || str_contains($pathLower, '/includes/')
            || str_contains($pathLower, '/services/')
            || str_contains($pathLower, '/config/')
            || str_contains($pathLower, '/tools/')
            || str_contains($pathLower, '/api/')
            || $base === 'bootstrap.php'
            || str_ends_with($base, 'service.php')
            || str_ends_with($base, 'helper.php')
            || str_ends_with($base, 'registry.php')
            || str_ends_with($base, 'validator.php')
            || str_ends_with($base, 'repository.php')
            || str_ends_with($base, 'provider.php')
        ) {
            $backend[] = $relative;
        }
    }

    return array_values(array_unique($backend));
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

    if ($ext === 'php') {
        $contents = preg_replace('/\/\/[^\n]*/', '', $contents);
        $contents = preg_replace('/\/\*.*?\*\*/s', '', $contents);
        $contents = preg_replace('/(["\'])(?:(?!\1|\\\\).|\\\\.)*\1/', '', $contents);
    }

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
        'capability_key', 'connector_key', 'preferred_language', 'voice',
        'budget_amount', 'claim', 'financial', 'when', 'mapping', 'trial',
        'operating', 'investing', 'financing', 'loans', 'posted',
        'drill', 'down', 'from', 'to', 'individual', 'account', 'transactions',
        'cash', 'flows', 'activities', 'proceeds', 'ledger', 'entries',
        'year', 'details', 'update', 'create', 'delete', 'edit', 'view',
        'list', 'dashboard', 'report', 'export', 'import', 'settings',
        'preferences', 'notification', 'notifications', 'message', 'messages',
        'task', 'tasks', 'project', 'projects', 'client', 'clients',
        'vendor', 'vendors', 'invoice', 'invoices', 'payment', 'payments',
        'expense', 'expenses', 'asset', 'assets', 'employee', 'employees',
        'department', 'departments', 'designation', 'designations',
        'role', 'roles', 'permission', 'permissions', 'user', 'users',
        'audit', 'logs', 'log', 'history', 'attachment', 'attachments',
        'comment', 'comments', 'reply', 'replies', 'note', 'notes',
        'document', 'documents', 'file', 'files', 'image', 'images',
        'category', 'categories', 'type', 'types', 'status', 'statuses',
        'priority', 'priorities', 'severity', 'severities', 'level', 'levels',
        'stage', 'stages', 'phase', 'phases', 'step', 'steps',
        'milestone', 'milestones', 'deliverable', 'deliverables',
        'requirement', 'requirements', 'specification', 'specifications',
        'test', 'tests', 'testing', 'qa', 'deployment', 'releases',
        'version', 'versions', 'revision', 'revisions', 'build',
        'environment', 'environments', 'region', 'regions', 'zone', 'zones',
        'cluster', 'clusters', 'node', 'nodes', 'server', 'servers',
        'instance', 'instances', 'container', 'containers', 'pod', 'pods',
        'service', 'services', 'endpoint', 'endpoints', 'api', 'apis',
        'integration', 'integrations', 'connector', 'connectors',
        'provider', 'providers', 'model', 'models', 'agent', 'agents',
        'workflow', 'workflows', 'automation', 'automations',
        'intelligence', 'insight', 'insights', 'analytics', 'analysis',
        'report', 'reports', 'dashboard', 'dashboards', 'widget', 'widgets',
        'chart', 'charts', 'graph', 'graphs', 'visualization', 'visualizations',
        'metric', 'metrics', 'measure', 'measures', 'indicator', 'indicators',
        'kpi', 'kpis', 'target', 'targets', 'goal', 'goals',
        'objective', 'objectives', 'strategy', 'strategies', 'plan', 'plans',
        'initiative', 'initiatives', 'program', 'programs', 'portfolio',
        'portfolios', 'investment', 'investments', 'fund', 'funds',
        'budget', 'budgets', 'forecast', 'forecasts', 'projection',
        'projections', 'scenario', 'scenarios', 'assumption', 'assumptions',
        'risk', 'risks', 'issue', 'issues', 'problem', 'problems',
        'incident', 'incidents', 'ticket', 'tickets', 'request', 'requests',
        'change', 'changes', 'release', 'releases', 'deployment',
        'configuration', 'configurations', 'setting', 'settings',
        'parameter', 'parameters', 'variable', 'variables', 'constant',
        'constants', 'expression', 'expressions', 'formula', 'formulas',
        'calculation', 'calculations', 'computation', 'computations',
        'validation', 'validations', 'verification', 'verifications',
        'authentication', 'authorization', 'authorisation', 'permission',
        'permissions', 'access', 'identity', 'identities', 'profile',
        'profiles', 'account', 'accounts', 'credential', 'credentials',
        'token', 'tokens', 'key', 'keys', 'secret', 'secrets',
        'certificate', 'certificates', 'signature', 'signatures',
        'encryption', 'decryption', 'cipher', 'ciphers', 'hash', 'hashes',
        'salt', 'salts', 'nonce', 'nonces', 'iv', 'ivs',
        'algorithm', 'algorithms', 'protocol', 'protocols', 'standard',
        'standards', 'specification', 'specifications', 'requirement',
        'requirements', 'guideline', 'guidelines', 'policy', 'policies',
        'procedure', 'procedures', 'process', 'processes', 'workflow',
        'workflows', 'pipeline', 'pipelines', 'stage', 'stages',
        'gate', 'gates', 'review', 'reviews', 'approval', 'approvals',
        'rejection', 'rejections', 'escalation', 'escalations',
        'notification', 'notifications', 'alert', 'alerts', 'reminder',
        'reminders', 'escalation', 'escalations', 'assignment',
        'assignments', 'delegation', 'delegations', 'ownership',
        'responsibility', 'responsibilities', 'accountability',
        'stakeholder', 'stakeholders', 'sponsor', 'sponsors',
        'customer', 'customers', 'client', 'clients', 'partner',
        'partners', 'vendor', 'vendors', 'supplier', 'suppliers',
        'contractor', 'contractors', 'consultant', 'consultants',
        'employee', 'employees', 'contract', 'contracts', 'agreement',
        'agreements', 'sla', 'slas', 'ola', 'olas', 'policy',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $table) {
                $table = trim($table, "`\"[]");
                $lower = strtolower($table);
                if (
                    $table !== ''
                    && strlen($table) > 3
                    && !in_array($lower, $skip, true)
                ) {
                    $tables[] = $lower;
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
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'sql') {
            foreach (extractSqlTablesFromFile($file) as $table) {
                $tables[] = $table;
            }
            continue;
        }
        if ($ext !== 'php') {
            continue;
        }
        $contents = @file_get_contents($file);
        if ($contents === false || $contents === '') {
            continue;
        }
        if (!preg_match('/\$db\s*->\s*(prepare|query|exec)\s*\(/i', $contents)) {
            continue;
        }
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
    $frontendFiles = detectFrontendFiles($files, $moduleDir);
    $backendFiles = detectBackendFiles($files, $moduleDir);
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

    $frontendOk = null;
    $backendOk = null;
    $databaseOk = null;
    if ($db instanceof PDO) {
        $frontendOk = !empty($frontendFiles);
        $backendOk = !empty($backendFiles);
        $databaseOk = !empty($referencedTables) ? empty($missingTables) : null;
    }

    return [
        'module' => $module,
        'path' => str_replace('\\', '/', $moduleDir),
        'frontend_ok' => $frontendOk,
        'backend_ok' => $backendOk,
        'database_ok' => $databaseOk,
        'frontend_files' => $frontendFiles,
        'backend_files' => $backendFiles,
        'referenced_tables' => $referencedTables,
        'existing_tables' => $existingTables,
        'missing_tables' => $missingTables,
    ];
}

function buildFileTableMap(string $moduleDir, ?PDO $db): array
{
    $map = [];
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
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'sql'], true)) {
            continue;
        }
        $tables = extractSqlTablesFromFile($path);
        if ($tables === []) {
            continue;
        }
        $existing = [];
        $missing = [];
        if ($db instanceof PDO) {
            foreach ($tables as $table) {
                if (tableExists($db, $table)) {
                    $existing[] = $table;
                } else {
                    $missing[] = $table;
                }
            }
        }
        $relative = str_replace('\\', '/', substr($path, strlen($moduleDir) + 1));
        $map[] = [
            'file' => $relative,
            'tables' => $tables,
            'existing' => $existing,
            'missing' => $missing,
        ];
    }
    return $map;
}

function runChecks(string $scope, ?PDO $db): array
{
    $moduleDirs = listModuleDirectories(__DIR__);
    $modules = [];
    $frontendPresent = 0;
    $backendPresent = 0;
    $databasePresent = 0;
    $databaseMissing = 0;

    foreach ($moduleDirs as $moduleDir) {
        $summary = summarizeModule($moduleDir, $db);
        $modules[] = $summary;

        if ($summary['frontend_ok'] === true) {
            $frontendPresent++;
        }
        if ($summary['backend_ok'] === true) {
            $backendPresent++;
        }
        if ($summary['database_ok'] === true) {
            $databasePresent++;
        } elseif ($summary['database_ok'] === false) {
            $databaseMissing++;
        }
    }

    return [
        'scope' => $scope,
        'summary' => [
            'modules_scanned' => count($modules),
            'frontend_present' => $frontendPresent,
            'backend_present' => $backendPresent,
            'database_present' => $databasePresent,
            'database_missing' => $databaseMissing,
        ],
        'modules' => $modules,
    ];
}

function getMissingTableCreationSql(PDO $db): array
{
    $missing = [];
    $moduleDirs = listModuleDirectories(__DIR__);
    foreach ($moduleDirs as $moduleDir) {
        $files = collectFiles($moduleDir);
        $tables = collectModuleTables($files);
        foreach ($tables as $table) {
            if (!tableExists($db, $table)) {
                $missing[] = $table;
            }
        }
    }
    $missing = array_values(array_unique($missing));
    sort($missing);
    return $missing;
}

// Initialize DB connection for both GET and POST
$db = null;
$dbError = null;
try {
    $db = getDB();
} catch (Throwable $throwable) {
    $dbError = $throwable->getMessage();
}

// POST handler - must run BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim($_POST['action'] ?? 'all'));
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($action === 'fix_missing') {
        $results = ['ok' => true, 'fixed' => [], 'errors' => []];
        
        if ($db) {
            $missingTables = getMissingTableCreationSql($db);
            foreach ($missingTables as $table) {
                try {
                    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $db->exec($sql);
                    $results['fixed'][] = $table;
                } catch (Throwable $e) {
                    $results['errors'][] = ['table' => $table, 'message' => $e->getMessage()];
                }
            }
        }
        
        echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($action === 'module_detail') {
        $module = trim($_POST['module'] ?? '');
        $moduleDir = __DIR__ . '/modules/' . $module;
        
        if ($module && is_dir($moduleDir)) {
            $detail = [
                'module' => $module,
                'files' => buildFileTableMap($moduleDir, $db),
            ];
            echo json_encode(['ok' => true, 'detail' => $detail], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Module not found'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    $scope = match ($action) {
        'frontend', 'backend', 'database' => $action,
        default => 'all',
    };

    echo json_encode([
        'ok' => true,
        'db_error' => $dbError,
        'result' => runChecks($scope, $db),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// GET request - show the full page
require_once __DIR__ . '/includes/header.php';

$initial = runChecks('all', $db);
?>
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="rounded-2xl bg-slate-950 text-white p-6 shadow-lg border border-slate-800 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-[0.35em] text-slate-400">ERP Self Check</div>
                <h1 class="text-3xl font-bold mt-2">Module frontend, backend, and database checker</h1>
                <p class="text-slate-300 mt-2 max-w-3xl">Click a button to scan the real runtime modules under <code>modules/</code> and show the results inline on the same page. Expand any module to see files and their tables.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-white/5 p-4 border border-white/10">
                    <div class="text-slate-400">Modules scanned</div>
                    <div class="text-2xl font-semibold" id="summary-modules"><?= (int)$initial['summary']['modules_scanned'] ?></div>
                </div>
                <div class="rounded-xl bg-white/5 p-4 border border-white/10">
                    <div class="text-slate-400">DB status</div>
                    <div class="text-2xl font-semibold"><?= $db instanceof PDO ? 'Connected' : 'Unavailable' ?></div>
                </div>
            </div>
        </div>
        <?php if (!empty($initial['summary']['database_missing'])): ?>
            <div class="mt-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-200 p-4 text-sm flex items-center justify-between">
                <span><?= (int)$initial['summary']['database_missing'] ?> module(s) have missing database tables. Expand the module to see details, or click Fix All to create placeholder tables.</span>
                <button onclick="fixAllMissing()" class="px-3 py-1.5 rounded-lg bg-red-500 text-white text-sm font-semibold hover:bg-red-400 whitespace-nowrap ml-3">Fix All</button>
            </div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-3 mt-5">
            <button class="check-btn px-4 py-2 rounded-xl bg-cyan-500 text-slate-950 font-semibold hover:bg-cyan-400" data-action="frontend">Check Frontend</button>
            <button class="check-btn px-4 py-2 rounded-xl bg-emerald-500 text-slate-950 font-semibold hover:bg-emerald-400" data-action="backend">Check Backend</button>
            <button class="check-btn px-4 py-2 rounded-xl bg-amber-400 text-slate-950 font-semibold hover:bg-amber-300" data-action="database">Check Database</button>
            <button class="check-btn px-4 py-2 rounded-xl bg-white text-slate-950 font-semibold hover:bg-slate-100" data-action="all">Check All</button>
        </div>
    </div>

    <div id="results-summary" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow border border-slate-200 p-4">
            <div class="text-slate-500 text-sm">Frontend present</div>
            <div class="text-3xl font-bold text-cyan-600" id="summary-frontend"><?= (int)$initial['summary']['frontend_present'] ?></div>
        </div>
        <div class="bg-white rounded-2xl shadow border border-slate-200 p-4">
            <div class="text-slate-500 text-sm">Backend present</div>
            <div class="text-3xl font-bold text-emerald-600" id="summary-backend"><?= (int)$initial['summary']['backend_present'] ?></div>
        </div>
        <div class="bg-white rounded-2xl shadow border border-slate-200 p-4">
            <div class="text-slate-500 text-sm">Database matched</div>
            <div class="text-3xl font-bold text-amber-600" id="summary-database"><?= (int)$initial['summary']['database_present'] ?></div>
        </div>
        <div class="bg-white rounded-2xl shadow border border-slate-200 p-4">
            <div class="text-slate-500 text-sm">Database missing</div>
            <div class="text-3xl font-bold text-rose-600" id="summary-missing"><?= (int)$initial['summary']['database_missing'] ?></div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border border-slate-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Module status</h2>
                <p class="text-sm text-slate-500">Click a module name to expand files and table mapping. Rows update after each check.</p>
            </div>
            <div id="loading-indicator" class="hidden text-sm text-slate-500">Running check...</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wide text-xs">
                    <tr>
                        <th class="text-left px-5 py-3">Module</th>
                        <th class="text-left px-5 py-3">Frontend</th>
                        <th class="text-left px-5 py-3">Backend</th>
                        <th class="text-left px-5 py-3">Database</th>
                        <th class="text-left px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="result-body" class="divide-y divide-slate-100">
                    <?php foreach ($initial['modules'] as $module): ?>
                        <?php
                            $missingCount = count($module['missing_tables']);
                            $hasDbIssue = $missingCount > 0;
                        ?>
                        <tr class="module-row" data-module="<?= e($module['module']) ?>">
                            <td class="px-5 py-4">
                                <button class="module-toggle font-medium text-slate-900 hover:text-indigo-600 flex items-center gap-2" data-module="<?= e($module['module']) ?>">
                                    <i class="fas fa-chevron-right text-xs text-slate-400 transition-transform"></i>
                                    <?= e($module['module']) ?>
                                </button>
                            </td>
                            <td class="px-5 py-4" data-field="frontend">
                                <?php if ($module['frontend_ok'] === true): ?>
                                    <span class="px-2 py-1 rounded-full bg-cyan-100 text-cyan-800">Present (<?= count($module['frontend_files']) ?>)</span>
                                <?php elseif ($module['frontend_ok'] === false): ?>
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-500">Missing</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-500">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4" data-field="backend">
                                <?php if ($module['backend_ok'] === true): ?>
                                    <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Present (<?= count($module['backend_files']) ?>)</span>
                                <?php elseif ($module['backend_ok'] === false): ?>
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-500">Missing</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-500">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4" data-field="database">
                                <?php if ($module['database_ok'] === true): ?>
                                    <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-800">Ready</span>
                                <?php elseif ($module['database_ok'] === false): ?>
                                    <span class="px-2 py-1 rounded-full bg-rose-100 text-rose-800">Missing (<?= $missingCount ?>)</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-500">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <?php if ($hasDbIssue): ?>
                                    <button class="fix-module-btn text-xs px-2 py-1 rounded bg-amber-500 text-white hover:bg-amber-400" data-module="<?= e($module['module']) ?>">Fix</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="module-detail-row hidden" data-module-detail="<?= e($module['module']) ?>">
                            <td colspan="5" class="px-5 py-4 bg-slate-50">
                                <div class="module-detail-content">
                                    <div class="text-sm text-slate-500">Loading details...</div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Live Console</h2>
                <p class="text-sm text-slate-500">Real-time logs from checks and fixes.</p>
            </div>
            <button onclick="clearConsole()" class="text-xs px-2 py-1 rounded bg-slate-200 text-slate-700 hover:bg-slate-300">Clear</button>
        </div>
        <div id="console-output" class="p-4 font-mono text-xs bg-slate-900 text-green-400 h-64 overflow-y-auto">
            <div>[System] Health check console ready.</div>
        </div>
    </div>
</div>

<script>
(function () {
    const buttons = document.querySelectorAll('.check-btn');
    const loading = document.getElementById('loading-indicator');
    const resultBody = document.getElementById('result-body');
    const summaryMap = {
        frontend: document.getElementById('summary-frontend'),
        backend: document.getElementById('summary-backend'),
        database: document.getElementById('summary-database'),
        missing: document.getElementById('summary-missing'),
        modules: document.getElementById('summary-modules'),
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function badge(label, kind) {
        const colors = {
            present: 'bg-cyan-100 text-cyan-800',
            backend: 'bg-emerald-100 text-emerald-800',
            ready: 'bg-amber-100 text-amber-800',
            missing: 'bg-rose-100 text-rose-800',
            na: 'bg-slate-100 text-slate-500',
        };
        return `<span class="px-2 py-1 rounded-full ${colors[kind] || colors.na}">${label}</span>`;
    }

    function log(message, type = 'info') {
        const consoleEl = document.getElementById('console-output');
        const time = new Date().toLocaleTimeString();
        const colors = {
            info: 'text-slate-300',
            success: 'text-green-400',
            error: 'text-red-400',
            warning: 'text-amber-400',
        };
        consoleEl.innerHTML += `<div class="${colors[type] || colors.info}">[${time}] ${escapeHtml(message)}</div>`;
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    window.clearConsole = function() {
        document.getElementById('console-output').innerHTML = '';
        log('Console cleared.', 'info');
    };

    function render(result) {
        const summary = result.summary || {};
        summaryMap.frontend.textContent = summary.frontend_present ?? 0;
        summaryMap.backend.textContent = summary.backend_present ?? 0;
        summaryMap.database.textContent = summary.database_present ?? 0;
        summaryMap.missing.textContent = summary.database_missing ?? 0;
        summaryMap.modules.textContent = summary.modules_scanned ?? 0;

        const scope = result.scope || 'all';
        resultBody.innerHTML = (result.modules || []).map((module) => {
            const frontend = module.frontend_ok === null ? badge('N/A', 'na') : (module.frontend_ok ? badge('Present', 'present') : badge('Missing', 'missing'));
            const backend = module.backend_ok === null ? badge('N/A', 'na') : (module.backend_ok ? badge('Present', 'backend') : badge('Missing', 'missing'));
            const database = module.database_ok === null ? badge('N/A', 'na') : (module.database_ok ? badge('Ready', 'ready') : badge('Missing', 'missing'));
            const missingCount = (module.missing_tables || []).length;
            const hasFix = missingCount > 0;

            return `<tr class="module-row" data-module="${escapeHtml(module.module)}">
                <td class="px-5 py-4">
                    <button class="module-toggle font-medium text-slate-900 hover:text-indigo-600 flex items-center gap-2" data-module="${escapeHtml(module.module)}">
                        <i class="fas fa-chevron-right text-xs text-slate-400 transition-transform"></i>
                        ${escapeHtml(module.module)}
                    </button>
                </td>
                <td class="px-5 py-4">${frontend}</td>
                <td class="px-5 py-4">${backend}</td>
                <td class="px-5 py-4">${database}</td>
                <td class="px-5 py-4">
                    ${hasFix ? `<button class="fix-module-btn text-xs px-2 py-1 rounded bg-amber-500 text-white hover:bg-amber-400" data-module="${escapeHtml(module.module)}">Fix</button>` : ''}
                </td>
            </tr>
            <tr class="module-detail-row hidden" data-module-detail="${escapeHtml(module.module)}">
                <td colspan="5" class="px-5 py-4 bg-slate-50">
                    <div class="module-detail-content">
                        <div class="text-sm text-slate-500">Loading details...</div>
                    </div>
                </td>
            </tr>`;
        }).join('');

        log(`Check complete: ${scope}. ${summary.modules_scanned || 0} modules scanned.`, 'success');
        if (summary.database_missing > 0) {
            log(`${summary.database_missing} module(s) have missing tables.`, 'warning');
        }
    }

    async function run(action) {
        loading.classList.remove('hidden');
        try {
            log(`Running ${action} check...`, 'info');
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({action})
            });
            const data = await response.json();
            if (!data.ok) {
                throw new Error('Checker failed');
            }
            render(data.result);
            if (data.db_error) {
                log('Database connection error: ' + data.db_error, 'error');
            }
        } catch (error) {
            console.error(error);
            log('Error: ' + error.message, 'error');
            alert('Unable to run check. See console for details.');
        } finally {
            loading.classList.add('hidden');
        }
    }

    async function loadModuleDetail(moduleName) {
        const detailRow = document.querySelector(`[data-module-detail="${moduleName}"]`);
        const contentEl = detailRow.querySelector('.module-detail-content');
        const toggleBtn = document.querySelector(`.module-toggle[data-module="${moduleName}"]`);
        const chevron = toggleBtn.querySelector('.fa-chevron-right');
        
        if (!detailRow.classList.contains('hidden')) {
            detailRow.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
            return;
        }
        
        detailRow.classList.remove('hidden');
        chevron.style.transform = 'rotate(90deg)';
        contentEl.innerHTML = '<div class="text-sm text-slate-500">Loading...</div>';
        
        try {
            log(`Loading details for ${moduleName}...`, 'info');
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({action: 'module_detail', module: moduleName})
            });
            const data = await response.json();
            if (!data.ok || !data.detail) {
                throw new Error(data.error || 'Failed to load module detail');
            }
            
            const detail = data.detail;
            let html = '<div class="space-y-4">';
            
            if ((detail.files || []).length === 0) {
                html += '<div class="text-sm text-slate-500">No database-referencing files found in this module.</div>';
            } else {
                html += '<div class="grid gap-3">';
                detail.files.forEach((file) => {
                    const missingTables = (file.missing || []).length;
                    const statusColor = missingTables > 0 ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50';
                    const statusText = missingTables > 0 ? 'text-red-700' : 'text-green-700';
                    
                    html += `<div class="rounded-lg border ${statusColor} p-3">
                        <div class="font-mono text-xs text-slate-700 mb-2 break-all">${escapeHtml(file.file)}</div>
                        <div class="flex flex-wrap gap-2 mb-2">`;
                    
                    if (file.existing && file.existing.length) {
                        html += `<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">${file.existing.length} existing</span>`;
                    }
                    if (missingTables > 0) {
                        html += `<span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-800">${missingTables} missing</span>`;
                    }
                    
                    html += `</div>`;
                    
                    if (file.tables && file.tables.length) {
                        html += '<div class="flex flex-wrap gap-1 mt-2">';
                        file.tables.forEach((t) => {
                            const exists = file.existing && file.existing.includes(t);
                            const cls = exists ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            const icon = exists ? '✓' : '✗';
                            html += `<span class="text-xs px-1.5 py-0.5 rounded ${cls}">${icon} ${escapeHtml(t)}</span>`;
                        });
                        html += '</div>';
                    }
                    
                    html += '</div>';
                });
                html += '</div>';
            }
            
            html += '</div>';
            contentEl.innerHTML = html;
            log(`Details loaded for ${moduleName}.`, 'success');
        } catch (error) {
            contentEl.innerHTML = `<div class="text-sm text-red-600">Error: ${escapeHtml(error.message)}</div>`;
            log(`Failed to load details for ${moduleName}: ${error.message}`, 'error');
        }
    }

    async function fixModule(moduleName) {
        log(`Fixing ${moduleName}...`, 'warning');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({action: 'module_detail', module: moduleName})
            });
            const data = await response.json();
            if (!data.ok || !data.detail) {
                throw new Error(data.error || 'Failed to load module detail');
            }
            
            const detail = data.detail;
            const allMissing = [];
            (detail.files || []).forEach((file) => {
                (file.missing || []).forEach((t) => {
                    if (!allMissing.includes(t)) allMissing.push(t);
                });
            });
            
            if (allMissing.length === 0) {
                log(`${moduleName} has no missing tables.`, 'success');
                return;
            }
            
            log(`${moduleName}: Creating ${allMissing.length} missing table(s)...`, 'warning');
            
            const fixResponse = await fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({action: 'fix_missing'})
            });
            const fixData = await fixResponse.json();
            
            if (fixData.fixed && fixData.fixed.length) {
                fixData.fixed.forEach((t) => log(`Created table: ${t}`, 'success'));
            }
            if (fixData.errors && fixData.errors.length) {
                fixData.errors.forEach((e) => log(`Failed to create ${e.table}: ${e.message}`, 'error'));
            }
            
            log(`Fix complete for ${moduleName}. Reloading...`, 'success');
            setTimeout(() => run('database'), 800);
        } catch (error) {
            log(`Fix failed for ${moduleName}: ${error.message}`, 'error');
        }
    }

    async function fixAllMissing() {
        log('Starting global fix for all missing tables...', 'warning');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({action: 'fix_missing'})
            });
            const data = await response.json();
            
            if (data.fixed && data.fixed.length) {
                data.fixed.forEach((t) => log(`Created table: ${t}`, 'success'));
            }
            if (data.errors && data.errors.length) {
                data.errors.forEach((e) => log(`Failed to create ${e.table}: ${e.message}`, 'error'));
            }
            
            log(`Fix complete. ${(data.fixed || []).length} created, ${(data.errors || []).length} failed. Reloading...`, 'success');
            setTimeout(() => run('database'), 800);
        } catch (error) {
            log('Global fix failed: ' + error.message, 'error');
        }
    }

    window.fixAllMissing = fixAllMissing;

    buttons.forEach((button) => {
        button.addEventListener('click', () => run(button.dataset.action || 'all'));
    });

    resultBody.addEventListener('click', (event) => {
        const toggleBtn = event.target.closest('.module-toggle');
        const fixBtn = event.target.closest('.fix-module-btn');
        
        if (toggleBtn) {
            const moduleName = toggleBtn.dataset.module;
            loadModuleDetail(moduleName);
        }
        
        if (fixBtn) {
            const moduleName = fixBtn.dataset.module;
            fixModule(moduleName);
        }
    });

    log('Health check initialized.', 'info');
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>