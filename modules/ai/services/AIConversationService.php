<?php
/**
 * AI Conversation Service.
 *
 * Handles conversation and message CRUD with user scoping.
 */

class AIConversationService
{
    const MAX_HISTORY_MESSAGES = 50;
    const SYSTEM_PROMPT = <<<'PROMPT'
You are the ERP AI Assistant, a grounded reasoning assistant integrated into the company's ERP system.

Your role:
- Help users with questions about their work, data, and ERP processes
- Provide precise, business-focused responses grounded in real ERP context
- Use the available governed actions, tasks, workflows, and navigation paths when the system can safely carry out a request
- Ask only for the missing fields needed to continue when a request is incomplete
- If a matching action exists, prepare it or guide the user to the correct form instead of saying the task cannot be done
- Never invent records, URLs, IDs, or business facts
- Never claim you executed a write action unless the system actually completed it
- Respect permissions, confirmations, and governance boundaries at all times

Guidelines:
- Be helpful and professional
- Prefer direct answers and concrete next steps over generic chatbot filler
- Format responses clearly using paragraphs
- If you don't know something, say so honestly
- Never fabricate data or claim access to data you don't have
- Focus on being genuinely useful to the user
PROMPT;

    // ---------------------------------------------------------
    // Conversation Operations
    // ---------------------------------------------------------

    public static function createConversation(PDO $db, int $userId, int $providerId, int $modelId, string $title = 'New Conversation'): array
    {
        // Validate provider and model
        $provider = AIProviderService::getProviderById($db, $providerId);
        if (!$provider || !$provider['is_active']) {
            return ['success' => false, 'errors' => ['Selected provider is not available.']];
        }

        $model = AIModelService::getModelById($db, $modelId);
        if (!$model || !$model['is_active'] || (int)$model['provider_id'] !== $providerId) {
            return ['success' => false, 'errors' => ['Selected model is not available for this provider.']];
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_conversations (user_id, title, provider_id, model_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            $stmt->execute([$userId, $title, $providerId, $modelId]);
            $convId = (int) $db->lastInsertId();

            // Add system prompt
            self::addMessage($db, $convId, 'system', self::SYSTEM_PROMPT);

            aiAuditLog($db, 'conversation_created', 'conversation', $convId, null, $userId);

            return ['success' => true, 'id' => $convId];
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public static function getConversationById(PDO $db, int $id, int $userId): ?array
    {
        $stmt = $db->prepare("
            SELECT c.*, p.name as provider_name, p.provider_type, m.display_name as model_name, m.model_id as model_identifier
            FROM ai_conversations c
            JOIN ai_providers p ON p.id = c.provider_id
            JOIN ai_models m ON m.id = c.model_id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function getUserConversations(PDO $db, int $userId, ?string $search = null, ?string $status = 'active'): array
    {
        $sql = "SELECT c.*, p.name as provider_name, m.display_name as model_name
                FROM ai_conversations c
                JOIN ai_providers p ON p.id = c.provider_id
                JOIN ai_models m ON m.id = c.model_id
                WHERE c.user_id = ?";
        $params = [$userId];

        if ($status !== null) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND c.title LIKE ?";
            $params[] = '%' . trim($search) . '%';
        }

        $sql .= " ORDER BY c.last_message_at DESC, c.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateConversationTitle(PDO $db, int $id, int $userId, string $title): array
    {
        $conv = self::getConversationById($db, $id, $userId);
        if (!$conv) return ['success' => false, 'errors' => ['Conversation not found.']];

        $stmt = $db->prepare("UPDATE ai_conversations SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([trim($title), $id, $userId]);

        return ['success' => true];
    }

    public static function archiveConversation(PDO $db, int $id, int $userId): array
    {
        $conv = self::getConversationById($db, $id, $userId);
        if (!$conv) return ['success' => false, 'errors' => ['Conversation not found.']];

        $stmt = $db->prepare("UPDATE ai_conversations SET status = 'archived', updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        aiAuditLog($db, 'conversation_archived', 'conversation', $id, null, $userId);

        return ['success' => true];
    }

    public static function deleteConversation(PDO $db, int $id, int $userId): array
    {
        $conv = self::getConversationById($db, $id, $userId);
        if (!$conv) return ['success' => false, 'errors' => ['Conversation not found.']];

        $stmt = $db->prepare("DELETE FROM ai_conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        aiAuditLog($db, 'conversation_deleted', 'conversation', $id, null, $userId);

        return ['success' => true];
    }

    // ---------------------------------------------------------
    // Message Operations
    // ---------------------------------------------------------

    public static function addMessage(PDO $db, int $conversationId, string $role, string $content, ?array $metadata = null): array
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_messages (conversation_id, role, content, metadata_json, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $conversationId,
                $role,
                $content,
                $metadata ? json_encode($metadata) : null,
            ]);

            $msgId = (int) $db->lastInsertId();

            // Update conversation
            $db->prepare("UPDATE ai_conversations SET message_count = message_count + 1, last_message_at = NOW(), updated_at = NOW() WHERE id = ?")
               ->execute([$conversationId]);

            return ['success' => true, 'id' => $msgId];
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public static function addMessageWithTokens(PDO $db, int $conversationId, string $role, string $content, ?int $inputTokens, ?int $outputTokens, ?array $metadata = null): array
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_messages (conversation_id, role, content, metadata_json, input_tokens, output_tokens, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $conversationId,
                $role,
                $content,
                $metadata ? json_encode($metadata) : null,
                $inputTokens,
                $outputTokens,
            ]);

            $msgId = (int) $db->lastInsertId();

            $db->prepare("UPDATE ai_conversations SET message_count = message_count + 1, last_message_at = NOW(), updated_at = NOW() WHERE id = ?")
               ->execute([$conversationId]);

            return ['success' => true, 'id' => $msgId];
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public static function getConversationMessages(PDO $db, int $conversationId, int $limit = self::MAX_HISTORY_MESSAGES): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$conversationId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get chat history formatted for provider API.
     */
    public static function getChatHistory(PDO $db, int $conversationId): array
    {
        $messages = self::getConversationMessages($db, $conversationId);
        $history = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $history[] = ['role' => 'system', 'content' => $msg['content']];
            } elseif (in_array($msg['role'], ['user', 'assistant'])) {
                $history[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        return $history;
    }
}
