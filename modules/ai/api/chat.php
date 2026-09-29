<?php
/**
 * AI Chat API Endpoint.
 *
 * Handles sending messages, tool calls, and receiving AI responses.
 * Implements the full AI F8 tool-call lifecycle.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/tools/procurement_tools.php';
require_once AI_MODULE_DIR . '/includes/ai_action_base.php';
require_once AI_MODULE_DIR . '/tools/procurement_actions.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($token) && !verifyCsrf($token)) { echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success' => false, 'error' => 'Invalid request.']); exit; }

$conversationId = (int) ($input['conversation_id'] ?? 0);
$message = trim($input['message'] ?? '');

if (!$conversationId) { echo json_encode(['success' => false, 'error' => 'Conversation ID required.']); exit; }
if ($message === '') { echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']); exit; }
if (strlen($message) > 10000) { echo json_encode(['success' => false, 'error' => 'Message too long (max 10,000 characters).']); exit; }

$db = getDB();
$userId = $_SESSION['user_id'];
$requestStartedAt = microtime(true);
$observabilityRequest = ['id' => 0, 'request_key' => '', 'trace_id' => ''];
$requestFinished = false;

// Validate conversation ownership
$conv = AIConversationService::getConversationById($db, $conversationId, $userId);
if (!$conv) { echo json_encode(['success' => false, 'error' => 'Conversation not found.']); exit; }
if ($conv['status'] !== 'active') { echo json_encode(['success' => false, 'error' => 'Conversation is archived.']); exit; }

// Sync conversation provider/model if passed from UI
$reqProviderId = (int) ($input['provider_id'] ?? 0);
$reqModelId = (int) ($input['model_id'] ?? 0);
if ($reqProviderId && $reqModelId && ($reqProviderId !== (int)$conv['provider_id'] || $reqModelId !== (int)$conv['model_id'])) {
    $db->prepare("UPDATE ai_conversations SET provider_id = ?, model_id = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$reqProviderId, $reqModelId, $conversationId]);
    $conv['provider_id'] = $reqProviderId;
    $conv['model_id'] = $reqModelId;
}

// Validate provider and model
$provider = AIProviderService::getProviderById($db, $conv['provider_id']);
if (!$provider || !$provider['is_active']) {
    echo json_encode(['success' => false, 'error' => 'Selected provider is no longer active or available.']); exit;
}
$model = AIModelService::getModelById($db, $conv['model_id']);
if (!$model || !$model['is_active']) {
    echo json_encode(['success' => false, 'error' => 'Selected model is no longer active or available.']); exit;
}

$messageClassification = class_exists('AIDataClassificationService')
    ? AIDataClassificationService::classifyContent($message, ['module' => 'chat'])
    : ['classification' => 'internal', 'masking_required' => false, 'confidence' => 0.0];

$observabilityRequest = class_exists('AIObservabilityService')
    ? AIObservabilityService::beginRequest($db, [
        'company_id' => (int) ($_SESSION['company_id'] ?? 1),
        'user_id' => $userId,
        'conversation_id' => $conversationId,
        'session_reference' => 'chat:' . $conversationId,
        'entry_point' => 'assistant',
        'provider_id' => $conv['provider_id'],
        'model_id' => $conv['model_id'],
        'feature' => 'ai_chat',
        'prompt_summary' => $message,
        'metadata' => ['page_context' => $input['page_context'] ?? []],
    ])
    : $observabilityRequest;

if (class_exists('AIObservabilityService')) {
    AIObservabilityService::setRequestContext([
        'request_id' => (int) ($observabilityRequest['id'] ?? 0),
        'trace_id' => $observabilityRequest['trace_id'] ?? null,
        'feature' => 'ai_chat',
    ]);
}

$failAndExit = function (string $messageText, string $errorCategory = 'unknown', string $status = 'failed') use (&$requestFinished, $db, $observabilityRequest, $requestStartedAt, $message, $conversationId) {
    if (!$requestFinished && class_exists('AIObservabilityService')) {
        AIObservabilityService::finishRequest($db, (int) ($observabilityRequest['id'] ?? 0), [
            'status' => $status,
            'error_category' => $errorCategory,
            'duration_ms' => (int) round((microtime(true) - $requestStartedAt) * 1000),
            'prompt_summary' => $message,
            'response_summary' => $messageText,
        ]);
        AIObservabilityService::clearRequestContext();
        $requestFinished = true;
    }

    echo json_encode([
        'success' => false,
        'error' => $messageText,
        'request_id' => (int) ($observabilityRequest['id'] ?? 0),
    ]);
    exit;
};

// Server-side Governance & Kill Switch Check (F24)
// Primary defense: AIPolicyEngine centralized evaluation
if (class_exists('AIPolicyEngine')) {
    $policyResult = AIPolicyEngine::evaluate($db, 'data_transmission', [
        'user_id' => $userId,
        'company_id' => $_SESSION['company_id'] ?? 1,
        'provider_id' => $conv['provider_id'],
        'model_id' => $conv['model_id'],
        'request_type' => 'chat',
        'message' => $message,
        'message_classification' => $messageClassification['classification'] ?? 'internal',
        'message_classification_confidence' => $messageClassification['confidence'] ?? null,
    ]);
    if ($policyResult['decision'] === 'deny') {
        AISecurityEventService::recordPolicyViolation($db, [
            'category' => 'policy_violation',
            'severity' => AISecurityEventService::SEVERITY_HIGH,
            'event_type' => 'policy_denied_chat',
            'request_id' => $observabilityRequest['id'] ?? null,
            'trace_id' => $observabilityRequest['trace_id'] ?? null,
        ]);
        $failAndExit($policyResult['reason'] ?: 'Policy denied this action.', 'policy_denied', 'denied');
    }
    if ($policyResult['requires_confirmation']) {
        // Store confirmation requirement for the response
        $pendingConfirmation = true;
        aiAuditLog($db, 'chat_requires_confirmation', 'conversation', $conversationId,
            'Policy requires confirmation: ' . $policyResult['reason'], $userId);
    }
    if ($policyResult['requires_review']) {
        AIReviewQueueService::addToQueue($db, [
            'request_id' => $observabilityRequest['id'] ?? null,
            'trace_id' => $observabilityRequest['trace_id'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? 1,
            'user_id' => $userId,
            'item_type' => 'chat_review',
            'item_data' => ['action' => 'ai_chat', 'message_preview' => substr($message, 0, 200), 'reason' => $policyResult['reason']],
            'risk_level' => AIReviewQueueService::PRIORITY_HIGH,
            'priority' => AIReviewQueueService::PRIORITY_HIGH,
        ]);
    }
    if ($policyResult['masking_required'] || !empty($messageClassification['masking_required'])) {
        // Mask sensitive data before sending to the provider.
        if (class_exists('AIDataClassificationService') && !empty($messageClassification['classification'])) {
            $message = AIDataClassificationService::maskData($message, (string) $messageClassification['classification']);
        }
        if (class_exists('AISecretProtectionService')) {
            $message = AISecretProtectionService::redactSecrets($message);
        }
    }
    if ($policyResult['provider_restricted']) {
        $failAndExit('This provider is not permitted for the selected data classification.', 'policy_denied', 'denied');
    }
}

// Fallback: AIGovernanceService guard check
if (!isset($provider) || $provider === null || !isset($pendingConfirmation)) {
    $guard = AIGovernanceService::guardRequest($db, 'ai_chat', $userId, (int)($_SESSION['company_id'] ?? 1), [
        'provider_id' => $conv['provider_id'],
        'model_id' => $conv['model_id'],
        'request_type' => 'chat',
    ]);
    if (!$guard['allowed']) {
        AISecurityEventService::recordPolicyViolation($db, [
            'category' => 'policy_violation',
            'severity' => AISecurityEventService::SEVERITY_HIGH,
            'event_type' => 'guard_denied_chat',
            'request_id' => $observabilityRequest['id'] ?? null,
            'trace_id' => $observabilityRequest['trace_id'] ?? null,
        ]);
        $failAndExit($guard['message'], 'policy_blocked', 'blocked');
    }
}

// Store user message
$userMsg = AIConversationService::addMessage($db, $conversationId, 'user', $message);
if (!$userMsg['success']) {
    echo json_encode(['success' => false, 'error' => 'Failed to save message.']); exit;
}

// Build ERP context
$pageContext = $input['page_context'] ?? [];
$contextSpanId = class_exists('AIObservabilityService')
    ? AIObservabilityService::startSpan($db, (int) ($observabilityRequest['id'] ?? 0), 'context_retrieval', 'ERP Context Retrieval')
    : 0;
$erpContext = AIContextService::buildContext($db, $userId, $pageContext);
$erpContext = AIGovernanceService::maskData($db, $erpContext, 'procurement');
$contextStr = AIContextService::formatContextForAI($erpContext);
$navContext = AINavigationService::buildNavigationContext($erpContext['permissions']);
if ($contextSpanId > 0) {
    AIObservabilityService::finishSpan($db, $contextSpanId, [
        'status' => 'success',
        'safe_output_summary' => 'ERP and navigation context prepared.',
    ]);
}

// Resolve provider adapter
$adapter = AIProviderAdapterFactory::create($db, $provider);
if (!$adapter) {
    $failAndExit('Unsupported provider type.', 'provider_error');
}

// Check if this model and provider support tool calling
$supportsTools = !empty($model['supports_tool_calling']) && $adapter->supportsToolCalling();

// Get available tools and actions for user if supported
$toolDefs = [];
$actionDefs = [];
$allFunctionsForAI = [];
$toolInstructions = '';

if ($supportsTools) {
    $toolDefs = AIToolRegistry::getAccessible($erpContext['permissions']);
    $actionDefs = AIActionRegistry::getAccessible($erpContext['permissions']);
    $toolsForAI = AIToolRegistry::formatForAI($toolDefs);
    $actionsForAI = AIActionRegistry::formatForAI($actionDefs);
    $allFunctionsForAI = array_merge($toolsForAI, $actionsForAI);

    if (!empty($toolDefs) || !empty($actionDefs)) {
        $toolInstructions = "\n\nAvailable ERP Functions:\n";
        foreach ($toolDefs as $tool) {
            $toolInstructions .= "- [READ] {$tool['name']}: {$tool['description']}\n";
        }
        foreach ($actionDefs as $act) {
            $toolInstructions .= "- [WRITE] {$act['name']}: {$act['description']}\n";
        }
        $toolInstructions .= "\nWhen a user asks about ERP data (vendors, purchase orders, purchase requisitions):\n";
        $toolInstructions .= "1. Use the appropriate READ tool to search or retrieve data.\n";
        $toolInstructions .= "2. Only use tools listed above. Never invent tool names.\n";
        $toolInstructions .= "3. Tools return real data from the ERP database.\n";
        $toolInstructions .= "4. If a tool returns no results, tell the user honestly.\n";
        $toolInstructions .= "5. Do not fabricate data that tools did not return.\n";
        $toolInstructions .= "\nWhen a user wants to CREATE or MODIFY data:\n";
        $toolInstructions .= "1. Use the appropriate WRITE action to prepare the change.\n";
        $toolInstructions .= "2. The action will be prepared as a PENDING ACTION requiring user confirmation.\n";
        $toolInstructions .= "3. NEVER execute write actions directly — they always require confirmation.\n";
        $toolInstructions .= "4. If required information is missing, ask the user for it before proposing the action.\n";
        $toolInstructions .= "5. Once all required info is gathered, propose the action and tell the user to confirm.\n";
        $toolInstructions .= "6. If a matching write action exists but the request is incomplete, guide the user to the correct ERP form instead of saying the task cannot be done.\n";
    }
}

// ═══ AI F11: Knowledge Base Auto-Retrieval ═══
$knowledgeContext = '';
$knowledgeResults = [];

try {
    // Auto-retrieve relevant company knowledge for the user's question
    $knowledgeSpanId = class_exists('AIObservabilityService')
        ? AIObservabilityService::startSpan($db, (int) ($observabilityRequest['id'] ?? 0), 'knowledge_search', 'Knowledge Search')
        : 0;
    $kbSearchResult = AIKnowledgeService::searchKnowledge($db, $userId, $message, null, 5);
    if ($kbSearchResult['success'] && !empty($kbSearchResult['results'])) {
        $knowledgeResults = $kbSearchResult['results'];
        $knowledgeContext = AIKnowledgeService::formatForAI($knowledgeResults);
    }
    if ($knowledgeSpanId > 0) {
        AIObservabilityService::finishSpan($db, $knowledgeSpanId, [
            'status' => 'success',
            'safe_output_summary' => !empty($knowledgeResults) ? 'Knowledge retrieved from company sources.' : 'No relevant company knowledge found.',
            'metadata' => ['matches' => count($knowledgeResults)],
        ]);
    }
} catch (\Throwable $e) {
    // Knowledge retrieval is optional — don't block chat on failure
    if (!empty($knowledgeSpanId)) {
        AIObservabilityService::finishSpan($db, $knowledgeSpanId, [
            'status' => 'partial_success',
            'error_category' => 'context_failure',
            'safe_output_summary' => $e->getMessage(),
        ]);
    }
}

// Get chat history
$history = AIConversationService::getChatHistory($db, $conversationId);

// Build system prompt with all context, including knowledge
$sourceSeparation = "\n\n" . '## Source Types & Rules
' .
    'You have access to three distinct information sources. Always keep them separate:
' .
    '1. [GENERAL KNOWLEDGE] — Your training knowledge. Never claim this came from company documents.
' .
    '2. [ERP DATA] — Real-time data returned by ERP tools. Never claim this came from a document.
' .
    '3. [COMPANY KNOWLEDGE] — Information retrieved from the company knowledge base (shown below). Always cite the source name when referencing this.
' .
    '\nRules:\n' .
    '- Never fabricate source citations.
' .
    '- If company knowledge is insufficient, say so honestly.
' .
    '- Never mix sources invisibly. Always be clear about where information comes from.';

$systemPrompt = AIConversationService::SYSTEM_PROMPT . $sourceSeparation . "\n\n" . $contextStr . "\n\n" . $navContext . $toolInstructions;

// Inject retrieved knowledge into the user message context (not system prompt)
// This keeps the system prompt stable while knowledge changes per-message
$knowledgeInjection = '';
if (!empty($knowledgeContext)) {
    $knowledgeInjection = "\n\n" . $knowledgeContext;
}

// Replace system message in history
$enrichedHistory = [];
$systemReplaced = false;
foreach ($history as $msg) {
    if ($msg['role'] === 'system' && !$systemReplaced) {
        $enrichedHistory[] = ['role' => 'system', 'content' => $systemPrompt];
        $systemReplaced = true;
    } else {
        $enrichedHistory[] = $msg;
    }
}
if (!$systemReplaced) {
    array_unshift($enrichedHistory, ['role' => 'system', 'content' => $systemPrompt]);
}

// Inject knowledge context into the latest user message
if (!empty($knowledgeInjection) && !empty($enrichedHistory)) {
    // Find the last user message and append knowledge context
    for ($i = count($enrichedHistory) - 1; $i >= 0; $i--) {
        if ($enrichedHistory[$i]['role'] === 'user') {
            $enrichedHistory[$i]['content'] .= $knowledgeInjection;
            break;
        }
    }
}

$modelIdStr = $model['model_id'];

// ── Tool Call Lifecycle ──
// Maximum tool call iterations to prevent loops
const MAX_TOOL_ITERATIONS = 5;
$toolIterations = 0;
$allToolCalls = [];
$finalContent = null;
$finalToolCalls = null;

// Prepare chat options
$chatOptions = [];
if (!empty($allFunctionsForAI) && $supportsTools) {
    $chatOptions['tools'] = $allFunctionsForAI;
    $chatOptions['tool_choice'] = 'auto';
}

// Tool call loop
$workingHistory = $enrichedHistory;

while ($toolIterations < MAX_TOOL_ITERATIONS) {
    $toolIterations++;

    // Send to AI
    $providerSpanId = class_exists('AIObservabilityService')
        ? AIObservabilityService::startSpan($db, (int) ($observabilityRequest['id'] ?? 0), 'response_generation', 'Provider Response Generation', [
            'metadata' => ['iteration' => $toolIterations, 'supports_tools' => $supportsTools],
        ])
        : 0;
    $result = $adapter->chat($modelIdStr, $workingHistory, $chatOptions);
    if ($providerSpanId > 0) {
        AIObservabilityService::finishSpan($db, $providerSpanId, [
            'status' => !empty($result['success']) ? 'success' : 'failed',
            'error_category' => !empty($result['success']) ? null : 'provider_error',
            'safe_output_summary' => !empty($result['success']) ? (($result['content'] ?? '') ?: 'Provider returned tool calls.') : ($result['error'] ?? 'Provider request failed.'),
            'metadata' => [
                'tool_call_count' => count($result['tool_calls'] ?? []),
                'input_tokens' => (int) ($result['input_tokens'] ?? 0),
                'output_tokens' => (int) ($result['output_tokens'] ?? 0),
            ],
        ]);
    }

    if (!$result['success']) {
        // AI request failed
        aiAuditLog($db, 'chat_message_failed', 'conversation', $conversationId,
            $result['error'] ?? 'Unknown error', $userId);

        $failAndExit($result['error'] ?? 'Failed to get AI response.', 'provider_error');
    }

    // Check for tool calls
    if (!empty($result['tool_calls'])) {
        // Add the assistant message with tool calls to history
        $assistantMsg = ['role' => 'assistant', 'content' => $result['content'] ?? '', 'tool_calls' => $result['tool_calls']];
        $workingHistory[] = $assistantMsg;

        // Execute each tool/action call
        $toolExecutor = new AIToolExecutor($db, $userId, $erpContext['permissions']);
        $actionExecutor = new AIActionExecutor($db, $userId, $erpContext['permissions']);

        foreach ($result['tool_calls'] as $tc) {
            $toolName = $tc['function']['name'] ?? '';
            $toolArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];
            $toolCallId = $tc['id'] ?? '';

            // Check if this is a READ tool or a WRITE action
            $isAction = AIActionRegistry::get($toolName) !== null;

            if ($isAction) {
                // ACTION: Propose (requires confirmation)
                $actionResult = $actionExecutor->propose($toolName, $toolArgs, $conversationId);

                if ($actionResult['success']) {
                    $allToolCalls[] = [
                        'name'   => $toolName,
                        'status' => 'pending',
                        'summary'=> 'Action prepared — awaiting confirmation',
                    ];

                    // Build a message for AI with the preview
                    $previewText = "Action proposed: {$actionResult['action_name']}\n";
                    $previewText .= "Preview:\n";
                    foreach ($actionResult['preview']['fields'] as $f) {
                        $previewText .= "- {$f['label']}: {$f['value']}\n";
                    }
                    if (!empty($actionResult['preview']['warnings'])) {
                        $previewText .= "Warnings: " . implode(', ', $actionResult['preview']['warnings']) . "\n";
                    }
                    $previewText .= "\nPending Action ID: {$actionResult['pending_action_id']}\n";
                    $previewText .= "Confirmation Token: {$actionResult['confirmation_token']}\n";
                    $previewText .= "Expires: {$actionResult['expires_at']}\n";
                    $previewText .= "\nThe user must confirm this action in the UI. Tell them to click the Confirm button.";

                    $workingHistory[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content'      => $previewText,
                    ];
                } else {
                    $allToolCalls[] = [
                        'name'   => $toolName,
                        'status' => 'error',
                        'summary'=> $actionResult['error'] ?? 'Action failed',
                    ];

                    $workingHistory[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content'      => 'Error: ' . ($actionResult['error'] ?? 'Action could not be prepared'),
                    ];
                }
            } else {
                // READ TOOL: Execute directly
                $toolResult = $toolExecutor->execute($toolName, $toolArgs, $conversationId);

                $allToolCalls[] = [
                    'name'   => $toolName,
                    'status' => $toolResult['success'] ? 'success' : 'error',
                    'summary'=> $toolResult['success']
                        ? 'Found data'
                        : ($toolResult['error'] ?? 'Tool failed'),
                ];

                $toolResultContent = $toolResult['success'] ? $toolResult['result'] : 'Error: ' . ($toolResult['error'] ?? 'Tool execution failed');
                $workingHistory[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content'      => $toolResultContent,
                ];
            }
        }

        // Continue the loop — AI will respond with tool results
        continue;
    }

    // Check action execution policies for each tool call
    if (class_exists('AIPolicyEngine')) {
        foreach ($result['tool_calls'] as $tc) {
            $toolName = $tc['function']['name'] ?? '';
            $toolArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];
            $actionEval = AIPolicyEngine::evaluate($db, 'action_execution', [
                'user_id' => $userId,
                'company_id' => $_SESSION['company_id'] ?? 1,
                'tool_name' => $toolName,
                'tool_args' => $toolArgs,
            ]);
            if ($actionEval['decision'] === 'deny') {
                AISecurityEventService::recordPolicyViolation($db, [
                    'category' => 'policy_violation',
                    'severity' => AISecurityEventService::SEVERITY_HIGH,
                    'event_type' => 'action_denied',
                    'request_id' => $observabilityRequest['id'] ?? null,
                    'trace_id' => $observabilityRequest['trace_id'] ?? null,
                ]);
                $failAndExit('Action denied by policy: ' . ($actionEval['reason'] ?? $toolName), 'policy_denied', 'denied');
            }
        }
    }

    // No tool calls — this is the final response
    $finalContent = $result['content'];
    $finalToolCalls = $allToolCalls;
    break;
}

// If we hit the iteration limit without a final response
if ($finalContent === null) {
    $finalContent = 'I apologize, but I encountered an issue processing your request. Please try again.';
}

// Parse navigation suggestions from AI response
$navigationSuggestions = [];
$aiContent = $finalContent;

if (preg_match('/\{[^{}]*"navigation_suggestions"[^{}]*\[[^\]]*\][^{}]*\}/s', $aiContent, $jsonMatch)) {
    $parsed = json_decode($jsonMatch[0], true);
    if ($parsed && isset($parsed['navigation_suggestions'])) {
        $navigationSuggestions = AINavigationService::formatSuggestions(
            $parsed['navigation_suggestions'],
            $erpContext['permissions']
        );
        $aiContent = trim(str_replace($jsonMatch[0], '', $aiContent));
    }
}

// Store assistant message
$meta = ['model' => $modelIdStr, 'provider' => $provider['provider_type']];
if (!empty($navigationSuggestions)) {
    $meta['navigation'] = $navigationSuggestions;
}

if (!empty($finalContent) && empty($finalToolCalls) && preg_match('/\b(i can(?:not|\'t)|i am unable|i\'m unable|i do not have the ability|i cannot do that|i do not have permission)\b/i', $finalContent)) {
    try {
        $fallbackIntent = AIIntentClassifier::classify($message)['primary'] ?? AIIntentClassifier::INTENT_CONVERSATION;
        $fallbackRoute = AICapabilityRouter::route(
            $db,
            $fallbackIntent,
            $message,
            array_merge($erpContext, ['conversation_id' => $conversationId]),
            AIConversationService::getConversationHistory($db, $conversationId, 10)
        );

        if (!empty($fallbackRoute['success']) && in_array(($fallbackRoute['type'] ?? ''), ['clarification', 'pending_confirmation', 'navigation'], true)) {
            if (!empty($fallbackRoute['message'])) {
                $aiContent = $fallbackRoute['message'];
            }

            if (empty($navigationSuggestions) && !empty($fallbackRoute['navigation']) && is_array($fallbackRoute['navigation'])) {
                $navigationSuggestions = $fallbackRoute['navigation'];
                $meta['navigation'] = $navigationSuggestions;
            }

            if (($fallbackRoute['type'] ?? '') === 'pending_confirmation') {
                $finalToolCalls[] = [
                    'name' => $fallbackRoute['action_id'] ?? 'ai.fallback',
                    'status' => 'pending',
                    'summary' => 'Action prepared — awaiting confirmation',
                ];
                $meta['tools_used'] = $finalToolCalls;
            }
        }
    } catch (\Throwable $e) {
        // Keep the provider response if the deterministic fallback fails.
    }
}
if (!empty($finalToolCalls)) {
    $meta['tools_used'] = $finalToolCalls;
}
if (!empty($knowledgeResults)) {
    $meta['knowledge_sources'] = array_map(fn($r) => [
        'title' => $r['source_title'] ?? 'Unknown',
        'collection' => $r['collection_name'] ?? 'Unknown',
        'relevance' => $r['relevance'] ?? null,
    ], $knowledgeResults);
}
$meta['request_id'] = (int) ($observabilityRequest['id'] ?? 0);
$meta['request_key'] = $observabilityRequest['request_key'] ?? null;

$aiMsg = AIConversationService::addMessageWithTokens(
    $db, $conversationId, 'assistant', $aiContent,
    $result['input_tokens'], $result['output_tokens'], $meta
);

// Log Usage & Observability
AIUsageService::log($db, [
    'provider_id' => $conv['provider_id'],
    'model_id' => $conv['model_id'],
    'feature' => 'ai_chat',
    'user_id' => $userId,
    'company_id' => (int)($_SESSION['company_id'] ?? 1),
    'request_type' => 'chat',
    'input_tokens' => (int)($result['input_tokens'] ?? 0),
    'output_tokens' => (int)($result['output_tokens'] ?? 0),
    'duration_ms' => 0,
    'status' => 'success',
    'metadata' => [
        'conversation_id' => $conversationId,
        'tools_count' => count($finalToolCalls ?? []),
        'observability_request_id' => (int) ($observabilityRequest['id'] ?? 0),
    ],
]);

if (class_exists('AIObservabilityService')) {
    AIObservabilityService::finishRequest($db, (int) ($observabilityRequest['id'] ?? 0), [
        'provider_id' => $conv['provider_id'],
        'model_id' => $conv['model_id'],
        'status' => !empty($finalToolCalls) ? 'partial_success' : 'success',
        'duration_ms' => (int) round((microtime(true) - $requestStartedAt) * 1000),
        'input_tokens' => (int) ($result['input_tokens'] ?? 0),
        'output_tokens' => (int) ($result['output_tokens'] ?? 0),
        'prompt_summary' => $message,
        'response_summary' => $aiContent,
        'metadata' => [
            'conversation_id' => $conversationId,
            'tool_iterations' => $toolIterations,
            'tools_count' => count($finalToolCalls ?? []),
            'knowledge_used' => !empty($knowledgeResults),
        ],
    ]);
    AIObservabilityService::clearRequestContext();
    $requestFinished = true;
}

// Audit
aiAuditLog($db, 'chat_message_sent', 'conversation', $conversationId,
    'Model: ' . $modelIdStr . ', Tools: ' . count($finalToolCalls), $userId);

echo json_encode([
    'success'           => true,
    'message'           => $aiContent,
    'request_id'        => (int) ($observabilityRequest['id'] ?? 0),
    'request_key'       => $observabilityRequest['request_key'] ?? null,
    'input_tokens'      => $result['input_tokens'],
    'output_tokens'     => $result['output_tokens'],
    'navigation'        => $navigationSuggestions,
    'tools_used'        => $finalToolCalls,
    'knowledge_used'    => !empty($knowledgeResults),
    'knowledge_sources' => $meta['knowledge_sources'] ?? [],
]);
