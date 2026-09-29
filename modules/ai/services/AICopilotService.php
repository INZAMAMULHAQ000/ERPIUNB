<?php
/**
 * AI F29 — Universal Copilot Service.
 *
 * Orchestrates ALL AI capabilities through a single unified pipeline:
 * User → Copilot → Understand → Retrieve → Reason → Plan → Govern → Execute → Verify → Explain
 *
 * Covers Sections 1-30.
 */
class AICopilotService
{
    private $db;
    private $companyId;
    private $userId;
    private $router;
    private $sessionId;

    public function __construct(PDO $db, int $companyId, int $userId, ?int $sessionId = null)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->router = new AICapabilityRouter($db, $companyId, $userId);
        $this->router->loadCapabilities();
    }

    // ================================================================
    // MAIN COPILOT PIPELINE
    // ================================================================

    /**
     * Process a copilot message through the full pipeline.
     *
     * Pipeline: Understand → Retrieve → Reason → Plan → Govern → Execute → Verify → Explain
     */
    public function processMessage(string $text, array $context = [], ?int $sessionId = null): array
    {
        $startTime = microtime(true);
        if ($sessionId) $this->sessionId = $sessionId;

        // ============================================================
        // HARD BOUNDARIES (Section 30): Block dangerous input patterns
        // ============================================================
        $blocked = $this->validateInput($text);
        if ($blocked) {
            return [
                'success' => false,
                'answer' => 'I cannot process that request. The input contains patterns that are not allowed.',
                'intent' => 'blocked',
                'components' => [['type' => 'warning', 'content' => 'Request blocked by security policy.']],
                'warnings' => ['Input contains disallowed patterns'],
                'latency_ms' => 0,
            ];
        }

        // Tenant isolation: verify session belongs to current company
        if ($this->sessionId) {
            $stmt = $this->db->prepare("SELECT company_id FROM ai_copilot_sessions WHERE id = ?");
            $stmt->execute([$this->sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($session && (int)$session['company_id'] !== $this->companyId) {
                return [
                    'success' => false,
                    'answer' => 'Session access denied. Tenant mismatch.',
                    'intent' => 'denied',
                    'latency_ms' => 0,
                ];
            }
        }

        // Ensure session exists
        if (!$this->sessionId) {
            $this->createSession($context);
        }

        // Record user message
        $userMsgId = $this->recordMessage('user', $text, $context);

        // ===== STEP 1: UNDERSTAND (Intent Classification) =====
        $intent = $this->classifyIntent($text, $context);

        // ===== STEP 2: RETRIEVE (Load relevant data) =====
        $retrieval = $this->retrieveContext($intent, $context);

        // ===== STEP 3: REASON (Process with AI/LLM) =====
        $reasoning = $this->reason($text, $intent, $retrieval, $context);

        // ===== STEP 4: PLAN (For multi-step actions) =====
        $plan = null;
        if ($intent['type'] === 'multi_step_task' || $intent['type'] === 'workflow_request') {
            $plan = $this->generatePlan($reasoning, $intent);
        }

        // ===== STEP 5: GOVERN (F24 Governance) =====
        $governance = $this->govern($intent, $reasoning, $plan);

        // ===== STEP 6: EXECUTE (If allowed) =====
        $execution = null;
        if ($governance['allowed']) {
            $execution = $this->execute($intent, $reasoning, $plan, $governance, $context);
        }

        // ===== STEP 7: VERIFY (Check results) =====
        $verification = $this->verify($intent, $execution);

        // ===== STEP 8: EXPLAIN (Build response) =====
        $response = $this->explain($text, $intent, $reasoning, $plan, $governance, $execution, $verification, $context);

        // Record assistant message
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);
        $assistantMsgId = $this->recordMessage('assistant', $response['answer'], [
            'intent' => $intent,
            'governance' => $governance,
            'execution' => $execution,
            'latency_ms' => $latencyMs,
        ], $intent['type'], $intent['confidence'], $latencyMs);

        // ===== F23: Record observability =====
        $this->recordTrace(
            $userMsgId, $assistantMsgId, $intent, $governance, $execution, $latencyMs,
            $reasoning['provider'] ?? null, $reasoning['model'] ?? null
        );

        $response['message_id'] = $assistantMsgId;
        $response['session_id'] = $this->sessionId;
        $response['latency_ms'] = $latencyMs;

        return $response;
    }

    // ================================================================
    // HARD BOUNDARIES: Input Validation (Section 30)
    // ================================================================

    /**
     * Validate copilot input for dangerous patterns.
     * Blocks SQL injection, PHP code, shell commands, arbitrary URLs.
     */
    private function validateInput(string $text): bool
    {
        $lower = strtolower($text);

        // Block SQL injection patterns
        if (preg_match('/\b(DROP\s+TABLE|DELETE\s+FROM|INSERT\s+INTO|UPDATE\s+.*\s+SET|EXEC\s*\(|EXECUTE\s*\(|UNION\s+SELECT|ALTER\s+TABLE|--\s|;\s*DROP)/i', $text)) {
            return true;
        }

        // Block natural-language requests to generate or run SQL/database queries.
        if (
            preg_match('/\b(write|generate|create|show|build|run|execute|draft|produce|give|make|help)\b/i', $text) &&
            preg_match('/\b(sql|query)\b/i', $text)
        ) {
            return true;
        }
        if (preg_match('/\b(query|select|report|list)\b.*\b(database|db|table|tables|records|rows)\b/i', $text)) {
            return true;
        }

        // Block PHP code patterns
        if (preg_match('/(<\?php|<\?=|\$_GET|\$_POST|\$_SERVER|\$_SESSION|eval\s*\(|exec\s*\(|system\s*\(|passthru|shell_exec|proc_open)/i', $text)) {
            return true;
        }

        // Block shell command patterns
        if (preg_match('/(\bbash\b|\bsh\b\s+-c|\bcmd\.exe\b|\bpowershell\b|\bwget\b|\bcurl\b\s+http|\brm\s+-rf|\bchmod\b|\bchown\b|\bcat\s+\/etc|\bdir\b|\bls\b|\bcopy\b|\bmove\b|\brename\b|\bdel\b|\bremove\b)/i', $text)) {
            return true;
        }

        // Block direct filesystem access requests
        if (preg_match('/(\bread|write|delete|open|list|browse|modify|edit|access|include|require)\s+(?:the\s+)?(?:file|files|folder|folders|directory|directories|filesystem|path)\b/i', $text)) {
            return true;
        }
        if (preg_match('/(\.\.\/|\\.\\.\\\\|[A-Za-z]:\\\\|\/etc\/|\/var\/|\/home\/|\/root\/)/i', $text)) {
            return true;
        }

        // Block arbitrary URL schemes (Section 30: no arbitrary URLs)
        if (preg_match('/^(https?:\/\/|ftp:\/\/|file:\/\/|data:)/i', trim($text))) {
            return true;
        }

        // Block attempts to create capabilities or grant permissions (Section 30)
        if (preg_match('/(create\s+capability|grant\s+permission|add\s+permission|bypass\s+security|bypass\s+governance|override\s+policy)/i', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * Scan retrieved content for prompt injection before passing to LLM.
     */
    private function sanitizeRetrievedContent(array $records): array
    {
        if (class_exists('AIInjectionDetectionService')) {
            foreach ($records as &$record) {
                foreach ($record as $key => $value) {
                    if (is_string($value) && strlen($value) > 10) {
                        $analysis = AIInjectionDetectionService::analyze(
                            $this->db, $value,
                            AIInjectionDetectionService::TRUST_UNTRUSTED_DOC,
                            ['source' => 'copilot_retrieval', 'field' => $key]
                        );
                        if ($analysis['action'] === 'block') {
                            $record[$key] = '[Content blocked by security policy]';
                        } elseif (!$analysis['safe']) {
                            $record[$key] = '[Content flagged as potentially unsafe]';
                        }
                    }
                }
            }
        }
        return $records;
    }

    /**
     * Scan document content for injection before passing to LLM.
     */
    private function sanitizeDocumentContent(array $document): array
    {
        if (class_exists('AIInjectionDetectionService') && !empty($document['extracted_text'])) {
            $analysis = AIInjectionDetectionService::analyze(
                $this->db, $document['extracted_text'],
                AIInjectionDetectionService::TRUST_UNTRUSTED_DOC,
                ['source' => 'copilot_document', 'doc_id' => $document['id'] ?? null]
            );
            if ($analysis['action'] === 'block') {
                $document['extracted_text'] = '[Document content blocked by security policy]';
            } elseif (!$analysis['safe']) {
                $document['extracted_text'] = '[Document content flagged - treat as untrusted data]';
            }
        }
        return $document;
    }

    // ================================================================
    // STEP 1: INTENT CLASSIFICATION (Section 3)
    // ================

    /**
     * Classify the user's intent into a controlled intent type.
     */
    private function classifyIntent(string $text, array $context): array
    {
        $lower = strtolower(trim($text));

        // Deterministic routing for simple intents (Section 28: avoid unnecessary model calls)
        $deterministic = $this->deterministicClassify($lower, $context);
        if ($deterministic) {
            return $deterministic;
        }

        // Fallback: LLM-based intent classification
        $intent = $this->llmClassifyIntent($text, $context);

        // Log intent
        $this->logIntent($text, $intent, $context);

        return $intent;
    }

    /**
     * Deterministic intent classification for common patterns.
     * Avoids unnecessary LLM calls (Section 28).
     */
    private function deterministicClassify(string $lower, array $context): ?array
    {
        // Navigation intents
        if (preg_match('/^(open|go\s+to|take\s+me\s+to|navigate|show\s+me\s+the|switch\s+to)\s+/i', $lower)) {
            return [
                'type' => 'navigation',
                'confidence' => 1.0,
                'parameters' => ['text' => $lower],
                'deterministic' => true,
            ];
        }

        // Simple search
        if (preg_match('/^(search|find|look\s+for|where\s+is|show\s+me)\s+/i', $lower)) {
            return [
                'type' => 'search',
                'confidence' => 0.95,
                'parameters' => ['text' => $lower],
                'deterministic' => true,
            ];
        }

        // "Do it" / "Handle this" semantics (Section 10)
        if (preg_match('/^(do\s+it|handle\s+this|fix\s+this|take\s+care|go\s+ahead|confirm|proceed)\s*$/i', $lower)) {
            return [
                'type' => 'do_it',
                'confidence' => 0.9,
                'parameters' => ['text' => $lower, 'needs_context' => true],
                'deterministic' => true,
            ];
        }

        // Quick commands (Section 17)
        $quickCommands = [
            'whats important today' => 'intelligence_investigation',
            'what\'s important today' => 'intelligence_investigation',
            'what needs my attention' => 'intelligence_investigation',
            'show overdue' => 'search',
            'overdue items' => 'search',
            'summarize my tasks' => 'analysis',
            'review recent documents' => 'document_question',
        ];

        foreach ($quickCommands as $pattern => $intent) {
            if (similar_text($lower, $pattern) / max(strlen($lower), strlen($pattern)) > 0.6) {
                return [
                    'type' => $intent,
                    'confidence' => 0.85,
                    'parameters' => ['text' => $lower, 'quick_command' => true],
                    'deterministic' => true,
                ];
            }
        }

        return null; // Needs LLM classification
    }

    /**
     * LLM-based intent classification.
     */
    private function llmClassifyIntent(string $text, array $context): array
    {
        $validIntents = [
            'question', 'navigation', 'search', 'analysis', 'explanation',
            'document_question', 'recommendation', 'intelligence_investigation',
            'action', 'multi_step_task', 'workflow_request',
            'external_integration_request', 'scheduled_task', 'follow_up', 'do_it',
        ];

        // Use the existing AI chat pipeline if available
        if (class_exists('AIChatService')) {
            $prompt = "Classify this ERP user request into exactly one of these intents: " . implode(', ', $validIntents) . "\n\n";
            $prompt .= "Context: " . json_encode(['module' => $context['module'] ?? null, 'page' => $context['page'] ?? null]) . "\n\n";
            $prompt .= "Request: " . $text . "\n\n";
            $prompt .= "Respond with JSON: {\"intent\": \"<intent_type>\", \"confidence\": <0.0-1.0>, \"parameters\": {}}";

            $chatResult = AIChatService::sendMessage($this->db, $this->companyId, $this->userId, $prompt, [
                'system_prompt' => 'You are an ERP intent classifier. Return only valid JSON.',
                'max_tokens' => 200,
            ]);

            if ($chatResult['success']) {
                $parsed = json_decode($chatResult['response'] ?? '{}', true);
                if (isset($parsed['intent']) && in_array($parsed['intent'], $validIntents)) {
                    return [
                        'type' => $parsed['intent'],
                        'confidence' => (float)($parsed['confidence'] ?? 0.7),
                        'parameters' => $parsed['parameters'] ?? [],
                        'deterministic' => false,
                    ];
                }
            }
        }

        // Default fallback intent
        return [
            'type' => 'question',
            'confidence' => 0.5,
            'parameters' => ['text' => $text],
            'deterministic' => false,
        ];
    }

    // ================================================================
    // STEP 2: RETRIEVE (Section 7: Universal Search)
    // ================================================================

    /**
     * Retrieve relevant context for the intent.
     */
    private function retrieveContext(array $intent, array $context): array
    {
        $retrieval = [
            'records' => [],
            'documents' => [],
            'intelligence' => [],
            'knowledge' => [],
        ];

        // If we have page context, retrieve record details
        if (!empty($context['record_type']) && !empty($context['record_id'])) {
            $retrieval['records'] = $this->retrieveRecord($context['record_type'], (int)$context['record_id']);
        }

        // For search intent, perform universal search
        if ($intent['type'] === 'search') {
            $retrieval['records'] = $this->universalSearch($intent['parameters']['text'] ?? '');
            // Store for citation generation
            $context['_search_results'] = $retrieval['records'];
        }

        // For intelligence intent, get briefing data
        if ($intent['type'] === 'intelligence_investigation' || $intent['type'] === 'recommendation') {
            if (class_exists('AIBriefingService')) {
                $retrieval['intelligence'] = AIBriefingService::generateBriefing($this->db, $this->userId, $this->companyId);
            }
        }

        // For document questions, retrieve document context
        if ($intent['type'] === 'document_question') {
            if (!empty($context['document_id'])) {
                $retrieval['documents'] = $this->retrieveDocument((int)$context['document_id']);
            }
        }

        // F29 Test 21: Sanitize all retrieved content for injection before LLM
        $retrieval['records'] = $this->sanitizeRetrievedContent($retrieval['records']);
        if (!empty($retrieval['documents'])) {
            $retrieval['documents'] = $this->sanitizeDocumentContent($retrieval['documents']);
        }

        return $retrieval;
    }

    /**
     * Retrieve a specific ERP record.
     */
    private function retrieveRecord(string $recordType, int $recordId): array
    {
        // Map record types to tables (with tenant isolation)
        $tableMap = [
            'customer' => ['table' => 'customers', 'company_col' => 'company_id'],
            'supplier' => ['table' => 'suppliers', 'company_col' => 'company_id'],
            'product' => ['table' => 'products', 'company_col' => 'company_id'],
            'invoice' => ['table' => 'invoices', 'company_col' => 'company_id'],
            'purchase_order' => ['table' => 'purchase_orders', 'company_col' => 'company_id'],
            'employee' => ['table' => 'employees', 'company_col' => 'company_id'],
        ];

        $mapping = $tableMap[strtolower($recordType)] ?? null;
        if (!$mapping) return [];

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$mapping['table']}
                WHERE id = ? AND {$mapping['company_col']} = ?
                LIMIT 1
            ");
            $stmt->execute([$recordId, $this->companyId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Universal search across ERP modules (Section 7).
     */
    private function universalSearch(string $query): array
    {
        $results = [];
        $searchLimit = 5;

        $searchable = [
            ['table' => 'customers', 'label_col' => 'name', 'module' => 'sales', 'type' => 'customer'],
            ['table' => 'suppliers', 'label_col' => 'name', 'module' => 'procurement', 'type' => 'supplier'],
            ['table' => 'products', 'label_col' => 'name', 'module' => 'inventory', 'type' => 'product'],
            ['table' => 'invoices', 'label_col' => 'invoice_number', 'module' => 'finance', 'type' => 'invoice'],
            ['table' => 'purchase_orders', 'label_col' => 'po_number', 'module' => 'procurement', 'type' => 'purchase_order'],
        ];

        foreach ($searchable as $source) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id, {$source['label_col']} as label, '{$source['type']}' as record_type, '{$source['module']}' as module
                    FROM {$source['table']}
                    WHERE company_id = ? AND {$source['label_col']} LIKE ?
                    LIMIT ?
                ");
                $stmt->execute([$this->companyId, '%' . $query . '%', $searchLimit]);
                $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            } catch (\Throwable $e) {
                // Skip failed searches
            }
        }

        return $results;
    }

    /**
     * Retrieve document context.
     */
    private function retrieveDocument(int $documentId): array
    {
        if (!class_exists('AIDocumentService')) return [];

        try {
            return AIDocumentService::getById($this->db, $documentId, $this->companyId) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ================================================================
    // STEP 3: REASON (Process with AI)
    // ================================================================

    /**
     * Process the request with AI reasoning.
     */
    private function reason(string $text, array $intent, array $retrieval, array $context): array
    {
        $response = ['answer' => '', 'evidence' => [], 'reasoning' => '', 'provider' => null, 'model' => null];

        // Build evidence from retrieval
        $evidence = [];
        if (!empty($retrieval['records'])) {
            $evidence[] = 'Found ' . count($retrieval['records']) . ' relevant records';
        }
        if (!empty($retrieval['intelligence'])) {
            $intel = $retrieval['intelligence'];
            if (!empty($intel['priorities'])) {
                $evidence[] = count($intel['priorities']) . ' priority items identified';
            }
        }
        if (!empty($retrieval['documents'])) {
            $evidence[] = 'Document context loaded: ' . ($retrieval['documents']['file_name'] ?? 'unknown');
        }

        // For simple intents, provide deterministic answers
        if ($intent['type'] === 'navigation') {
            return [
                'answer' => 'Navigating...',
                'evidence' => [],
                'reasoning' => 'Navigation request detected',
                'navigation' => $this->router->routeNavigation($intent['parameters']['text'] ?? $text),
                'provider' => 'deterministic',
                'model' => null,
            ];
        }

        if ($this->isProcessIntelligenceQuery($text) && class_exists('AIProcessIntelligenceService')) {
            $service = new AIProcessIntelligenceService($this->db, $this->companyId, $this->userId);
            $result = $service->processRequest($text, $context);

            if (!empty($result['success'])) {
                return $this->buildProcessIntelligenceReasoning($result);
            }

            return [
                'answer' => $result['error'] ?? 'Process intelligence could not be completed.',
                'evidence' => ['Process Intelligence request was blocked or unavailable.'],
                'reasoning' => 'Process intelligence request failed',
                'provider' => 'deterministic',
                'model' => null,
                'route' => function_exists('url') ? url('modules/ai/process-intelligence/index.php') : '/modules/ai/process-intelligence/index.php',
                'page_name' => 'Process Intelligence',
                'source' => 'process_intelligence',
                'process_result' => $result,
            ];
        }

        // Use LLM for complex reasoning
        if (class_exists('AIChatService')) {
            $systemPrompt = "You are an AI ERP Copilot. You help users with their ERP system. ";
            $systemPrompt .= "Always base your answers on the evidence provided. ";
            $systemPrompt .= "Never fabricate data. If you don't know, say so. ";
            $systemPrompt .= "Be concise and business-focused. ";
            $systemPrompt .= "Never include passwords, API keys, tokens, or secrets in your response. ";

            $contextText = '';
            if (!empty($retrieval['records'])) {
                $contextText .= "\n\nRelevant records:\n" . json_encode(array_slice($retrieval['records'], 0, 10), JSON_PRETTY_PRINT);
            }
            if (!empty($retrieval['intelligence'])) {
                $contextText .= "\n\nIntelligence data:\n" . json_encode(array_slice($retrieval['intelligence']['priorities'] ?? [], 0, 5), JSON_PRETTY_PRINT);
            }
            if (!empty($retrieval['documents'])) {
                $contextText .= "\n\nDocument content:\n" . substr($retrieval['documents']['extracted_text'] ?? '', 0, 2000);
            }

            $chatResult = AIChatService::sendMessage($this->db, $this->companyId, $this->userId, $text . $contextText, [
                'system_prompt' => $systemPrompt,
            ]);

            if ($chatResult['success']) {
                $answer = $chatResult['response'] ?? '';
                // F29 Test 20: Strip secrets from LLM response
                if (class_exists('AISecretProtectionService')) {
                    $answer = AISecretProtectionService::redactSecrets($answer);
                }
                $response['answer'] = $answer;
                $response['evidence'] = $evidence;
                $response['reasoning'] = 'AI analysis based on available evidence';
                $response['provider'] = $chatResult['provider'] ?? 'unknown';
                $response['model'] = $chatResult['model'] ?? 'unknown';
            }
        }

        return $response;
    }

    // ================================================================
    // STEP 4: PLAN (Section 11: Plan-First Experience)
    // ================================================================

    /**
     * Generate a plan for multi-step tasks.
     */
    private function generatePlan(array $reasoning, array $intent): ?array
    {
        if (empty($reasoning['answer'])) return null;

        $plan = [
            'steps' => [],
            'estimated_steps' => 0,
            'requires_confirmation' => false,
        ];

        if (class_exists('AIChatService')) {
            $prompt = "Generate an execution plan for this request. Return JSON with steps array.\n";
            $prompt .= "Each step: {\"action\": \"...\", \"capability\": \"...\", \"risk_level\": \"low|medium|high\", \"requires_confirmation\": true|false}\n";
            $prompt .= "Request: " . $reasoning['answer'];

            $chatResult = AIChatService::sendMessage($this->db, $this->companyId, $this->userId, $prompt, [
                'system_prompt' => 'Return only valid JSON array of plan steps.',
                'max_tokens' => 500,
            ]);

            if ($chatResult['success']) {
                $parsed = json_decode($chatResult['response'] ?? '[]', true);
                if (is_array($parsed)) {
                    $plan['steps'] = $parsed;
                    $plan['estimated_steps'] = count($parsed);
                    foreach ($parsed as $step) {
                        if (!empty($step['requires_confirmation'])) {
                            $plan['requires_confirmation'] = true;
                            break;
                        }
                    }
                }
            }
        }

        return $plan;
    }

    // ================================================================
    // STEP 5: GOVERN (Section 25: Governance Pipeline)
    // ================================================================

    /**
     * Apply F24 governance to the intent and planned execution.
     */
    private function govern(array $intent, array $reasoning, ?array $plan): array
    {
        $result = [
            'allowed' => true,
            'decision' => 'allow',
            'requires_confirmation' => false,
            'requires_review' => false,
            'risk_level' => 'low',
            'checks' => [],
        ];

        // 1. Emergency kill switch (Test 24: check ALL relevant kill switches)
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($this->db, 'disable_ai_actions')
                || AIEmergencyControlsService::isControlActive($this->db, 'disable_all_ai')) {
                return [
                    'allowed' => false,
                    'decision' => 'deny',
                    'reason' => 'AI actions are disabled by emergency kill switch',
                    'checks' => ['emergency' => false],
                ];
            }
            // For write/action intents, also check disable_ai_actions_specifically
            if (in_array($intent['type'], ['action', 'multi_step_task', 'workflow_request', 'external_integration_request'])) {
                if (AIEmergencyControlsService::isControlActive($this->db, 'disable_autonomous_agents')) {
                    return [
                        'allowed' => false,
                        'decision' => 'deny',
                        'reason' => 'Autonomous actions are disabled by emergency kill switch',
                        'checks' => ['emergency_agents' => false],
                    ];
                }
            }
        }

        // 2. F24 full governance middleware
        if (class_exists('AIGovernanceMiddleware')) {
            $middlewareResult = AIGovernanceMiddleware::enforce($this->db, 'copilot', $intent['type'], [
                'user_id' => $this->userId,
                'company_id' => $this->companyId,
                'intent' => $intent,
                'action_type' => $intent['type'] === 'action' ? 'write' : 'read',
            ]);

            $result['checks']['governance'] = $middlewareResult;
            if (!$middlewareResult['allowed']) {
                return [
                    'allowed' => false,
                    'decision' => $middlewareResult['decision'] ?? 'deny',
                    'reason' => $middlewareResult['reason'] ?? 'Governance denied this action',
                    'checks' => $result['checks'],
                ];
            }

            if (!empty($middlewareResult['requires_confirmation'])) {
                $result['requires_confirmation'] = true;
            }
            if (!empty($middlewareResult['requires_review'])) {
                $result['requires_review'] = true;
            }
        }

        // 3. Plan-level governance
        if ($plan && !empty($plan['requires_confirmation'])) {
            $result['requires_confirmation'] = true;
        }

        // 4. Risk assessment
        $result['risk_level'] = $this->assessCopilotRisk($intent);

        // 5. F24 Provider restrictions (Test 20): check if sensitive data + restricted provider
        if (class_exists('AIPolicyEngine')) {
            $providerCheck = AIPolicyEngine::evaluateProvider($this->db, $this->companyId, $intent['type']);
            if (!empty($providerCheck) && !empty($providerCheck['denied'])) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = 'Provider not permitted for this type of data: ' . ($providerCheck['reason'] ?? '');
            }
        }

        return $result;
    }

    /**
     * Assess risk level for copilot actions.
     */
    private function assessCopilotRisk(array $intent): string
    {
        $type = $intent['type'] ?? 'question';

        $riskMap = [
            'question' => 'low',
            'search' => 'low',
            'explanation' => 'low',
            'navigation' => 'low',
            'analysis' => 'low',
            'recommendation' => 'low',
            'document_question' => 'low',
            'intelligence_investigation' => 'medium',
            'action' => 'high',
            'multi_step_task' => 'high',
            'workflow_request' => 'high',
            'external_integration_request' => 'high',
            'scheduled_task' => 'medium',
        ];

        return $riskMap[$type] ?? 'medium';
    }

    // ================================================================
    // STEP 6: EXECUTE (Section 4: Capability Execution)
    // ================================================================

    /**
     * Execute through the appropriate capability.
     */
    private function execute(array $intent, array $reasoning, ?array $plan, array $governance, array $context): ?array
    {
        // Navigation: return redirect URL
        if ($intent['type'] === 'navigation' && !empty($reasoning['navigation']['success'])) {
            return [
                'type' => 'navigation',
                'url' => $this->resolveRouteUrl($reasoning['navigation']['url'] ?? ''),
                'page_name' => $reasoning['navigation']['page_name'],
            ];
        }

        if (($reasoning['source'] ?? '') === 'process_intelligence' && !empty($reasoning['route'])) {
            return [
                'type' => 'navigation',
                'url' => $this->resolveRouteUrl($reasoning['route']),
                'page_name' => $reasoning['page_name'] ?? 'Process Intelligence',
            ];
        }

        // Multi-step task: create F25 agent task
        if ($intent['type'] === 'multi_step_task' && $plan) {
            return $this->executeViaF25($reasoning, $plan, $context);
        }

        // Intelligence investigation
        if ($intent['type'] === 'intelligence_investigation') {
            return $this->executeIntelligenceInvestigation($reasoning, $context);
        }

        // For read-only intents, no execution needed
        if (in_array($intent['type'], ['question', 'search', 'explanation', 'analysis', 'document_question', 'recommendation'])) {
            return [
                'type' => 'informational',
                'delivered' => true,
            ];
        }

        // External integration
        if ($intent['type'] === 'external_integration_request') {
            return $this->executeViaF22($reasoning, $governance, $context);
        }

        return null;
    }

    private function isProcessIntelligenceQuery(string $text): bool
    {
        return preg_match('/\b(process(?:es)?|bottleneck(?:s)?|approval\s+delay(?:s)?|cycle\s+time(?:s)?|sla|breach(?:es)?|rework|stuck|stalled|workflow\s+change|process\s+comparison|slow(?:er|down)?|delay(?:ed|s)?|taking\s+(?:so\s+)?long|too\s+long|queue(?:s|d)?|backlog(?:s)?|lag(?:ging)?|late(?:r)?)\b/i', $text) === 1;
    }

    private function buildProcessIntelligenceReasoning(array $result): array
    {
        $route = $result['route'] ?? (function_exists('url') ? url('modules/ai/process-intelligence/index.php') : '/modules/ai/process-intelligence/index.php');
        $summary = $this->summarizeProcessIntelligenceResult($result);

        return [
            'answer' => $summary,
            'evidence' => $this->buildProcessIntelligenceEvidence($result),
            'reasoning' => 'Routed to AI Process Intelligence',
            'provider' => 'deterministic',
            'model' => null,
            'route' => $route,
            'page_name' => 'Process Intelligence',
            'source' => 'process_intelligence',
            'process_result' => $result,
        ];
    }

    private function summarizeProcessIntelligenceResult(array $result): string
    {
        $type = $result['type'] ?? 'overview';

        if ($type === 'bottlenecks') {
            $findings = $result['findings'] ?? [];
            $top = $findings[0]['title'] ?? 'No bottlenecks detected';
            return 'I routed this to AI Process Intelligence. ' . count($findings) . ' bottleneck finding(s) were detected. ' . $top;
        }

        if ($type === 'metrics') {
            $rows = $result['metrics']['rows'][0] ?? [];
            return 'I routed this to AI Process Intelligence. Median cycle time is ' . ($rows['median_cycle_hours'] ?? 'n/a') . ' hrs, with p90 ' . ($rows['p90_cycle_hours'] ?? 'n/a') . ' hrs and p95 ' . ($rows['p95_cycle_hours'] ?? 'n/a') . ' hrs.';
        }

        if ($type === 'sla') {
            $sla = $result['sla'] ?? [];
            return 'I routed this to AI Process Intelligence. ' . (int) ($sla['breached_count'] ?? 0) . ' breached, ' . (int) ($sla['met_count'] ?? 0) . ' met, ' . (int) ($sla['at_risk_count'] ?? 0) . ' at risk, breach rate ' . ($sla['breach_percentage'] ?? 0) . '%.';
        }

        if ($type === 'rework') {
            $rework = $result['rework'] ?? [];
            return 'I routed this to AI Process Intelligence. ' . (int) ($rework['rework_instance_count'] ?? 0) . ' instance(s) show rework, average rework ' . ($rework['avg_rework_hours'] ?? 0) . ' hrs.';
        }

        if ($type === 'comparison') {
            $rows = $result['comparison']['rows'] ?? [];
            return 'I routed this to AI Process Intelligence. It compared ' . count($rows) . ' authorized dimension group(s) for ' . ($result['process_key'] ?? 'the selected process') . '.';
        }

        if ($type === 'process_map') {
            $transitions = $result['map']['transitions'] ?? [];
            return 'I routed this to AI Process Intelligence. ' . count($transitions) . ' grounded transition path(s) were identified.';
        }

        return 'I routed this to AI Process Intelligence for grounded process analysis.';
    }

    private function buildProcessIntelligenceEvidence(array $result): array
    {
        $evidence = [];
        if (!empty($result['process_key'])) {
            $evidence[] = 'Process key: ' . $result['process_key'];
        }
        if (isset($result['metrics']['rows'][0]['sample_size'])) {
            $evidence[] = 'Sample size: ' . $result['metrics']['rows'][0]['sample_size'];
        }
        if (isset($result['sla']['sample_size'])) {
            $evidence[] = 'Sample size: ' . $result['sla']['sample_size'];
        }
        if (isset($result['rework']['sample_size'])) {
            $evidence[] = 'Sample size: ' . $result['rework']['sample_size'];
        }
        if (!empty($result['findings'][0]['title'])) {
            $evidence[] = 'Top finding: ' . $result['findings'][0]['title'];
        }
        if (!empty($result['comparison']['dimension'])) {
            $evidence[] = 'Comparison dimension: ' . $result['comparison']['dimension'];
        }
        $evidence[] = 'Route: ' . (function_exists('url') ? url('modules/ai/process-intelligence/index.php') : '/modules/ai/process-intelligence/index.php');

        return $evidence;
    }

    /**
     * Execute a multi-step task through F25.
     */
    private function executeViaF25(array $reasoning, array $plan, array $context): array
    {
        if (class_exists('AIAgentRuntimeService')) {
            $agentKey = $this->resolveAutonomousAgentKey($reasoning, $context);
            $agentId = AIAgentRuntimeService::resolveAgentDefinitionIdByKey($this->db, $agentKey)
                ?? AIAgentRuntimeService::resolveAgentDefinitionIdByKey($this->db, 'erp_general_assistant')
                ?? 0;
            $userPermissions = function_exists('getUserPermissions') ? getUserPermissions($this->userId) : [];
            $taskResult = AIAgentRuntimeService::createTask(
                $this->db, $this->userId, $this->companyId,
                $userPermissions, $agentId,
                $reasoning['answer'],
                'copilot',
                null
            );

            if ($taskResult['success']) {
                return [
                    'type' => 'f25_task',
                    'task_id' => $taskResult['task_id'],
                    'task_key' => $taskResult['task_key'],
                    'status' => 'created',
                    'plan' => $plan,
                ];
            }
        }

        return ['type' => 'failed', 'error' => 'Could not create agent task'];
    }

    /**
     * Execute intelligence investigation (Section 13).
     */
    private function executeIntelligenceInvestigation(array $reasoning, array $context): array
    {
        $result = [
            'type' => 'intelligence_investigation',
            'findings' => [],
            'recommendations' => [],
        ];

        // Get intelligence briefing
        if (class_exists('AIBriefingService')) {
            $briefing = AIBriefingService::generateBriefing($this->db, $this->userId, $this->companyId);
            $result['findings'] = $briefing['priorities'] ?? [];
            $result['recommendations'] = $briefing['recommended_actions'] ?? [];
        }

        return $result;
    }

    /**
     * Execute external integration through F22.
     */
    private function executeViaF22(array $reasoning, array $governance, array $context): array
    {
        return [
            'type' => 'external_integration',
            'requires_confirmation' => $governance['requires_confirmation'],
            'message' => 'External integration requires confirmation.',
        ];
    }

    // ================================================================
    // STEP 7: VERIFY (Section 19: Error Recovery)
    // ================================================================

    /**
     * Verify execution results.
     */
    private function verify(array $intent, ?array $execution): array
    {
        $verification = [
            'verified' => true,
            'partial' => false,
            'failures' => [],
            'successes' => [],
            'remaining' => [],
        ];

        if (!$execution) {
            if (in_array($intent['type'], ['question', 'search', 'explanation', 'analysis', 'document_question'])) {
                $verification['verified'] = true;
            }
            return $verification;
        }

        if ($execution['type'] === 'failed') {
            $verification['verified'] = false;
            $verification['partial'] = !empty($execution['partial']);
            $verification['failures'][] = $execution['error'] ?? 'Unknown error';
            if (!empty($execution['remaining'])) {
                $verification['remaining'] = $execution['remaining'];
            }
        }

        if ($execution['type'] === 'f25_task') {
            $verification['successes'][] = 'Agent task created: ' . ($execution['task_key'] ?? '');
            // Check if task was fully created or partially
            if (!empty($execution['plan']['steps'])) {
                $verification['successes'][] = 'Plan with ' . count($execution['plan']['steps']) . ' steps prepared';
            }
        }

        if ($execution['type'] === 'navigation') {
            $verification['successes'][] = 'Navigation destination found: ' . ($execution['page_name'] ?? '');
        }

        if ($execution['type'] === 'informational') {
            $verification['successes'][] = 'Information retrieved successfully';
        }

        if ($execution['type'] === 'intelligence_investigation') {
            $count = count($execution['findings'] ?? []);
            $verification['successes'][] = "Intelligence investigation complete: {$count} findings";
        }

        // Test 15: Build clear partial failure message
        if ($verification['partial'] || (!empty($verification['failures']) && !empty($verification['successes']))) {
            $verification['partial'] = true;
            // Build human-readable summary
            $parts = [];
            if (!empty($verification['successes'])) {
                $parts[] = count($verification['successes']) . ' operation(s) succeeded';
            }
            if (!empty($verification['failures'])) {
                $parts[] = count($verification['failures']) . ' operation(s) failed';
            }
            if (!empty($verification['remaining'])) {
                $parts[] = count($verification['remaining']) . ' operation(s) remaining';
            }
            $verification['summary'] = implode('; ', $parts);
        }

        return $verification;
    }

    // ================================================================
    // STEP 8: EXPLAIN (Section 12, 22: Response Components)
    // ================================================================

    /**
     * Build the final response with all components.
     */
    private function explain(
        string $text,
        array $intent,
        array $reasoning,
        ?array $plan,
        array $governance,
        ?array $execution,
        array $verification,
        array $context
    ): array {
        $response = [
            'success' => true,
            'answer' => $reasoning['answer'] ?? '',
            'intent' => $intent['type'],
            'intent_confidence' => $intent['confidence'] ?? 0,
            'components' => [],
            'citations' => [],
            'actions' => [],
            'warnings' => [],
            'requires_confirmation' => $governance['requires_confirmation'] ?? false,
            'requires_review' => $governance['requires_review'] ?? false,
            'risk_level' => $governance['risk_level'] ?? 'low',
        ];

        // Answer component
        if (!empty($reasoning['answer'])) {
            $response['components'][] = [
                'type' => 'answer',
                'content' => $reasoning['answer'],
            ];
        }

        // Evidence component
        if (!empty($reasoning['evidence'])) {
            $response['components'][] = [
                'type' => 'evidence',
                'items' => $reasoning['evidence'],
            ];
        }

        // Citations (Section 23): from execution + search results
        if (!empty($execution['url'])) {
            $response['citations'][] = [
                'label' => $execution['page_name'] ?? 'View',
                'url' => $execution['url'],
            ];
        }
        if (($reasoning['source'] ?? '') === 'process_intelligence' && !empty($reasoning['route'])) {
            $hasProcessCitation = false;
            foreach ($response['citations'] as $cite) {
                if ($this->resolveRouteUrl($cite['url'] ?? '') === $this->resolveRouteUrl($reasoning['route'])) {
                    $hasProcessCitation = true;
                    break;
                }
            }
            if (!$hasProcessCitation) {
                $response['citations'][] = [
                    'label' => $reasoning['page_name'] ?? 'Process Intelligence',
                    'url' => $this->resolveRouteUrl($reasoning['route']),
                ];
            }

            $hasProcessAction = false;
            foreach ($response['actions'] as $action) {
                if (($action['type'] ?? '') === 'navigate' && $this->resolveRouteUrl($action['url'] ?? '') === $this->resolveRouteUrl($reasoning['route'])) {
                    $hasProcessAction = true;
                    break;
                }
            }
            if (!$hasProcessAction) {
                $response['actions'][] = [
                    'type' => 'navigate',
                    'label' => 'Open ' . ($reasoning['page_name'] ?? 'Process Intelligence'),
                    'url' => $this->resolveRouteUrl($reasoning['route']),
                ];
            }
        }
        // Add citations from search results
        if (!empty($context['_search_results'])) {
            foreach (array_slice($context['_search_results'], 0, 5) as $sr) {
                $url = $this->getRecordUrl($sr['record_type'] ?? '', (int)($sr['id'] ?? 0));
                if ($url) {
                    $response['citations'][] = [
                        'label' => ($sr['record_type'] ?? '') . ': ' . ($sr['label'] ?? ''),
                        'url' => $url,
                    ];
                }
            }
        }

        // Plan component (Section 11)
        if ($plan && !empty($plan['steps'])) {
            $response['components'][] = [
                'type' => 'plan',
                'steps' => $plan['steps'],
                'estimated_steps' => $plan['estimated_steps'],
                'requires_confirmation' => $plan['requires_confirmation'],
            ];

            // Action buttons for plan
            $response['actions'][] = [
                'type' => 'execute_plan',
                'label' => 'Execute Plan',
                'requires_confirmation' => $plan['requires_confirmation'],
            ];
            $response['actions'][] = [
                'type' => 'change_plan',
                'label' => 'Change Plan',
            ];
        }

        // Task handoff (Section 20)
        if ($execution && $execution['type'] === 'f25_task') {
            $response['components'][] = [
                'type' => 'task_progress',
                'task_id' => $execution['task_id'],
                'task_key' => $execution['task_key'],
                'status' => $execution['status'],
                'message' => "I'll handle this as a background task. You can continue using the ERP.",
            ];
        }

        // Navigation result
        if ($execution && $execution['type'] === 'navigation') {
            $response['actions'][] = [
                'type' => 'navigate',
                'label' => 'Open ' . ($execution['page_name'] ?? ''),
                'url' => $execution['url'],
            ];
        }

        // Warnings & Partial Failures (Section 19: Error Recovery)
        if (!empty($verification['failures'])) {
            $response['warnings'] = $verification['failures'];
            if ($verification['partial'] ?? false) {
                $summary = $verification['summary'] ?? implode('; ', $verification['failures']);
                $response['answer'] .= "\n\n⚠️ Partial results: " . $summary;
                if (!empty($verification['successes'])) {
                    $response['answer'] .= "\n✅ " . implode('; ', $verification['successes']);
                }
                if (!empty($verification['remaining'])) {
                    $response['answer'] .= "\n📋 Remaining: " . implode('; ', $verification['remaining']);
                }
            } else {
                $response['answer'] .= "\n\n⚠️ " . implode('; ', $verification['failures']);
            }
        }

        // Intelligence findings (Section 13)
        if ($execution && $execution['type'] === 'intelligence_investigation') {
            if (!empty($execution['findings'])) {
                $response['components'][] = [
                    'type' => 'intelligence',
                    'findings' => $execution['findings'],
                    'recommendations' => $execution['recommendations'],
                ];
            }
        }

        // Confirmation UX (Section 18)
        if (($governance['requires_confirmation'] ?? false) || ($governance['requires_review'] ?? false)) {
            $response['components'][] = [
                'type' => 'confirmation',
                'action' => $intent['type'],
                'risk_level' => $governance['risk_level'] ?? 'low',
                'requires_review' => $governance['requires_review'] ?? false,
            ];
        }

        return $response;
    }

    // ================================================================
    // SESSION MANAGEMENT
    // ================================================================

    /**
     * Create a new copilot session (Section 16: Personal AI Home).
     */
    public function createSession(array $context = []): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_copilot_sessions
                (company_id, user_id, source, context_module, context_page,
                 context_record_type, context_record_id, context_metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->companyId,
            $this->userId,
            $context['source'] ?? 'web',
            $context['module'] ?? null,
            $context['page'] ?? null,
            $context['record_type'] ?? null,
            $context['record_id'] ?? null,
            !empty($context) ? json_encode($context) : null,
        ]);

        $this->sessionId = (int) $this->db->lastInsertId();

        return ['success' => true, 'session_id' => $this->sessionId];
    }

    /**
     * Get the user's personal AI home data (Section 16).
     */
    public function getHomeData(): array
    {
        $home = [
            'today_priorities' => [],
            'recent_conversations' => [],
            'active_tasks' => [],
            'pending_approvals' => [],
            'important_alerts' => [],
            'recent_documents' => [],
            'suggested_actions' => [],
            'quick_commands' => $this->router->getQuickCommands(),
        ];

        // Today's priorities from F26
        if (class_exists('AIBriefingService')) {
            $briefing = AIBriefingService::generateBriefing($this->db, $this->userId, $this->companyId);
            $home['today_priorities'] = $briefing['priorities'] ?? [];
        }

        // Recent conversations
        $stmt = $this->db->prepare("
            SELECT id, started_at, last_intent, message_count
            FROM ai_copilot_sessions
            WHERE company_id = ? AND user_id = ? AND status = 'active'
            ORDER BY last_activity_at DESC LIMIT 5
        ");
        $stmt->execute([$this->companyId, $this->userId]);
        $home['recent_conversations'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Active tasks from F25
        if (class_exists('AIAgentRuntimeService')) {
            $stmt = $this->db->prepare("
                SELECT id, task_key, description, status, priority, created_at
                FROM ai_agent_tasks
                WHERE company_id = ? AND user_id = ? AND status IN ('pending', 'running')
                ORDER BY created_at DESC LIMIT 5
            ");
            $stmt->execute([$this->companyId, $this->userId]);
            $home['active_tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Pending approvals from F24/F25
        try {
            $stmt = $this->db->prepare("
                SELECT id, action_type, risk_level, created_at, expires_at
                FROM ai_voice_confirmations
                WHERE company_id = ? AND user_id = ? AND status = 'pending' AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 5
            ");
            $stmt->execute([$this->companyId, $this->userId]);
            $home['pending_approvals'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

        // Important alerts from F26
        try {
            $stmt = $this->db->prepare("
                SELECT id, title, severity, event_type, created_at
                FROM ai_intelligence_events
                WHERE company_id = ? AND status = 'new'
                AND (assigned_to = ? OR assigned_to IS NULL)
                ORDER BY
                    CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
                    created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$this->companyId, $this->userId]);
            $home['important_alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

        // Recent documents from F27
        try {
            $stmt = $this->db->prepare("
                SELECT id, file_name, document_type, status, created_at
                FROM ai_documents
                WHERE company_id = ? AND status = 'active'
                ORDER BY created_at DESC LIMIT 5
            ");
            $stmt->execute([$this->companyId]);
            $home['recent_documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Suggested actions
        $home['suggested_actions'] = $this->generateSuggestedActions($home);

        return $home;
    }

    /**
     * Generate suggested actions based on context.
     */
    private function generateSuggestedActions(array $home): array
    {
        $actions = [];

        if (!empty($home['today_priorities'])) {
            $actions[] = [
                'label' => 'Review ' . count($home['today_priorities']) . ' priority items',
                'prompt' => "Show me today's priorities",
                'icon' => 'fas fa-exclamation-triangle',
                'color' => '#e74c3c',
            ];
        }

        if (!empty($home['pending_approvals'])) {
            $actions[] = [
                'label' => 'Review ' . count($home['pending_approvals']) . ' pending approvals',
                'prompt' => 'Show my pending approvals',
                'icon' => 'fas fa-check-circle',
                'color' => '#f39c12',
            ];
        }

        if (!empty($home['active_tasks'])) {
            $actions[] = [
                'label' => 'Check ' . count($home['active_tasks']) . ' active tasks',
                'prompt' => 'Show my active tasks',
                'icon' => 'fas fa-tasks',
                'color' => '#667eea',
            ];
        }

        return $actions;
    }

    /**
     * Get conversation history for a session.
     */
    public function getHistory(int $sessionId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_copilot_messages
            WHERE session_id = ? AND company_id = ?
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $this->companyId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get recent sessions for the user.
     */
    public function getRecentSessions(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   (SELECT content_text FROM ai_copilot_messages 
                    WHERE session_id = s.id AND role = 'user' 
                    ORDER BY created_at ASC LIMIT 1) as first_message
            FROM ai_copilot_sessions s
            WHERE s.company_id = ? AND s.user_id = ?
            ORDER BY s.last_activity_at DESC
            LIMIT ?
        ");
        $stmt->execute([$this->companyId, $this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Record a message in the session.
     */
    private function recordMessage(string $role, string $content, array $metadata = [], ?string $intentType = null, ?float $confidence = null, ?int $latencyMs = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_copilot_messages
                (session_id, company_id, user_id, role, content_text,
                 intent_type, intent_confidence, intent_parameters,
                 capabilities_used, routing_decision, governance_decision,
                 latency_ms, response_components)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->sessionId,
            $this->companyId,
            $this->userId,
            $role,
            $content,
            $intentType,
            $confidence,
            !empty($metadata['intent']) ? json_encode($metadata['intent']) : null,
            !empty($metadata['capabilities_used']) ? json_encode($metadata['capabilities_used']) : null,
            !empty($metadata['routing']) ? json_encode($metadata['routing']) : null,
            !empty($metadata['governance']) ? json_encode($metadata['governance']) : null,
            $latencyMs,
            !empty($metadata['response_components']) ? json_encode($metadata['response_components']) : null,
        ]);

        $msgId = (int) $this->db->lastInsertId();

        // Update session
        $this->db->prepare("
            UPDATE ai_copilot_sessions
            SET message_count = message_count + 1, last_intent = ?, last_activity_at = NOW()
            WHERE id = ?
        ")->execute([$intentType, $this->sessionId]);

        return $msgId;
    }

    /**
     * Log an intent classification.
     */
    private function logIntent(string $text, array $intent, array $context): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_copilot_intents
                    (company_id, user_id, session_id, raw_text, classified_intent,
                     confidence, parameters)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->companyId,
                $this->userId,
                $this->sessionId,
                substr($text, 0, 5000),
                $intent['type'],
                $intent['confidence'] ?? null,
                !empty($intent['parameters']) ? json_encode($intent['parameters']) : null,
            ]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Record F23 observability trace.
     */
    private function recordTrace(
        int $userMsgId,
        int $assistantMsgId,
        array $intent,
        array $governance,
        ?array $execution,
        int $latencyMs,
        ?string $provider = null,
        ?string $model = null
    ): void {
        try {
            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::recordTrace($this->db, $this->companyId, 'copilot_message', [
                    'user_message_id' => $userMsgId,
                    'assistant_message_id' => $assistantMsgId,
                    'session_id' => $this->sessionId,
                    'intent' => $intent['type'],
                    'intent_confidence' => $intent['confidence'],
                    'intent_parameters' => $intent['parameters'] ?? [],
                    'deterministic' => $intent['deterministic'] ?? false,
                    'provider' => $provider,
                    'model' => $model,
                    'governance_decision' => $governance['decision'] ?? 'allow',
                    'governance_checks' => array_keys($governance['checks'] ?? []),
                    'risk_level' => $governance['risk_level'] ?? 'low',
                    'requires_confirmation' => $governance['requires_confirmation'] ?? false,
                    'execution_type' => $execution['type'] ?? null,
                    'execution_success' => ($execution['type'] ?? '') !== 'failed',
                    'latency_ms' => $latencyMs,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Get a safe ERP URL for a record type and ID.
     * Only returns registered routes (Section 21, 30).
     */
    private function getRecordUrl(string $recordType, int $recordId): ?string
    {
        $routeMap = [
            'customer' => '/modules/sales/customers/view.php?id=',
            'supplier' => '/modules/procurement/suppliers/view.php?id=',
            'product' => '/modules/inventory/products/view.php?id=',
            'invoice' => '/modules/finance/invoices/view.php?id=',
            'purchase_order' => '/modules/procurement/po/view.php?id=',
            'employee' => '/modules/hr/employees/view.php?id=',
        ];

        $prefix = $routeMap[strtolower($recordType)] ?? null;
        if ($prefix && $recordId > 0) {
            return $this->resolveRouteUrl($prefix . $recordId);
        }
        return null;
    }

    private function resolveAutonomousAgentKey(array $reasoning, array $context): string
    {
        $haystack = strtolower(trim(implode(' ', [
            (string) ($reasoning['answer'] ?? ''),
            (string) ($reasoning['reasoning'] ?? ''),
            (string) ($context['module'] ?? ''),
            (string) ($context['page'] ?? ''),
            (string) ($context['record_type'] ?? ''),
        ])));

        if (preg_match('/\b(procurement|supplier|requisition|quotation|quote|rfq|purchase order|vendor)\b/i', $haystack)) {
            return 'procurement_specialist';
        }

        return 'erp_general_assistant';
    }

    private function resolveRouteUrl(?string $route): string
    {
        $route = trim((string) $route);
        if ($route === '') {
            return '';
        }

        if (function_exists('resolveAppUrl')) {
            return resolveAppUrl($route);
        }

        if (function_exists('url')) {
            return url(ltrim($route, '/'));
        }

        return $route;
    }

    // ================================================================
    // FEEDBACK (Section 27)
    // ================================================================

    /**
     * Submit feedback on a copilot response.
     */
    public function submitFeedback(int $messageId, string $feedbackType, ?string $feedbackText = null): array
    {
        $validTypes = ['helpful', 'not_helpful', 'incorrect', 'incomplete', 'successful', 'partial', 'unsafe'];
        if (!in_array($feedbackType, $validTypes)) {
            return ['success' => false, 'error' => 'Invalid feedback type'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO ai_copilot_feedback
                (company_id, user_id, session_id, message_id, feedback_type, feedback_text)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->companyId,
            $this->userId,
            $this->sessionId,
            $messageId,
            $feedbackType,
            $feedbackText,
        ]);

        // Also update the message
        $this->db->prepare("
            UPDATE ai_copilot_messages
            SET feedback = ?, feedback_at = NOW()
            WHERE id = ? AND company_id = ?
        ")->execute([$feedbackType, $messageId, $this->companyId]);

        return ['success' => true];
    }

    // ================================================================
    // CONVERSATIONAL FOLLOW-UP (Section 9)
    // ================================================================

    /**
     * Get recent context from the current session for follow-up understanding.
     */
    public function getSessionContext(int $sessionId, int $count = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT role, content_text, intent_type, intent_parameters, created_at
            FROM ai_copilot_messages
            WHERE session_id = ? AND company_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $this->companyId, $count]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_reverse($messages); // Chronological
    }
}
