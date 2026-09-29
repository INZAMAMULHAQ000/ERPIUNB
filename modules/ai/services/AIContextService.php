<?php
/**
 * AI Context Service.
 *
 * Builds normalized ERP context for AI requests.
 * Reusable by AI Assistant, Agents, Tools, and Automation.
 */

class AIContextService
{
    /**
     * Build the full ERP context for the current request.
     *
     * @param PDO $db Database connection
     * @param int $userId Authenticated user ID
     * @param array $pageContext Optional frontend page context
     * @return array Normalized context structure
     */
    public static function buildContext(PDO $db, int $userId, array $pageContext = []): array
    {
        return [
            'user'        => self::getUserContext($db, $userId),
            'company'     => self::getCompanyContext($db, $userId),
            'location'    => self::getLocationContext($pageContext),
            'record'      => self::getRecordContext($pageContext),
            'permissions' => self::getPermissionContext($db, $userId),
            'modules'     => self::getModuleRegistry(),
        ];
    }

    /**
     * Build user context from authenticated session.
     */
    private static function getUserContext(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $roles = [];
        $roleStmt = $db->prepare("
            SELECT r.name FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $roleStmt->execute([$userId]);
        foreach ($roleStmt->fetchAll() as $r) {
            $roles[] = $r['name'];
        }

        return [
            'id'    => $user['id'] ?? null,
            'name'  => $user['name'] ?? 'Unknown',
            'roles' => $roles,
        ];
    }

    /**
     * Build company/tenant context.
     */
    private static function getCompanyContext(PDO $db, int $userId): array
    {
        // Check if ERP has company/tenant support
        try {
            $stmt = $db->prepare("
                SELECT c.id, c.company_name
                FROM companies c
                JOIN user_companies uc ON c.id = uc.company_id
                WHERE uc.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $company = $stmt->fetch();

            if ($company) {
                return ['id' => (int) $company['id'], 'name' => $company['company_name']];
            }
        } catch (Exception $e) {
            // Table may not exist — return empty
        }

        return ['id' => null, 'name' => null];
    }

    /**
     * Build location context from frontend page context.
     */
    private static function getLocationContext(array $pageContext): array
    {
        return [
            'module' => isset($pageContext['module']) ? self::sanitizeIdentifier($pageContext['module']) : null,
            'page'   => isset($pageContext['page']) ? self::sanitizeIdentifier($pageContext['page']) : null,
            'route'  => isset($pageContext['route']) ? self::sanitizeIdentifier($pageContext['route']) : null,
        ];
    }

    /**
     * Build record context from frontend page context.
     */
    private static function getRecordContext(array $pageContext): array
    {
        $record = $pageContext['record'] ?? [];

        $id = isset($record['id']) ? (int) $record['id'] : null;
        if ($id !== null && ($id <= 0 || $id > 10000000)) $id = null; // Sanity check

        return [
            'type'      => isset($record['type']) ? self::sanitizeIdentifier($record['type']) : null,
            'id'        => $id,
            'reference' => isset($record['reference']) ? self::sanitizeText($record['reference'], 100) : null,
        ];
    }

    /**
     * Build permission context — normalized list of user capabilities.
     */
    private static function getPermissionContext(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY perm
        ");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'perm');
    }

    /**
     * Get the module registry — describes available ERP modules to AI.
     */
    public static function getModuleRegistry(): array
    {
        return AIModuleRegistry::getAll();
    }

    /**
     * Format context as a structured string for AI system prompt.
     */
    public static function formatContextForAI(array $context): string
    {
        $lines = ['Current ERP Context:'];
        $lines[] = '';

        // User
        $lines[] = 'User:';
        $lines[] = '- Name: ' . ($context['user']['name'] ?? 'Unknown');
        if (!empty($context['user']['roles'])) {
            $lines[] = '- Roles: ' . implode(', ', $context['user']['roles']);
        }
        $lines[] = '';

        // Company
        if (!empty($context['company']['name'])) {
            $lines[] = 'Company: ' . $context['company']['name'];
            $lines[] = '';
        }

        // Location
        if (!empty($context['location']['module'])) {
            $lines[] = 'Current Location:';
            $lines[] = '- Module: ' . ucfirst($context['location']['module']);
            if (!empty($context['location']['page'])) {
                $lines[] = '- Page: ' . ucfirst(str_replace('_', ' ', $context['location']['page']));
            }
            if (!empty($context['location']['route'])) {
                $lines[] = '- Route: ' . $context['location']['route'];
            }
            $lines[] = '';
        }

        // Record
        if (!empty($context['record']['type'])) {
            $lines[] = 'Current Record:';
            $lines[] = '- Type: ' . ucfirst(str_replace('_', ' ', $context['record']['type']));
            if (!empty($context['record']['reference'])) {
                $lines[] = '- Reference: ' . $context['record']['reference'];
            }
            $lines[] = '';
        }

        // Available modules
        if (!empty($context['modules'])) {
            $lines[] = 'Available ERP Modules:';
            foreach ($context['modules'] as $mod) {
                $lines[] = '- ' . $mod['name'] . ': ' . $mod['description'];
            }
            $lines[] = '';
        }

        $lines[] = 'Important:';
        $lines[] = '- This context describes the user\'s current ERP environment.';
        $lines[] = '- Do not claim to have accessed or modified data beyond the provided context.';
        $lines[] = '- Do not fabricate database details that have not been provided.';
        $lines[] = '- You can reference the current location and record in your responses.';

        return implode("\n", $lines);
    }

    /**
     * Sanitize an identifier (module, page, etc.)
     */
    private static function sanitizeIdentifier(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', substr($value, 0, 50));
    }

    /**
     * Sanitize free text.
     */
    private static function sanitizeText(string $value, int $maxLen = 200): string
    {
        return substr(trim($value), 0, $maxLen);
    }
}
