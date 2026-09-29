<?php
/**
 * AI F15 — Intent Classification Engine.
 *
 * Classifies user input into intent categories for routing.
 * Uses keyword/pattern matching with controlled fallback.
 */
class AIIntentClassifier
{
    const INTENT_CONVERSATION = 'conversation';
    const INTENT_QUESTION = 'question';
    const INTENT_NAVIGATION = 'navigation';
    const INTENT_DATA_LOOKUP = 'data_lookup';
    const INTENT_ANALYSIS = 'analysis';
    const INTENT_KNOWLEDGE_SEARCH = 'knowledge_search';
    const INTENT_TASK_REQUEST = 'task_request';
    const INTENT_ACTION_REQUEST = 'action_request';
    const INTENT_AUTOMATION_REQUEST = 'automation_request';
    const INTENT_AGENT_REQUEST = 'agent_request';
    const INTENT_INSIGHT_REQUEST = 'insight_request';
    const INTENT_MULTI_INTENT = 'multi_intent';
    const INTENT_UNKNOWN = 'unknown';

    // Intent detection patterns (ordered by priority)
    private static array $patterns = [
        self::INTENT_NAVIGATION => [
            'patterns' => [
                '/\b(take me to|go to|navigate to|jump to)\b/i',
                '/^\b(open|show me the|show my)\b/i',
            ],
            'keywords' => ['navigate', 'go to', 'take me', 'jump to'],
            'weight' => 1.5,
        ],
        self::INTENT_DATA_LOOKUP => [
            'patterns' => [
                '/\b(show me|find|search for|look up|list|get me|display)\b/i',
                '/\bhow many|count|total|what are the|which ones\b/i',
            ],
            'keywords' => ['find', 'search', 'show', 'list', 'get', 'display', 'look up', 'how many', 'count', 'vendors', 'suppliers', 'requisitions', 'orders', 'invoices'],
            'weight' => 1.3,
        ],
        self::INTENT_KNOWLEDGE_SEARCH => [
            'patterns' => [
                '/\b(what does|what is|what are|explain|tell me about|what does our|company policy|approval limit|procedure|guideline)\b/i',
                '/\b(policy|procedure|guideline|compliance|regulation|rule|standard)\b/i',
            ],
            'keywords' => ['policy', 'procedure', 'guideline', 'what does', 'explain', 'tell me about'],
            'weight' => 1.2,
        ],
        self::INTENT_ACTION_REQUEST => [
            'patterns' => [
                '/\b(create|add|make|new|submit|send|approve|reject|delete|remove|update|modify|change|set)\b/i',
                '/\b(supplier|requisition|quotation|quote|rfq|order|invoice|payment|employee|user|task|record)\b/i',
            ],
            'keywords' => ['create', 'add', 'make', 'new', 'submit', 'approve', 'reject', 'delete', 'update', 'quotation', 'quote', 'rfq'],
            'weight' => 1.4,
        ],
        self::INTENT_TASK_REQUEST => [
            'patterns' => [
                '/\b(remind me|create a task|task to|set reminder|todo|to-do|follow up)\b/i',
            ],
            'keywords' => ['remind', 'task', 'schedule', 'reminder', 'todo', 'follow up'],
            'weight' => 1.3,
        ],
        self::INTENT_AUTOMATION_REQUEST => [
            'patterns' => [
                '/\b(automate|workflow|workflow studio|whenever|on schedule|recurring|repeatedly)\b/i',
                '/\b(every day|every week|every monday|each morning|daily at|weekly summary)\b/i',
                '/\bevery\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday|day|week|month)\b/i',
            ],
            'keywords' => ['automate', 'workflow', 'workflow studio', 'recurring', 'every day', 'every week', 'every monday', 'weekly'],
            'weight' => 1.4,
        ],
        self::INTENT_AGENT_REQUEST => [
            'patterns' => [
                '/\b(review|analyze|investigate|check all|examine|audit|assess|evaluate)\b.*\b(all|entire|complete|full|whole|backlog)\b/i',
                '/\b(agent|specialist|deep dive|comprehensive|thorough)\b/i',
            ],
            'keywords' => ['review all', 'analyze all', 'investigate', 'agent', 'specialist', 'backlog', 'entire'],
            'weight' => 1.1,
        ],
        self::INTENT_INSIGHT_REQUEST => [
            'patterns' => [
                '/\b(what needs|what requires|attention|problems|issues|alerts|insights|what is wrong|what is happening)\b/i',
                '/\b(show me.*problems|any issues|what needs my attention|proactive)\b/i',
            ],
            'keywords' => ['attention', 'problems', 'issues', 'insights', 'alerts', 'what needs'],
            'weight' => 1.1,
        ],
        self::INTENT_ANALYSIS => [
            'patterns' => [
                '/\b(analyze|summarize|summary|explain why|why are|compare|trend|pattern|reason|cause)\b/i',
                '/\b(why is|why are|what caused|how come|reason for|analysis of)\b/i',
                '/\bso many|so much|too many|too much|problem|issue|delayed|delay\b/i',
            ],
            'keywords' => ['analyze', 'summarize', 'explain why', 'why', 'compare', 'trend', 'so many', 'problem', 'delayed'],
            'weight' => 1.0,
        ],
        self::INTENT_QUESTION => [
            'patterns' => [
                '/^(who|what|when|where|which|how|is|are|was|were|do|does|can|could|would|should|will)\b/i',
            ],
            'keywords' => ['who', 'what', 'when', 'where', 'which', 'how many', 'is', 'are', 'can', 'does'],
            'weight' => 0.9,
        ],
    ];

    /**
     * Classify user input into intent(s).
     */
    public static function classify(string $input): array
    {
        $input = trim($input);
        if (empty($input)) {
            return ['primary' => self::INTENT_UNKNOWN, 'intents' => [], 'confidence' => 0];
        }

        $scores = [];

        foreach (self::$patterns as $intent => $config) {
            $score = 0;

            // Check regex patterns
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $input)) {
                    $score += 2;
                    break;
                }
            }

            // Check keyword matches
            $inputLower = strtolower($input);
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($inputLower, strtolower($keyword))) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $scores[$intent] = $score * ($config['weight'] ?? 1.0);
            }
        }

        if (empty($scores)) {
            // Default to conversation or question
            $hasQuestionMark = str_ends_with($input, '?');
            $primary = $hasQuestionMark ? self::INTENT_QUESTION : self::INTENT_CONVERSATION;
            return ['primary' => $primary, 'intents' => [$primary], 'confidence' => 0.3];
        }

        // Sort by score
        arsort($scores);
        $topScore = reset($scores);
        $topIntent = array_key_first($scores);

        // Check for multi-intent
        $threshold = $topScore * 0.8;
        $matchedIntents = [];
        foreach ($scores as $intent => $score) {
            if ($score >= $threshold) {
                $matchedIntents[] = $intent;
            }
        }

        $primary = $matchedIntents[0] ?? $topIntent;
        $confidence = min(1.0, $topScore / 5.0);

        // Detect multi-intent
        if (count($matchedIntents) > 1) {
            $primary = self::INTENT_MULTI_INTENT;
        }

        return [
            'primary' => $primary,
            'intents' => $matchedIntents,
            'confidence' => round($confidence, 2),
            'scores' => $scores,
        ];
    }

    /**
     * Get intent description for display.
     */
    public static function describe(string $intent): string
    {
        return match($intent) {
            self::INTENT_CONVERSATION => 'General conversation',
            self::INTENT_QUESTION => 'Question',
            self::INTENT_NAVIGATION => 'Navigation',
            self::INTENT_DATA_LOOKUP => 'Data lookup',
            self::INTENT_ANALYSIS => 'Analysis',
            self::INTENT_KNOWLEDGE_SEARCH => 'Knowledge search',
            self::INTENT_TASK_REQUEST => 'Task request',
            self::INTENT_ACTION_REQUEST => 'Action request',
            self::INTENT_AUTOMATION_REQUEST => 'Automation request',
            self::INTENT_AGENT_REQUEST => 'Agent request',
            self::INTENT_INSIGHT_REQUEST => 'Insight request',
            self::INTENT_MULTI_INTENT => 'Multi-part request',
            self::INTENT_UNKNOWN => 'Unknown',
            default => 'Unknown',
        };
    }

    /**
     * Get all valid intent types.
     */
    public static function getIntents(): array
    {
        return [
            self::INTENT_CONVERSATION,
            self::INTENT_QUESTION,
            self::INTENT_NAVIGATION,
            self::INTENT_DATA_LOOKUP,
            self::INTENT_ANALYSIS,
            self::INTENT_KNOWLEDGE_SEARCH,
            self::INTENT_TASK_REQUEST,
            self::INTENT_ACTION_REQUEST,
            self::INTENT_AUTOMATION_REQUEST,
            self::INTENT_AGENT_REQUEST,
            self::INTENT_INSIGHT_REQUEST,
            self::INTENT_MULTI_INTENT,
            self::INTENT_UNKNOWN,
        ];
    }
}
