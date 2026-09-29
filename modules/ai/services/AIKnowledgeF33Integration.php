<?php
/**
 * AI F33 — Knowledge Integration Service
 * 
 * Copilot integration, record-level knowledge, F27/F31/F25 integrations.
 * Sections 19-23 of F33 specification.
 */

class AIKnowledgeF33Integration {

    private $db;
    private $userId;
    private $companyId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->companyId = $_SESSION['company_id'] ?? 0;
    }

    /**
     * F29 Copilot integration — route knowledge questions to F33.
     */
    public function copilotKnowledgeRoute(string $query, array $context = []): array {
        $qaService = new AIKnowledgeQAService($this->db, $this->userId);

        return $qaService->askQuestion($query, [
            'query_type' => 'copilot',
            'context_type' => $context['module'] ?? null,
            'context_id' => $context['record_id'] ?? null,
        ]);
    }

    /**
     * Record-level knowledge — ask about ERP record context.
     */
    public function recordKnowledge(string $recordType, int $recordId, string $question): array {
        // Validate record type against known types
        $validTypes = ['customer', 'supplier', 'invoice', 'po', 'quotation', 'ticket', 'order', 'product', 'employee', 'document'];
        $recordType = in_array($recordType, $validTypes) ? $recordType : 'record';

        $qaService = new AIKnowledgeQAService($this->db, $this->userId);

        $enhancedQuery = "For {$recordType} record #{$recordId}: " . mb_substr($question, 0, 1000);

        return $qaService->askQuestion($enhancedQuery, [
            'query_type' => 'record_level',
            'context_type' => $recordType,
            'context_id' => $recordId,
        ]);
    }

    /**
     * F27 Document integration — compare document with knowledge.
     */
    public function compareDocumentWithKnowledge(int $documentId, string $knowledgeTopic): array {
        // Get document content
        $docService = new AIDocumentIntelligenceService($this->companyId, $this->userId);
        $docContent = null;

        if (method_exists($docService, 'getDocumentContent')) {
            $docContent = $docService->getDocumentContent($documentId);
        }

        // Get knowledge
        $qaService = new AIKnowledgeQAService($this->db, $this->userId);
        $knowledgeResult = $qaService->askQuestion($knowledgeTopic, ['query_type' => 'document_compare']);

        // Build comparison prompt
        $prompt = "Compare the following document with company knowledge:\n\n";
        $prompt .= "DOCUMENT CONTENT:\n" . ($docContent ? mb_substr($docContent, 0, 3000) : 'Unable to retrieve document content') . "\n\n";
        $prompt .= "COMPANY KNOWLEDGE:\n" . mb_substr($knowledgeResult['answer'] ?? 'No knowledge found', 0, 3000) . "\n\n";
        $prompt .= "Identify:\n1. Alignment between document and knowledge\n2. Discrepancies\n3. Missing information\n";
        $prompt .= "Do NOT claim legal or compliance conclusions without explicit evidence.\n";

        $aiResult = $this->callAIProvider($prompt);

        return [
            'comparison' => $aiResult['text'] ?? '',
            'knowledge_sources' => $knowledgeResult['sources'] ?? [],
            'knowledge_confidence' => $knowledgeResult['confidence'] ?? 0,
            'citations' => $knowledgeResult['citations'] ?? [],
        ];
    }

    /**
     * F31 Process Intelligence integration — check SOP compliance.
     */
    public function checkProcessCompliance(string $processDescription, string $sopTopic): array {
        $qaService = new AIKnowledgeQAService($this->db, $this->userId);
        $knowledgeResult = $qaService->askQuestion($sopTopic, ['query_type' => 'process_check']);

        $prompt = "Compare this process with the documented SOP:\n\n";
        $prompt .= "PROCESS:\n" . mb_substr($processDescription, 0, 3000) . "\n\n";
        $prompt .= "DOCUMENTED SOP:\n" . mb_substr($knowledgeResult['answer'] ?? 'No SOP found', 0, 3000) . "\n\n";
        $prompt .= "Identify:\n1. Steps that align with the SOP\n2. Steps that deviate\n3. Missing steps from the SOP\n";
        $prompt .= "Do NOT automatically declare non-compliance. Only state deviations factually.\n";

        $aiResult = $this->callAIProvider($prompt);

        return [
            'analysis' => $aiResult['text'] ?? '',
            'sop_sources' => $knowledgeResult['sources'] ?? [],
            'sop_confidence' => $knowledgeResult['confidence'] ?? 0,
            'has_sop' => !empty($knowledgeResult['sources']),
        ];
    }

    /**
     * F25 Agent integration — use knowledge for task planning.
     */
    public function agentKnowledgeLookup(string $procedureTopic, array $taskContext = []): array {
        $qaService = new AIKnowledgeQAService($this->db, $this->userId);
        $result = $qaService->askQuestion($procedureTopic, ['query_type' => 'agent']);

        return [
            'procedure' => $result['answer'] ?? '',
            'sources' => $result['sources'] ?? [],
            'confidence' => $result['confidence'] ?? 0,
            'citations' => $result['citations'] ?? [],
            'task_context' => $taskContext,
            'note' => 'Knowledge retrieval does not grant permissions. All actions still require F24 governance.',
        ];
    }

    /**
     * Get relevant knowledge for an ERP page/module.
     */
    public function getPageKnowledge(string $module, ?int $recordId = null): array {
        // Map modules to knowledge collections
        $moduleMap = [
            'procurement' => ['Procurement SOPs'],
            'finance' => ['Finance Procedures', 'Company Policies'],
            'hrms' => ['HR Policies', 'Onboarding Guides'],
            'crm' => ['Sales Procedures', 'Customer Service'],
            'general' => ['Company Policies', 'Operations Manual'],
        ];

        $collectionNames = $moduleMap[$module] ?? ['Company Policies'];
        $companyId = $this->companyId ?: 1;

        $placeholders = implode(',', array_fill(0, count($collectionNames), '?'));
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.description,
                   (SELECT COUNT(*) FROM ai_knowledge_sources WHERE collection_id = c.id AND status = 'indexed') as source_count
            FROM ai_knowledge_collections c
            WHERE c.company_id = ? AND c.name IN ({$placeholders}) AND c.status = 'active'
        ");
        $stmt->execute(array_merge([$companyId], $collectionNames));
        $collections = $stmt->fetchAll();

        return [
            'collections' => $collections,
            'module' => $module,
            'record_id' => $recordId,
        ];
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
}
