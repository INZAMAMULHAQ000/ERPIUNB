<?php
/**
 * AI F15 — Command Center Service.
 *
 * Orchestrates the full command lifecycle:
 * context gathering, classification, routing, response composition, history.
 */
class AICommandCenterService
{
    const MAX_MESSAGE_LENGTH = 4000;
    const MAX_CONVERSATION_HISTORY = 50;
    const MAX_DECOMPOSED_STEPS = 5;
    const MAX_TOOLS_PER_COMMAND = 5;
    const MAX_RESULT_RECORDS = 20;
    const MAX_CONCURRENT_COMMANDS = 3;

    /**
     * Process a user command through the full pipeline.
     */
    public static function processCommand(PDO $db, int $userId, string $input, ?int $conversationId = null, array $pageContext = []): array
    {
        $startTime = microtime(true);
        $companyId = $pageContext['company_id'] ?? $_SESSION['company_id'] ?? 1;

        // Validate input
        $input = trim($input);
        if (empty($input)) {
            return ['success' => false, 'error' => 'Please enter a command.'];
        }
        if (mb_strlen($input) > self::MAX_MESSAGE_LENGTH) {
            return ['success' => false, 'error' => 'Command too long. Maximum ' . self::MAX_MESSAGE_LENGTH . ' characters.'];
        }

        // Check concurrent command limit
        $running = $db->prepare("SELECT COUNT(*) FROM ai_command_executions WHERE user_id = ? AND status = 'running'");
        $running->execute([$userId]);
        if ((int) $running->fetchColumn() >= self::MAX_CONCURRENT_COMMANDS) {
            return ['success' => false, 'error' => 'Too many concurrent commands. Please wait for current commands to complete.'];
        }

        // Get or create conversation
        if (!$conversationId) {
            $conversationId = self::createConversation($db, $userId, $companyId);
        }

        $observabilityRequest = ['id' => 0, 'request_key' => ''];
        if (class_exists('AIObservabilityService')) {
            $observabilityRequest = AIObservabilityService::beginRequest($db, [
                'company_id' => $companyId,
                'user_id' => $userId,
                'command_conversation_id' => $conversationId,
                'session_reference' => 'command:' . $conversationId,
                'entry_point' => 'command_center',
                'feature' => 'command_center',
                'prompt_summary' => $input,
                'metadata' => ['page_context' => $pageContext],
            ]);
            AIObservabilityService::setRequestContext([
                'request_id' => (int) ($observabilityRequest['id'] ?? 0),
                'trace_id' => $observabilityRequest['trace_id'] ?? null,
                'feature' => 'command_center',
            ]);
        }

        try {
            // Store user message
            $userMsgId = self::storeMessage($db, $conversationId, $userId, 'user', $input);

            // Get conversation history
            $history = self::getConversationHistory($db, $conversationId, 10);

            // Build context
            $contextSpanId = class_exists('AIObservabilityService')
                ? AIObservabilityService::startSpan($db, (int) ($observabilityRequest['id'] ?? 0), 'context_retrieval', 'Command Context Build')
                : 0;
            $context = array_merge($pageContext, [
                'user_id' => $userId,
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
            ]);
            if ($contextSpanId > 0) {
                AIObservabilityService::finishSpan($db, $contextSpanId, [
                    'status' => 'success',
                    'safe_output_summary' => 'Command context prepared.',
                ]);
            }

            // Classify intent
            $classificationSpanId = class_exists('AIObservabilityService')
                ? AIObservabilityService::startSpan($db, (int) ($observabilityRequest['id'] ?? 0), 'intent_classification', 'Intent Classification')
                : 0;
            $classification = AIIntentClassifier::classify($input);
            $intent = $classification['primary'];
            if ($classificationSpanId > 0) {
                AIObservabilityService::finishSpan($db, $classificationSpanId, [
                    'status' => 'success',
                    'safe_output_summary' => 'Intent resolved to ' . $intent,
                    'metadata' => ['confidence' => $classification['confidence'] ?? null],
                ]);
            }

            // Route to capability
            $routeSpanId = class_exists('AIObservabilityService')
                ? AIObservabilityService::startSpan($db, (int) ($observabilityRequest['id'] ?? 0), 'capability_routing', 'Capability Routing')
                : 0;
            $routeResult = AICapabilityRouter::route($db, $intent, $input, $context, $history);
            if ($routeSpanId > 0) {
                AIObservabilityService::finishSpan($db, $routeSpanId, [
                    'status' => !empty($routeResult['success']) ? 'success' : 'partial_success',
                    'safe_output_summary' => $routeResult['message'] ?? 'Capability routed.',
                    'metadata' => ['source' => $routeResult['source'] ?? 'unknown'],
                ]);
            }

            // Compose response
            $response = self::composeResponse($routeResult, $classification);
            $response['request_id'] = (int) ($observabilityRequest['id'] ?? 0);

            // Store assistant message
            $assistantMetadata = array_merge($response, [
                'request_id' => (int) ($observabilityRequest['id'] ?? 0),
                'request_key' => $observabilityRequest['request_key'] ?? null,
            ]);
            $assistantMsgId = self::storeMessage($db, $conversationId, $userId, 'assistant', $response['message'] ?? '', $intent, $assistantMetadata);

            // Create execution record
            $duration = round((microtime(true) - $startTime) * 1000);
            self::createExecutionRecord($db, $conversationId, $assistantMsgId, $userId, $companyId, $input, $intent, $routeResult, $duration);

            // Update conversation title if first message
            self::updateConversationTitle($db, $conversationId, $input);

            // Log audit
            self::log('command_processed', $userId, ['intent' => $intent, 'conversation_id' => $conversationId]);

            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::finishRequest($db, (int) ($observabilityRequest['id'] ?? 0), [
                    'status' => !empty($routeResult['success']) ? 'success' : 'partial_success',
                    'duration_ms' => $duration,
                    'prompt_summary' => $input,
                    'response_summary' => $response['message'] ?? '',
                    'metadata' => [
                        'intent' => $intent,
                        'confidence' => $classification['confidence'] ?? null,
                        'source' => $routeResult['source'] ?? 'unknown',
                    ],
                ]);
            }

            return array_merge($response, [
                'success' => true,
                'conversation_id' => $conversationId,
                'intent' => $intent,
                'confidence' => $classification['confidence'],
            ]);
        } catch (\Throwable $e) {
            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::finishRequest($db, (int) ($observabilityRequest['id'] ?? 0), [
                    'status' => 'failed',
                    'error_category' => 'unknown',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000),
                    'prompt_summary' => $input,
                    'response_summary' => $e->getMessage(),
                ]);
            }
            return ['success' => false, 'error' => 'Command processing failed.'];
        } finally {
            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::clearRequestContext();
            }
        }
    }

    /**
     * Compose a structured response from routing results.
     */
    private static function composeResponse(array $routeResult, array $classification): array
    {
        $type = $routeResult['type'] ?? 'answer';
        $message = $routeResult['message'] ?? 'No response generated.';

        $response = [
            'type' => $type,
            'message' => $message,
            'source' => $routeResult['source'] ?? 'system',
        ];

        // Add type-specific fields
        if ($type === 'navigation') {
            $response['route'] = function_exists('resolveAppUrl')
                ? resolveAppUrl($routeResult['route'] ?? '')
                : ($routeResult['route'] ?? '');
            $response['route_label'] = $routeResult['label'] ?? 'Open';
        }

        if ($type === 'pending_confirmation') {
            $response['action_id'] = $routeResult['action_id'] ?? '';
            $response['action_name'] = $routeResult['action_name'] ?? ($routeResult['label'] ?? 'Pending Action');
            $response['pending_action_id'] = $routeResult['pending_action_id'] ?? null;
            $response['confirmation_token'] = $routeResult['confirmation_token'] ?? null;
            $response['preview'] = $routeResult['preview'] ?? null;
            $response['requires_confirmation'] = true;
        }

        if (!empty($routeResult['navigation']) && is_array($routeResult['navigation'])) {
            $response['navigation'] = $routeResult['navigation'];
        }

        if ($type === 'data_result') {
            $response['items'] = $routeResult['items'] ?? [];
            $response['count'] = $routeResult['count'] ?? 0;
            $response['tool_used'] = $routeResult['tool_used'] ?? '';
        }

        if ($type === 'knowledge_result') {
            $response['items'] = $routeResult['items'] ?? [];
        }

        if ($type === 'multi') {
            $response['results'] = $routeResult['results'] ?? [];
            $response['intents'] = $routeResult['intents'] ?? [];
        }

        if ($type === 'insight') {
            $response['items'] = $routeResult['items'] ?? [];
            $response['counts'] = $routeResult['counts'] ?? [];
        }

        return $response;
    }

    // ═══════════════════════════════════════════════════════
    // CONVERSATION MANAGEMENT
    // ═══════════════════════════════════════════════════════

    /**
     * Create a new conversation.
     */
    public static function createConversation(PDO $db, int $userId, int $companyId): int
    {
        $db->prepare("
            INSERT INTO ai_command_conversations (user_id, company_id, status, started_at)
            VALUES (?, ?, 'active', NOW())
        ")->execute([$userId, $companyId]);
        return (int) $db->lastInsertId();
    }

    /**
     * Get user's recent conversations.
     */
    public static function getConversations(PDO $db, int $userId, int $limit = 10): array
    {
        $stmt = $db->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM ai_command_messages WHERE conversation_id = c.id) as message_count
            FROM ai_command_conversations c
            WHERE c.user_id = ? AND c.status = 'active'
            ORDER BY c.last_message_at DESC
            LIMIT " . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a conversation with messages.
     */
    public static function getConversation(PDO $db, int $conversationId, int $userId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_command_conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$conversationId, $userId]);
        $conversation = $stmt->fetch();
        if (!$conversation) return null;

        $messages = self::getConversationHistory($db, $conversationId, self::MAX_CONVERSATION_HISTORY);
        $conversation['messages'] = $messages;

        return $conversation;
    }

    /**
     * Get conversation history.
     */
    public static function getConversationHistory(PDO $db, int $conversationId, int $limit = 20): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_command_messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC
            LIMIT " . (int) $limit
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    /**
     * Store a message.
     */
    private static function storeMessage(PDO $db, int $conversationId, int $userId, string $role, string $content, ?string $intent = null, ?array $metadata = null): int
    {
        $db->prepare("
            INSERT INTO ai_command_messages (conversation_id, user_id, role, content, intent, metadata_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$conversationId, $userId, $role, $content, $intent, $metadata ? json_encode($metadata) : null]);

        // Update conversation last_message_at
        $db->prepare("UPDATE ai_command_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conversationId]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update conversation title from first user message.
     */
    private static function updateConversationTitle(PDO $db, int $conversationId, string $firstMessage): void
    {
        $stmt = $db->prepare("SELECT title FROM ai_command_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        if (!$stmt->fetchColumn()) {
            $title = mb_substr($firstMessage, 0, 100);
            $db->prepare("UPDATE ai_command_conversations SET title = ? WHERE id = ?")->execute([$title, $conversationId]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // EXECUTION RECORDS
    // ═══════════════════════════════════════════════════════

    /**
     * Create an execution record.
     */
    private static function createExecutionRecord(PDO $db, ?int $conversationId, ?int $messageId, int $userId, int $companyId, string $input, string $intent, array $result, int $durationMs): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_command_executions
                (conversation_id, message_id, user_id, company_id, command_summary, intent,
                 capability_chain, status, result_summary, duration_ms, started_at, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ")->execute([
                $conversationId,
                $messageId,
                $userId,
                $companyId,
                mb_substr($input, 0, 500),
                $intent,
                json_encode([$result['source'] ?? 'unknown']),
                $result['success'] ? 'completed' : 'failed',
                mb_substr($result['message'] ?? '', 0, 1000),
                $durationMs,
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Get execution history for a user.
     */
    public static function getExecutionHistory(PDO $db, int $userId, int $limit = 20): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_command_executions
            WHERE user_id = ?
            ORDER BY started_at DESC
            LIMIT " . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════
    // SUGGESTIONS
    // ═══════════════════════════════════════════════════════

    /**
     * Get contextual suggestions.
     */
    public static function getSuggestions(PDO $db, ?string $module = null): array
    {
        $sql = "SELECT * FROM ai_command_suggestions WHERE enabled = 1";
        $params = [];
        if ($module) {
            $sql .= " AND (module = ? OR module IS NULL)";
            $params[] = $module;
        }
        $sql .= " ORDER BY sort_order ASC, module ASC LIMIT 20";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Seed default suggestions.
     */
    public static function seedSuggestions(PDO $db): int
    {
        $suggestions = [
            ['module' => null, 'category' => 'general', 'text' => 'What needs my attention?', 'intent' => 'insight_request', 'sort' => 1],
            ['module' => null, 'category' => 'general', 'text' => 'Show my tasks', 'intent' => 'data_lookup', 'sort' => 2],
            ['module' => null, 'category' => 'general', 'text' => 'Take me to the dashboard', 'intent' => 'navigation', 'sort' => 3],
            ['module' => 'procurement', 'category' => 'procurement', 'text' => 'Show pending requisitions', 'intent' => 'data_lookup', 'sort' => 10],
            ['module' => 'procurement', 'category' => 'procurement', 'text' => 'Find a supplier', 'intent' => 'data_lookup', 'sort' => 11],
            ['module' => 'procurement', 'category' => 'procurement', 'text' => 'Review procurement backlog', 'intent' => 'analysis', 'sort' => 12],
            ['module' => 'procurement', 'category' => 'procurement', 'text' => 'Check procurement policy', 'intent' => 'knowledge_search', 'sort' => 13],
        ];

        $inserted = 0;
        foreach ($suggestions as $s) {
            try {
                $db->prepare("
                    INSERT IGNORE INTO ai_command_suggestions (module, category, suggestion_text, intent_hint, sort_order, enabled, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ")->execute([$s['module'], $s['category'], $s['text'], $s['intent'], $s['sort']]);
                $inserted++;
            } catch (\Throwable $e) {}
        }
        return $inserted;
    }

    // ═══════════════════════════════════════════════════════
    // AUDIT
    // ═══════════════════════════════════════════════════════

    private static function log(string $event, int $userId, ?array $extra = null): void
    {
        try {
            $db = getDB();
            $details = array_merge(['event' => $event], $extra ?? []);
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'command', ?, ?, NOW())
            ")->execute([$userId, "f15_{$event}", $extra['conversation_id'] ?? 0, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
