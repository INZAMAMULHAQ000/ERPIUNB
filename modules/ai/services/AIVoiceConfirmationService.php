<?php
/**
 * AI F28 — Voice Confirmation Service.
 *
 * Manages high-risk voice confirmations with parameter binding.
 * Ensures confirmation is bound to user, tenant, exact action, parameters, and expiration.
 *
 * Covers Section 8: High-Risk Voice Confirmation.
 */
class AIVoiceConfirmationService
{
    /**
     * Create a pending confirmation for a high-risk voice action.
     */
    public static function createConfirmation(
        PDO $db,
        int $userId,
        int $companyId,
        string $actionType,
        ?string $actionId,
        array $parameters,
        string $riskLevel,
        ?int $sessionId = null,
        ?int $messageId = null,
        int $expirySeconds = 300
    ): array {
        $confirmationKey = bin2hex(random_bytes(32));
        $parametersJson = json_encode($parameters);
        $parametersHash = hash('sha256', $parametersJson);
        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);

        $stmt = $db->prepare("
            INSERT INTO ai_voice_confirmations
                (company_id, user_id, session_id, message_id, confirmation_key,
                 action_type, action_id, parameters_json, parameters_hash,
                 risk_level, status, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([
            $companyId,
            $userId,
            $sessionId,
            $messageId,
            $confirmationKey,
            $actionType,
            $actionId,
            $parametersJson,
            $parametersHash,
            $riskLevel,
            $expiresAt,
        ]);

        return [
            'success' => true,
            'confirmation_id' => (int) $db->lastInsertId(),
            'confirmation_key' => $confirmationKey,
            'expires_at' => $expiresAt,
            'risk_level' => $riskLevel,
            'action_type' => $actionType,
            'parameters' => $parameters,
        ];
    }

    /**
     * Confirm a pending voice action.
     * Verifies user, tenant, parameters hash, and expiration.
     */
    public static function confirm(
        PDO $db,
        string $confirmationKey,
        int $userId,
        int $companyId,
        ?array $currentParameters = null
    ): array {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_confirmations
            WHERE confirmation_key = ? AND company_id = ? AND user_id = ?
        ");
        $stmt->execute([$confirmationKey, $companyId, $userId]);
        $confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$confirmation) {
            return ['success' => false, 'error' => 'Confirmation not found'];
        }

        // Check status
        if ($confirmation['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Confirmation is ' . $confirmation['status']];
        }

        // Check expiration
        if (strtotime($confirmation['expires_at']) < time()) {
            self::invalidate($db, (int) $confirmation['id'], 'expired');
            return ['success' => false, 'error' => 'Confirmation has expired'];
        }

        // Test 8: Verify parameters haven't changed
        if ($currentParameters !== null) {
            $currentHash = hash('sha256', json_encode($currentParameters));
            if ($currentHash !== $confirmation['parameters_hash']) {
                self::invalidate($db, (int) $confirmation['id'], 'parameters_changed');
                return ['success' => false, 'error' => 'Parameters have changed — confirmation invalidated'];
            }
        }

        // Confirm
        $db->prepare("
            UPDATE ai_voice_confirmations
            SET status = 'confirmed', confirmed_at = NOW()
            WHERE id = ?
        ")->execute([(int) $confirmation['id']]);

        return [
            'success' => true,
            'confirmation_id' => (int) $confirmation['id'],
            'action_type' => $confirmation['action_type'],
            'action_id' => $confirmation['action_id'],
            'parameters' => json_decode($confirmation['parameters_json'], true),
            'risk_level' => $confirmation['risk_level'],
        ];
    }

    /**
     * Deny a pending voice action.
     */
    public static function deny(PDO $db, string $confirmationKey, int $userId, int $companyId): array
    {
        $stmt = $db->prepare("
            UPDATE ai_voice_confirmations
            SET status = 'denied', denied_at = NOW()
            WHERE confirmation_key = ? AND company_id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$confirmationKey, $companyId, $userId]);

        return ['success' => $stmt->rowCount() > 0];
    }

    /**
     * Invalidate a confirmation.
     */
    public static function invalidate(PDO $db, int $confirmationId, string $reason): void
    {
        $db->prepare("
            UPDATE ai_voice_confirmations
            SET status = 'invalidated', invalidated_reason = ?
            WHERE id = ? AND status = 'pending'
        ")->execute([$reason, $confirmationId]);
    }

    /**
     * Expire all overdue confirmations.
     */
    public static function expireOverdue(PDO $db): int
    {
        $stmt = $db->prepare("
            UPDATE ai_voice_confirmations
            SET status = 'expired'
            WHERE status = 'pending' AND expires_at < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get pending confirmations for a user.
     */
    public static function getPending(PDO $db, int $userId, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_confirmations
            WHERE user_id = ? AND company_id = ? AND status = 'pending'
            AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get confirmation by key.
     */
    public static function getByKey(PDO $db, string $confirmationKey, int $companyId): ?array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_confirmations
            WHERE confirmation_key = ? AND company_id = ?
        ");
        $stmt->execute([$confirmationKey, $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get confirmation by database ID.
     */
    public static function getById(PDO $db, int $confirmationId, int $companyId, int $userId): ?array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_voice_confirmations
            WHERE id = ? AND company_id = ? AND user_id = ?
        ");
        $stmt->execute([$confirmationId, $companyId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
