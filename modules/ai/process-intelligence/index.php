<?php
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.process_intelligence.view')) {
    die('Access Denied');
}
if (!AIKillSwitchService::isEnabled(getDB(), 'process_intelligence')) {
    die('Process Intelligence is currently disabled.');
}

$api = rtrim(BASE_URL ?? '', '/') . '/modules/ai/api/process_intelligence.php';
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Process Intelligence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: #0b1220; color: #f1f5f9; }
        .btn { border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: var(--ai-accent); color: #0f172a; }
        .btn-secondary { background: rgba(148, 163, 184, 0.15); color: var(--ai-text-primary); border: 1px solid var(--ai-border); }
        .panel { display: none; }
        .panel.active { display: block; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .metric { font-size: 30px; font-weight: 700; color: var(--ai-accent); margin-top: 8px; }
        .muted { color: var(--ai-text-muted); font-size: 13px; }
        .list { display: grid; gap: 12px; }
        .item { border: 1px solid var(--ai-border); border-radius: 12px; padding: 14px; background: var(--ai-card-bg); }
        .severity-high, .severity-critical { border-left: 4px solid #f87171; }
        .severity-medium { border-left: 4px solid #fbbf24; }
        .severity-low { border-left: 4px solid #38bdf8; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .chip { background: rgba(148, 163, 184, 0.12); color: var(--ai-text-secondary); border-radius: 999px; padding: 4px 10px; font-size: 12px; }
        .query { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .query input, .query select { flex: 1; min-width: 200px; padding: 11px 12px; border-radius: 10px; border: 1px solid var(--ai-input-border); background: var(--ai-input-bg); color: var(--ai-text-primary); }
        .query button { padding: 11px 16px; }
        .table-wrap { overflow: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid var(--ai-border); font-size: 13px; vertical-align: top; color: var(--ai-text-secondary); }
        th { color: var(--ai-text-muted); background: rgba(15, 23, 42, 0.55); }
        .notice { background: rgba(251, 191, 36, 0.12); border: 1px solid rgba(251, 191, 36, 0.35); color: #fbbf24; padding: 12px; border-radius: 12px; font-size: 13px; }
        @media (max-width: 720px) {
            .ai-page-header { padding: 18px; }
            .ai-page-container { padding: 14px; }
        }
    </style>
</head>
<body class="ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.process_intelligence'); ?>
    <?php ai_render_page_styles(); ?>
    <div class="ai-page-container">
        <div class="ai-page-header">
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-diagram-project"></i></span>
                AI Process Intelligence
            </h1>
            <p class="ai-page-subtitle">Grounded process discovery, bottleneck analysis, SLA tracking, recommendations, simulations, and workflow proposals. This module analyzes authorized ERP lifecycle data and never changes production workflows silently.</p>
            <div class="toolbar">
                <button class="btn btn-primary" onclick="refreshProcessData()"><i class="fas fa-arrows-rotate"></i> Refresh Process Data</button>
                <button class="btn btn-secondary" onclick="runQuickQuery('Where are our biggest process bottlenecks?')"><i class="fas fa-search"></i> Ask a Process Question</button>
            </div>
        </div>

        <div class="ai-tabs">
            <button class="ai-tab active" data-panel="overview" onclick="showPanel('overview', this)">Overview</button>
            <button class="ai-tab" data-panel="explorer" onclick="showPanel('explorer', this)">Process Explorer</button>
            <button class="ai-tab" data-panel="maps" onclick="showPanel('maps', this)">Process Maps</button>
            <button class="ai-tab" data-panel="bottlenecks" onclick="showPanel('bottlenecks', this)">Bottlenecks</button>
            <button class="ai-tab" data-panel="comparisons" onclick="showPanel('comparisons', this)">Comparisons</button>
            <button class="ai-tab" data-panel="recommendations" onclick="showPanel('recommendations', this)">Recommendations</button>
            <button class="ai-tab" data-panel="simulations" onclick="showPanel('simulations', this)">Simulations</button>
            <button class="ai-tab" data-panel="history" onclick="showPanel('history', this)">History</button>
        </div>

        <div class="ai-content-card">
            <div class="query">
                <input id="question" type="text" placeholder="e.g. Why are purchase approvals taking so long?" onkeydown="if(event.key==='Enter')submitQuestion()">
                <button class="btn btn-primary" onclick="submitQuestion()">Analyze</button>
            </div>
            <div id="questionResult" class="notice">Process questions route here through controlled repositories only. Arbitrary SQL or hidden workflow execution is rejected.</div>
        </div>

        <section class="panel active" id="panel-overview"></section>
        <section class="panel" id="panel-explorer"></section>
        <section class="panel" id="panel-maps"></section>
        <section class="panel" id="panel-bottlenecks"></section>
        <section class="panel" id="panel-comparisons"></section>
        <section class="panel" id="panel-recommendations"></section>
        <section class="panel" id="panel-simulations"></section>
        <section class="panel" id="panel-history"></section>
    </div>

    <script>
    const API = <?= json_encode($api) ?>;

    function showPanel(name, button) {
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.ai-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('panel-' + name).classList.add('active');
        if (button) button.classList.add('active');
        if (name === 'overview') loadOverview();
        if (name === 'explorer') loadExplorer();
        if (name === 'maps') loadMap();
        if (name === 'bottlenecks') loadBottlenecks();
        if (name === 'comparisons') loadComparisons();
        if (name === 'recommendations') loadRecommendations();
        if (name === 'simulations') renderSimulationPanel();
        if (name === 'history') loadHistory();
    }

    async function api(action, data = {}) {
        const fd = new FormData();
        fd.append('action', action);
        Object.entries(data).forEach(([key, value]) => {
            fd.append(key, typeof value === 'string' ? value : JSON.stringify(value));
        });
        const response = await fetch(API, { method: 'POST', body: fd });
        return response.json();
    }

    function safe(text) {
        return String(text ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'": '&#39;'}[s]));
    }

    async function refreshProcessData() {
        const result = await api('refresh');
        document.getElementById('questionResult').innerHTML = `<strong>Refresh:</strong> ${safe(result.error || ('Processed ' + result.events_processed + ' events and rebuilt ' + result.runs_rebuilt + ' process instances.'))}`;
        loadOverview();
    }

    async function submitQuestion() {
        const text = document.getElementById('question').value.trim();
        if (!text) return;
        const result = await api('query', { text });
        document.getElementById('questionResult').innerHTML = renderQuestionResult(result);
    }

    function runQuickQuery(text) {
        document.getElementById('question').value = text;
        submitQuestion();
    }

    async function loadOverview() {
        const result = await api('overview');
        const overview = result.overview || {};
        const totals = overview.totals || {};
        const processCards = (overview.processes || []).map(row => `
            <div class="item">
                <strong>${safe(row.process_key)}</strong>
                <div class="chips">
                    <span class="chip">${safe(row.total_runs)} instances</span>
                    <span class="chip">${safe(Number(row.avg_cycle_hours || 0).toFixed(2))} avg hrs</span>
                    <span class="chip">${safe(row.breached_runs)} SLA breaches</span>
                </div>
            </div>
        `).join('');
        const findings = (overview.recent_findings || []).map(renderFinding).join('');
        document.getElementById('panel-overview').innerHTML = `
            <div class="grid">
                <div class="ai-content-card"><div class="muted">Process Definitions</div><div class="metric">${safe(totals.definitions || 0)}</div></div>
                <div class="ai-content-card"><div class="muted">Normalized Events</div><div class="metric">${safe(totals.events || 0)}</div></div>
                <div class="ai-content-card"><div class="muted">Process Instances</div><div class="metric">${safe(totals.instances || 0)}</div></div>
                <div class="ai-content-card"><div class="muted">Open Findings</div><div class="metric">${safe(totals.open_findings || 0)}</div></div>
            </div>
            <div class="ai-content-card">
                <h3>Process Health</h3>
                <div class="list">${processCards || '<div class="muted">No discovered processes yet.</div>'}</div>
            </div>
            <div class="ai-content-card">
                <h3>Recent Bottlenecks</h3>
                <div class="list">${findings || '<div class="muted">No findings detected.</div>'}</div>
            </div>
        `;
    }

    async function loadExplorer() {
        const result = await api('explorer', { limit: 50 });
        const rows = result.runs || [];
        document.getElementById('panel-explorer').innerHTML = `
            <div class="ai-content-card table-wrap">
                <table>
                    <thead><tr><th>Process</th><th>Instance</th><th>Status</th><th>Cycle Hours</th><th>Approval Hours</th><th>SLA</th><th>Last Event</th></tr></thead>
                    <tbody>
                        ${rows.map(row => `<tr>
                            <td>${safe(row.process_key)}</td>
                            <td>${safe(row.process_instance_id)}</td>
                            <td>${safe(row.run_status)}</td>
                            <td>${safe(row.cycle_time_hours)}</td>
                            <td>${safe(row.approval_time_hours)}</td>
                            <td>${safe(row.sla_status)}</td>
                            <td>${safe(row.last_event_name)}<div class="muted">${safe(row.last_event_at)}</div></td>
                        </tr>`).join('') || '<tr><td colspan="7">No process runs found.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function loadMap() {
        const result = await api('map');
        const map = result.map || {};
        document.getElementById('panel-maps').innerHTML = `
            <div class="ai-content-card">
                <h3>Transitions</h3>
                <div class="list">
                    ${(map.transitions || []).map(row => `
                        <div class="item">
                            <strong>${safe(row.from)} → ${safe(row.to)}</strong>
                            <div class="chips">
                                <span class="chip">${safe(row.frequency)} times</span>
                                <span class="chip">Avg ${safe(row.average_duration_hours)} hrs</span>
                                <span class="chip">Median ${safe(row.median_duration_hours)} hrs</span>
                                <span class="chip">P90 ${safe(row.p90_duration_hours)} hrs</span>
                            </div>
                        </div>
                    `).join('') || '<div class="muted">No transitions available.</div>'}
                </div>
            </div>
        `;
    }

    async function loadBottlenecks() {
        const result = await api('bottlenecks');
        const findings = result.findings || [];
        document.getElementById('panel-bottlenecks').innerHTML = `
            <div class="ai-content-card">
                <div class="list">${findings.map(renderFinding).join('') || '<div class="muted">No bottlenecks detected.</div>'}</div>
            </div>
        `;
    }

    async function loadComparisons() {
        const result = await api('compare', { dimension: 'department' });
        const rows = result.comparison?.rows || [];
        document.getElementById('panel-comparisons').innerHTML = `
            <div class="ai-content-card table-wrap">
                <table>
                    <thead><tr><th>Department</th><th>Instances</th><th>Avg Cycle Hours</th><th>Avg Approval Hours</th><th>SLA Breaches</th></tr></thead>
                    <tbody>
                        ${rows.map(row => `<tr>
                            <td>${safe(row.dimension_value || 'Unassigned')}</td>
                            <td>${safe(row.instance_count)}</td>
                            <td>${safe(Number(row.avg_cycle_hours || 0).toFixed(2))}</td>
                            <td>${safe(Number(row.avg_approval_hours || 0).toFixed(2))}</td>
                            <td>${safe(row.breach_count)}</td>
                        </tr>`).join('') || '<tr><td colspan="5">No comparison data found.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function loadRecommendations() {
        const result = await api('recommendations');
        const rows = result.recommendations || [];
        document.getElementById('panel-recommendations').innerHTML = `
            <div class="ai-content-card">
                <div class="list">
                    ${rows.map(row => `
                        <div class="item">
                            <strong>${safe(row.title)}</strong>
                            <div class="muted">${safe(row.recommendation_text)}</div>
                            <div class="chips">
                                <span class="chip">${safe(row.process_key)}</span>
                                <span class="chip">${safe(row.priority_level)} priority</span>
                                <span class="chip">Advisory only</span>
                            </div>
                            <div class="toolbar" style="margin-top:10px;">
                                <button class="btn btn-primary" onclick="createTask(${Number(row.id)})">Convert to F25 Task</button>
                                <button class="btn btn-secondary" style="color:var(--ai-accent);background:var(--ai-badge-info-bg);border-color:var(--ai-accent);" onclick="createProposal(${Number(row.id)})">Prepare Proposal</button>
                            </div>
                        </div>
                    `).join('') || '<div class="muted">No recommendations yet.</div>'}
                </div>
            </div>
        `;
    }

    function renderSimulationPanel() {
        document.getElementById('panel-simulations').innerHTML = `
            <div class="ai-content-card">
                <h3>Run What-If Simulation</h3>
                <div class="query">
                    <input id="simName" type="text" value="Approval queue reduction scenario" placeholder="Scenario name">
                    <input id="approvalReduction" type="number" value="20" placeholder="Approval reduction %">
                    <input id="queueReduction" type="number" value="15" placeholder="Queue reduction %">
                    <input id="reworkReduction" type="number" value="10" placeholder="Rework reduction %">
                    <input id="volumeChange" type="number" value="0" placeholder="Volume change %">
                    <button class="btn btn-primary" onclick="runSimulation()">Simulate</button>
                </div>
                <div id="simulationResult" class="notice">Simulation runs against historical process snapshots only. ERP production records remain unchanged.</div>
            </div>
        `;
    }

    async function runSimulation() {
        const variables = {
            approval_reduction_pct: Number(document.getElementById('approvalReduction').value || 0),
            queue_reduction_pct: Number(document.getElementById('queueReduction').value || 0),
            rework_reduction_pct: Number(document.getElementById('reworkReduction').value || 0),
            volume_change_pct: Number(document.getElementById('volumeChange').value || 0)
        };
        const result = await api('simulate', { name: document.getElementById('simName').value, variables });
        document.getElementById('simulationResult').innerHTML = result.success
            ? (() => {
                const range = result.historical_range || {};
                const uncertainty = result.uncertainty || {};
                const minCycle = Number(uncertainty.cycle_time_range_hours?.min ?? 0).toFixed(2);
                const maxCycle = Number(uncertainty.cycle_time_range_hours?.max ?? 0).toFixed(2);
                const confidenceScore = Number(uncertainty.confidence_score ?? 0).toFixed(2);
                return `
                    <strong>${safe(result.simulation_name)}:</strong>
                    baseline ${safe(Number(result.baseline.avg_cycle_hours || 0).toFixed(2))} hrs,
                    simulated ${safe(result.simulated_cycle_hours)} hrs (${safe(result.impact_percentage)}%).
                    <div class="muted">Method: ${safe(result.method || 'Historical-average adjustment')}</div>
                    <div class="muted">Historical range: ${safe(range.start || 'n/a')} to ${safe(range.end || 'n/a')} (${safe(range.count || 0)} run(s))</div>
                    <div class="muted">Uncertainty: ${safe(uncertainty.confidence_level || 'unknown')} confidence, score ${safe(confidenceScore)}, cycle range ${safe(minCycle)} - ${safe(maxCycle)} hrs</div>
                    <div class="muted">${safe(result.safety_label)}</div>
                `;
            })()
            : safe(result.error);
    }

    async function createTask(recommendationId) {
        const result = await api('task', { recommendation_id: recommendationId });
        alert(result.success ? `Task created: ${result.id}` : result.error);
    }

    async function createProposal(recommendationId) {
        const result = await api('proposal', { recommendation_id: recommendationId });
        alert(result.success ? result.message : result.error);
    }

    async function loadHistory() {
        const result = await api('history', { limit: 20 });
        const history = result.history || {};
        document.getElementById('panel-history').innerHTML = `
            <div class="ai-content-card">
                <h3>Findings</h3>
                <div class="list">${(history.findings || []).map(renderFinding).join('') || '<div class="muted">No finding history.</div>'}</div>
            </div>
            <div class="ai-content-card table-wrap">
                <h3>Proposals</h3>
                <table>
                    <thead><tr><th>Title</th><th>Process</th><th>Approval</th><th>Governance</th><th>Workflow</th></tr></thead>
                    <tbody>
                        ${(history.proposals || []).map(row => `<tr>
                            <td>${safe(row.title)}</td>
                            <td>${safe(row.process_key)}</td>
                            <td>${safe(row.approval_status)}</td>
                            <td>${safe(row.governance_status)}</td>
                            <td>${safe(row.workflow_id || 'Draft only')}</td>
                        </tr>`).join('') || '<tr><td colspan="5">No proposal history.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderFinding(row) {
        const severity = safe(row.severity || 'medium');
        return `
            <div class="item severity-${severity}">
                <strong>${safe(row.title || row.process_key)}</strong>
                <div class="muted">${safe(row.summary || row.detected_at || '')}</div>
                <div class="chips">
                    <span class="chip">${severity}</span>
                    <span class="chip">${safe(row.process_key || '')}</span>
                    <span class="chip">${safe(row.finding_type || row.status || '')}</span>
                </div>
            </div>
        `;
    }

    function renderQuestionResult(result) {
        if (!result.success) {
            return `<strong>${safe(result.type || 'rejected')}:</strong> ${safe(result.error || 'Request failed.')}`;
        }
        if (result.type === 'bottlenecks') {
            const findings = result.findings || [];
            if (!findings.length) {
                return '<strong>bottlenecks:</strong> No grounded bottlenecks were detected in the current authorized sample.';
            }
            return `<strong>bottlenecks:</strong> ${safe(findings.length)} finding(s) detected. ${safe(findings[0].title || '')}`;
        }
        if (result.type === 'comparison') {
            const rows = result.comparison?.rows || [];
            return `<strong>comparison:</strong> ${safe(rows.length)} dimension group(s) compared for ${safe(result.process_key || 'all processes')}.`;
        }
        if (result.type === 'metrics') {
            const rows = result.metrics?.rows || [];
            if (!rows.length) {
                return '<strong>metrics:</strong> No process metrics available.';
            }
            const top = rows[0];
            return `<strong>metrics:</strong> ${safe(rows.length)} process(es), median ${safe(top.median_cycle_hours)} hrs, p90 ${safe(top.p90_cycle_hours)} hrs, p95 ${safe(top.p95_cycle_hours)} hrs.`;
        }
        if (result.type === 'sla') {
            const sla = result.sla || {};
            return `<strong>sla:</strong> ${safe(sla.breached_count || 0)} breached, ${safe(sla.met_count || 0)} met, ${safe(sla.at_risk_count || 0)} at risk, breach rate ${safe(sla.breach_percentage || 0)}%.`;
        }
        if (result.type === 'rework') {
            const rework = result.rework || {};
            return `<strong>rework:</strong> ${safe(rework.rework_instance_count || 0)} instance(s) with rework, avg rework ${safe(rework.avg_rework_hours || 0)} hrs.`;
        }
        if (result.type === 'process_map') {
            const transitions = result.map?.transitions || [];
            return `<strong>process map:</strong> ${safe(transitions.length)} grounded transition path(s) identified.`;
        }
        if (result.type === 'recommendations') {
            const rows = result.recommendations || [];
            return `<strong>recommendations:</strong> ${safe(rows.length)} advisory recommendation(s) prepared.`;
        }
        if (result.type === 'overview') {
            const totals = result.overview?.totals || {};
            return `<strong>overview:</strong> ${safe(totals.definitions || 0)} process definition(s), ${safe(totals.instances || 0)} instance(s), ${safe(totals.open_findings || 0)} open finding(s).`;
        }
        return `<strong>${safe(result.type || 'result')}:</strong> Request completed.`;
    }

    loadOverview();
    </script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>
