<?php
/**
 * AI Navigation Registry.
 *
 * Centralized, AI-readable registry of all navigable ERP destinations.
 * Each entry contains metadata the AI can use to understand and guide navigation.
 *
 * Future modules register their pages here. The registry is permission-aware:
 * only destinations the current user can access are included in AI context.
 */

class AINavigationRegistry
{
    private static array $destinations = [];
    private static bool $registered = false;

    /**
     * Register all ERP destinations.
     */
    public static function init(): void
    {
        if (self::$registered) return;
        self::$registered = true;

        // ── Dashboard ──
        self::add([
            'id'          => 'dashboard',
            'module'      => 'dashboard',
            'section'     => null,
            'name'        => 'Dashboard',
            'description' => 'Main ERP dashboard with overview widgets',
            'url'         => 'index.php',
            'icon'        => 'fas fa-home',
            'keywords'    => ['dashboard', 'home', 'overview', 'main', 'landing', 'start'],
            'synonyms'    => ['main page', 'home page', 'front page'],
            'permissions' => [],
            'active'      => true,
        ]);

        // ── AI Module ──
        self::add([
            'id'          => 'ai.assistant',
            'module'      => 'ai',
            'section'     => 'assistant',
            'name'        => 'AI Assistant',
            'description' => 'Chat with AI models through configured providers',
            'url'         => 'modules/ai/assistant/index.php',
            'icon'        => 'fas fa-robot',
            'keywords'    => ['assistant', 'chat', 'ai', 'bot', 'help', 'ask', 'conversation', 'talk'],
            'synonyms'    => ['ai chat', 'ai help', 'ai bot', 'virtual assistant'],
            'permissions' => ['ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.management',
            'module'      => 'ai',
            'section'     => 'management',
            'name'        => 'AI Management Center',
            'description' => 'Central hub for managing all AI resources',
            'url'         => 'modules/ai/management/index.php',
            'icon'        => 'fas fa-cogs',
            'keywords'    => ['management', 'center', 'hub', 'admin', 'configure'],
            'synonyms'    => ['ai admin', 'ai settings', 'ai management'],
            'permissions' => [],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.command_center',
            'module'      => 'ai',
            'section'     => 'command_center',
            'name'        => 'AI Command Center',
            'description' => 'Universal natural-language ERP control and navigation',
            'url'         => 'modules/ai/command-center/index.php',
            'icon'        => 'fas fa-terminal',
            'keywords'    => ['command center', 'natural language', 'erp control', 'navigate', 'commands'],
            'synonyms'    => ['ai command center', 'universal ai control'],
            'permissions' => ['ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.copilot',
            'module'      => 'ai',
            'section'     => 'copilot',
            'name'        => 'AI Copilot',
            'description' => 'Cross-module copilot experience for guided ERP actions',
            'url'         => 'modules/ai/copilot/index.php',
            'icon'        => 'fas fa-robot',
            'keywords'    => ['copilot', 'assistant', 'guided actions', 'cross-module'],
            'synonyms'    => ['ai copilot', 'universal copilot'],
            'permissions' => ['ai.copilot.use'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.insights',
            'module'      => 'ai',
            'section'     => 'insights',
            'name'        => 'AI Insights',
            'description' => 'Proactive insight feed and recommendations',
            'url'         => 'modules/ai/insights/index.php',
            'icon'        => 'fas fa-lightbulb',
            'keywords'    => ['insights', 'recommendations', 'notifications', 'proactive'],
            'synonyms'    => ['ai insight center', 'insight feed'],
            'permissions' => ['ai.insights.view', 'ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.analytics',
            'module'      => 'ai',
            'section'     => 'analytics',
            'name'        => 'AI Analytics',
            'description' => 'Business analytics, forecasting, and decision intelligence',
            'url'         => 'modules/ai/analytics/index.php',
            'icon'        => 'fas fa-chart-line',
            'keywords'    => ['analytics', 'forecasting', 'decision intelligence', 'kpi'],
            'synonyms'    => ['ai analytics center', 'analytics dashboard'],
            'permissions' => [],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.communications',
            'module'      => 'ai',
            'section'     => 'communications',
            'name'        => 'AI Communications',
            'description' => 'Business correspondence, drafting, translation, and summaries',
            'url'         => 'modules/ai/communications/index.php',
            'icon'        => 'fas fa-comments',
            'keywords'    => ['communications', 'correspondence', 'drafting', 'translation', 'summary'],
            'synonyms'    => ['ai communications center', 'business correspondence'],
            'permissions' => [],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.workspaces',
            'module'      => 'ai',
            'section'     => 'workspaces',
            'name'        => 'AI Workspaces',
            'description' => 'Collaborative AI workspaces for conversations and research',
            'url'         => 'modules/ai/workspaces/index.php',
            'icon'        => 'fas fa-users',
            'keywords'    => ['workspaces', 'collaboration', 'research', 'shared context'],
            'synonyms'    => ['ai workspaces', 'collaborative ai'],
            'permissions' => ['ai.workspaces.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.skills',
            'module'      => 'ai',
            'section'     => 'skills',
            'name'        => 'AI Skills',
            'description' => 'Skill catalog, execution, and analytics',
            'url'         => 'modules/ai/skills/index.php',
            'icon'        => 'fas fa-star',
            'keywords'    => ['skills', 'skill catalog', 'skill analytics'],
            'synonyms'    => ['ai skills', 'skill management'],
            'permissions' => [],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.intelligence',
            'module'      => 'ai',
            'section'     => 'intelligence',
            'name'        => 'AI Intelligence',
            'description' => 'Proactive detections, recommendations, and briefing insights',
            'url'         => 'modules/ai/intelligence/index.php',
            'icon'        => 'fas fa-radar',
            'keywords'    => ['intelligence', 'alerts', 'recommendations', 'briefing'],
            'synonyms'    => ['ai intelligence center', 'proactive intelligence'],
            'permissions' => ['ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.providers',
            'module'      => 'ai',
            'section'     => 'providers',
            'name'        => 'AI Providers',
            'description' => 'Configure AI providers like OpenAI, OpenRouter, NVIDIA',
            'url'         => 'modules/ai/providers/index.php',
            'icon'        => 'fas fa-server',
            'keywords'    => ['provider', 'providers', 'openai', 'openrouter', 'nvidia', 'api', 'connection', 'credentials'],
            'synonyms'    => ['ai providers', 'model providers', 'api providers', 'llm providers'],
            'permissions' => ['ai.providers.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.models',
            'module'      => 'ai',
            'section'     => 'models',
            'name'        => 'AI Models',
            'description' => 'Manage available AI models and their capabilities',
            'url'         => 'modules/ai/models/index.php',
            'icon'        => 'fas fa-brain',
            'keywords'    => ['model', 'models', 'gpt', 'claude', 'llama', 'capabilities', 'chat', 'completion'],
            'synonyms'    => ['ai models', 'llm models', 'language models'],
            'permissions' => ['ai.models.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.tasks',
            'module'      => 'ai',
            'section'     => 'tasks',
            'name'        => 'AI Tasks',
            'description' => 'Create and manage multi-step AI tasks',
            'url'         => 'modules/ai/tasks/index.php',
            'icon'        => 'fas fa-list-check',
            'keywords'    => ['task', 'tasks', 'multi-step', 'plan', 'planning', 'workflow'],
            'synonyms'    => ['ai tasks', 'task management', 'task planner'],
            'permissions' => ['ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.automations',
            'module'      => 'ai',
            'section'     => 'automations',
            'name'        => 'AI Automations',
            'description' => 'Create reusable automation workflows',
            'url'         => 'modules/ai/automations/index.php',
            'icon'        => 'fas fa-bolt',
            'keywords'    => ['automation', 'automations', 'workflow', 'reusable', 'repeat'],
            'synonyms'    => ['ai automations', 'workflow automation', 'reusable workflows'],
            'permissions' => ['ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.workflows',
            'module'      => 'ai',
            'section'     => 'workflows',
            'name'        => 'AI Workflow Studio',
            'description' => 'Build safe visual ERP workflows with versioning, testing, and approval controls',
            'url'         => 'modules/ai/workflows/index.php',
            'icon'        => 'fas fa-diagram-project',
            'keywords'    => ['workflow', 'workflow studio', 'automation studio', 'builder', 'visual automation'],
            'synonyms'    => ['ai workflow studio', 'visual workflow builder', 'workflow builder'],
            'permissions' => ['ai.workflows.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.process_intelligence',
            'module'      => 'ai',
            'section'     => 'process_intelligence',
            'name'        => 'AI Process Intelligence',
            'description' => 'Grounded process discovery, bottleneck analysis, simulations, and governed workflow improvement proposals',
            'url'         => 'modules/ai/process-intelligence/index.php',
            'icon'        => 'fas fa-diagram-project',
            'keywords'    => ['process', 'process intelligence', 'bottleneck', 'sla', 'cycle time', 'rework', 'approval delay', 'workflow optimization'],
            'synonyms'    => ['workflow optimization', 'process bottlenecks', 'process analysis', 'process explorer'],
            'permissions' => ['ai.process_intelligence.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.agents',
            'module'      => 'ai',
            'section'     => 'agents',
            'name'        => 'AI Agents',
            'description' => 'Configure and manage AI agents',
            'url'         => 'modules/ai/agents/index.php',
            'icon'        => 'fas fa-user-astronaut',
            'keywords'    => ['agent', 'agents', 'automated', 'automation agent'],
            'synonyms'    => ['ai agents', 'automated agents'],
            'permissions' => ['ai.assistant.access'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.knowledge',
            'module'      => 'ai',
            'section'     => 'knowledge',
            'name'        => 'AI Knowledge',
            'description' => 'Manage knowledge bases for AI context',
            'url'         => 'modules/ai/knowledge/index.php',
            'icon'        => 'fas fa-book',
            'keywords'    => ['knowledge', 'knowledge base', 'documents', 'rag', 'context', 'kb', 'docs', 'reference', 'articles'],
            'synonyms'    => ['ai knowledge', 'knowledge base', 'doc store', 'documentation', 'reference material'],
            'permissions' => ['ai.knowledge.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.documents',
            'module'      => 'ai',
            'section'     => 'documents',
            'name'        => 'AI Document Intelligence',
            'description' => 'Upload, search, compare, and review business documents with AI assistance',
            'url'         => 'modules/ai/documents/index.php',
            'icon'        => 'fas fa-file-alt',
            'keywords'    => ['document', 'documents', 'ocr', 'quotation', 'invoice', 'contract', 'compare', 'qna'],
            'synonyms'    => ['document intelligence', 'ai documents', 'document qna'],
            'permissions' => ['ai.documents.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.voice',
            'module'      => 'ai',
            'section'     => 'voice',
            'name'        => 'AI Voice Interface',
            'description' => 'Push-to-talk voice input, transcription preview, and spoken responses',
            'url'         => 'modules/ai/voice/index.php',
            'icon'        => 'fas fa-microphone',
            'keywords'    => ['voice', 'speech', 'microphone', 'transcription', 'dictation', 'tts', 'stt'],
            'synonyms'    => ['voice interface', 'speech input', 'voice commands'],
            'permissions' => ['ai.voice.use'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.integrations',
            'module'      => 'ai',
            'section'     => 'integrations',
            'name'        => 'AI Integrations',
            'description' => 'Manage external connectors, connections, webhooks, sync jobs, and integration policies',
            'url'         => 'modules/ai/integrations/index.php',
            'icon'        => 'fas fa-plug',
            'keywords'    => ['integration', 'integrations', 'connectors', 'connections', 'webhooks', 'sync', 'rest api', 'external services', 'calendar', 'email'],
            'synonyms'    => ['integration center', 'external connectors', 'connector management'],
            'permissions' => ['ai.integrations.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.observability',
            'module'      => 'ai',
            'section'     => 'observability',
            'name'        => 'AI Observability',
            'description' => 'Observe request traces, failures, evaluations, feedback, experiments, and AI quality reports',
            'url'         => 'modules/ai/observability/index.php',
            'icon'        => 'fas fa-wave-square',
            'keywords'    => ['observability', 'traces', 'evaluations', 'feedback', 'benchmarks', 'quality', 'failures', 'reports'],
            'synonyms'    => ['ai observability', 'ai tracing', 'ai evaluations', 'ai quality center'],
            'permissions' => ['ai.observability.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.tools',
            'module'      => 'ai',
            'section'     => 'tools',
            'name'        => 'AI Tools',
            'description' => 'Configure AI tools and function calling',
            'url'         => 'modules/ai/tools/index.php',
            'icon'        => 'fas fa-wrench',
            'keywords'    => ['tool', 'tools', 'function', 'functions', 'capabilities'],
            'synonyms'    => ['ai tools', 'function calling'],
            'permissions' => ['ai.tools.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.automation',
            'module'      => 'ai',
            'section'     => 'automation',
            'name'        => 'AI Automation',
            'description' => 'Configure AI automation workflows',
            'url'         => 'modules/ai/automation/index.php',
            'icon'        => 'fas fa-bolt',
            'keywords'    => ['automation', 'workflow', 'automated', 'triggers'],
            'synonyms'    => ['ai automation', 'automated workflows'],
            'permissions' => ['ai.automation.view', 'ai.monitoring.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.monitoring',
            'module'      => 'ai',
            'section'     => 'monitoring',
            'name'        => 'AI Monitoring',
            'description' => 'Monitor AI usage, costs, and performance',
            'url'         => 'modules/ai/monitoring/index.php',
            'icon'        => 'fas fa-chart-line',
            'keywords'    => ['monitoring', 'usage', 'costs', 'performance', 'logs', 'analytics'],
            'synonyms'    => ['ai monitoring', 'usage stats', 'cost tracking'],
            'permissions' => ['ai.management.view', 'ai.settings.manage'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'ai.settings',
            'module'      => 'ai',
            'section'     => 'settings',
            'name'        => 'AI Settings',
            'description' => 'Global AI module settings and configuration',
            'url'         => 'modules/ai/settings/index.php',
            'icon'        => 'fas fa-sliders',
            'keywords'    => ['settings', 'configuration', 'preferences', 'options'],
            'synonyms'    => ['ai settings', 'ai config', 'ai preferences'],
            'permissions' => ['ai.settings.manage', 'ai.management.view'],
            'active'      => true,
        ]);

        // ── Procurement Module ──
        self::add([
            'id'          => 'procurement.dashboard',
            'module'      => 'procurement',
            'section'     => 'dashboard',
            'name'        => 'Procurement Dashboard',
            'description' => 'Overview of procurement activities and metrics',
            'url'         => 'modules/procurement/dashboard/index.php',
            'icon'        => 'fas fa-chart-pie',
            'keywords'    => ['procurement', 'dashboard', 'overview', 'summary'],
            'synonyms'    => ['procurement overview', 'buying dashboard'],
            'permissions' => ['procurement.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.requisitions',
            'module'      => 'procurement',
            'section'     => 'requisitions',
            'name'        => 'Purchase Requisitions',
            'description' => 'Create and manage purchase requisition requests',
            'url'         => 'modules/procurement/requisitions/index.php',
            'icon'        => 'fas fa-file-invoice',
            'keywords'    => ['requisition', 'requisitions', 'purchase request', 'pr', 'request', 'procure'],
            'synonyms'    => ['purchase requisitions', 'procurement requests', 'buy requests'],
            'permissions' => ['procurement.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.requisitions.create',
            'module'      => 'procurement',
            'section'     => 'requisitions',
            'name'        => 'Create Purchase Requisition',
            'description' => 'Create a new purchase requisition',
            'url'         => 'modules/procurement/requisitions/create.php',
            'icon'        => 'fas fa-plus-circle',
            'keywords'    => ['create', 'new', 'add', 'requisition'],
            'synonyms'    => ['new requisition', 'create requisition', 'raise requisition'],
            'permissions' => ['procurement.pr.create'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.rfq',
            'module'      => 'procurement',
            'section'     => 'rfq',
            'name'        => 'Request for Quotations',
            'description' => 'Send RFQs to suppliers and track responses',
            'url'         => 'modules/procurement/rfq/dashboard.php',
            'icon'        => 'fas fa-paper-plane',
            'keywords'    => ['rfq', 'quotation request', 'request for quotation', 'request for quote', 'vendor', 'vendors', 'supplier', 'suppliers'],
            'synonyms'    => ['rfqs', 'quotation requests', 'rfq management', 'vendor management', 'supplier management'],
            'permissions' => ['procurement.rfq.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.quotations',
            'module'      => 'procurement',
            'section'     => 'quotations',
            'name'        => 'Vendor Quotations',
            'description' => 'Review and compare supplier quotations',
            'url'         => 'modules/procurement/quotations/index.php',
            'icon'        => 'fas fa-file-alt',
            'keywords'    => ['quotation', 'quotations', 'vendor quote', 'supplier quote', 'bid', 'offer', 'vendor', 'vendors', 'supplier', 'suppliers'],
            'synonyms'    => ['vendor quotations', 'supplier quotes', 'price quotes', 'vendor management', 'supplier management'],
            'permissions' => ['procurement.quotation.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.purchase_orders',
            'module'      => 'procurement',
            'section'     => 'purchase_orders',
            'name'        => 'Purchase Orders',
            'description' => 'Create and manage purchase orders',
            'url'         => 'modules/procurement/purchase_orders/list.php',
            'icon'        => 'fas fa-shopping-cart',
            'keywords'    => ['purchase order', 'po', 'orders', 'buying', 'procurement order'],
            'synonyms'    => ['purchase orders', 'po management', 'order management'],
            'permissions' => ['procurement.po.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.purchase_orders.create',
            'module'      => 'procurement',
            'section'     => 'purchase_orders',
            'name'        => 'Create Purchase Order',
            'description' => 'Create a new purchase order',
            'url'         => 'modules/procurement/purchase_orders/create.php',
            'icon'        => 'fas fa-plus-circle',
            'keywords'    => ['create', 'new', 'add', 'purchase order', 'po'],
            'synonyms'    => ['new purchase order', 'create po', 'raise po'],
            'permissions' => ['procurement.po.create'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'procurement.reports',
            'module'      => 'procurement',
            'section'     => 'reports',
            'name'        => 'Procurement Reports',
            'description' => 'View procurement analytics and reports',
            'url'         => 'modules/procurement/reports/index.php',
            'icon'        => 'fas fa-chart-bar',
            'keywords'    => ['report', 'reports', 'analytics', 'statistics', 'procurement report'],
            'synonyms'    => ['procurement reports', 'procurement analytics'],
            'permissions' => ['procurement.view'],
            'active'      => true,
        ]);

        // ── People Management ──
        self::add([
            'id'          => 'people',
            'module'      => 'people_management',
            'section'     => null,
            'name'        => 'People',
            'description' => 'Manage people and contacts',
            'url'         => 'modules/people_management/index.php',
            'icon'        => 'fas fa-address-book',
            'keywords'    => ['people', 'contacts', 'person', 'persons'],
            'synonyms'    => ['contacts', 'people management'],
            'permissions' => ['people_management.view'],
            'active'      => true,
        ]);

        // ── Users ──
        self::add([
            'id'          => 'users',
            'module'      => 'users',
            'section'     => null,
            'name'        => 'Users',
            'description' => 'Manage system users and accounts',
            'url'         => 'modules/users/index.php',
            'icon'        => 'fas fa-users',
            'keywords'    => ['user', 'users', 'account', 'accounts', 'admin'],
            'synonyms'    => ['user management', 'accounts', 'user accounts'],
            'permissions' => ['users.view'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'users.create',
            'module'      => 'users',
            'section'     => null,
            'name'        => 'Create User',
            'description' => 'Open the user creation form to add a new ERP account',
            'url'         => 'modules/users/create.php',
            'icon'        => 'fas fa-user-plus',
            'keywords'    => ['create user', 'new user', 'add user', 'user form', 'account form', 'onboard user'],
            'synonyms'    => ['new account', 'create account', 'add account', 'user onboarding'],
            'permissions' => ['users.create'],
            'active'      => true,
        ]);

        // ── Roles ──
        self::add([
            'id'          => 'roles',
            'module'      => 'roles',
            'section'     => null,
            'name'        => 'Roles',
            'description' => 'Manage user roles and role assignments',
            'url'         => 'modules/roles/index.php',
            'icon'        => 'fas fa-user-tag',
            'keywords'    => ['role', 'roles', 'access level', 'permission group'],
            'synonyms'    => ['role management', 'user roles', 'access roles'],
            'permissions' => ['roles.view'],
            'active'      => true,
        ]);

        // ── Permissions ──
        self::add([
            'id'          => 'permissions',
            'module'      => 'permissions',
            'section'     => null,
            'name'        => 'Permissions',
            'description' => 'Manage system permissions and access controls',
            'url'         => 'modules/permissions/index.php',
            'icon'        => 'fas fa-key',
            'keywords'    => ['permission', 'permissions', 'access', 'authorization'],
            'synonyms'    => ['access control', 'permission management'],
            'permissions' => ['permissions.view'],
            'active'      => true,
        ]);

        // ── Audit Logs ──
        self::add([
            'id'          => 'audit_logs',
            'module'      => 'audit_logs',
            'section'     => null,
            'name'        => 'Audit Logs',
            'description' => 'View system audit trail and activity logs',
            'url'         => 'modules/audit_logs/index.php',
            'icon'        => 'fas fa-clipboard-list',
            'keywords'    => ['audit', 'logs', 'activity', 'trail', 'history'],
            'synonyms'    => ['audit trail', 'activity logs', 'system logs'],
            'permissions' => ['audit.view'],
            'active'      => true,
        ]);

        // ── HRMS ──
        self::add([
            'id'          => 'hrms.dashboard',
            'module'      => 'hrms',
            'section'     => 'dashboard',
            'name'        => 'HRMS Dashboard',
            'description' => 'Human resources management overview',
            'url'         => 'modules/hrms_dashboard/index.php',
            'icon'        => 'fas fa-users-cog',
            'keywords'    => ['hrms', 'hr', 'human resources', 'dashboard'],
            'synonyms'    => ['hr dashboard', 'hrms overview', 'human resources'],
            'permissions' => ['employees.view', 'attendance.view', 'leaves.view'],
            'active'      => true,
        ]);

        // ── Projects ──
        self::add([
            'id'          => 'projects.dashboard',
            'module'      => 'projects',
            'section'     => 'dashboard',
            'name'        => 'Projects Dashboard',
            'description' => 'Project management overview and task tracking',
            'url'         => 'modules/projects_dashboard/index.php',
            'icon'        => 'fas fa-project-diagram',
            'keywords'    => ['project', 'projects', 'tasks', 'task', 'timesheet'],
            'synonyms'    => ['project management', 'task management', 'project overview'],
            'permissions' => ['projects.view', 'tasks.view', 'timesheets.view'],
            'active'      => true,
        ]);

        // ── CRM ──
        self::add([
            'id'          => 'crm.dashboard',
            'module'      => 'crm',
            'section'     => 'dashboard',
            'name'        => 'CRM Dashboard',
            'description' => 'Customer relationship management overview',
            'url'         => 'modules/crm_dashboard/index.php',
            'icon'        => 'fas fa-chart-line',
            'keywords'    => ['crm', 'customer', 'relationship', 'leads', 'clients', 'deals'],
            'synonyms'    => ['customer management', 'crm overview', 'sales pipeline'],
            'permissions' => ['leads.view', 'clients.view', 'deals.view'],
            'active'      => true,
        ]);

        // ── Finance ──
        self::add([
            'id'          => 'finance.dashboard',
            'module'      => 'finance',
            'section'     => 'dashboard',
            'name'        => 'Finance Dashboard',
            'description' => 'Financial management, invoices, and payments',
            'url'         => 'modules/finance/index.php',
            'icon'        => 'fas fa-coins',
            'keywords'    => ['finance', 'financial', 'money', 'budget', 'invoice', 'payment'],
            'synonyms'    => ['financial management', 'accounting', 'finance overview'],
            'permissions' => ['finance.view', 'budget.view'],
            'active'      => true,
        ]);

        // ── Assets ──
        self::add([
            'id'          => 'assets.dashboard',
            'module'      => 'assets',
            'section'     => 'dashboard',
            'name'        => 'Assets Dashboard',
            'description' => 'IT asset management and inventory',
            'url'         => 'modules/assets_dashboard/index.php',
            'icon'        => 'fas fa-desktop',
            'keywords'    => ['asset', 'assets', 'inventory', 'equipment', 'hardware'],
            'synonyms'    => ['asset management', 'it assets', 'equipment tracking'],
            'permissions' => ['assets.view', 'inventory.view'],
            'active'      => true,
        ]);

        // ── Helpdesk ──
        self::add([
            'id'          => 'helpdesk.dashboard',
            'module'      => 'helpdesk',
            'section'     => 'dashboard',
            'name'        => 'Helpdesk Dashboard',
            'description' => 'Support ticket management and SLAs',
            'url'         => 'modules/helpdesk_dashboard/index.php',
            'icon'        => 'fas fa-headset',
            'keywords'    => ['helpdesk', 'support', 'ticket', 'tickets', 'sla'],
            'synonyms'    => ['support desk', 'ticket management', 'help desk'],
            'permissions' => ['helpdesk_dashboard.view', 'tickets.view'],
            'active'      => true,
        ]);

        // ── Reports & Analytics ──
        self::add([
            'id'          => 'reports.dashboard',
            'module'      => 'reports',
            'section'     => 'dashboard',
            'name'        => 'Reports & Analytics',
            'description' => 'Executive reports and business analytics',
            'url'         => 'modules/executive_dashboard/index.php',
            'icon'        => 'fas fa-chart-bar',
            'keywords'    => ['report', 'reports', 'analytics', 'executive', 'business intelligence'],
            'synonyms'    => ['reports', 'analytics', 'business reports', 'executive reports'],
            'permissions' => ['analytics.view', 'reports.view'],
            'active'      => true,
        ]);

        // ── Settings ──
        self::add([
            'id'          => 'settings.appearance',
            'module'      => 'settings',
            'section'     => 'appearance',
            'name'        => 'Appearance',
            'description' => 'Customize ERP appearance and theme',
            'url'         => 'modules/appearance/index.php',
            'icon'        => 'fas fa-palette',
            'keywords'    => ['appearance', 'theme', 'style', 'customize', 'looks'],
            'synonyms'    => ['theme settings', 'visual settings', 'ui settings'],
            'permissions' => [],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'settings.widgets',
            'module'      => 'settings',
            'section'     => 'widgets',
            'name'        => 'Widgets',
            'description' => 'Manage dashboard widgets',
            'url'         => 'modules/widgets/index.php',
            'icon'        => 'fas fa-puzzle-piece',
            'keywords'    => ['widget', 'widgets', 'dashboard widget'],
            'synonyms'    => ['widget management', 'dashboard widgets'],
            'permissions' => ['widgets.manage'],
            'active'      => true,
        ]);

        self::add([
            'id'          => 'settings.template_designer',
            'module'      => 'settings',
            'section'     => 'template_designer',
            'name'        => 'Template Designer',
            'description' => 'Design and customize document templates',
            'url'         => 'modules/template_designer/index.php',
            'icon'        => 'fas fa-pen-ruler',
            'keywords'    => ['template', 'templates', 'designer', 'document template'],
            'synonyms'    => ['template design', 'document templates'],
            'permissions' => ['template_designer.view'],
            'active'      => true,
        ]);
    }

    /**
     * Register a single destination.
     */
    public static function add(array $dest): void
    {
        self::$destinations[$dest['id']] = $dest;
    }

    /**
     * Get all registered destinations.
     */
    public static function getAll(): array
    {
        self::init();
        return self::$destinations;
    }

    /**
     * Get a destination by ID.
     */
    public static function getById(string $id): ?array
    {
        self::init();
        return self::$destinations[$id] ?? null;
    }

    /**
     * Get all destinations accessible to a user (permission-filtered).
     */
    public static function getAccessible(array $userPermissions): array
    {
        self::init();
        $accessible = [];

        foreach (self::$destinations as $dest) {
            if (!$dest['active']) continue;

            // No permission required = accessible to all
            if (empty($dest['permissions'])) {
                $accessible[] = $dest;
                continue;
            }

            // Check if user has ANY of the required permissions
            foreach ($dest['permissions'] as $perm) {
                if (in_array($perm, $userPermissions)) {
                    $accessible[] = $dest;
                    break;
                }
            }
        }

        return $accessible;
    }

    /**
     * Get accessible destinations grouped by module.
     */
    public static function getAccessibleGrouped(array $userPermissions): array
    {
        $accessible = self::getAccessible($userPermissions);
        $grouped = [];

        foreach ($accessible as $dest) {
            $mod = $dest['module'];
            if (!isset($grouped[$mod])) {
                $grouped[$mod] = [];
            }
            $grouped[$mod][] = $dest;
        }

        return $grouped;
    }
}
