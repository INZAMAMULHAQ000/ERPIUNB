<?php
/**
 * AI F29 — Capability Router.
 *
 * Centralized registry and routing for all AI capabilities.
 * Maps intents to registered capabilities with priority, permissions, and risk assessment.
 *
 * Covers Section 4: Capability Routing.
 */
class AICapabilityRouter
{
    private $db;
    private $companyId;
    private $userId;
    private static $capabilities = [];
    private static $routes = [];

    public function __construct(PDO $db, int $companyId, int $userId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Load all enabled capabilities from the database.
     */
    public function loadCapabilities(): void
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_copilot_capabilities
            WHERE is_enabled = 1
            ORDER BY priority DESC
        ");
        $stmt->execute();
        self::$capabilities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Also include built-in capabilities that aren't in the DB
        $builtin = $this->getBuiltinCapabilities();
        self::$capabilities = array_merge(self::$capabilities, $builtin);
    }

    /**
     * Get built-in capabilities not stored in DB.
     */
    private function getBuiltinCapabilities(): array
    {
        return [
            [
                'capability_key' => 'copilot_chat',
                'name' => 'AI Copilot Chat',
                'module' => 'ai',
                'feature' => 'F29',
                'intent_types' => ['question', 'explanation', 'analysis', 'recommendation', 'search', 'do_it'],
                'priority' => 80,
                'requires_confirmation' => false,
                'max_risk_level' => 'low',
            ],
            [
                'capability_key' => 'copilot_conversational',
                'name' => 'Conversational Follow-up',
                'module' => 'ai',
                'feature' => 'F29',
                'intent_types' => ['follow_up', 'clarification', 'do_it'],
                'priority' => 70,
                'requires_confirmation' => false,
                'max_risk_level' => 'low',
            ],
        ];
    }

    /**
     * Route an intent to the best matching capabilities.
     *
     * @param string $intentType The classified intent
     * @param array $parameters Intent parameters
     * @param array $context Page/record context
     * @return array Routing result with selected capability and execution plan
     */
    public function route(string $intentType, array $parameters = [], array $context = []): array
    {
        $candidates = [];

        foreach (self::$capabilities as $cap) {
            $intentTypes = $cap['intent_types'] ?? [];
            if (is_string($intentTypes)) {
                $intentTypes = json_decode($intentTypes, true) ?: [];
            }
            if (in_array($intentType, $intentTypes)) {
                // Check permissions
                if (!$this->hasCapabilityPermission($cap)) {
                    continue;
                }

                // Check risk level
                if (isset($parameters['risk_level'])) {
                    $maxRisk = $cap['max_risk_level'] ?? 'medium';
                    if (!$this->isRiskAcceptable($parameters['risk_level'], $maxRisk)) {
                        continue;
                    }
                }

                $candidates[] = $cap;
            }
        }

        if (empty($candidates)) {
            return [
                'success' => false,
                'error' => 'No capability registered for intent: ' . $intentType,
                'intent' => $intentType,
            ];
        }

        // Sort by priority (highest first)
        usort($candidates, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        $selected = $candidates[0];

        // For multi-capability intents, build a chain
        $chain = [];
        foreach ($candidates as $candidate) {
            $chain[] = [
                'capability_key' => $candidate['capability_key'],
                'name' => $candidate['name'],
                'module' => $candidate['module'],
                'feature' => $candidate['feature'],
                'requires_confirmation' => !empty($candidate['requires_confirmation']),
                'requires_review' => !empty($candidate['requires_review']),
            ];
        }

        return [
            'success' => true,
            'selected' => $selected['capability_key'],
            'capability' => $selected,
            'chain' => $chain,
            'requires_confirmation' => !empty($selected['requires_confirmation']),
            'requires_review' => !empty($selected['requires_review']),
        ];
    }

    /**
     * Route to the best ERP navigation destination.
     *
     * @param string $text User navigation request
     * @return array Navigation destination with URL and validation
     */
    public function routeNavigation(string $text): array
    {
        $lower = strtolower(trim($text));

        // Load routes from DB
        if (empty(self::$routes)) {
            $stmt = $this->db->prepare("
                SELECT * FROM ai_copilot_routes WHERE is_active = 1
            ");
            $stmt->execute();
            self::$routes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach (self::$routes as $route) {
            $keywords = json_decode($route['nav_keywords'] ?? '[]', true) ?: [];
            $score = 0;

            // Check keyword matches
            foreach ($keywords as $keyword) {
                if (strpos($lower, strtolower($keyword)) !== false) {
                    $score += 2;
                }
            }

            // Check route name match
            if (strpos($lower, strtolower($route['page_name'])) !== false) {
                $score += 3;
            }

            // Check module match
            if (strpos($lower, strtolower($route['module'])) !== false) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $route;
            }
        }

        if ($bestMatch && $bestScore >= 2) {
            // Check permission
            if ($bestMatch['permission_required'] && !$this->hasPermission($bestMatch['permission_required'])) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to access ' . $bestMatch['page_name'],
                    'route' => $bestMatch,
                ];
            }

            return [
                'success' => true,
                'route_key' => $bestMatch['route_key'],
                'url' => $bestMatch['route_path'],
                'module' => $bestMatch['module'],
                'page_name' => $bestMatch['page_name'],
                'description' => $bestMatch['description'],
            ];
        }

        return [
            'success' => false,
            'error' => 'Could not determine the navigation destination',
            'suggestion' => 'Try saying "Open [page name]" or "Go to [module]"',
        ];
    }

    /**
     * Get all available capabilities for the current user.
     */
    public function getAvailableCapabilities(): array
    {
        $available = [];
        foreach (self::$capabilities as $cap) {
            if ($this->hasCapabilityPermission($cap)) {
                $available[] = [
                    'capability_key' => $cap['capability_key'],
                    'name' => $cap['name'],
                    'description' => $cap['description'] ?? '',
                    'module' => $cap['module'],
                    'feature' => $cap['feature'],
                    'intent_types' => json_decode($cap['intent_types'] ?? '[]', true) ?: [],
                ];
            }
        }
        return $available;
    }

    /**
     * Get quick command suggestions based on available capabilities.
     */
    public function getQuickCommands(): array
    {
        $commands = [
            ['text' => "What's important today?", 'category' => 'intelligence'],
            ['text' => 'Show overdue items', 'category' => 'analysis'],
            ['text' => 'Analyze procurement', 'category' => 'analysis'],
            ['text' => 'Find unusual transactions', 'category' => 'intelligence'],
            ['text' => 'Summarize my tasks', 'category' => 'productivity'],
            ['text' => 'Review recent documents', 'category' => 'documents'],
            ['text' => 'What needs my attention?', 'category' => 'intelligence'],
            ['text' => 'Open the dashboard', 'category' => 'navigation'],
            ['text' => 'Search for suppliers', 'category' => 'search'],
            ['text' => 'Check for policy violations', 'category' => 'governance'],
        ];

        // Filter by available capabilities
        $available = $this->getAvailableCapabilities();
        $availableIntents = [];
        foreach ($available as $cap) {
            $availableIntents = array_merge($availableIntents, $cap['intent_types']);
        }
        $availableIntents = array_unique($availableIntents);

        return $commands; // All commands are available as suggestions
    }

    /**
     * Check if user has permission for a capability.
     */
    private function hasCapabilityPermission(array $cap): bool
    {
        $module = $cap['module'] ?? '';
        $feature = $cap['feature'] ?? '';

        // Map feature to permission
        $permMap = [
            'F9' => 'erp.actions.use',
            'F11' => 'ai.knowledge.view',
            'F13' => 'ai.scheduler.view',
            'F16' => 'ai.management.view',
            'F18' => 'ai.workflows.view',
            'F22' => 'ai.integrations.use',
            'F23' => 'ai.observability.view',
            'F24' => 'ai.governance.view',
            'F25' => 'ai.agents.view',
            'F26' => 'ai.intelligence.view',
            'F27' => 'ai.documents.view',
            'F28' => 'ai.voice.use',
            'F29' => 'ai.copilot.use',
        ];

        $perm = $permMap[$feature] ?? null;
        if ($perm && function_exists('hasPermission')) {
            return hasPermission($perm);
        }

        return true; // Default to allowed if no permission check available
    }

    /**
     * Check if a risk level is acceptable given max allowed.
     */
    private function isRiskAcceptable(string $actual, string $max): bool
    {
        $levels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        return ($levels[$actual] ?? 1) <= ($levels[$max] ?? 4);
    }

    /**
     * Check if user has a specific permission.
     */
    private function hasPermission(string $permission): bool
    {
        if (function_exists('hasPermission')) {
            return hasPermission($permission);
        }
        return true;
    }

    /**
     * Register a dynamic capability at runtime.
     */
    public static function registerCapability(array $capability): void
    {
        self::$capabilities[] = $capability;
    }
}
