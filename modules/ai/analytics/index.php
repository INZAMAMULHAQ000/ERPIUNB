<?php
/**
 * AI F30 — AI Business Analytics Center.
 */
require_once __DIR__ . '/../bootstrap.php';
$db = getDB();
$companyId = $_SESSION['company_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? 1;
$API = rtrim(BASE_URL ?? '', '/') . '/modules/ai/api/analytics.php';
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Analytics Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php ai_render_shell_styles(); ?>
    <?php ai_render_page_styles(); ?>
    <style>
        .panel { display: none; }
        .panel.active { display: block; }
        .chat-input {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .chat-input input { flex: 1; min-width: 200px; }
        .chat-input button { white-space: nowrap; }
        .quick-queries {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .result-card {
            background: var(--ai-card-bg);
            border: 1px solid var(--ai-card-border);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--ai-accent);
            box-shadow: var(--ai-card-shadow);
            color: var(--ai-text-primary);
        }
        .result-card.forecast { border-left-color: var(--ai-accent-2); }
        .result-card.scenario { border-left-color: var(--ai-warning); }
        .result-card.briefing { border-left-color: var(--ai-success); }
        .result-card.anomaly { border-left-color: var(--ai-danger); }
        .forecast-details {
            background: rgba(15, 23, 42, 0.45);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        body.ai-module-page[data-theme="ibm-light"] .forecast-details { background: #f1f5f9; }
        .forecast-details .row {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            border-bottom: 1px solid var(--ai-border);
            color: var(--ai-text-secondary);
        }
        .forecast-details .row:last-child { border-bottom: none; }
        .safety-label {
            background: rgba(251, 191, 36, 0.12);
            color: var(--ai-warning);
            padding: 0.5rem 0.75rem;
            border-radius: 0.4rem;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        body.ai-module-page[data-theme="ibm-light"] .safety-label { color: #92400e; }
        .briefing-section { margin-bottom: 1rem; }
        .briefing-section h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--ai-accent);
            margin-bottom: 0.5rem;
        }
        .briefing-item {
            padding: 0.5rem 0.75rem;
            background: rgba(15, 23, 42, 0.35);
            border-radius: 0.4rem;
            margin-bottom: 0.35rem;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            color: var(--ai-text-secondary);
        }
        body.ai-module-page[data-theme="ibm-light"] .briefing-item,
        body.ai-module-page[data-theme="ibm-light"] .result-card { background: #f8fafc; }
        .loading {
            text-align: center;
            padding: 1.5rem;
            color: var(--ai-text-muted);
        }
        .comparison {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        .comparison .prev { color: var(--ai-text-muted); font-size: 0.85rem; }
        .comparison .change { font-weight: 700; font-size: 0.9rem; }
    </style>
</head>
<body class="ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.analytics'); ?>
    <div class="ai-page-container">
        <?php ai_render_page_header('AI Analytics Center', 'Business Analytics, Forecasting & Decision Intelligence', 'fas fa-chart-line'); ?>
        <div class="ai-tabs">
            <button class="ai-tab tab active" onclick="showPanel('overview')">Executive Overview</button>
            <button class="ai-tab tab" onclick="showPanel('query')">Natural Language Query</button>
            <button class="ai-tab tab" onclick="showPanel('kpis')">KPIs</button>
            <button class="ai-tab tab" onclick="showPanel('trends')">Trends</button>
            <button class="ai-tab tab" onclick="showPanel('comparisons')">Comparisons</button>
            <button class="ai-tab tab" onclick="showPanel('forecast')">Forecasting</button>
            <button class="ai-tab tab" onclick="showPanel('scenarios')">Scenarios</button>
            <button class="ai-tab tab" onclick="showPanel('recommendations')">Recommendations</button>
            <button class="ai-tab tab" onclick="showPanel('anomalies')">Anomalies</button>
            <button class="ai-tab tab" onclick="showPanel('saved')">Saved Analyses</button>
            <button class="ai-tab tab" onclick="showPanel('scheduled')">Scheduled Reports</button>
        </div>
        <div class="panel active" id="panel-overview">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-search"></i> Ask a Question</h3>
                <div class="chat-input">
                    <input type="text" id="overviewQuery" class="ai-form-input" placeholder="e.g. How are sales performing this quarter?" onkeydown="if(event.key==='Enter')runOverviewQuery()">
                    <button onclick="runOverviewQuery()" class="btn-primary"><i class="fas fa-paper-plane"></i> Ask</button>
                </div>
                <div class="quick-queries">
                    <button class="quick-query" onclick="quickQuery('Show revenue this quarter')">Revenue this quarter</button>
                    <button class="quick-query" onclick="quickQuery('Compare procurement with last quarter')">Procurement comparison</button>
                    <button class="quick-query" onclick="quickQuery('Show overdue invoices')">Overdue invoices</button>
                    <button class="quick-query" onclick="quickQuery('Show inventory value')">Inventory value</button>
                    <button class="quick-query" onclick="quickQuery('Show executive briefing')">Executive briefing</button>
                </div>
                <div id="overviewResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-query">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-robot"></i> Natural Language Analytics</h3>
                <p style="font-size:13px;color:var(--ai-text-muted);margin-bottom:12px;">Ask questions about your business data in plain English. The AI will translate your request into controlled analytical operations.</p>
                <div class="chat-input">
                    <input type="text" id="nlQuery" class="ai-form-input" placeholder="e.g. Which suppliers increased prices this quarter?" onkeydown="if(event.key==='Enter')runNLQuery()">
                    <button onclick="runNLQuery()" class="btn-primary"><i class="fas fa-paper-plane"></i> Analyze</button>
                </div>
                <div id="nlResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-kpis">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-tachometer-alt"></i> Key Performance Indicators</h3>
                <div id="kpiGrid" class="ai-stat-grid"><div class="loading">Loading KPIs...</div></div>
            </div>
        </div>
        <div class="panel" id="panel-trends">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-wave-square"></i> Trends</h3>
                <div class="chat-input">
                    <input type="text" id="trendQuery" class="ai-form-input" placeholder="e.g. Show revenue trend over time" onkeydown="if(event.key==='Enter')runTrend()">
                    <button onclick="runTrend()" class="btn-primary"><i class="fas fa-chart-line"></i> Analyze</button>
                </div>
                <div class="quick-queries">
                    <button class="quick-query" onclick="quickTrend('Show revenue trend this quarter')">Revenue trend</button>
                    <button class="quick-query" onclick="quickTrend('Show inventory trend this quarter')">Inventory trend</button>
                    <button class="quick-query" onclick="quickTrend('Show overdue invoices trend this quarter')">Overdue trend</button>
                </div>
                <div id="trendResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-comparisons">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-balance-scale"></i> Comparisons</h3>
                <div class="chat-input">
                    <input type="text" id="comparisonQuery" class="ai-form-input" placeholder="e.g. Compare revenue this quarter with last quarter" onkeydown="if(event.key==='Enter')runComparison()">
                    <button onclick="runComparison()" class="btn-primary"><i class="fas fa-not-equal"></i> Compare</button>
                </div>
                <div class="quick-queries">
                    <button class="quick-query" onclick="quickComparison('Compare revenue this quarter with last quarter')">Current vs previous</button>
                    <button class="quick-query" onclick="quickComparison('Show revenue year over year')">Year over year</button>
                    <button class="quick-query" onclick="quickComparison('Show revenue month over month')">Month over month</button>
                </div>
                <div id="comparisonResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-forecast">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-chart-area"></i> AI Forecasting</h3>
                <div class="ai-grid ai-grid-cols-1" style="gap:12px;margin-bottom:16px;">
                    <select id="forecastMetric" class="ai-form-select">
                        <option value="revenue">Revenue</option>
                        <option value="purchase_spend">Purchase Spend</option>
                        <option value="inventory_value">Inventory Value</option>
                        <option value="outstanding_receivables">Receivables</option>
                        <option value="overdue_invoices">Overdue Invoices</option>
                    </select>
                    <div class="ai-grid ai-grid-cols-3" style="gap:8px;">
                        <select id="forecastPeriod" class="ai-form-select">
                            <option value="30">30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <select id="forecastMethod" class="ai-form-select">
                            <option value="moving_average">Moving Average</option>
                            <option value="weighted_moving_average">Weighted Moving Average</option>
                            <option value="historical_trend">Historical Trend</option>
                            <option value="linear_regression">Linear Regression</option>
                        </select>
                        <button onclick="runForecast()" class="btn-primary"><i class="fas fa-play"></i> Forecast</button>
                    </div>
                </div>
                <div id="forecastResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-scenarios">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-flask"></i> What-If Scenario Analysis</h3>
                <p style="font-size:13px;color:var(--ai-text-muted);margin-bottom:12px;">Test business scenarios without changing any ERP data.</p>
                <div class="chat-input">
                    <input type="text" id="scenarioQuery" class="ai-form-input" placeholder="e.g. What if sales increase 10%?" onkeydown="if(event.key==='Enter')runScenario()">
                    <button onclick="runScenario()" class="btn-primary"><i class="fas fa-play"></i> Simulate</button>
                </div>
                <div id="scenarioResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-recommendations">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-lightbulb"></i> Recommendations</h3>
                <p style="font-size:13px;color:var(--ai-text-muted);margin-bottom:12px;">Use verified analysis and briefing output to surface actionable next steps.</p>
                <div class="quick-queries">
                    <button class="quick-query" onclick="runRecommendation('Show executive briefing')">Executive briefing</button>
                    <button class="quick-query" onclick="runRecommendation('Analyze revenue and recommend next steps')">Revenue recommendation</button>
                    <button class="quick-query" onclick="runRecommendation('Analyze procurement and recommend next steps')">Procurement recommendation</button>
                </div>
                <div id="recommendationResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-anomalies">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-exclamation-triangle"></i> Anomaly Detection</h3>
                <div class="chat-input">
                    <input type="text" id="anomalyQuery" class="ai-form-input" placeholder="e.g. Find unusual transactions" onkeydown="if(event.key==='Enter')runAnomaly()">
                    <button onclick="runAnomaly()" class="btn-primary"><i class="fas fa-search"></i> Detect</button>
                </div>
                <div id="anomalyResult" class="result-area"></div>
            </div>
        </div>
        <div class="panel" id="panel-saved">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-bookmark"></i> Saved Analyses</h3>
                <div id="savedList"><div class="loading">Loading...</div></div>
            </div>
        </div>
        <div class="panel" id="panel-scheduled">
            <div class="ai-content-card">
                <h3 class="ai-section-title"><i class="fas fa-clock"></i> Scheduled Reports</h3>
                <div class="chat-input">
                    <button onclick="runDueScheduled()" class="btn-primary"><i class="fas fa-play"></i> Run Due Now</button>
                    <button onclick="loadScheduled()" class="btn-secondary"><i class="fas fa-sync-alt"></i> Refresh List</button>
                </div>
                <div id="scheduledList"><div class="loading">Loading scheduled analyses...</div></div>
            </div>
        </div>
    </div>
    <script>
    const API = <?= json_encode($API) ?>;
    function showPanel(name) { document.querySelectorAll('.panel').forEach(p => p.classList.remove('active')); document.querySelectorAll('.tab').forEach(t => t.classList.remove('active')); document.getElementById('panel-' + name).classList.add('active'); if (window.event && window.event.currentTarget) window.event.currentTarget.classList.add('active'); if (name === 'kpis') loadKPIs(); if (name === 'saved') loadSaved(); if (name === 'scheduled') loadScheduled(); }

    async function apiCall(action, data = {}) {
        try {
            const fd = new FormData(); fd.append('action', action); for (const [k, v] of Object.entries(data)) fd.append(k, typeof v === 'string' ? v : JSON.stringify(v));
            const r = await fetch(API, { method: 'POST', body: fd });
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                return { success: false, error: 'Invalid API response', raw: text.slice(0, 500) };
            }
        } catch (e) {
            return { success: false, error: e.message || 'Request failed' };
        }
    }

    function quickQuery(text) { document.getElementById('overviewQuery').value = text; runOverviewQuery(); }
    async function runOverviewQuery() { const q = document.getElementById('overviewQuery').value.trim(); if (!q) return; document.getElementById('overviewResult').innerHTML = '<div class="loading">Analyzing...</div>'; const r = await apiCall('query', { text: q }); document.getElementById('overviewResult').innerHTML = renderResult(r); }
    async function runNLQuery() { const q = document.getElementById('nlQuery').value.trim(); if (!q) return; document.getElementById('nlResult').innerHTML = '<div class="loading">Analyzing...</div>'; const r = await apiCall('query', { text: q }); document.getElementById('nlResult').innerHTML = renderResult(r); }

    async function loadKPIs() { const r = await apiCall('get_kpis'); const grid = document.getElementById('kpiGrid'); if (!r.success || !r.kpis || r.kpis.length === 0) { grid.innerHTML = '<div class="loading">No accessible KPIs for this session.</div>'; return; } grid.innerHTML = r.kpis.map(item => renderKPI(item.result || { success: false, error: 'Unavailable' }, item.metric || {})).join('') || '<div class="loading">No KPI results returned.</div>'; }

    function renderKPI(r, m) { if (!r.success) return `<div class="ai-stat-card"><div class="ai-stat-label">${esc(m.name)}</div><div style="color:var(--ai-danger);font-size:12px;">${esc(r.error || 'Unavailable')}</div></div>`; const v = r.total || 0; const currencyNote = r.currency?.mode === 'mixed' ? 'Mixed currencies' : (r.currency?.currency || (m.currency_dependent ? 'AED' : '')); const fmt = m.currency_dependent ? numberFormat(v) : v.toLocaleString(); const change = r.comparison ? `<div style="font-size:12px;margin-top:6px;font-weight:600;color:${r.comparison.change >= 0 ? 'var(--ai-success)' : 'var(--ai-danger)'};">${r.comparison.change >= 0 ? '+' : ''}${r.comparison.change}%</div>` : ''; return `<div class="ai-stat-card"><div class="ai-stat-value">${fmt}</div><div class="ai-stat-label">${esc(m.name)}</div>${currencyNote ? `<div style="font-size:11px;color:var(--ai-text-muted);margin-top:4px;">${esc(currencyNote)}</div>` : ''}${change}</div>`; }

    async function runTrend() { const q = document.getElementById('trendQuery').value.trim(); if (!q) return; document.getElementById('trendResult').innerHTML = '<div class="loading">Analyzing trend...</div>'; const r = await apiCall('query', { text: q }); document.getElementById('trendResult').innerHTML = renderResult(r); }
    function quickTrend(text) { document.getElementById('trendQuery').value = text; runTrend(); }

    async function runComparison() { const q = document.getElementById('comparisonQuery').value.trim(); if (!q) return; document.getElementById('comparisonResult').innerHTML = '<div class="loading">Comparing...</div>'; const r = await apiCall('query', { text: q }); document.getElementById('comparisonResult').innerHTML = renderResult(r); }
    function quickComparison(text) { document.getElementById('comparisonQuery').value = text; runComparison(); }

    async function runRecommendation(text) { document.getElementById('recommendationResult').innerHTML = '<div class="loading">Generating recommendation...</div>'; const r = await apiCall('query', { text }); document.getElementById('recommendationResult').innerHTML = renderResult(r); }

    async function runForecast() { const m = document.getElementById('forecastMetric').value; const p = document.getElementById('forecastPeriod').value; const method = document.getElementById('forecastMethod').value; document.getElementById('forecastResult').innerHTML = '<div class="loading">Generating forecast...</div>'; const r = await apiCall('forecast', { metric_key: m, periods: p, method }); document.getElementById('forecastResult').innerHTML = renderForecast(r); }

    async function runScenario() { const q = document.getElementById('scenarioQuery').value.trim(); if (!q) return; document.getElementById('scenarioResult').innerHTML = '<div class="loading">Running simulation...</div>'; const r = await apiCall('query', { text: q }); document.getElementById('scenarioResult').innerHTML = renderResult(r); }

    async function runAnomaly() { const q = document.getElementById('anomalyQuery').value.trim(); if (!q) return; document.getElementById('anomalyResult').innerHTML = '<div class="loading">Detecting anomalies...</div>'; const r = await apiCall('query', { text: 'Detect anomalies: ' + q }); document.getElementById('anomalyResult').innerHTML = renderResult(r); }

    async function loadSaved() { const r = await apiCall('get_saved'); const div = document.getElementById('savedList'); if (r.success && r.analyses && r.analyses.length > 0) { div.innerHTML = r.analyses.map(a => `<div class="result-card"><strong>${esc(a.name)}</strong><div style="font-size:12px;color:var(--ai-text-muted);margin-top:4px;">Metric: ${esc(a.metric_key)} | Created: ${a.created_at}</div><div style="font-size:12px;color:var(--ai-text-muted);">Schedule: ${esc(a.refresh_schedule || 'none')}</div></div>`).join(''); } else { div.innerHTML = '<div style="text-align:center;padding:30px;color:var(--ai-text-muted);">No saved analyses yet</div>'; } }

    async function loadScheduled() { const r = await apiCall('get_saved'); const div = document.getElementById('scheduledList'); if (r.success && r.analyses && r.analyses.length > 0) { const scheduled = r.analyses.filter(a => a.refresh_schedule); div.innerHTML = scheduled.length ? scheduled.map(a => `<div class="result-card"><strong>${esc(a.name)}</strong><div style="font-size:12px;color:var(--ai-text-muted);margin-top:4px;">Metric: ${esc(a.metric_key)} | Last run: ${esc(a.last_run_at || 'never')}</div><div style="font-size:12px;color:var(--ai-text-muted);">Schedule: ${esc(a.refresh_schedule)}</div></div>`).join('') : '<div style="text-align:center;padding:30px;color:var(--ai-text-muted);">No scheduled analyses yet</div>'; } else { div.innerHTML = '<div style="text-align:center;padding:30px;color:var(--ai-text-muted);">No scheduled analyses yet</div>'; } }

    async function runDueScheduled() { const div = document.getElementById('scheduledList'); div.innerHTML = '<div class="loading">Running scheduled analyses...</div>'; const r = await apiCall('run_scheduled'); div.innerHTML = r.success ? `<div class="result-card"><strong>Executed:</strong> ${r.executed_count || 0}</div>` : `<div class="result-card" style="border-left-color:var(--ai-danger);"><strong>Error:</strong> ${esc(r.error || 'Failed')}</div>`; await loadScheduled(); }
    function renderResult(r) { if (!r.success) return `<div class="result-card" style="border-left-color:var(--ai-danger);"><strong>Error:</strong> ${esc(r.error || 'Unknown error')}</div>`; let h = ''; if (r.request_type === 'briefing' || r.type === 'executive_briefing') { h = renderBriefing(r); } else if (r.forecast_value !== undefined) { h = renderForecast(r); } else if (r.scenario_value !== undefined) { h = renderScenario(r); } else { h = renderAnalytical(r); } if (r.explanation) { h += `<div style="margin-top:8px;padding:12px;background:rgba(56,189,248,0.08);border-radius:8px;font-size:13px;color:var(--ai-text-secondary);"><strong>Analysis:</strong> ${esc(r.explanation.summary || '')}${r.explanation.caveat ? '<br><em style="color:var(--ai-text-muted);">' + esc(r.explanation.caveat) + '</em>' : ''}</div>`; } if (r.latency_ms) h += `<div style="font-size:11px;color:var(--ai-text-muted);margin-top:4px;">${r.latency_ms}ms</div>`; return h; }

    function renderAnalytical(r) { let h = `<div class="result-card"><div style="font-size:12px;color:var(--ai-text-muted);">${esc(r.metric?.name || r.request_type || '')}</div><div style="font-size:24px;font-weight:700;color:var(--ai-accent);margin:4px 0;">${numberFormat(r.total || 0)}</div><div style="font-size:12px;color:var(--ai-text-muted);">Source: ${esc(r.source?.table || '')} | Calculation: ${esc(r.calculation_definition?.type || '')}</div>`; if (r.currency?.mode === 'mixed') { h += `<div style="font-size:12px;color:var(--ai-warning);margin-top:4px;">${esc(r.currency.note || 'Mixed currencies detected')}</div>`; } else if (r.currency?.currency) { h += `<div style="font-size:12px;color:var(--ai-text-muted);margin-top:4px;">Currency: ${esc(r.currency.currency)}</div>`; } if (r.comparison) { h += `<div class="comparison"><span class="prev">vs previous period</span><span class="change" style="font-weight:700;font-size:14px;color:${r.comparison.change >= 0 ? 'var(--ai-success)' : 'var(--ai-danger)'};">${r.comparison.change >= 0 ? '+' : ''}${r.comparison.change}%</span></div>`; } if (r.visualization && r.visualization.data && r.visualization.data.length > 0) { h += '<div style="margin-top:12px;font-size:13px;color:var(--ai-text-secondary);"><strong>Chart Data</strong><div style="font-size:12px;color:var(--ai-text-muted);">' + esc(r.visualization.chart_type) + '</div></div>'; } if (r.source_records && r.source_records.length > 0) { h += '<table style="width:100%;font-size:13px;margin-top:12px;border-collapse:collapse;"><tr style="background:rgba(148,163,184,0.10);"><th style="padding:6px 8px;text-align:left;color:var(--ai-text-muted);">Drill-down</th><th style="padding:6px 8px;text-align:right;color:var(--ai-text-muted);">Value</th></tr>'; r.source_records.slice(0, 10).forEach(row => { h += `<tr style="border-bottom:1px solid var(--ai-border);"><td style="padding:6px 8px;color:var(--ai-text-secondary);">${esc(row.label || '')}</td><td style="padding:6px 8px;text-align:right;font-weight:600;color:var(--ai-text-primary);">${numberFormat(row.metric_value || 0)}</td></tr>`; }); h += '</table>'; } h += '</div>'; return h; }

    function renderForecast(r) { let h = `<div class="result-card forecast"><div style="font-size:12px;color:var(--ai-text-muted);">Forecast: ${esc(r.metric_key || '')}</div><div style="font-size:24px;font-weight:700;color:var(--ai-accent-2);margin:4px 0;">${numberFormat(r.forecast_value || 0)}</div><div style="font-size:13px;color:var(--ai-text-muted);">Horizon: ${r.forecast_period?.periods_ahead || ''} days | Method: ${esc(r.forecast_method || '')}</div>`; if (r.historical_range) { h += `<div style="font-size:12px;color:var(--ai-text-muted);margin-top:4px;">Historical range: ${esc(String(r.historical_range.start || ''))} to ${esc(String(r.historical_range.end || ''))} (${r.historical_range.count || 0} points)</div>`; } if (r.confidence) { h += `<div style="margin-top:6px;font-size:13px;color:var(--ai-text-secondary);">Confidence: <strong style="color:${r.confidence.level === 'high' ? 'var(--ai-success)' : r.confidence.level === 'medium' ? 'var(--ai-warning)' : 'var(--ai-danger)'};">${r.confidence.level}</strong> (${r.confidence.score})</div>`; if (r.confidence.reasons) h += `<div style="font-size:12px;color:var(--ai-text-muted);">${r.confidence.reasons.join(' • ')}</div>`; } if (r.trend) { h += `<div style="margin-top:6px;font-size:13px;color:var(--ai-text-secondary);">Trend: <strong>${r.trend.direction}</strong> (${r.trend.change_percentage}%)</div>`; } h += '<div class="forecast-details"><div class="row"><span>Methodology</span><span>' + esc(r.forecast_method || '') + '</span></div>'; if (r.assumptions) { h += '<div class="row"><span>Assumptions</span><span>' + r.assumptions.length + ' listed</span></div>'; } if (r.limitations) { h += '<div class="row"><span>Limitations</span><span>' + r.limitations.length + ' listed</span></div>'; } h += '</div>'; h += `<div class="safety-label">🔒 ${esc(r.safety_label || 'Simulation — no ERP data has been changed')}</div></div>`; return h; }

    function renderScenario(r) { let h = `<div class="result-card scenario"><div style="font-size:12px;color:var(--ai-text-muted);">Scenario: ${esc(r.metric_key || '')}</div><div style="display:flex;gap:20px;margin:8px 0;"><div><div style="font-size:12px;color:var(--ai-text-muted);">Baseline</div><div style="font-size:20px;font-weight:700;color:var(--ai-text-primary);">${numberFormat(r.baseline_value || 0)}</div></div><div style="font-size:24px;color:var(--ai-text-muted);">→</div><div><div style="font-size:12px;color:var(--ai-text-muted);">Scenario</div><div style="font-size:20px;font-weight:700;color:var(--ai-warning);">${numberFormat(r.scenario_value || 0)}</div></div><div><div style="font-size:12px;color:var(--ai-text-muted);">Impact</div><div style="font-size:20px;font-weight:700;color:${r.impact_percentage >= 0 ? 'var(--ai-success)' : 'var(--ai-danger)'};">${r.impact_percentage >= 0 ? '+' : ''}${r.impact_percentage}%</div></div></div>`; if (r.variables) { h += '<div style="font-size:13px;color:var(--ai-text-muted);">Variables: '; h += Object.entries(r.variables).map(([k, v]) => `${esc(k)}: ${v > 0 ? '+' : ''}${v}`).join(', '); h += '</div>'; } if (r.methodology) { h += `<div style="font-size:12px;color:var(--ai-text-muted);margin-top:4px;">Methodology: ${esc(r.methodology)}</div>`; } h += `<div class="safety-label">🔒 ${esc(r.safety_label || 'Simulation — no ERP data has been changed')}</div></div>`; return h; }

    function renderBriefing(r) { let h = '<div class="result-card briefing"><h3 style="color:var(--ai-success);margin-bottom:12px;">📊 Executive Briefing</h3>'; if (r.business_snapshot) { h += '<div class="briefing-section"><h4>Business Snapshot</h4>'; r.business_snapshot.forEach(s => { const change = s.comparison ? `<span style="color:${s.comparison.change >= 0 ? 'var(--ai-success)' : 'var(--ai-danger)'};font-weight:600;">${s.comparison.change >= 0 ? '+' : ''}${s.comparison.change}%</span>` : ''; h += `<div class="briefing-item"><span>${esc(s.metric)}</span><span><strong>${numberFormat(s.value)}</strong> ${change}</span></div>`; }); h += '</div>'; } if (r.risks && r.risks.length > 0) { h += '<div class="briefing-section"><h4>⚠️ Risks</h4>'; r.risks.forEach(risk => { h += `<div class="briefing-item" style="border-left:3px solid var(--ai-danger);"><span>${esc(risk.description)}</span></div>`; }); h += '</div>'; } if (r.opportunities && r.opportunities.length > 0) { h += '<div class="briefing-section"><h4>✅ Opportunities</h4>'; r.opportunities.forEach(opp => { h += `<div class="briefing-item" style="border-left:3px solid var(--ai-success);"><span>${esc(opp.description)}</span></div>`; }); h += '</div>'; } if (r.major_changes && r.major_changes.length > 0) { h += '<div class="briefing-section"><h4>Major Changes</h4>'; r.major_changes.forEach(change => { h += `<div class="briefing-item"><span>${esc(change.metric || '')}</span><span>${esc(change.summary || '')}</span></div>`; }); h += '</div>'; } h += '</div>'; return h; }

    function numberFormat(n) { return typeof n === 'number' ? n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : n; }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    </script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>
