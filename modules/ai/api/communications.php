<?php
/**
 * AI F32 — Communications API
 * 
 * API endpoints for the AI Communications & Business Correspondence Assistant.
 */

// Bootstrap
$aiModuleDir = dirname(__DIR__);
require_once dirname($aiModuleDir) . '/ai/bootstrap.php';

header('Content-Type: application/json');

// Get current user
$userId = $_SESSION['user_id'] ?? 0;
$companyId = $_SESSION['company_id'] ?? 0;

if (!$userId || !$companyId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Check permissions
if (function_exists('hasPermission') && !hasPermission('ai.assistant.access', $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ============================================================
    // DRAFT OPERATIONS
    // ============================================================

    case 'create_draft':
        $params = [
            'context_type'       => $_POST['context_type'] ?? 'manual',
            'context_id'         => intval($_POST['context_id'] ?? 0) ?: null,
            'communication_type' => $_POST['communication_type'] ?? 'email',
            'tone'               => $_POST['tone'] ?? 'professional',
            'formality'          => $_POST['formality'] ?? 'standard',
            'language'           => $_POST['language'] ?? 'en',
            'length'             => $_POST['length'] ?? 'standard',
            'subject'            => $_POST['subject'] ?? null,
            'recipient'          => $_POST['recipient'] ?? null,
            'instructions'       => $_POST['instructions'] ?? '',
        ];

        $draftService = new AICommunicationDraftService($companyId, $userId);
        $result = $draftService->createDraft($params);
        echo json_encode($result);
        break;

    case 'regenerate_draft':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $newParams = [
            'tone'         => $_POST['tone'] ?? null,
            'formality'    => $_POST['formality'] ?? null,
            'language'     => $_POST['language'] ?? null,
            'instructions' => $_POST['instructions'] ?? null,
        ];
        $newParams = array_filter($newParams);

        $draftService = new AICommunicationDraftService($companyId, $userId);
        $result = $draftService->regenerateDraft($draftId, $newParams);
        echo json_encode($result);
        break;

    case 'apply_suggestion':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $text = $_POST['suggestion_text'] ?? '';

        $draftService = new AICommunicationDraftService($companyId, $userId);
        $result = $draftService->applySuggestion($draftId, $text);
        echo json_encode($result);
        break;

    case 'update_draft':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $db = getDB();
        if ($db) {
            $sql = "UPDATE ai_communication_drafts SET subject = ?, body = ?, status = 'editing', updated_at = NOW() WHERE id = ? AND company_id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $subject = $_POST['subject'] ?? '';
                $body = $_POST['body'] ?? '';
                $stmt->bind_param('ssii', $subject, $body, $draftId, $companyId);
                $stmt->execute();
                echo json_encode(['success' => true, 'draft_id' => $draftId]);
                $stmt->close();
            }
        }
        break;

    case 'list_drafts':
        $filters = [
            'status'            => $_GET['status'] ?? null,
            'context_type'      => $_GET['context_type'] ?? null,
            'communication_type'=> $_GET['communication_type'] ?? null,
            'limit'             => intval($_GET['limit'] ?? 50),
            'offset'            => intval($_GET['offset'] ?? 0),
        ];
        $filters = array_filter($filters);

        $draftService = new AICommunicationDraftService($companyId, $userId);
        $result = $draftService->listDrafts($filters);
        echo json_encode(['drafts' => $result, 'count' => count($result)]);
        break;

    case 'get_draft':
        $draftId = intval($_GET['draft_id'] ?? 0);
        $draftService = new AICommunicationDraftService($companyId, $userId);
        $result = $draftService->getDraft($draftId);
        echo json_encode($result ?: ['error' => 'Draft not found']);
        break;

    case 'delete_draft':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $db = getDB();
        if ($db) {
            $stmt = $db->prepare("UPDATE ai_communication_drafts SET deleted_at = NOW() WHERE id = ? AND company_id = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $draftId, $companyId);
                $stmt->execute();
                echo json_encode(['success' => true]);
                $stmt->close();
            }
        }
        break;

    // ============================================================
    // SUMMARIZATION
    // ============================================================

    case 'summarize':
        $contextType = $_POST['context_type'] ?? 'customer';
        $contextId = intval($_POST['context_id'] ?? 0);
        $messageCount = intval($_POST['message_count'] ?? 20);

        $summaryService = new AICommunicationSummarizationService($companyId, $userId);
        $result = $summaryService->summarizeHistory($contextType, $contextId, $messageCount);
        echo json_encode($result);
        break;

    case 'extract_action_items':
        $text = $_POST['text'] ?? '';

        $summaryService = new AICommunicationSummarizationService($companyId, $userId);
        $result = $summaryService->extractActionItems($text);
        echo json_encode(['action_items' => $result]);
        break;

    case 'get_timeline':
        $contextType = $_GET['context_type'] ?? 'customer';
        $contextId = intval($_GET['context_id'] ?? 0);
        $limit = intval($_GET['limit'] ?? 50);

        $summaryService = new AICommunicationSummarizationService($companyId, $userId);
        $result = $summaryService->buildTimeline($contextType, $contextId, $limit);
        echo json_encode(['timeline' => $result]);
        break;

    // ============================================================
    // CLASSIFICATION
    // ============================================================

    case 'classify':
        $text = $_POST['text'] ?? '';
        $contextType = $_POST['context_type'] ?? null;
        $contextId = intval($_POST['context_id'] ?? 0) ?: null;

        $classService = new AICommunicationClassificationService($companyId, $userId);
        $result = $classService->classify($text, $contextType, $contextId);
        echo json_encode($result);
        break;

    case 'correct_classification':
        $classId = intval($_POST['classification_id'] ?? 0);
        $newClass = $_POST['classification'] ?? '';
        $reason = $_POST['reason'] ?? null;

        $classService = new AICommunicationClassificationService($companyId, $userId);
        $result = $classService->correctClassification($classId, $newClass, $reason);
        echo json_encode($result);
        break;

    case 'detect_followups':
        $classService = new AICommunicationClassificationService($companyId, $userId);
        $result = $classService->detectFollowUps();
        echo json_encode(['followups' => $result, 'count' => count($result)]);
        break;

    case 'update_followup':
        $followupId = intval($_POST['followup_id'] ?? 0);
        $action = $_POST['followup_action'] ?? '';
        $options = [];
        if (!empty($_POST['snooze_until'])) {
            $options['snooze_until'] = $_POST['snooze_until'];
        }

        $classService = new AICommunicationClassificationService($companyId, $userId);
        $result = $classService->updateFollowUp($followupId, $action, $options);
        echo json_encode($result);
        break;

    // ============================================================
    // TRANSLATION
    // ============================================================

    case 'translate':
        $text = $_POST['text'] ?? '';
        $targetLang = $_POST['target_language'] ?? 'en';
        $sourceLang = $_POST['source_language'] ?? 'en';

        $transService = new AICommunicationTranslationService($companyId, $userId);
        $result = $transService->translate($text, $targetLang, ['source_language' => $sourceLang]);
        echo json_encode($result);
        break;

    case 'get_languages':
        $transService = new AICommunicationTranslationService($companyId, $userId);
        $result = $transService->getSupportedLanguages();
        echo json_encode(['languages' => $result]);
        break;

    // ============================================================
    // SAFETY / SENDING
    // ============================================================

    case 'validate_sending':
        $draft = [
            'recipient'      => $_POST['recipient'] ?? '',
            'recipient_type' => $_POST['recipient_type'] ?? '',
            'subject'        => $_POST['subject'] ?? '',
            'body'           => $_POST['body'] ?? '',
            'communication_type' => $_POST['communication_type'] ?? 'email',
            'context_id'     => intval($_POST['context_id'] ?? 0),
        ];

        $safetyService = new AICommunicationSafetyService($companyId, $userId);
        $result = $safetyService->validateBeforeSending($draft);
        echo json_encode($result);
        break;

    case 'create_send_confirmation':
        $draftId = intval($_POST['draft_id'] ?? 0);

        $draftService = new AICommunicationDraftService($companyId, $userId);
        $draft = $draftService->getDraft($draftId);

        if (!$draft) {
            echo json_encode(['error' => 'Draft not found']);
            break;
        }

        $safetyService = new AICommunicationSafetyService($companyId, $userId);
        $result = $safetyService->createSendConfirmation($draftId, $draft);
        echo json_encode($result);
        break;

    case 'confirm_send':
        $token = $_POST['confirmation_token'] ?? '';
        $confirmParams = json_decode($_POST['confirm_params'] ?? '{}', true);

        $safetyService = new AICommunicationSafetyService($companyId, $userId);
        $result = $safetyService->confirmAndSend($token, $confirmParams);
        echo json_encode($result);
        break;

    // ============================================================
    // TEMPLATES
    // ============================================================

    case 'list_templates':
        $filters = [
            'category'          => $_GET['category'] ?? null,
            'status'            => $_GET['status'] ?? null,
            'communication_type'=> $_GET['communication_type'] ?? null,
        ];
        $filters = array_filter($filters);

        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->listTemplates($filters);
        echo json_encode(['templates' => $result]);
        break;

    case 'get_template':
        $templateId = intval($_GET['template_id'] ?? 0);
        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->getTemplate($templateId);
        echo json_encode($result ?: ['error' => 'Template not found']);
        break;

    case 'create_template':
        $params = [
            'name'               => $_POST['name'] ?? '',
            'description'        => $_POST['description'] ?? '',
            'body'               => $_POST['body'] ?? '',
            'subject'            => $_POST['subject'] ?? '',
            'communication_type' => $_POST['communication_type'] ?? 'email',
            'category'           => $_POST['category'] ?? 'custom',
            'tone'               => $_POST['tone'] ?? 'professional',
            'language'           => $_POST['language'] ?? 'en',
        ];

        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->createTemplate($params);
        echo json_encode($result);
        break;

    case 'generate_template':
        $params = [
            'communication_type' => $_POST['communication_type'] ?? 'email',
            'category'           => $_POST['category'] ?? 'custom',
            'tone'               => $_POST['tone'] ?? 'professional',
            'description'        => $_POST['description'] ?? '',
        ];

        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->generateTemplate($params);
        echo json_encode($result);
        break;

    case 'resolve_template':
        $templateId = intval($_POST['template_id'] ?? 0);
        $contextType = $_POST['context_type'] ?? 'customer';
        $contextId = intval($_POST['context_id'] ?? 0);

        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->resolveTemplate($templateId, $contextType, $contextId);
        echo json_encode($result);
        break;

    case 'update_template':
        $templateId = intval($_POST['template_id'] ?? 0);
        $updates = [];
        foreach (['name', 'description', 'subject_template', 'body_template', 'tone', 'language', 'category', 'status'] as $field) {
            if (isset($_POST[$field])) {
                $updates[$field] = $_POST[$field];
            }
        }

        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->updateTemplate($templateId, $updates);
        echo json_encode($result);
        break;

    case 'activate_template':
        $templateId = intval($_POST['template_id'] ?? 0);
        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->activateTemplate($templateId);
        echo json_encode($result);
        break;

    case 'rollback_template':
        $templateId = intval($_POST['template_id'] ?? 0);
        $version = intval($_POST['version'] ?? 1);

        $tplService = new AICommunicationTemplateService($companyId, $userId);
        $result = $tplService->rollbackTemplate($templateId, $version);
        echo json_encode($result);
        break;

    // ============================================================
    // F25 INTEGRATION
    // ============================================================

    case 'create_communication_task':
        $params = [
            'task_type' => $_POST['task_type'] ?? 'draft_followups',
            'criteria'  => json_decode($_POST['criteria'] ?? '{}', true),
            'priority'  => $_POST['priority'] ?? 'normal',
        ];

        $f25 = new AICommunicationF25Integration($companyId, $userId);
        $result = $f25->createCommunicationTask($params);
        echo json_encode($result);
        break;

    // ============================================================
    // F26 INTEGRATION
    // ============================================================

    case 'detect_communication_events':
        $f26 = new AICommunicationF26Integration($companyId, $userId);
        $result = $f26->detectCommunicationEvents();
        echo json_encode(['events' => $result, 'count' => count($result)]);
        break;

    case 'generate_suggested_communication':
        $eventId = intval($_POST['event_id'] ?? 0);
        $eventData = json_decode($_POST['event_data'] ?? '{}', true);

        $f26 = new AICommunicationF26Integration($companyId, $userId);
        $result = $f26->generateSuggestedCommunication($eventId, $eventData);
        echo json_encode($result);
        break;

    // ============================================================
    // F27 INTEGRATION
    // ============================================================

    case 'draft_from_document':
        $docId = intval($_POST['document_id'] ?? 0);
        $instructions = $_POST['instructions'] ?? '';
        $options = [
            'communication_type' => $_POST['communication_type'] ?? 'email',
            'tone'               => $_POST['tone'] ?? 'professional',
            'document_type'      => $_POST['document_type'] ?? 'document',
        ];

        $f27 = new AICommunicationF27Integration($companyId, $userId);
        $result = $f27->draftFromDocument($docId, $instructions, $options);
        echo json_encode($result);
        break;

    case 'explain_discrepancy':
        $docId1 = intval($_POST['document_id_1'] ?? 0);
        $docId2 = intval($_POST['document_id_2'] ?? 0);

        $f27 = new AICommunicationF27Integration($companyId, $userId);
        $result = $f27->explainDocumentDiscrepancy($docId1, $docId2);
        echo json_encode($result);
        break;

    // ============================================================
    // SUGGESTIONS
    // ============================================================

    case 'get_suggestions':
        $body = $_POST['body'] ?? '';
        $tone = $_POST['tone'] ?? 'professional';
        $contextType = $_POST['context_type'] ?? null;
        $contextId = intval($_POST['context_id'] ?? 0) ?: null;

        $draftService = new AICommunicationDraftService($companyId, $userId);
        $result = $draftService->generateSuggestions($body, $tone, [
            'context_type' => $contextType,
            'context_id'   => $contextId,
        ]);
        echo json_encode(['suggestions' => $result]);
        break;

    // ============================================================
    // FEEDBACK
    // ============================================================

    case 'submit_feedback':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $feedbackType = $_POST['feedback_type'] ?? 'thumbs_up';
        $comment = $_POST['comment'] ?? '';

        $db = getDB();
        if ($db) {
            $sql = "INSERT INTO ai_communication_events (company_id, event_type, entity_type, entity_id, user_id, metadata)
                    VALUES (?, 'feedback', 'draft', ?, ?, ?)";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $meta = json_encode(['feedback_type' => $feedbackType, 'comment' => $comment]);
                $stmt->bind_param('iisi', $companyId, $draftId, $userId, $meta);
                $stmt->execute();
                $stmt->close();
            }
        }
        echo json_encode(['success' => true]);
        break;

    // ============================================================
    // OBSERVABILITY
    // ============================================================

    case 'get_events':
        $db = getDB();
        if ($db) {
            $limit = min(intval($_GET['limit'] ?? 50), 200);
            $eventType = $_GET['event_type'] ?? null;

            $sql = "SELECT * FROM ai_communication_events WHERE company_id = ?";
            $params = [$companyId];
            $types = 'i';

            if ($eventType) {
                $sql .= " AND event_type = ?";
                $params[] = $eventType;
                $types .= 's';
            }

            $sql .= " ORDER BY created_at DESC LIMIT {$limit}";

            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $events = [];
                while ($row = $result->fetch_assoc()) {
                    $events[] = $row;
                }
                $stmt->close();
                echo json_encode(['events' => $events]);
            }
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        break;
}
