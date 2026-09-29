<?php
/**
 * AI F33 — Knowledge Center (Enhanced)
 *
 * Enterprise Knowledge & RAG Intelligence with 12 tabs.
 */
$pageTitle = 'AI Knowledge Center';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_text_extractor.php';
require_once AI_MODULE_DIR . '/includes/ai_embedding_provider.php';
require_once AI_MODULE_DIR . '/includes/ai_vector_store.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeVersionService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeQAService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeFreshnessService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeReviewService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeGapService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeF33Integration.php';

if (!hasPermission('ai.knowledge.view')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];
$collectionId = (int) ($_GET['collection_id'] ?? 0);
$currentCollection = $collectionId ? AIKnowledgeService::getCollectionById($db, $collectionId) : null;
$sources = $currentCollection ? AIKnowledgeService::getSourcesByCollection($db, $collectionId) : [];
$collections = AIKnowledgeService::getCollections($db, $userId);
$accessList = ($currentCollection && $currentCollection['visibility'] === 'restricted') ? AIKnowledgeService::getAccessList($db, $collectionId) : [];

$freshService = new AIKnowledgeFreshnessService($db, $userId);
$freshnessData = $collectionId ? $freshService->getCollectionFreshness($collectionId) : null;

$reviewService = new AIKnowledgeReviewService($db, $userId);
$reviewStats = $reviewService->getReviewStats();
$pendingReviews = $reviewService->getPendingReviews();
$lifecycle = $reviewService->getLifecycleStatus();

$apiBase = '/modules/ai/api/knowledge_f33.php';
$baseApi = '/modules/ai/api/knowledge.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Knowledge Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php require_once AI_MODULE_DIR . '/includes/ai_ui.php'; ?>
    <?php ai_render_shell_styles(); ?>
    <?php ai_render_page_styles(); ?>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        .panel{display:none}
        .panel.active{display:block}
        .list-item{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--ai-border);transition:background .15s}
        .list-item:hover{background:var(--ai-input-bg)}
        .list-item:last-child{border-bottom:none}
        .list-item .info{flex:1}
        .list-item .title{font-size:14px;font-weight:500;color:var(--ai-text-primary)}
        .list-item .meta{font-size:12px;color:var(--ai-text-secondary);margin-top:3px}
        .list-item .actions{display:flex;gap:6px}
        .empty-state{text-align:center;padding:40px;color:var(--ai-text-muted)}
        .empty-state i{font-size:48px;margin-bottom:12px;display:block}
        .result-box{white-space:pre-wrap;font-size:14px;line-height:1.5;max-height:600px;overflow-y:auto;background:var(--ai-input-bg);border:1px solid var(--ai-border);border-radius:8px;padding:14px}
        .citation{display:inline-block;background:var(--ai-input-bg);border:1px solid var(--ai-border);border-radius:4px;padding:3px 8px;font-size:11px;margin:2px;color:var(--ai-text-secondary)}
        .citation.active{border-color:var(--ai-accent);color:var(--ai-accent)}
        .source-ref{font-size:12px;color:var(--ai-text-secondary);margin-top:8px}
        .source-ref a{color:var(--ai-accent);text-decoration:none}
        .quality-bar{height:8px;background:var(--ai-input-bg);border-radius:4px;overflow:hidden;margin-top:6px}
        .quality-fill{height:100%;border-radius:4px;transition:width .3s}
        .version-timeline{position:relative;padding-left:20px}
        .version-timeline::before{content:'';position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--ai-border)}
        .version-item{position:relative;padding:8px 0 8px 16px;font-size:13px}
        .version-item::before{content:'';position:absolute;left:-14px;top:12px;width:10px;height:10px;border-radius:50%;background:var(--ai-accent);border:2px solid var(--ai-card-bg)}
        .version-item.current::before{background:var(--ai-success)}
        .ai-draft-banner{background:linear-gradient(135deg,#1a1a3a,#2a1a3a);border:1px solid #4a2a6a;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#c084fc}
        .ai-stat-card.accent .ai-stat-value{color:var(--ai-accent)}
        .ai-stat-card.green .ai-stat-value{color:var(--ai-success)}
        .ai-stat-card.yellow .ai-stat-value{color:var(--ai-warning)}
        .ai-stat-card.red .ai-stat-value{color:var(--ai-danger)}
        @media(max-width:768px){
            .version-timeline{padding-left:16px}
            .version-item{padding-left:12px}
            .version-item::before{left:-10px;width:8px;height:8px}
        }
    </style>
</head>
<body class="ai-module-page">
<div class="ai-page-container">
    <?php ai_render_page_header('AI Knowledge Center', 'Enterprise Knowledge & RAG Intelligence with 12 tabs.', 'fas fa-book'); ?>
    <div class="flex justify-end mb-4">
        <button onclick="location.reload()" class="px-3 py-1.5 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-sm transition-colors">
            <i class="fas fa-sync mr-1"></i>Refresh
        </button>
    </div>
    <?php ai_render_module_nav('ai.knowledge'); ?>
    <div class="ai-tabs">
        <button class="ai-tab active" onclick="showPanel('home')">Home</button>
        <button class="ai-tab" onclick="showPanel('ask')">Ask Knowledge</button>
        <button class="ai-tab" onclick="showPanel('search')">Search</button>
        <button class="ai-tab" onclick="showPanel('collections')">Collections</button>
        <button class="ai-tab" onclick="showPanel('versions')">Versions</button>
        <button class="ai-tab" onclick="showPanel('reviews')">Reviews</button>
        <button class="ai-tab" onclick="showPanel('freshness')">Freshness</button>
        <button class="ai-tab" onclick="showPanel('quality')">Quality</button>
        <button class="ai-tab" onclick="showPanel('gaps')">Gaps</button>
        <button class="ai-tab" onclick="showPanel('drafts')">AI Drafts</button>
        <button class="ai-tab" onclick="showPanel('history')">History</button>
    </div>

    <!-- HOME -->
    <div id="panel-home" class="panel active">
        <div class="ai-grid ai-grid-cols-4 mb-6">
            <div class="ai-stat-card accent">
                <div class="ai-stat-icon-wrap"><i class="fas fa-folder"></i></div>
                <div class="ai-stat-value"><?= count($collections) ?></div>
                <div class="ai-stat-label">Collections</div>
            </div>
            <div class="ai-stat-card green">
                <div class="ai-stat-icon-wrap"><i class="fas fa-check-circle"></i></div>
                <div class="ai-stat-value"><?= $lifecycle['published'] ?? 0 ?></div>
                <div class="ai-stat-label">Published</div>
            </div>
            <div class="ai-stat-card yellow">
                <div class="ai-stat-icon-wrap"><i class="fas fa-clock"></i></div>
                <div class="ai-stat-value"><?= $reviewStats['pending'] ?? 0 ?></div>
                <div class="ai-stat-label">Pending Reviews</div>
            </div>
            <div class="ai-stat-card red">
                <div class="ai-stat-icon-wrap"><i class="fas fa-exclamation-circle"></i></div>
                <div class="ai-stat-value"><?= $freshnessData['expired'] ?? 0 ?></div>
                <div class="ai-stat-label">Expired</div>
            </div>
        </div>
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-search" style="color:var(--ai-accent)"></i> Quick Ask</h3>
            <div class="flex gap-2">
                <input type="text" id="home-ask-input" class="ai-form-input" placeholder="Ask a knowledge question... e.g., What is our purchasing approval policy?" onkeydown="if(event.key==='Enter')homeAsk()">
                <button onclick="homeAsk()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                    <i class="fas fa-paper-plane mr-1"></i>Ask
                </button>
            </div>
            <div id="home-ask-result" class="hidden mt-3"></div>
        </div>
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-book" style="color:var(--ai-accent)"></i> Collections</h3>
            <div class="ai-grid ai-grid-cols-3">
                <?php foreach (array_slice($collections, 0, 9) as $col): ?>
                <a href="?collection_id=<?= $col['id'] ?>" class="ai-content-card block hover:border-sky-500/30 transition-colors">
                    <div class="font-semibold mb-1"><?= htmlspecialchars($col['name']) ?></div>
                    <div class="text-xs text-slate-300"><?= htmlspecialchars(substr($col['description'] ?? '', 0, 80)) ?></div>
                    <div class="text-xs text-slate-400 mt-1">
                        <span class="ai-badge <?= $col['visibility'] === 'company' ? 'ai-badge-success' : ($col['visibility'] === 'restricted' ? 'ai-badge-danger' : 'ai-badge-purple') ?>"><?= ucfirst($col['visibility']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ASK KNOWLEDGE -->
    <div id="panel-ask" class="panel">
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-question-circle" style="color:var(--ai-accent)"></i> Ask Knowledge</h3>
            <div class="ai-form-group">
                <label class="ai-form-label">Your Question</label>
                <textarea id="ask-question" class="ai-form-textarea" rows="3" placeholder="e.g., How do we handle customer refunds?&#10;What is the procedure for onboarding a new supplier?"></textarea>
            </div>
            <div class="ai-grid ai-grid-cols-3">
                <div class="ai-form-group">
                    <label class="ai-form-label">Collection (optional)</label>
                    <select id="ask-collection" class="ai-form-select">
                        <option value="">All collections</option>
                        <?php foreach ($collections as $col): ?>
                        <option value="<?= $col['id'] ?>"><?= htmlspecialchars($col['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ai-form-group">
                    <label class="ai-form-label">Historical Date (optional)</label>
                    <input type="date" id="ask-date" class="ai-form-input">
                </div>
                <div class="ai-form-group flex items-end">
                    <button onclick="doAsk()" class="w-full px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                        <i class="fas fa-paper-plane mr-1"></i>Ask
                    </button>
                </div>
            </div>
            <div id="ask-result" class="hidden"></div>
        </div>
    </div>

    <!-- SEARCH -->
    <div id="panel-search" class="panel">
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-search" style="color:var(--ai-accent)"></i> Knowledge Search</h3>
            <div class="flex gap-2">
                <input type="text" id="search-query" class="ai-form-input" placeholder="Search knowledge..." onkeydown="if(event.key==='Enter')doSearch()">
                <button onclick="doSearch()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                    <i class="fas fa-search mr-1"></i>Search
                </button>
            </div>
            <div id="search-results" class="mt-3"></div>
        </div>
    </div>

    <!-- COLLECTIONS -->
    <div id="panel-collections" class="panel">
        <div class="ai-content-card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="ai-section-title mb-0" style="border-bottom:none;padding-bottom:0"><i class="fas fa-folder" style="color:var(--ai-accent)"></i> Collections</h3>
                <button onclick="showCreateCollection()" class="px-3 py-1.5 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                    <i class="fas fa-plus mr-1"></i>New Collection
                </button>
            </div>
            <div class="ai-grid ai-grid-cols-3">
                <?php foreach ($collections as $col): ?>
                <div class="ai-content-card">
                    <div class="font-semibold mb-1"><?= htmlspecialchars($col['name']) ?></div>
                    <div class="text-xs text-slate-300 mb-2"><?= htmlspecialchars($col['description'] ?? '') ?></div>
                    <div class="flex gap-1.5 flex-wrap">
                        <span class="ai-badge <?= $col['visibility'] === 'company' ? 'ai-badge-success' : ($col['visibility'] === 'restricted' ? 'ai-badge-danger' : 'ai-badge-purple') ?>"><?= ucfirst($col['visibility']) ?></span>
                        <?php if ($col['module']): ?><span class="ai-badge"><?= htmlspecialchars($col['module']) ?></span><?php endif; ?>
                    </div>
                    <div class="mt-2 flex gap-1.5">
                        <a href="?collection_id=<?= $col['id'] ?>" class="px-2 py-1 border border-slate-600 rounded text-slate-300 hover:bg-slate-700 text-xs transition-colors">
                            <i class="fas fa-eye mr-1"></i>View
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- VERSIONS -->
    <div id="panel-versions" class="panel">
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-code-branch" style="color:var(--ai-accent)"></i> Version History</h3>
            <div class="ai-form-group">
                <label class="ai-form-label">Source ID</label>
                <div class="flex gap-2">
                    <input type="number" id="version-source-id" class="ai-form-input" placeholder="Source ID">
                    <button onclick="loadVersions()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                        <i class="fas fa-search mr-1"></i>Load
                    </button>
                </div>
            </div>
            <div id="versions-list"></div>
        </div>
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-exclamation-triangle" style="color:var(--ai-warning)"></i> Lifecycle Status</h3>
            <div class="ai-grid ai-grid-cols-3" id="lifecycle-stats">
                <?php foreach ($lifecycle as $status => $count): ?>
                <div class="ai-stat-card">
                    <div class="ai-stat-value"><?= $count ?></div>
                    <div class="ai-stat-label"><?= ucfirst($status) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- REVIEWS -->
    <div id="panel-reviews" class="panel">
        <div class="ai-content-card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="ai-section-title mb-0" style="border-bottom:none;padding-bottom:0"><i class="fas fa-clipboard-check" style="color:var(--ai-accent)"></i> Review Queue</h3>
                <span class="ai-badge ai-badge-warning"><?= count($pendingReviews) ?> pending</span>
            </div>
            <div id="reviews-list">
                <?php if (empty($pendingReviews)): ?>
                <div class="ai-content-card text-center text-slate-400">
                    <i class="fas fa-check-circle text-3xl text-slate-500 mb-3 block"></i>
                    No pending reviews
                </div>
                <?php else: ?>
                <?php foreach ($pendingReviews as $review): ?>
                <?php
                    $sevMap = ['urgent' => 'ai-badge-danger', 'high' => 'ai-badge-danger', 'normal' => 'ai-badge-success', 'pending' => 'ai-badge-warning', 'medium' => 'ai-badge-warning', 'low' => 'ai-badge-success'];
                    $sevClass = $sevMap[$review['severity']] ?? 'ai-badge';
                ?>
                <div class="ai-content-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-semibold text-sm text-slate-100"><?= htmlspecialchars($review['title']) ?></span>
                                <span class="ai-badge <?= $sevClass ?>"><?= htmlspecialchars($review['severity']) ?></span>
                            </div>
                            <div class="text-sm text-slate-300">
                                <?= htmlspecialchars($review['review_type']) ?> · <?= htmlspecialchars($review['source_title'] ?? 'N/A') ?> · <?= htmlspecialchars($review['created_at']) ?>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="resolveReview(<?= $review['id'] ?>, 'approved')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs transition-colors">
                                <i class="fas fa-check mr-1"></i>Approve
                            </button>
                            <button onclick="resolveReview(<?= $review['id'] ?>, 'rejected')" class="px-3 py-1.5 bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 rounded-lg text-xs transition-colors">
                                <i class="fas fa-times mr-1"></i>Reject
                            </button>
                            <button onclick="dismissReview(<?= $review['id'] ?>)" class="px-3 py-1.5 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-xs transition-colors">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- FRESHNESS -->
    <div id="panel-freshness" class="panel">
        <div class="ai-content-card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="ai-section-title mb-0" style="border-bottom:none;padding-bottom:0"><i class="fas fa-clock" style="color:var(--ai-warning)"></i> Freshness Check</h3>
                <button onclick="checkFreshness()" class="px-3 py-1.5 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                    <i class="fas fa-sync mr-1"></i>Run Check
                </button>
            </div>
            <div id="freshness-results">
                <?php if ($freshnessData): ?>
                <div class="ai-grid ai-grid-cols-4 mb-4">
                    <div class="ai-stat-card green">
                        <div class="ai-stat-icon-wrap"><i class="fas fa-leaf"></i></div>
                        <div class="ai-stat-value"><?= $freshnessData['fresh'] ?? 0 ?></div>
                        <div class="ai-stat-label">Fresh</div>
                    </div>
                    <div class="ai-stat-card yellow">
                        <div class="ai-stat-icon-wrap"><i class="fas fa-clock"></i></div>
                        <div class="ai-stat-value"><?= $freshnessData['expiring'] ?? 0 ?></div>
                        <div class="ai-stat-label">Expiring</div>
                    </div>
                    <div class="ai-stat-card red">
                        <div class="ai-stat-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="ai-stat-value"><?= $freshnessData['stale'] ?? 0 ?></div>
                        <div class="ai-stat-label">Stale</div>
                    </div>
                    <div class="ai-stat-card red">
                        <div class="ai-stat-icon-wrap"><i class="fas fa-times-circle"></i></div>
                        <div class="ai-stat-value"><?= $freshnessData['expired'] ?? 0 ?></div>
                        <div class="ai-stat-label">Expired</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QUALITY -->
    <div id="panel-quality" class="panel">
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-star" style="color:var(--ai-accent)"></i> Quality Assessment</h3>
            <div class="ai-form-group">
                <label class="ai-form-label">Source ID</label>
                <div class="flex gap-2">
                    <input type="number" id="quality-source-id" class="ai-form-input" placeholder="Source ID">
                    <button onclick="checkQuality()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                        <i class="fas fa-chart-bar mr-1"></i>Assess
                    </button>
                </div>
            </div>
            <div id="quality-result"></div>
        </div>
    </div>

    <!-- GAPS -->
    <div id="panel-gaps" class="panel">
        <div class="ai-content-card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="ai-section-title mb-0" style="border-bottom:none;padding-bottom:0"><i class="fas fa-exclamation-circle" style="color:var(--ai-warning)"></i> Knowledge Gaps</h3>
                <button onclick="detectGaps()" class="px-3 py-1.5 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                    <i class="fas fa-search mr-1"></i>Detect Gaps
                </button>
            </div>
            <div id="gaps-list">
                <div class="ai-content-card text-center text-slate-400">
                    <i class="fas fa-puzzle-piece text-3xl text-slate-500 mb-3 block"></i>
                    Click "Detect Gaps" to find knowledge gaps
                </div>
            </div>
        </div>
    </div>

    <!-- AI DRAFTS -->
    <div id="panel-drafts" class="panel">
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-magic" style="color:var(--ai-accent)"></i> AI-Generated Knowledge Drafts</h3>
            <div class="ai-draft-banner"><i class="fas fa-robot mr-1"></i> AI drafts are NOT authoritative. Human approval is required before publication.</div>
            <div class="ai-grid ai-grid-cols-2">
                <div>
                    <div class="ai-form-group">
                        <label class="ai-form-label">Topic</label>
                        <input type="text" id="draft-topic" class="ai-form-input" placeholder="e.g., Supplier onboarding procedure">
                    </div>
                    <div class="ai-form-group">
                        <label class="ai-form-label">Type</label>
                        <select id="draft-type" class="ai-form-select">
                            <option value="faq">FAQ</option>
                            <option value="sop_outline">SOP Outline</option>
                            <option value="article">Knowledge Article</option>
                            <option value="procedure_summary">Procedure Summary</option>
                            <option value="training_material">Training Material</option>
                        </select>
                    </div>
                    <button onclick="generateDraft()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                        <i class="fas fa-magic mr-1"></i>Generate Draft
                    </button>
                </div>
                <div id="draft-result"></div>
            </div>
        </div>
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-file-alt" style="color:var(--ai-accent)"></i> Existing Drafts</h3>
            <div id="drafts-list">
                <div class="ai-content-card text-center text-slate-400">Loading...</div>
            </div>
        </div>
    </div>

    <!-- HISTORY -->
    <div id="panel-history" class="panel">
        <div class="ai-content-card">
            <h3 class="ai-section-title"><i class="fas fa-history" style="color:var(--ai-accent)"></i> Knowledge Events</h3>
            <div id="events-list">
                <div class="ai-content-card text-center text-slate-400">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
const F33_API = '<?= $apiBase ?>';

function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ai-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + name)?.classList.add('active');
    event.target.classList.add('active');
    if (name === 'drafts') loadDrafts();
    if (name === 'history') loadEvents();
}

function homeAsk() {
    const q = document.getElementById('home-ask-input').value.trim();
    if (!q) return;
    const fd = new FormData();
    fd.append('action', 'ask');
    fd.append('query', q);
    fetch(F33_API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('home-ask-result');
        box.classList.remove('hidden');
        box.innerHTML = renderAnswer(d);
    });
}

function doAsk() {
    const q = document.getElementById('ask-question').value.trim();
    if (!q) return;
    const fd = new FormData();
    fd.append('action', 'ask');
    fd.append('query', q);
    fd.append('collection_id', document.getElementById('ask-collection').value);
    fd.append('historical_date', document.getElementById('ask-date').value);
    fetch(F33_API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        document.getElementById('ask-result').classList.remove('hidden');
        document.getElementById('ask-result').innerHTML = renderAnswer(d);
    });
}

function renderAnswer(d) {
    if (d.error) return `<div class="ai-alert ai-alert-danger"><i class="fas fa-exclamation-circle mr-1"></i>${esc(d.error)}</div>`;
    let html = '';
    if (d.no_answer) {
        html += `<div class="ai-alert ai-alert-warning"><i class="fas fa-info-circle mr-1"></i> ${esc(d.no_answer)}</div>`;
    }
    if (d.answer) {
        html += `<div class="result-box">${esc(d.answer)}</div>`;
    }
    html += `<div class="mt-2 text-xs text-slate-400">Confidence: <strong>${d.confidence_label || 'Unknown'}</strong> (${((d.confidence||0)*100).toFixed(0)}%) · ${d.results_count||0} sources · ${d.latency_ms||0}ms</div>`;
    if (d.conflicts && d.conflicts.length) {
        html += `<div class="ai-alert ai-alert-warning mt-2"><i class="fas fa-exclamation-triangle mr-1"></i> Conflicting sources detected: ${d.conflicts.map(c=>esc(c.title)).join(', ')}</div>`;
    }
    if (d.citations && d.citations.length) {
        html += '<div class="mt-2"><strong>Sources:</strong></div>';
        html += d.citations.map(c => `<span class="citation ${c.used_in_answer?'active':''}">${esc(c.source_title)}${c.version ? ' v'+c.version : ''}${c.effective_date ? ' ('+c.effective_date+')' : ''}</span>`).join(' ');
    }
    if (d.sources && d.sources.length) {
        html += '<div class="source-ref">' + d.sources.map(s => `<a href="/modules/ai/knowledge/index.php?collection_id=${s.collection_id}">${esc(s.title)}</a>`).join(' · ') + '</div>';
    }
    if (d.query_id) {
        html += `<div class="mt-3 flex gap-2">
            <button class="px-3 py-1.5 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-xs transition-colors" onclick="submitFeedback(${d.query_id},'helpful')"><i class="fas fa-thumbs-up mr-1"></i>Helpful</button>
            <button class="px-3 py-1.5 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-xs transition-colors" onclick="submitFeedback(${d.query_id},'not_helpful')"><i class="fas fa-thumbs-down mr-1"></i>Not helpful</button>
        </div>`;
    }
    return html;
}

function doSearch() {
    const q = document.getElementById('search-query').value.trim();
    if (!q) return;
    fetch(F33_API + '?action=search&q=' + encodeURIComponent(q)).then(r=>r.json()).then(d => {
        const box = document.getElementById('search-results');
        if (!d.results || !d.results.length) { box.innerHTML = '<div class="ai-content-card text-center text-slate-400">No results found</div>'; return; }
        box.innerHTML = d.results.map(r => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(r.source_title || 'Unknown')}</div>
                    <div class="meta">${esc(r.collection_name || '')} · Chunk #${r.chunk_index || 0} ${r.relevance ? '· Relevance: ' + (r.relevance*100).toFixed(0)+'%' : ''}</div>
                </div>
            </div>
        `).join('');
    });
}

function loadVersions() {
    const sourceId = document.getElementById('version-source-id').value;
    if (!sourceId) return;
    fetch(F33_API + '?action=get_versions&source_id=' + sourceId).then(r=>r.json()).then(d => {
        const box = document.getElementById('versions-list');
        if (!d.versions || !d.versions.length) { box.innerHTML = '<div class="ai-content-card text-center text-slate-400">No versions found</div>'; return; }
        box.innerHTML = '<div class="version-timeline">' + d.versions.map(v => {
            const statusBadge = v.approval_status === 'published' ? 'ai-badge-success' : (v.approval_status === 'draft' ? 'ai-badge-purple' : 'ai-badge-warning');
            return `<div class="version-item ${v.approval_status === 'published' ? 'current' : ''}">
                <strong>v${v.version_number}</strong> — <span class="ai-badge ${statusBadge}">${esc(v.approval_status)}</span>
                ${v.effective_date ? ' · Effective: ' + v.effective_date : ''}
                ${v.expiration_date ? ' · Expires: ' + v.expiration_date : ''}
                ${v.change_summary ? '<div class="text-xs text-slate-400 mt-0.5">' + esc(v.change_summary) + '</div>' : ''}
                <div class="text-xs text-slate-500">by ${esc(v.created_by_name || 'Unknown')} · ${v.created_at}</div>
            </div>`;
        }).join('') + '</div>';
    });
}

function checkFreshness() {
    fetch(F33_API + '?action=check_freshness').then(r=>r.json()).then(d => {
        const box = document.getElementById('freshness-results');
        if (!d.alerts || !d.alerts.length) { box.innerHTML = '<div class="ai-alert ai-alert-success"><i class="fas fa-check mr-1"></i> All knowledge is fresh</div>'; return; }
        const sevMap = {urgent:'ai-badge-danger',high:'ai-badge-danger',normal:'ai-badge-success',pending:'ai-badge-warning',medium:'ai-badge-warning',low:'ai-badge-success'};
        box.innerHTML = d.alerts.map(a => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(a.title)}</div>
                    <div class="meta"><span class="ai-badge ${sevMap[a.severity] || 'ai-badge'}">${esc(a.severity)}</span> ${esc(a.description || '')}</div>
                </div>
            </div>
        `).join('');
    });
}

function checkQuality() {
    const sourceId = document.getElementById('quality-source-id').value;
    if (!sourceId) return;
    fetch(F33_API + '?action=get_quality&source_id=' + sourceId).then(r=>r.json()).then(d => {
        const box = document.getElementById('quality-result');
        if (d.error) { box.innerHTML = `<div class="ai-alert ai-alert-danger">${esc(d.error)}</div>`; return; }
        const color = d.percentage >= 80 ? 'var(--ai-success)' : d.percentage >= 60 ? 'var(--ai-warning)' : 'var(--ai-danger)';
        box.innerHTML = `
            <div class="ai-stat-card mb-3"><div class="ai-stat-value" style="color:${color}">${d.grade || '?'}</div><div class="ai-stat-label">Quality Grade (${d.percentage || 0}%)</div></div>
            <div class="quality-bar"><div class="quality-fill" style="width:${d.percentage||0}%;background:${color}"></div></div>
            <div class="mt-3">${(d.factors||[]).map(f => `
                <div class="flex justify-between py-1 text-sm" style="border-bottom:1px solid var(--ai-border)">
                    <span>${esc(f.name)}</span><span>${f.score}/${f.max} <span class="text-slate-500">(${esc(f.detail)})</span></span>
                </div>
            `).join('')}</div>
        `;
    });
}

function detectGaps() {
    fetch(F33_API + '?action=detect_gaps').then(r=>r.json()).then(d => {
        const box = document.getElementById('gaps-list');
        if (!d.gaps || !d.gaps.length) { box.innerHTML = '<div class="ai-content-card text-center text-slate-400"><i class="fas fa-check-circle text-3xl text-slate-500 mb-3 block"></i>No knowledge gaps detected</div>'; return; }
        box.innerHTML = d.gaps.map(g => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(g.topic || '')}</div>
                    <div class="meta">Asked ${g.frequency || 1} times · ${esc(g.description || '')}</div>
                    ${g.recommendation ? '<div class="text-xs mt-1" style="color:var(--ai-accent)">' + esc(g.recommendation) + '</div>' : ''}
                </div>
            </div>
        `).join('');
    });
}

function generateDraft() {
    const topic = document.getElementById('draft-topic').value.trim();
    if (!topic) return;
    const fd = new FormData();
    fd.append('action', 'generate_draft');
    fd.append('topic', topic);
    fd.append('draft_type', document.getElementById('draft-type').value);
    fetch(F33_API, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        const box = document.getElementById('draft-result');
        if (d.error) { box.innerHTML = `<div class="ai-alert ai-alert-danger">${esc(d.error)}</div>`; return; }
        box.innerHTML = `
            <div class="ai-alert ai-alert-warning"><i class="fas fa-robot mr-1"></i> AI Draft — Not Authoritative</div>
            <div class="result-box">${esc(d.content || '')}</div>
            <div class="mt-2 flex gap-2">
                <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs transition-colors" onclick="approveDraft(${d.draft_id})"><i class="fas fa-check mr-1"></i>Approve</button>
                <button class="px-3 py-1.5 bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 rounded-lg text-xs transition-colors" onclick="rejectDraft(${d.draft_id})"><i class="fas fa-times mr-1"></i>Reject</button>
            </div>
        `;
        loadDrafts();
    });
}

function loadDrafts() {
    fetch(F33_API + '?action=list_drafts').then(r=>r.json()).then(d => {
        const box = document.getElementById('drafts-list');
        if (!d.drafts || !d.drafts.length) { box.innerHTML = '<div class="ai-content-card text-center text-slate-400">No drafts</div>'; return; }
        box.innerHTML = d.drafts.map(dr => {
            const statusBadge = dr.status === 'draft' ? 'ai-badge-purple' : (dr.status === 'approved' ? 'ai-badge-success' : 'ai-badge');
            return `<div class="list-item">
                <div class="info">
                    <div class="title">${esc(dr.title)}</div>
                    <div class="meta"><span class="ai-badge ${statusBadge}">${esc(dr.status)}</span> ${esc(dr.draft_type)} · ${dr.is_authoritative ? '✅ Authoritative' : '⚠️ Not authoritative'} · ${esc(dr.created_at)}</div>
                </div>
                <div class="actions">
                    ${dr.status === 'draft' ? `<button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs transition-colors" onclick="approveDraft(${dr.id})">Approve</button>` : ''}
                    ${dr.status === 'approved' ? `<button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-xs transition-colors" onclick="publishDraft(${dr.id})">Publish</button>` : ''}
                </div>
            </div>`;
        }).join('');
    });
}

function approveDraft(id) {
    const fd = new FormData(); fd.append('action','approve_draft'); fd.append('draft_id',id);
    fetch(F33_API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success)toast('Approved');loadDrafts(); });
}
function rejectDraft(id) {
    const fd = new FormData(); fd.append('action','reject_draft'); fd.append('draft_id',id);
    fetch(F33_API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success)toast('Rejected');loadDrafts(); });
}
function publishDraft(id) {
    const fd = new FormData(); fd.append('action','publish_draft'); fd.append('draft_id',id);
    fetch(F33_API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success)toast('Published');loadDrafts(); });
}

function resolveReview(id, resolution) {
    const fd = new FormData(); fd.append('action','resolve_review'); fd.append('review_id',id); fd.append('resolution',resolution);
    fetch(F33_API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success){toast('Resolved');location.reload();} });
}
function dismissReview(id) {
    const fd = new FormData(); fd.append('action','dismiss_review'); fd.append('review_id',id);
    fetch(F33_API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success){toast('Dismissed');location.reload();} });
}

function submitFeedback(queryId, rating) {
    const fd = new FormData(); fd.append('action','feedback'); fd.append('query_id',queryId); fd.append('rating',rating);
    fetch(F33_API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success)toast('Thank you for your feedback'); });
}

function loadEvents() {
    fetch(F33_API + '?action=get_events&limit=50').then(r=>r.json()).then(d => {
        const box = document.getElementById('events-list');
        if (!d.events || !d.events.length) { box.innerHTML = '<div class="ai-content-card text-center text-slate-400">No events</div>'; return; }
        box.innerHTML = d.events.map(e => `
            <div class="list-item">
                <div class="info">
                    <div class="title">${esc(e.event_type)} — ${esc(e.entity_type||'')} #${e.entity_id||''}</div>
                    <div class="meta">${e.user_id ? 'User #'+e.user_id : ''} · ${e.tokens_used||0} tokens · ${e.latency_ms||0}ms · ${esc(e.created_at)}</div>
                </div>
            </div>
        `).join('');
    });
}

function showCreateCollection() { alert('Use the original Knowledge UI to create collections'); }

function esc(s) { if(!s)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toast(msg) { const t=document.createElement('div');t.style.cssText='position:fixed;bottom:20px;right:20px;background:var(--ai-card-bg);border:1px solid var(--ai-border);border-radius:8px;padding:12px 20px;z-index:10000;color:var(--ai-text-primary)';t.textContent=msg;document.body.appendChild(t);setTimeout(()=>t.remove(),3000); }
</script>

<?php ai_render_chat_launcher(); ?>
</body>
</html>
