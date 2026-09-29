<?php
require_once __DIR__ . '/../config/database.php';

function hasRole($role) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.name FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ? AND r.name = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $role]);
    return $stmt->fetch() !== false;
}

function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $originalPermission = $permission;
    
    // Handle fixed_assets module permissions (fixedasset.* -> fixed_assets.fixedasset.*)
    // This must be checked first, before the dot check, because fixedasset.* permissions contain dots
    if (str_starts_with($permission, 'fixedasset')) {
        $permission = 'fixed_assets.' . $permission;
    }
    // If no dot and no module prefix, try to infer from known modules
    elseif (!str_contains($permission, '.')) {
        $moduleMap = [
            'dashboard' => 'dashboard',
            'users' => 'users',
            'roles' => 'roles',
            'permissions' => 'permissions',
            'departments' => 'departments',
            'designations' => 'designations',
            'employees' => 'employees',
            'attendance' => 'attendance',
            'leaves' => 'leaves',
            'holidays' => 'holidays',
            'projects' => 'projects',
            'tasks' => 'tasks',
            'timesheets' => 'timesheets',
            'milestones' => 'milestones',
            'leads' => 'leads',
            'clients' => 'clients',
            'deals' => 'deals',
            'invoices' => 'invoices',
            'payments' => 'payments',
            'expenses' => 'expenses',
            'vendors' => 'vendors',
            'inventory' => 'inventory',
            'helpdesk' => 'helpdesk',
            'tickets' => 'tickets',
            'ticket_categories' => 'ticket_categories',
            'slas' => 'slas',
            'knowledgebase' => 'knowledgebase',
            'reports' => 'reports',
            'analytics' => 'analytics',
            'auditlogs' => 'auditlogs',
            'scheduled_reports' => 'scheduled_reports',
            'settings' => 'settings',
            'people_management' => 'people_management',
            'leave_management' => 'leave_management',
            'widgets' => 'widgets',
            'template_designer' => 'template_designer',
            'finance' => 'finance',
            'assets' => 'assets',
            'asset_assignments' => 'asset_assignments',
            'asset_returns' => 'asset_returns',
            'maintenance' => 'maintenance',
            'licenses' => 'licenses',
        ];
        
        foreach ($moduleMap as $key => $module) {
            if (str_starts_with($permission, $key . '.')) {
                $permission = $module . '.' . $permission;
                break;
            }
        }
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT CONCAT(p.module, '.', p.name) as perm FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $perms = array_column($stmt->fetchAll(), 'perm');
    
    return in_array($permission, $perms);
}

function getUserRoles($userId = null) {
    $userId = $userId ?? $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return [];
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.id, r.name FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getUserPermissions($userId = null) {
    $userId = $userId ?? $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return [];
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'perm');
}

function requireRole($role) {
    if (!hasRole($role)) {
        die('Access Denied: Role required');
    }
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        die('Access Denied: Permission required');
    }
}
