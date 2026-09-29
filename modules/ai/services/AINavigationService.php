<?php
/**
 * AI Navigation Service.
 *
 * Provides deterministic navigation search and validation.
 * Accepts natural-language queries and returns relevant accessible destinations.
 *
 * Does NOT use AI providers for search — matching is server-side deterministic.
 */

class AINavigationService
{
    /**
     * Search for navigation destinations matching a natural language query.
     *
     * @param string $query The user's navigation query
     * @param array  $userPermissions User's permission list
     * @param string|null $currentModule User's current module context
     * @param int $maxResults Maximum results to return
     * @return array Ranked list of matching destinations with relevance scores
     */
    public static function search(string $query, array $userPermissions, ?string $currentModule = null, int $maxResults = 5): array
    {
        $accessible = AINavigationRegistry::getAccessible($userPermissions);
        $query = strtolower(trim($query));
        $queryWords = self::tokenize($query);

        $results = [];

        foreach ($accessible as $dest) {
            $score = self::scoreDestination($dest, $query, $queryWords, $currentModule);
            if ($score > 0) {
                $results[] = ['destination' => $dest, 'score' => $score];
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $maxResults);
    }

    /**
     * Validate a destination ID and return resolved URL + metadata.
     *
     * @param string $destinationId The destination ID to validate
     * @param array $userPermissions User's permission list
     * @return array|null Validated destination or null if invalid
     */
    public static function validateDestination(string $destinationId, array $userPermissions): ?array
    {
        $dest = AINavigationRegistry::getById($destinationId);

        if (!$dest) return null;
        if (!$dest['active']) return null;

        // Check permissions
        if (!empty($dest['permissions'])) {
            $hasAccess = false;
            foreach ($dest['permissions'] as $perm) {
                if (in_array($perm, $userPermissions)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) return null;
        }

        return $dest;
    }

    /**
     * Build compact navigation context for the AI system prompt.
     * Only includes accessible destinations grouped by module.
     *
     * @param array $userPermissions User's permission list
     * @return string Compact text representation for AI prompt
     */
    public static function buildNavigationContext(array $userPermissions): string
    {
        $grouped = AINavigationRegistry::getAccessibleGrouped($userPermissions);

        if (empty($grouped)) {
            return "No accessible ERP destinations found for this user.";
        }

        $lines = ['Available ERP destinations (navigation):'];
        $lines[] = '';

        // Module display order
        $moduleOrder = ['ai', 'procurement', 'finance', 'hrms', 'crm', 'helpdesk', 'reports', 'assets', 'projects', 'users', 'roles', 'permissions', 'people_management', 'audit_logs', 'settings'];

        $orderedModules = [];
        foreach ($moduleOrder as $mod) {
            if (isset($grouped[$mod])) {
                $orderedModules[$mod] = $grouped[$mod];
            }
        }
        // Add any remaining modules not in the predefined order
        foreach ($grouped as $mod => $dests) {
            if (!isset($orderedModules[$mod])) {
                $orderedModules[$mod] = $dests;
            }
        }

        foreach ($orderedModules as $module => $dests) {
            $moduleLabel = self::formatModuleName($module);
            $lines[] = $moduleLabel . ':';

            foreach ($dests as $dest) {
                $desc = $dest['description'] ?? '';
                $lines[] = '- ' . $dest['name'] . ': ' . $desc . ' [id=' . $dest['id'] . ']';
            }
            $lines[] = '';
        }

        $lines[] = 'Navigation rules:';
        $lines[] = '- When directing a user to a page, use the [id] value from the list above.';
        $lines[] = '- Only use destinations listed here. Never invent URLs or page names.';
        $lines[] = '- Never claim a page exists if it is not listed above.';
        $lines[] = '- For direct navigation requests (e.g., "Take me to suppliers"), include a navigation suggestion with the matching destination_id.';
        $lines[] = '- For guidance requests (e.g., "Where do I create a supplier?"), explain the location and provide a navigation suggestion.';
        $lines[] = '- Navigation suggestions must be formatted as JSON: {"navigation_suggestions":[{"destination_id":"...","label":"...","reason":"..."}]}';
        $lines[] = '- Always include navigation_suggestions in your response when a relevant destination exists.';
        $lines[] = '- Do not automatically redirect users. They must click to navigate.';
        $lines[] = '- You cannot read, modify, or create ERP records. You can only guide navigation.';

        return implode("\n", $lines);
    }

    /**
     * Score a destination against a query.
     */
    private static function scoreDestination(array $dest, string $query, array $queryWords, ?string $currentModule): float
    {
        $score = 0.0;
        $name = strtolower($dest['name']);
        $desc = strtolower($dest['description'] ?? '');
        $keywords = array_map('strtolower', $dest['keywords'] ?? []);
        $synonyms = array_map('strtolower', $dest['synonyms'] ?? []);
        $section = strtolower($dest['section'] ?? '');
        $module = strtolower($dest['module'] ?? '');

        // 1. Exact name match (highest score)
        if ($query === $name) {
            $score += 100;
        }

        // 2. Name starts with query
        if (strpos($name, $query) === 0) {
            $score += 80;
        }

        // 3. Name contains query
        if (strpos($name, $query) !== false) {
            $score += 60;
        }

        // 4. Keyword exact match
        foreach ($keywords as $kw) {
            if ($query === $kw) {
                $score += 90;
                break;
            }
        }

        // 5. Keyword contains query
        foreach ($keywords as $kw) {
            if (strpos($kw, $query) !== false || strpos($query, $kw) !== false) {
                $score += 50;
                break;
            }
        }

        // 6. Synonym match
        foreach ($synonyms as $syn) {
            if (strpos($syn, $query) !== false || strpos($query, $syn) !== false) {
                $score += 55;
                break;
            }
        }

        // 7. Word-level matching
        foreach ($queryWords as $word) {
            if (strlen($word) < 3) continue; // Skip very short words

            foreach ($keywords as $kw) {
                if ($kw === $word || strpos($kw, $word) !== false || strpos($word, $kw) !== false) {
                    $score += 20;
                    break;
                }
            }

            foreach ($synonyms as $syn) {
                if (strpos($syn, $word) !== false) {
                    $score += 15;
                    break;
                }
            }
        }

        // 8. Module name match
        if (strpos($module, $query) !== false || strpos($query, $module) !== false) {
            $score += 25;
        }

        // 9. Description match (lower priority)
        if (strpos($desc, $query) !== false) {
            $score += 30;
        }

        foreach ($queryWords as $word) {
            if (strlen($word) < 3) continue;
            if (strpos($desc, $word) !== false) {
                $score += 10;
            }
        }

        // 10. Current module boost — prefer destinations in the user's current module
        if ($currentModule && $module === strtolower($currentModule)) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Tokenize a query into meaningful words.
     */
    private static function tokenize(string $query): array
    {
        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'to', 'in', 'on', 'at', 'for', 'of', 'is', 'i', 'want', 'need',
                       'me', 'my', 'do', 'can', 'how', 'where', 'what', 'which', 'go', 'take',
                       'open', 'show', 'view', 'see', 'manage', 'create', 'make', 'new', 'add'];

        $words = preg_split('/[\s\-_\/]+/', strtolower($query));
        $words = array_filter($words, fn($w) => strlen($w) >= 2 && !in_array($w, $stopWords));

        return array_values($words);
    }

    /**
     * Format a module ID into a human-readable name.
     */
    private static function formatModuleName(string $module): string
    {
        $names = [
            'ai'               => 'AI',
            'procurement'      => 'Procurement',
            'finance'          => 'Finance',
            'hrms'             => 'HRMS',
            'crm'              => 'CRM',
            'helpdesk'         => 'Helpdesk',
            'reports'          => 'Reports & Analytics',
            'assets'           => 'Assets',
            'projects'         => 'Projects',
            'users'            => 'Users & Roles',
            'roles'            => 'Users & Roles',
            'permissions'      => 'Users & Roles',
            'people_management' => 'People',
            'audit_logs'       => 'Audit Logs',
            'settings'         => 'Settings',
            'dashboard'        => 'Dashboard',
        ];

        return $names[$module] ?? ucfirst(str_replace('_', ' ', $module));
    }

    /**
     * Format navigation suggestions for the AI response.
     *
     * @param array $suggestions Array of ['destination_id' => ..., 'label' => ..., 'reason' => ...]
     * @param array $userPermissions
     * @return array Validated and formatted suggestions
     */
    public static function formatSuggestions(array $suggestions, array $userPermissions): array
    {
        $validated = [];

        foreach ($suggestions as $suggestion) {
            $destId = $suggestion['destination_id'] ?? '';
            $dest = self::validateDestination($destId, $userPermissions);

            if ($dest) {
                $validated[] = [
                    'destination_id' => $dest['id'],
                    'label'          => $suggestion['label'] ?? $dest['name'],
                    'reason'         => $suggestion['reason'] ?? $dest['description'],
                    'module'         => $dest['module'],
                    'url'            => $dest['url'],
                    'icon'           => $dest['icon'] ?? 'fas fa-arrow-right',
                ];
            }
        }

        return $validated;
    }
}
