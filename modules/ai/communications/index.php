<?php
/**
 * AI F32 — Communications Center
 * 
 * Unified AI Communications & Business Correspondence interface.
 * 14 tabs covering all F32 sections.
 */
$aiModuleDir = dirname(__DIR__);
require_once dirname($aiModuleDir) . '/ai/bootstrap.php';

$userId = $_SESSION['user_id'] ?? 0;
$companyId = $_SESSION['company_id'] ?? 0;
$basePath = '/modules/ai';
$apiBase = $basePath . '/api/communications.php';
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Communications Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #1a1d27;
            --bg-card: #21242f;
            --bg-input: #2a2d3a;
            --border: #2d3140;
            --text-primary: #e8eaed;
            --text-secondary: #9aa0b0;
            --text-muted: #6b7280;
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --green: #22c55e;
            --red: #ef4444;
            --yellow: #eab308;
            --orange: #f97316;
            --blue: #3b82f6;
            --purple: #a855f7;
        }
        .tabs {
            display: flex;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            padding: 0 16px;
        }
        .tab {
            padding: 12px 16px;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 13px;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .tab:hover { color: var(--text-primary); }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .panel { display: none; }
        .panel.active { display: block; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 5px; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        .form-control:focus { outline: none; border-color: var(--accent); }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 120px; font-family: inherit; }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: var(--bg-input); color: var(--text-primary); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--border); }
        .btn-success { background: var(--green); color: #fff; }
        .btn-danger { background: var(--red); color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-low { background: #1e3a5f; color: #60a5fa; }
        .badge-normal { background: #1a3a2a; color: #4ade80; }
        .badge-medium { background: #3a3a1a; color: #facc15; }
        .badge-high { background: #3a1a1a; color: #f87171; }
        .badge-urgent { background: #4a1a2a; color: #fb7185; }
        .badge-draft { background: #2a2a3a; color: #a5b4fc; }
        .badge-sent { background: #1a3a2a; color: #4ade80; }
        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .list-item:hover { background: var(--bg-input); }
        .list-item:last-child { border-bottom: none; }
        .list-item .info { flex: 1; }
        .list-item .title { font-size: 14px; font-weight: 500; }
        .list-item .meta { font-size: 12px; color: var(--text-secondary); margin-top: 3px; }
        .list-item .actions { display: flex; gap: 6px; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .section-header h2 { font-size: 18px; font-weight: 600; }
        .result-box {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
            margin-top: 12px;
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.5;
            max-height: 500px;
            overflow-y: auto;
        }
        .side-by-side { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .side-by-side > div { background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; padding: 14px; }
        .side-by-side .label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; font-weight: 600; }
        .loading { text-align: center; padding: 20px; color: var(--text-muted); }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 20px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .source-ref { display: inline-block; background: var(--bg-input); border: 1px solid var(--border); border-radius: 4px; padding: 2px 6px; font-size: 11px; margin: 2px; color: var(--text-secondary); }
        .warning-box { background: #3a2a1a; border: 1px solid #5a4a2a; border-radius: 8px; padding: 10px 14px; margin-top: 8px; font-size: 13px; color: #fbbf24; }
        .error-box { background: #3a1a1a; border: 1px solid #5a2a2a; border-radius: 8px; padding: 10px 14px; margin-top: 8px; font-size: 13px; color: #f87171; }
        .success-box { background: #1a3a2a; border: 1px solid #2a5a3a; border-radius: 8px; padding: 10px 14px; margin-top: 8px; font-size: 13px; color: #4ade80; }

        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4, .side-by-side { grid-template-columns: 1fr; }
            .tabs { padding: 0 8px; }
            .tab { padding: 10px 10px; font-size: 12px; }
            .ai-page-container { padding: 12px; }
        }
    </style>
    <?php ai_render_shell_styles(); ?>
</head>
<body class="ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.communications'); ?>

    <?php ai_render_page_styles(); ?>
<div class="ai-page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-comments"></i></span>
                AI Communications Center
            </h1>
        </div>
        <div>
            <button class="btn btn-secondary btn-sm" onclick="loadAllData()"><i class="fas fa-sync"></i> Refresh</button>
        </div>
    </div>
</div>

<div class="tabs">
    <div class="tab active" onclick="showPanel('overview')">Overview</div>
    <div class="tab" onclick="showPanel('drafts')">Drafts</div>
    <div class="tab" onclick="showPanel('sent')">Sent</div>
    <div class="tab" onclick="showPanel('templates')">Templates</div>
    <div class="tab" onclick="showPanel('summarize')">Summaries</div>
    <div class="tab" onclick="showPanel('classify')">Classify</div>
    <div class="tab" onclick="showPanel('followups')">Follow-Ups</div>
    <div class="tab" onclick="showPanel('translate')">Translation</div>
    <div class="tab" onclick="showPanel('suggestions')">Suggestions</div>
    <div class="tab" onclick="showPanel('batch')">Batch Drafts</div>
    <div class="tab" onclick="showPanel('f26')">Intelligence</div>
    <div class="tab" onclick="showPanel('f27')">Documents</div>
    <div class="tab" onclick="showPanel('f25')">Agent Tasks</div>
    <div class="tab" onclick="showPanel('events')">History</div>
</div>

<div class="ai-page-container">

    <!-- OVERVIEW -->
    <div id="panel-overview" class="panel active">
        <div class="ai-stat-grid" id="overview-stats">
            <div class="ai-stat-card"><div class="ai-stat-value" id="stat-drafts" style="color:var(--ai-accent)">0</div><div class="ai-stat-label">Active Drafts</div></div>
            <div class="ai-stat-card"><div class="ai-stat-value" id="stat-sent" style="color:var(--ai-success)">0</div><div class="ai-stat-label">Sent Today</div></div>
            <div class="ai-stat-card"><div class="ai-stat-value" id="stat-followups" style="color:var(--ai-warning)">0</div><div class="ai-stat-label">Pending Follow-Ups</div></div>
            <div class="ai-stat-card"><div class="ai-stat-value" id="stat-urgent" style="color:var(--ai-danger)">0</div><div class="ai-stat-label">Urgent</div></div>
        </div>
        <div class="ai-content-card">
            <h3><i class="fas fa-plus-circle" style="color:var(--ai-accent)"></i> Quick Draft</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group">
                        <label>Context</label>
                        <select id="quick-ctx-type" class="form-control" onchange="document.getElementById('quick-ctx-id').style.display = this.value !== 'manual' ? 'block' : 'none'">
                            <option value="manual">Manual</option>
                            <option value="customer">Customer</option>
                            <option value="supplier">Supplier</option>
                            <option value="invoice">Invoice</option>
                            <option value="po">Purchase Order</option>
                            <option value="quotation">Quotation</option>
                            <option value="ticket">Support Ticket</option>
                        </select>
                    </div>
                    <div class="form-group" id="quick-ctx-id" style="display:none">
                        <label>Record ID</label>
                        <input type="number" id="quick-ctx-id-val" class="form-control" placeholder="Enter record ID">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="quick-comm-type" class="form-control">
                            <option value="email">Email</option>
                            <option value="customer_reply">Customer Reply</option>
                            <option value="supplier_reply">Supplier Reply</option>
                            <option value="follow_up">Follow-Up</option>
                            <option value="reminder">Reminder</option>
                            <option value="escalation">Escalation</option>
                            <option value="internal_message">Internal Message</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tone</label>
                        <select id="quick-tone" class="form-control">
                            <option value="professional">Professional</option>
                            <option value="friendly">Friendly</option>
                            <option value="formal">Formal</option>
                            <option value="concise">Concise</option>
                            <option value="diplomatic">Diplomatic</option>
                            <option value="firm">Firm</option>
                            <option value="apologetic">Apologetic</option>
                            <option value="executive">Executive</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea id="quick-instructions" class="form-control" rows="6" placeholder="Describe what you want to communicate...&#10;&#10;Example: Draft an email to supplier asking why shipment is late for PO-1234"></textarea>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn-primary" onclick="quickDraft()"><i class="fas fa-magic"></i> Generate Draft</button>
                        <button class="btn btn-secondary" onclick="document.getElementById('quick-instructions').value=''"><i class="fas fa-times"></i> Clear</button>
                    </div>
                </div>
            </div>
            <div id="quick-draft-result" style="display:none"></div>
        </div>
        <div class="ai-content-card">
            <h3><i class="fas fa-clock" style="color:var(--ai-warning)"></i> Recent Drafts</h3>
            <div id="recent-drafts-list"><div class="loading">Loading...</div></div>
        </div>
    </div>

    <!-- DRAFTS -->
    <div id="panel-drafts" class="panel">
        <div class="section-header"><h2>Drafts</h2></div>
        <div id="drafts-list"><div class="loading">Loading...</div></div>
    </div>

    <!-- SENT -->
    <div id="panel-sent" class="panel">
        <div class="section-header"><h2>Sent AI-Assisted Messages</h2></div>
        <div id="sent-list"><div class="loading">Loading...</div></div>
    </div>

    <!-- TEMPLATES -->
    <div id="panel-templates" class="panel">
        <div class="section-header">
            <h2>Templates</h2>
            <div>
                <button class="btn btn-primary btn-sm" onclick="showCreateTemplate()"><i class="fas fa-plus"></i> Create</button>
                <button class="btn btn-secondary btn-sm" onclick="generateAITemplate()"><i class="fas fa-magic"></i> AI Generate</button>
            </div>
        </div>
        <div id="templates-list"><div class="loading">Loading...</div></div>
        <div id="create-template-form" style="display:none"></div>
    </div>

    <!-- SUMMARIZE -->
    <div id="panel-summarize" class="panel">
        <div class="section-header"><h2>Communication Summaries</h2></div>
        <div class="ai-content-card">
            <h3><i class="fas fa-compress-alt" style="color:var(--ai-accent)"></i> Summarize History</h3>
            <div class="grid-3">
                <div class="form-group">
                    <label>Context Type</label>
                    <select id="sum-ctx-type" class="form-control">
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                        <option value="invoice">Invoice</option>
                        <option value="ticket">Support Ticket</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Record ID</label>
                    <input type="number" id="sum-ctx-id" class="form-control" placeholder="Record ID">
                </div>
                <div class="form-group">
                    <label>Messages</label>
                    <input type="number" id="sum-count" class="form-control" value="20" min="5" max="100">
                </div>
            </div>
            <button class="btn btn-primary" onclick="doSummarize()"><i class="fas fa-compress"></i> Summarize</button>
            <div id="summary-result" style="display:none"></div>
        </div>
    </div>

    <!-- CLASSIFY -->
    <div id="panel-classify" class="panel">
        <div class="section-header"><h2>Classify Communication</h2></div>
        <div class="ai-content-card">
            <div class="form-group">
                <label>Message Text</label>
                <textarea id="classify-text" class="form-control" rows="5" placeholder="Paste or type a message to classify..."></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Context Type</label>
                    <select id="classify-ctx-type" class="form-control">
                        <option value="">Auto-detect</option>
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Record ID</label>
                    <input type="number" id="classify-ctx-id" class="form-control" placeholder="Optional">
                </div>
            </div>
            <button class="btn btn-primary" onclick="doClassify()"><i class="fas fa-tags"></i> Classify</button>
            <div id="classify-result" style="display:none"></div>
        </div>
    </div>

    <!-- FOLLOW-UPS -->
    <div id="panel-followups" class="panel">
        <div class="section-header">
            <h2>Follow-Ups</h2>
            <button class="btn btn-primary btn-sm" onclick="detectFollowUps()"><i class="fas fa-search"></i> Detect Follow-Ups</button>
        </div>
        <div id="followups-list"><div class="empty-state"><i class="fas fa-bell"></i>Click "Detect Follow-Ups" to scan for pending communications</div></div>
    </div>

    <!-- TRANSLATE -->
    <div id="panel-translate" class="panel">
        <div class="section-header"><h2>Translation</h2></div>
        <div class="ai-content-card">
            <div class="form-group">
                <label>Original Text</label>
                <textarea id="translate-text" class="form-control" rows="5" placeholder="Enter text to translate..."></textarea>
            </div>
            <div class="grid-3">
                <div class="form-group">
                    <label>From</label>
                    <select id="translate-from" class="form-control">
                        <option value="en">English</option><option value="ar">Arabic</option><option value="fr">French</option>
                        <option value="de">German</option><option value="es">Spanish</option><option value="it">Italian</option>
                        <option value="pt">Portuguese</option><option value="zh">Chinese</option><option value="ja">Japanese</option>
                        <option value="ko">Korean</option><option value="tr">Turkish</option><option value="hi">Hindi</option>
                        <option value="nl">Dutch</option><option value="ru">Russian</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>To</label>
                    <select id="translate-to" class="form-control">
                        <option value="ar">Arabic</option><option value="en">English</option><option value="fr">French</option>
                        <option value="de">German</option><option value="es">Spanish</option><option value="it">Italian</option>
                        <option value="pt">Portuguese</option><option value="zh">Chinese</option><option value="ja">Japanese</option>
                        <option value="ko">Korean</option><option value="tr">Turkish</option><option value="hi">Hindi</option>
                        <option value="nl">Dutch</option><option value="ru">Russian</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button class="btn btn-primary" onclick="doTranslate()" style="width:100%"><i class="fas fa-language"></i> Translate</button>
                </div>
            </div>
            <div id="translate-result" style="display:none"></div>
        </div>
    </div>

    <!-- SUGGESTIONS -->
    <div id="panel-suggestions" class="panel">
        <div class="section-header"><h2>Suggested Replies</h2></div>
        <div class="ai-content-card">
            <div class="form-group">
                <label>Incoming Message</label>
                <textarea id="suggestion-text" class="form-control" rows="5" placeholder="Paste the message you want to reply to..."></textarea>
            </div>
            <div class="form-group">
                <label>Desired Tone</label>
                <select id="suggestion-tone" class="form-control">
                    <option value="professional">Professional</option><option value="friendly">Friendly</option>
                    <option value="formal">Formal</option><option value="concise">Concise</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="doSuggestions()"><i class="fas fa-lightbulb"></i> Generate Suggestions</button>
            <div id="suggestions-result" style="display:none"></div>
        </div>
    </div>

    <!-- BATCH DRAFTS -->
    <div id="panel-batch" class="panel">
        <div class="section-header"><h2>Batch Drafting</h2></div>
        <div class="ai-content-card">
            <div class="grid-2">
                <div>
                    <div class="form-group">
                        <label>Target</label>
                        <select id="batch-target" class="form-control">
                            <option value="invoice">Overdue Invoices</option>
                            <option value="quotation">Pending Quotations</option>
                            <option value="supplier">Late Supplier Deliveries</option>
                            <option value="ticket">Unresolved Tickets</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Communication Type</label>
                        <select id="batch-type" class="form-control">
                            <option value="email">Email</option>
                            <option value="follow_up">Follow-Up</option>
                            <option value="reminder">Reminder</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tone</label>
                        <select id="batch-tone" class="form-control">
                            <option value="professional">Professional</option>
                            <option value="firm">Firm</option>
                            <option value="friendly">Friendly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Limit</label>
                        <input type="number" id="batch-limit" class="form-control" value="10" min="1" max="100">
                    </div>
                    <button class="btn btn-primary" onclick="doBatchDraft()"><i class="fas fa-layer-group"></i> Generate Batch</button>
                </div>
                <div>
                    <div id="batch-result" style="display:none"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- F26 INTELLIGENCE -->
    <div id="panel-f26" class="panel">
        <div class="section-header">
            <h2>Communication Intelligence</h2>
            <button class="btn btn-primary btn-sm" onclick="detectF26Events()"><i class="fas fa-radar"></i> Scan Events</button>
        </div>
        <div id="f26-events-list"><div class="empty-state"><i class="fas fa-bell"></i>Click "Scan Events" to detect communication-related intelligence</div></div>
    </div>

    <!-- F27 DOCUMENTS -->
    <div id="panel-f27" class="panel">
        <div class="section-header"><h2>Document-Aware Communication</h2></div>
        <div class="ai-content-card">
            <div class="grid-2">
                <div>
                    <h3 style="margin-bottom:12px"><i class="fas fa-file-alt" style="color:var(--ai-accent)"></i> Draft from Document</h3>
                    <div class="form-group">
                        <label>Document ID</label>
                        <input type="number" id="f27-doc-id" class="form-control" placeholder="F27 Document ID">
                    </div>
                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea id="f27-instructions" class="form-control" rows="3" placeholder="e.g., Draft a response to this supplier quotation"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tone</label>
                        <select id="f27-tone" class="form-control">
                            <option value="professional">Professional</option><option value="firm">Firm</option>
                            <option value="friendly">Friendly</option><option value="diplomatic">Diplomatic</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="doF27Draft()"><i class="fas fa-magic"></i> Draft from Document</button>
                </div>
                <div id="f27-result"></div>
            </div>
        </div>
    </div>

    <!-- F25 AGENT TASKS -->
    <div id="panel-f25" class="panel">
        <div class="section-header"><h2>Communication Agent Tasks</h2></div>
        <div class="ai-content-card">
            <h3><i class="fas fa-robot" style="color:var(--ai-accent)"></i> Create Agent Task</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group">
                        <label>Task Type</label>
                        <select id="f25-task-type" class="form-control">
                            <option value="draft_followups">Draft Follow-Ups</option>
                            <option value="bulk_reminders">Batch Payment Reminders</option>
                            <option value="escalation">Escalation Communications</option>
                            <option value="bulk_drafts">Batch Communication Drafts</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target</label>
                        <select id="f25-target" class="form-control">
                            <option value="invoice">Invoices</option>
                            <option value="quotation">Quotations</option>
                            <option value="supplier">Suppliers</option>
                            <option value="ticket">Tickets</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select id="f25-priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="doF25Task()"><i class="fas fa-robot"></i> Create Task</button>
                </div>
                <div id="f25-result"></div>
            </div>
        </div>
    </div>

    <!-- EVENTS / HISTORY -->
    <div id="panel-events" class="panel">
        <div class="section-header">
            <h2>Communication History</h2>
            <select id="events-filter" class="form-control" style="width:auto" onchange="loadEvents()">
                <option value="">All Events</option>
                <option value="draft_created">Drafts Created</option>
                <option value="classify">Classifications</option>
                <option value="translate">Translations</option>
                <option value="send_success">Sends</option>
                <option value="send_failed">Failed Sends</option>
                <option value="feedback">Feedback</option>
            </select>
        </div>
        <div id="events-list"><div class="loading">Loading...</div></div>
    </div>
</div>

<script>
const API = '<?= $apiBase ?>';

function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.classList.add('active');
    event.target.classList.add('active');
    loadPanelData(name);
}

function loadPanelData(name) {
    switch(name) {
        case 'overview': loadOverview(); break;
        case 'drafts': loadDrafts('draft'); break;
        case 'sent': loadDrafts('sent'); break;
        case 'templates': loadTemplates(); break;
        case 'events': loadEvents(); break;
    }
}

function loadOverview() {
    fetch(API + '?action=list_drafts&status=draft&limit=5')
        .then(r => r.json())
        .then(d => {
            document.getElementById('stat-drafts').textContent = d.count || 0;
            renderDraftList('recent-drafts-list', d.drafts || [], false);
        });
    fetch(API + '?action=list_drafts&status=sent&limit=100')
        .then(r => r.json())
        .then(d => document.getElementById('stat-sent').textContent = (d.drafts || []).length);
    fetch(API + '?action=detect_followups')
        .then(r => r.json())
        .then(d => {
            document.getElementById('stat-followups').textContent = d.count || 0;
            const urgent = (d.followups || []).filter(f => f.priority === 'high' || f.priority === 'urgent');
            document.getElementById('stat-urgent').textContent = urgent.length;
        });
}

function loadDrafts(status) {
    fetch(API + '?action=list_drafts&status=' + status + '&limit=50')
        .then(r => r.json())
        .then(d => renderDraftList(status === 'sent' ? 'sent-list' : 'drafts-list', d.drafts || [], true));
}

function renderDraftList(containerId, drafts, showActions) {
    const c = document.getElementById(containerId);
    if (!drafts.length) {
        c.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i>No drafts found</div>';
        return;
    }
    c.innerHTML = drafts.map(d => `
        <div class="list-item">
            <div class="info">
                <div class="title">${esc(d.subject || '(no subject)')}</div>
                <div class="meta">
                    <span class="badge badge-${d.status === 'sent' ? 'sent' : 'draft'}">${d.status}</span>
                    <span class="badge badge-${d.risk_level || 'low'}">${d.risk_level || 'low'}</span>
                    ${d.communication_type || ''} · ${d.tone || ''} · ${d.created_at || ''}
                </div>
            </div>
            <div class="actions">
                ${showActions && d.status !== 'sent' ? `<button class="btn btn-primary btn-sm" onclick="viewDraft(${d.id})"><i class="fas fa-eye"></i></button>` : ''}
                ${showActions && d.status !== 'sent' ? `<button class="btn btn-danger btn-sm" onclick="deleteDraft(${d.id})"><i class="fas fa-trash"></i></button>` : ''}
            </div>
        </div>
    `).join('');
}

function quickDraft() {
    const instructions = document.getElementById('quick-instructions').value.trim();
    if (!instructions) return alert('Please enter instructions');
    const fd = new FormData();
    fd.append('action', 'create_draft');
    fd.append('context_type', document.getElementById('quick-ctx-type').value);
    fd.append('context_id', document.getElementById('quick-ctx-id-val').value || '');
    fd.append('communication_type', document.getElementById('quick-comm-type').value);
    fd.append('tone', document.getElementById('quick-tone').value);
    fd.append('instructions', instructions);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('quick-draft-result');
        box.style.display = 'block';
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        let html = `<div class="success-box">Draft created (ID: ${d.draft_id})</div>`;
        if (d.subject) html += `<div style="margin-top:8px;font-weight:600">Subject: ${esc(d.subject)}</div>`;
        html += `<div class="result-box">${esc(d.body || '')}</div>`;
        if (d.source_references && d.source_references.length) {
            html += '<div style="margin-top:8px"><strong>Based on:</strong> ' + d.source_references.map(s => `<span class="source-ref">${esc(s.type || s.field || '')}: ${esc(s.value || s.name || s.id || '')}</span>`).join(' ') + '</div>';
        }
        if (d.placeholders && d.placeholders.length) {
            html += '<div class="warning-box"><i class="fas fa-exclamation-triangle"></i> Missing info: ' + d.placeholders.map(p => esc(p)).join(', ') + '</div>';
        }
        if (d.suggestions && d.suggestions.length) {
            html += '<div style="margin-top:12px"><strong>Suggestions:</strong></div>' + d.suggestions.map(s => `<div style="margin-top:6px;padding:8px;background:var(--bg-input);border-radius:6px;cursor:pointer" onclick="applySuggestion(' + d.draft_id + ', this)"><strong>' + esc(s.type) + ':</strong> ' + esc(s.text).substring(0, 150) + '...</div>`).join('');
        }
        box.innerHTML = html;
    });
}

function applySuggestion(draftId, el) {
    const text = el.textContent.split(': ').slice(1).join(': ');
    if (confirm('Apply this suggestion to the draft?')) {
        const fd = new FormData();
        fd.append('action', 'apply_suggestion');
        fd.append('draft_id', draftId);
        fd.append('suggestion_text', text);
        fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
            if (d.success) toast('Suggestion applied');
        });
    }
}

function doSummarize() {
    const fd = new FormData();
    fd.append('action', 'summarize');
    fd.append('context_type', document.getElementById('sum-ctx-type').value);
    fd.append('context_id', document.getElementById('sum-ctx-id').value);
    fd.append('message_count', document.getElementById('sum-count').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('summary-result');
        box.style.display = 'block';
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        let html = `<div class="result-box">${esc(d.summary_text || '')}</div>`;
        if (d.key_topics && d.key_topics.length) html += `<div style="margin-top:8px"><strong>Key Topics:</strong> ${d.key_topics.map(t => '<span class="source-ref">' + esc(t) + '</span>').join(' ')}</div>`;
        if (d.action_items && d.action_items.length) html += `<div style="margin-top:8px"><strong>Action Items:</strong></div><ul style="margin-left:20px">${d.action_items.map(i => '<li>' + esc(typeof i === 'string' ? i : i.description || '') + '</li>').join('')}</ul>`;
        if (d.sentiment) html += `<div style="margin-top:8px"><em>Sentiment: ${esc(d.sentiment)} (AI interpretation, not definitive)</em></div>`;
        box.innerHTML = html;
    });
}

function doClassify() {
    const fd = new FormData();
    fd.append('action', 'classify');
    fd.append('text', document.getElementById('classify-text').value);
    fd.append('context_type', document.getElementById('classify-ctx-type').value);
    fd.append('context_id', document.getElementById('classify-ctx-id').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('classify-result');
        box.style.display = 'block';
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        box.innerHTML = `
            <div class="success-box">
                <strong>Classification:</strong> <span class="badge badge-medium">${d.classification || 'unknown'}</span>
                Confidence: ${(d.confidence * 100).toFixed(1)}%
                ${d.deterministic ? ' (deterministic)' : ' (AI-assisted)'}
            </div>
            <div style="margin-top:8px"><strong>Priority:</strong> <span class="badge badge-${d.priority || 'normal'}">${d.priority || 'normal'}</span> — ${esc(d.priority_reason || '')}</div>
        `;
    });
}

function detectFollowUps() {
    fetch(API + '?action=detect_followups').then(r=>r.json()).then(d => {
        const c = document.getElementById('followups-list');
        if (!d.followups || !d.followups.length) {
            c.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i>No follow-ups needed</div>';
            return;
        }
        c.innerHTML = d.followups.map(f => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(f.reason || '')}</div>
                    <div class="meta">
                        <span class="badge badge-${f.priority || 'normal'}">${f.priority || 'normal'}</span>
                        ${f.context_type || ''} #${f.context_id || ''} · ${f.suggested_action || ''}
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn-primary btn-sm" onclick="handleFollowUp(${f.followup_id}, 'draft')">Draft</button>
                    <button class="btn btn-secondary btn-sm" onclick="handleFollowUp(${f.followup_id}, 'snooze', '${new Date(Date.now()+86400000).toISOString().split('T')[0]}')">Snooze</button>
                    <button class="btn btn-secondary btn-sm" onclick="handleFollowUp(${f.followup_id}, 'ignore')">Ignore</button>
                </div>
            </div>
        `).join('');
    });
}

function handleFollowUp(id, action, snooze) {
    const fd = new FormData();
    fd.append('action', 'update_followup');
    fd.append('followup_id', id);
    fd.append('followup_action', action);
    if (snooze) fd.append('snooze_until', snooze);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.success) { toast('Updated'); detectFollowUps(); }
    });
}

function doTranslate() {
    const fd = new FormData();
    fd.append('action', 'translate');
    fd.append('text', document.getElementById('translate-text').value);
    fd.append('source_language', document.getElementById('translate-from').value);
    fd.append('target_language', document.getElementById('translate-to').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('translate-result');
        box.style.display = 'block';
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        box.innerHTML = `
            <div class="side-by-side">
                <div><div class="label">Original (${esc(d.source_lang)})</div><div style="white-space:pre-wrap">${esc(d.original || '')}</div></div>
                <div><div class="label">Translated (${esc(d.target_lang)})</div><div style="white-space:pre-wrap">${esc(d.translated || '')}</div></div>
            </div>
            ${d.verification && !d.verification.passed ? '<div class="warning-box"><i class="fas fa-exclamation-triangle"></i> Value preservation issues: ' + d.verification.issues.map(i => esc(i)).join(', ') + '</div>' : ''}
        `;
    });
}

function doSuggestions() {
    const fd = new FormData();
    fd.append('action', 'get_suggestions');
    fd.append('body', document.getElementById('suggestion-text').value);
    fd.append('tone', document.getElementById('suggestion-tone').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('suggestions-result');
        box.style.display = 'block';
        if (!d.suggestions || !d.suggestions.length) { box.innerHTML = '<div class="empty-state">No suggestions generated</div>'; return; }
        box.innerHTML = d.suggestions.map(s => `
            <div class="ai-content-card" style="margin-bottom:12px">
                <h3 style="font-size:14px">${esc(s.type)}</h3>
                <div class="result-box">${esc(s.text || '')}</div>
                <div style="margin-top:8px"><button class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(this.closest('.card').querySelector('.result-box').textContent);toast('Copied')">Copy</button></div>
            </div>
        `).join('');
    });
}

function doBatchDraft() {
    const fd = new FormData();
    fd.append('action', 'create_communication_task');
    fd.append('task_type', 'bulk_drafts');
    fd.append('criteria', JSON.stringify({target: document.getElementById('batch-target').value, limit: parseInt(document.getElementById('batch-limit').value)}));
    fd.append('priority', 'normal');
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('batch-result');
        box.style.display = 'block';
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        box.innerHTML = `<div class="success-box"><i class="fas fa-check"></i> Batch task created${d.task_id ? ' (Task #' + d.task_id + ')' : ''}. Drafts will be generated and require approval before sending.</div>`;
    });
}

function detectF26Events() {
    fetch(API + '?action=detect_communication_events').then(r=>r.json()).then(d => {
        const c = document.getElementById('f26-events-list');
        if (!d.events || !d.events.length) { c.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i>No communication events detected</div>'; return; }
        c.innerHTML = d.events.map(e => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(e.title || '')}</div>
                    <div class="meta">
                        <span class="badge badge-${e.severity || 'low'}">${e.severity || 'low'}</span>
                        ${e.event_type || ''} · ${e.context_type || ''} #${e.context_id || ''}
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn-primary btn-sm" onclick="generateF26Suggestion(${e.intelligence_id || 0}, '${esc(JSON.stringify(e))}')"><i class="fas fa-magic"></i> Draft</button>
                </div>
            </div>
        `).join('');
    });
}

function generateF26Suggestion(eventId, eventDataStr) {
    const fd = new FormData();
    fd.append('action', 'generate_suggested_communication');
    fd.append('event_id', eventId);
    fd.append('event_data', eventDataStr);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.draft && d.draft.draft_id) toast('Draft created #' + d.draft.draft_id);
        else toast('Check drafts tab for results');
    });
}

function doF27Draft() {
    const fd = new FormData();
    fd.append('action', 'draft_from_document');
    fd.append('document_id', document.getElementById('f27-doc-id').value);
    fd.append('instructions', document.getElementById('f27-instructions').value);
    fd.append('tone', document.getElementById('f27-tone').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('f27-result');
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        box.innerHTML = `<div class="success-box">Draft created (ID: ${d.draft_id})</div><div class="result-box">${esc(d.body || '')}</div>`;
    });
}

function doF25Task() {
    const fd = new FormData();
    fd.append('action', 'create_communication_task');
    fd.append('task_type', document.getElementById('f25-task-type').value);
    fd.append('criteria', JSON.stringify({target: document.getElementById('f25-target').value}));
    fd.append('priority', document.getElementById('f25-priority').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('f25-result');
        if (d.error) { box.innerHTML = `<div class="error-box">${esc(d.error)}</div>`; return; }
        box.innerHTML = `<div class="success-box"><i class="fas fa-check"></i> Agent task created${d.task_id ? ' #' + d.task_id : ''}. The task will be processed through the F25 governance pipeline.</div>`;
    });
}

function loadEvents() {
    const filter = document.getElementById('events-filter').value;
    fetch(API + '?action=get_events' + (filter ? '&event_type=' + filter : '') + '&limit=50')
        .then(r=>r.json())
        .then(d => {
            const c = document.getElementById('events-list');
            if (!d.events || !d.events.length) { c.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i>No events found</div>'; return; }
            c.innerHTML = d.events.map(e => `
                <div class="list-item">
                    <div class="info">
                        <div class="title">${esc(e.event_type || '')} — ${e.entity_type || ''} #${e.entity_id || ''}</div>
                        <div class="meta">
                            ${e.user_id ? 'User #' + e.user_id : ''} · ${e.tokens_used || 0} tokens · ${e.cost_usd || 0} USD · ${e.created_at || ''}
                        </div>
                    </div>
                </div>
            `).join('');
        });
}

function loadTemplates() {
    fetch(API + '?action=list_templates').then(r=>r.json()).then(d => {
        const c = document.getElementById('templates-list');
        if (!d.templates || !d.templates.length) { c.innerHTML = '<div class="empty-state"><i class="fas fa-file-alt"></i>No templates</div>'; return; }
        c.innerHTML = d.templates.map(t => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(t.name || '')}</div>
                    <div class="meta">
                        <span class="badge badge-${t.status === 'active' ? 'sent' : 'draft'}">${t.status}</span>
                        ${t.category || ''} · ${t.communication_type || ''} · v${t.version || 1}
                        ${t.ai_generated ? ' · <i class="fas fa-magic" style="color:var(--ai-accent)"></i> AI' : ''}
                    </div>
                </div>
                <div class="actions">
                    ${t.status !== 'active' ? `<button class="btn btn-success btn-sm" onclick="activateTemplate(${t.id})">Activate</button>` : ''}
                </div>
            </div>
        `).join('');
    });
}

function showCreateTemplate() {
    const box = document.getElementById('create-template-form');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
    box.innerHTML = `
        <div class="ai-content-card">
            <h3>Create Template</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group"><label>Name</label><input id="tpl-name" class="form-control" placeholder="Template name"></div>
                    <div class="form-group"><label>Subject</label><input id="tpl-subject" class="form-control" placeholder="Use {{variable}} syntax"></div>
                    <div class="form-group"><label>Type</label><select id="tpl-type" class="form-control"><option value="email">Email</option><option value="internal_message">Internal</option></select></div>
                    <div class="form-group"><label>Category</label><select id="tpl-cat" class="form-control"><option value="payment">Payment</option><option value="quotation">Quotation</option><option value="order">Order</option><option value="support">Support</option><option value="custom">Custom</option></select></div>
                </div>
                <div>
                    <div class="form-group"><label>Body</label><textarea id="tpl-body" class="form-control" rows="8" placeholder="Use {{customer_name}}, {{invoice_number}}, etc."></textarea></div>
                </div>
            </div>
            <button class="btn btn-primary" onclick="createTemplate()">Create Template</button>
        </div>
    `;
}

function createTemplate() {
    const fd = new FormData();
    fd.append('action', 'create_template');
    fd.append('name', document.getElementById('tpl-name').value);
    fd.append('subject', document.getElementById('tpl-subject').value);
    fd.append('body', document.getElementById('tpl-body').value);
    fd.append('communication_type', document.getElementById('tpl-type').value);
    fd.append('category', document.getElementById('tpl-cat').value);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.error) { toast(d.error); return; }
        toast('Template created'); loadTemplates();
    });
}

function activateTemplate(id) {
    const fd = new FormData();
    fd.append('action', 'activate_template');
    fd.append('template_id', id);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.success) { toast('Template activated'); loadTemplates(); }
    });
}

function generateAITemplate() {
    const desc = prompt('Describe the template you need (e.g., "Payment reminder for overdue invoices")');
    if (!desc) return;
    const fd = new FormData();
    fd.append('action', 'generate_template');
    fd.append('description', desc);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.error) { toast(d.error); return; }
        toast('AI template created'); loadTemplates();
    });
}

function viewDraft(id) {
    fetch(API + '?action=get_draft&draft_id=' + id).then(r=>r.json()).then(d => {
        if (d.error) return toast(d.error);
        const content = `Subject: ${d.subject || '(none)'}\n\n${d.body || ''}`;
        alert(content);
    });
}

function deleteDraft(id) {
    if (!confirm('Delete this draft?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_draft');
    fd.append('draft_id', id);
    fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        toast('Deleted'); loadOverview();
    });
}

function loadAllData() { loadOverview(); }

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// Load overview on start
loadOverview();
</script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>
