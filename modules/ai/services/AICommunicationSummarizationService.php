<?php
/**
 * AI F32 — Communication Summarization Service
 * 
 * Summarizes communication histories, extracts action items, builds timelines.
 * Sections 8-10 of F32 specification.
 */

class AICommunicationSummarizationService {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Summarize communication history for a context.
     *
     * @param string $contextType customer,supplier,employee,invoice,po,quotation,ticket
     * @param int    $contextId
     * @param int    $messageCount Number of recent messages to summarize
     * @return array Summary with key topics, action items, decisions, etc.
     */
    public function summarizeHistory($contextType, $contextId, $messageCount = 20) {
        // 1. Fetch bounded communication history
        $history = $this->getCommunicationHistory($contextType, $contextId, $messageCount);

        if (empty($history)) {
            return ['error' => 'No communication history found'];
        }

        // 2. Build context for AI
        $historyText = $this->formatHistoryForAI($history);

        // 3. Build summarization prompt
        $prompt = $this->buildSummaryPrompt($historyText, $contextType, $contextId, count($history));

        // 4. Call AI provider
        $aiResult = $this->callAIProvider($prompt);

        // 5. Parse structured response
        $summary = $this->parseSummaryResponse($aiResult, $history);

        // 6. Store summary
        $summaryId = $this->storeSummary([
            'context_type'  => $contextType,
            'context_id'    => $contextId,
            'summary_text'  => $summary['text'],
            'key_topics'    => $summary['key_topics'],
            'unresolved_issues' => $summary['unresolved_issues'],
            'decisions'     => $summary['decisions'],
            'commitments'   => $summary['commitments'],
            'dates'         => $summary['dates'],
            'action_items'  => $summary['action_items'],
            'sentiment'     => $summary['sentiment'],
            'next_recommended_step' => $summary['next_step'],
            'message_count' => count($history),
        ]);

        // 7. Record F23 event
        $this->recordEvent('summarize', $summaryId, [
            'context_type' => $contextType,
            'message_count' => count($history),
        ]);

        return array_merge(['summary_id' => $summaryId], $summary);
    }

    /**
     * Extract action items from authorized communications.
     *
     * @param string $text Communication text to analyze
     * @param array  $context Additional context
     * @return array Extracted action items with confidence and evidence
     */
    public function extractActionItems($text, $context = []) {
        $prompt = "Extract action items from this business communication:\n\n";
        $prompt .= "\"{$text}\"\n\n";
        $prompt .= "For each action item, provide:\n";
        $prompt .= "- description: what needs to be done\n";
        $prompt .= "- responsible_party: who should do it (if explicitly mentioned, otherwise null)\n";
        $prompt .= "- deadline: any mentioned date (if any)\n";
        $prompt .= "- confidence: 0.0-1.0 how certain this is an action item\n";
        $prompt .= "- evidence: the exact text that indicates this action\n";
        $prompt .= "- source_text: the surrounding sentence for context\n";
        $prompt .= "- proposed_action: one of [create_task, send_followup, schedule_reminder, request_approval, none]\n\n";
        $prompt .= "Return as JSON array. Only include clear action items. Do not invent actions.";

        $aiResult = $this->callAIProvider($prompt);

        $items = json_decode($aiResult['text'] ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }

        // Validate each item
        $validated = [];
        foreach ($items as $item) {
            if (empty($item['description'])) continue;
            $validated[] = [
                'description'      => $this->sanitizeOutput($item['description'] ?? ''),
                'responsible_party'=> $this->sanitizeOutput($item['responsible_party'] ?? null),
                'deadline'         => $item['deadline'] ?? null,
                'confidence'       => max(0, min(1, floatval($item['confidence'] ?? 0.5))),
                'evidence'         => $this->sanitizeOutput($item['evidence'] ?? ''),
                'source_text'      => $this->sanitizeOutput($item['source_text'] ?? ''),
                'proposed_action'  => $item['proposed_action'] ?? 'none',
            ];
        }

        return $validated;
    }

    /**
     * Build a conversation timeline for a context.
     *
     * @param string $contextType
     * @param int    $contextId
     * @param int    $limit
     * @return array Timeline events in chronological order
     */
    public function buildTimeline($contextType, $contextId, $limit = 50) {
        $db = $this->getDb();
        if (!$db) return [];

        // Gather events from multiple sources
        $events = [];

        // AI communication drafts
        $sql = "SELECT id, communication_type as type, subject, body, status, tone, created_at as date, 'draft' as source
                FROM ai_communication_drafts
                WHERE context_type = ? AND context_id = ? AND company_id = ? AND deleted_at IS NULL
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('siii', $contextType, $contextId, $this->companyId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['preview'] = mb_substr($row['body'], 0, 150);
                unset($row['body']);
                $events[] = $row;
            }
            $stmt->close();
        }

        // Follow-up events
        $sql = "SELECT id, context_type as type, reason as description, status, priority, created_at as date, 'followup' as source
                FROM ai_communication_followups
                WHERE context_type = ? AND context_id = ? AND company_id = ?
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('siii', $contextType, $contextId, $this->companyId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            $stmt->close();
        }

        // Communication events (F23)
        $sql = "SELECT id, event_type as type, metadata, created_at as date, 'event' as source
                FROM ai_communication_events
                WHERE entity_type = ? AND entity_id IN (
                    SELECT id FROM ai_communication_drafts WHERE context_type = ? AND context_id = ? AND company_id = ?
                )
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ssiii', $contextType, $contextType, $contextId, $this->companyId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            $stmt->close();
        }

        // Sort chronologically
        usort($events, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return array_slice($events, 0, $limit);
    }

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    private function getCommunicationHistory($contextType, $contextId, $limit) {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT communication_type, subject, body, tone, status, created_at
                FROM ai_communication_drafts
                WHERE context_type = ? AND context_id = ? AND company_id = ? AND deleted_at IS NULL
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('siii', $contextType, $contextId, $this->companyId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();

        return array_reverse($history);
    }

    private function formatHistoryForAI($history) {
        $lines = [];
        foreach ($history as $msg) {
            $date = $msg['created_at'] ?? 'Unknown date';
            $type = ucfirst($msg['communication_type'] ?? 'message');
            $subject = $msg['subject'] ?? '';
            $body = mb_substr($msg['body'] ?? '', 0, 500);
            $status = $msg['status'] ?? '';

            $lines[] = "[{$date}] [{$type}] [{$status}] Subject: {$subject}\n{$body}\n";
        }
        return implode("\n---\n", $lines);
    }

    private function buildSummaryPrompt($historyText, $contextType, $contextId, $count) {
        $prompt = "Summarize this business communication history ({$count} messages) with a {$contextType}.\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "- Only extract facts actually present in the messages.\n";
        $prompt .= "- Distinguish extracted facts from AI interpretation.\n";
        $prompt .= "- Clearly mark sentiment as 'AI interpretation, not definitive'.\n";
        $prompt .= "- Do not fabricate decisions, commitments, or action items.\n";
        $prompt .= "- If information is ambiguous, note it as such.\n\n";
        $prompt .= "Provide a JSON response with:\n";
        $prompt .= "- text: human-readable summary\n";
        $prompt .= "- key_topics: array of key topics discussed\n";
        $prompt .= "- unresolved_issues: array of unresolved issues\n";
        $prompt .= "- decisions: array of decisions made (only if explicitly stated)\n";
        $prompt .= "- commitments: array of commitments made (only if explicitly stated)\n";
        $prompt .= "- dates: array of important dates mentioned\n";
        $prompt .= "- action_items: array of action items with descriptions\n";
        $prompt .= "- sentiment: 'positive'|'neutral'|'negative'|'mixed' (mark as interpretation)\n";
        $prompt .= "- next_step: recommended next step based on the conversation\n\n";
        $prompt .= "Communication History:\n{$historyText}\n";

        return $prompt;
    }

    private function parseSummaryResponse($aiResult, $history) {
        $text = $aiResult['text'] ?? '';

        // Try to parse as JSON
        $data = json_decode($text, true);
        if (is_array($data)) {
            return [
                'text'               => $this->sanitizeOutput($data['text'] ?? $text),
                'key_topics'         => $data['key_topics'] ?? [],
                'unresolved_issues'  => $data['unresolved_issues'] ?? [],
                'decisions'          => $data['decisions'] ?? [],
                'commitments'        => $data['commitments'] ?? [],
                'dates'              => $data['dates'] ?? [],
                'action_items'       => $data['action_items'] ?? [],
                'sentiment'          => $data['sentiment'] ?? 'neutral',
                'next_step'          => $data['next_step'] ?? null,
            ];
        }

        // Fallback: return as plain text summary
        return [
            'text'               => $this->sanitizeOutput($text),
            'key_topics'         => [],
            'unresolved_issues'  => [],
            'decisions'          => [],
            'commitments'        => [],
            'dates'              => [],
            'action_items'       => [],
            'sentiment'          => 'neutral',
            'next_step'          => null,
        ];
    }

    private function storeSummary($data) {
        $db = $this->getDb();
        if (!$db) return 0;

        $summaryKey = bin2hex(random_bytes(16));

        $sql = "INSERT INTO ai_communication_summaries
                (company_id, user_id, summary_key, context_type, context_id,
                 summary_text, key_topics, unresolved_issues, decisions, commitments,
                 dates, action_items, sentiment, next_recommended_step, message_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return 0;

        $stmt->bind_param('iissssssssssssi',
            $this->companyId, $this->userId, $summaryKey,
            $data['context_type'], $data['context_id'],
            $data['summary_text'],
            json_encode($data['key_topics']),
            json_encode($data['unresolved_issues']),
            json_encode($data['decisions']),
            json_encode($data['commitments']),
            json_encode($data['dates']),
            json_encode($data['action_items']),
            $data['sentiment'],
            $data['next_recommended_step'],
            $data['message_count']
        );

        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    private function callAIProvider($prompt) {
        if (class_exists('AIProviderService')) {
            $providerService = new AIProviderService();
            if (method_exists($providerService, 'complete')) {
                $result = $providerService->complete($prompt);
                if ($result) return $result;
            }
        }
        return ['text' => '{}', 'provider' => 'none', 'model' => 'none', 'tokens_used' => 0, 'cost_usd' => 0];
    }

    private function sanitizeOutput($text) {
        if (!is_string($text)) return '';
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        return trim($text);
    }

    private function recordEvent($eventType, $entityId, $metadata = []) {
        $db = $this->getDb();
        if (!$db) return;

        $sql = "INSERT INTO ai_communication_events (company_id, event_type, entity_type, entity_id, user_id, metadata)
                VALUES (?, ?, 'summary', ?, ?, ?)";
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
