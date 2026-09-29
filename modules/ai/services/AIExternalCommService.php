<?php
/**
 * AI F24 — External Communication Protection Service.
 *
 * Protects against unauthorized external communications.
 * AI should distinguish between draft and send, with default safe behavior.
 *
 * Covers Section 29: External Communication Protection.
 */
class AIExternalCommService
{
    const ACTION_DRAFT = 'draft';
    const ACTION_SEND  = 'send';

    const CHANNEL_EMAIL   = 'email';
    const CHANNEL_SMS     = 'sms';
    const CHANNEL_WEBHOOK = 'webhook';
    const CHANNEL_API     = 'external_api';

    /**
     * Check if an external communication is allowed.
     *
     * @param PDO    $db      Database connection
     * @param string $channel Communication channel
     * @param string $action  draft or send
     * @param array  $params  Communication parameters
     * @param int    $userId  User ID
     * @param int    $companyId Company ID
     * @return array Decision result
     */
    public static function checkCommunication(
        PDO $db,
        string $channel,
        string $action,
        array $params = [],
        int $userId = 0,
        int $companyId = 0
    ): array {
        $result = [
            'allowed' => true,
            'requires_confirmation' => false,
            'requires_review' => false,
            'reason' => '',
        ];

        // Draft is always allowed (default safe behavior — Section 29)
        if ($action === self::ACTION_DRAFT) {
            return $result;
        }

        // Send requires confirmation
        if ($action === self::ACTION_SEND) {
            $result['requires_confirmation'] = true;
        }

        // Check for sensitive recipients
        if (!empty($params['recipients'])) {
            $recipients = is_array($params['recipients']) ? $params['recipients'] : [$params['recipients']];
            foreach ($recipients as $recipient) {
                if (self::isInternalRecipient($recipient)) {
                    // Internal — lower risk
                } else {
                    // External — higher risk
                    $result['requires_review'] = true;
                    $result['reason'] = 'External recipient detected — review required';
                }
            }
        }

        // Check for sensitive content
        $content = $params['content'] ?? $params['body'] ?? '';
        if (self::containsSensitiveContent($content)) {
            $result['requires_review'] = true;
            $result['reason'] = 'Message contains potentially sensitive content';
        }

        // Check channel-specific policies
        $channelCheck = self::checkChannelPolicy($db, $channel, $companyId);
        if (!$channelCheck['allowed']) {
            $result['allowed'] = false;
            $result['reason'] = $channelCheck['reason'];
            return $result;
        }

        return $result;
    }

    /**
     * Check if a recipient is internal (same domain/company).
     */
    private static function isInternalRecipient(string $email): bool
    {
        // Simple check — in production, compare against company domains
        $internalDomains = ['@company.com', '@internal.company.com'];
        foreach ($internalDomains as $domain) {
            if (str_ends_with(strtolower($email), $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if content contains sensitive information.
     */
    private static function containsSensitiveContent(string $content): bool
    {
        $sensitivePatterns = [
            '/\b(?:api[_-]?key|apikey)\s*[:=]/i',
            '/\b(?:password|passwd|pwd)\s*[:=]/i',
            '/\b(?:secret|token)\s*[:=]/i',
            '/\b\d{3}-\d{2}-\d{4}\b/', // SSN
            '/\b\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\b/', // Credit card
            '/\b(?:confidential|restricted|classified)\b/i',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check channel-specific policies.
     */
    private static function checkChannelPolicy(PDO $db, string $channel, int $companyId): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT effect FROM ai_policies
                 WHERE type = 'data_transmission'
                 AND scope IN ('global', 'company')
                 AND status = 'active'
                 AND (scope_id IS NULL OR scope_id = ?)
                 ORDER BY priority DESC
                 LIMIT 1"
            );
            $stmt->execute([$companyId]);
            $policy = $stmt->fetch();

            if ($policy && $policy['effect'] === 'deny') {
                return ['allowed' => false, 'reason' => 'External communication blocked by policy'];
            }
        } catch (\Throwable $e) {
            // If no policy, allow
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Sanitize content before sending externally.
     */
    public static function sanitizeForExternal(string $content): string
    {
        // Remove any internal system references
        $content = preg_replace('/[A-Z]{2,}-\d{4,}/', '[REFERENCE REDACTED]', $content);

        // Remove potential internal URLs
        $content = preg_replace('/https?:\/\/(?:intranet|internal|admin)\.[^\s]+/', '[INTERNAL URL REDACTED]', $content);

        return $content;
    }
}
