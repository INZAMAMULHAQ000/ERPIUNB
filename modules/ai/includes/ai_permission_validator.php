<?php
/**
 * AI Permission Validator
 * 
 * Validates user permissions for AI module operations.
 */

class AIPermissionValidator
{
    /**
     * Check if current user has a specific AI permission.
     */
    public static function hasPermission(string $permission): bool
    {
        if (function_exists('hasPermission')) {
            return hasPermission($permission);
        }
        return false;
    }

    /**
     * Get all permissions the current user has.
     */
    public static function getUserPermissions(): array
    {
        if (function_exists('getUserPermissions')) {
            return getUserPermissions();
        }
        return [];
    }

    /**
     * Check if user can access AI assistant.
     */
    public static function canAccessAssistant(): bool
    {
        return self::hasPermission('ai.assistant.access');
    }

    /**
     * Check if user can manage AI settings.
     */
    public static function canManageSettings(): bool
    {
        return self::hasPermission('ai.settings.manage');
    }

    /**
     * Check if user can view providers.
     */
    public static function canViewProviders(): bool
    {
        return self::hasPermission('ai.providers.view');
    }

    /**
     * Check if user can manage providers.
     */
    public static function canManageProviders(): bool
    {
        return self::hasPermission('ai.providers.create') ||
               self::hasPermission('ai.providers.update') ||
               self::hasPermission('ai.providers.delete');
    }

    /**
     * Check if user can view models.
     */
    public static function canViewModels(): bool
    {
        return self::hasPermission('ai.models.view');
    }

    /**
     * Check if user can manage models.
     */
    public static function canManageModels(): bool
    {
        return self::hasPermission('ai.models.manage');
    }

    /**
     * Check if user can view knowledge base.
     */
    public static function canViewKnowledge(): bool
    {
        return self::hasPermission('ai.knowledge.view');
    }

    /**
     * Check if user can manage knowledge base.
     */
    public static function canManageKnowledge(): bool
    {
        return self::hasPermission('ai.knowledge.manage');
    }

    /**
     * Check if user can search knowledge base.
     */
    public static function canSearchKnowledge(): bool
    {
        return self::hasPermission('ai.knowledge.search');
    }

    public static function canViewWorkflows(): bool
    {
        return self::hasPermission('ai.workflows.view');
    }

    public static function canCreateWorkflows(): bool
    {
        return self::hasPermission('ai.workflows.create');
    }

    public static function canEditWorkflows(): bool
    {
        return self::hasPermission('ai.workflows.edit');
    }

    public static function canRunWorkflows(): bool
    {
        return self::hasPermission('ai.workflows.run');
    }

    /**
     * Get accessible modules for the current user.
     */
    public static function getAccessibleModules(): array
    {
        $permissions = self::getUserPermissions();
        $modules = [];
        
        // Map permissions to modules
        $moduleMap = [
            'ai'            => 'ai.assistant.access',
            'procurement'   => 'procurement.view',
            'users'         => 'users.view',
            'roles'         => 'roles.view',
            'permissions'   => 'permissions.view',
            'audit'         => 'audit.view',
            'hrms'          => 'employees.view',
            'projects'      => 'projects.view',
            'crm'           => 'leads.view',
            'finance'       => 'finance.view',
            'assets'        => 'assets.view',
            'helpdesk'      => 'tickets.view',
            'reports'       => 'reports.view',
        ];
        
        foreach ($moduleMap as $module => $perm) {
            if (in_array($perm, $permissions)) {
                $modules[] = $module;
            }
        }
        
        return $modules;
    }
}
