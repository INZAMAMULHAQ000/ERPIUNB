<?php
/**
 * AI F15 — Command Center.
 *
 * Universal AI interface for natural language ERP control.
 */
$pageTitle = 'AI Command Center';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.assistant.access')) {
    die('Access Denied');
}

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;
$companyId = $_SESSION['company_id'] ?? 1;

// Initialize router with defaults
AICapabilityRouter::registerDefaults();

// Seed suggestions if needed
$existingSuggestions = $db->query("SELECT COUNT(*) FROM ai_command_suggestions")->fetchColumn();
if ($existingSuggestions == 0) {
    AICommandCenterService::seedSuggestions($db);
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'send':
            $input = trim($_POST['message'] ?? '');
            $conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : null;

            // Gather page context from client
            $pageContext = [];
            if (!empty($_POST['context_module'])) $pageContext['module'] = $_POST['context_module'];
            if (!empty($_POST['context_page'])) $pageContext['page'] = $_POST['context_page'];
            if (!empty($_POST['context_record_type'])) $pageContext['record_type'] = $_POST['context_record_type'];
            if (!empty($_POST['context_record_id'])) $pageContext['record_id'] = (int) $_POST['context_record_id'];

            $result = AICommandCenterService::processCommand($db, $userId, $input, $conversationId, $pageContext);
            echo json_encode($result);
            break;

        case 'conversations':
            $conversations = AICommandCenterService::getConversations($db, $userId, 20);
            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;

        case 'get_conversation':
            $convId = (int) ($_POST['conversation_id'] ?? 0);
            $conversation = AICommandCenterService::getConversation($db, $convId, $userId);
            echo json_encode(['success' => $conversation !== null, 'conversation' => $conversation]);
            break;

        case 'suggestions':
            $module = $_POST['module'] ?? null;
            $suggestions = AICommandCenterService::getSuggestions($db, $module);
            echo json_encode(['success' => true, 'suggestions' => $suggestions]);
            break;

        case 'history':
            $history = AICommandCenterService::getExecutionHistory($db, $userId, 20);
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// Get initial data
$conversations = AICommandCenterService::getConversations($db, $userId, 5);
$suggestions = AICommandCenterService::getSuggestions($db);
$insightCounts = AIInsightService::getUserInsightCounts($db, $userId);
?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.command_center'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-header">
    <div>
        <h1 class="ai-page-title">
            <span class="ai-page-icon"><i class="fas fa-terminal"></i></span>
            AI Command Center
        </h1>
        <p class="ai-page-subtitle">Ask anything about your ERP in natural language</p>
    </div>
</div>

<div class="ai-page-container">
<nav class="flex flex-wrap gap-2 mb-5" aria-label="AI navigation">
    <a href="<?= url('modules/ai/assistant/index.php') ?>" class="bg-slate-700/50 border border-slate-600 text-slate-200 px-3 py-2 rounded hover:bg-slate-600 text-sm transition-colors">
        <i class="fas fa-robot mr-1 text-blue-400"></i>AI Assistant
    </a>
    <a href="<?= url('modules/ai/command-center/index.php') ?>" class="bg-blue-600/20 border border-blue-500/40 text-blue-300 px-3 py-2 rounded text-sm font-medium transition-colors">
        <i class="fas fa-terminal mr-1"></i>Command Center
    </a>
    <a href="<?= url('modules/ai/insights/index.php') ?>" class="bg-slate-700/50 border border-slate-600 text-slate-200 px-3 py-2 rounded hover:bg-slate-600 text-sm transition-colors">
        <i class="fas fa-lightbulb mr-1 text-yellow-400"></i>Insights
    </a>
    <a href="<?= url('modules/ai/automation/index.php') ?>" class="bg-slate-700/50 border border-slate-600 text-slate-200 px-3 py-2 rounded hover:bg-slate-600 text-sm transition-colors">
        <i class="fas fa-bolt mr-1 text-orange-400"></i>Automation
    </a>
    <a href="<?= url('modules/ai/workflows/index.php') ?>" class="bg-slate-700/50 border border-slate-600 text-slate-200 px-3 py-2 rounded hover:bg-slate-600 text-sm transition-colors">
        <i class="fas fa-diagram-project mr-1 text-sky-400"></i>Workflow Studio
    </a>
    <a href="<?= url('modules/ai/agents/index.php') ?>" class="bg-slate-700/50 border border-slate-600 text-slate-200 px-3 py-2 rounded hover:bg-slate-600 text-sm transition-colors">
        <i class="fas fa-user-cog mr-1 text-green-400"></i>Agents
    </a>
</nav>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Chat Area -->
    <div class="lg:col-span-3">
        <div class="ai-content-card flex flex-col" style="height: 600px;">
            <!-- Chat Messages -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-4">
                <!-- Welcome Message -->
                <div class="flex gap-3">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm flex-shrink-0">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="bg-slate-700/50 rounded-lg p-3 max-w-xl">
                        <p class="text-sm text-slate-200">Hello! I'm your AI Command Center. I can help you:</p>
                        <ul class="text-sm text-slate-300 mt-2 space-y-1">
                            <li>🔍 Search for records (requisitions, suppliers, orders)</li>
                            <li>🧭 Navigate to any ERP page</li>
                            <li>📚 Find company policies and knowledge</li>
                            <li>📊 Analyze data and get insights</li>
                            <li>✅ Create tasks and set up automations</li>
                            <li>🤖 Delegate complex reviews to AI agents</li>
                        </ul>
                        <p class="text-sm text-slate-400 mt-2">Just type your request in natural language below.</p>
                    </div>
                </div>
            </div>

            <!-- Suggested Actions -->
            <div id="suggestionsBar" class="border-t border-white/10 px-4 py-2 flex gap-2 overflow-x-auto">
                <?php foreach (array_slice($suggestions, 0, 5) as $sug): ?>
                <button onclick="sendSuggestion('<?= htmlspecialchars($sug['suggestion_text'], ENT_QUOTES) ?>')" class="flex-shrink-0 px-3 py-1 bg-slate-700/50 hover:bg-slate-600/50 rounded-full text-xs text-slate-300 transition">
                    <?= htmlspecialchars($sug['suggestion_text']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Input Area -->
            <div class="border-t border-white/10 p-4">
                <div class="flex gap-2">
                    <input type="text" id="commandInput" placeholder="Ask ERP AI anything..."
                           class="flex-1 ai-form-input"
                           onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMessage();}">
                    <button onclick="sendMessage()" id="sendBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <span id="contextIndicator" class="text-xs text-slate-500"></span>
                    <span id="intentBadge" class="text-xs text-slate-500 hidden"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4">
        <!-- Quick Stats -->
        <div class="ai-content-card">
            <h3 class="text-sm font-semibold text-slate-100 mb-3">Quick Stats</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-400">Active Insights</span>
                    <span class="font-medium"><?= $insightCounts['total'] ?></span>
                </div>
                <?php if ($insightCounts['critical'] > 0): ?>
                <div class="flex justify-between text-red-400">
                    <span>Critical</span>
                    <span class="font-medium"><?= $insightCounts['critical'] ?></span>
                </div>
                <?php endif; ?>
                <?php if ($insightCounts['warning'] > 0): ?>
                <div class="flex justify-between text-yellow-400">
                    <span>Warnings</span>
                    <span class="font-medium"><?= $insightCounts['warning'] ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Conversations -->
        <div class="ai-content-card">
            <h3 class="text-sm font-semibold text-slate-100 mb-3">Recent Conversations</h3>
            <div class="space-y-2">
                <?php if (empty($conversations)): ?>
                    <p class="text-xs text-slate-500">No conversations yet</p>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                    <button onclick="loadConversation(<?= $conv['id'] ?>)" class="w-full text-left p-2 rounded hover:bg-slate-700/50 text-xs transition-colors">
                        <div class="font-medium text-slate-100 truncate"><?= htmlspecialchars($conv['title'] ?? 'New Conversation') ?></div>
                        <div class="text-slate-500"><?= date('M j, H:i', strtotime($conv['last_message_at'])) ?></div>
                    </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="ai-content-card">
            <h3 class="text-sm font-semibold text-slate-100 mb-3">Quick Actions</h3>
            <div class="space-y-2">
                <a href="<?= url('modules/ai/insights/index.php') ?>" class="block p-2 rounded hover:bg-slate-700/50 text-xs text-slate-300 transition-colors">
                    <i class="fas fa-lightbulb mr-1 text-yellow-400"></i>View All Insights
                </a>
                <a href="<?= url('modules/ai/automation/index.php') ?>" class="block p-2 rounded hover:bg-slate-700/50 text-xs text-slate-300 transition-colors">
                    <i class="fas fa-bolt mr-1 text-orange-400"></i>Manage Automations
                </a>
                <a href="<?= url('modules/ai/workflows/index.php') ?>" class="block p-2 rounded hover:bg-slate-700/50 text-xs text-slate-300 transition-colors">
                    <i class="fas fa-diagram-project mr-1 text-sky-400"></i>Workflow Studio
                </a>
                <a href="<?= url('modules/ai/agents/index.php') ?>" class="block p-2 rounded hover:bg-slate-700/50 text-xs text-slate-300 transition-colors">
                    <i class="fas fa-user-cog mr-1 text-green-400"></i>AI Agents
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<script>
let currentConversationId = null;

function sendMessage() {
    const input = document.getElementById('commandInput');
    const message = input.value.trim();
    if (!message) return;

    // Add user message to chat
    addMessage('user', message);
    input.value = '';
    input.disabled = true;
    document.getElementById('sendBtn').disabled = true;

    // Show typing indicator
    const typingId = addTypingIndicator();

    // Get page context
    const context = {
        context_module: document.querySelector('meta[name="current-module"]')?.content || '',
        context_page: document.querySelector('meta[name="current-page"]')?.content || '',
        context_record_type: document.querySelector('meta[name="record-type"]')?.content || '',
        context_record_id: document.querySelector('meta[name="record-id"]')?.content || '',
    };

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('message', message);
    if (currentConversationId) formData.append('conversation_id', currentConversationId);
    Object.keys(context).forEach(k => { if (context[k]) formData.append(k, context[k]); });

    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            removeTypingIndicator(typingId);
            input.disabled = false;
            document.getElementById('sendBtn').disabled = false;

            if (result.success) {
                currentConversationId = result.conversation_id;

                // Show intent badge
                const badge = document.getElementById('intentBadge');
                badge.textContent = result.intent || '';
                badge.classList.remove('hidden');

                // Add assistant response
                const response = result.message || 'No response.';
                addMessage('assistant', response, result);

                // Add navigation button if applicable
                if (result.type === 'navigation' && result.route) {
                    addNavigationCard(result.title || 'Navigation', result.route, result.route_label || 'Open');
                }
                if (Array.isArray(result.navigation) && result.navigation.length > 0) {
                    result.navigation.forEach(nav => {
                        addNavigationCard(nav.label || result.title || 'Navigation', nav.url || result.route || '', nav.label || 'Open');
                    });
                }

                if (result.type === 'pending_confirmation' && result.pending_action_id) {
                    addPendingActionCard(result);
                }

                // Add data results if applicable
                if (result.type === 'data_result' && result.items && result.items.length > 0) {
                    addDataCard(result.title || 'Results', result.items, result.count);
                }

                // Update suggestions based on context
                updateSuggestions(result.source);
            } else {
                addMessage('assistant', result.error || 'Something went wrong.');
            }
        })
        .catch(err => {
            removeTypingIndicator(typingId);
            input.disabled = false;
            document.getElementById('sendBtn').disabled = false;
            addMessage('assistant', 'Sorry, something went wrong. Please try again.');
        });
}

function sendSuggestion(text) {
    document.getElementById('commandInput').value = text;
    sendMessage();
}

function addMessage(role, content, metadata = null) {
    const chat = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'flex gap-3';

    if (role === 'user') {
        div.innerHTML = `
            <div class="flex-1 flex justify-end">
                <div class="bg-blue-600 text-white rounded-lg p-3 max-w-xl text-sm">${escapeHtml(content)}</div>
            </div>
            <div class="w-8 h-8 bg-slate-600 rounded-full flex items-center justify-center text-slate-200 text-sm flex-shrink-0">
                <i class="fas fa-user"></i>
            </div>
        `;
    } else {
        div.innerHTML = `
            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm flex-shrink-0">
                <i class="fas fa-robot"></i>
            </div>
            <div class="bg-slate-700/50 rounded-lg p-3 max-w-xl">
                <div class="text-sm text-slate-200 whitespace-pre-wrap">${formatResponse(content)}</div>
            </div>
        `;
    }

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

function addNavigationCard(title, route, label) {
    const chat = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'flex gap-3';
    const href = resolveAppUrl(route);
    div.innerHTML = `
        <div class="w-8"></div>
        <div class="bg-sky-500/10 border border-sky-500/20 rounded-lg p-3 max-w-xl">
            <div class="text-sm font-medium text-sky-300">${escapeHtml(title)}</div>
            <a href="${escapeHtml(href)}" class="inline-block mt-2 px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                <i class="fas fa-external-link-alt mr-1"></i>${escapeHtml(label)}
            </a>
        </div>
    `;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

function addPendingActionCard(result) {
    const chat = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'flex gap-3';
    const preview = result.preview || {};
    const fields = Array.isArray(preview.fields) ? preview.fields.slice(0, 4) : [];
    const warnings = Array.isArray(preview.warnings) ? preview.warnings : [];
    div.innerHTML = `
        <div class="w-8"></div>
        <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-3 max-w-xl">
            <div class="text-sm font-semibold text-amber-300">${escapeHtml(result.action_name || result.title || 'Pending Action')}</div>
            <div class="mt-2 space-y-1 text-xs text-amber-200">
                ${fields.map(f => `<div class="flex justify-between gap-3"><span class="text-amber-400">${escapeHtml(f.label || '')}:</span><span class="font-medium text-right">${escapeHtml(String(f.value ?? ''))}</span></div>`).join('')}
            </div>
            ${warnings.length > 0 ? `<div class="mt-3 space-y-1">${warnings.map(w => `<div class="bg-slate-700/50 border border-amber-500/20 text-amber-200 px-2 py-1 rounded text-xs">${escapeHtml(w)}</div>`).join('')}</div>` : ''}
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" onclick="confirmPendingAction(${parseInt(result.pending_action_id, 10) || 0}, ${escapeHtml(JSON.stringify(result.confirmation_token || ''))}, this)" class="px-3 py-1.5 bg-amber-600 text-white rounded text-xs hover:bg-amber-700">
                    Confirm
                </button>
                <button type="button" onclick="cancelPendingAction(${parseInt(result.pending_action_id, 10) || 0})" class="px-3 py-1.5 bg-slate-700 border border-amber-500/30 text-amber-200 rounded text-xs hover:bg-slate-600">
                    Cancel
                </button>
            </div>
        </div>
    `;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

function addDataCard(title, items, count) {
    const chat = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'flex gap-3';
    const itemsHtml = items.map(i => `<div class="text-xs text-slate-300 py-0.5">${escapeHtml(i)}</div>`).join('');
    div.innerHTML = `
        <div class="w-8"></div>
        <div class="bg-slate-700/50 border border-slate-600 rounded-lg p-3 max-w-xl">
            <div class="text-sm font-medium text-slate-100">${escapeHtml(title)} <span class="text-slate-500">(${count} records)</span></div>
            <div class="mt-2 space-y-1">${itemsHtml}</div>
        </div>
    `;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

function resolveAppUrl(route) {
    const value = (route || '').toString().trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    const base = BASE_URL.replace(/\/+$/, '');
    return `${base}/${value.replace(/^\/+/, '')}`;
}

function addTypingIndicator() {
    const chat = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'flex gap-3 typing-indicator';
    div.id = 'typing-' + Date.now();
    div.innerHTML = `
        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm flex-shrink-0">
            <i class="fas fa-robot"></i>
        </div>
        <div class="bg-slate-700/50 rounded-lg p-3">
            <div class="flex gap-1">
                <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
        </div>
    `;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
    return div.id;
}

function removeTypingIndicator(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

function loadConversation(id) {
    currentConversationId = id;
    const formData = new FormData();
    formData.append('action', 'get_conversation');
    formData.append('conversation_id', id);

    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            if (result.success && result.conversation) {
                const chat = document.getElementById('chatMessages');
                chat.innerHTML = '';
                (result.conversation.messages || []).forEach(msg => {
                    addMessage(msg.role, msg.content);
                });
            }
        });
}

function updateSuggestions(source) {
    // Reload suggestions based on context
    const formData = new FormData();
    formData.append('action', 'suggestions');
    if (source === 'erp_data') formData.append('module', 'procurement');

    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            if (result.success && result.suggestions) {
                const bar = document.getElementById('suggestionsBar');
                bar.innerHTML = result.suggestions.slice(0, 5).map(s =>
                    `<button onclick="sendSuggestion('${escapeHtml(s.suggestion_text).replace(/'/g, "\\'")}')" class="flex-shrink-0 px-3 py-1 bg-slate-700/50 hover:bg-slate-600/50 rounded-full text-xs text-slate-300 transition">${escapeHtml(s.suggestion_text)}</button>`
                ).join('');
            }
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function confirmPendingAction(pendingId, token, btn = null) {
    if (!pendingId || !token) {
        alert('Missing pending action details.');
        return;
    }

    if (btn) btn.disabled = true;

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=confirm`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({
                pending_action_id: pendingId,
                token: token
            })
        });
        const data = await resp.json();

        if (!data.success) {
            alert(data.error || 'Failed to confirm action.');
            return;
        }

        if (data.data) {
            addMessage('assistant', data.data);
        }

        if (data.result) {
            addMessage('assistant', data.result.message || 'Action completed.', data.result);
        }

        await refreshPendingActions();
    } catch (err) {
        alert('Error confirming action.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

function formatResponse(text) {
    return escapeHtml(text)
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}

// Focus input on load
document.getElementById('commandInput').focus();
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
