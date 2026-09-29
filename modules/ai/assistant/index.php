<?php
/**
 * AI Assistant.
 *
 * Main AI Assistant chat interface.
 */
$pageTitle = 'AI Assistant';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_ui.php';

$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';

if (!hasPermission('ai.assistant.access')) {
    die('Access Denied');
}

$db = getDB();
$userId = $_SESSION['user_id'];

// F24: Policy engine status check for governance health indicator
$policyEngineStatus = 'unavailable';
if (class_exists('AIPolicyEngine')) {
    try {
        $policyResult = AIPolicyEngine::evaluate($db, 'ai_assistant_access', [
            'user_id' => $userId,
            'company_id' => $_SESSION['company_id'] ?? 1,
            'request_type' => 'assistant_access',
        ]);
        $policyEngineStatus = $policyResult['decision'] === 'deny' ? 'restricted' : 'active';
    } catch (\Throwable $e) {
        $policyEngineStatus = 'error';
    }
}

// F24: Get autonomy level
$autonomyLevel = 'suggest';
if (class_exists('AIPolicyEngine')) {
    $autonomyLevel = AIPolicyEngine::getAutonomyLevel($db, $_SESSION['company_id'] ?? null, null, $userId);
}

// Get available providers and models
$providers = AIProviderService::getProviders($db, true);
$allModels = [];
foreach ($providers as $p) {
    $models = AIModelService::getActiveModelsByProvider($db, $p['id']);
    foreach ($models as $m) {
        $allModels[] = $m;
    }
}

// Auto-provision a small NVIDIA starter set for NVIDIA NIM providers if missing
foreach ($providers as $p) {
    if ($p['provider_type'] !== 'nvidia_nim') continue;
    $existing = AIModelService::getActiveModelsByProvider($db, $p['id']);
    $seedModels = [
        [
            'model_id' => 'openai/gpt-oss-20b',
            'display_name' => 'openai/gpt-oss-20b',
            'description' => 'OpenAI GPT-OSS 20B',
            'model_type' => 'chat',
            'supports_chat' => 1,
            'supports_tool_calling' => 1,
        ],
        [
            'model_id' => 'google/diffusiongemma-26b-a4b-it',
            'display_name' => 'Google DiffusionGemma 26B A4B IT',
            'description' => 'Google DiffusionGemma 26B A4B IT',
            'model_type' => 'multimodal',
            'supports_chat' => 1,
            'supports_tool_calling' => 0,
        ],
    ];

    $existingIds = array_column($existing, 'model_id');
    $inserted = false;
    try {
        foreach ($seedModels as $seed) {
            if (in_array($seed['model_id'], $existingIds, true)) {
                continue;
            }
            $db->prepare("
                INSERT INTO ai_models (provider_id, model_id, display_name, description, model_type,
                    supports_chat, supports_tool_calling, is_active, is_default, created_by, updated_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?, NOW(), NOW())
            ")->execute([
                $p['id'],
                $seed['model_id'],
                $seed['display_name'],
                $seed['description'],
                $seed['model_type'],
                $seed['supports_chat'],
                $seed['supports_tool_calling'],
                $userId,
                $userId,
            ]);
            $inserted = true;
        }
        if ($inserted) {
            // Refresh model list
            $allModels = [];
            foreach ($providers as $pp) {
                foreach (AIModelService::getActiveModelsByProvider($db, $pp['id']) as $m) {
                    $allModels[] = $m;
                }
            }
        }
    } catch (Exception $e) { /* model may already exist */ }
    break; // only need to do this for one NVIDIA provider
}

// Get default provider/model
$defaultProvider = AIProviderService::getDefaultProvider($db);
$defaultModel = AIModelService::getDefaultModel($db);

$hasProviders = count($providers) > 0;
$hasModels = count($allModels) > 0;
?>
<?php if ($isEmbed): ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - ERP System</title>
    <script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/light-m365.css">
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/ibm-carbon.css">
</head>
<body class="bg-gray-50 ai-embed ai-module-page" data-theme="<?= $currentTheme ?>">
<?php else: ?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php endif; ?>
<style>
/* ═══ Chat Layout ═══ */
:root { --ai-chat-layout-offset: 180px; --ai-chat-layout-mobile-offset: 120px; }
body.ai-embed { --ai-chat-layout-offset: 120px; --ai-chat-layout-mobile-offset: 88px; }
.chat-layout { display: flex; height: calc(100vh - var(--ai-chat-layout-offset)); overflow: hidden; position: relative; }
.chat-sidebar { width: 280px; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; background: #fff; flex-shrink: 0; z-index: 20; }
.chat-sidebar-header { padding: 12px; border-bottom: 1px solid #e5e7eb; }
.chat-sidebar-list { flex: 1; overflow-y: auto; padding: 8px; }
.chat-main { flex: 1; display: flex; flex-direction: column; background: #f9fafb; min-width: 0; }
.chat-header { padding: 10px 16px; border-bottom: 1px solid #e5e7eb; background: #fff; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.chat-messages { flex: 1; overflow-y: auto; padding: 16px; }
.chat-input-area { padding: 12px 16px; border-top: 1px solid #e5e7eb; background: #fff; }
.msg-user { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.msg-user .msg-bubble { background: #3b82f6; color: #fff; padding: 10px 14px; border-radius: 12px 12px 4px 12px; max-width: 80%; white-space: pre-wrap; word-break: break-word; }
.msg-ai { display: flex; justify-content: flex-start; margin-bottom: 12px; }
.msg-ai .msg-bubble { background: #fff; border: 1px solid #e5e7eb; padding: 10px 14px; border-radius: 12px 12px 12px 4px; max-width: 80%; white-space: pre-wrap; word-break: break-word; }
.msg-system { display: flex; justify-content: center; margin-bottom: 8px; }
.msg-system .msg-bubble { background: #f3f4f6; color: #6b7280; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; }
.conv-item { padding: 10px 12px; border-radius: 8px; cursor: pointer; margin-bottom: 4px; transition: background 0.15s; }
.conv-item:hover { background: #f3f4f6; }
.conv-item.active { background: #eff6ff; border: 1px solid #bfdbfe; }
.conv-item .conv-title { font-size: 0.85rem; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-item .conv-meta { font-size: 0.7rem; color: #9ca3af; margin-top: 2px; }
.typing-indicator { display: inline-flex; gap: 4px; padding: 10px 14px; }
.typing-dot { width: 8px; height: 8px; background: #9ca3af; border-radius: 50%; animation: typing 1.4s infinite ease-in-out; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }
.nav-card { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 12px 16px; margin-top: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
.nav-card:hover { background: #e0f2fe; border-color: #7dd3fc; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.nav-card-icon { width: 36px; height: 36px; border-radius: 8px; background: #0ea5e9; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.nav-card-info { flex: 1; min-width: 0; }
.nav-card-label { font-weight: 600; font-size: 0.85rem; color: #0c4a6e; }
.nav-card-path { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
.nav-card-reason { font-size: 0.75rem; color: #475569; margin-top: 4px; }
.nav-card-arrow { color: #0ea5e9; font-size: 0.8rem; flex-shrink: 0; }
.tool-activity { margin-top: 6px; padding: 6px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 0.78rem; color: #166534; display: flex; align-items: center; gap: 6px; }
.tool-activity i { color: #22c55e; }
.tool-activity.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.tool-activity.error i { color: #ef4444; }
.feedback-row { margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.feedback-btn { border: 1px solid #d1d5db; background: #fff; color: #4b5563; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; }
.feedback-btn:hover { background: #f9fafb; border-color: #9ca3af; }
.feedback-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.feedback-status { font-size: 0.75rem; color: #16a34a; font-weight: 600; }

/* Mobile sidebar toggle */
.sidebar-toggle { display: none; position: fixed; bottom: 80px; left: 12px; z-index: 30; width: 44px; height: 44px; border-radius: 50%; background: #3b82f6; color: #fff; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.2); cursor: pointer; align-items: center; justify-content: center; font-size: 1.1rem; }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 15; }

/* ═══ Responsive: Tablet (≤768px) ═══ */
@media (max-width: 768px) {
    .chat-layout { height: calc(100vh - var(--ai-chat-layout-mobile-offset)); }
    .chat-sidebar { position: fixed; left: -300px; top: 0; bottom: 0; width: 280px; transition: left 0.25s ease; box-shadow: none; }
    .chat-sidebar.open { left: 0; box-shadow: 4px 0 24px rgba(0,0,0,0.15); }
    .sidebar-toggle { display: flex; }
    .sidebar-overlay.open { display: block; }
    .chat-header { padding: 8px 12px; gap: 6px; }
    .chat-header .flex.items-center.gap-3 { gap: 4px; flex-wrap: nowrap; overflow-x: auto; }
    .chat-header select { font-size: 0.75rem; padding: 4px 6px; max-width: 120px; }
    .chat-messages { padding: 12px; }
    .msg-user .msg-bubble, .msg-ai .msg-bubble { max-width: 88%; font-size: 0.875rem; }
    .chat-input-area { padding: 10px 12px; }
    .chat-input-area textarea { font-size: 0.875rem; }
}

/* ═══ Responsive: Mobile (≤480px) ═══ */
@media (max-width: 480px) {
    .chat-layout { height: calc(100vh - var(--ai-chat-layout-mobile-offset)); }
    .chat-header { padding: 6px 10px; flex-direction: column; align-items: flex-start; }
    .chat-header .flex.items-center.gap-3 { width: 100%; }
    .chat-header select { font-size: 0.7rem; padding: 3px 4px; max-width: 100px; }
    #convActions { gap: 4px; }
    .chat-messages { padding: 10px 8px; }
    .msg-user .msg-bubble, .msg-ai .msg-bubble { max-width: 92%; font-size: 0.8rem; padding: 8px 10px; }
    .chat-input-area { padding: 8px 10px; }
    .chat-input-area textarea { font-size: 0.8rem; min-height: 36px; }
    #contextIndicator { font-size: 0.65rem; }
    .chat-input-area .flex { gap: 6px; }
}
</style>

<?php if (!$isEmbed): ?>
<?php ai_render_module_nav('ai.assistant'); ?>
<?php endif; ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-header">
    <div class="flex justify-between items-start flex-wrap gap-4">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-robot"></i></span>
                AI Assistant
            </h1>
            <p class="ai-page-subtitle">Chat with AI models through your configured providers</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <div class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm">
                <span class="text-gray-500 hidden sm:inline">Governance:</span>
                <span class="ai-badge <?php echo $policyEngineStatus === 'active' ? 'ai-badge-success' : ($policyEngineStatus === 'restricted' ? 'ai-badge-danger' : '') ?>"<?php echo $policyEngineStatus !== 'active' && $policyEngineStatus !== 'restricted' ? ' style="background:#e5e7eb;color:#4b5563;"' : '' ?>>
                    <?php echo ucfirst($policyEngineStatus) ?>
                </span>
            </div>
            <div class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm">
                <span class="text-gray-500 hidden sm:inline">Autonomy:</span>
                <span class="ai-badge ai-badge-purple capitalize"><?php echo htmlspecialchars($autonomyLevel) ?></span>
            </div>
        </div>
    </div>
</div>

<?php if (!$hasProviders || !$hasModels): ?>
<div class="ai-content-card">
    <div class="ai-alert ai-alert-warning">
        <i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i>
        <div>
            <h3 class="text-lg font-semibold mb-2">Setup Required</h3>
            <p class="mb-4">
                <?php if (!$hasProviders): ?>
                No AI providers are configured. Please add a provider first.
                <?php else: ?>
                No AI models are available. Please add models to your providers.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="flex justify-center gap-3">
        <?php if (hasPermission("ai.providers.view")): ?>
        <a href="<?= url("modules/ai/providers/index.php") ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <i class="fas fa-server mr-1"></i>Configure Providers
        </a>
        <?php endif; ?>
        <?php if (hasPermission("ai.models.manage")): ?>
        <a href="<?= url("modules/ai/models/index.php") ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
            <i class="fas fa-brain mr-1"></i>Manage Models
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

<!-- Chat Layout -->
<div class="chat-layout bg-white rounded-lg shadow overflow-hidden">
    <!-- Mobile Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle conversations">
        <i class="fas fa-comments"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <button onclick="newChat()" class="w-full bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                <i class="fas fa-plus mr-1"></i>New Chat
            </button>
            <input type="text" id="convSearch" placeholder="Search conversations..."
                   class="w-full mt-2 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-blue-500"
                   oninput="searchConversations(this.value)">
        </div>
        <div class="chat-sidebar-list" id="convList">
            <div class="text-center text-gray-400 text-sm py-4">Loading...</div>
        </div>
    </div>

    <!-- Main Chat -->
    <div class="chat-main">
        <div class="chat-header">
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <select id="providerSelect" class="border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm bg-white font-medium text-gray-700" onchange="onProviderChange()">
                    <?php foreach ($providers as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($defaultProvider && $defaultProvider['id'] == $p['id']) ? 'selected' : '' ?>>
                        <?= sanitize($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select id="modelSelect" class="border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm bg-white text-gray-700" onchange="onModelChange()">
                    <?php foreach ($allModels as $m): ?>
                    <option value="<?= $m['id'] ?>" data-provider="<?= $m['provider_id'] ?>"
                            <?= ($defaultModel && $defaultModel['id'] == $m['id']) ? 'selected' : '' ?>>
                        <?= sanitize($m['display_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="contextIndicator" class="hidden text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded"></div>
            <div id="convActions" class="hidden flex items-center gap-2">
                <button onclick="renameConversation()" class="text-gray-500 hover:text-gray-700 p-1" title="Rename">
                    <i class="fas fa-pen text-sm"></i>
                </button>
                <button onclick="archiveConversation()" class="text-gray-500 hover:text-yellow-600 p-1" title="Archive">
                    <i class="fas fa-archive text-sm"></i>
                </button>
                <button onclick="deleteConversation()" class="text-gray-500 hover:text-red-600 p-1" title="Delete">
                    <i class="fas fa-trash text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Pending Actions Panel -->
        <div id="pendingActionsPanel" class="hidden" style="border-bottom: 1px solid #e5e7eb; background: #fffbeb; padding: 8px 16px;">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-amber-700"><i class="fas fa-clock mr-1"></i>Pending Actions</span>
                <button onclick="refreshPendingActions()" class="text-xs text-amber-600 hover:text-amber-800"><i class="fas fa-sync"></i></button>
            </div>
            <div id="pendingActionsList"></div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <h3 id="confirmTitle" class="text-lg font-semibold text-gray-800 mb-4"></h3>
                    <div id="confirmFields" class="space-y-2 mb-4"></div>
                    <div id="confirmWarnings" class="hidden mb-4"></div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 rounded-b-lg">
                    <button onclick="cancelAction()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 text-sm">Cancel</button>
                    <button onclick="confirmAction()" id="confirmBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">Confirm & Execute</button>
                </div>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="text-center text-gray-400 py-20">
                <i class="fas fa-robot text-4xl mb-4"></i>
                <p class="text-lg font-medium">AI Assistant</p>
                <p class="text-sm mt-1">Select a model and start a conversation</p>
            </div>
        </div>

        <div class="chat-input-area">
            <div class="flex gap-2 sm:gap-3">
                <textarea id="msgInput" rows="1" placeholder="Type your message..."
                          class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 sm:px-4 py-2 text-sm resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          onkeydown="handleKeydown(event)" oninput="autoResize(this)"></textarea>
                <button id="sendBtn" onclick="sendMessage()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrfToken() ?>';
const BASE_URL = '<?= url('') ?>';
let currentConvId = null;
let sending = false;

// ── Mobile Sidebar Toggle ──
function toggleSidebar() {
    const sidebar = document.getElementById('chatSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}

function closeSidebar() {
    const sidebar = document.getElementById('chatSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
}

// ── AI Context Engine ──
const AIContext = {
    _context: { module: null, page: null, route: null, record: null },
    setPageContext(ctx) {
        this._context = { ...this._context, ...ctx };
        this._updateIndicator();
    },
    getContext() { return { ...this._context }; },
    _updateIndicator() {
        const el = document.getElementById('contextIndicator');
        if (!el) return;
        const parts = [];
        if (this._context.module) parts.push(this._context.module);
        if (this._context.page) parts.push(this._context.page);
        if (this._context.record?.reference) parts.push(this._context.record.reference);
        else if (this._context.record?.type) parts.push(this._context.record.type);
        if (parts.length > 0) {
            el.innerHTML = '<i class="fas fa-crosshairs text-green-500 mr-1"></i>' + parts.map(p => escapeHtml(p)).join(' → ');
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }
};
// Auto-detect context from URL
(function() {
    const path = window.location.pathname;
    const match = path.match(/modules\/([a-z_]+)\/([a-z_]+)\/?/);
    if (match) AIContext.setPageContext({ module: match[1], page: match[2], route: path });
})();

// Provider/Model data
const providers = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $providers)) ?>;
const models = <?= json_encode(array_map(fn($m) => ['id' => $m['id'], 'provider_id' => $m['provider_id'], 'name' => $m['display_name']], $allModels)) ?>;

// ── Load conversations ──
async function loadConversations(search = '') {
    const resp = await fetch(`${BASE_URL}/modules/ai/api/conversations.php?action=list&search=${encodeURIComponent(search)}`);
    const data = await resp.json();
    const list = document.getElementById('convList');

    if (!data.success || data.conversations.length === 0) {
        list.innerHTML = '<div class="text-center text-gray-400 text-sm py-4">No conversations</div>';
        return;
    }

    list.innerHTML = data.conversations.map(c => `
        <div class="conv-item ${c.id == currentConvId ? 'active' : ''}" onclick="openConversation(${c.id})">
            <div class="conv-title">${escapeHtml(c.title)}</div>
            <div class="conv-meta">${escapeHtml(c.provider_name)} · ${escapeHtml(c.model_name)} · ${c.message_count} msgs</div>
        </div>
    `).join('');
}

function searchConversations(q) { loadConversations(q); }

// ── New Chat ──
async function newChat() {
    const providerId = document.getElementById('providerSelect').value;
    const modelId = document.getElementById('modelSelect').value;

    if (!providerId || !modelId) { alert('Please select a provider and model.'); return; }

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/conversations.php?action=create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ provider_id: parseInt(providerId), model_id: parseInt(modelId) })
        });
        const data = await resp.json();

        if (data.success) {
            currentConvId = data.id;
            document.getElementById('convActions').classList.remove('hidden');
            document.getElementById('chatMessages').innerHTML = '<div class="text-center text-gray-400 py-10"><p>Send a message to start the conversation</p></div>';
            await loadConversations();
        } else {
            alert(data.errors ? data.errors.join('\n') : (data.error || 'Failed to create conversation'));
        }
    } catch (e) {
        alert('Network error creating conversation.');
    }
}

// ── Open Conversation ──
async function openConversation(id) {
    currentConvId = id;
    closeSidebar(); // Close mobile sidebar
    document.getElementById('convActions').classList.remove('hidden');

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/conversations.php?action=messages&conversation_id=${id}`);
        const data = await resp.json();

        if (!data.success) { alert(data.error || 'Failed to load conversation'); return; }

        // Update provider/model selects
        const conv = data.conversation;
        const provSelect = document.getElementById('providerSelect');
        provSelect.value = conv.provider_id;
        updateModels();
        const modelSelect = document.getElementById('modelSelect');
        modelSelect.value = conv.model_id;

        renderMessages(data.messages);
        await loadConversations();
    } catch (e) {
        alert('Network error loading conversation.');
    }
}

// ── Render Messages ──
function renderMessages(messages) {
    const container = document.getElementById('chatMessages');
    container.innerHTML = messages
        .filter(m => m.role !== 'system')
        .map(m => {
            let html = `<div class="msg-${m.role === 'user' ? 'user' : 'ai'}"><div class="msg-bubble">${escapeHtml(m.content)}`;
            let requestId = null;
            // Render tool activity and navigation cards from stored metadata
            if (m.role === 'assistant' && m.metadata_json) {
                try {
                    const meta = typeof m.metadata_json === 'string' ? JSON.parse(m.metadata_json) : m.metadata_json;
                    if (meta) {
                        requestId = meta.request_id || null;
                        // Tool activity
                        if (meta.tools_used && meta.tools_used.length > 0) {
                            html += `<div style="margin-top:8px;">`;
                            meta.tools_used.forEach(tool => {
                                const isError = tool.status === 'error';
                                const icon = isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
                                const label = isError ? 'Failed' : 'Used';
                                const toolLabel = tool.name.replace(/^(procurement\.|ai\.)/, '').replace(/_/g, ' ');
                                html += `<div class="tool-activity ${isError ? 'error' : ''}">
                                    <i class="${icon}"></i>
                                    <span>${escapeHtml(label)}: ${escapeHtml(toolLabel)}</span>
                                </div>`;
                            });
                            html += `</div>`;
                        }
                        // Navigation cards
                        if (meta.navigation && meta.navigation.length > 0) {
                            html += `<div style="margin-top:10px;">`;
                            meta.navigation.forEach(nav => {
                                html += `<a class="nav-card" href="${escapeHtml(resolveAppUrl(nav.url || ''))}" target="_self">
                                    <div class="nav-card-icon"><i class="${escapeHtml(nav.icon || 'fas fa-arrow-right')}"></i></div>
                                    <div class="nav-card-info">
                                        <div class="nav-card-label">📍 ${escapeHtml(nav.label)}</div>
                                        <div class="nav-card-path">${escapeHtml(nav.module)} → ${escapeHtml(nav.name)}</div>
                                        ${nav.reason ? `<div class="nav-card-reason">${escapeHtml(nav.reason)}</div>` : ''}
                                    </div>
                                    <div class="nav-card-arrow"><i class="fas fa-chevron-right"></i></div>
                                </a>`;
                            });
                            html += `</div>`;
                        }
                        if (requestId) {
                            html += renderFeedbackControls(requestId);
                        }
                    }
                } catch(e) {}
            }
            html += `</div></div>`;
            return html;
        }).join('');

    if (messages.filter(m => m.role !== 'system').length === 0) {
        container.innerHTML = '<div class="text-center text-gray-400 py-10"><p>Send a message to start the conversation</p></div>';
    }

    container.scrollTop = container.scrollHeight;
}

// ── Send Message ──
async function sendMessage() {
    if (sending) return;

    const input = document.getElementById('msgInput');
    const message = input.value.trim();
    if (!message) return;

    const providerId = document.getElementById('providerSelect').value;
    const modelId = document.getElementById('modelSelect').value;

    if (!providerId || !modelId) {
        alert('Please select a provider and model first.');
        return;
    }

    if (!currentConvId) {
        await newChat();
        if (!currentConvId) return;
    }

    sending = true;
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    input.value = '';
    autoResize(input);

    // Add user message to UI
    const container = document.getElementById('chatMessages');
    if (container.querySelector('.text-center.text-gray-400')) container.innerHTML = '';

    container.innerHTML += `<div class="msg-user"><div class="msg-bubble">${escapeHtml(message)}</div></div>`;
    container.innerHTML += `<div class="msg-ai" id="typingIndicator"><div class="msg-bubble"><div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div></div></div>`;
    container.scrollTop = container.scrollHeight;

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/chat.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ 
                conversation_id: currentConvId,
                provider_id: parseInt(providerId),
                model_id: parseInt(modelId),
                message: message, 
                page_context: AIContext.getContext() 
            })
        });
        const data = await resp.json();

        // Remove typing indicator
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();

        if (data.success) {
            let aiHtml = `<div class="msg-ai"><div class="msg-bubble">${escapeHtml(data.message)}`;
            // Render tool activity if present
            if (data.tools_used && data.tools_used.length > 0) {
                aiHtml += `<div style="margin-top:8px;">`;
                data.tools_used.forEach(tool => {
                    const isPending = tool.status === 'pending';
                    const isError = tool.status === 'error';
                    const icon = isPending ? 'fas fa-clock' : isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
                    const label = isPending ? 'Awaiting Confirmation' : isError ? 'Failed' : 'Used';
                    const toolLabel = tool.name.replace(/^(procurement\.|ai\.)/, '').replace(/_/g, ' ');
                    const extraClass = isPending ? 'pending' : isError ? 'error' : '';
                    aiHtml += `<div class="tool-activity ${extraClass}" style="${isPending ? 'background:#fef3c7;border-color:#fcd34d;color:#92400e;' : ''}">
                        <i class="${icon}" style="${isPending ? 'color:#f59e0b;' : ''}"></i>
                        <span>${escapeHtml(label)}: ${escapeHtml(toolLabel)}</span>
                    </div>`;
                });
                aiHtml += `</div>`;
                refreshPendingActions();
            }
            // Render navigation cards if present
            if (data.navigation && data.navigation.length > 0) {
                aiHtml += `<div style="margin-top:10px;">`;
                data.navigation.forEach(nav => {
                    aiHtml += `<a class="nav-card" href="${escapeHtml(resolveAppUrl(nav.url || ''))}" target="_self">
                        <div class="nav-card-icon"><i class="${escapeHtml(nav.icon || 'fas fa-arrow-right')}"></i></div>
                        <div class="nav-card-info">
                            <div class="nav-card-label">📍 ${escapeHtml(nav.label)}</div>
                            <div class="nav-card-path">${escapeHtml(nav.module)} → ${escapeHtml(nav.name)}</div>
                            ${nav.reason ? `<div class="nav-card-reason">${escapeHtml(nav.reason)}</div>` : ''}
                        </div>
                        <div class="nav-card-arrow"><i class="fas fa-chevron-right"></i></div>
                    </a>`;
                });
                aiHtml += `</div>`;
            }
            if (data.request_id) {
                aiHtml += renderFeedbackControls(data.request_id);
            }
            aiHtml += `</div></div>`;
            container.innerHTML += aiHtml;
        } else {
            container.innerHTML += `<div class="msg-ai"><div class="msg-bubble" style="border-color:#fca5a5;color:#dc2626;background:#fef2f2;"><strong>Error:</strong> ${escapeHtml(data.error || 'Failed to get response')}</div></div>`;
        }
    } catch (err) {
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();
        container.innerHTML += `<div class="msg-ai"><div class="msg-bubble" style="border-color:#fca5a5;color:#dc2626;background:#fef2f2;">Network error connecting to AI API. Please try again.</div></div>`;
    }

    container.scrollTop = container.scrollHeight;
    sending = false;
    sendBtn.disabled = false;
    input.focus();
    await loadConversations();
}

function renderFeedbackControls(requestId) {
    return `<div class="feedback-row" data-request-id="${requestId}">
        <span class="text-xs text-gray-400">Response feedback</span>
        <button type="button" class="feedback-btn" onclick="submitAiFeedback(${requestId}, 'helpful')">Helpful</button>
        <button type="button" class="feedback-btn" onclick="submitAiFeedback(${requestId}, 'not_helpful')">Not Helpful</button>
        <button type="button" class="feedback-btn" onclick="submitAiFeedback(${requestId}, 'report_problem')">Report Problem</button>
    </div>`;
}

async function submitAiFeedback(requestId, feedbackType) {
    const row = document.querySelector(`.feedback-row[data-request-id="${requestId}"]`);
    if (!row) return;

    let reasonCode = null;
    let comment = null;
    if (feedbackType !== 'helpful') {
        const reasonInput = prompt('Reason: incorrect, incomplete, unsafe, slow, irrelevant, or other', feedbackType === 'report_problem' ? 'incorrect' : 'incomplete');
        if (reasonInput === null) return;
        const normalizedReason = String(reasonInput).trim().toLowerCase().replace(/\s+/g, '_');
        const allowedReasons = ['incorrect', 'incomplete', 'unsafe', 'slow', 'irrelevant', 'other'];
        reasonCode = allowedReasons.includes(normalizedReason) ? normalizedReason : 'other';
        comment = prompt('Optional note for review queue', '') || null;
    }

    row.querySelectorAll('button').forEach(btn => { btn.disabled = true; });

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/observability.php?action=submit_feedback`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({
                request_id: requestId,
                feedback_type: feedbackType,
                reason_code: reasonCode,
                comment: comment
            })
        });
        const data = await resp.json();
        if (!data.success) {
            row.querySelectorAll('button').forEach(btn => { btn.disabled = false; });
            alert(data.error || 'Failed to submit feedback.');
            return;
        }
        row.innerHTML = '<span class="feedback-status">Feedback recorded</span>';
    } catch (e) {
        row.querySelectorAll('button').forEach(btn => { btn.disabled = false; });
        alert('Network error submitting feedback.');
    }
}

// ── Update Models ──
function updateModels() {
    const providerId = document.getElementById('providerSelect').value;
    const modelSelect = document.getElementById('modelSelect');
    const available = models.filter(m => m.provider_id == providerId);

    if (available.length === 0) {
        modelSelect.innerHTML = '<option value="">No models available for this provider</option>';
        return;
    }

    modelSelect.innerHTML = available
        .map((m, idx) => `<option value="${m.id}" ${idx === 0 ? 'selected' : ''}>${escapeHtml(m.name)}</option>`)
        .join('');
}

function onProviderChange() {
    updateModels();
    currentConvId = null;
    document.getElementById('convActions').classList.add('hidden');
    document.getElementById('chatMessages').innerHTML = '<div class="text-center text-gray-400 py-10"><p>Ready to chat. Type a message below and press Send.</p></div>';
    loadConversations();
}

function onModelChange() {
    // Model changed within provider
}

// ── Conversation Actions ──
async function renameConversation() {
    if (!currentConvId) return;
    const name = prompt('Enter new conversation name:');
    if (!name || name.trim() === '') return;

    await fetch(`${BASE_URL}/modules/ai/api/conversations.php?action=rename`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: currentConvId, title: name.trim() })
    });
    await loadConversations();
}

async function archiveConversation() {
    if (!currentConvId) return;
    if (!confirm('Archive this conversation?')) return;

    await fetch(`${BASE_URL}/modules/ai/api/conversations.php?action=archive`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: currentConvId })
    });
    currentConvId = null;
    document.getElementById('convActions').classList.add('hidden');
    document.getElementById('chatMessages').innerHTML = '<div class="text-center text-gray-400 py-20"><i class="fas fa-robot text-4xl mb-4"></i><p>Select a conversation or start a new one</p></div>';
    await loadConversations();
}

async function deleteConversation() {
    if (!currentConvId) return;
    if (!confirm('Delete this conversation permanently?')) return;

    await fetch(`${BASE_URL}/modules/ai/api/conversations.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: currentConvId })
    });
    currentConvId = null;
    document.getElementById('convActions').classList.add('hidden');
    document.getElementById('chatMessages').innerHTML = '<div class="text-center text-gray-400 py-20"><i class="fas fa-robot text-4xl mb-4"></i><p>Select a conversation or start a new one</p></div>';
    await loadConversations();
}

// ── Actions ──
let currentPendingAction = null;

async function refreshPendingActions() {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=list`);
        const data = await resp.json();
        const panel = document.getElementById('pendingActionsPanel');
        const list = document.getElementById('pendingActionsList');

        if (!data.success || !data.actions || data.actions.length === 0) {
            panel.classList.add('hidden');
            return;
        }

        panel.classList.remove('hidden');
        list.innerHTML = data.actions.map(a => {
            const preview = a.preview || {};
            const fields = (preview.fields || []).slice(0, 3).map(f => `${f.label}: ${f.value}`).join(' | ');
            const isExpired = new Date(a.expires_at) < new Date();
            return `<div class="flex items-center justify-between py-1 text-xs">
                <div class="flex-1 truncate">
                    <span class="font-medium text-amber-800">${escapeHtml(a.name)}</span>
                    <span class="text-amber-600 ml-1">${escapeHtml(fields)}</span>
                </div>
                <div class="flex gap-1 ml-2">
                    ${a.status === 'pending' && !isExpired ? `<button onclick="showConfirmModal(${a.id})" class="px-2 py-0.5 bg-green-500 text-white rounded text-xs hover:bg-green-600">Confirm</button>` : ''}
                    ${a.status === 'pending' ? `<button onclick="cancelPendingAction(${a.id})" class="px-2 py-0.5 bg-red-500 text-white rounded text-xs hover:bg-red-600">Cancel</button>` : ''}
                </div>
            </div>`;
        }).join('');
    } catch (e) { /* silently fail */ }
}

async function showConfirmModal(pendingId) {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=list`);
        const data = await resp.json();
        const action = data.actions?.find(a => a.id === pendingId);
        if (!action) return;

        currentPendingAction = action;
        const preview = action.preview || {};

        document.getElementById('confirmTitle').textContent = preview.title || action.name;
        document.getElementById('confirmFields').innerHTML = (preview.fields || []).map(f =>
            `<div class="flex justify-between text-sm"><span class="text-gray-500">${escapeHtml(f.label)}:</span><span class="font-medium">${escapeHtml(String(f.value))}</span></div>`
        ).join('');

        const warningsDiv = document.getElementById('confirmWarnings');
        if (preview.warnings && preview.warnings.length > 0) {
            warningsDiv.innerHTML = preview.warnings.map(w =>
                `<div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-3 py-2 rounded text-sm"><i class="fas fa-exclamation-triangle mr-1"></i>${escapeHtml(w)}</div>`
            ).join('');
            warningsDiv.classList.remove('hidden');
        } else {
            warningsDiv.classList.add('hidden');
        }

        document.getElementById('confirmModal').classList.remove('hidden');
    } catch (e) { /* silently fail */ }
}

function cancelAction() {
    document.getElementById('confirmModal').classList.add('hidden');
    currentPendingAction = null;
}

async function confirmAction() {
    if (!currentPendingAction) return;
    if (!currentPendingAction.confirmation_token) {
        alert('This pending action is missing a confirmation token. Refresh the list and try again.');
        return;
    }

    const btn = document.getElementById('confirmBtn');
    btn.disabled = true;
    btn.textContent = 'Executing...';

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=confirm`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({
                pending_action_id: currentPendingAction.id,
                token: currentPendingAction.confirmation_token
            })
        });
        const data = await resp.json();

        if (!data.success) {
            alert(data.error || 'Failed to confirm action.');
            return;
        }

        cancelAction();
        await refreshPendingActions();
        alert(data.data ? 'Action completed successfully.' : 'Action executed successfully.');
    } catch (e) {
        alert('Error confirming action.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirm & Execute';
    }
}

async function cancelPendingAction(pendingId) {
    if (!confirm('Cancel this pending action?')) return;

    try {
        await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pending_action_id: pendingId })
        });
        await refreshPendingActions();
    } catch (e) { /* silently fail */ }
}

// ── Helpers ──
function resolveAppUrl(route) {
    const value = (route || '').toString().trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    const base = BASE_URL.replace(/\/+$/, '');
    return `${base}/${value.replace(/^\/+/, '')}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 150) + 'px';
}

// Init
updateModels();
loadConversations();
refreshPendingActions();
</script>

<?php endif; ?>

<?php if ($isEmbed): ?>
</body>
</html>
<?php else: ?>
<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
<?php endif; ?>
