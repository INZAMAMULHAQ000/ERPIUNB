<?php
/**
 * AI F9 — User Action Handlers.
 *
 * Implements Create User actions for the ERP users module.
 */

AIActionRegistry::register([
    'id'                    => 'users.create',
    'name'                  => 'Create User',
    'description'           => 'Create a new ERP user account. Requires name, email, and password. Optional roles can be assigned.',
    'module'                => 'users',
    'category'              => 'create',
    'risk_level'            => AI_ACTION_RISK_MEDIUM,
    'required_permissions'  => ['users.create'],
    'requires_confirmation' => true,
    'required_fields'       => ['name', 'email', 'password'],
    'input_schema'          => [
        'type'       => 'object',
        'properties' => [
            'name'       => ['type' => 'string', 'description' => 'Full user name'],
            'email'      => ['type' => 'string', 'description' => 'Login email address'],
            'password'   => ['type' => 'string', 'description' => 'Initial login password'],
            'status'     => ['type' => 'string', 'enum' => ['active', 'inactive', 'suspended'], 'description' => 'Account status'],
            'role_ids'   => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Role IDs to assign to the user'],
            'role_names' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Role names to resolve and assign'],
            'notes'      => ['type' => 'string', 'description' => 'Optional notes or context'],
        ],
        'required' => ['name', 'email', 'password'],
    ],
    'handler_class'         => 'AIActionCreateUser',
]);

class AIActionCreateUser extends AIActionHandlerBase
{
    private function fetchRoleCatalog(): array
    {
        try {
            $stmt = $this->db->query("SELECT id, name FROM roles WHERE deleted_at IS NULL ORDER BY name ASC");
            $roles = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return is_array($roles) ? $roles : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function normalizeList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $value)), static fn($v) => $v !== ''));
        }

        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[,;|]+/', $value) ?: []), static fn($v) => $v !== ''));
        }

        if (is_numeric($value)) {
            return [(string) $value];
        }

        return [];
    }

    private function resolveRoleIds(array $params): array
    {
        $roles = $this->fetchRoleCatalog();
        if (empty($roles)) {
            return [];
        }

        $byId = [];
        $byName = [];
        foreach ($roles as $role) {
            $id = (int) ($role['id'] ?? 0);
            $name = strtolower(trim((string) ($role['name'] ?? '')));
            if ($id > 0) {
                $byId[$id] = $id;
            }
            if ($name !== '') {
                $byName[$name] = $id;
            }
        }

        $resolved = [];
        foreach ($this->normalizeList($params['role_ids'] ?? null) as $roleId) {
            $id = (int) $roleId;
            if ($id > 0 && isset($byId[$id])) {
                $resolved[$id] = true;
            }
        }

        foreach ($this->normalizeList($params['role_names'] ?? ($params['roles'] ?? null)) as $roleName) {
            $needle = strtolower(trim($roleName));
            if ($needle === '') {
                continue;
            }

            if (isset($byName[$needle])) {
                $resolved[(int) $byName[$needle]] = true;
                continue;
            }

            foreach ($roles as $role) {
                $roleId = (int) ($role['id'] ?? 0);
                $roleLabel = strtolower(trim((string) ($role['name'] ?? '')));
                if ($roleId > 0 && ($roleLabel === $needle || str_contains($roleLabel, $needle) || str_contains($needle, $roleLabel))) {
                    $resolved[$roleId] = true;
                    break;
                }
            }
        }

        return array_values(array_map('intval', array_keys($resolved)));
    }

    private function getRoleNames(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        try {
            $stmt = $this->db->prepare("SELECT name FROM roles WHERE id IN ($placeholders) AND deleted_at IS NULL ORDER BY name ASC");
            $stmt->execute(array_values(array_map('intval', $roleIds)));
            return array_values(array_filter(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name')));
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function validateInput(array $params): array
    {
        $name = $this->sanitizeInput((string) ($params['name'] ?? $params['full_name'] ?? ''), 100);
        $email = $this->sanitizeInput((string) ($params['email'] ?? ''), 255);
        $password = (string) ($params['password'] ?? '');
        $status = strtolower(trim((string) ($params['status'] ?? 'active')));

        if ($name === '' || mb_strlen($name) < 2) {
            return ['valid' => false, 'error' => 'User name is required.', 'cleaned' => []];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'A valid email address is required.', 'cleaned' => []];
        }
        if (mb_strlen($password) < 6) {
            return ['valid' => false, 'error' => 'A password of at least 6 characters is required.', 'cleaned' => []];
        }

        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $status = 'active';
        }

        $checkStmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            return ['valid' => false, 'error' => 'A user with this email already exists.', 'cleaned' => []];
        }

        $cleaned = [
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'status'    => $status,
            'role_ids'  => $this->resolveRoleIds($params),
            'notes'     => $this->sanitizeInput((string) ($params['notes'] ?? ''), 500),
        ];

        return ['valid' => true, 'error' => null, 'cleaned' => $cleaned];
    }

    public function buildPreview(array $params): array
    {
        $roleNames = $this->getRoleNames($params['role_ids'] ?? []);

        $fields = [
            ['label' => 'Name', 'value' => $params['name']],
            ['label' => 'Email', 'value' => $params['email']],
            ['label' => 'Status', 'value' => ucfirst((string) ($params['status'] ?? 'active'))],
            ['label' => 'Password', 'value' => 'Provided and will be hashed securely'],
        ];

        $fields[] = [
            'label' => 'Roles',
            'value' => !empty($roleNames) ? implode(', ', $roleNames) : 'No roles selected',
        ];

        $warnings = [];
        if (empty($roleNames)) {
            $warnings[] = 'No roles are assigned. The user will be created without extra role-based permissions.';
        }
        if (($params['status'] ?? 'active') !== 'active') {
            $warnings[] = 'The account will be created with a non-active status.';
        }

        return [
            'title'    => 'Create New User',
            'fields'   => $fields,
            'warnings' => $warnings,
        ];
    }

    public function execute(array $params): array
    {
        try {
            $this->db->beginTransaction();

            $insert = $this->db->prepare("
                INSERT INTO users (name, email, password, status, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $params['name'],
                $params['email'],
                password_hash($params['password'], PASSWORD_DEFAULT),
                $params['status'] ?: 'active',
                $this->userId,
            ]);

            $userId = (int) $this->db->lastInsertId();

            if (!empty($params['role_ids']) && is_array($params['role_ids'])) {
                $roleStmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach (array_unique(array_map('intval', $params['role_ids'])) as $roleId) {
                    if ($roleId > 0) {
                        $roleStmt->execute([$userId, $roleId]);
                    }
                }
            }

            $this->db->commit();

            if (function_exists('logAudit')) {
                logAudit($this->userId, 'create', 'users');
            }

            $roleNames = $this->getRoleNames($params['role_ids'] ?? []);
            $message = "User created successfully.\n\nName: {$params['name']}\nEmail: {$params['email']}\nID: {$userId}\nStatus: " . ucfirst((string) ($params['status'] ?? 'active'));
            if (!empty($roleNames)) {
                $message .= "\nRoles: " . implode(', ', $roleNames);
            }

            return [
                'success'   => true,
                'data'      => $message,
                'error'     => null,
                'record_id' => $userId,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success'   => false,
                'data'      => null,
                'error'     => 'Failed to create user: ' . $e->getMessage(),
                'record_id' => null,
            ];
        }
    }
}
