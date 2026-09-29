<?php
/**
 * AI Document Intelligence Center — F20
 */
require_once dirname(__DIR__) . '/bootstrap.php';
$db = getDB();
$companyId = $_SESSION['company_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? 0;
$tab = $_GET['tab'] ?? 'documents';
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    switch ($action) {
        case 'register_document':
            $result = AIDocumentService::register($db, [
                'company_id' => $companyId,
                'file_name' => $_POST['file_name'] ?? '',
                'mime_type' => $_POST['mime_type'] ?? '',
                'file_size' => $_POST['file_size'] ?? 0,
                'file_hash' => $_POST['file_hash'] ?? null,
                'source_module' => $_POST['source_module'] ?? null,
                'related_record_type' => $_POST['related_record_type'] ?? null,
                'related_record_id' => $_POST['related_record_id'] ?? null,
                'document_type' => $_POST['document_type'] ?? 'unknown',
                'created_by' => $userId,
            ]);
            echo json_encode($result);
            exit;

        case 'process_document':
            $result = AIDocumentPipelineService::enqueueFullPipeline($db, $companyId, (int)$_POST['document_id']);
            echo json_encode($result);
            exit;

        case 'classify':
            $result = AIDocumentExtractionService::classify($db, (int)$_POST['document_id'], [
                'document_type' => $_POST['document_type'] ?? 'unknown',
                'confidence' => $_POST['confidence'] ?? 0,
                'reason_summary' => $_POST['reason'] ?? null,
            ]);
            echo json_encode($result);
            exit;

        case 'store_extraction':
            $result = AIDocumentExtractionService::storeExtraction($db, (int)$_POST['document_id'], [
                'extraction_type' => $_POST['extraction_type'] ?? 'general',
                'extracted_data' => json_decode($_POST['extracted_data'] ?? '{}', true),
                'confidence' => $_POST['confidence'] ?? null,
                'provider' => $_POST['provider'] ?? null,
            ]);
            echo json_encode($result);
            exit;

        case 'review_extraction':
            $result = AIDocumentExtractionService::reviewExtraction($db, (int)$_POST['extraction_id'], $userId, $_POST['status'] ?? 'accepted');
            echo json_encode($result);
            exit;

        case 'generate_summary':
            $result = AIDocumentExtractionService::generateSummary($db, (int)$_POST['document_id'], $_POST['summary_type'] ?? 'short');
            echo json_encode($result);
            exit;

        case 'create_comparison':
            $result = AIDocumentComparisonService::create($db, [
                'company_id' => $companyId,
                'title' => $_POST['title'] ?? '',
                'comparison_type' => $_POST['comparison_type'] ?? 'general',
                'document_ids' => json_decode($_POST['document_ids'] ?? '[]', true),
                'created_by' => $userId,
            ]);
            echo json_encode($result);
            exit;

        case 'ask_document':
            $result = AIDocumentIntelligenceService::askDocument(
                $db,
                $companyId,
                $userId,
                (int)($_POST['document_id'] ?? 0),
                $_POST['question'] ?? ''
            );
            echo json_encode($result);
            exit;

        case 'propose_draft':
            $result = AIDocumentIntelligenceService::proposeDraftFromDocument(
                $db,
                $companyId,
                $userId,
                (int)($_POST['document_id'] ?? 0),
                $_POST['action_id'] ?? 'procurement.create_purchase_requisition'
            );
            echo json_encode($result);
            exit;

        case 'suggest_versions':
            $result = AIDocumentIntelligenceService::suggestVersionRelationships($db, $companyId, (int)($_POST['document_id'] ?? 0));
            echo json_encode($result);
            exit;

        case 'search_documents':
            $result = AIDocumentSearchService::search($db, $companyId, [
                'query' => $_POST['query'] ?? '',
                'document_type' => $_POST['document_type'] ?? null,
                'limit' => $_POST['limit'] ?? 20,
            ]);
            $result['success'] = true;
            echo json_encode($result);
            exit;

        case 'create_template':
            $result = AIDocumentTemplateService::create($db, [
                'company_id' => $companyId,
                'name' => $_POST['name'] ?? '',
                'document_type' => $_POST['document_type'] ?? 'unknown',
                'schema_json' => json_decode($_POST['schema_json'] ?? '{}', true),
                'created_by' => $userId,
            ]);
            echo json_encode($result);
            exit;

        case 'archive_document':
            $result = AIDocumentService::archive($db, (int)$_POST['document_id']);
            echo json_encode($result);
            exit;

        case 'get_document':
            $doc = AIDocumentService::getById($db, (int)$_POST['document_id'], $companyId);
            $extractions = AIDocumentExtractionService::getPendingReviews($db, (int)$_POST['document_id']);
            $summaries = AIDocumentExtractionService::getSummaries($db, (int)$_POST['document_id']);
            $relationships = AIDocumentService::getRelationships($db, (int)$_POST['document_id']);
            $qna = AIDocumentIntelligenceService::getSessionHistory($db, (int)$_POST['document_id'], $userId, 10);
            $versions = $db->prepare("SELECT * FROM ai_document_version_suggestions WHERE source_document_id = ? ORDER BY created_at DESC");
            $versions->execute([(int)$_POST['document_id']]);
            $versionSuggestions = $versions->fetchAll(PDO::FETCH_ASSOC);
            $audit = AIDocumentService::getAuditTrail($db, (int)$_POST['document_id']);
            $actions = $db->prepare("SELECT * FROM ai_document_action_requests WHERE document_id = ? ORDER BY created_at DESC");
            $actions->execute([(int)$_POST['document_id']]);
            $actionRequests = $actions->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => (bool)$doc, 'document' => $doc, 'extractions' => $extractions, 'summaries' => $summaries, 'relationships' => $relationships, 'qna' => $qna, 'version_suggestions' => $versionSuggestions, 'action_requests' => $actionRequests, 'audit' => $audit]);
            exit;

        case 'get_stats':
            $stats = AIDocumentService::getStats($db, $companyId);
            $queue = AIDocumentService::getQueueStatus($db, $companyId);
            echo json_encode(['success' => true, 'stats' => $stats, 'queue' => $queue]);
            exit;

        case 'get_queue':
            $queue = AIDocumentPipelineService::getQueueStats($db, $companyId);
            echo json_encode(['success' => true, 'queue' => $queue]);
            exit;

        case 'retry_queue':
            $result = AIDocumentPipelineService::retryTask($db, (int)$_POST['queue_id']);
            echo json_encode($result);
            exit;

        case 'cancel_queue':
            $result = AIDocumentPipelineService::cancelTask($db, (int)$_POST['queue_id']);
            echo json_encode($result);
            exit;

        case 'get_templates':
            $templates = AIDocumentTemplateService::listByCompany($db, $companyId);
            echo json_encode(['success' => true, 'templates' => $templates]);
            exit;

        case 'get_expiring':
            $expiring = AIDocumentSearchService::getExpiring($db, $companyId, (int)($_POST['days'] ?? 90));
            echo json_encode(['success' => true, 'documents' => $expiring]);
            exit;

        case 'list_documents':
            $filter = [];
            if (!empty($_POST['status'])) $filter['status'] = $_POST['status'];
            if (!empty($_POST['document_type'])) $filter['document_type'] = $_POST['document_type'];
            $docs = AIDocumentService::listByCompany($db, $companyId, $filter);
            echo json_encode(['success' => true, 'documents' => $docs]);
            exit;

        case 'list_comparisons':
            $comps = AIDocumentComparisonService::listByCompany($db, $companyId);
            echo json_encode(['success' => true, 'comparisons' => $comps]);
            exit;

        case 'get_document_chunks':
            $docId = (int)($_POST['document_id'] ?? 0);
            $chunks = [];
            if ($docId > 0) {
                $stmt = $db->prepare("SELECT * FROM ai_document_chunks WHERE document_id = ? ORDER BY chunk_index");
                $stmt->execute([$docId]);
                $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success' => true, 'chunks' => $chunks]);
            exit;

        case 'list_templates':
            $tpls = AIDocumentTemplateService::listByCompany($db, $companyId);
            echo json_encode(['success' => true, 'templates' => $tpls]);
            exit;

        case 'get_sensitive_policies':
            $stmt = $db->prepare("SELECT * FROM ai_sensitive_doc_policies WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'policies' => $policies]);
            exit;

        case 'list_queue':
            $qFilter = [];
            if (!empty($_POST['status'])) $qFilter['status'] = $_POST['status'];
            $queue = AIDocumentPipelineService::getQueueStats($db, $companyId);
            echo json_encode(['success' => true, 'queue' => $queue]);
            exit;

        // F27: Match document against ERP records
        case 'match_document':
            $result = AIDocumentMatchingService::matchDocument($db, $companyId, (int)($_POST['document_id'] ?? 0));
            echo json_encode($result);
            exit;

        // F27: Three-way Invoice/PO/Delivery matching
        case 'three_way_match':
            $result = AIDocumentInvoicePOService::performThreeWayMatch(
                $db, $companyId,
                !empty($_POST['invoice_document_id']) ? (int)$_POST['invoice_document_id'] : null,
                !empty($_POST['po_document_id']) ? (int)$_POST['po_document_id'] : null,
                !empty($_POST['delivery_document_id']) ? (int)$_POST['delivery_document_id'] : null,
                !empty($_POST['invoice_record_id']) ? (int)$_POST['invoice_record_id'] : null,
                !empty($_POST['po_record_id']) ? (int)$_POST['po_record_id'] : null,
                !empty($_POST['delivery_record_id']) ? (int)$_POST['delivery_record_id'] : null
            );
            echo json_encode($result);
            exit;

        // F27: Run F26 intelligence analysis on a document
        case 'analyze_for_intelligence':
            $result = AIDocumentF26Integration::analyzeDocument($db, $companyId, (int)($_POST['document_id'] ?? 0));
            echo json_encode($result);
            exit;

        // F27: Create F25 task from document
        case 'create_task_from_document':
            $docId = (int)($_POST['document_id'] ?? 0);
            $doc = AIDocumentService::getById($db, $docId, $companyId);
            if (!$doc) { echo json_encode(['success' => false, 'error' => 'Document not found']); exit; }

            // F24: Check emergency kill switch
            if (class_exists('AIEmergencyControlsService')) {
                if (AIEmergencyControlsService::isControlActive($db, 'disable_autonomous_agents')) {
                    echo json_encode(['success' => false, 'error' => 'Emergency control active: autonomous agents disabled']); exit;
                }
            }

            // F24: Governance check
            if (class_exists('AIDocumentGovernanceService')) {
                $govCheck = AIDocumentGovernanceService::enforce($db, 'create_task_from_document', [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'document_id' => $docId,
                ]);
                if (!$govCheck['allowed']) {
                    echo json_encode(['success' => false, 'error' => $govCheck['reason'] ?? 'Governance denied this action']); exit;
                }
            }

            $request = $_POST['request'] ?? 'Review document: ' . $doc['file_name'];
            $agentDefId = (int)($_POST['agent_def_id'] ?? 1);
            $result = AIAgentRuntimeService::createTask($db, $userId, $companyId, [], $agentDefId, $request, 'document', $docId);
            echo json_encode($result);
            exit;

        // F27: Get document field confidences
        case 'get_field_confidences':
            $extId = (int)($_POST['extraction_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM ai_document_field_confidence WHERE extraction_id = ? ORDER BY confidence ASC");
            $stmt->execute([$extId]);
            echo json_encode(['success' => true, 'fields' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;

        // F27: Get document matches
        case 'get_matches':
            $matches = AIDocumentMatchingService::getMatches($db, (int)($_POST['document_id'] ?? 0));
            echo json_encode(['success' => true, 'matches' => $matches]);
            exit;

        // F27: Get security events for a document
        case 'get_security_events':
            $events = AIDocumentSecurityService::getSecurityEvents($db, (int)($_POST['document_id'] ?? 0));
            echo json_encode(['success' => true, 'events' => $events]);
            exit;

        // F27: Get governance summary
        case 'get_governance_summary':
            $summary = AIDocumentGovernanceService::getGovernanceSummary($db, $companyId);
            echo json_encode(['success' => true, 'summary' => $summary]);
            exit;

        // F27: Enforce retention policies
        case 'enforce_retention':
            $result = AIDocumentSecurityService::enforceRetention($db, $companyId);
            echo json_encode(['success' => true, 'result' => $result]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}

// Load data for the page
$stats = AIDocumentService::getStats($db, $companyId);
$documents = AIDocumentService::listByCompany($db, $companyId, ['limit' => 50]);
$queueStats = AIDocumentPipelineService::getQueueStats($db, $companyId);
$templates = AIDocumentTemplateService::listByCompany($db, $companyId);
$comparisons = AIDocumentComparisonService::listByCompany($db, $companyId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Document Intelligence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-badge { padding: 2px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <?php ai_render_page_styles(); ?>
</head>
<body class="min-h-screen ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.documents'); ?>
    <?php ai_render_page_header('AI Document Intelligence', 'Upload, process, understand, and search business documents', 'fas fa-file-alt'); ?>

    <div class="ai-page-container">
        <!-- Stats Overview -->
        <div class="ai-stat-grid">
            <?php
            $statCards = [
                ['label' => 'Total Documents', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-file'],
                ['label' => 'Ready', 'value' => $stats['ready'] ?? 0, 'icon' => 'fa-check-circle'],
                ['label' => 'Needs Review', 'value' => $stats['needs_review'] ?? 0, 'icon' => 'fa-eye'],
                ['label' => 'Processing', 'value' => ($stats['queued'] ?? 0) + ($stats['processing'] ?? 0), 'icon' => 'fa-cog'],
                ['label' => 'Failed', 'value' => $stats['failed'] ?? 0, 'icon' => 'fa-times-circle'],
                ['label' => 'Sensitive', 'value' => $stats['sensitive'] ?? 0, 'icon' => 'fa-shield-alt'],
            ];
            foreach ($statCards as $sc): ?>
            <div class="ai-stat-card text-center">
                <div class="ai-stat-icon-wrap"><i class="fas <?= $sc['icon'] ?>"></i></div>
                <div class="ai-stat-value"><?= $sc['value'] ?></div>
                <div class="ai-stat-label"><?= $sc['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tabs -->
        <div class="ai-tabs">
            <?php
            $tabs = [
                'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt'],
                'upload' => ['label' => 'Upload', 'icon' => 'fa-upload'],
                'queue' => ['label' => 'Processing Queue', 'icon' => 'fa-cogs'],
                'extractions' => ['label' => 'Extractions', 'icon' => 'fa-magic'],
                'templates' => ['label' => 'Templates', 'icon' => 'fa-layer-group'],
                'comparisons' => ['label' => 'Comparisons', 'icon' => 'fa-columns'],
                'search' => ['label' => 'Search', 'icon' => 'fa-search'],
                'qna' => ['label' => 'Q&A', 'icon' => 'fa-comments'],
                'actions' => ['label' => 'Actions', 'icon' => 'fa-clipboard-check'],
                'relationships' => ['label' => 'Relationships', 'icon' => 'fa-link'],
                'expiring' => ['label' => 'Expiring', 'icon' => 'fa-clock'],
            ];
            foreach ($tabs as $k => $t): ?>
                <a href="?tab=<?= $k ?>" class="ai-tab flex items-center gap-1 whitespace-nowrap <?= $tab === $k ? 'active' : '' ?>">
                    <i class="fas <?= $t['icon'] ?> text-xs"></i> <?= $t['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="fade-in">
        <?php if ($tab === 'documents'): ?>
            <!-- Document List -->
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-file-alt text-sky-400 mr-2"></i>Document Registry</h3>
                    <div class="flex gap-2">
                        <select id="filterType" onchange="filterDocuments()" class="border border-white/10 rounded-lg px-3 py-1.5 text-sm">
                            <option value="">All Types</option>
                            <option value="quotation">Quotation</option>
                            <option value="invoice">Invoice</option>
                            <option value="purchase_order">Purchase Order</option>
                            <option value="contract">Contract</option>
                            <option value="policy">Policy</option>
                            <option value="report">Report</option>
                        </select>
                    </div>
                </div>
                <?php if (empty($documents)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No documents yet. Upload or register a document to get started.</p>
                <?php else: ?>
                    <div class="ai-table-wrapper">
                        <table class="ai-data-table">
                            <thead>
                                <tr><th class="py-2 px-3 text-left">File</th><th class="py-2 px-3 text-left">Type</th><th class="py-2 px-3 text-left">Processing</th><th class="py-2 px-3 text-left">Extraction</th><th class="py-2 px-3 text-left">Created</th><th class="py-2 px-3 text-right">Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr class="hover:bg-slate-800/50">
                                    <td class="py-2 px-3 font-semibold text-slate-100 max-w-[200px] truncate"><?= htmlspecialchars($d['file_name']) ?></td>
                                    <td class="py-2 px-3"><span class="ai-badge ai-badge-info"><?= ucfirst(str_replace('_', ' ', $d['document_type'])) ?></span></td>
                                    <td class="py-2 px-3"><span class="<?= match($d['processing_status']) { 'ready' => 'ai-badge ai-badge-success', 'needs_review' => 'ai-badge ai-badge-warning', 'failed' => 'ai-badge ai-badge-danger', 'processing' => 'ai-badge ai-badge-info', default => 'ai-badge' } ?>"><?= ucfirst($d['processing_status']) ?></span></td>
                                    <td class="py-2 px-3"><span class="<?= match($d['extraction_status']) { 'reviewed' => 'ai-badge ai-badge-success', 'validated' => 'ai-badge ai-badge-info', 'needs_review' => 'ai-badge ai-badge-warning', default => 'ai-badge' } ?>"><?= ucfirst($d['extraction_status']) ?></span></td>
                                    <td class="py-2 px-3 text-slate-500 text-xs"><?= date('M j', strtotime($d['created_at'])) ?></td>
                                    <td class="py-2 px-3 text-right">
                                        <button onclick="processDocument(<?= $d['document_id'] ?>)" class="text-xs mr-2" style="color:var(--ai-accent)" title="Process"><i class="fas fa-cog"></i></button>
                                        <button onclick="viewDocument(<?= $d['document_id'] ?>)" class="text-xs" style="color:var(--ai-accent)" title="View"><i class="fas fa-eye"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'upload'): ?>
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-upload text-sky-400 mr-2"></i>Register Document</h3>
                <form id="uploadForm" onsubmit="return registerDocument(event)">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="ai-form-label">File Name <span class="text-red-600">*</span></labe><input type="text" name="file_name" required class="ai-form-input"></div>
                        <div><label class="ai-form-label">Document Type</label>
                            <select name="document_type" class="ai-form-select">
                                <option value="unknown">Unknown</option><option value="quotation">Quotation</option><option value="invoice">Invoice</option>
                                <option value="purchase_order">Purchase Order</option><option value="contract">Contract</option><option value="policy">Policy</option>
                                <option value="procedure">Procedure</option><option value="report">Report</option><option value="supplier_document">Supplier Document</option>
                                <option value="identity_document">Identity Document</option><option value="other">Other</option>
                            </select></div>
                        <div><label class="ai-form-label">MIME Type</label><input type="text" name="mime_type" placeholder="application/pdf" class="ai-form-input"></div>
                        <div><label class="ai-form-label">Source Module</label><input type="text" name="source_module" placeholder="procurement" class="ai-form-input"></div>
                        <div><label class="ai-form-label">Related Record Type</label><input type="text" name="related_record_type" placeholder="purchase_requisition" class="ai-form-input"></div>
                        <div><label class="ai-form-label">Related Record ID</label><input type="number" name="related_record_id" class="ai-form-input"></div>
                        <div><label class="ai-form-label">File Hash (SHA-256)</label><input type="text" name="file_hash" class="ai-form-input"></div>
                    </div>
                    <button type="submit" class="mt-4 bg-sky-500 hover:bg-sky-400 text-white px-6 py-2 rounded-lg text-sm font-semibold transition-colors"><i class="fas fa-plus mr-1"></i>Register Document</button>
                </form>
            </div>

        <?php elseif ($tab === 'queue'): ?>
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-cogs text-purple-400 mr-2"></i>Processing Queue</h3>
                <?php if (empty($queueStats)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No tasks in queue.</p>
                <?php else: ?>
                    <div class="ai-table-wrapper">
                        <table class="ai-data-table">
                            <thead>
                                <tr><th class="py-2 px-3 text-left">Task Type</th><th class="py-2 px-3 text-left">Status</th><th class="py-2 px-3 text-left">Count</th><th class="py-2 px-3 text-left">Oldest</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($queueStats as $q): ?>
                                <tr><td class="py-2 px-3 font-semibold"><?= ucfirst(str_replace('_', ' ', $q['task_type'])) ?></td>
                                    <td class="py-2 px-3"><span class="<?= match($q['status']) { 'completed' => 'ai-badge ai-badge-success', 'failed' => 'ai-badge ai-badge-danger', 'processing' => 'ai-badge ai-badge-info', default => 'ai-badge' } ?>"><?= ucfirst($q['status']) ?></span></td>
                                    <td class="py-2 px-3"><?= $q['count'] ?></td>
                                    <td class="py-2 px-3 text-xs text-slate-500"><?= $q['oldest'] ? date('M j g:i A', strtotime($q['oldest'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'extractions'): ?>
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-magic text-indigo-400 mr-2"></i>Extraction Results</h3>
                <p class="text-sm text-slate-400">Extraction results are displayed per document. Click "View" on a document to see its extractions.</p>
            </div>

        <?php elseif ($tab === 'templates'): ?>
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-layer-group text-green-400 mr-2"></i>Extraction Templates</h3>
                    <button onclick="showTemplateModal()" class="bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors border border-emerald-500/20"><i class="fas fa-plus mr-1"></i>New Template</button>
                </div>
                <?php if (empty($templates)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No templates yet. Create one to standardize extraction.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($templates as $t): ?>
                        <div class="p-4 rounded-lg border border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-slate-100 text-sm"><?= htmlspecialchars($t['name']) ?></h4>
                                <span class="<?= $t['status'] === 'published' ? 'ai-badge ai-badge-success' : 'ai-badge' ?>"><?= ucfirst($t['status']) ?></span>
                            </div>
                            <p class="text-xs text-slate-400">Type: <?= ucfirst(str_replace('_', ' ', $t['document_type'])) ?> · Version <?= $t['version'] ?></p>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'comparisons'): ?>
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-columns text-orange-400 mr-2"></i>Document Comparisons</h3>
                    <button onclick="showComparisonModal()" class="bg-orange-500/10 text-orange-400 hover:bg-orange-500/20 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors border border-orange-500/20"><i class="fas fa-plus mr-1"></i>New Comparison</button>
                </div>
                <?php if (empty($comparisons)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No comparisons yet. Select documents to compare.</p>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach ($comparisons as $c): ?>
                        <div class="p-4 rounded-lg border border-white/10">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-slate-100 text-sm"><?= htmlspecialchars($c['title']) ?></h4>
                                    <p class="text-xs text-slate-400"><?= ucfirst($c['comparison_type']) ?> · <?= count($c['document_ids'] ?? []) ?> documents</p>
                                </div>
                                <span class="<?= $c['status'] === 'completed' ? 'ai-badge ai-badge-success' : 'ai-badge' ?>"><?= ucfirst($c['status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'search'): ?>
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-search text-sky-400 mr-2"></i>Document Search</h3>
                <form id="searchForm" onsubmit="return searchDocuments(event)">
                    <div class="flex gap-2 mb-4">
                        <input type="text" id="searchQuery" placeholder="Search documents..." class="ai-form-input flex-1">
                        <select id="searchType" class="ai-form-select">
                            <option value="">All Types</option><option value="quotation">Quotation</option><option value="invoice">Invoice</option>
                            <option value="purchase_order">Purchase Order</option><option value="contract">Contract</option>
                        </select>
                        <button type="submit" class="bg-sky-500 hover:bg-sky-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <div id="searchResults"></div>
            </div>

        <?php elseif ($tab === 'qna'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-comments text-sky-400 mr-2"></i>Ask This Document</h3>
                    <form id="qnaForm" onsubmit="return askDocument(event)">
                        <label class="ai-form-label">Document</label>
                        <select id="qnaDocumentId" class="ai-form-select mb-3" required>
                            <option value="">Select a document</option>
                            <?php foreach ($documents as $doc): ?>
                                <option value="<?= (int)$doc['document_id'] ?>"><?= htmlspecialchars($doc['file_name']) ?> (<?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <label class="ai-form-label">Question</label>
                        <textarea id="qnaQuestion" rows="4" class="ai-form-textarea" placeholder="What are the payment terms?" required></textarea>
                        <button type="submit" class="mt-3 bg-sky-500 hover:bg-sky-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors"><i class="fas fa-paper-plane mr-1"></i>Ask</button>
                    </form>
                </div>
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-receipt text-indigo-400 mr-2"></i>Answer & Evidence</h3>
                    <div id="qnaAnswer" class="text-sm text-slate-200 whitespace-pre-wrap">Select a document and ask a question.</div>
                    <div id="qnaEvidence" class="mt-4 space-y-2"></div>
                </div>
            </div>

        <?php elseif ($tab === 'actions'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-clipboard-check text-green-400 mr-2"></i>Propose ERP Draft</h3>
                    <form id="actionForm" onsubmit="return proposeDraft(event)">
                        <label class="ai-form-label">Document</label>
                        <select id="actionDocumentId" class="ai-form-select mb-3" required>
                            <option value="">Select a document</option>
                            <?php foreach ($documents as $doc): ?>
                                <option value="<?= (int)$doc['document_id'] ?>"><?= htmlspecialchars($doc['file_name']) ?> (<?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <label class="ai-form-label">Action</label>
                        <select id="actionId" class="ai-form-select mb-3">
                            <option value="procurement.create_purchase_requisition">Create Purchase Requisition</option>
                        </select>
                        <button type="submit" class="bg-emerald-500 hover:bg-emerald-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors"><i class="fas fa-wand-magic-sparkles mr-1"></i>Propose Draft</button>
                    </form>
                </div>
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-clock text-amber-400 mr-2"></i>Pending Action Preview</h3>
                    <div id="actionPreview" class="text-sm text-slate-200 whitespace-pre-wrap">No draft action proposed yet.</div>
                    <div id="actionToken" class="mt-3 text-xs text-slate-400"></div>
                </div>
            </div>

        <?php elseif ($tab === 'relationships'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-link text-orange-400 mr-2"></i>Version Suggestions</h3>
                    <p class="text-sm text-slate-400 mb-3">Run automatic version detection on a selected document.</p>
                    <button class="bg-orange-500 hover:bg-orange-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors" onclick="suggestVersions()"><i class="fas fa-sitemap mr-1"></i>Suggest Versions</button>
                    <div id="versionSuggestions" class="mt-4 space-y-2"></div>
                </div>
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-list text-sky-400 mr-2"></i>Document Links</h3>
                    <p class="text-sm text-slate-400">Open a document, then use the document details view to review relationships and audit history.</p>
                </div>
            </div>

        <?php elseif ($tab === 'expiring'): ?>
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-clock text-amber-400 mr-2"></i>Expiring Documents</h3>
                <p class="text-sm text-slate-400">Documents with expiry dates (contracts, agreements) will appear here.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <script>
    const BASE = '/modules/ai/documents/index.php';

    async function api(action, data = {}) {
        const fd = new FormData();
        fd.append('action', action);
        for (const [k, v] of Object.entries(data)) fd.append(k, v);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        return resp.json();
    }

    async function registerDocument(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'register_document');
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) { alert('Document registered! ID: ' + r.document_id); location.reload(); }
        else alert(r.error || 'Failed');
    }

    async function processDocument(docId) {
        const r = await api('process_document', { document_id: docId });
        if (r.success) { alert('Queued for processing!'); location.reload(); }
        else alert(r.error || 'Failed');
    }

    async function viewDocument(docId) {
        const r = await api('get_document', { document_id: docId });
        if (r.success) {
            const d = r.document;
            let info = `File: ${d.file_name}\nType: ${d.document_type}\nProcessing: ${d.processing_status}\nExtraction: ${d.extraction_status}`;
            if (d.extracted_text) info += `\n\nExtracted Text:\n${d.extracted_text.substring(0, 500)}`;
            if (r.extractions.length) info += `\n\nExtractions: ${r.extractions.length}`;
            if (r.summaries.length) info += `\nSummaries: ${r.summaries.length}`;
            alert(info);
        }
    }

    async function searchDocuments(e) {
        e.preventDefault();
        const r = await api('search_documents', { query: document.getElementById('searchQuery').value, document_type: document.getElementById('searchType').value });
        const div = document.getElementById('searchResults');
        if (r.results && r.results.length) {
            div.innerHTML = r.results.map(d => `<div class="p-3 border border-white/10 rounded-lg mb-2 ai-content-card"><span class="font-semibold">${d.file_name}</span> <span class="ai-badge ai-badge-info">${d.document_type}</span> <span class="text-xs text-slate-500">${d.processing_status}</span></div>`).join('');
        } else {
            div.innerHTML = '<p class="text-slate-500 text-sm">No results found.</p>';
        }
    }

    async function askDocument(e) {
        e.preventDefault();
        const documentId = document.getElementById('qnaDocumentId').value;
        const question = document.getElementById('qnaQuestion').value.trim();
        if (!documentId || !question) return;

        const r = await api('ask_document', { document_id: documentId, question });
        const answerDiv = document.getElementById('qnaAnswer');
        const evidenceDiv = document.getElementById('qnaEvidence');

        if (r.success) {
            answerDiv.textContent = r.answer || 'No answer returned.';
            evidenceDiv.innerHTML = (r.evidence || []).map(ev => `
                <div class="p-3 rounded-lg border border-white/10 bg-slate-800/50 ai-content-card">
                    <div class="text-xs text-slate-400 mb-1">Page ${ev.page_number ?? '-'} · Chunk ${ev.chunk_index ?? '-'}</div>
                    <div class="text-sm text-slate-200">${escapeHtml(ev.snippet || '')}</div>
                </div>
            `).join('') || '<p class="text-sm text-slate-500">No evidence captured.</p>';
        } else {
            answerDiv.textContent = r.error || 'Failed to answer the question.';
            evidenceDiv.innerHTML = '';
        }
    }

    async function proposeDraft(e) {
        e.preventDefault();
        const documentId = document.getElementById('actionDocumentId').value;
        const actionId = document.getElementById('actionId').value;
        if (!documentId || !actionId) return;

        const r = await api('propose_draft', { document_id: documentId, action_id: actionId });
        const previewDiv = document.getElementById('actionPreview');
        const tokenDiv = document.getElementById('actionToken');

        if (r.success && r.proposal) {
            const preview = r.proposal.preview || {};
            previewDiv.innerHTML = `
                <div class="font-semibold text-slate-100 mb-2">${escapeHtml(preview.title || 'Pending Action')}</div>
                <div class="space-y-1">${(preview.fields || []).map(f => `<div><span class="text-slate-400">${escapeHtml(f.label)}:</span> ${escapeHtml(String(f.value ?? ''))}</div>`).join('')}</div>
                ${preview.warnings && preview.warnings.length ? `<div class="mt-3 p-3 rounded-lg ai-alert ai-alert-warning">${preview.warnings.map(escapeHtml).join('<br>')}</div>` : ''}
            `;
            tokenDiv.textContent = r.proposal.requires_confirmation ? 'Confirmation token is available in the pending action flow.' : '';
        } else {
            previewDiv.textContent = r.error || 'Failed to propose draft.';
            tokenDiv.textContent = '';
        }
    }

    async function suggestVersions() {
        const documentId = document.getElementById('qnaDocumentId')?.value || document.getElementById('actionDocumentId')?.value || '';
        if (!documentId) {
            alert('Select a document first.');
            return;
        }
        const r = await api('suggest_versions', { document_id: documentId });
        const div = document.getElementById('versionSuggestions');
        if (r.success) {
            div.innerHTML = (r.suggestions || []).map(s => `
                <div class="p-3 rounded-lg border border-white/10 bg-slate-800/50 ai-content-card">
                    <div class="font-semibold text-slate-100">${escapeHtml(s.file_name || 'Document')}</div>
                    <div class="text-xs text-slate-400">Confidence: ${(Number(s.confidence) * 100).toFixed(0)}%</div>
                </div>
            `).join('') || '<p class="text-sm text-slate-500">No version matches found.</p>';
        } else {
            div.innerHTML = `<p class="text-sm text-red-400">${escapeHtml(r.error || 'Failed')}</p>`;
        }
    }

    function filterDocuments() { location.href = '?tab=documents&type=' + document.getElementById('filterType').value; }
    function showTemplateModal() { alert('Template creation modal — coming soon'); }
    function showComparisonModal() { alert('Comparison creation modal — coming soon'); }
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>
