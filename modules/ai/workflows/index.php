<?php
/**
 * AI F18 — Workflow Studio.
 */
$pageTitle = 'AI Workflow Studio';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIWorkflowService.php';
require_once AI_MODULE_DIR . '/includes/ai_ui.php';

if (!hasPermission('ai.workflows.view')) {
    die('Access Denied');
}
if (!AIKillSwitchService::isEnabled(getDB(), 'workflow_studio')) {
    die('Workflow Studio is currently disabled.');
}

$db = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$companyId = (int) ($_SESSION['company_id'] ?? 1);
$requestedWorkflowId = isset($_GET['workflow_id']) ? (int) $_GET['workflow_id'] : 0;

$workflows = AIWorkflowService::getWorkflows($db, $userId, ['company_id' => $companyId]);
$runs = AIWorkflowService::getRuns($db, null, 10);
$registry = AIWorkflowService::getNodeRegistry();
$templates = AIWorkflowService::getTemplates();
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>

<?php ai_render_module_nav('ai.workflows'); ?>

<?php ai_render_page_styles(); ?>

<div class="ai-page-header">
        <div class="flex justify-between items-start flex-wrap gap-4">
            <div>
                <h1 class="ai-page-title">
                    <span class="ai-page-icon"><i class="fas fa-diagram-project"></i></span>
                    AI Workflow Studio
                </h1>
                <p class="ai-page-subtitle">Create safe ERP automations with registered triggers, conditions, AI nodes, tasks, notifications, and approvals.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors" onclick="openNewWorkflow()">
                    <i class="fas fa-plus mr-1"></i>New Workflow
                </button>
                <button type="button" class="bg-slate-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-slate-600 transition-colors border border-slate-500" onclick="openDraftModal()">
                    <i class="fas fa-wand-magic-sparkles mr-1"></i>Generate Draft
                </button>
            </div>
        </div>
    </div>

<div class="ai-page-container">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
    <section class="xl:col-span-3 space-y-4">
        <div class="ai-content-card">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-200">Workflows</h2>
                <span class="text-xs text-slate-500"><?= count($workflows) ?> total</span>
            </div>
            <div id="workflowList" class="space-y-2 max-h-[720px] overflow-y-auto pr-1">
                <?php foreach ($workflows as $workflow): ?>
                    <button type="button" class="workflow-list-item w-full text-left border border-slate-600/40 rounded-xl p-3 hover:bg-slate-700/30 transition-colors" data-id="<?= (int) $workflow['id'] ?>" onclick="selectWorkflow(<?= (int) $workflow['id'] ?>)">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-sm text-slate-100"><?= htmlspecialchars($workflow['name']) ?></div>
                            <span class="text-[11px] uppercase tracking-wide px-2 py-1 rounded-full bg-slate-700 text-slate-300"><?= htmlspecialchars($workflow['status']) ?></span>
                        </div>
                        <div class="text-xs text-slate-400 mt-2">
                            <?= htmlspecialchars($workflow['module'] ?: 'ai') ?> · <?= htmlspecialchars($workflow['trigger_type']) ?> · v<?= (int) $workflow['current_version_number'] ?>
                        </div>
                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($workflow['ai_draft_summary'] ?? 'No AI draft metadata') ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ai-content-card">
            <h2 class="text-sm font-semibold text-slate-200 mb-3">Templates</h2>
            <div class="space-y-2">
                <?php foreach ($templates as $template): ?>
                    <button type="button" class="w-full text-left border border-slate-600/40 rounded-xl p-3 hover:bg-slate-700/30 transition-colors" onclick='loadTemplate(<?= json_encode($template, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        <div class="font-medium text-sm text-slate-100"><?= htmlspecialchars($template['name']) ?></div>
                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($template['description']) ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="xl:col-span-6 space-y-4">
        <div class="ai-content-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-100">Structured Visual Editor</h2>
                    <p class="text-xs text-slate-400">This studio uses an ordered visual flow editor instead of unrestricted scripting or arbitrary code.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="border border-slate-500 text-slate-200 px-3 py-2 rounded-lg text-sm hover:bg-slate-700/50 transition-colors" onclick="addNode()">Add Node</button>
                    <button type="button" class="border border-slate-500 text-slate-200 px-3 py-2 rounded-lg text-sm hover:bg-slate-700/50 transition-colors" onclick="runValidation()">Validate</button>
                    <button type="button" class="border border-slate-500 text-slate-200 px-3 py-2 rounded-lg text-sm hover:bg-slate-700/50 transition-colors" onclick="testWorkflow()">Test Dry-Run</button>
                    <button type="button" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors" onclick="saveWorkflow()">Save Draft</button>
                </div>
            </div>
            <div id="lifecycleActions" class="flex flex-wrap gap-2 mb-4"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="ai-form-label">Workflow Name</label>
                    <input id="workflowName" type="text" class="ai-form-input w-full" placeholder="Overdue requisition alert">
                </div>
                <div>
                    <label class="ai-form-label">Module</label>
                    <input id="workflowModule" type="text" class="ai-form-input w-full" placeholder="procurement">
                </div>
                <div class="md:col-span-2">
                    <label class="ai-form-label">Description</label>
                    <textarea id="workflowDescription" rows="2" class="ai-form-input w-full" placeholder="What should this workflow do?"></textarea>
                </div>
                <div>
                    <label class="ai-form-label">Trigger Type</label>
                    <select id="triggerType" class="ai-form-select w-full" onchange="renderTriggerConfig()">
                        <option value="manual">Manual</option>
                        <option value="event">Event</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>
                <div>
                    <label class="ai-form-label">Trigger Config</label>
                    <div id="triggerConfig" class="ai-content-card text-sm min-h-[3rem]"></div>
                </div>
            </div>

            <div id="validationBox" class="ai-alert hidden mb-4"></div>

            <div id="canvas" class="space-y-4"></div>
        </div>
    </section>

    <section class="xl:col-span-3 space-y-4">
        <div class="ai-content-card">
            <h2 class="text-sm font-semibold text-slate-200 mb-3">Node Registry</h2>
            <div class="space-y-2 max-h-[360px] overflow-y-auto pr-1">
                <?php foreach ($registry as $nodeType => $node): ?>
                    <div class="border border-slate-600/40 rounded-xl p-3">
                        <div class="font-medium text-sm text-slate-100"><?= htmlspecialchars($node['label']) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($node['category']) ?> · <?= htmlspecialchars($node['safety']) ?></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars(implode(', ', $node['inputs'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ai-content-card">
            <h2 class="text-sm font-semibold text-slate-200 mb-3">Recent Runs</h2>
            <div id="runList" class="space-y-2">
                <?php foreach ($runs as $run): ?>
                    <button type="button" class="w-full text-left border border-slate-600/40 rounded-xl p-3 hover:bg-slate-700/30 transition-colors" onclick="viewRun(<?= (int) $run['id'] ?>)">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-medium text-sm text-slate-100"><?= htmlspecialchars($run['workflow_name']) ?></div>
                            <span class="text-[11px] px-2 py-1 rounded-full bg-slate-700 text-slate-300"><?= htmlspecialchars($run['status']) ?></span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($run['trigger_type']) ?> · <?= htmlspecialchars($run['created_at']) ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="runDetails" class="ai-content-card hidden"></div>
    </section>
</div>
</div>

<div id="draftModal" class="hidden fixed inset-0 bg-black/70 z-50 items-center justify-center">
    <div class="ai-content-card w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-100">AI-Assisted Workflow Draft</h3>
            <button type="button" class="text-slate-400 hover:text-slate-200 transition-colors" onclick="closeDraftModal()">&times;</button>
        </div>
        <textarea id="draftPrompt" rows="5" class="ai-form-textarea w-full" placeholder="When a purchase requisition is pending for more than 7 days, notify the procurement manager and create a review task."></textarea>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" class="px-4 py-2 border border-slate-500 text-slate-200 rounded-lg text-sm hover:bg-slate-700/50 transition-colors" onclick="closeDraftModal()">Cancel</button>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors" onclick="generateDraft()">Generate Draft</button>
        </div>
    </div>
</div>

<script>
const registry = <?= json_encode($registry, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentWorkflowId = <?= $requestedWorkflowId > 0 ? $requestedWorkflowId : (!empty($workflows) ? (int) $workflows[0]['id'] : 'null') ?>;
let state = {
    name: '',
    description: '',
    module: 'ai',
    trigger_type: 'manual',
    trigger_config: { label: 'Manual run' },
    graph: { nodes: [], edges: [] },
    ai_draft_prompt: '',
    ai_draft_summary: '',
    available_actions: []
};

function openNewWorkflow() {
    currentWorkflowId = null;
    state = {
        name: '',
        description: '',
        module: 'ai',
        trigger_type: 'manual',
        trigger_config: { label: 'Manual run' },
        graph: {
            nodes: [
                { id: 'trigger_1', type: 'manual_trigger', name: 'Trigger', config: { label: 'Manual run' } },
                { id: 'end_1', type: 'end', name: 'End', config: {} }
            ],
            edges: [{ from: 'trigger_1', to: 'end_1', label: 'next' }]
        },
        ai_draft_prompt: '',
        ai_draft_summary: '',
        available_actions: []
    };
    hydrateForm();
    highlightSelection();
}

function hydrateForm() {
    document.getElementById('workflowName').value = state.name || '';
    document.getElementById('workflowDescription').value = state.description || '';
    document.getElementById('workflowModule').value = state.module || 'ai';
    document.getElementById('triggerType').value = state.trigger_type || 'manual';
    renderTriggerConfig();
    renderCanvas();
    renderLifecycleActions();
}

function buildPayloadFromForm() {
    state.name = document.getElementById('workflowName').value.trim();
    state.description = document.getElementById('workflowDescription').value.trim();
    state.module = document.getElementById('workflowModule').value.trim() || 'ai';
    state.trigger_type = document.getElementById('triggerType').value;
    state.trigger_config = collectTriggerConfig();
    state.graph.nodes = state.graph.nodes.map((node, index) => {
        const card = document.querySelector(`[data-node-index="${index}"]`);
        if (!card) return node;
        return {
            id: card.querySelector('[data-field="id"]').value.trim() || `node_${index + 1}`,
            type: card.querySelector('[data-field="type"]').value,
            name: card.querySelector('[data-field="name"]').value.trim() || 'Node',
            config: safeParseJson(card.querySelector('[data-field="config"]').value)
        };
    });
    state.graph.edges = [];
    for (let i = 0; i < state.graph.nodes.length - 1; i++) {
        state.graph.edges.push({ from: state.graph.nodes[i].id, to: state.graph.nodes[i + 1].id, label: 'next' });
    }
    return { ...state };
}

function renderTriggerConfig() {
    const type = document.getElementById('triggerType').value;
    const box = document.getElementById('triggerConfig');
    const config = state.trigger_config || {};
    if (type === 'manual') {
        box.innerHTML = `<label class="ai-form-label">Label</label><input id="triggerLabel" class="ai-form-input w-full" value="${escapeHtmlAttr(config.label || 'Manual run')}">`;
        return;
    }
    if (type === 'event') {
        box.innerHTML = `
            <label class="ai-form-label">Event Name</label>
            <input id="triggerEvent" class="ai-form-input w-full mb-2" value="${escapeHtmlAttr(config.event_name || 'procurement.requisition.created')}">
            <label class="ai-form-label">Filters (JSON)</label>
            <textarea id="triggerFilters" rows="3" class="ai-form-textarea w-full font-mono text-sm">${JSON.stringify(config.filters || {}, null, 2)}</textarea>
        `;
        return;
    }
    box.innerHTML = `
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="ai-form-label">Schedule Type</label>
                <select id="triggerScheduleType" class="ai-form-select w-full">
                    <option value="daily" ${config.schedule_type === 'daily' ? 'selected' : ''}>Daily</option>
                    <option value="weekly" ${config.schedule_type === 'weekly' ? 'selected' : ''}>Weekly</option>
                    <option value="interval" ${config.schedule_type === 'interval' ? 'selected' : ''}>Interval</option>
                </select>
            </div>
            <div>
                <label class="ai-form-label">Time</label>
                <input id="triggerTime" class="ai-form-input w-full" value="${escapeHtmlAttr(config.time || '09:00')}">
            </div>
            <div>
                <label class="ai-form-label">Day</label>
                <input id="triggerDay" class="ai-form-input w-full" value="${escapeHtmlAttr(config.day_of_week || 'monday')}">
            </div>
            <div>
                <label class="ai-form-label">Timezone</label>
                <input id="triggerTimezone" class="ai-form-input w-full" value="${escapeHtmlAttr(config.timezone || 'Asia/Dubai')}">
            </div>
            <div class="col-span-2">
                <label class="ai-form-label">Interval Minutes</label>
                <input id="triggerInterval" class="ai-form-input w-full" value="${escapeHtmlAttr(String(config.interval_minutes || 60))}">
            </div>
        </div>
    `;
}

function collectTriggerConfig() {
    const type = document.getElementById('triggerType').value;
    if (type === 'manual') {
        return { label: document.getElementById('triggerLabel')?.value.trim() || 'Manual run' };
    }
    if (type === 'event') {
        return {
            event_name: document.getElementById('triggerEvent')?.value.trim() || 'procurement.requisition.created',
            filters: safeParseJson(document.getElementById('triggerFilters')?.value || '{}')
        };
    }
    return {
        schedule_type: document.getElementById('triggerScheduleType')?.value || 'daily',
        time: document.getElementById('triggerTime')?.value.trim() || '09:00',
        day_of_week: document.getElementById('triggerDay')?.value.trim() || 'monday',
        timezone: document.getElementById('triggerTimezone')?.value.trim() || 'Asia/Dubai',
        interval_minutes: parseInt(document.getElementById('triggerInterval')?.value || '60', 10) || 60
    };
}

function renderCanvas() {
    const canvas = document.getElementById('canvas');
    canvas.innerHTML = '';
    state.graph.nodes.forEach((node, index) => {
        const card = document.createElement('div');
        card.className = 'ai-content-card mb-4';
        card.dataset.nodeIndex = index;
        card.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex w-8 h-8 rounded-full bg-sky-500/20 text-sky-400 items-center justify-center text-xs font-semibold">${index + 1}</span>
                    <div>
                        <div class="text-sm font-semibold text-slate-100">${escapeHtml(node.name || 'Node')}</div>
                        <div class="text-xs text-slate-400">${escapeHtml(registry[node.type]?.category || 'Workflow')}</div>
                    </div>
                </div>
                <button type="button" class="text-red-400 hover:text-red-300 text-xs transition-colors" onclick="removeNode(${index})">Remove</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="ai-form-label">Node ID</label>
                    <input data-field="id" class="ai-form-input w-full font-mono text-sm" value="${escapeHtmlAttr(node.id)}">
                </div>
                <div>
                    <label class="ai-form-label">Type</label>
                    <select data-field="type" class="ai-form-select w-full" onchange="changeNodeType(${index}, this.value)">
                        ${Object.keys(registry).map(type => `<option value="${type}" ${type === node.type ? 'selected' : ''}>${escapeHtml(type)}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="ai-form-label">Name</label>
                    <input data-field="name" class="ai-form-input w-full" value="${escapeHtmlAttr(node.name || '')}">
                </div>
                <div class="md:col-span-3">
                    <label class="ai-form-label">Config (JSON)</label>
                    <textarea data-field="config" rows="5" class="ai-form-textarea w-full font-mono text-sm">${JSON.stringify(node.config || {}, null, 2)}</textarea>
                </div>
            </div>
        `;
        canvas.appendChild(card);
        if (index < state.graph.nodes.length - 1) {
            const connector = document.createElement('div');
            connector.className = 'flex justify-center';
            connector.innerHTML = `<div class="text-xs text-slate-400 px-3 py-1 rounded-full bg-slate-700/50">then</div>`;
            canvas.appendChild(connector);
        }
    });
}

function addNode() {
    const index = state.graph.nodes.length + 1;
    state.graph.nodes.splice(Math.max(state.graph.nodes.length - 1, 0), 0, {
        id: `node_${index}`,
        type: 'field_condition',
        name: 'Condition',
        config: { field: 'status', operator: 'equals', value: 'Pending' }
    });
    renderCanvas();
}

function removeNode(index) {
    if (state.graph.nodes.length <= 2) return;
    state.graph.nodes.splice(index, 1);
    renderCanvas();
}

function changeNodeType(index, type) {
    const label = registry[type]?.label || type;
    state.graph.nodes[index].type = type;
    state.graph.nodes[index].name = label;
    state.graph.nodes[index].config = defaultConfigForType(type);
    renderCanvas();
}

function defaultConfigForType(type) {
    const defaults = {
        manual_trigger: { label: 'Manual run' },
        schedule_trigger: { schedule_type: 'daily', time: '09:00', timezone: 'Asia/Dubai' },
        event_trigger: { event_name: 'procurement.requisition.created', filters: {} },
        field_condition: { field: 'status', operator: 'equals', value: 'Pending' },
        threshold_condition: { metric: 'age_days', operator: 'greater_than_or_equal', value: 7 },
        read_tool: { tool_id: 'procurement.search_purchase_requisitions', input: { status: 'pending_approval' } },
        notification: { recipient_resolver: 'workflow_owner', title_template: 'Workflow Alert: {{workflow.name}}', summary_template: 'Workflow condition matched for record {{record_id}}.', severity: 'medium' },
        create_task: { title_template: 'Workflow follow-up', description_template: 'Created from workflow {{workflow.name}}.', assignee_resolver: 'workflow_owner' },
        approval_request: { title_template: 'Workflow approval required', summary_template: 'Please review workflow {{workflow.name}}.', approver_resolver: 'workflow_owner' },
        delay: { hours: 24 },
        wait_until: { datetime: '2026-09-02 09:00:00' },
        ai_analysis: { analysis_type: 'classify_urgency', field: 'status' },
        knowledge_search: { query: 'procurement approval policy', collection_id: null },
        if_else: {},
        end: {}
    };
    return defaults[type] || {};
}

async function selectWorkflow(id) {
    const response = await fetch(`<?= url('modules/ai/api/workflows.php?action=get&id=') ?>${id}`);
    const data = await response.json();
    if (!data.success || !data.workflow) {
        alert(data.error || 'Unable to load workflow.');
        return;
    }
    currentWorkflowId = id;
    state = {
        name: data.workflow.name || '',
        description: data.workflow.description || '',
        module: data.workflow.module || 'ai',
        trigger_type: data.workflow.trigger_type || 'manual',
        trigger_config: data.workflow.trigger_config || { label: 'Manual run' },
        graph: data.workflow.graph || { nodes: [], edges: [] },
        ai_draft_prompt: data.workflow.ai_draft_prompt || '',
        ai_draft_summary: data.workflow.ai_draft_summary || '',
        available_actions: data.workflow.available_actions || []
    };
    hydrateForm();
    highlightSelection();
    showValidation(data.workflow.validation || null);
}

function highlightSelection() {
    document.querySelectorAll('.workflow-list-item').forEach(el => {
        el.classList.toggle('ring-2', currentWorkflowId && parseInt(el.dataset.id, 10) === currentWorkflowId);
        el.classList.toggle('ring-blue-300', currentWorkflowId && parseInt(el.dataset.id, 10) === currentWorkflowId);
    });
}

async function saveWorkflow() {
    const payload = buildPayloadFromForm();
    const url = currentWorkflowId
        ? '<?= url('modules/ai/api/workflows.php?action=update') ?>'
        : '<?= url('modules/ai/api/workflows.php?action=create') ?>';
    if (currentWorkflowId) payload.id = currentWorkflowId;

    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!data.success) {
        showValidation(data.validation || { errors: [data.error || 'Save failed.'], warnings: [] });
        return;
    }
    currentWorkflowId = data.workflow_id;
    await refreshList();
    await selectWorkflow(currentWorkflowId);
}

async function runValidation() {
    buildPayloadFromForm();
    const response = await fetch('<?= url('modules/ai/api/workflows.php?action=validate') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ graph: state.graph, trigger_type: state.trigger_type, trigger_config: state.trigger_config })
    });
    const data = await response.json();
    if (data.success && data.validation) {
        const validation = data.validation;
        if (!validation.errors?.length) {
            validation.warnings = [...(validation.warnings || []), 'Validation passed. Saving will create a draft version.'];
        }
        showValidation(validation);
        return;
    }
    showValidation(data.validation || { errors: [data.error || 'Validation failed.'], warnings: [] });
}

async function testWorkflow() {
    if (!currentWorkflowId) {
        alert('Save the workflow first.');
        return;
    }
    const response = await fetch('<?= url('modules/ai/api/workflows.php?action=test') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: currentWorkflowId,
            trigger_context: { status: 'Pending', age_days: 10, record_id: 101, amount: 15000 }
        })
    });
    const data = await response.json();
    if (!data.success) {
        alert(data.error || 'Test failed.');
        return;
    }
    alert((data.message || 'Test complete.') + '\n\n' + data.timeline.map(step => `${step.node_type}: ${step.summary}`).join('\n'));
    await refreshList();
}

async function generateDraft() {
    const prompt = document.getElementById('draftPrompt').value.trim();
    if (!prompt) return;
    const response = await fetch('<?= url('modules/ai/api/workflows.php?action=generate') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt, module: document.getElementById('workflowModule').value.trim() || 'ai', persist: true })
    });
    const data = await response.json();
    if (!data.success) {
        alert(data.error || 'Draft generation failed.');
        return;
    }
    closeDraftModal();
    await refreshList();
    await selectWorkflow(data.workflow_id);
}

function openDraftModal() {
    document.getElementById('draftModal').classList.remove('hidden');
    document.getElementById('draftModal').classList.add('flex');
}

function closeDraftModal() {
    document.getElementById('draftModal').classList.add('hidden');
    document.getElementById('draftModal').classList.remove('flex');
}

async function refreshList() {
    const response = await fetch('<?= url('modules/ai/api/workflows.php?action=list') ?>');
    const data = await response.json();
    if (!data.success) return;
    const list = document.getElementById('workflowList');
    list.innerHTML = (data.workflows || []).map(workflow => `
        <button type="button" class="workflow-list-item w-full text-left border border-slate-600/40 rounded-xl p-3 hover:bg-slate-700/30 transition-colors" data-id="${workflow.id}" onclick="selectWorkflow(${workflow.id})">
            <div class="flex items-center justify-between gap-3">
                <div class="font-medium text-sm text-slate-100">${escapeHtml(workflow.name)}</div>
                <span class="text-[11px] uppercase tracking-wide px-2 py-1 rounded-full bg-slate-700 text-slate-300">${escapeHtml(workflow.status)}</span>
            </div>
            <div class="text-xs text-slate-400 mt-2">${escapeHtml(workflow.module || 'ai')} · ${escapeHtml(workflow.trigger_type)} · v${workflow.current_version_number}</div>
            <div class="text-xs text-slate-500 mt-1">${escapeHtml(workflow.ai_draft_summary || 'No AI draft metadata')}</div>
        </button>
    `).join('');
    const runList = document.getElementById('runList');
    runList.innerHTML = (data.runs || []).map(run => `
        <button type="button" class="w-full text-left border border-slate-600/40 rounded-xl p-3 hover:bg-slate-700/30 transition-colors" onclick="viewRun(${run.id})">
            <div class="flex items-center justify-between gap-2">
                <div class="font-medium text-sm text-slate-100">${escapeHtml(run.workflow_name)}</div>
                <span class="text-[11px] px-2 py-1 rounded-full bg-slate-700 text-slate-300">${escapeHtml(run.status)}</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">${escapeHtml(run.trigger_type)} · ${escapeHtml(run.created_at)}</div>
        </button>
    `).join('');
    highlightSelection();
}

function renderLifecycleActions() {
    const box = document.getElementById('lifecycleActions');
    const labels = {
        mark_testing: 'Move To Testing',
        approve: 'Approve',
        publish: 'Publish',
        enable: 'Enable',
        disable: 'Disable',
        archive: 'Archive'
    };
    if (!currentWorkflowId || !state.available_actions?.length) {
        box.innerHTML = '';
        return;
    }
    box.innerHTML = state.available_actions.map(action => `
        <button type="button" class="px-3 py-2 border border-slate-500 text-slate-200 rounded-lg text-sm hover:bg-slate-700/50 transition-colors" onclick="changeWorkflowStatus('${action}')">${escapeHtml(labels[action] || action)}</button>
    `).join('');
}

async function changeWorkflowStatus(action) {
    if (!currentWorkflowId) return;
    const response = await fetch('<?= url('modules/ai/api/workflows.php?action=status') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentWorkflowId, workflow_action: action })
    });
    const data = await response.json();
    if (!data.success) {
        alert(data.error || 'Unable to update workflow status.');
        return;
    }
    await refreshList();
    await selectWorkflow(currentWorkflowId);
}

function showValidation(validation) {
    const box = document.getElementById('validationBox');
    if (!validation) {
        box.classList.add('hidden');
        return;
    }
    const errors = validation.errors || [];
    const warnings = validation.warnings || [];
    box.classList.remove('hidden');
    box.className = 'ai-alert mb-4 ' + (errors.length ? 'ai-alert-danger' : 'ai-alert-success');
    box.innerHTML = `
        <div class="font-semibold mb-2">${errors.length ? 'Validation issues' : 'Validation passed'}</div>
        ${errors.length ? `<div>${errors.map(item => `• ${escapeHtml(item)}`).join('<br>')}</div>` : ''}
        ${warnings.length ? `<div class="mt-2 text-amber-400">${warnings.map(item => `• ${escapeHtml(item)}`).join('<br>')}</div>` : ''}
    `;
}

function loadTemplate(template) {
    currentWorkflowId = null;
    state = {
        name: template.name,
        description: template.description,
        module: 'ai',
        trigger_type: template.trigger_type,
        trigger_config: template.graph.nodes[0]?.config || { label: 'Manual run' },
        graph: template.graph,
        ai_draft_prompt: '',
        ai_draft_summary: 'Loaded from system template.',
        available_actions: []
    };
    hydrateForm();
    highlightSelection();
}

async function viewRun(runId) {
    const response = await fetch('<?= url('modules/ai/api/workflows.php?action=run_get&run_id=') ?>' + runId);
    const data = await response.json();
    if (!data.success || !data.run) return;
    const box = document.getElementById('runDetails');
    box.classList.remove('hidden');
    box.innerHTML = `
        <h3 class="text-sm font-semibold text-slate-200 mb-3">Run Details</h3>
        <div class="text-sm text-slate-100 font-medium mb-1">${escapeHtml(data.run.workflow_name)}</div>
        <div class="text-xs text-slate-400 mb-3">${escapeHtml(data.run.status)} · ${escapeHtml(data.run.trigger_type)} · ${escapeHtml(data.run.created_at)}</div>
        <div class="space-y-2">
            ${(data.run.node_history || []).map(node => `
                <div class="border border-slate-600/40 rounded-xl p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-medium text-slate-100">${escapeHtml(node.node_type)}</div>
                        <span class="text-[11px] px-2 py-1 rounded-full bg-slate-700 text-slate-300">${escapeHtml(node.status)}</span>
                    </div>
                    <div class="text-xs text-slate-400 mt-1">${escapeHtml(node.safe_output_summary || '')}</div>
                </div>
            `).join('')}
        </div>
    `;
}

function safeParseJson(value) {
    try {
        return JSON.parse(value || '{}');
    } catch (error) {
        return {};
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[s]));
}

function escapeHtmlAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
}

<?php if ($requestedWorkflowId > 0): ?>
selectWorkflow(<?= $requestedWorkflowId ?>);
<?php elseif (!empty($workflows)): ?>
selectWorkflow(<?= (int) $workflows[0]['id'] ?>);
<?php else: ?>
openNewWorkflow();
<?php endif; ?>
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
