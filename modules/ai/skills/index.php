<?php
/**
 * AI Skills Center — F17
 * Skill management, execution, and analytics.
 */

require_once __DIR__ . '/../bootstrap.php';

$pageTitle = 'AI Skills Center';
$tab = $_GET['tab'] ?? 'skills';
$detail = $_GET['detail'] ?? null;
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'] ?? 1;

    if ($action === 'create_skill') {
        $data = [
            'skill_id' => $_POST['skill_id'] ?? '',
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'module' => $_POST['module'] ?? null,
            'category' => $_POST['category'] ?? null,
            'skill_type' => $_POST['skill_type'] ?? 'guidance',
            'mode' => $_POST['mode'] ?? 'guide',
            'safety_classification' => $_POST['safety_classification'] ?? 'read_only',
            'steps' => json_decode($_POST['steps_json'] ?? '[]', true) ?: [],
            'required_permissions' => array_filter(explode(',', $_POST['required_permissions'] ?? '')),
            'required_capabilities' => array_filter(explode(',', $_POST['required_capabilities'] ?? '')),
        ];
        $errors = AISkillService::validateSkill($data);
        if (empty($errors)) {
            AISkillService::createSkill($db, $data);
        }
        header("Location: ?tab=skills");
        exit;
    } elseif ($action === 'update_skill') {
        $skillId = $_POST['skill_id'] ?? '';
        $updates = [];
        if (isset($_POST['name'])) $updates['name'] = $_POST['name'];
        if (isset($_POST['description'])) $updates['description'] = $_POST['description'];
        if (isset($_POST['status'])) $updates['status'] = $_POST['status'];
        if (isset($_POST['is_active'])) $updates['is_active'] = (int)$_POST['is_active'];
        AISkillService::updateSkill($db, $skillId, $updates);
        header("Location: ?tab=skills&detail=" . $skillId);
        exit;
    } elseif ($action === 'publish_skill') {
        $skillId = $_POST['skill_id'] ?? '';
        AISkillService::publishSkill($db, $skillId, $userId);
        header("Location: ?tab=skills&detail=" . $skillId);
        exit;
    } elseif ($action === 'start_run') {
        $skillId = $_POST['skill_id'] ?? '';
        $companyId = $_SESSION['company_id'] ?? 1;
        $runId = AISkillService::startRun($db, $skillId, $userId, $companyId);
        if ($runId) {
            header("Location: ?tab=runs&run=" . $runId);
        } else {
            header("Location: ?tab=skills");
        }
        exit;
    } elseif ($action === 'cancel_run') {
        $runId = $_POST['run_id'] ?? '';
        AISkillService::cancelRun($db, $runId, $userId);
        header("Location: ?tab=runs");
        exit;
    } elseif ($action === 'submit_feedback') {
        $runId = $_POST['run_id'] ?? '';
        $rating = $_POST['rating'] ?? '';
        $comment = $_POST['comment'] ?? null;
        AISkillService::submitFeedback($db, $runId, $userId, $rating, $comment);
        header("Location: ?tab=runs&run=" . $runId);
        exit;
    } elseif ($action === 'set_preference') {
        $key = $_POST['pref_key'] ?? '';
        $value = $_POST['pref_value'] ?? '';
        if ($key && $value) {
            AISkillService::setPreference($db, $userId, $key, $value, $_SESSION['company_id'] ?? null);
        }
        header("Location: ?tab=preferences");
        exit;
    } elseif ($action === 'delete_preference') {
        $key = $_POST['pref_key'] ?? '';
        AISkillService::deletePreference($db, $userId, $key, $_SESSION['company_id'] ?? null);
        header("Location: ?tab=preferences");
        exit;
    } elseif ($action === 'clear_preferences') {
        AISkillService::clearPreferences($db, $userId);
        header("Location: ?tab=preferences");
        exit;
    } elseif ($action === 'create_from_template') {
        $templateId = $_POST['template_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $skillId = AISkillService::createFromTemplate($db, $templateId, ['name' => $name, 'created_by' => $userId]);
        if ($skillId) {
            header("Location: ?tab=skills");
        } else {
            header("Location: ?tab=templates");
        }
        exit;
    }

    header("Location: ?tab=$tab");
    exit;
}

// Gather data
$userId = $_SESSION['user_id'] ?? 1;
$companyId = $_SESSION['company_id'] ?? null;

$skills = AISkillService::getSkills($db, ['limit' => 50]);
$publishedCount = count(AISkillService::getSkills($db, ['status' => 'published']));
$templates = AISkillService::getTemplates($db);
$preferences = AISkillService::getPreferences($db, $userId, $companyId);
$suggestions = AISkillService::getSuggestions($db, $userId, ['module' => $_GET['module'] ?? null]);

// Get runs
$runsStmt = $db->prepare("SELECT r.*, s.name as skill_name FROM ai_skill_runs r LEFT JOIN ai_skills s ON r.skill_id = s.skill_id ORDER BY r.started_at DESC LIMIT 20");
$runsStmt->execute();
$runs = $runsStmt->fetchAll(PDO::FETCH_ASSOC);

$detailSkill = null;
$detailAnalytics = null;
$detailRuns = [];
if ($detail) {
    $detailSkill = AISkillService::getSkill($db, $detail);
    $detailAnalytics = AISkillService::getAnalytics($db, $detail);
    $detailRuns = AISkillService::getRunsForSkill($db, $detail, 10);
}

$runDetail = null;
if (!empty($_GET['run'])) {
    $runDetail = AISkillService::getRun($db, $_GET['run']);
}

// Module list
$modules = ['procurement', 'finance', 'hr', 'inventory', 'system'];
$skillTypes = ['guidance', 'navigation', 'workflow', 'analysis', 'knowledge', 'composite'];
$statuses = ['draft', 'testing', 'approved', 'published', 'deprecated', 'archived'];

$badgeClass = [
    'draft' => 'ai-badge bg-slate-700/50 text-slate-400',
    'testing' => 'ai-badge ai-badge-warning',
    'approved' => 'ai-badge ai-badge-purple',
    'published' => 'ai-badge ai-badge-success',
    'deprecated' => 'ai-badge ai-badge-danger',
    'archived' => 'ai-badge bg-slate-700/50 text-slate-400',
    'started' => 'ai-badge ai-badge-info',
    'in_progress' => 'ai-badge ai-badge-warning',
    'completed' => 'ai-badge ai-badge-success',
    'failed' => 'ai-badge ai-badge-danger',
    'cancelled' => 'ai-badge bg-slate-700/50 text-slate-400',
];

function badge($status) {
    global $badgeClass;
    return $badgeClass[$status] ?? 'ai-badge';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <?php ai_render_shell_styles(); ?>
</head>
<body class="ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.skills'); ?>
    <?php ai_render_page_styles(); ?>

    <div class="ai-page-container">
        <?php ai_render_page_header('AI Skills Center', 'ERP learning, guided workflows, and adaptive assistance', 'fas fa-star'); ?>

        <div class="ai-tabs">
            <a href="?tab=skills" class="ai-tab <?php echo $tab === 'skills' ? 'active' : ''; ?>"><i class="fas fa-list-check"></i>Skills</a>
            <a href="?tab=builder" class="ai-tab <?php echo $tab === 'builder' ? 'active' : ''; ?>"><i class="fas fa-hammer"></i>Builder</a>
            <a href="?tab=templates" class="ai-tab <?php echo $tab === 'templates' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i>Templates</a>
            <a href="?tab=runs" class="ai-tab <?php echo $tab === 'runs' ? 'active' : ''; ?>"><i class="fas fa-play"></i>Runs</a>
            <a href="?tab=preferences" class="ai-tab <?php echo $tab === 'preferences' ? 'active' : ''; ?>"><i class="fas fa-sliders-h"></i>Preferences</a>
            <a href="?tab=analytics" class="ai-tab <?php echo $tab === 'analytics' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i>Analytics</a>
        </div>

        <?php if ($tab === 'skills'): ?>
            <?php if ($detail && $detailSkill): ?>
            <!-- Skill Detail -->
            <div class="grid gap-4">
                <div class="ai-content-card">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                        <h3 class="text-lg font-semibold text-slate-100"><?php echo htmlspecialchars($detailSkill['name']); ?></h3>
                        <div class="flex flex-wrap gap-2">
                            <span class="<?php echo badge($detailSkill['status']); ?>"><?php echo ucfirst($detailSkill['status']); ?></span>
                            <span class="ai-badge bg-slate-700/50 text-slate-400"><?php echo htmlspecialchars($detailSkill['skill_type']); ?></span>
                            <a href="?tab=skills" class="bg-slate-700 text-slate-300 hover:bg-slate-600 px-3 py-1.5 rounded-lg text-xs transition-colors"><i class="fas fa-arrow-left mr-1"></i>Back</a>
                        </div>
                    </div>
                    <p class="text-sm text-slate-400 mb-4"><?php echo htmlspecialchars($detailSkill['description'] ?? ''); ?></p>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                            <span class="text-sm text-slate-400">Module</span>
                            <span class="text-sm font-semibold text-slate-100"><?php echo htmlspecialchars($detailSkill['module'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                            <span class="text-sm text-slate-400">Version</span>
                            <span class="text-sm font-semibold text-slate-100">v<?php echo $detailSkill['version']; ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                            <span class="text-sm text-slate-400">Mode</span>
                            <span class="text-sm font-semibold text-slate-100"><?php echo ucfirst($detailSkill['mode']); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                            <span class="text-sm text-slate-400">Safety</span>
                            <span class="text-sm font-semibold text-slate-100"><?php echo ucfirst(str_replace('_', ' ', $detailSkill['safety_classification'])); ?></span>
                        </div>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-2">Steps</h4>
                    <div class="ai-table-wrapper">
                        <table class="ai-data-table">
                            <thead><tr><th>#</th><th>Type</th><th>Reference</th><th>Description</th></tr></thead>
                            <tbody>
                            <?php
                            $steps = json_decode($detailSkill['steps_json'], true) ?: [];
                            foreach ($steps as $i => $step): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><span class="ai-badge bg-slate-700/50 text-slate-400"><?php echo htmlspecialchars($step['type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($step['reference'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($step['description'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="start_run">
                            <input type="hidden" name="skill_id" value="<?php echo htmlspecialchars($detailSkill['skill_id']); ?>">
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm transition-colors"><i class="fas fa-play mr-1"></i>Start Skill</button>
                        </form>
                        <?php if ($detailSkill['status'] === 'draft'): ?>
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="update_skill">
                            <input type="hidden" name="skill_id" value="<?php echo htmlspecialchars($detailSkill['skill_id']); ?>">
                            <input type="hidden" name="status" value="testing">
                            <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-sm transition-colors"><i class="fas fa-flask mr-1"></i>Move to Testing</button>
                        </form>
                        <?php endif; ?>
                        <?php if (in_array($detailSkill['status'], ['draft', 'testing', 'approved'])): ?>
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="publish_skill">
                            <input type="hidden" name="skill_id" value="<?php echo htmlspecialchars($detailSkill['skill_id']); ?>">
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm transition-colors"><i class="fas fa-rocket mr-1"></i>Publish</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <?php if ($detailAnalytics): ?>
            <div class="ai-content-card mt-4">
                <h3 class="ai-section-title"><i class="fas fa-chart-bar"></i> Analytics (30 days)</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                        <span class="text-sm text-slate-400">Starts</span>
                        <span class="text-sm font-semibold text-slate-100"><?php echo $detailAnalytics['totals']['starts']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                        <span class="text-sm text-slate-400">Completed</span>
                        <span class="text-sm font-semibold text-slate-100"><?php echo $detailAnalytics['totals']['completed']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                        <span class="text-sm text-slate-400">Cancelled</span>
                        <span class="text-sm font-semibold text-slate-100"><?php echo $detailAnalytics['totals']['cancelled']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                        <span class="text-sm text-slate-400">Failed</span>
                        <span class="text-sm font-semibold text-slate-100"><?php echo $detailAnalytics['totals']['failed']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                        <span class="text-sm text-slate-400">Completion Rate</span>
                        <span class="text-sm font-semibold text-slate-100"><?php echo $detailAnalytics['totals']['completion_rate']; ?>%</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Runs -->
            <?php if (count($detailRuns) > 0): ?>
            <div class="ai-content-card mt-4">
                <h3 class="ai-section-title"><i class="fas fa-play"></i> Recent Runs</h3>
                <div class="ai-table-wrapper">
                    <table class="ai-data-table">
                        <thead><tr><th>Run ID</th><th>User</th><th>Status</th><th>Progress</th><th>Started</th></tr></thead>
                        <tbody>
                        <?php foreach ($detailRuns as $r): ?>
                            <tr>
                                <td><a href="?tab=runs&run=<?php echo $r['run_id']; ?>" class="text-sky-400 hover:text-sky-300 transition-colors"><?php echo substr($r['run_id'], 0, 12); ?>...</a></td>
                                <td><?php echo $r['user_id']; ?></td>
                                <td><span class="<?php echo badge($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td><?php echo $r['current_step']; ?>/<?php echo $r['total_steps']; ?></td>
                                <td><?php echo $r['started_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
        <!-- Skills List -->
        <div class="ai-content-card">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <h3 class="text-lg font-semibold text-slate-100">📋 All Skills (<?php echo count($skills); ?> published: <?php echo $publishedCount; ?>)</h3>
                <a href="?tab=builder" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-sm transition-colors"><i class="fas fa-plus mr-1"></i>Create Skill</a>
            </div>

            <?php if (count($suggestions) > 0): ?>
            <div class="ai-content-card bg-sky-500/10 border border-sky-500/20 mb-4">
                <strong class="text-sky-400">💡 Suggested for you:</strong>
                <div class="flex flex-wrap gap-2 mt-2">
                    <?php foreach ($suggestions as $s): ?>
                        <a href="?tab=skills&detail=<?php echo $s['skill_id']; ?>" class="bg-sky-500/15 text-sky-300 hover:bg-sky-500/25 px-3 py-1.5 rounded-lg text-xs transition-colors">
                            <?php echo htmlspecialchars($s['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($skills)): ?>
                <div class="ai-content-card text-center text-slate-400"><p>No skills created yet. Click "Create Skill" to start.</p></div>
            <?php else: ?>
            <div class="ai-table-wrapper">
                <table class="ai-data-table">
                    <thead>
                        <tr><th>Skill</th><th>Type</th><th>Module</th><th>Status</th><th>Mode</th><th>Safety</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($skills as $s): ?>
                        <tr>
                            <td>
                                <a href="?tab=skills&detail=<?php echo $s['skill_id']; ?>" class="text-sky-400 hover:text-sky-300 font-semibold transition-colors">
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </a>
                                <br><small class="text-slate-500"><?php echo htmlspecialchars($s['skill_id']); ?></small>
                            </td>
                            <td><span class="ai-badge bg-slate-700/50 text-slate-400"><?php echo $s['skill_type']; ?></span></td>
                            <td><?php echo htmlspecialchars($s['module'] ?? '-'); ?></td>
                            <td><span class="<?php echo badge($s['status']); ?>"><?php echo ucfirst($s['status']); ?></span></td>
                            <td><?php echo ucfirst($s['mode']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $s['safety_classification'])); ?></td>
                            <td>
                                <a href="?tab=skills&detail=<?php echo $s['skill_id']; ?>" class="bg-sky-600 hover:bg-sky-500 text-white px-3 py-1.5 rounded-lg text-xs transition-colors">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php elseif ($tab === 'builder'): ?>
    <!-- Skill Builder -->
    <div class="ai-content-card">
        <h3 class="text-lg font-semibold text-slate-100 mb-4">🔧 Create New Skill</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_skill">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="ai-form-group">
                    <label class="ai-form-label">Skill ID</label>
                    <input type="text" name="skill_id" required placeholder="e.g. procurement.review_pr" class="ai-form-input">
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Name</label>
                    <input type="text" name="name" required placeholder="e.g. Review Pending Requisitions" class="ai-form-input">
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Module</label>
                    <select name="module" class="ai-form-select">
                        <option value="">— None —</option>
                        <?php foreach ($modules as $m): ?>
                            <option value="<?php echo $m; ?>"><?php echo ucfirst($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Skill Type</label>
                    <select name="skill_type" class="ai-form-select">
                        <?php foreach ($skillTypes as $t): ?>
                            <option value="<?php echo $t; ?>"><?php echo ucfirst($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Mode</label>
                    <select name="mode" class="ai-form-select">
                        <option value="guide">Guide (explain only)</option>
                        <option value="assist">Assist (may take actions)</option>
                    </select>
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Safety Classification</label>
                    <select name="safety_classification" class="ai-form-select">
                        <option value="read_only">Read Only</option>
                        <option value="confirmation_required">Confirmation Required</option>
                    </select>
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Category</label>
                    <input type="text" name="category" placeholder="e.g. procurement" class="ai-form-input">
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Required Permissions (comma-separated)</label>
                    <input type="text" name="required_permissions" placeholder="e.g. procurement.requisition.view" class="ai-form-input">
                </div>
            </div>
            <div class="ai-form-group">
                <label class="ai-form-label">Description</label>
                <textarea name="description" placeholder="What does this skill do?" class="ai-form-textarea"></textarea>
            </div>
            <div class="ai-form-group">
                <label class="ai-form-label">Steps (JSON Array)</label>
                <textarea name="steps_json" rows="6" placeholder='[{"type":"navigation","reference":"procurement.requisitions","description":"Navigate to requisitions"},{"type":"analysis","description":"Analyze data"},{"type":"response","description":"Present findings"}]' class="ai-form-textarea"></textarea>
            </div>
            <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-sm transition-colors">Create Skill</button>
        </form>
    </div>

    <?php elseif ($tab === 'templates'): ?>
    <!-- Templates -->
    <div class="ai-content-card">
        <h3 class="text-lg font-semibold text-slate-100 mb-4">📄 Skill Templates</h3>
        <?php if (empty($templates)): ?>
            <div class="ai-content-card text-center text-slate-400"><p>No templates available.</p></div>
        <?php else: ?>
        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead><tr><th>Template</th><th>Type</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                    <tr>
                        <td><strong class="text-slate-100"><?php echo htmlspecialchars($t['name']); ?></strong></td>
                        <td><span class="ai-badge bg-slate-700/50 text-slate-400"><?php echo $t['template_type']; ?></span></td>
                        <td><?php echo htmlspecialchars($t['description']); ?></td>
                        <td>
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="action" value="create_from_template">
                                <input type="hidden" name="template_id" value="<?php echo $t['template_id']; ?>">
                                <input type="text" name="name" placeholder="Skill name" class="ai-form-input" style="width: 200px; padding: 4px 8px; border: 1px solid rgba(148,163,184,0.24); border-radius: 6px; font-size: 12px; background: rgba(15,23,42,0.70); color: #f1f5f9;">
                                <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-3 py-1.5 rounded-lg text-xs transition-colors">Use Template</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'runs'): ?>
    <!-- Runs -->
    <div class="ai-content-card">
        <?php if ($runDetail): ?>
        <h3 class="text-lg font-semibold text-slate-100 mb-4">▶️ Run: <?php echo substr($runDetail['run_id'], 0, 16); ?>...</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                <span class="text-sm text-slate-400">Skill</span>
                <span class="text-sm font-semibold text-slate-100"><?php echo htmlspecialchars($runDetail['skill_id']); ?></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                <span class="text-sm text-slate-400">Status</span>
                <span class="<?php echo badge($runDetail['status']); ?>"><?php echo ucfirst($runDetail['status']); ?></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                <span class="text-sm text-slate-400">Progress</span>
                <span class="text-sm font-semibold text-slate-100"><?php echo $runDetail['current_step']; ?>/<?php echo $runDetail['total_steps']; ?></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/50">
                <span class="text-sm text-slate-400">Started</span>
                <span class="text-sm font-semibold text-slate-100"><?php echo $runDetail['started_at']; ?></span>
            </div>
        </div>

        <?php if (!in_array($runDetail['status'], ['completed', 'cancelled', 'failed'])): ?>
        <form method="POST" class="mb-4">
            <input type="hidden" name="action" value="cancel_run">
            <input type="hidden" name="run_id" value="<?php echo $runDetail['run_id']; ?>">
            <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg text-sm transition-colors">Cancel Run</button>
        </form>
        <?php endif; ?>

        <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-2">Feedback</h4>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach (['helpful', 'not_helpful', 'too_long', 'incorrect'] as $r): ?>
            <form method="POST" class="inline-block">
                <input type="hidden" name="action" value="submit_feedback">
                <input type="hidden" name="run_id" value="<?php echo $runDetail['run_id']; ?>">
                <input type="hidden" name="rating" value="<?php echo $r; ?>">
                <button type="submit" class="bg-sky-500/15 text-sky-400 hover:bg-sky-500/25 px-3 py-1.5 rounded-lg text-xs transition-colors"><?php echo ucfirst(str_replace('_', ' ', $r)); ?></button>
            </form>
            <?php endforeach; ?>
        </div>

        <a href="?tab=runs" class="bg-slate-700 text-slate-300 hover:bg-slate-600 px-3 py-1.5 rounded-lg text-xs transition-colors inline-block"><i class="fas fa-arrow-left mr-1"></i>Back to Runs</a>

        <?php else: ?>
        <h3 class="text-lg font-semibold text-slate-100 mb-4">▶️ Skill Runs (<?php echo count($runs); ?>)</h3>
        <?php if (empty($runs)): ?>
            <div class="ai-content-card text-center text-slate-400"><p>No skill runs yet.</p></div>
        <?php else: ?>
        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead><tr><th>Run</th><th>Skill</th><th>User</th><th>Status</th><th>Progress</th><th>Started</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($runs as $r): ?>
                    <tr>
                        <td><a href="?tab=runs&run=<?php echo $r['run_id']; ?>" class="text-sky-400 hover:text-sky-300 transition-colors"><?php echo substr($r['run_id'], 0, 12); ?>...</a></td>
                        <td><?php echo htmlspecialchars($r['skill_name'] ?? $r['skill_id']); ?></td>
                        <td><?php echo $r['user_id']; ?></td>
                        <td><span class="<?php echo badge($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                        <td><?php echo $r['current_step']; ?>/<?php echo $r['total_steps']; ?></td>
                        <td><?php echo $r['started_at']; ?></td>
                        <td><a href="?tab=runs&run=<?php echo $r['run_id']; ?>" class="bg-sky-600 hover:bg-sky-500 text-white px-3 py-1.5 rounded-lg text-xs transition-colors">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php elseif ($tab === 'preferences'): ?>
    <!-- User Preferences -->
    <div class="ai-content-card">
        <h3 class="text-lg font-semibold text-slate-100 mb-2">⚙️ Your AI Preferences</h3>
        <p class="text-sm text-slate-400 mb-4">Control how the AI assistant behaves for you.</p>

        <?php if (empty($preferences)): ?>
            <div class="ai-content-card text-center text-slate-400"><p>No preferences set yet. Add one below.</p></div>
        <?php else: ?>
        <div class="grid gap-3">
            <?php foreach ($preferences as $p): ?>
            <div class="ai-content-card">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div>
                        <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($p['preference_key']); ?></span>
                        <span class="text-sm text-slate-400">— <?php echo htmlspecialchars($p['preference_value']); ?></span>
                        <small class="text-slate-500">(<?php echo $p['category']; ?>, via <?php echo $p['source']; ?>)</small>
                    </div>
                    <form method="POST" class="inline-block">
                        <input type="hidden" name="action" value="delete_preference">
                        <input type="hidden" name="pref_key" value="<?php echo htmlspecialchars($p['preference_key']); ?>">
                        <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs transition-colors">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mt-4 mb-2">Add Preference</h4>
        <form method="POST" class="mb-4">
            <input type="hidden" name="action" value="set_preference">
            <div class="flex flex-wrap gap-2">
                <input type="text" name="pref_key" placeholder="Key (e.g. response_length)" required class="ai-form-input" style="flex: 1; min-width: 160px;">
                <input type="text" name="pref_value" placeholder="Value (e.g. short)" required class="ai-form-input" style="flex: 1; min-width: 160px;">
                <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-sm transition-colors">Save</button>
            </div>
        </form>

        <?php if (count($preferences) > 0): ?>
        <form method="POST" class="mt-4">
            <input type="hidden" name="action" value="clear_preferences">
            <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg text-sm transition-colors" onclick="return confirm('Clear all preferences?')">Clear All Preferences</button>
        </form>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'analytics'): ?>
    <!-- Analytics Overview -->
    <div class="ai-content-card">
        <h3 class="ai-section-title"><i class="fas fa-chart-bar"></i> Skill Analytics</h3>
        <?php
        $allAnalytics = [];
        foreach ($skills as $s) {
            if ($s['status'] === 'published') {
                $allAnalytics[$s['skill_id']] = AISkillService::getAnalytics($db, $s['skill_id']);
            }
        }
        ?>
        <?php if (empty($allAnalytics)): ?>
            <div class="ai-content-card text-center text-slate-400"><p>No published skills with analytics data.</p></div>
        <?php else: ?>
        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead><tr><th>Skill</th><th>Starts</th><th>Completed</th><th>Failed</th><th>Completion Rate</th><th>Feedback</th></tr></thead>
                <tbody>
                <?php foreach ($allAnalytics as $sid => $a): ?>
                    <tr>
                        <td><a href="?tab=skills&detail=<?php echo $sid; ?>" class="text-sky-400 hover:text-sky-300 font-semibold transition-colors"><?php echo htmlspecialchars($sid); ?></a></td>
                        <td><?php echo $a['totals']['starts']; ?></td>
                        <td><?php echo $a['totals']['completed']; ?></td>
                        <td><?php echo $a['totals']['failed']; ?></td>
                        <td><?php echo $a['totals']['completion_rate']; ?>%</td>
                        <td><?php echo $a['totals']['feedback']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>
