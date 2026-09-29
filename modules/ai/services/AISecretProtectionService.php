<?php
/**
 * AI F24 — Secret Protection Service.
 *
 * Ensures AI never receives credentials, API keys, tokens, or secrets.
 * Tool execution happens server-side; credentials never pass through AI.
 *
 * Covers Sections 16 (Secret Protection) and 47 (Logging Security).
 */
class AISecretProtectionService
{
    // Patterns that match secrets/credentials
    private static array $secretPatterns = [
        '/\b(?:api[_-]?key|apikey)\s*[:=]\s*["\']?[^\s"\']+/i',
        '/\b(?:secret|token|password|passwd|pwd)\s*[:=]\s*["\']?[^\s"\']+/i',
        '/\bsk-[A-Za-z0-9_\-]{20,}/',      // OpenAI API keys
        '/\bghp_[A-Za-z0-9_]{36}/',         // GitHub tokens
        '/\bgho_[A-Za-z0-9_]{36}/',         // GitHub OAuth
        '/\bAKIA[A-Z0-9]{16}/',             // AWS Access Key
        '/\beyJ[A-Za-z0-9_\-]+\.eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', // JWT
        '/Bearer\s+[A-Za-z0-9_\-\.]+/i',    // Bearer tokens
        '/\b[A-Za-z0-9_]{32,}\b/',          // Generic long secrets (high false positive — use carefully)
    ];

    // Field names that should never be exposed to AI
    private static array $sensitiveFieldNames = [
        'password', 'passwd', 'pwd', 'secret', 'api_key', 'apikey',
        'access_key', 'secret_key', 'private_key', 'token',
        'refresh_token', 'access_token', 'session_token',
        'encryption_key', 'signing_key', 'oauth_secret',
        'database_password', 'db_password', 'db_pass',
        'smtp_password', 'mail_password',
        'ssl_key', 'ssl_certificate',
        'webhook_secret', 'signing_secret',
    ];

    /**
     * Check if content contains secrets.
     *
     * @param string $content Content to scan
     * @return array Detection result
     */
    public static function containsSecrets(string $content): array
    {
        $result = [
            'has_secrets' => false,
            'detected' => [],
        ];

        foreach (self::$secretPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $result['has_secrets'] = true;
                $result['detected'][] = [
                    'pattern' => $pattern,
                    'match' => self::redactMatch($matches[0]),
                ];
            }
        }

        return $result;
    }

    /**
     * Redact secrets from content.
     *
     * @param string $content Content with potential secrets
     * @return string Redacted content
     */
    public static function redactSecrets(string $content): string
    {
        // Redact known secret patterns
        foreach (self::$secretPatterns as $pattern) {
            $content = preg_replace_callback($pattern, function ($matches) {
                return self::redactMatch($matches[0]);
            }, $content);
        }

        return $content;
    }

    /**
     * Redact a matched secret string, keeping first/last chars for identification.
     */
    private static function redactMatch(string $match): string
    {
        $len = strlen($match);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($match, 0, 4) . str_repeat('*', $len - 8) . substr($match, -4);
    }

    /**
     * Ensure tool execution happens server-side without exposing credentials.
     * AI says "Use email connector" → Server retrieves credential, executes, returns safe result.
     *
     * @param string $toolName Tool name
     * @param array  $params   Tool parameters
     * @return array Sanitized parameters (secrets removed)
     */
    public static function sanitizeToolParams(string $toolName, array $params): array
    {
        $sanitized = [];

        foreach ($params as $key => $value) {
            // Never pass secret fields to AI
            if (in_array(strtolower($key), self::$sensitiveFieldNames)) {
                continue; // Omit the secret field entirely
            }

            // Check string values for embedded secrets
            if (is_string($value)) {
                $value = self::redactSecrets($value);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Check if a field name is sensitive.
     */
    public static function isSensitiveField(string $fieldName): bool
    {
        return in_array(strtolower($fieldName), self::$sensitiveFieldNames);
    }

    /**
     * Remove secrets from logs (Section 47: Logging Security).
     *
     * @param array $logData Log data
     * @return array Sanitized log data
     */
    public static function sanitizeLogData(array $logData): array
    {
        $sanitized = [];

        foreach ($logData as $key => $value) {
            $keyLower = strtolower($key);

            // Redact sensitive fields
            if (in_array($keyLower, self::$sensitiveFieldNames) ||
                preg_match('/(?:api[_-]?key|secret|token|password|credential)/i', $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            // Sanitize string values
            if (is_string($value)) {
                $sanitized[$key] = self::redactSecrets($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a server-side operation (like tool execution) is safe.
     * Verifies credentials are handled server-side, not through AI.
     */
    public static function validateServerSideExecution(string $toolName, array $context): array
    {
        $result = [
            'safe' => true,
            'warnings' => [],
        ];

        // Check if any credential fields are being passed through AI context
        $aiContextKeys = ['message', 'prompt', 'user_input', 'ai_context'];
        foreach ($aiContextKeys as $key) {
            if (!empty($context[$key]) && is_string($context[$key])) {
                $secrets = self::containsSecrets($context[$key]);
                if ($secrets['has_secrets']) {
                    $result['safe'] = false;
                    $result['warnings'][] = 'Secrets detected in AI context — must be handled server-side';
                }
            }
        }

        return $result;
    }

    /**
     * Get sensitive field names list (for UI display).
     */
    public static function getSensitiveFieldNames(): array
    {
        return self::$sensitiveFieldNames;
    }
}
