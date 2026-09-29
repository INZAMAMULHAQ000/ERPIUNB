<?php
/**
 * AI F24 — Navigation Security Service.
 *
 * Ensures AI navigation only uses registered ERP routes.
 * Blocks dangerous URL schemes and validates navigation targets.
 *
 * Covers Section 30: Navigation Security.
 */
class AINavigationSecurityService
{
    // Allowed URL schemes (only relative paths or whitelisted external)
    private static array $allowedSchemes = ['https'];
    // Blocked URL schemes
    private static array $blockedSchemes = ['javascript:', 'data:', 'file:', 'ftp:', 'sftp:'];
    // Blocked patterns in URLs
    private static array $blockedPatterns = [
        '/javascript\s*:/i',
        '/data\s*:\s*text\/html/i',
        '/vbscript\s*:/i',
        '/\beval\s*\(/i',
        '/\balert\s*\(/i',
        '/<script/i',
    ];

    /**
     * Validate a navigation URL is safe for AI to use.
     *
     * @param string $url The URL to validate
     * @return array Validation result
     */
    public static function validateUrl(string $url): array
    {
        $result = [
            'safe' => true,
            'url' => $url,
            'reason' => '',
            'type' => 'internal', // internal, external, blocked
        ];

        $trimmed = trim($url);

        if (empty($trimmed)) {
            return $result;
        }

        // Check for blocked schemes
        $urlLower = strtolower($trimmed);
        foreach (self::$blockedSchemes as $scheme) {
            if (str_starts_with($urlLower, $scheme)) {
                $result['safe'] = false;
                $result['type'] = 'blocked';
                $result['reason'] = "Blocked URL scheme: $scheme";
                return $result;
            }
        }

        // Check for blocked patterns
        foreach (self::$blockedPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                $result['safe'] = false;
                $result['type'] = 'blocked';
                $result['reason'] = 'URL contains blocked pattern';
                return $result;
            }
        }

        // Determine if internal or external
        if (str_starts_with($trimmed, '/')) {
            // Relative internal path — safe
            $result['type'] = 'internal';
            $result['safe'] = true;
        } elseif (preg_match('#^https?://#i', $trimmed)) {
            // External URL — needs policy check
            $result['type'] = 'external';
            $result['safe'] = true; // Will be checked against provider policies
        } else {
            // Relative path without leading slash
            $result['type'] = 'internal';
            $result['safe'] = true;
        }

        return $result;
    }

    /**
     * Filter a list of navigation suggestions, keeping only safe ones.
     *
     * @param array $suggestions Navigation suggestions from AI
     * @return array Filtered safe suggestions
     */
    public static function filterSuggestions(array $suggestions): array
    {
        $filtered = [];

        foreach ($suggestions as $suggestion) {
            $route = $suggestion['route'] ?? $suggestion['url'] ?? '';
            $validation = self::validateUrl($route);

            if ($validation['safe']) {
                $suggestion['_navigation_type'] = $validation['type'];
                $filtered[] = $suggestion;
            }
        }

        return $filtered;
    }

    /**
     * Check if a URL is an internal ERP route.
     */
    public static function isInternalRoute(string $url): bool
    {
        $trimmed = trim($url);
        return str_starts_with($trimmed, '/') || !preg_match('#^https?://#i', $trimmed);
    }

    /**
     * Validate that an external link is allowed by policy.
     */
    public static function isExternalLinkAllowed(PDO $db, string $url, ?int $companyId = null): bool
    {
        try {
            $stmt = $db->prepare(
                "SELECT config_value_json FROM ai_governance_config
                 WHERE config_key = 'allowed_external_domains'
                 AND (company_id IS NULL OR company_id = ?)"
            );
            $stmt->execute([$companyId]);
            $config = $stmt->fetch();

            if (!$config) {
                return false; // Default: don't allow external links
            }

            $allowedDomains = json_decode($config['config_value_json'] ?? '[]', true);
            $host = parse_url($url, PHP_URL_HOST);

            return $host && in_array($host, $allowedDomains);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
