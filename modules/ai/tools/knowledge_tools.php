<?php
/**
 * AI F11 — Knowledge Search Tool.
 */

AIToolRegistry::register([
    'id'                  => 'knowledge.search',
    'name'                => 'search_knowledge',
    'description'         => 'Search the company knowledge base for relevant documents, policies, SOPs, and procedures. Returns relevant excerpts with source attribution.',
    'module'              => 'knowledge',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['ai.assistant.access'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'query'         => ['type' => 'string', 'description' => 'Search query to find relevant knowledge'],
            'collection_id' => ['type' => 'integer', 'description' => 'Optional: limit search to a specific collection'],
        ],
        'required' => ['query'],
    ],
    'handler_class'       => 'AIToolSearchKnowledge',
]);

class AIToolSearchKnowledge extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $query = $params['query'] ?? '';
        $collectionId = !empty($params['collection_id']) ? (int) $params['collection_id'] : null;

        $result = AIKnowledgeService::searchKnowledge($this->db, $this->userId, $query, $collectionId, 5);

        if (!$result['success']) {
            return ['success' => false, 'data' => null, 'error' => $result['error'] ?? 'Search failed'];
        }

        if (empty($result['results'])) {
            return ['success' => true, 'data' => 'No relevant knowledge found for: ' . $query];
        }

        $lines = ['Found relevant company knowledge:'];
        $lines[] = '';

        foreach ($result['results'] as $r) {
            $relevance = isset($r['relevance']) ? ' [relevance: ' . round($r['relevance'] * 100) . '%]' : '';
            $lines[] = "--- Source: {$r['source_title']} ({$r['collection_name']}){$relevance} ---";
            $lines[] = $r['content'];
            $lines[] = '';
        }

        $lines[] = 'Always attribute information to the source document when citing.';

        return ['success' => true, 'data' => implode("\n", $lines)];
    }
}
