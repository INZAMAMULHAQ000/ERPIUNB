<?php
/**
 * AI F15 — Capability Router.
 *
 * Maps classified intents to existing AI capabilities (F7-F14).
 * Each capability handler must be registered.
 */
class AICapabilityRouter
{
    private static array $handlers = [];
    private static bool $defaultsLoaded = false;

    private PDO $db;
    private int $companyId;
    private int $userId;
    private array $capabilities = [];

    public function __construct(?PDO $db = null, int $companyId = 1, int $userId = 0)
    {
        $this->db = $db ?? getDB();
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    public function loadCapabilities(): array
    {
        if (!self::$defaultsLoaded) {
            self::registerDefaults();
            self::$defaultsLoaded = true;
        }

        if (empty($this->capabilities)) {
            $this->capabilities = $this->fetchCapabilities();
        }

        return $this->capabilities;
    }

    public function getAvailableCapabilities(): array
    {
        return $this->loadCapabilities();
    }

    public function getQuickCommands(): array
    {
        return [
            ['text' => "Where are our biggest process bottlenecks?"],
            ['text' => "Why are invoices slower this month?"],
            ['text' => "Show SLA breaches for requisition approvals"],
            ['text' => "Compare requisition approvals between departments"],
            ['text' => "Show cycle time metrics for requisition approvals"],
            ['text' => "Create a task from this request"],
            ['text' => "Create a user account"],
            ['text' => "Show my recent documents"],
            ['text' => "What's important today?"],
        ];
    }

    public function routeNavigation(string $input): array
    {
        $result = self::handleNavigation($this->db, $input, [
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
        ]);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'url' => !empty($result['route'])
                ? (function_exists('resolveAppUrl') ? resolveAppUrl($result['route']) : (function_exists('url') ? url(ltrim((string) $result['route'], '/')) : (string) $result['route']))
                : '',
            'route' => !empty($result['route'])
                ? (function_exists('resolveAppUrl') ? resolveAppUrl($result['route']) : (function_exists('url') ? url(ltrim((string) $result['route'], '/')) : (string) $result['route']))
                : '',
            'page_name' => $result['label'] ?? ($result['page_name'] ?? 'Navigation'),
            'label' => $result['label'] ?? ($result['page_name'] ?? 'Open'),
            'message' => $result['message'] ?? '',
            'alternatives' => $result['alternatives'] ?? [],
            'source' => $result['source'] ?? 'navigation',
        ];
    }

    /**
     * Register a capability handler.
     */
    public static function register(string $intent, callable $handler): void
    {
        self::$handlers[$intent] = $handler;
    }

    /**
     * Route a request to the appropriate capability handler(s).
     */
    public static function route(PDO $db, string $intent, string $input, array $context = [], array $conversationHistory = []): array
    {
        if (!self::$defaultsLoaded) {
            self::registerDefaults();
            self::$defaultsLoaded = true;
        }

        $results = [];

        // Handle multi-intent by routing to each matched intent
        if ($intent === AIIntentClassifier::INTENT_MULTI_INTENT) {
            $classification = AIIntentClassifier::classify($input);
            $intents = $classification['intents'] ?? [];

            foreach ($intents as $subIntent) {
                if (isset(self::$handlers[$subIntent])) {
                    $result = self::executeHandler($db, $subIntent, $input, $context, $conversationHistory);
                    if ($result) $results[] = $result;
                }
            }

            return [
                'success' => true,
                'type' => 'multi',
                'results' => $results,
                'intents' => $intents,
            ];
        }

        // Single intent routing
        if (isset(self::$handlers[$intent])) {
            $result = self::executeHandler($db, $intent, $input, $context, $conversationHistory);
            return $result ?: ['success' => false, 'type' => 'error', 'message' => 'Handler returned no result.'];
        }

        // Fallback to conversation
        if (isset(self::$handlers[AIIntentClassifier::INTENT_CONVERSATION])) {
            $result = self::executeHandler($db, AIIntentClassifier::INTENT_CONVERSATION, $input, $context, $conversationHistory);
            return $result ?: ['success' => false, 'type' => 'error', 'message' => 'No handler available.'];
        }

        return ['success' => false, 'type' => 'error', 'message' => 'No capability available for this request.'];
    }

    /**
     * Execute a handler safely.
     */
    private static function executeHandler(PDO $db, string $intent, string $input, array $context, array $history): ?array
    {
        try {
            $handler = self::$handlers[$intent];
            return $handler($db, $input, $context, $history);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'type' => 'error',
                'message' => 'Unable to process this request: ' . $e->getMessage(),
                'intent' => $intent,
            ];
        }
    }

    /**
     * Check if a handler is registered for an intent.
     */
    public static function hasHandler(string $intent): bool
    {
        return isset(self::$handlers[$intent]);
    }

    /**
     * Get all registered handlers.
     */
    public static function getHandlers(): array
    {
        return array_keys(self::$handlers);
    }

    // ═══════════════════════════════════════════════════════
    // BUILT-IN HANDLERS
    // ═══════════════════════════════════════════════════════

    /**
     * Register all default capability handlers.
     */
    public static function registerDefaults(): void
    {
        // Navigation (F7)
        self::register(AIIntentClassifier::INTENT_NAVIGATION, function(PDO $db, string $input, array $context, array $history) {
            return self::handleNavigation($db, $input, $context);
        });

        // Data Lookup (F8 tools)
        self::register(AIIntentClassifier::INTENT_DATA_LOOKUP, function(PDO $db, string $input, array $context, array $history) {
            return self::handleDataLookup($db, $input, $context);
        });

        // Knowledge Search (F11)
        self::register(AIIntentClassifier::INTENT_KNOWLEDGE_SEARCH, function(PDO $db, string $input, array $context, array $history) {
            return self::handleKnowledgeSearch($db, $input, $context);
        });

        // Action Request (F9)
        self::register(AIIntentClassifier::INTENT_ACTION_REQUEST, function(PDO $db, string $input, array $context, array $history) {
            return self::handleActionRequest($db, $input, $context);
        });

        // Task Request (F10)
        self::register(AIIntentClassifier::INTENT_TASK_REQUEST, function(PDO $db, string $input, array $context, array $history) {
            return self::handleTaskRequest($db, $input, $context);
        });

        // Automation Request (F13)
        self::register(AIIntentClassifier::INTENT_AUTOMATION_REQUEST, function(PDO $db, string $input, array $context, array $history) {
            return self::handleAutomationRequest($db, $input, $context);
        });

        // Agent Request (F12)
        self::register(AIIntentClassifier::INTENT_AGENT_REQUEST, function(PDO $db, string $input, array $context, array $history) {
            return self::handleAgentRequest($db, $input, $context);
        });

        // Insight Request (F14)
        self::register(AIIntentClassifier::INTENT_INSIGHT_REQUEST, function(PDO $db, string $input, array $context, array $history) {
            return self::handleInsightRequest($db, $input, $context);
        });

        // Analysis
        self::register(AIIntentClassifier::INTENT_ANALYSIS, function(PDO $db, string $input, array $context, array $history) {
            return self::handleAnalysis($db, $input, $context, $history);
        });

        // Question
        self::register(AIIntentClassifier::INTENT_QUESTION, function(PDO $db, string $input, array $context, array $history) {
            return self::handleQuestion($db, $input, $context, $history);
        });

        // Conversation (fallback)
        self::register(AIIntentClassifier::INTENT_CONVERSATION, function(PDO $db, string $input, array $context, array $history) {
            return self::handleConversation($db, $input, $context, $history);
        });
    }

    // ═══════════════════════════════════════════════════════
    // HANDLER IMPLEMENTATIONS
    // ═══════════════════════════════════════════════════════

    private static function handleNavigation(PDO $db, string $input, array $context): array
    {
        $result = AINavigationService::findNavigation($db, $input);
        if ($result && isset($result['route'])) {
            return [
                'success' => true,
                'type' => 'navigation',
                'title' => $result['label'] ?? 'Navigation',
                'message' => "Taking you to " . ($result['label'] ?? 'the requested page') . ".",
                'route' => function_exists('resolveAppUrl') ? resolveAppUrl($result['route']) : $result['route'],
                'label' => $result['label'] ?? 'Open',
                'source' => 'navigation',
            ];
        }

        // Try search for partial match
        $searchResult = AINavigationService::searchPages($db, $input);
        if (!empty($searchResult)) {
            $top = $searchResult[0];
            $alternatives = array_slice($searchResult, 1, 3);
            if (function_exists('resolveAppUrl')) {
                $alternatives = array_map(static function (array $alternative): array {
                    if (!empty($alternative['route'])) {
                        $alternative['route'] = resolveAppUrl($alternative['route']);
                    }
                    return $alternative;
                }, $alternatives);
            }
            return [
                'success' => true,
                'type' => 'navigation',
                'title' => 'Did you mean?',
                'message' => "I found a matching page: " . ($top['label'] ?? $input),
                'route' => function_exists('resolveAppUrl') ? resolveAppUrl($top['route'] ?? '') : ($top['route'] ?? ''),
                'label' => $top['label'] ?? 'Open',
                'alternatives' => $alternatives,
                'source' => 'navigation',
            ];
        }

        return [
            'success' => false,
            'type' => 'clarification',
            'message' => "I couldn't find a matching page for \"" . htmlspecialchars($input) . "\". Could you be more specific?",
        ];
    }

    private static function handleDataLookup(PDO $db, string $input, array $context): array
    {
        if (class_exists('AIIntegrationService') && preg_match('/\b(calendar|inventory|supplier import|webhook|external api)\b/i', $input)) {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
            $userPermissions = function_exists('getUserPermissions') ? getUserPermissions($userId) : [];
            $integration = AIIntegrationService::suggestCommandCapability($db, $userId, $companyId, $input, $userPermissions);
            if (is_array($integration)) {
                if (!empty($integration['success']) && empty($integration['requires_confirmation'])) {
                    return [
                        'success' => true,
                        'type' => 'external_data',
                        'title' => 'External Integration Result',
                        'message' => 'I used an approved external connector capability for this request.',
                        'data' => $integration['normalized'] ?? $integration['data'] ?? [],
                        'source' => 'integration',
                    ];
                }
                if (!empty($integration['requires_confirmation'])) {
                    return [
                        'success' => true,
                        'type' => 'pending_confirmation',
                        'title' => 'External Connector Confirmation',
                        'message' => $integration['message'] ?? 'This external capability needs confirmation.',
                        'preview' => $integration['preview'] ?? [],
                        'source' => 'integration',
                    ];
                }
                if (!empty($integration['error'])) {
                    return [
                        'success' => true,
                        'type' => 'answer',
                        'message' => $integration['error'] . (!empty($integration['suggestion']) ? ' ' . $integration['suggestion'] : ''),
                        'source' => 'integration',
                    ];
                }
            }
        }

        // Try to find a matching tool
        $tools = AIToolRegistry::getAll();
        $bestTool = null;
        $bestScore = 0;

        $inputLower = strtolower($input);
        foreach ($tools as $toolId => $tool) {
            $score = 0;
            $name = strtolower($tool['name'] ?? $toolId);
            $desc = strtolower($tool['description'] ?? '');

            // Check keyword matches
            if (str_contains($inputLower, 'requisition')) {
                if (str_contains($toolId, 'requisition') || str_contains($name, 'requisition')) $score += 3;
            }
            if (str_contains($inputLower, 'supplier') || str_contains($inputLower, 'vendor')) {
                if (str_contains($toolId, 'vendor') || str_contains($toolId, 'supplier')) $score += 3;
            }
            if (str_contains($inputLower, 'purchase order') || str_contains($inputLower, 'po')) {
                if (str_contains($toolId, 'purchase_order')) $score += 3;
            }
            if (str_contains($inputLower, 'knowledge')) {
                if (str_contains($toolId, 'knowledge')) $score += 3;
            }

            // General match
            if ($score === 0) {
                foreach (explode('_', $toolId) as $word) {
                    if (str_contains($inputLower, $word)) $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTool = $toolId;
            }
        }

        if ($bestTool && $bestScore > 0) {
            try {
                $toolResult = self::executeTool($db, $bestTool, [], $context);
                if (!empty($toolResult['success'])) {
                    $preview = array_filter(array_map('trim', explode("\n", (string) ($toolResult['result'] ?? ''))));
                    $count = count($preview);
                    $summary = "Found {$count} records.";
                    if ($count > 5) $summary .= " Showing first 5.";

                    $items = [];
                    foreach (array_slice($preview, 0, 5) as $item) {
                        $items[] = htmlspecialchars($item);
                    }

                    return [
                        'success' => true,
                        'type' => 'data_result',
                        'title' => ucwords(str_replace('_', ' ', $bestTool)),
                        'message' => $summary,
                        'items' => $items,
                        'count' => $count,
                        'tool_used' => $bestTool,
                        'source' => 'erp_data',
                    ];
                }
            } catch (\Throwable $e) {
                // Tool execution failed
            }
        }

        return [
            'success' => false,
            'type' => 'clarification',
            'message' => "I'm not sure which data you're looking for. Could you specify the type of record (e.g., requisitions, suppliers, purchase orders)?",
        ];
    }

    private static function handleKnowledgeSearch(PDO $db, string $input, array $context): array
    {
        $result = self::executeTool($db, 'knowledge.search', ['query' => $input], $context);
        if (!empty($result['success']) && !empty($result['result'])) {
            $rows = array_filter(array_map('trim', explode("\n", (string) $result['result'])));
            $items = [];
            foreach (array_slice($rows, 0, 3) as $row) {
                $items[] = ['title' => 'Knowledge', 'snippet' => htmlspecialchars($row)];
            }

            return [
                'success' => true,
                'type' => 'knowledge_result',
                'title' => 'Knowledge Base',
                'message' => "Found " . count($rows) . " relevant knowledge articles.",
                'items' => $items,
                'source' => 'knowledge',
            ];
        }

        return [
            'success' => true,
            'type' => 'answer',
            'message' => "I couldn't find relevant information in the company knowledge base for your query.",
            'source' => 'knowledge',
        ];
    }

    private static function handleActionRequest(PDO $db, string $input, array $context): array
    {
        // Parse action intent
        $actionType = 'unknown';
        $entity = '';

        if (preg_match('/\b(create|add|make|new)\b/i', $input, $m)) {
            $actionType = 'create';
        } elseif (preg_match('/\b(update|modify|change|edit)\b/i', $input, $m)) {
            $actionType = 'update';
        } elseif (preg_match('/\b(delete|remove|cancel)\b/i', $input, $m)) {
            $actionType = 'delete';
        } elseif (preg_match('/\b(approve|reject|submit)\b/i', $input, $m)) {
            $actionType = strtolower($m[0]);
        }

        if (preg_match('/\b(user|users|account|accounts)\b/i', $input)) $entity = 'user';
        elseif (preg_match('/\b(supplier|vendor)\b/i', $input)) $entity = 'supplier';
        elseif (preg_match('/\b(requisition|pr)\b/i', $input)) $entity = 'purchase_requisition';
        elseif (preg_match('/\b(quotation|quote|rfq)\b/i', $input)) $entity = 'quotation';
        elseif (preg_match('/\b(purchase order|po)\b/i', $input)) $entity = 'purchase_order';

        if ($entity && $actionType !== 'unknown') {
            $actionId = self::resolveActionIdForRequest($actionType, $entity);
            if ($actionId) {
                $userId = (int) ($context['user_id'] ?? $_SESSION['user_id'] ?? 0);
                $companyId = (int) ($context['company_id'] ?? $_SESSION['company_id'] ?? 1);
                $userPermissions = function_exists('getUserPermissions') ? getUserPermissions($userId) : [];
                $params = self::buildActionParameters($actionId, $input, $context, $userId, $companyId);

                if (class_exists('AIActionExecutor')) {
                    $executor = new AIActionExecutor($db, $userId, $userPermissions);
                    $proposal = $executor->propose($actionId, $params, (int) ($context['conversation_id'] ?? 0));

                    if (!empty($proposal['success'])) {
                        return [
                            'success' => true,
                            'type' => 'pending_confirmation',
                            'title' => $proposal['preview']['title'] ?? 'Action Required',
                            'message' => "I prepared {$proposal['action_name']}. Review the preview and confirm to execute it.",
                            'action_id' => $actionId,
                            'action_name' => $proposal['action_name'] ?? ($proposal['preview']['title'] ?? 'Pending Action'),
                            'pending_action_id' => $proposal['pending_action_id'] ?? null,
                            'confirmation_token' => $proposal['confirmation_token'] ?? null,
                            'preview' => $proposal['preview'] ?? null,
                            'action_type' => $actionType,
                            'entity' => $entity,
                            'requires_confirmation' => true,
                            'source' => 'action',
                        ];
                    }

                    $errorText = (string) ($proposal['error'] ?? 'I could not prepare that action.');
                    $navigation = [];
                    if ($actionId === 'users.create' && (str_contains(strtolower($errorText), 'required') || str_contains(strtolower($errorText), 'missing') || str_contains(strtolower($errorText), 'invalid input'))) {
                        if (class_exists('AINavigationService')) {
                            $navigation = AINavigationService::formatSuggestions([
                                [
                                    'destination_id' => 'users.create',
                                    'label' => 'Create User',
                                    'reason' => 'Open the Create User form to complete the name, email, and password.',
                                ],
                            ], $userPermissions);
                        }
                        return [
                            'success' => true,
                            'type' => 'clarification',
                            'message' => 'I can create the user, but I need the name, email, and password first. Open the Create User form to continue.',
                            'navigation' => $navigation,
                            'source' => 'action',
                        ];
                    }

                    return [
                        'success' => false,
                        'type' => 'clarification',
                        'message' => $errorText . ((str_contains(strtolower($errorText), 'required') || str_contains(strtolower($errorText), 'missing')) ? ' Please provide the missing details and try again.' : ''),
                        'source' => 'action',
                    ];
                }

                return [
                    'success' => true,
                    'type' => 'pending_confirmation',
                    'title' => 'Action Required',
                    'message' => "I can {$actionType} a {$entity} for you. This requires your confirmation before proceeding.",
                    'action_id' => $actionId,
                    'action_type' => $actionType,
                    'entity' => $entity,
                    'requires_confirmation' => true,
                    'source' => 'action',
                ];
            }
        }

        return [
            'success' => true,
            'type' => 'clarification',
            'message' => "I understand you want to perform an action. Could you specify what you'd like to do? For example:\n• Create a supplier\n• Submit a purchase requisition\n• Approve a pending order",
        ];
    }

    private static function resolveActionIdForRequest(string $actionType, string $entity): ?string
    {
        if ($actionType !== 'create') {
            return null;
        }

        return match ($entity) {
            'user' => 'users.create',
            'supplier' => 'procurement.create_supplier',
            'purchase_requisition' => 'procurement.create_purchase_requisition',
            'quotation' => 'procurement.create_quotation_draft',
            default => null,
        };
    }

    private static function buildActionParameters(string $actionId, string $input, array $context, int $userId, int $companyId): array
    {
        $today = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));
        $nextMonth = date('Y-m-d', strtotime('+30 days'));

        if ($actionId === 'procurement.create_supplier') {
            $name = preg_replace('/\b(create|add|make|new|supplier|vendor)\b/i', ' ', $input);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            return [
                'vendor_name' => $name !== '' ? $name : trim((string) ($context['record_name'] ?? '')),
                'notes' => $input,
            ];
        }

        if ($actionId === 'users.create') {
            $name = trim((string) ($context['record_name'] ?? $context['name'] ?? ''));
            if ($name === '') {
                $name = preg_replace('/\b(create|add|make|new|user|account|accounts)\b/i', ' ', $input);
                $name = trim(preg_replace('/\s+/', ' ', $name));
            }

            $email = trim((string) ($context['email'] ?? ''));
            if ($email === '' && preg_match('/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}\b/i', $input, $matches)) {
                $email = $matches[0];
            }

            $password = trim((string) ($context['password'] ?? $context['initial_password'] ?? ''));
            if ($password === '' && preg_match('/\b(?:password|passcode)\s*(?:is|=|:)?\s*([^\s,;]+)/i', $input, $matches)) {
                $password = trim((string) $matches[1], " \t\n\r\0\x0B\"'");
            }

            $status = 'active';
            if (preg_match('/\b(suspended|inactive|disabled)\b/i', $input, $matches)) {
                $status = strtolower($matches[1] === 'disabled' ? 'inactive' : $matches[1]);
                if ($status === 'disabled') {
                    $status = 'inactive';
                }
            }

            $roleNames = [];
            if (preg_match('/\bsuper\s*admin\b/i', $input)) {
                $roleNames[] = 'Super Admin';
            }
            if (preg_match('/\b(?:assign|with|as)\s+admin\b/i', $input) || preg_match('/\badmin\s+role\b/i', $input)) {
                $roleNames[] = 'Admin';
            }
            if (!empty($context['role_names']) && is_array($context['role_names'])) {
                $roleNames = array_merge($roleNames, array_map('strval', $context['role_names']));
            }
            if (!empty($context['roles']) && is_array($context['roles'])) {
                $roleNames = array_merge($roleNames, array_map('strval', $context['roles']));
            }

            return [
                'name'       => $name,
                'email'      => $email,
                'password'   => $password,
                'status'     => $status,
                'role_names' => array_values(array_unique(array_filter(array_map('trim', $roleNames)))),
                'notes'      => $input,
            ];
        }

        if ($actionId === 'procurement.create_purchase_requisition') {
            return [
                'requester_id' => $userId,
                'department_id' => (int) ($context['department_id'] ?? 0) ?: null,
                'request_date' => $today,
                'required_date' => $nextWeek,
                'priority' => preg_match('/\burgent\b/i', $input) ? 'urgent' : (preg_match('/\bhigh\b/i', $input) ? 'high' : 'normal'),
                'purpose' => trim($input),
                'description' => $input,
                'currency' => $context['currency'] ?? 'AED',
            ];
        }

        if ($actionId === 'procurement.create_quotation_draft') {
            $rfqId = (int) ($context['record_id'] ?? 0);
            $recordType = strtolower((string) ($context['record_type'] ?? ''));
            $page = strtolower((string) ($context['page'] ?? ''));

            if ($rfqId <= 0 && (str_contains($recordType, 'rfq') || str_contains($page, 'rfq'))) {
                $rfqId = (int) ($context['record_id'] ?? 0);
            }

            if ($rfqId <= 0 && preg_match('/\b(?:rfq|request for quotation|request for quote)\s*#?\s*(\d+)\b/i', $input, $matches)) {
                $rfqId = (int) $matches[1];
            }

            $vendorId = (int) ($context['vendor_id'] ?? 0);
            if ($vendorId <= 0 && preg_match('/\b(?:vendor|supplier)\s*#?\s*(\d+)\b/i', $input, $matches)) {
                $vendorId = (int) $matches[1];
            }

            return [
                'rfq_id' => $rfqId,
                'vendor_id' => $vendorId > 0 ? $vendorId : null,
                'quotation_date' => $today,
                'valid_until' => $nextMonth,
                'currency' => $context['currency'] ?? 'AED',
                'payment_terms' => $context['payment_terms'] ?? '',
                'delivery_terms' => $context['delivery_terms'] ?? '',
                'incoterms' => $context['incoterms'] ?? '',
                'warranty_terms' => $context['warranty_terms'] ?? '',
                'vendor_reference' => $context['vendor_reference'] ?? '',
                'lead_time_days' => isset($context['lead_time_days']) ? (int) $context['lead_time_days'] : null,
                'notes' => $input,
            ];
        }

        return [];
    }

    private static function handleTaskRequest(PDO $db, string $input, array $context): array
    {
        $userId = $context['user_id'] ?? $_SESSION['user_id'] ?? 0;

        // Extract task details
        $title = preg_replace('/\b(remind me to|create a task to|task to|set reminder)\b/i', '', $input);
        $title = trim($title);
        if (empty($title)) $title = $input;

        // Try to create task
        $result = AITaskService::createTask($db, $userId, $title, "Created from Command Center", null);

        if ($result['success']) {
            return [
                'success' => true,
                'type' => 'task_created',
                'title' => 'Task Created',
                'message' => "Task created: " . htmlspecialchars($title),
                'task_id' => $result['id'],
                'source' => 'task',
            ];
        }

        return [
            'success' => true,
            'type' => 'clarification',
            'message' => "I can help you create a task. Please provide the task description and optional due date.",
        ];
    }

    private static function handleAutomationRequest(PDO $db, string $input, array $context): array
    {
        $userId = $context['user_id'] ?? ($_SESSION['user_id'] ?? 0);
        if (!class_exists('AIWorkflowService') || !AIKillSwitchService::isEnabled($db, 'workflow_generation')) {
            return [
                'success' => true,
                'type' => 'answer',
                'title' => 'Automation',
                'message' => "Workflow draft generation is currently unavailable. Open Workflow Studio to build a draft manually.",
                'source' => 'automation',
            ];
        }

        if (preg_match('/\b(automate|workflow|notify|when|whenever|every|schedule|overdue)\b/i', $input)) {
            $draft = AIWorkflowService::generateDraft($db, $userId, $input, $context, true);
            if (!empty($draft['success'])) {
                return [
                    'success' => true,
                    'type' => 'navigation',
                    'title' => 'Workflow Draft Created',
                    'message' => "I created a draft workflow in Workflow Studio. Review, test, and approve it before enabling execution.",
                    'route' => function_exists('resolveAppUrl')
                        ? resolveAppUrl(url('modules/ai/workflows/index.php?workflow_id=' . (int) $draft['workflow_id']))
                        : url('modules/ai/workflows/index.php?workflow_id=' . (int) $draft['workflow_id']),
                    'label' => 'Open Workflow Studio',
                    'source' => 'automation',
                ];
            }
        }

        return [
            'success' => true,
            'type' => 'answer',
            'title' => 'Automation',
            'message' => "I can help you set up an automation. Describe the trigger and the outcome, or open Workflow Studio to build a draft visually.",
            'source' => 'automation',
        ];
    }

    private static function handleAgentRequest(PDO $db, string $input, array $context): array
    {
        $userId = $context['user_id'] ?? $_SESSION['user_id'] ?? 0;

        // Check if this is complex enough for agent delegation
        $complexity = self::assessComplexity($input);
        if ($complexity >= 3) {
            // Find suitable agent
            $agents = AIAgentRegistry::getAll();
            foreach ($agents as $agentId => $agent) {
                if (str_contains(strtolower($input), 'procurement') && str_contains($agentId, 'procurement')) {
                    return [
                        'success' => true,
                        'type' => 'agent_run',
                        'title' => 'Agent Delegation',
                        'message' => "This requires a multi-step review. Starting " . ($agent['name'] ?? 'Specialist') . "...",
                        'agent_id' => $agentId,
                        'source' => 'agent',
                    ];
                }
            }
        }

        return [
            'success' => true,
            'type' => 'answer',
            'message' => "I can help with that. Let me know if you'd like me to start a detailed review using an AI agent.",
            'source' => 'agent',
        ];
    }

    private static function handleInsightRequest(PDO $db, string $input, array $context): array
    {
        $userId = $context['user_id'] ?? $_SESSION['user_id'] ?? 0;
        $counts = AIInsightService::getUserInsightCounts($db, $userId);
        $recent = AIInsightService::getUserInsights($db, $userId, ['limit' => 3]);

        $items = [];
        foreach ($recent as $insight) {
            $sev = match($insight['severity'] ?? 'info') { 'critical' => '🔴', 'warning' => '⚠️', 'notice' => 'ℹ️', default => '💡' };
            $items[] = $sev . " " . htmlspecialchars($insight['title'] ?? '');
        }

        $summary = "You have {$counts['total']} active insight(s)";
        if ($counts['critical'] > 0) $summary .= ", including {$counts['critical']} critical";
        $summary .= ".";

        return [
            'success' => true,
            'type' => 'insight',
            'title' => 'AI Insights',
            'message' => $summary,
            'items' => $items,
            'counts' => $counts,
            'source' => 'insight',
        ];
    }

    private static function handleAnalysis(PDO $db, string $input, array $context, array $history): array
    {
        if (class_exists('AIProcessIntelligenceService') && self::isProcessIntelligenceInput($input)) {
            $service = new AIProcessIntelligenceService(
                $db,
                (int) ($context['company_id'] ?? ($_SESSION['company_id'] ?? 1)),
                (int) ($context['user_id'] ?? ($_SESSION['user_id'] ?? 0))
            );
            $result = $service->processRequest($input, $context);
            if (!empty($result['success'])) {
                return [
                    'success' => true,
                    'type' => 'process_intelligence',
                    'title' => 'Process Intelligence',
                    'message' => 'I routed this request to AI Process Intelligence.',
                    'data' => $result,
                    'route' => function_exists('resolveAppUrl')
                        ? resolveAppUrl($result['route'] ?? url('modules/ai/process-intelligence/index.php'))
                        : ($result['route'] ?? url('modules/ai/process-intelligence/index.php')),
                    'label' => 'Open Process Intelligence',
                    'source' => 'process_intelligence',
                ];
            }
            return [
                'success' => false,
                'type' => 'error',
                'message' => $result['error'] ?? 'Process intelligence request could not be completed.',
                'source' => 'process_intelligence',
            ];
        }

        // Try to combine data lookup + knowledge
        $dataResult = self::handleDataLookup($db, $input, $context);
        $knowledgeResult = self::handleKnowledgeSearch($db, $input, $context);

        $parts = [];
        if ($dataResult['success'] && isset($dataResult['message'])) {
            $parts[] = ['source' => 'ERP Data', 'content' => $dataResult['message']];
        }
        if ($knowledgeResult['success'] && isset($knowledgeResult['message']) && !str_contains($knowledgeResult['message'], "couldn't find")) {
            $parts[] = ['source' => 'Company Knowledge', 'content' => $knowledgeResult['message']];
        }

        if (!empty($parts)) {
            $message = "";
            foreach ($parts as $part) {
                $message .= "**{$part['source']}**\n{$part['content']}\n\n";
            }
            return [
                'success' => true,
                'type' => 'analysis',
                'title' => 'Analysis',
                'message' => trim($message),
                'parts' => $parts,
                'source' => 'analysis',
            ];
        }

        return [
            'success' => true,
            'type' => 'answer',
            'message' => "I can help analyze that. Could you be more specific about what aspect you'd like me to look into?",
        ];
    }

    private static function handleQuestion(PDO $db, string $input, array $context, array $history): array
    {
        if (class_exists('AIProcessIntelligenceService') && self::isProcessIntelligenceInput($input)) {
            $service = new AIProcessIntelligenceService(
                $db,
                (int) ($context['company_id'] ?? ($_SESSION['company_id'] ?? 1)),
                (int) ($context['user_id'] ?? ($_SESSION['user_id'] ?? 0))
            );
            $result = $service->processRequest($input, $context);
            if (!empty($result['success'])) {
                return [
                    'success' => true,
                    'type' => 'process_intelligence',
                    'title' => 'Process Intelligence',
                    'message' => 'I routed this question to AI Process Intelligence.',
                    'data' => $result,
                    'route' => function_exists('resolveAppUrl')
                        ? resolveAppUrl($result['route'] ?? url('modules/ai/process-intelligence/index.php'))
                        : ($result['route'] ?? url('modules/ai/process-intelligence/index.php')),
                    'label' => 'Open Process Intelligence',
                    'source' => 'process_intelligence',
                ];
            }
            return [
                'success' => false,
                'type' => 'error',
                'message' => $result['error'] ?? 'Process intelligence request could not be completed.',
                'source' => 'process_intelligence',
            ];
        }

        // First try knowledge search
        $knowledgeResult = self::handleKnowledgeSearch($db, $input, $context);
        if ($knowledgeResult['success'] && !str_contains($knowledgeResult['message'] ?? '', "couldn't find")) {
            return $knowledgeResult;
        }

        // Then try data lookup
        $dataResult = self::handleDataLookup($db, $input, $context);
        if ($dataResult['success']) {
            return $dataResult;
        }

        // Fallback to AI conversation
        return self::handleConversation($db, $input, $context, $history);
    }

    private function fetchCapabilities(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT capability_key, name, description, module, feature, intent_types, is_enabled,
                       requires_confirmation, requires_review, max_risk_level, priority, handler_class,
                       handler_method, metadata
                FROM ai_copilot_capabilities
                WHERE is_enabled = 1
                ORDER BY priority DESC, id ASC
            ");

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function isProcessIntelligenceInput(string $input): bool
    {
        return preg_match('/\b(process(?:es)?|bottleneck(?:s)?|approval\s+delay(?:s)?|cycle\s+time(?:s)?|sla|breach(?:es)?|rework|stuck|stalled|workflow\s+change|process\s+comparison|slow(?:er|down)?|delay(?:ed|s)?|taking\s+(?:so\s+)?long|too\s+long|queue(?:s|d)?|backlog(?:s)?|lag(?:ging)?|late(?:r)?)\b/i', $input) === 1;
    }

    private static function handleConversation(PDO $db, string $input, array $context, array $history): array
    {
        // Use AI provider for general conversation
        $provider = AIProviderService::getDefaultProvider($db);
        if (!$provider) {
            return [
                'success' => true,
                'type' => 'answer',
                'message' => "I can help you with ERP tasks. Try asking me to:\n• Search for records\n• Navigate to a page\n• Find company policies\n• Create a task\n• Check insights",
                'source' => 'system',
            ];
        }

        try {
            $systemPrompt = class_exists('AIConversationService')
                ? AIConversationService::SYSTEM_PROMPT
                : "You are an ERP AI assistant. Answer accurately, use ERP context when available, and prefer concrete next steps over generic chatbot language.";

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach (array_slice($history, -8) as $msg) {
                $role = in_array(($msg['role'] ?? 'user'), ['system', 'assistant', 'user', 'tool'], true) ? $msg['role'] : 'user';
                $content = trim((string) ($msg['content'] ?? ''));
                if ($content === '') {
                    continue;
                }
                $messages[] = ['role' => $role, 'content' => $content];
            }
            $messages[] = ['role' => 'user', 'content' => $input];

            $response = AIProviderService::chat($db, $provider['id'] ?? 0, $input, [
                'messages' => $messages,
                'system_prompt' => $systemPrompt,
                'max_tokens' => 500,
                'temperature' => 0.45,
            ]);

            return [
                'success' => true,
                'type' => 'answer',
                'message' => $response ?: "I'm here to help with ERP tasks. What would you like to do?",
                'source' => 'ai',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => true,
                'type' => 'answer',
                'message' => "I can help you with ERP tasks. Try asking me to search for records, navigate to pages, or find information.",
                'source' => 'system',
            ];
        }
    }

    /**
     * Assess request complexity (1-5 scale).
     */
    private static function assessComplexity(string $input): int
    {
        $complexity = 1;
        if (str_contains($input, ' and ') || str_contains($input, ' then ')) $complexity++;
        if (str_contains($input, ' all ') || str_contains($input, ' entire ') || str_contains($input, ' comprehensive ')) $complexity++;
        if (preg_match('/\b(analyze|review|investigate|compare)\b/i', $input)) $complexity++;
        if (preg_match('/\b(every|all|entire|complete|full)\b/i', $input)) $complexity++;
        return min(5, $complexity);
    }

    private static function executeTool(PDO $db, string $toolId, array $params, array $context): array
    {
        $userId = (int) ($context['user_id'] ?? ($_SESSION['user_id'] ?? 0));
        $permissions = $context['permissions'] ?? (function_exists('getUserPermissions') && $userId > 0 ? getUserPermissions($userId) : []);
        $executor = new AIToolExecutor($db, $userId, is_array($permissions) ? $permissions : []);
        return $executor->execute($toolId, $params, (int) ($context['conversation_id'] ?? 0));
    }
}
