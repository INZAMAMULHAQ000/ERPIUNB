<?php
/**
 * AI Automation — Automation Management UI.
 */
$pageTitle = 'AI Automations';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIAutomationService.php';

if (!hasPermission('ai.assistant.access')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];
$automations = AIAutomationService::getUserAutomations($db, $userId);
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_page_styles(); ?>
<?php ai_render_module_nav('ai.automations'); ?>

<div class="ai-page-container">
    <div class="ai-page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-bolt"></i></span>
                AI Automations
            </h1>
            <p class="ai-page-subtitle">Create reusable multi-step automation workflows</p>
        </div>
        <button onclick="showCreateModal()" style="background:var(--ai-accent);color:#0f172a;border:none;cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;">
            <i class="fas fa-plus mr-1"></i>New Automation
        </button>
    </div>

    <?php if (empty($automations)): ?>
    <div class="ai-content-card text-center">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(148,163,184,0.12)">
            <i class="fas fa-bolt text-2xl" style="color:var(--ai-text-muted)"></i>
        </div>
        <h3 class="text-lg font-semibold mb-2" style="color:var(--ai-text-primary)">No Automations Yet</h3>
        <p class="mb-4" style="color:var(--ai-text-secondary)">Create an automation to save reusable task workflows.</p>
        <button onclick="showCreateModal()" style="background:var(--ai-accent);color:#0f172a;border:none;cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;">
            <i class="fas fa-plus mr-1"></i>Create Automation
        </button>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($automations as $auto): ?>
        <div class="ai-content-card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold" style="color:var(--ai-text-primary)"><?= sanitize($auto['name']) ?></h3>
                <span class="ai-badge <?= $auto['is_active'] ? 'ai-badge-success' : 'ai-badge' ?>">
                    <?= $auto['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <?php if ($auto['description']): ?>
            <p class="text-sm mb-3" style="color:var(--ai-text-secondary)"><?= sanitize(substr($auto['description'], 0, 100)) ?></p>
            <?php endif; ?>
            <div class="text-xs mb-3" style="color:var(--ai-text-muted)">
                <span class="mr-3"><i class="fas fa-bolt mr-1"></i><?= ucfirst($auto['trigger_type']) ?></span>
                <?php if ($auto['last_run_at']): ?>
                <span><i class="fas fa-clock mr-1"></i>Last run: <?= date('M j', strtotime($auto['last_run_at'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <button onclick="runAutomation(<?= $auto['id'] ?>)" class="flex-1 text-white px-3 py-1.5 rounded text-xs hover:opacity-90" style="background:var(--ai-success);color:#0f172a">
                    <i class="fas fa-play mr-1"></i>Run
                </button>
                <button onclick="toggleAutomation(<?= $auto['id'] ?>)" class="px-3 py-1.5 rounded text-xs" style="background:var(--ai-input-bg);color:var(--ai-text-secondary);border:1px solid var(--ai-input-border)">
                    <i class="fas fa-power-off"></i>
                </button>
                <button onclick="deleteAutomation(<?= $auto['id'] ?>)" class="px-3 py-1.5 rounded text-xs" style="background:var(--ai-danger);color:#0f172a;border:1px solid var(--ai-danger)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Create Automation Modal -->
    <div id="createModal" class="hidden fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.6)">
        <div class="ai-content-card max-w-lg w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4" style="color:var(--ai-text-primary)">Create Automation</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--ai-text-secondary)">Name</label>
                        <input type="text" id="autoName" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border);color:var(--ai-text-primary)" placeholder="e.g., Weekly PR Report">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--ai-text-secondary)">Description</label>
                        <textarea id="autoDesc" rows="2" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border);color:var(--ai-text-primary)" placeholder="What does this automation do?"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--ai-text-secondary)">Workflow Steps (JSON)</label>
                        <textarea id="autoWorkflow" rows="6" class="w-full rounded-lg px-3 py-2 text-sm font-mono" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border);color:var(--ai-text-primary)" placeholder='{"steps":[{"step":1,"type":"read","title":"Search PRs","tool_id":"procurement.search_purchase_requisitions","input":{}},{"step":2,"type":"reasoning","title":"Summarize results"}]}'></textarea>
                        <p class="text-xs mt-1" style="color:var(--ai-text-muted)">Define steps as read (tool), action, or reasoning steps.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 flex justify-end gap-3" style="border-top:1px solid var(--ai-border)">
                <button onclick="hideCreateModal()" style="background:var(--ai-input-bg);color:var(--ai-text-secondary);border:1px solid var(--ai-input-border);cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;">Cancel</button>
                <button onclick="createAutomation()" style="background:var(--ai-accent);color:#0f172a;border:none;cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;">Create</button>
            </div>
        </div>
    </div>

    <script>
function showCreateModal() { document.getElementById('createModal').classList.remove('hidden'); }
function hideCreateModal() { document.getElementById('createModal').classList.add('hidden'); }

async function createAutomation() {
    const name = document.getElementById('autoName').value.trim();
    const desc = document.getElementById('autoDesc').value.trim();
    const workflowText = document.getElementById('autoWorkflow').value.trim();

    if (!name) { alert('Name is required.'); return; }

    let workflow;
    try {
        workflow = JSON.parse(workflowText || '{"steps":[]}');
    } catch (e) {
        alert('Invalid workflow JSON: ' + e.message);
        return;
    }

    const resp = await fetch('<?= url('modules/ai/api/automations.php?action=create') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, description: desc, trigger_type: 'manual', workflow })
    });
    const data = await resp.json();

    if (data.success) {
        location.reload();
    } else {
        alert(data.error || 'Failed to create automation.');
    }
}

async function runAutomation(id) {
    if (!confirm('Run this automation?')) return;

    const resp = await fetch('<?= url('modules/ai/api/automations.php?action=run') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await resp.json();

    if (data.success) {
        window.location.href = '<?= url('modules/ai/tasks/view.php?id=') ?>' + data.task_id;
    } else {
        alert(data.error || 'Failed to run automation.');
    }
}

async function toggleAutomation(id) {
    await fetch('<?= url('modules/ai/api/automations.php?action=toggle') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    location.reload();
}

async function deleteAutomation(id) {
    if (!confirm('Delete this automation?')) return;
    await fetch('<?= url('modules/ai/api/automations.php?action=delete') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    location.reload();
}
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
