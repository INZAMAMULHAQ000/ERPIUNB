<?php
/**
 * AI Shared Conversation Service
 * 
 * F19: Shared AI Conversations — share, convert, messages, context.
 * All methods are static and take $db as first parameter.
 */

class AISharedConversationService {

    // ── Conversation Management ───────────────────────────────

    /**
     * Create a shared workspace conversation.
     */
    public static function create($db, $workspaceId, $userId, $title = null) {
        $stmt = $db->prepare("INSERT INTO ai_workspace_conversations (workspace_id, title, created_by) VALUES (?, ?, ?)");
        $stmt->execute([(int)$workspaceId, $title, (int)$userId]);
        $convId = (int)$db->lastInsertId();

        AIWorkspaceService::logActivity($db, $workspaceId, $userId, 'conversation_shared', 'conversation', $convId, ['title' => $title]);

        // Notify all workspace members
        $members = $db->prepare("SELECT user_id FROM ai_workspace_members WHERE workspace_id = ? AND user_id != ?");
        $members->execute([(int)$workspaceId, (int)$userId]);
        while ($m = $members->fetch(PDO::FETCH_ASSOC)) {
            AIWorkspaceService::createNotification($db, $workspaceId, (int)$m['user_id'], 'research_updated', 'New shared conversation', "A new conversation '$title' was created in the workspace.", 'conversation', $convId);
        }

        return ['success' => true, 'conversation_id' => $convId];
    }

    /**
     * Convert a private conversation to shared workspace conversation.
     */
    public static function shareToWorkspace($db, $privateConvId, $workspaceId, $userId, $confirmed = false) {
        if (!$confirmed) {
            // Return preview of what will be shared
            $stmt = $db->prepare("SELECT * FROM ai_conversations WHERE id = ?");
            $stmt->execute([(int)$privateConvId]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$conv) return ['success' => false, 'error' => 'Conversation not found'];

            $msgStmt = $db->prepare("SELECT id as message_id, role as message_type, LEFT(content, 100) as preview FROM ai_messages WHERE conversation_id = ? ORDER BY created_at");
            $msgStmt->execute([(int)$privateConvId]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$conv) return ['success' => false, 'error' => 'Conversation not found'];

            return [
                'success' => true,
                'requires_confirmation' => true,
                'preview' => [
                    'title' => $conv['title'] ?? 'Untitled',
                    'message_count' => count($messages),
                    'messages' => $messages,
                ]
            ];
        }

        // Create shared conversation
        $title = 'Shared: ' . ($db->query("SELECT title FROM ai_conversations WHERE id = " . (int)$privateConvId)->fetchColumn() ?: 'Untitled');
        $result = self::create($db, $workspaceId, $userId, $title);
        if (!$result['success']) return $result;

        $newConvId = $result['conversation_id'];

        // Copy messages from private conversation
        $msgStmt = $db->prepare("SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at");
        $msgStmt->execute([(int)$privateConvId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        $ins = $db->prepare("INSERT INTO ai_workspace_messages (conversation_id, author_user_id, message_type, content, metadata_json) VALUES (?, ?, ?, ?, ?)");
        foreach ($messages as $msg) {
            $ins->execute([
                $newConvId,
                $msg['user_id'] ?? $msg['author_user_id'] ?? 0,
                $msg['message_type'] ?? $msg['role'] ?? 'user',
                $msg['content'],
                $msg['metadata'] ?? null,
            ]);
        }

        // Record the share
        $db->prepare("INSERT INTO ai_conversation_shares (conversation_id, workspace_id, shared_by) VALUES (?, ?, ?)")
            ->execute([(int)$privateConvId, (int)$workspaceId, (int)$userId]);

        return ['success' => true, 'conversation_id' => $newConvId, 'messages_copied' => count($messages)];
    }

    /**
     * List shared conversations in a workspace.
     */
    public static function listByWorkspace($db, $workspaceId) {
        $stmt = $db->prepare("SELECT c.*, 
            (SELECT COUNT(*) FROM ai_workspace_messages WHERE conversation_id = c.conversation_id) as message_count,
            u.name as creator_name
            FROM ai_workspace_conversations c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.workspace_id = ? AND c.status = 'active'
            ORDER BY c.updated_at DESC");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a shared conversation with messages.
     */
    public static function getById($db, $conversationId) {
        $stmt = $db->prepare("SELECT * FROM ai_workspace_conversations WHERE conversation_id = ?");
        $stmt->execute([(int)$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conv) return null;

        $msgStmt = $db->prepare("SELECT * FROM ai_workspace_messages WHERE conversation_id = ? ORDER BY created_at");
        $msgStmt->execute([(int)$conversationId]);
        $conv['messages'] = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        return $conv;
    }

    // ── Message Management ────────────────────────────────────

    /**
     * Add a message to a shared conversation.
     */
    public static function addMessage($db, $conversationId, $userId, $content, $type = 'user', $metadata = null) {
        $validTypes = ['user','ai','system','activity'];
        if (!in_array($type, $validTypes)) $type = 'user';

        $stmt = $db->prepare("INSERT INTO ai_workspace_messages (conversation_id, author_user_id, message_type, content, metadata_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([(int)$conversationId, (int)$userId, $type, $content, $metadata ? json_encode($metadata) : null]);
        $msgId = (int)$db->lastInsertId();

        // Update conversation timestamp
        $db->prepare("UPDATE ai_workspace_conversations SET updated_at = NOW() WHERE conversation_id = ?")->execute([(int)$conversationId]);

        return ['success' => true, 'message_id' => $msgId];
    }

    /**
     * Get messages for a conversation with pagination.
     */
    public static function getMessages($db, $conversationId, $limit = 50, $offset = 0) {
        $stmt = $db->prepare("SELECT m.*, u.name as author_name
            FROM ai_workspace_messages m
            LEFT JOIN users u ON u.id = m.author_user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
            LIMIT ? OFFSET ?");
        $stmt->execute([(int)$conversationId, (int)$limit, (int)$offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Archive a shared conversation.
     */
    public static function archive($db, $conversationId, $userId) {
        $db->prepare("UPDATE ai_workspace_conversations SET status = 'archived' WHERE conversation_id = ?")->execute([(int)$conversationId]);
        return ['success' => true];
    }
}
