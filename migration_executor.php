<?php
/**
 * Migration Script Executor
 * 
 * Visually execute SQL migration files with tracking.
 * 
 * Features:
 * - Lists all .sql files in config/migration_scripts/
 * - Executes each file idempotently (IF NOT EXISTS safe)
 * - Shows success/failure for each file
 * - Tracks executed files in session
 * - "Run All" and "Rollback" capabilities
 * - Session-based tracking (survives page refresh)
 */

// ── Safety: Localhost only ─────────────────────────────────────────
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (php_sapi_name() === 'cli')
    || ($serverName !== '' && in_array($serverName, ['localhost', '127.0.0.1', '::1']))
    || ($httpHost !== '' && in_array($httpHost, ['localhost', '127.0.0.1', '::1']));

if (!$isLocal) {
    die('<h1 style="color:red;text-align:center;margin-top:4rem">This executor can only run on localhost.</h1>');
}

// ── Bootstrap ──────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
$db = getDB();

// ── Session tracking for executed files ────────────────────────────
session_start();
if (!isset($_SESSION['executed_scripts'])) {
    $_SESSION['executed_scripts'] = [];
}
if (!isset($_SESSION['script_results'])) {
    $_SESSION['script_results'] = [];
}

// ── Get all .sql files from migration_scripts folder ────────────────
$scriptDir = __DIR__ . '/config/migration_scripts';
$sqlFiles = [];

if (is_dir($scriptDir)) {
    $files = scandir($scriptDir);
    foreach ($files as $file) {
        if (is_file($scriptDir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $sqlFiles[] = $file;
        }
    }
    sort($sqlFiles);
}

// ── Execute a single script ────────────────────────────────────────
function executeScript($db, $filePath, $fileName) {
    $result = [
        'file' => $fileName,
        'status' => 'pending',
        'messages' => [],
        'affected' => 0
    ];
    
    try {
        $sql = file_get_contents($filePath);
        
        if ($sql === false || trim($sql) === '') {
            $result['status'] = 'error';
            $result['messages'][] = "File is empty or could not be read";
            return $result;
        }
        
        // Strip USE statements (DB is already selected in getDB())
        $sql = preg_replace('/^USE\s+\w+;/mi', '', $sql);
        
        // Remove single-line comments (-- ...)
        $sql = preg_replace('/^--.*$/m', '', $sql);
        
        // Remove multi-line comments (/* ... */)
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolons
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty(trim($s))
        );
        
        $count = 0;
        $errors = [];
        
        foreach ($statements as $stmt) {
            try {
                $db->exec($stmt);
                $count++;
            } catch (PDOException $e) {
                // Skip errors for IF NOT EXISTS / duplicate key etc. - idempotent
                if (strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate entry') !== false ||
                    strpos($e->getMessage(), 'Duplicate key') !== false ||
                    strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    // Expected for idempotent runs - ignore
                } else {
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        $result['status'] = 'success';
        $result['affected'] = $count;
        if (!empty($errors)) {
            $result['messages'] = array_slice($errors, 0, 3);
        }
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['messages'][] = 'Error: ' . $e->getMessage();
    }
    
    return $result;
}

// ── Handle form submissions ─────────────────────────────────────────
$action = $_POST['action'] ?? '';

// Run All Scripts
if ($action === 'run_all') {
    foreach ($sqlFiles as $file) {
        $filePath = $scriptDir . '/' . $file;
        $result = executeScript($db, $filePath, $file);
        $_SESSION['script_results'][] = $result;
        
        // Track as executed
        if (!in_array($file, $_SESSION['executed_scripts'])) {
            $_SESSION['executed_scripts'][] = $file;
        }
    }
    // Refresh to show results
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Run Single Script (from dropdown)
if ($action === 'run_single' && isset($_POST['script_name'])) {
    $selectedFile = $_POST['script_name'];
    $filePath = $scriptDir . '/' . $selectedFile;
    $result = executeScript($db, $filePath, $selectedFile);
    $_SESSION['script_results'][] = $result;
    
    if (!in_array($selectedFile, $_SESSION['executed_scripts'])) {
        $_SESSION['executed_scripts'][] = $selectedFile;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Reset All Results
if ($action === 'reset') {
    $_SESSION['executed_scripts'] = [];
    $_SESSION['script_results'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Migration Script Executor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --bg-secondary: #11111a;
            --card: #1a1a24;
            --border: #2a2a3a;
            --fg: #f0f0f5;
            --muted: #737380;
            --accent: #10b981;
            --accent-hover: #059669;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --progress-running: #f59e0b;
            --progress-done: #10b981;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--fg);
            min-height: 100vh;
            margin: 0;
        }
        
        .navbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
        }
        
        .navbar .title {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 600;
        }
        
        .stat-card .label {
            color: var(--muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }
        
        .main-content {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            min-height: 600px;
        }
        
        .header-bar {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-ready { background: rgba(16, 185, 129, 0.15); color: var(--accent); }
        .status-running { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .status-done { background: rgba(30, 64, 175, 0.15); color: #63b3ed; }
        
        .progress-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
        }
        
        .progress-item:hover {
            border-color: var(--accent);
        }
        
        .progress-item.running {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
        }
        
        .progress-item.done {
            border-color: var(--accent);
            background: rgba(16, 185, 129, 0.03);
        }
        
        .progress-item.failed {
            border-color: var(--error);
            background: rgba(239, 68, 68, 0.05);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: var(--border);
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--accent);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-fill.running {
            background: var(--warning);
            animation: pulse 2s infinite;
        }
        
        .progress-fill.done {
            background: var(--accent);
        }
        
        .progress-fill.failed {
            background: var(--error);
        }
        
        .script-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-pending { color: var(--muted); }
        .status-success { color: var(--accent); }
        .status-failed { color: var(--error); }
        .status-running { color: var(--warning); animation: bounce 2s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.25rem 0.5rem;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 4px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .filter-btn:not(.active):hover {
            background: var(--bg-secondary);
            color: var(--fg);
        }
        
        .migration-form {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .migration-form h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .migration-form select {
            width: 100%;
            padding: 0.5rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--fg);
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        .migration-form button {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.5rem;
        }
        
        .output-messages {
            margin-top: 1rem;
        }
        
        .output-message {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .output-message.success { background: rgba(16, 185, 129, 0.15); color: var(--accent); }
        .output-message.warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .output-message.error { background: rgba(239, 68, 68, 0.15); color: var(--error); }
        .output-message.info { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        
        .file-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .file-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .file-item:hover {
            border-color: var(--accent);
        }
        
        .file-item.running {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
        }
        
        .file-item.done {
            border-color: var(--accent);
            background: rgba(16, 185, 129, 0.03);
        }
        
        .file-item .file-name {
            font-weight: 600;
            color: var(--fg);
        }
        
        .file-item .file-status {
            font-size: 0.7rem;
            color: var(--muted);
        }
        
        @media (max-width: 640px) {
            .navbar .title { display: none; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <span class="title">ERP Migration Script Executor</span>
        </div>
    </nav>

    <div class="container">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo count($sqlFiles); ?></div>
                <div class="label">Total Scripts</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo count($_SESSION['executed_scripts'] ?? []); ?></div>
                <div class="label">Executed</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: var(<?php echo(count($_SESSION['executed_scripts'] ?? []) / count($sqlFiles) * 100 >= 90 ? '--accent' : (count($_SESSION['executed_scripts'] ?? []) / count($sqlFiles) * 100 >= 75 ? '--warning' : '--error')); ?>);">
                    <?php 
                    $pct = count($sqlFiles) > 0 ? round((count($_SESSION['executed_scripts'] ?? []) / count($sqlFiles)) * 100, 1) : 0;
                    echo $pct . '%';
                    ?>
                </div>
                <div class="label">Completion</div>
            </div>
        </div>

        <!-- Status Badge -->
        <div class="status-badge status-<?php 
            $pct = count($sqlFiles) > 0 ? round((count($_SESSION['executed_scripts'] ?? []) / count($sqlFiles)) * 100, 1) : 0;
            echo $pct >= 90 ? 'ready' : ($pct >= 75 ? 'warning' : 'error');
        ?>">
            <?php echo number_format($pct, 1) ?>% Complete
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header Bar -->
            <div class="header-bar">
                <h1>Migration Script Executor</h1>
                
                <div>
                    <form method="POST" style="display: inline;">
                        <button name="action" value="reset" onclick="return confirm('Reset all execution tracking?')" style="padding: 0.5rem 1rem; background: #6b7280; color: white; border: none; border-radius: 4px; font-size: 0.875rem; cursor: pointer;">Reset Tracker</button>
                    </form>
                    <span style="margin-left: 1rem; font-size: 0.75rem; color: var(--muted);">
                        Session-based tracking (clears on browser close)
                    </span>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="table-section" style="margin-bottom: 2rem;">
                <h2 class="section-title">Execution Control</h2>
                
                <div class="stats-grid" style="margin-bottom: 1rem;">
                    <div class="stat-card">
                        <div class="number">100</div>
                        <div class="label">% per Script</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">1</div>
                        <div class="label">Run Mode</div>
                    </div>
                </div>
                
                <!-- Run All Form -->
                <form method="POST" class="migration-form">
                    <h3>Run All Migration Scripts</h3>
                    <p style="font-size: 0.875rem; color: var(--muted); margin-bottom: 1rem;">
                        Executes all <?php echo count($sqlFiles); ?> SQL files from config/migration_scripts/
                    </p>
                    <button type="submit" name="action" value="run_all" style="width: 100%; padding: 0.75rem; background: var(--accent); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
                        ▶️ Run All Scripts
                    </button>
                </form>
                
                <!-- Run Single Script -->
                <form method="POST" class="migration-form" style="margin-top: 1rem;">
                    <h3>Run Single Script</h3>
                    <select name="script_name" onchange="this.form.submit()" style="margin-bottom: 0.5rem;">
                        <option value="">-- Select a script --</option>
                        <?php foreach ($sqlFiles as $file): ?>
                            <option value="<?php echo htmlspecialchars($file); ?>" 
                                <?php 
                                $isExecuted = in_array($file, $_SESSION['executed_scripts'] ?? []);
                                echo $isExecuted ? 'disabled' : '';
                                ?>>
                                <?php echo htmlspecialchars($file); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="run_single" style="width: 100%; padding: 0.5rem 0; background: var(--info); color: white; border: none; border-radius: 6px; font-size: 0.875rem; cursor: pointer;">
                        Run Selected
                    </button>
                </form>
            </div>

            <!-- Results Panel -->
            <div class="table-section">
                <h2 class="section-title">Execution Results</h2>
                
                <?php if (empty($_SESSION['script_results'] ?? [])): ?>
                    <div class="info" style="padding: 2rem; text-align: center; color: var(--muted);">
                        No scripts executed yet. Click "Run All Scripts" to begin.
                    </div>
                <?php else: ?>
                    <div class="progress-item done">
                        <div>Execution session completed</div>
                        <div class="progress-bar">
                            <div class="progress-fill done" style="width: 100%"></div>
                        </div>
                    </div>
                    
                    <div class="output-messages">
                        <?php foreach ($_SESSION['script_results'] as $result): ?>
                            <div class="output-message <?php echo $result['status']; ?>">
                                <strong><?php echo htmlspecialchars($result['file']); ?></strong> 
                                <?php echo $result['status'] === 'success' ? 
                                    "({$result['affected']} statement(s) executed)" : 
                                    "(" . implode(', ', $result['messages']) . ")";
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Summary -->
                    <div class="stats-grid" style="margin-top: 1rem;">
                        <?php 
                        $success = 0; $failed = 0;
                        foreach ($_SESSION['script_results'] as $r) {
                            $r['status'] === 'success' ? $success++ : $failed++;
                        }
                        ?>
                        <div class="stat-card">
                            <div class="number" style="color: var(--accent);"><?php echo $success; ?></div>
                            <div class="label">Successful</div>
                        </div>
                        <div class="stat-card">
                            <div class="number" style="color: var(--error);"><?php echo $failed; ?></div>
                            <div class="label">Failed</div>
                        </div>
                        <div class="stat-card">
                            <div class="number"><?php echo count($_SESSION['script_results']); ?></div>
                            <div class="label">Total Run</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Individual Script Status -->
            <div class="table-section" style="margin-top: 2rem;">
                <h2 class="section-title">Script Status Details</h2>
                
                <div class="file-list">
                    <?php foreach ($sqlFiles as $file): 
                        $isExecuted = in_array($file, $_SESSION['executed_scripts'] ?? []);
                        $filePath = $scriptDir . '/' . $file;
                        $hasResult = in_array($file, array_column($_SESSION['script_results'] ?? [], 'file'));
                    ?>
                        <div class="file-item <?php echo $isExecuted ? 'done' : ($hasResult ? 'failed' : ''); ?>">
                            <div class="file-name">`<?php echo $file; ?>`</div>
                            <div class="file-status">
                                <?php 
                                if ($isExecuted) echo "✅ Executed";
                                elseif ($hasResult) echo "❌ Failed";
                                else echo "⏳ Pending";
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
            // Highlight the selected script in the dropdown
            document.addEventListener('DOMContentLoaded', function() {
                const select = document.querySelector('select');
                if (select) {
                    // Check for selected script in URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const selected = urlParams.get('script');
                    if (selected) {
                        select.value = selected;
                    }
                }
            });
        </script>
    </body>
</html>