<?php
/**
 * AI F29 — Universal AI Copilot: Command Center & Cross-Module Intelligence
 *
 * The single unified AI experience across the ERP.
 * Users simply tell it what they need — the Copilot orchestrates all capabilities.
 */
require_once __DIR__ . '/../bootstrap.php';
$db = getDB();
$companyId = $_SESSION['company_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? 1;

// Permission check
if (function_exists('hasPermission') && !hasPermission('ai.copilot.use')) {
    header('Location: ' . url('modules/ai/assistant/index.php'));
    exit;
}

// Detect context from URL parameters
$contextModule = $_GET['module'] ?? null;
$contextPage = $_GET['page'] ?? null;
$contextRecordType = $_GET['record_type'] ?? null;
$contextRecordId = isset($_GET['record_id']) && $_GET['record_id'] !== '' ? (int) $_GET['record_id'] : null;

$contextJson = json_encode(array_filter([
    'module' => $contextModule,
    'page' => $contextPage,
    'record_type' => $contextRecordType,
    'record_id' => $contextRecordId,
    'source' => 'web',
]));
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Copilot — Universal Command Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f1419; color: #e1e8ed; height: 100vh; overflow: hidden; }

        .copilot-layout { display: flex; height: 100vh; }

        /* Sidebar */
        .sidebar { width: 280px; background: #1a1f2e; border-right: 1px solid #2a3040; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #2a3040; }
        .sidebar-header h2 { font-size: 18px; color: #667eea; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { font-size: 22px; }

        .sidebar-section { padding: 12px 16px; }
        .sidebar-section-title { font-size: 11px; text-transform: uppercase; color: #8899aa; letter-spacing: 1px; margin-bottom: 8px; }

        .session-list { flex: 1; overflow-y: auto; padding: 8px 12px; }
        .session-item {
            padding: 10px 12px; border-radius: 8px; cursor: pointer; margin-bottom: 4px;
            font-size: 13px; color: #b0bec5; transition: all 0.2s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .session-item:hover { background: #2a3040; color: #fff; }
        .session-item.active { background: #667eea22; color: #667eea; border-left: 3px solid #667eea; }

        .new-chat-btn {
            margin: 12px; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600;
            display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;
        }
        .new-chat-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(102,126,234,0.4); }

        /* Main Chat Area */
        .main-area { flex: 1; display: flex; flex-direction: column; }

        .chat-header {
            padding: 16px 24px; background: #1a1f2e; border-bottom: 1px solid #2a3040;
            display: flex; justify-content: space-between; align-items: center;
        }
        .chat-header-left { display: flex; align-items: center; gap: 12px; }
        .chat-header h3 { font-size: 15px; color: #e1e8ed; }
        .context-badge {
            padding: 4px 10px; background: #667eea22; color: #667eea; border-radius: 12px;
            font-size: 11px; display: flex; align-items: center; gap: 4px;
        }
        .chat-header-actions { display: flex; gap: 8px; }
        .header-btn {
            padding: 6px 12px; background: transparent; border: 1px solid #2a3040; color: #8899aa;
            border-radius: 6px; cursor: pointer; font-size: 12px; transition: all 0.2s;
        }
        .header-btn:hover { border-color: #667eea; color: #667eea; }

        /* Messages */
        .messages-container { flex: 1; overflow-y: auto; padding: 24px; }
        .messages-inner { max-width: 800px; margin: 0 auto; }

        .welcome-card {
            text-align: center; padding: 60px 20px; max-width: 600px; margin: 0 auto;
        }
        .welcome-card h1 { font-size: 28px; margin-bottom: 8px; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .welcome-card p { color: #8899aa; font-size: 14px; margin-bottom: 30px; }

        .priorities-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .priority-card {
            background: #1a1f2e; border: 1px solid #2a3040; border-radius: 10px; padding: 16px; text-align: center; cursor: pointer; transition: all 0.2s;
        }
        .priority-card:hover { border-color: #667eea; transform: translateY(-2px); }
        .priority-card .count { font-size: 24px; font-weight: 700; color: #667eea; }
        .priority-card .label { font-size: 11px; color: #8899aa; margin-top: 4px; }

        .quick-commands { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .quick-cmd {
            padding: 8px 16px; background: #1a1f2e; border: 1px solid #2a3040; border-radius: 20px;
            font-size: 13px; color: #b0bec5; cursor: pointer; transition: all 0.2s;
        }
        .quick-cmd:hover { border-color: #667eea; color: #667eea; background: #667eea11; }

        /* Message bubbles */
        .message { margin-bottom: 20px; display: flex; gap: 12px; }
        .message.user { flex-direction: row-reverse; }
        .message-avatar {
            width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px; flex-shrink: 0;
        }
        .message.user .message-avatar { background: #667eea; color: white; }
        .message.assistant .message-avatar { background: #764ba2; color: white; }

        .message-content { max-width: 85%; }
        .message.user .message-content { text-align: right; }
        .message-bubble {
            padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5;
            word-wrap: break-word; white-space: pre-wrap;
        }
        .message.user .message-bubble { background: #667eea; color: white; border-bottom-right-radius: 4px; }
        .message.assistant .message-bubble { background: #1a1f2e; color: #e1e8ed; border-bottom-left-radius: 4px; border: 1px solid #2a3040; }

        /* Response components */
        .response-component { margin-top: 8px; }
        .component-evidence {
            background: #1a2332; border-left: 3px solid #667eea; padding: 8px 12px;
            border-radius: 0 6px 6px 0; font-size: 12px; color: #8899aa;
        }
        .component-plan {
            background: #1a2332; border: 1px solid #2a3040; border-radius: 8px; padding: 12px;
        }
        .component-plan .step { padding: 6px 0; font-size: 13px; color: #b0bec5; border-bottom: 1px solid #2a304022; }
        .component-plan .step:last-child { border-bottom: none; }
        .component-warning { background: #3d2200; border-left: 3px solid #f39c12; padding: 8px 12px; border-radius: 0 6px 6px 0; font-size: 13px; color: #f39c12; }
        .component-confirmation { background: #2d1a1a; border-left: 3px solid #e74c3c; padding: 8px 12px; border-radius: 0 6px 6px 0; font-size: 13px; color: #e74c3c; }
        .component-task { background: #1a2332; border-left: 3px solid #27ae60; padding: 8px 12px; border-radius: 0 6px 6px 0; font-size: 13px; color: #27ae60; }
        .component-intelligence { background: #1a2332; border: 1px solid #2a3040; border-radius: 8px; padding: 12px; }

        .response-actions { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
        .action-btn {
            padding: 6px 14px; border-radius: 6px; border: 1px solid #2a3040; background: transparent;
            color: #667eea; cursor: pointer; font-size: 12px; transition: all 0.2s; text-decoration: none;
        }
        .action-btn:hover { background: #667eea22; border-color: #667eea; }
        .action-btn.primary { background: #667eea; color: white; border-color: #667eea; }
        .action-btn.primary:hover { background: #5a6fd6; }

        /* Citations */
        .citations { margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
        .citation {
            padding: 4px 10px; background: #1a2332; border: 1px solid #2a3040; border-radius: 12px;
            font-size: 11px; color: #667eea; text-decoration: none; display: flex; align-items: center; gap: 4px;
        }
        .citation:hover { background: #667eea22; }

        /* Feedback */
        .feedback-bar { display: flex; gap: 4px; margin-top: 8px; }
        .feedback-btn {
            padding: 4px 8px; background: transparent; border: 1px solid #2a3040; border-radius: 4px;
            color: #8899aa; cursor: pointer; font-size: 11px; transition: all 0.2s;
        }
        .feedback-btn:hover { border-color: #667eea; color: #667eea; }
        .feedback-btn.active { background: #667eea22; border-color: #667eea; color: #667eea; }

        /* Input Area */
        .input-area { padding: 16px 24px; background: #1a1f2e; border-top: 1px solid #2a3040; }
        .input-inner { max-width: 800px; margin: 0 auto; }
        .input-wrapper {
            display: flex; gap: 10px; align-items: flex-end; background: #0f1419;
            border: 1px solid #2a3040; border-radius: 12px; padding: 8px 12px;
        }
        .input-wrapper:focus-within { border-color: #667eea; }
        .input-wrapper textarea {
            flex: 1; background: transparent; border: none; color: #e1e8ed; font-size: 14px;
            resize: none; outline: none; max-height: 120px; line-height: 1.5; font-family: inherit;
        }
        .input-wrapper textarea::placeholder { color: #556677; }
        .send-btn {
            width: 36px; height: 36px; border-radius: 8px; border: none;
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 14px; transition: all 0.2s; flex-shrink: 0;
        }
        .send-btn:hover { transform: scale(1.05); }
        .send-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
        .voice-btn {
            width: 36px; height: 36px; border-radius: 8px; border: 1px solid #2a3040;
            background: transparent; color: #8899aa; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;
        }
        .voice-btn:hover { border-color: #667eea; color: #667eea; }
        .input-hint { font-size: 11px; color: #556677; margin-top: 6px; text-align: center; }

        /* Loading */
        .typing-indicator { display: flex; gap: 4px; padding: 8px 0; }
        .typing-dot { width: 8px; height: 8px; border-radius: 50%; background: #667eea; animation: typingBounce 1.4s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingBounce { 0%, 60%, 100% { transform: translateY(0); opacity: 0.4; } 30% { transform: translateY(-8px); opacity: 1; } }

        /* Mobile */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .sidebar.open { display: flex; position: fixed; z-index: 100; height: 100vh; width: 260px; }
            .mobile-toggle { display: flex !important; }
            .chat-header { padding: 12px 16px; }
            .messages-container { padding: 16px; }
            .input-area { padding: 12px 16px; }
        }
        .mobile-toggle { display: none; width: 36px; height: 36px; border-radius: 8px; border: 1px solid #2a3040; background: transparent; color: #8899aa; cursor: pointer; align-items: center; justify-content: center; }
    </style>
    <?php ai_render_shell_styles(); ?>
</head>
<body class="ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.copilot'); ?>
    <div class="copilot-layout">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-brain"></i> Copilot</h2>
            </div>
            <button class="new-chat-btn" onclick="newSession()">
                <i class="fas fa-plus"></i> New Conversation
            </button>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Recent Conversations</div>
            </div>
            <div class="session-list" id="sessionList"></div>
        </div>

        <!-- Main Area -->
        <div class="main-area">
            <div class="chat-header">
                <div class="chat-header-left">
                    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <h3>AI Copilot</h3>
                    <span class="context-badge" id="contextBadge" style="display:none;">
                        <i class="fas fa-link"></i> <span id="contextText"></span>
                    </span>
                </div>
                <div class="chat-header-actions">
                    <button class="header-btn" onclick="showCapabilities()"><i class="fas fa-puzzle-piece"></i> Capabilities</button>
                    <button class="header-btn" onclick="showGovernance()"><i class="fas fa-shield-alt"></i> Governance</button>
                </div>
            </div>

            <div class="messages-container" id="messagesContainer">
                <div class="messages-inner" id="messagesInner">
                    <!-- Welcome / Home Screen -->
                    <div id="welcomeScreen">
                        <div class="welcome-card">
                            <h1>What can I help you with?</h1>
                            <p>I'm your unified AI assistant. I can answer questions, search across your ERP, analyze data, manage documents, and coordinate tasks — all from one place.</p>
                            <div class="priorities-grid" id="prioritiesGrid"></div>
                            <div class="quick-commands" id="quickCommands"></div>
                        </div>
                    </div>
                    <!-- Messages will appear here -->
                    <div id="messagesList"></div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="input-area">
                <div class="input-inner">
                    <div class="input-wrapper">
                        <textarea id="messageInput" rows="1" placeholder="Ask me anything about your ERP..." onkeydown="handleKeyDown(event)" oninput="autoResize(this)"></textarea>
                        <button class="voice-btn" onclick="startVoiceInput()" title="Voice input"><i class="fas fa-microphone"></i></button>
                        <button class="send-btn" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
                    </div>
                    <div class="input-hint">Press Enter to send • Shift+Enter for new line • Try: "What's important today?"</div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const BASE_URL = window.location.href.split('?')[0];
    const API_URL = new URL('../api/copilot.php', window.location.href).href;
    const CONTEXT = <?= $contextJson ?>;
    let currentSessionId = null;
    let isProcessing = false;

    // ===== INITIALIZATION =====
    document.addEventListener('DOMContentLoaded', () => {
        loadHome();
        loadSessions();
        if (CONTEXT.record_type) {
            document.getElementById('contextBadge').style.display = 'flex';
            document.getElementById('contextText').textContent = CONTEXT.record_type + ' #' + CONTEXT.record_id;
        }
    });

    // ===== CORE: Send Message =====
    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const text = input.value.trim();
        if (!text || isProcessing) return;

        input.value = '';
        autoResize(input);
        hideWelcome();

        // Show user message
        appendMessage('user', text);

        // Show typing indicator
        showTyping();
        isProcessing = true;
        document.getElementById('sendBtn').disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('text', text);
            formData.append('context', JSON.stringify(CONTEXT));
            if (currentSessionId) formData.append('session_id', currentSessionId);

            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const result = await resp.json();

            hideTyping();

            if (result.session_id) currentSessionId = result.session_id;

            // Render assistant response
            renderCopilotResponse(result);

            loadSessions();
        } catch (err) {
            hideTyping();
            appendMessage('assistant', '⚠️ Something went wrong. Please try again.');
        }

        isProcessing = false;
        document.getElementById('sendBtn').disabled = false;
    }

    // ===== Render Copilot Response =====
    function renderCopilotResponse(result) {
        const container = document.getElementById('messagesList');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message assistant';

        let html = '<div class="message-avatar"><i class="fas fa-brain"></i></div>';
        html += '<div class="message-content">';
        html += '<div class="message-bubble">' + escapeHtml(result.answer || 'I could not determine a response.') + '</div>';

        // Render components
        if (result.components) {
            for (const comp of result.components) {
                html += renderComponent(comp);
            }
        }

        // Citations
        if (result.citations && result.citations.length > 0) {
            html += '<div class="citations">';
            for (const cite of result.citations) {
                html += '<a class="citation" href="' + escapeHtml(cite.url) + '" target="_blank"><i class="fas fa-external-link-alt"></i> ' + escapeHtml(cite.label) + '</a>';
            }
            html += '</div>';
        }

        // Action buttons
        if (result.actions && result.actions.length > 0) {
            html += '<div class="response-actions">';
            for (const act of result.actions) {
                if (act.url) {
                    html += '<a class="action-btn primary" href="' + escapeHtml(act.url) + '">' + escapeHtml(act.label) + '</a>';
                } else {
                    html += '<button class="action-btn primary" onclick="handleAction(\'' + act.type + '\')">' + escapeHtml(act.label) + '</button>';
                }
            }
            html += '</div>';
        }

        // Warnings
        if (result.warnings && result.warnings.length > 0) {
            for (const warn of result.warnings) {
                html += '<div class="response-component"><div class="component-warning">⚠️ ' + escapeHtml(warn) + '</div></div>';
            }
        }

        // Feedback
        html += '<div class="feedback-bar">';
        html += '<button class="feedback-btn" onclick="submitFeedback(' + (result.message_id || 0) + ', \'helpful\', this)">👍 Helpful</button>';
        html += '<button class="feedback-btn" onclick="submitFeedback(' + (result.message_id || 0) + ', \'not_helpful\', this)">👎 Not helpful</button>';
        html += '<button class="feedback-btn" onclick="submitFeedback(' + (result.message_id || 0) + ', \'incorrect\', this)">⚠️ Incorrect</button>';
        html += '</div>';

        // Latency
        if (result.latency_ms) {
            html += '<div style="font-size:10px;color:#556677;margin-top:4px;">' + result.latency_ms + 'ms</div>';
        }

        html += '</div>';
        msgDiv.innerHTML = html;
        container.appendChild(msgDiv);
        scrollToBottom();
    }

    function renderComponent(comp) {
        let html = '<div class="response-component">';
        switch (comp.type) {
            case 'answer':
                // Already in the main bubble
                break;
            case 'evidence':
                html += '<div class="component-evidence"><strong>📋 Evidence:</strong><br>';
                for (const item of comp.items) {
                    html += '• ' + escapeHtml(item) + '<br>';
                }
                html += '</div>';
                break;
            case 'plan':
                html += '<div class="component-plan"><strong>📝 Proposed Plan</strong>';
                for (let i = 0; i < comp.steps.length; i++) {
                    const step = comp.steps[i];
                    html += '<div class="step">' + (i + 1) + '. ' + escapeHtml(step.action || step.description || '') + '</div>';
                }
                if (comp.requires_confirmation) {
                    html += '<div style="margin-top:8px;display:flex;gap:6px;">';
                    html += '<button class="action-btn primary" onclick="handleAction(\'execute_plan\')">Execute Plan</button>';
                    html += '<button class="action-btn" onclick="handleAction(\'change_plan\')">Change Plan</button>';
                    html += '<button class="action-btn" onclick="handleAction(\'cancel_plan\')">Cancel</button>';
                    html += '</div>';
                }
                html += '</div>';
                break;
            case 'task_progress':
                html += '<div class="component-task"><i class="fas fa-robot"></i> ' + escapeHtml(comp.message || 'Background task created.') + '<br>';
                html += '<span style="font-size:11px;">Task: ' + escapeHtml(comp.task_key || '') + ' (' + escapeHtml(comp.status || '') + ')</span></div>';
                break;
            case 'intelligence':
                html += '<div class="component-intelligence"><strong>🔍 Intelligence Findings</strong>';
                if (comp.findings && comp.findings.length > 0) {
                    for (const f of comp.findings) {
                        const severity = f.severity || 'medium';
                        const color = severity === 'critical' ? '#e74c3c' : severity === 'high' ? '#f39c12' : '#667eea';
                        html += '<div style="margin:4px 0;padding:6px 8px;background:#0f1419;border-radius:4px;border-left:3px solid ' + color + ';font-size:13px;">';
                        html += '<span style="color:' + color + ';font-weight:600;">' + escapeHtml(severity.toUpperCase()) + '</span> ';
                        html += escapeHtml(f.title || f.description || '');
                        html += '</div>';
                    }
                }
                if (comp.recommendations && comp.recommendations.length > 0) {
                    html += '<div style="margin-top:8px;font-size:12px;color:#8899aa;"><strong>Recommended:</strong></div>';
                    for (const r of comp.recommendations) {
                        html += '<div style="font-size:12px;color:#b0bec5;">• ' + escapeHtml(r.recommendation || r.action || '') + '</div>';
                    }
                }
                html += '</div>';
                break;
            case 'confirmation':
                html += '<div class="component-confirmation"><i class="fas fa-shield-alt"></i> ';
                html += 'This action requires ' + (comp.requires_review ? 'review' : 'confirmation') + ' (Risk: ' + escapeHtml(comp.risk_level || 'medium') + ')';
                html += '</div>';
                break;
        }
        html += '</div>';
        return html;
    }

    // ===== Session Management =====
    async function newSession() {
        currentSessionId = null;
        document.getElementById('messagesList').innerHTML = '';
        document.getElementById('welcomeScreen').style.display = 'block';
        loadHome();
    }

    async function loadSessions() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_recent_sessions');
            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const result = await resp.json();
            const list = document.getElementById('sessionList');
            if (result.success && result.sessions.length > 0) {
                list.innerHTML = result.sessions.map(s =>
                    '<div class="session-item ' + (s.id == currentSessionId ? 'active' : '') + '" onclick="loadSession(' + s.id + ')">' +
                    escapeHtml(s.first_message || 'Session ' + s.id) +
                    '</div>'
                ).join('');
            } else {
                list.innerHTML = '<div style="padding:16px;text-align:center;color:#556677;font-size:12px;">No conversations yet</div>';
            }
        } catch (e) {}
    }

    async function loadSession(sessionId) {
        currentSessionId = sessionId;
        document.getElementById('welcomeScreen').style.display = 'none';
        const list = document.getElementById('messagesList');
        list.innerHTML = '';

        try {
            const formData = new FormData();
            formData.append('action', 'get_history');
            formData.append('session_id', sessionId);
            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const result = await resp.json();
            if (result.success) {
                for (const msg of result.messages) {
                    appendMessage(msg.role, msg.content_text);
                }
            }
        } catch (e) {}
        loadSessions();
    }

    // ===== Home Data =====
    async function loadHome() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_home');
            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const result = await resp.json();
            if (result.success) {
                const home = result.home;

                // Priorities grid
                const grid = document.getElementById('prioritiesGrid');
                const items = [
                    { count: (home.today_priorities || []).length, label: 'Priority Items', icon: 'fas fa-exclamation-triangle', color: '#e74c3c' },
                    { count: (home.active_tasks || []).length, label: 'Active Tasks', icon: 'fas fa-robot', color: '#667eea' },
                    { count: (home.pending_approvals || []).length, label: 'Approvals', icon: 'fas fa-check-circle', color: '#f39c12' },
                    { count: (home.important_alerts || []).length, label: 'Alerts', icon: 'fas fa-bell', color: '#e74c3c' },
                    { count: (home.recent_documents || []).length, label: 'Documents', icon: 'fas fa-file-alt', color: '#27ae60' },
                ];
                grid.innerHTML = items.map(it =>
                    '<div class="priority-card"><div class="count" style="color:' + it.color + ';">' + it.count + '</div><div class="label">' + it.label + '</div></div>'
                ).join('');

                // Quick commands
                const cmds = document.getElementById('quickCommands');
                cmds.innerHTML = (home.quick_commands || []).slice(0, 8).map(c =>
                    '<div class="quick-cmd" onclick="sendQuickCommand(\'' + escapeHtml(c.text) + '\')">' + escapeHtml(c.text) + '</div>'
                ).join('');
            }
        } catch (e) {}
    }

    function sendQuickCommand(text) {
        document.getElementById('messageInput').value = text;
        sendMessage();
    }

    // ===== Helper Functions =====
    function appendMessage(role, text) {
        const container = document.getElementById('messagesList');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message ' + role;
        const icon = role === 'user' ? 'fas fa-user' : 'fas fa-brain';
        msgDiv.innerHTML = '<div class="message-avatar"><i class="' + icon + '"></i></div><div class="message-content"><div class="message-bubble">' + escapeHtml(text) + '</div></div>';
        container.appendChild(msgDiv);
        scrollToBottom();
    }

    function showTyping() {
        const container = document.getElementById('messagesList');
        const div = document.createElement('div');
        div.id = 'typingIndicator';
        div.className = 'message assistant';
        div.innerHTML = '<div class="message-avatar"><i class="fas fa-brain"></i></div><div class="message-content"><div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div></div>';
        container.appendChild(div);
        scrollToBottom();
    }

    function hideTyping() {
        const el = document.getElementById('typingIndicator');
        if (el) el.remove();
    }

    function hideWelcome() {
        document.getElementById('welcomeScreen').style.display = 'none';
    }

    function scrollToBottom() {
        const c = document.getElementById('messagesContainer');
        setTimeout(() => c.scrollTop = c.scrollHeight, 50);
    }

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    function handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    }

    async function submitFeedback(messageId, type, btn) {
        if (!messageId) return;
        const formData = new FormData();
        formData.append('action', 'submit_feedback');
        formData.append('message_id', messageId);
        formData.append('feedback_type', type);
        await fetch(API_URL, { method: 'POST', body: formData });
        // Visual feedback
        btn.classList.add('active');
    }

    function handleAction(type) {
        if (type === 'execute_plan') {
            document.getElementById('messageInput').value = 'Execute the plan';
            sendMessage();
        } else if (type === 'change_plan') {
            document.getElementById('messageInput').value = 'I want to modify the plan';
            sendMessage();
        }
    }

    function showCapabilities() { alert('Capabilities panel — shows all registered AI capabilities available to you.'); }
    function showGovernance() { window.open(new URL('../governance/index.php', window.location.href).href, '_blank'); }
    function startVoiceInput() { window.open(new URL('../voice/index.php', window.location.href).href, '_blank'); }
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>
