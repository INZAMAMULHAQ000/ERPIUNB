<?php
/**
 * AI F32 — Communication Drafting Engine
 * 
 * Grounded drafting with verified ERP facts, source evidence, suggested replies.
 * Sections 4-7 of F32 specification.
 */

class AICommunicationDraftService {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Create an AI-drafted communication with grounded facts and source evidence.
     *
     * @param array $params Request parameters
     * @return array Draft with body, sources, verified_facts, suggestions
     */
    public function createDraft($params) {
        $contextType  = $params['context_type'] ?? 'manual';
        $contextId    = $params['context_id'] ?? null;
        $commType     = $params['communication_type'] ?? 'email';
        $tone         = $params['tone'] ?? 'professional';
        $formality    = $params['formality'] ?? 'standard';
        $language     = $params['language'] ?? 'en';
        $length       = $params['length'] ?? 'standard';
        $subject      = $params['subject'] ?? null;
        $recipient    = $params['recipient'] ?? null;
        $instructions = $params['instructions'] ?? '';
        $existingBody = $params['existing_body'] ?? null;

        // 1. Build authorized context
        $contextService = new AICommunicationContextService($this->companyId, $this->userId);
        $context = $contextService->buildContext($contextType, $contextId, [
            'max_history' => 20,
            'include_documents' => true,
        ]);

        // 2. Extract verified ERP facts from context
        $verifiedFacts = $this->extractVerifiedFacts($context);

        // 3. Build the AI prompt with grounding instructions
        $prompt = $this->buildDraftingPrompt($params, $context, $verifiedFacts);

        // 4. Call AI provider
        $aiResult = $this->callAIProvider($prompt, $params);

        // 5. Parse and validate the response
        $draft = $this->parseDraftResponse($aiResult, $verifiedFacts);

        // 6. Generate source references
        $sources = $this->buildSourceReferences($context, $verifiedFacts);

        // 7. Identify placeholders for missing information
        $placeholders = $this->detectPlaceholders($draft['body'], $verifiedFacts);

        // 8. Calculate risk level
        $riskLevel = $this->assessRiskLevel($params, $draft, $recipient);

        // 9. Store the draft
        $draftId = $this->storeDraft([
            'context_type'       => $contextType,
            'context_id'         => $contextId,
            'communication_type' => $commType,
            'subject'            => $subject ?: ($draft['subject'] ?? null),
            'recipient'          => $recipient,
            'tone'               => $tone,
            'formality'          => $formality,
            'language'           => $language,
            'length'             => $length,
            'body'               => $draft['body'],
            'verified_facts'     => $verifiedFacts,
            'source_references'  => $sources,
            'placeholders'       => $placeholders,
            'provider'           => $aiResult['provider'] ?? null,
            'model'              => $aiResult['model'] ?? null,
            'tokens_used'        => $aiResult['tokens_used'] ?? 0,
            'cost_usd'           => $aiResult['cost_usd'] ?? 0,
            'risk_level'         => $riskLevel,
            'approval_required'  => in_array($riskLevel, ['high', 'critical']),
        ]);

        // 10. Record F23 observability event
        $this->recordEvent('draft_created', $draftId, [
            'context_type' => $contextType,
            'communication_type' => $commType,
            'tone' => $tone,
            'tokens_used' => $aiResult['tokens_used'] ?? 0,
            'risk_level' => $riskLevel,
        ]);

        return [
            'draft_id'         => $draftId,
            'subject'          => $subject ?: ($draft['subject'] ?? null),
            'body'             => $draft['body'],
            'verified_facts'   => $verifiedFacts,
            'source_references'=> $sources,
            'placeholders'     => $placeholders,
            'risk_level'       => $riskLevel,
            'approval_required'=> in_array($riskLevel, ['high', 'critical']),
            'tone'             => $tone,
            'suggestions'      => $this->generateSuggestions($draft['body'], $tone, $params),
        ];
    }

    /**
     * Generate suggested replies for an incoming communication.
     *
     * @param array $params Request with context and message info
     * @return array Multiple reply suggestions
     */
    public function generateSuggestions($body, $tone, $params = []) {
        $contextType = $params['context_type'] ?? 'manual';
        $contextId   = $params['context_id'] ?? null;

        $suggestions = [];
        $suggestionTypes = [
            'concise'    => 'A brief, to-the-point reply',
            'detailed'   => 'A thorough, comprehensive reply',
            'polite_decline' => 'A professional, polite decline',
            'clarification'  => 'Request clarification on missing details',
            'escalation'     => 'Escalate to a supervisor or specialist',
        ];

        foreach ($suggestionTypes as $type => $description) {
            $prompt = "Generate a {$type} reply to this message: \"{$body}\"\n";
            $prompt .= "Tone: {$tone}\n";
            $prompt .= "Type: {$description}\n";
            $prompt .= "Keep it professional and business-appropriate.\n";

            $aiResult = $this->callAIProvider(['prompt' => $prompt], $params);
            if (!empty($aiResult['text'])) {
                $suggestions[] = [
                    'type'        => $type,
                    'description' => $description,
                    'text'        => $this->sanitizeOutput($aiResult['text']),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Regenerate a draft with different tone or instructions.
     */
    public function regenerateDraft($draftId, $newParams) {
        $draft = $this->getDraft($draftId);
        if (!$draft) {
            return ['error' => 'Draft not found'];
        }

        return $this->createDraft(array_merge([
            'context_type'       => $draft['context_type'],
            'context_id'         => $draft['context_id'],
            'communication_type' => $draft['communication_type'],
            'subject'            => $draft['subject'],
            'recipient'          => $draft['recipient'],
            'existing_body'      => $draft['body'],
        ], $newParams));
    }

    /**
     * Apply a suggested reply to a draft.
     */
    public function applySuggestion($draftId, $suggestionText) {
        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        $stmt = $db->prepare("UPDATE ai_communication_drafts SET body = ?, status = 'editing', updated_at = NOW() WHERE id = ? AND company_id = ?");
        if (!$stmt) return ['error' => 'Query failed'];

        $stmt->bind_param('ssi', $suggestionText, $draftId, $this->companyId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $this->recordEvent('draft_edited', $draftId, ['edit_type' => 'suggestion_applied']);
            return ['success' => true, 'draft_id' => $draftId];
        }

        return ['error' => 'Draft not found or not editable'];
    }

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    /**
     * Extract verified ERP facts from context for grounding.
     */
    private function extractVerifiedFacts($context) {
        $facts = [];
        $sections = $context['sections'] ?? [];

        if (!empty($sections['primary'])) {
            $primary = $sections['primary'];
            foreach ($primary as $key => $value) {
                if (in_array(strtolower($key), ['id', 'name', 'email', 'phone', 'address', 'invoice_number', 'po_number', 'quotation_number', 'order_number', 'total_amount', 'tax_amount', 'discount_amount', 'due_date', 'delivery_date', 'status', 'currency', 'reference', 'notes'])) {
                    $facts[$key] = $value;
                }
            }
        }

        if (!empty($sections['financial'])) {
            foreach ($sections['financial'] as $key => $value) {
                if ($value !== null && $value !== '' && $value !== '0') {
                    $facts['financial_' . $key] = $value;
                }
            }
        }

        return $facts;
    }

    /**
     * Build the AI prompt for grounded drafting.
     */
    private function buildDraftingPrompt($params, $context, $verifiedFacts) {
        $commType = $params['communication_type'] ?? 'email';
        $tone = $params['tone'] ?? 'professional';
        $formality = $params['formality'] ?? 'standard';
        $language = $params['language'] ?? 'en';
        $length = $params['length'] ?? 'standard';
        $instructions = $params['instructions'] ?? '';
        $existingBody = $params['existing_body'] ?? null;

        $prompt = "You are an AI business communication assistant.\n\n";
        $prompt .= "TASK: Generate a {$commType} communication.\n\n";

        // Grounding rules — CRITICAL
        $prompt .= "GROUNDING RULES (MUST FOLLOW):\n";
        $prompt .= "- ONLY use facts provided in the VERIFIED ERP FACTS section below.\n";
        $prompt .= "- NEVER invent prices, dates, payment status, delivery dates, amounts, commitments, or business facts.\n";
        $prompt .= "- If information is missing, use [PLACEHOLDER: description] and explain what is needed.\n";
        $prompt .= "- Distinguish between verified facts and AI-generated wording.\n";
        $prompt .= "- Never fabricate business commitments or contractual obligations.\n\n";

        // Tone and style
        $prompt .= "STYLE: Tone={$tone}, Formality={$formality}, Language={$language}, Length={$length}\n\n";

        // Verified facts
        if (!empty($verifiedFacts)) {
            $prompt .= "VERIFIED ERP FACTS:\n";
            foreach ($verifiedFacts as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
            $prompt .= "\n";
        }

        // Context summary
        if (!empty($context['sections']['history'])) {
            $count = count($context['sections']['history']);
            $prompt .= "COMMUNICATION HISTORY: {$count} previous messages in this thread.\n\n";
        }

        // User instructions
        if ($instructions) {
            // Sanitize instructions against injection
            $instructions = $this->sanitizeInput($instructions);
            $prompt .= "USER INSTRUCTIONS: {$instructions}\n\n";
        }

        // Rewrite mode
        if ($existingBody) {
            $prompt .= "REWRITE the following message with the specified tone:\n\"{$existingBody}\"\n\n";
        }

        $prompt .= "Generate the communication now. Start with a subject line if applicable, then the body.\n";

        return $prompt;
    }

    /**
     * Call the AI provider for text generation.
     */
    private function callAIProvider($promptOrParams, $options = []) {
        $prompt = is_array($promptOrParams) ? ($promptOrParams['prompt'] ?? '') : $promptOrParams;

        // Try to use existing AI infrastructure
        if (class_exists('AIProviderService')) {
            $providerService = new AIProviderService();
            if (method_exists($providerService, 'complete')) {
                $result = $providerService->complete($prompt, $options);
                if ($result) {
                    return [
                        'text'        => $result['text'] ?? '',
                        'provider'    => $result['provider'] ?? 'unknown',
                        'model'       => $result['model'] ?? 'unknown',
                        'tokens_used' => $result['tokens_used'] ?? 0,
                        'cost_usd'    => $result['cost_usd'] ?? 0,
                    ];
                }
            }
        }

        // Fallback: return a placeholder for demo/development
        return [
            'text'        => "[AI provider not configured. Draft would be generated here based on the provided context and instructions.]",
            'provider'    => 'none',
            'model'       => 'none',
            'tokens_used' => 0,
            'cost_usd'    => 0,
        ];
    }

    /**
     * Parse the AI response into structured draft.
     */
    private function parseDraftResponse($aiResult, $verifiedFacts) {
        $text = $aiResult['text'] ?? '';

        // Extract subject if present (first line starting with "Subject:")
        $subject = null;
        if (preg_match('/^Subject:\s*(.+)$/mi', $text, $m)) {
            $subject = trim($m[1]);
            $text = str_replace($m[0], '', $text);
        }

        return [
            'subject' => $subject,
            'body'    => $this->sanitizeOutput(trim($text)),
        ];
    }

    /**
     * Build source references for the draft.
     */
    private function buildSourceReferences($context, $verifiedFacts) {
        $sources = $context['sources'] ?? [];

        // Add verified fact sources
        foreach ($verifiedFacts as $key => $value) {
            $sources[] = [
                'type'  => 'erp_fact',
                'field' => $key,
                'value' => $value,
            ];
        }

        return $sources;
    }

    /**
     * Detect placeholders for missing information.
     */
    private function detectPlaceholders($body, $verifiedFacts) {
        $placeholders = [];

        // Find explicit placeholders
        if (preg_match_all('/\[PLACEHOLDER:\s*(.+?)\]/', $body, $matches)) {
            foreach ($matches[1] as $match) {
                $placeholders[] = trim($match);
            }
        }

        // Check for common missing info patterns
        $expectedFields = ['due_date', 'delivery_date', 'total_amount', 'amount'];
        foreach ($expectedFields as $field) {
            if (empty($verifiedFacts[$field]) && stripos($body, $field) !== false) {
                $placeholders[] = "Missing {$field} in ERP data";
            }
        }

        return $placeholders;
    }

    /**
     * Assess risk level of the communication.
     */
    private function assessRiskLevel($params, $draft, $recipient) {
        $risk = 'low';

        $body = strtolower($draft['body'] ?? '');
        $subject = strtolower($params['subject'] ?? '');

        // Financial commitments
        if (preg_match('/\b(commit|promise|guarantee|warranty|refund|payment\s+will|will\s+pay)\b/', $body)) {
            $risk = 'high';
        }

        // Contractual language
        if (preg_match('/\b(contract|agreement|terms|binding|legal|lawyer|attorney|terminate|termination)\b/', $body)) {
            $risk = 'high';
        }

        // Sensitive topics
        if (preg_match('/\b(complaint|lawsuit|dispute|refund|cancel|termination|breach)\b/', $body)) {
            $risk = max($risk, 'medium', SORT_REGULAR);
        }

        // External recipient
        if ($recipient && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $risk = $risk === 'low' ? 'medium' : $risk;
        }

        // Bulk communication
        if (!empty($params['bulk']) || ($params['recipient_count'] ?? 1) > 1) {
            $risk = 'high';
        }

        return $risk;
    }

    /**
     * Store a draft in the database.
     */
    private function storeDraft($data) {
        $db = $this->getDb();
        if (!$db) return 0;

        $draftKey = bin2hex(random_bytes(16));

        $sql = "INSERT INTO ai_communication_drafts 
                (company_id, user_id, draft_key, context_type, context_id, communication_type,
                 subject, recipient, tone, formality, language, length, urgency,
                 body, verified_facts, source_references, placeholders,
                 provider, model, tokens_used, cost_usd,
                 risk_level, approval_required, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";

        $stmt = $db->prepare($sql);
        if (!$stmt) return 0;

        $verifiedFactsJson = json_encode($data['verified_facts'] ?? []);
        $sourcesJson = json_encode($data['source_references'] ?? []);
        $placeholdersJson = json_encode($data['placeholders'] ?? []);

        $stmt->bind_param('iisssissssssssssssidsis',
            $this->companyId, $this->userId, $draftKey,
            $data['context_type'], $data['context_id'], $data['communication_type'],
            $data['subject'], $data['recipient'], $data['tone'],
            $data['formality'], $data['language'], $data['length'], $data['urgency'] ?? 'normal',
            $data['body'], $verifiedFactsJson, $sourcesJson, $placeholdersJson,
            $data['provider'], $data['model'], $data['tokens_used'],
            $data['cost_usd'], $data['risk_level'], $data['approval_required'] ? 1 : 0
        );

        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Get a draft by ID.
     */
    public function getDraft($draftId) {
        $db = $this->getDb();
        if (!$db) return null;

        $stmt = $db->prepare("SELECT * FROM ai_communication_drafts WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
        if (!$stmt) return null;

        $stmt->bind_param('ii', $draftId, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $draft = $result->fetch_assoc();
        $stmt->close();

        return $draft;
    }

    /**
     * List drafts for the current user/company.
     */
    public function listDrafts($filters = []) {
        $db = $this->getDb();
        if (!$db) return [];

        $where = "company_id = ? AND deleted_at IS NULL";
        $params = [$this->companyId];
        $types = 'i';

        if (!empty($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (!empty($filters['context_type'])) {
            $where .= " AND context_type = ?";
            $params[] = $filters['context_type'];
            $types .= 's';
        }
        if (!empty($filters['communication_type'])) {
            $where .= " AND communication_type = ?";
            $params[] = $filters['communication_type'];
            $types .= 's';
        }

        $limit = min($filters['limit'] ?? 50, 200);
        $offset = $filters['offset'] ?? 0;

        $sql = "SELECT id, draft_key, context_type, context_id, communication_type, subject, recipient,
                       tone, status, risk_level, approval_required, sent_at, created_at, updated_at
                FROM ai_communication_drafts 
                WHERE {$where}
                ORDER BY updated_at DESC 
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $drafts = [];
        while ($row = $result->fetch_assoc()) {
            $drafts[] = $row;
        }
        $stmt->close();

        return $drafts;
    }

    /**
     * Sanitize user input against prompt injection.
     */
    private function sanitizeInput($text) {
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        if (class_exists('AIInjectionDetectionService')) {
            $detector = new AIInjectionDetectionService();
            if (method_exists($detector, 'scan')) {
                $result = $detector->scan($text);
                if (!empty($result['detected'])) {
                    return '[Content filtered for safety]';
                }
            }
        }
        return $text;
    }

    /**
     * Sanitize AI output.
     */
    private function sanitizeOutput($text) {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        // Remove potential injection in output
        $text = preg_replace('/system\s*(prompt|instructions)/i', '[filtered]', $text);
        return trim($text);
    }

    /**
     * Record an F23 observability event.
     */
    private function recordEvent($eventType, $entityId, $metadata = []) {
        $db = $this->getDb();
        if (!$db) return;

        $sql = "INSERT INTO ai_communication_events 
                (company_id, event_type, entity_type, entity_id, user_id, metadata)
                VALUES (?, ?, 'draft', ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return;

        $metaJson = json_encode($metadata);
        $stmt->bind_param('isisi', $this->companyId, $eventType, $entityId, $this->userId, $metaJson);
        $stmt->execute();
        $stmt->close();
    }

    private function getDb() {
        if (function_exists('getDB')) return getDB();
        global $db;
        return $db ?? null;
    }
}
