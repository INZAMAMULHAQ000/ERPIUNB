<?php
/**
 * AI F28 — Voice Context Service.
 *
 * Manages conversational context for voice interactions.
 * Preserves conversation, user, tenant, permissions, ERP context.
 * Never transfers authorization through conversational context.
 *
 * Covers Section 4: Conversational Context.
 */
class AIVoiceContextService
{
    /**
     * Create a new voice session.
     */
    public static function createSession(PDO $db, int $userId, int $companyId, string $language = 'en'): array
    {
        $stmt = $db->prepare("
            INSERT INTO ai_voice_sessions
                (company_id, user_id, language, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([$companyId, $userId, $language]);

        return [
            'success' => true,
            'session_id' => (int) $db->lastInsertId(),
            'language' => $language,
        ];
    }

    /**
     * Get active voice session for a user.
     */
    public static function getActiveSession(PDO $db, int $userId, int $companyId): ?array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_sessions
            WHERE user_id = ? AND company_id = ? AND status = 'active'
            ORDER BY last_activity_at DESC LIMIT 1
        ");
        $stmt->execute([$userId, $companyId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        return $session ?: null;
    }

    /**
     * Add a message to a voice session.
     */
    public static function addMessage(
        PDO $db,
        int $sessionId,
        int $userId,
        int $companyId,
        string $role,
        string $contentText,
        array $metadata = []
    ): array {
        $stmt = $db->prepare("
            INSERT INTO ai_voice_messages
                (session_id, company_id, user_id, role, content_text,
                 transcription_text, transcription_confidence, transcription_language,
                 audio_duration_ms, tts_generated, tts_provider,
                 intent_type, action_type, action_id, action_status,
                 risk_level, governance_decision,
                 f25_task_id, f25_task_key, f26_event_id, f27_document_id,
                 metadata_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $companyId,
            $userId,
            $role,
            $contentText,
            $metadata['transcription_text'] ?? null,
            $metadata['transcription_confidence'] ?? null,
            $metadata['transcription_language'] ?? null,
            $metadata['audio_duration_ms'] ?? null,
            $metadata['tts_generated'] ? 1 : 0,
            $metadata['tts_provider'] ?? null,
            $metadata['intent_type'] ?? null,
            $metadata['action_type'] ?? null,
            $metadata['action_id'] ?? null,
            $metadata['action_status'] ?? null,
            $metadata['risk_level'] ?? 'low',
            $metadata['governance_decision'] ?? null,
            $metadata['f25_task_id'] ?? null,
            $metadata['f25_task_key'] ?? null,
            $metadata['f26_event_id'] ?? null,
            $metadata['f27_document_id'] ?? null,
            !empty($metadata) ? json_encode($metadata) : null,
        ]);

        $messageId = (int) $db->lastInsertId();

        // Update session message count and activity
        $db->prepare("
            UPDATE ai_voice_sessions
            SET message_count = message_count + 1, last_activity_at = NOW()
            WHERE id = ?
        ")->execute([$sessionId]);

        return ['success' => true, 'message_id' => $messageId];
    }

    /**
     * Get conversation history for a session.
     */
    public static function getConversationHistory(PDO $db, int $sessionId, int $limit = 50): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_messages
            WHERE session_id = ?
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent messages for context (last N messages).
     */
    public static function getRecentContext(PDO $db, int $sessionId, int $count = 10): array
    {
        $stmt = $db->prepare("
            SELECT role, content_text, intent_type, action_type, risk_level, created_at
            FROM ai_voice_messages
            WHERE session_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $count]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_reverse($messages); // Chronological order
    }

    /**
     * End a voice session.
     */
    public static function endSession(PDO $db, int $sessionId): void
    {
        $db->prepare("
            UPDATE ai_voice_sessions
            SET status = 'completed', ended_at = NOW()
            WHERE id = ?
        ")->execute([$sessionId]);
    }

    /**
     * Build ERP context for voice requests.
     * Section 4: Preserves current ERP context without transferring authorization.
     */
    public static function buildERPContext(int $userId, int $companyId, array $pageContext = []): array
    {
        return [
            'user_id' => $userId,
            'company_id' => $companyId,
            'page' => $pageContext['page'] ?? null,
            'module' => $pageContext['module'] ?? null,
            'record_id' => $pageContext['record_id'] ?? null,
            'record_type' => $pageContext['record_type'] ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
            // Note: Authorization is checked server-side, not via context
        ];
    }

    /**
     * Get user's voice preferences.
     */
    public static function getPreferences(PDO $db, int $userId, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_preferences
            WHERE user_id = ? AND company_id = ?
        ");
        $stmt->execute([$userId, $companyId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefs) {
            return [
                'preferred_language' => 'en',
                'preferred_voice' => null,
                'tts_speed' => 1.0,
                'auto_tts' => true,
                'push_to_talk' => true,
                'noise_cancellation' => false,
                'max_recording_seconds' => 120,
            ];
        }

        return $prefs;
    }

    /**
     * Save user's voice preferences.
     */
    public static function savePreferences(PDO $db, int $userId, int $companyId, array $prefs): bool
    {
        $stmt = $db->prepare("
            INSERT INTO ai_voice_preferences
                (company_id, user_id, preferred_language, preferred_voice, tts_speed,
                 auto_tts, push_to_talk, noise_cancellation, max_recording_seconds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                preferred_language = VALUES(preferred_language),
                preferred_voice = VALUES(preferred_voice),
                tts_speed = VALUES(tts_speed),
                auto_tts = VALUES(auto_tts),
                push_to_talk = VALUES(push_to_talk),
                noise_cancellation = VALUES(noise_cancellation),
                max_recording_seconds = VALUES(max_recording_seconds)
        ");
        return $stmt->execute([
            $companyId,
            $userId,
            $prefs['preferred_language'] ?? 'en',
            $prefs['preferred_voice'] ?? null,
            $prefs['tts_speed'] ?? 1.0,
            $prefs['auto_tts'] ?? true,
            $prefs['push_to_talk'] ?? true,
            $prefs['noise_cancellation'] ?? false,
            $prefs['max_recording_seconds'] ?? 120,
        ]);
    }

    /**
     * Delete conversation history (Section 21: Voice History).
     */
    public static function deleteHistory(PDO $db, int $sessionId, int $userId, int $companyId): bool
    {
        // Verify ownership
        $stmt = $db->prepare("
            SELECT id FROM ai_voice_sessions
            WHERE id = ? AND user_id = ? AND company_id = ?
        ");
        $stmt->execute([$sessionId, $userId, $companyId]);
        if (!$stmt->fetch()) return false;

        // Delete messages first (foreign key)
        $db->prepare("DELETE FROM ai_voice_messages WHERE session_id = ?")->execute([$sessionId]);
        $db->prepare("DELETE FROM ai_voice_sessions WHERE id = ?")->execute([$sessionId]);

        return true;
    }
}
