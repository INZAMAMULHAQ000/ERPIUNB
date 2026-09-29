<?php
/**
 * AI Module Registry.
 *
 * Central registry for ERP modules to describe themselves to the AI Context Engine.
 * Future modules can register here.
 */

class AIModuleRegistry
{
    private static array $modules = [];

    /**
     * Register a module.
     */
    public static function register(string $id, string $name, string $description, array $pages = []): void
    {
        self::$modules[$id] = [
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'pages'       => $pages,
        ];
    }

    /**
     * Get all registered modules.
     */
    public static function getAll(): array
    {
        return self::$modules;
    }

    /**
     * Get a specific module.
     */
    public static function get(string $id): ?array
    {
        return self::$modules[$id] ?? null;
    }
}

// ── Register ERP Modules ──

AIModuleRegistry::register('ai', 'AI', 'AI Assistant, model management, and provider configuration', [
    'assistant'  => 'AI Assistant chat interface',
    'providers'  => 'AI provider management',
    'models'     => 'AI model management',
    'management' => 'AI Management Center',
]);

AIModuleRegistry::register('procurement', 'Procurement', 'Purchase requisitions, suppliers, RFQs and purchase orders', [
    'dashboard'      => 'Procurement dashboard',
    'requisitions'   => 'Purchase requisitions',
    'rfq'            => 'Request for quotations',
    'quotations'     => 'Vendor quotations',
    'reports'        => 'Procurement reports',
]);

AIModuleRegistry::register('finance', 'Finance', 'Financial management, invoices, payments, and budgets', [
    'index'    => 'Finance dashboard',
    'invoices' => 'Invoices',
    'payments' => 'Payments',
]);

AIModuleRegistry::register('hrms', 'HRMS', 'Human resources, employees, attendance, and leave', [
    'employees' => 'Employee management',
    'attendance'=> 'Attendance tracking',
    'leaves'    => 'Leave management',
]);

AIModuleRegistry::register('crm', 'CRM', 'Customer relationship management, leads, clients, and deals', [
    'leads'   => 'Lead management',
    'clients' => 'Client management',
    'deals'   => 'Deal pipeline',
]);

AIModuleRegistry::register('helpdesk', 'Helpdesk', 'Support tickets, SLAs, and knowledge base', [
    'tickets' => 'Support tickets',
    'slas'    => 'SLA management',
]);
