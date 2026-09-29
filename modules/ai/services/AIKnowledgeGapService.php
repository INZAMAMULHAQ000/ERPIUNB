<?php
/**
 * AI F33 — Knowledge Gap & AI Draft Service
 * 
 * Gap detection, AI-generated knowledge drafts.
 * Sections 25-26 of F33 specification.
 */

class AIKnowledgeGapService {

    private $db;
    private $userId;
    private $companyId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->companyId = $_SESSION['company_id'] ?? 0;
    }

    /**
     * Detect knowledge gaps from unanswered/low-confidence queries.
     */
    public function detectGaps(): array {
        // Find queries with no answer or low confidence
        $stmt = $this->db->prepare("
            SELECT query_text, COUNT(*) as frequency, 
                   AVG(answer_confidence) as avg_confidence,
                   GROUP_CONCAT(DISTINCT user_id) as user_ids,
                   MIN(created_at) as first_asked,
                   MAX(created_at) as last_asked
            FROM ai_knowledge_queries
            WHERE company_id = ?
            AND (answer_provided = 0 OR answer_confidence < 0.4)
            AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY LOWER(query_text)
            HAVING frequency >= 2
            ORDER BY frequency DESC
            LIMIT 50
        ");
        $stmt->execute([$this->companyId]);
        $rows = $stmt->fetchAll();

        $gaps = [];
        foreach ($rows as $row) {
            // Check if gap already exists
            $existing = $this->findExistingGap($row['query_text']);
            if ($existing) {
                // Update frequency
                $this->db->prepare("UPDATE ai_knowledge_gaps SET frequency = ? WHERE id = ?")->execute([$row['frequency'], $existing['id']]);
                $gaps[] = array_merge($existing, ['frequency' => $row['frequency']]);
                continue;
            }

            // Create new gap
            $gapResult = $this->createGap([
                'topic' => $row['query_text'],
                'description' => "Asked {$row['frequency']} times with low/no answer (avg confidence: " . round($row['avg_confidence'] ?? 0, 2) . ")",
                'evidence' => ['query' => $row['query_text'], 'frequency' => $row['frequency']],
                'affected_users' => explode(',', $row['user_ids'] ?? ''),
                'frequency' => $row['frequency'],
                'recommendation' => $this->generateGapRecommendation($row),
            ]);

            if ($gapResult['success']) {
                $gaps[] = ['id' => $gapResult['gap_id']] + $row;
            }
        }

        $this->logEvent('gap_detected', null, null, ['gaps_found' => count($gaps)]);

        return $gaps;
    }

    /**
     * Get all detected gaps.
     */
    public function getGaps(?string $status = null): array {
        $conditions = ["company_id = ?"];
        $params = [$this->companyId];

        if ($status) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT * FROM ai_knowledge_gaps 
            WHERE {$where}
            ORDER BY frequency DESC, created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Generate an AI-drafted FAQ/SOP/article.
     */
    public function generateDraft(string $topic, string $draftType, array $options = []): array {
        // Retrieve relevant knowledge
        $qaService = new AIKnowledgeQAService($this->db, $this->userId);
        $retrieval = $qaService->askQuestion($topic, ['query_type' => 'draft']);

        $context = '';
        if (!empty($retrieval['sources'])) {
            foreach ($retrieval['sources'] as $src) {
                $context .= "Source: {$src['title']}\n";
            }
        }

        $prompt = "Generate a {$draftType} document about: {$topic}\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "- This will be marked as 'AI Draft — Not Authoritative'.\n";
        $prompt .= "- Base the content on the provided knowledge sources where available.\n";
        $prompt .= "- If knowledge is insufficient, note gaps clearly.\n";
        $prompt .= "- Do NOT invent company policies or procedures.\n";
        $prompt .= "- Keep it factual and professional.\n\n";

        if ($context) {
            $prompt .= "Available knowledge:\n{$context}\n\n";
        }

        $prompt .= "Generate the document now. Start with a clear title and structure.";

        $aiResult = $this->callAIProvider($prompt);

        $content = $aiResult['text'] ?? '';
        if (empty($content)) {
            return ['error' => 'Failed to generate draft'];
        }

        // Store the draft
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_knowledge_drafts 
                (company_id, user_id, draft_type, title, content, source_query, source_citations, 
                 collection_id, status, is_ai_generated, is_authoritative)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', 1, 0)
            ");

            $title = $options['title'] ?? $topic;
            $stmt->execute([
                $this->companyId, $this->userId, $draftType, $title, $content,
                $topic, json_encode($retrieval['citations'] ?? []),
                $options['collection_id'] ?? null,
            ]);

            $draftId = (int) $this->db->lastInsertId();

            $this->logEvent('draft_generated', 'draft', $draftId, ['type' => $draftType, 'topic' => $topic]);

            return [
                'success' => true,
                'draft_id' => $draftId,
                'title' => $title,
                'content' => $content,
                'type' => $draftType,
                'is_authoritative' => false,
                'citations' => $retrieval['citations'] ?? [],
            ];
        } catch (\Throwable $e) {
            return ['error' => 'Failed to store draft'];
        }
    }

    /**
     * Approve an AI draft for publication.
     */
    public function approveDraft(int $draftId): array {
        try {
            $this->db->prepare("
                UPDATE ai_knowledge_drafts 
                SET status = 'approved', is_authoritative = 1, approved_by = ?, approved_at = NOW()
                WHERE id = ? AND company_id = ?
            ")->execute([$this->userId, $draftId, $this->companyId]);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['error' => 'Failed to approve draft'];
        }
    }

    /**
     * Reject an AI draft.
     */
    public function rejectDraft(int $draftId, ?string $reason = null): array {
        try {
            $this->db->prepare("
                UPDATE ai_knowledge_drafts SET status = 'rejected', metadata_json = ? WHERE id = ? AND company_id = ?
            ")->execute([json_encode(['rejection_reason' => $reason]), $draftId, $this->companyId]);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['error' => 'Failed to reject draft'];
        }
    }

    /**
     * Publish an approved draft as a knowledge source.
     */
    public function publishDraft(int $draftId): array {
        $draft = $this->getDraft($draftId);
        if (!$draft) return ['error' => 'Draft not found'];
        if ($draft['status'] !== 'approved') return ['error' => 'Draft must be approved before publishing'];

        $collectionId = $draft['collection_id'];
        if (!$collectionId) return ['error' => 'No collection assigned'];

        // Create as knowledge source
        $knowledgeService = new AIKnowledgeService();
        $result = $knowledgeService::createTextSource($this->db, $this->userId, $collectionId, $draft['title'], $draft['content']);

        if ($result['success']) {
            $this->db->prepare("UPDATE ai_knowledge_drafts SET status = 'published', published_at = NOW() WHERE id = ?")->execute([$draftId]);
            return ['success' => true, 'source_id' => $result['id']];
        }

        return ['error' => $result['error'] ?? 'Failed to publish'];
    }

    /**
     * List AI-generated drafts.
     */
    public function listDrafts(?string $status = null): array {
        $conditions = ["company_id = ?"];
        $params = [$this->companyId];

        if ($status) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("SELECT * FROM ai_knowledge_drafts WHERE {$where} ORDER BY created_at DESC LIMIT 50");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function getDraft(int $draftId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM ai_knowledge_drafts WHERE id = ? AND company_id = ?");
        $stmt->execute([$draftId, $this->companyId]);
        return $stmt->fetch() ?: null;
    }

    private function findExistingGap(string $topic): ?array {
        $stmt = $this->db->prepare("SELECT * FROM ai_knowledge_gaps WHERE company_id = ? AND topic = ? AND status != 'resolved' LIMIT 1");
        $stmt->execute([$this->companyId, $topic]);
        return $stmt->fetch() ?: null;
    }

    private function createGap(array $params): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_knowledge_gaps (company_id, topic, description, evidence, affected_users, frequency, recommendation, suggested_owner)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->companyId,
                $params['topic'], $params['description'] ?? null,
                json_encode($params['evidence'] ?? []),
                json_encode($params['affected_users'] ?? []),
                $params['frequency'] ?? 1,
                $params['recommendation'] ?? null,
                $params['suggested_owner'] ?? null,
            ]);
            return ['success' => true, 'gap_id' => (int) $this->db->lastInsertId()];
        } catch (\Throwable $e) {
            return ['error' => 'Failed to create gap'];
        }
    }

    private function generateGapRecommendation(array $row): string {
        $freq = $row['frequency'] ?? 1;
        if ($freq >= 10) return "High-priority gap: frequently asked but unanswered. Consider creating a dedicated knowledge article.";
        if ($freq >= 5) return "Moderate gap: multiple users asking. Consider adding to FAQ or creating a procedure document.";
        return "Low-priority gap: monitor for increased frequency before creating new content.";
    }

    private function callAIProvider(string $prompt): array {
        if (class_exists('AIProviderService')) {
            $providerService = new AIProviderService();
            if (method_exists($providerService, 'complete')) {
                $result = $providerService->complete($prompt);
                if ($result) return $result;
            }
        }
        return ['text' => '', 'provider' => 'none', 'model' => 'none', 'tokens_used' => 0, 'cost_usd' => 0];
    }

    private function logEvent(string $eventType, ?string $entityType, ?int $entityId, array $metadata = []): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_knowledge_events (company_id, event_type, entity_type, entity_id, user_id, metadata_json)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->companyId, $eventType, $entityType, $entityId, $this->userId, json_encode($metadata)]);
        } catch (\Throwable $e) {}
    }
}
