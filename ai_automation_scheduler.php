<?php
/**
 * AI F13 — Automation Scheduler CLI Entry Point.
 *
 * This script is intended to be called by cron:
 *   * * * * * php /path/to/project/ai_automation_scheduler.php
 *
 * It processes all due scheduled automations with proper locking.
 *
 * Safety:
 * - CLI-only (checks sapi_name)
 * - Database-backed locking prevents duplicate execution
 * - Concurrency limits enforced
 * - Owner permissions revalidated
 * - Execution limits applied
 *
 * Usage:
 *   php ai_automation_scheduler.php              # Process due automations
 *   php ai_automation_scheduler.php --status     # Show scheduler status
 *   php ai_automation_scheduler.php --dry-run    # Preview without executing
 */

// Ensure CLI execution only
if (php_sapi_name() !== 'cli') {
    echo "Error: This script must be run from the command line.\n";
    exit(1);
}

// Bootstrap ERP
$erpRoot = __DIR__;
require_once $erpRoot . '/config/database.php';
require_once $erpRoot . '/helpers/rbac.php';
require_once $erpRoot . '/helpers/security.php';

// Bootstrap AI module
define('AI_MODULE_DIR', $erpRoot . '/modules/ai');
define('AI_APP_ROOT', $erpRoot);

require_once AI_MODULE_DIR . '/includes/ai_http_client.php';
require_once AI_MODULE_DIR . '/includes/ai_credential.php';
require_once AI_MODULE_DIR . '/includes/ai_text_extractor.php';
require_once AI_MODULE_DIR . '/includes/ai_embedding_provider.php';
require_once AI_MODULE_DIR . '/includes/ai_vector_store.php';
require_once AI_MODULE_DIR . '/includes/ai_tool_base.php';
require_once AI_MODULE_DIR . '/includes/ai_action_base.php';
require_once AI_MODULE_DIR . '/includes/ai_agent_base.php';
require_once AI_MODULE_DIR . '/includes/ai_agents_def.php';
require_once AI_MODULE_DIR . '/tools/procurement_tools.php';
require_once AI_MODULE_DIR . '/tools/procurement_actions.php';
require_once AI_MODULE_DIR . '/tools/knowledge_tools.php';
require_once AI_MODULE_DIR . '/services/AIContextService.php';
require_once AI_MODULE_DIR . '/services/AIConversationService.php';
require_once AI_MODULE_DIR . '/services/AINavigationService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeService.php';
require_once AI_MODULE_DIR . '/services/AIProviderService.php';
require_once AI_MODULE_DIR . '/services/AIModelService.php';
require_once AI_MODULE_DIR . '/services/AITaskService.php';
require_once AI_MODULE_DIR . '/services/AIAutomationService.php';
require_once AI_MODULE_DIR . '/services/AIAgentService.php';
require_once AI_MODULE_DIR . '/services/AIAutomationSchedulerService.php';

// Connect to database
$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER, DB_PASS,
    [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Parse arguments
$args = array_slice($argv, 1);
$action = $args[0] ?? 'run';

$logFile = AI_APP_ROOT . '/storage/ai_scheduler.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

function schedulerLog(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

switch ($action) {
    case '--status':
        schedulerLog("=== Scheduler Status ===");

        $due = $db->prepare("
            SELECT COUNT(*) FROM ai_automations a
            JOIN ai_automation_triggers t ON a.id = t.automation_id
            WHERE t.trigger_type = 'scheduled' AND t.enabled = 1 AND a.is_active = 1
            AND a.next_run_at IS NOT NULL AND a.next_run_at <= NOW()
        ");
        $due->execute();
        schedulerLog("Due automations: " . $due->fetchColumn());

        $running = $db->prepare("SELECT COUNT(*) FROM ai_automation_runs WHERE status = 'running'");
        $running->execute();
        schedulerLog("Running: " . $running->fetchColumn());

        $locks = $db->prepare("SELECT COUNT(*) FROM ai_scheduler_locks WHERE expires_at > NOW()");
        $locks->execute();
        schedulerLog("Active locks: " . $locks->fetchColumn());

        $recentRuns = $db->prepare("
            SELECT COUNT(*) FROM ai_automation_runs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $recentRuns->execute();
        schedulerLog("Runs in last hour: " . $recentRuns->fetchColumn());
        break;

    case '--dry-run':
        schedulerLog("=== Dry Run ===");
        $due = $db->prepare("
            SELECT a.id, a.name, a.next_run_at, a.safety_classification
            FROM ai_automations a
            JOIN ai_automation_triggers t ON a.id = t.automation_id
            WHERE t.trigger_type = 'scheduled' AND t.enabled = 1 AND a.is_active = 1
            AND a.next_run_at IS NOT NULL AND a.next_run_at <= NOW()
        ");
        $due->execute();
        $automations = $due->fetchAll();
        foreach ($automations as $auto) {
            schedulerLog("  Would process: [{$auto['id']}] {$auto['name']} (next: {$auto['next_run_at']}, safety: {$auto['safety_classification']})");
        }
        if (empty($automations)) {
            schedulerLog("  No automations due.");
        }
        break;

    case 'run':
    default:
        $startTime = microtime(true);
        schedulerLog("=== Scheduler Run Started ===");

        $result = AIAutomationSchedulerService::processDueAutomations($db);

        $duration = round((microtime(true) - $startTime) * 1000);
        schedulerLog("Processed: {$result['processed']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}");
        schedulerLog("Duration: {$duration}ms");
        schedulerLog("=== Scheduler Run Complete ===");
        break;
}
