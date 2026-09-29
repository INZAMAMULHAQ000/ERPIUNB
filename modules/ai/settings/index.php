<?php
/**
 * AI Settings Management Page.
 *
 * Configures system-wide AI settings, safety limits, cost budgets,
 * default models, rate limits, and retention periods.
 */
$pageTitle = 'AI Settings';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.settings.manage') && !hasPermission('ai.management.view') && ($_SESSION['role'] ?? '') !== 'superadmin') {
    die('Access Denied');
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Settings Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $defaultProviderId = (int) ($_POST['default_provider_id'] ?? 0);
        $defaultModelId = (int) ($_POST['default_model_id'] ?? 0);
        $companyDailyLimit = (float) ($_POST['company_daily_limit'] ?? 10.0);
        $userDailyLimit = (float) ($_POST['user_daily_limit'] ?? 5.0);
        $maxToolCalls = min(5, max(1, (int) ($_POST['max_tool_calls'] ?? 5)));
        $maxAgentSteps = min(20, max(1, (int) ($_POST['max_agent_steps'] ?? 20)));
        $maxAutoRuntime = min(300, max(30, (int) ($_POST['max_automation_runtime'] ?? 300)));
        $convRetentionDays = max(1, (int) ($_POST['conv_retention_days'] ?? 180));
        $errorRetentionDays = max(1, (int) ($_POST['error_retention_days'] ?? 30));

        try {
            // Update default provider
            if ($defaultProviderId > 0) {
                $db->exec("UPDATE ai_providers SET is_default = 0");
                $db->prepare("UPDATE ai_providers SET is_default = 1 WHERE id = ?")->execute([$defaultProviderId]);
            }

            // Update default model
            if ($defaultModelId > 0) {
                $db->exec("UPDATE ai_models SET is_default = 0");
                $db->prepare("UPDATE ai_models SET is_default = 1 WHERE id = ?")->execute([$defaultModelId]);
            }

            // Update cost controls
            $stmt = $db->prepare("
                UPDATE ai_cost_controls
                SET max_cost = ?, updated_at = NOW()
                WHERE control_type = 'per_company_daily' AND target_id IS NULL
            ");
            $stmt->execute([$companyDailyLimit]);

            // Update safety policies
            $db->prepare("UPDATE ai_safety_policies SET max_value = ?, updated_at = NOW() WHERE policy_key = 'max_tool_calls'")->execute([$maxToolCalls]);
            $db->prepare("UPDATE ai_safety_policies SET max_value = ?, updated_at = NOW() WHERE policy_key = 'max_agent_steps'")->execute([$maxAgentSteps]);
            $db->prepare("UPDATE ai_safety_policies SET max_value = ?, updated_at = NOW() WHERE policy_key = 'max_automation_runtime'")->execute([$maxAutoRuntime]);

            // Update retention policies
            $db->prepare("UPDATE ai_retention_policies SET retention_days = ?, updated_at = NOW() WHERE data_type = 'conversation_history'")->execute([$convRetentionDays]);
            $db->prepare("UPDATE ai_retention_policies SET retention_days = ?, updated_at = NOW() WHERE data_type = 'error_logs'")->execute([$errorRetentionDays]);

            AIGovernanceService::auditConfigChange($db, 'settings', 'global', 'updated', null, $_POST, $userId);
            $message = 'AI system settings updated successfully.';
        } catch (\Throwable $e) {
            $error = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

// Fetch current values
$providers = AIProviderService::getProviders($db);
$models = $db->query("SELECT * FROM ai_models WHERE is_active = 1 ORDER BY display_name")->fetchAll();
$defaultProvider = AIProviderService::getDefaultProvider($db);
$defaultModel = AIModelService::getDefaultModel($db);

$companyBudget = $db->query("SELECT max_cost FROM ai_cost_controls WHERE control_type = 'per_company_daily' AND target_id IS NULL LIMIT 1")->fetchColumn() ?: '10.00';
$maxToolPolicy = $db->query("SELECT max_value FROM ai_safety_policies WHERE policy_key = 'max_tool_calls' LIMIT 1")->fetchColumn() ?: '5';
$maxAgentPolicy = $db->query("SELECT max_value FROM ai_safety_policies WHERE policy_key = 'max_agent_steps' LIMIT 1")->fetchColumn() ?: '20';
$maxRuntimePolicy = $db->query("SELECT max_value FROM ai_safety_policies WHERE policy_key = 'max_automation_runtime' LIMIT 1")->fetchColumn() ?: '300';
$convRetention = $db->query("SELECT retention_days FROM ai_retention_policies WHERE data_type = 'conversation_history' LIMIT 1")->fetchColumn() ?: '180';
$errorRetention = $db->query("SELECT retention_days FROM ai_retention_policies WHERE data_type = 'error_logs' LIMIT 1")->fetchColumn() ?: '30';
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.settings'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <div class="ai-page-header">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h1 class="ai-page-title">
                    <span class="ai-page-icon"><i class="fas fa-sliders-h"></i></span>
                    AI System Settings
                </h1>
                <p class="ai-page-subtitle">Configure default models, safety boundaries, cost budgets, and retention periods</p>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="ai-alert ai-alert-success mb-6">
        <i class="fas fa-check-circle"></i>
        <div><?= htmlspecialchars($message) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="ai-alert ai-alert-danger mb-6">
        <i class="fas fa-exclamation-circle"></i>
        <div><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Section 1: Default Provider & Model -->
        <div class="ai-content-card">
            <h3 class="ai-section-title">
                <i class="fas fa-server"></i> Default Provider & Model Routing
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="ai-form-label">Default AI Provider</label>
                    <select name="default_provider_id" class="ai-form-select">
                        <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($defaultProvider && $defaultProvider['id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['provider_type']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Fallback provider for unassigned AI conversations.</p>
                </div>

                <div>
                    <label class="ai-form-label">Default Chat Model</label>
                    <select name="default_model_id" class="ai-form-select">
                        <?php foreach ($models as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($defaultModel && $defaultModel['id'] == $m['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['display_name']) ?> (<?= htmlspecialchars($m['model_id']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Default model selected in Assistant and Command Center.</p>
                </div>
            </div>
        </div>

        <!-- Section 2: Cost & Budget Controls -->
        <div class="ai-content-card">
            <h3 class="ai-section-title">
                <i class="fas fa-hand-holding-usd"></i> AI Budget & Cost Limits
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="ai-form-label">Company Daily Cost Limit ($ USD)</label>
                    <input type="number" step="0.01" min="0" name="company_daily_limit" value="<?= htmlspecialchars($companyBudget) ?>"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Maximum AI spending per calendar day across all users.</p>
                </div>

                <div>
                    <label class="ai-form-label">Per-User Daily Cost Limit ($ USD)</label>
                    <input type="number" step="0.01" min="0" name="user_daily_limit" value="5.00"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Protects against runaway requests from individual user sessions.</p>
                </div>
            </div>
        </div>

        <!-- Section 3: Safety & Hard Limits -->
        <div class="ai-content-card">
            <h3 class="ai-section-title">
                <i class="fas fa-shield-alt"></i> AI Safety & Execution Boundaries
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="ai-form-label">Max Tool Calls / Request (Max 5)</label>
                    <input type="number" min="1" max="5" name="max_tool_calls" value="<?= htmlspecialchars($maxToolPolicy) ?>"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Prevents recursive tool execution loops.</p>
                </div>

                <div>
                    <label class="ai-form-label">Max Agent Steps (Max 20)</label>
                    <input type="number" min="1" max="20" name="max_agent_steps" value="<?= htmlspecialchars($maxAgentPolicy) ?>"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Hard limit on autonomous agent task depth.</p>
                </div>

                <div>
                    <label class="ai-form-label">Max Automation Runtime (Seconds)</label>
                    <input type="number" min="30" max="300" name="max_automation_runtime" value="<?= htmlspecialchars($maxRuntimePolicy) ?>"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Timeout for automated background runs.</p>
                </div>
            </div>
        </div>

        <!-- Section 4: Data Retention -->
        <div class="ai-content-card">
            <h3 class="ai-section-title">
                <i class="fas fa-calendar-times"></i> Data Retention Policies
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="ai-form-label">Conversation Retention (Days)</label>
                    <input type="number" min="1" name="conv_retention_days" value="<?= htmlspecialchars($convRetention) ?>"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Days to preserve archived conversation history.</p>
                </div>

                <div>
                    <label class="ai-form-label">Error Log Retention (Days)</label>
                    <input type="number" min="1" name="error_retention_days" value="<?= htmlspecialchars($errorRetention) ?>"
                           class="ai-form-input">
                    <p class="text-xs text-slate-400 mt-1">Days to preserve resolved AI error logs.</p>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2.5 rounded-lg shadow-sm transition flex items-center gap-2">
                <i class="fas fa-save"></i> Save System Settings
            </button>
        </div>
    </form>
</div>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
