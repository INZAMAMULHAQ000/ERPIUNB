<?php
/**
 * AI F28 — Voice + F26 Intelligence Integration.
 *
 * Allows users to query F26 intelligence via voice.
 * Covers Section 18: Voice + F26 Intelligence.
 */
class AIVoiceF26Integration
{
    /**
     * Answer a voice query about F26 intelligence.
     */
    public static function queryIntelligence(PDO $db, int $userId, int $companyId, string $text): array
    {
        $lower = strtolower($text);

        // Detect intelligence-related queries
        $isIntelQuery = preg_match('/\b(attention|important|risk|alert|issue|problem|warning|intelligence|insight|briefing|today|this week)\b/i', $lower);

        if (!$isIntelQuery) {
            return ['success' => false, 'is_intelligence_query' => false];
        }

        // Get briefing data
        $briefing = AIBriefingService::generateBriefing($db, $userId, $companyId);

        // Build a natural language response
        $responseParts = [];

        if (!empty($briefing['priorities'])) {
            $responseParts[] = 'You have ' . count($briefing['priorities']) . ' priority items:';
            foreach (array_slice($briefing['priorities'], 0, 5) as $priority) {
                $responseParts[] = '- [' . strtoupper($priority['severity']) . '] ' . $priority['title'];
            }
        } else {
            $responseParts[] = 'No critical issues at this time.';
        }

        if (!empty($briefing['recommended_actions'])) {
            $responseParts[] = '';
            $responseParts[] = 'Recommended actions:';
            foreach (array_slice($briefing['recommended_actions'], 0, 3) as $action) {
                $responseParts[] = '- ' . $action['recommendation'];
            }
        }

        // ERP metrics summary
        if (!empty($briefing['erp_metrics']['finance'])) {
            $f = $briefing['erp_metrics']['finance'];
            $responseParts[] = '';
            $responseParts[] = 'Finance: ' . ($f['overdue_count'] ?? 0) . ' overdue invoices totaling $' . number_format((float)($f['overdue_amount'] ?? 0), 2);
        }

        if (!empty($briefing['erp_metrics']['procurement'])) {
            $p = $briefing['erp_metrics']['procurement'];
            $responseParts[] = 'Procurement: ' . ($p['overdue_count'] ?? 0) . ' overdue purchase orders.';
        }

        $response = implode("\n", $responseParts);

        return [
            'success' => true,
            'is_intelligence_query' => true,
            'response' => $response,
            'briefing' => $briefing,
            'can_create_task' => !empty($briefing['priorities']),
        ];
    }

    /**
     * Handle "handle this" — create F25 task from intelligence.
     */
    public static function handleIntelligenceEvent(
        PDO $db,
        int $userId,
        int $companyId,
        int $eventId,
        ?int $sessionId = null
    ): array {
        $event = AIIntelligenceService::getEvent($db, $eventId, $userId);
        if (!$event) {
            return ['success' => false, 'error' => 'Intelligence event not found'];
        }

        // Create F25 task from the event
        $request = $event['suggested_action'] ?? $event['title'];
        return AIVoiceF25Integration::createTaskFromVoice(
            $db, $userId, $companyId,
            $request,
            ['source' => 'intelligence', 'event_id' => $eventId],
            $sessionId
        );
    }
}
