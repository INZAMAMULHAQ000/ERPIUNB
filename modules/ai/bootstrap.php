<?php
/**
 * AI Module Bootstrap
 * 
 * Registers module structure, permissions, menu, and loads all components.
 */

// Define AI module directory constants (this file is in modules/ai/)
if (!defined('AI_MODULE_DIR')) {
    define('AI_MODULE_DIR', __DIR__); // = modules/ai/
}
if (!defined('AI_APP_ROOT')) {
    define('AI_APP_ROOT', dirname(__DIR__, 2)); // = project root
}

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include ERP core dependencies (go from modules/ai/ to project root)
$erpRoot = dirname(dirname(AI_MODULE_DIR));
if (file_exists($erpRoot . '/config/database.php')) {
    require_once $erpRoot . '/config/database.php';
}
if (file_exists($erpRoot . '/helpers/rbac.php')) {
    require_once $erpRoot . '/helpers/rbac.php';
}
if (file_exists($erpRoot . '/helpers/security.php')) {
    require_once $erpRoot . '/helpers/security.php';
}
if (file_exists($erpRoot . '/helpers/url.php')) {
    require_once $erpRoot . '/helpers/url.php';
}

// Include central configuration
require_once dirname(__DIR__) . '/ai/config/ai_central_config.php';

// Include permission validation
require_once dirname(__DIR__) . '/ai/includes/ai_permission_validator.php';

// Include provider adapter base
require_once dirname(__DIR__) . '/ai/includes/ai_provider_adapter_base.php';

// Include navigation registry
require_once dirname(__DIR__) . '/ai/includes/ai_navigation_registry.php';

// Include module registry
require_once dirname(__DIR__) . '/ai/includes/ai_module_registry.php';

// Include tool base (registry + executor)
require_once dirname(__DIR__) . '/ai/includes/ai_tool_base.php';

// Include action base (registry + executor + confirmation tokens)
require_once dirname(__DIR__) . '/ai/includes/ai_action_base.php';

// Include text extractor
require_once dirname(__DIR__) . '/ai/includes/ai_text_extractor.php';

// Include AI F11: embedding provider and vector store abstractions
require_once dirname(__DIR__) . '/ai/includes/ai_embedding_provider.php';
require_once dirname(__DIR__) . '/ai/includes/ai_vector_store.php';

// Include services
require_once dirname(__DIR__) . '/ai/services/AIContextService.php';
require_once dirname(__DIR__) . '/ai/services/AIConversationService.php';
require_once dirname(__DIR__) . '/ai/services/AINavigationService.php';
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeService.php';
require_once dirname(__DIR__) . '/ai/services/AIProviderService.php';
require_once dirname(__DIR__) . '/ai/services/AIModelService.php';
require_once dirname(__DIR__) . '/ai/services/AITaskService.php';
require_once dirname(__DIR__) . '/ai/services/AITaskPlanner.php';
require_once dirname(__DIR__) . '/ai/services/AIAutomationService.php';

// Include AI F12: agent framework
require_once dirname(__DIR__) . '/ai/includes/ai_agent_base.php';
require_once dirname(__DIR__) . '/ai/includes/ai_agents_def.php';
require_once dirname(__DIR__) . '/ai/services/AIAgentService.php';

// Include AI F25: agent runtime & autonomous task engine
require_once dirname(__DIR__) . '/ai/services/AIAgentPlanService.php';
require_once dirname(__DIR__) . '/ai/services/AIAgentCheckpointService.php';
require_once dirname(__DIR__) . '/ai/services/AIAgentApprovalService.php';
require_once dirname(__DIR__) . '/ai/services/AIAgentArtifactService.php';
require_once dirname(__DIR__) . '/ai/services/AIAgentRuntimeService.php';

// Include AI F26: proactive intelligence & recommendations
require_once dirname(__DIR__) . '/ai/services/AIIntelligenceService.php';
require_once dirname(__DIR__) . '/ai/services/AIDetectionEngine.php';
require_once dirname(__DIR__) . '/ai/services/AIWatchlistService.php';
require_once dirname(__DIR__) . '/ai/services/AIBriefingService.php';

// Include AI F13: automation scheduler & event bus
require_once dirname(__DIR__) . '/ai/services/AIAutomationSchedulerService.php';
require_once dirname(__DIR__) . '/ai/services/AIEventBusService.php';

// Include AI F14: insight engine & service
require_once dirname(__DIR__) . '/ai/includes/ai_insight_registry.php';
require_once dirname(__DIR__) . '/ai/includes/ai_insights_def.php';
require_once dirname(__DIR__) . '/ai/services/AIInsightEngine.php';
require_once dirname(__DIR__) . '/ai/services/AIInsightService.php';

// Include AI F15: command center & intent classification
require_once dirname(__DIR__) . '/ai/includes/ai_intent_classifier.php';
require_once dirname(__DIR__) . '/ai/includes/ai_capability_router.php';
require_once dirname(__DIR__) . '/ai/services/AICommandCenterService.php';

// Include AI F16: management, health, usage, kill switch
require_once dirname(__DIR__) . '/ai/services/AIHealthService.php';
require_once dirname(__DIR__) . '/ai/services/AIUsageService.php';
require_once dirname(__DIR__) . '/ai/services/AIKillSwitchService.php';
require_once dirname(__DIR__) . '/ai/services/AIGovernanceService.php';
require_once dirname(__DIR__) . '/ai/services/AIManagementService.php';

// Include AI F17: skills, ERP learning, adaptive assistance
require_once dirname(__DIR__) . '/ai/services/AISkillService.php';

// Include AI F18: workflow studio
require_once dirname(__DIR__) . '/ai/services/AIWorkflowService.php';

// Include AI F19: collaboration, workspaces, shared conversations, findings, research
require_once dirname(__DIR__) . '/ai/services/AIWorkspaceService.php';
require_once dirname(__DIR__) . '/ai/services/AISharedConversationService.php';
require_once dirname(__DIR__) . '/ai/services/AIFindingService.php';
require_once dirname(__DIR__) . '/ai/services/AIResearchService.php';
require_once dirname(__DIR__) . '/ai/services/AIWorkspaceContextService.php';
require_once dirname(__DIR__) . '/ai/services/AIWorkspaceActivityService.php';

// Include AI F20: document intelligence
require_once dirname(__DIR__) . '/ai/services/AIDocumentService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentPipelineService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentExtractionService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentTemplateService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentComparisonService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentSearchService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentIntelligenceService.php';

// Include AI F27: document intelligence enhancements
require_once dirname(__DIR__) . '/ai/services/AIDocumentMatchingService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentInvoicePOService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentF26Integration.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentSecurityService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentGovernanceService.php';
require_once dirname(__DIR__) . '/ai/services/AIDocumentAdminService.php';

// Include AI F21: voice interface
require_once dirname(__DIR__) . '/ai/services/AIVoiceSTTService.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceTTSService.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceService.php';

// Include AI F28: voice & conversational interface enhancements
require_once dirname(__DIR__) . '/ai/services/AIVoiceGovernanceService.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceConfirmationService.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceContextService.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceF25Integration.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceF26Integration.php';
require_once dirname(__DIR__) . '/ai/services/AIVoiceF27Integration.php';

// Include AI F29: universal copilot & cross-module intelligence
require_once dirname(__DIR__) . '/ai/services/AICopilotService.php';

// Include AI F30: business analytics, forecasting & decision intelligence
require_once dirname(__DIR__) . '/ai/services/AIMetricRegistry.php';
require_once dirname(__DIR__) . '/ai/services/AIAnalyticsQueryEngine.php';
require_once dirname(__DIR__) . '/ai/services/AIForecastingService.php';
require_once dirname(__DIR__) . '/ai/services/AIScenarioService.php';
require_once dirname(__DIR__) . '/ai/services/AIAnalyticsService.php';

// Include AI F31: process intelligence & workflow optimization
require_once dirname(__DIR__) . '/ai/services/AIProcessIntelligenceService.php';

// Include AI F32: communications & business correspondence assistant
require_once dirname(__DIR__) . '/ai/services/AICommunicationContextService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationDraftService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationSummarizationService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationClassificationService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationTranslationService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationSafetyService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationTemplateService.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationF25Integration.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationF26Integration.php';
require_once dirname(__DIR__) . '/ai/services/AICommunicationF27Integration.php';

// Shared AI shell helpers used by both ERP-wrapped and standalone AI pages.
require_once dirname(__DIR__) . '/ai/includes/ai_ui.php';

// Include AI F33: enterprise knowledge & RAG intelligence
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeVersionService.php';
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeQAService.php';
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeFreshnessService.php';
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeReviewService.php';
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeGapService.php';
require_once dirname(__DIR__) . '/ai/services/AIKnowledgeF33Integration.php';

// Include AI F22: integrations and external connectors
require_once dirname(__DIR__) . '/ai/services/AIIntegrationService.php';

// Include AI F23: observability, evaluation, and continuous improvement
require_once dirname(__DIR__) . '/ai/services/AIObservabilityService.php';

// Include AI F24: security, governance & guardrails
require_once dirname(__DIR__) . '/ai/services/AIPolicyEngine.php';
require_once dirname(__DIR__) . '/ai/services/AIPolicyVersionService.php';
require_once dirname(__DIR__) . '/ai/services/AIRiskClassifier.php';
require_once dirname(__DIR__) . '/ai/services/AIReviewQueueService.php';
require_once dirname(__DIR__) . '/ai/services/AISecurityEventService.php';
require_once dirname(__DIR__) . '/ai/services/AIDataClassificationService.php';
require_once dirname(__DIR__) . '/ai/services/AIGovernanceAudit.php';
require_once dirname(__DIR__) . '/ai/services/AIParameterValidator.php';
require_once dirname(__DIR__) . '/ai/services/AILoopDetector.php';
require_once dirname(__DIR__) . '/ai/services/AIInjectionDetectionService.php';
require_once dirname(__DIR__) . '/ai/services/AISqlSafetyService.php';
require_once dirname(__DIR__) . '/ai/services/AIEmergencyControlsService.php';
require_once dirname(__DIR__) . '/ai/services/AINavigationSecurityService.php';
require_once dirname(__DIR__) . '/ai/services/AIFileSecurityService.php';
require_once dirname(__DIR__) . '/ai/services/AIExternalCommService.php';
require_once dirname(__DIR__) . '/ai/services/AICostGuardrailsService.php';
require_once dirname(__DIR__) . '/ai/services/AIBulkActionService.php';
require_once dirname(__DIR__) . '/ai/services/AIFinancialProtectionService.php';
require_once dirname(__DIR__) . '/ai/services/AIToolAuthorizationService.php';
require_once dirname(__DIR__) . '/ai/services/AISecretProtectionService.php';
require_once dirname(__DIR__) . '/ai/services/AIGovernanceMiddleware.php';
require_once dirname(__DIR__) . '/ai/services/AIPolicySimulator.php';

// Include tools
require_once dirname(__DIR__) . '/ai/tools/procurement_tools.php';
require_once dirname(__DIR__) . '/ai/tools/procurement_actions.php';
require_once dirname(__DIR__) . '/ai/tools/user_actions.php';
require_once dirname(__DIR__) . '/ai/tools/knowledge_tools.php';
require_once dirname(__DIR__) . '/ai/tools/integration_actions.php';
require_once dirname(__DIR__) . '/ai/tools/integration_tools.php';

// Register AI module with the ERP
if (function_exists('registerModule')) {
    registerModule([
        'id'          => 'ai',
        'name'        => 'AI',
        'description' => 'AI-powered ERP assistant with contextual help, suggestions, and tools',
        'version'     => '1.0.0',
        'icon'        => 'fas fa-brain',
        'route'       => '/modules/ai/assistant/index.php',
    ]);
}

/**
 * Register AI permissions.
 */
if (function_exists('registerPermission')) {
    registerPermission([
        'id'          => 'ai.assistant.access',
        'name'        => 'AI Assistant Access',
        'description' => 'Access the AI assistant',
        'module'      => 'ai',
        'group'       => 'Assistant',
    ]);

    registerPermission([
        'id'          => 'ai.settings.manage',
        'name'        => 'Manage AI Settings',
        'description' => 'Configure AI module settings',
        'module'      => 'ai',
        'group'       => 'Settings',
    ]);

    registerPermission([
        'id'          => 'ai.assistant.context.read',
        'name'        => 'Read AI Context',
        'description' => 'Allow AI to read context from current page',
        'module'      => 'ai',
        'group'       => 'Assistant',
    ]);

    registerPermission([
        'id'          => 'ai.assistant.suggestions.read',
        'name'        => 'Read AI Suggestions',
        'description' => 'Allow AI to provide suggestions',
        'module'      => 'ai',
        'group'       => 'Assistant',
    ]);

    registerPermission([
        'id'          => 'ai.providers.view',
        'name'        => 'View AI Providers',
        'description' => 'View AI provider configurations',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.providers.create',
        'name'        => 'Create AI Providers',
        'description' => 'Create new AI providers',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.providers.update',
        'name'        => 'Update AI Providers',
        'description' => 'Update AI provider configurations',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.providers.delete',
        'name'        => 'Delete AI Providers',
        'description' => 'Delete AI providers',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.models.view',
        'name'        => 'View AI Models',
        'description' => 'View AI model configurations',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.models.manage',
        'name'        => 'Manage AI Models',
        'description' => 'Create, update, delete AI models',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.knowledge.view',
        'name'        => 'View Knowledge Base',
        'description' => 'View knowledge base documents',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    registerPermission([
        'id'          => 'ai.knowledge.manage',
        'name'        => 'Manage Knowledge Base',
        'description' => 'Create, update, delete knowledge base documents',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    registerPermission([
        'id'          => 'ai.knowledge.search',
        'name'        => 'Search Knowledge Base',
        'description' => 'Search and retrieve from knowledge base',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    registerPermission([
        'id'          => 'ai.agents.view',
        'name'        => 'View AI Agents',
        'description' => 'View available AI agents and their status',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    registerPermission([
        'id'          => 'ai.agents.execute',
        'name'        => 'Execute AI Agents',
        'description' => 'Start and manage AI agent runs',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    // F25: Agent Runtime permissions
    registerPermission([
        'id'          => 'ai.agents.tasks.create',
        'name'        => 'Create Agent Tasks',
        'description' => 'Create new agent tasks from requests',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    registerPermission([
        'id'          => 'ai.agents.tasks.manage',
        'name'        => 'Manage Agent Tasks',
        'description' => 'Pause, resume, cancel agent tasks',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    registerPermission([
        'id'          => 'ai.agents.approvals.review',
        'name'        => 'Review Agent Approvals',
        'description' => 'Approve or reject agent action requests',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    registerPermission([
        'id'          => 'ai.agents.artifacts.view',
        'name'        => 'View Agent Artifacts',
        'description' => 'View artifacts produced by agent runs',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    registerPermission([
        'id'          => 'ai.agents.admin',
        'name'        => 'Administer Agents',
        'description' => 'Create, edit, enable/disable agent definitions',
        'module'      => 'ai',
        'group'       => 'Agents',
    ]);

    // F26: Intelligence permissions
    registerPermission([
        'id'          => 'ai.intelligence.view',
        'name'        => 'View Intelligence',
        'description' => 'View AI intelligence alerts, recommendations, and insights',
        'module'      => 'ai',
        'group'       => 'Intelligence',
    ]);

    registerPermission([
        'id'          => 'ai.intelligence.manage',
        'name'        => 'Manage Intelligence',
        'description' => 'Manage detection rules, monitors, and watchlists',
        'module'      => 'ai',
        'group'       => 'Intelligence',
    ]);

    registerPermission([
        'id'          => 'ai.intelligence.dismiss',
        'name'        => 'Dismiss Intelligence Events',
        'description' => 'Dismiss or snooze intelligence events',
        'module'      => 'ai',
        'group'       => 'Intelligence',
    ]);

    registerPermission([
        'id'          => 'ai.intelligence.run',
        'name'        => 'Run Detection Engine',
        'description' => 'Manually trigger the detection engine',
        'module'      => 'ai',
        'group'       => 'Intelligence',
    ]);

    // F13: Automation Scheduler & Events permissions
    registerPermission([
        'id'          => 'ai.automation.view',
        'name'        => 'View Automations',
        'description' => 'View automation workflows and triggers',
        'module'      => 'ai',
        'group'       => 'Automation',
    ]);

    registerPermission([
        'id'          => 'ai.automation.create',
        'name'        => 'Create Automations',
        'description' => 'Create and configure automation workflows',
        'module'      => 'ai',
        'group'       => 'Automation',
    ]);

    registerPermission([
        'id'          => 'ai.automation.execute',
        'name'        => 'Execute Automations',
        'description' => 'Manually trigger automation runs',
        'module'      => 'ai',
        'group'       => 'Automation',
    ]);

    registerPermission([
        'id'          => 'ai.events.view',
        'name'        => 'View Event Log',
        'description' => 'View ERP event log and statistics',
        'module'      => 'ai',
        'group'       => 'Automation',
    ]);

    // F14: Insights permissions
    registerPermission([
        'id'          => 'ai.insights.view',
        'name'        => 'View AI Insights',
        'description' => 'View AI-generated proactive insights',
        'module'      => 'ai',
        'group'       => 'Insights',
    ]);

    registerPermission([
        'id'          => 'ai.insights.manage',
        'name'        => 'Manage AI Insights',
        'description' => 'Configure and manage insight definitions',
        'module'      => 'ai',
        'group'       => 'Insights',
    ]);

    registerPermission([
        'id'          => 'ai.insights.dismiss',
        'name'        => 'Dismiss AI Insights',
        'description' => 'Dismiss and provide feedback on insights',
        'module'      => 'ai',
        'group'       => 'Insights',
    ]);

    registerPermission([
        'id'          => 'ai.insights.configure',
        'name'        => 'Configure AI Insights',
        'description' => 'Configure insight evaluation settings',
        'module'      => 'ai',
        'group'       => 'Insights',
    ]);

    // F15: Command Center permissions
    registerPermission([
        'id'          => 'ai.command_center.access',
        'name'        => 'Access AI Command Center',
        'description' => 'Use the universal AI command interface',
        'module'      => 'ai',
        'group'       => 'Command Center',
    ]);

    // F16: Management Center permissions
    registerPermission([
        'id'          => 'ai.management.view',
        'name'        => 'View AI Management Center',
        'description' => 'View AI management dashboard and health status',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    registerPermission([
        'id'          => 'ai.management.admin',
        'name'        => 'Administer AI Management',
        'description' => 'Toggle kill switches, run diagnostics, purge data',
        'module'      => 'ai',
        'group'       => 'Management',
    ]);

    // F17: Skills permissions
    registerPermission([
        'id'          => 'ai.skills.view',
        'name'        => 'View AI Skills',
        'description' => 'View available AI skills',
        'module'      => 'ai',
        'group'       => 'Skills',
    ]);

    registerPermission([
        'id'          => 'ai.skills.use',
        'name'        => 'Use AI Skills',
        'description' => 'Execute AI skills for ERP workflows',
        'module'      => 'ai',
        'group'       => 'Skills',
    ]);

    registerPermission([
        'id'          => 'ai.skills.manage',
        'name'        => 'Manage AI Skills',
        'description' => 'Create, edit, and manage AI skills',
        'module'      => 'ai',
        'group'       => 'Skills',
    ]);

    registerPermission([
        'id'          => 'ai.skills.publish',
        'name'        => 'Publish AI Skills',
        'description' => 'Approve and publish AI skills',
        'module'      => 'ai',
        'group'       => 'Skills',
    ]);

    registerPermission([
        'id'          => 'ai.skills.test',
        'name'        => 'Test AI Skills',
        'description' => 'Test AI skills in sandbox mode',
        'module'      => 'ai',
        'group'       => 'Skills',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.view',
        'name'        => 'View Workflow Studio',
        'description' => 'View workflow definitions and run history',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.create',
        'name'        => 'Create Workflows',
        'description' => 'Create AI workflows',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.edit',
        'name'        => 'Edit Workflows',
        'description' => 'Edit draft workflow versions',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.test',
        'name'        => 'Test Workflows',
        'description' => 'Run workflow dry-runs',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.publish',
        'name'        => 'Publish Workflows',
        'description' => 'Publish approved workflow versions',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.enable',
        'name'        => 'Enable Workflows',
        'description' => 'Enable or disable workflow execution',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.approve',
        'name'        => 'Approve Workflows',
        'description' => 'Approve high-risk workflows and approval nodes',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    registerPermission([
        'id'          => 'ai.workflows.run',
        'name'        => 'Run Workflows',
        'description' => 'Execute manual workflows and workflow nodes',
        'module'      => 'ai',
        'group'       => 'Workflows',
    ]);

    // F19: Collaboration & Workspaces permissions
    registerPermission([
        'id'          => 'ai.workspaces.view',
        'name'        => 'View Workspaces',
        'description' => 'View AI workspaces and their contents',
        'module'      => 'ai',
        'group'       => 'Collaboration',
    ]);

    registerPermission([
        'id'          => 'ai.workspaces.create',
        'name'        => 'Create Workspaces',
        'description' => 'Create new AI workspaces',
        'module'      => 'ai',
        'group'       => 'Collaboration',
    ]);

    registerPermission([
        'id'          => 'ai.workspaces.manage',
        'name'        => 'Manage Workspaces',
        'description' => 'Manage workspace settings, members, and configurations',
        'module'      => 'ai',
        'group'       => 'Collaboration',
    ]);

    registerPermission([
        'id'          => 'ai.workspaces.join',
        'name'        => 'Join Workspaces',
        'description' => 'Accept invitations to join AI workspaces',
        'module'      => 'ai',
        'group'       => 'Collaboration',
    ]);

    registerPermission([
        'id'          => 'ai.workspaces.share',
        'name'        => 'Share in Workspaces',
        'description' => 'Share conversations, findings, and agent results in workspaces',
        'module'      => 'ai',
        'group'       => 'Collaboration',
    ]);

    registerPermission([
        'id'          => 'ai.workspaces.findings.review',
        'name'        => 'Review AI Findings',
        'description' => 'Review, accept, or dismiss AI findings in workspaces',
        'module'      => 'ai',
        'group'       => 'Collaboration',
    ]);

    // F20: Document Intelligence permissions
    registerPermission([
        'id'          => 'ai.documents.view',
        'name'        => 'View Documents',
        'description' => 'View AI document intelligence registry',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.upload',
        'name'        => 'Upload Documents',
        'description' => 'Register and upload documents for AI processing',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.process',
        'name'        => 'Process Documents',
        'description' => 'Trigger document processing and extraction',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.review',
        'name'        => 'Review Documents',
        'description' => 'Review and approve document extractions',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.compare',
        'name'        => 'Compare Documents',
        'description' => 'Compare multiple documents',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.ask',
        'name'        => 'Ask Documents',
        'description' => 'Ask AI questions about documents',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.manage_templates',
        'name'        => 'Manage Document Templates',
        'description' => 'Create and manage extraction templates',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    registerPermission([
        'id'          => 'ai.documents.create_actions',
        'name'        => 'Create Document Actions',
        'description' => 'Create ERP actions from document extractions',
        'module'      => 'ai',
        'group'       => 'Documents',
    ]);

    // AI F21: Voice Interface permissions
    registerPermission([
        'id'          => 'ai.voice.use',
        'name'        => 'Use Voice Interface',
        'description' => 'Access the AI voice interface for voice commands',
        'module'      => 'ai',
        'group'       => 'Voice',
    ]);

    registerPermission([
        'id'          => 'ai.voice.transcribe',
        'name'        => 'Transcribe Audio',
        'description' => 'Upload audio and receive transcriptions',
        'module'      => 'ai',
        'group'       => 'Voice',
    ]);

    registerPermission([
        'id'          => 'ai.voice.playback',
        'name'        => 'Voice Playback',
        'description' => 'Use text-to-speech for AI responses',
        'module'      => 'ai',
        'group'       => 'Voice',
    ]);

    registerPermission([
        'id'          => 'ai.voice.dictate',
        'name'        => 'Voice Dictation',
        'description' => 'Dictate text into ERP fields',
        'module'      => 'ai',
        'group'       => 'Voice',
    ]);

    registerPermission([
        'id'          => 'ai.voice.manage',
        'name'        => 'Manage Voice Settings',
        'description' => 'Configure voice providers, shortcuts, and settings',
        'module'      => 'ai',
        'group'       => 'Voice',
    ]);

    // AI F29: Copilot permissions
    registerPermission([
        'id'          => 'ai.copilot.use',
        'name'        => 'Use AI Copilot',
        'description' => 'Access the universal AI copilot and command center',
        'module'      => 'ai',
        'group'       => 'Copilot',
    ]);

    registerPermission([
        'id'          => 'ai.copilot.manage',
        'name'        => 'Manage Copilot Settings',
        'description' => 'Configure copilot capabilities, routes, and settings',
        'module'      => 'ai',
        'group'       => 'Copilot',
    ]);

    registerPermission([
        'id'          => 'ai.copilot.navigate',
        'name'        => 'AI Navigation',
        'description' => 'Allow AI to navigate to ERP pages',
        'module'      => 'ai',
        'group'       => 'Copilot',
    ]);

    registerPermission([
        'id'          => 'ai.copilot.search',
        'name'        => 'Universal AI Search',
        'description' => 'Search across all ERP modules via AI',
        'module'      => 'ai',
        'group'       => 'Copilot',
    ]);

    registerPermission([
        'id'          => 'ai.copilot.execute',
        'name'        => 'Execute Copilot Actions',
        'description' => 'Allow AI copilot to execute permitted actions',
        'module'      => 'ai',
        'group'       => 'Copilot',
    ]);

    // AI F30: Analytics permissions
    registerPermission([
        'id'          => 'ai.analytics.view',
        'name'        => 'View Analytics',
        'description' => 'Access the AI analytics center and view business metrics',
        'module'      => 'ai',
        'group'       => 'Analytics',
    ]);

    registerPermission([
        'id'          => 'ai.analytics.forecast',
        'name'        => 'Run Forecasts',
        'description' => 'Generate AI-powered business forecasts',
        'module'      => 'ai',
        'group'       => 'Analytics',
    ]);

    registerPermission([
        'id'          => 'ai.analytics.scenario',
        'name'        => 'Run Scenarios',
        'description' => 'Run what-if scenario analysis',
        'module'      => 'ai',
        'group'       => 'Analytics',
    ]);

    registerPermission([
        'id'          => 'ai.analytics.manage',
        'name'        => 'Manage Analytics',
        'description' => 'Save analyses, configure metrics, manage scheduled reports',
        'module'      => 'ai',
        'group'       => 'Analytics',
    ]);

    registerPermission([
        'id'          => 'ai.process_intelligence.view',
        'name'        => 'View Process Intelligence',
        'description' => 'Access the AI process intelligence center and grounded process analytics',
        'module'      => 'ai',
        'group'       => 'Process Intelligence',
    ]);

    registerPermission([
        'id'          => 'ai.process_intelligence.manage',
        'name'        => 'Manage Process Intelligence',
        'description' => 'Refresh process data, manage process definitions, and configure schedules',
        'module'      => 'ai',
        'group'       => 'Process Intelligence',
    ]);

    registerPermission([
        'id'          => 'ai.process_intelligence.simulate',
        'name'        => 'Run Process Simulations',
        'description' => 'Run what-if process simulations without changing ERP production data',
        'module'      => 'ai',
        'group'       => 'Process Intelligence',
    ]);

    registerPermission([
        'id'          => 'ai.process_intelligence.propose',
        'name'        => 'Prepare Process Proposals',
        'description' => 'Convert process recommendations into governed tasks and workflow proposals',
        'module'      => 'ai',
        'group'       => 'Process Intelligence',
    ]);

    // AI F32: Communications permissions
    registerPermission([
        'id'          => 'ai.communications.draft',
        'name'        => 'Draft Communications',
        'description' => 'Create AI-drafted business communications',
        'module'      => 'ai',
        'group'       => 'Communications',
    ]);

    registerPermission([
        'id'          => 'ai.communications.send',
        'name'        => 'Send Communications',
        'description' => 'Send AI-drafted communications through ERP',
        'module'      => 'ai',
        'group'       => 'Communications',
    ]);

    registerPermission([
        'id'          => 'ai.communications.templates',
        'name'        => 'Manage Communication Templates',
        'description' => 'Create and manage communication templates',
        'module'      => 'ai',
        'group'       => 'Communications',
    ]);

    registerPermission([
        'id'          => 'ai.communications.classify',
        'name'        => 'Classify Communications',
        'description' => 'Use AI to classify and prioritize communications',
        'module'      => 'ai',
        'group'       => 'Communications',
    ]);

    registerPermission([
        'id'          => 'ai.communications.translate',
        'name'        => 'Translate Communications',
        'description' => 'Translate business communications',
        'module'      => 'ai',
        'group'       => 'Communications',
    ]);

    registerPermission([
        'id'          => 'ai.communications.view',
        'name'        => 'View Communications Center',
        'description' => 'Access the AI communications center',
        'module'      => 'ai',
        'group'       => 'Communications',
    ]);

    // AI F33: Knowledge permissions
    registerPermission([
        'id'          => 'ai.knowledge.ask',
        'name'        => 'Ask Knowledge',
        'description' => 'Ask AI questions against the knowledge base',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    registerPermission([
        'id'          => 'ai.knowledge.index',
        'name'        => 'Index Knowledge',
        'description' => 'Add, update, and manage knowledge sources',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    registerPermission([
        'id'          => 'ai.knowledge.review',
        'name'        => 'Review Knowledge',
        'description' => 'Review and approve knowledge content',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    registerPermission([
        'id'          => 'ai.knowledge.admin',
        'name'        => 'Administer Knowledge',
        'description' => 'Manage knowledge collections, permissions, and freshness rules',
        'module'      => 'ai',
        'group'       => 'Knowledge',
    ]);

    // AI F22: Integration permissions
    registerPermission([
        'id'          => 'ai.integrations.view',
        'name'        => 'View Integrations',
        'description' => 'Access the AI integration center',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integrations.manage',
        'name'        => 'Manage Integrations',
        'description' => 'Create and manage external connectors and policies',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integrations.connect',
        'name'        => 'Create Connections',
        'description' => 'Create and configure integration connections',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integrations.disconnect',
        'name'        => 'Disconnect Integrations',
        'description' => 'Revoke integration connections and credentials',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integrations.use',
        'name'        => 'Use Integrations',
        'description' => 'Use approved external connector capabilities',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integrations.webhooks.manage',
        'name'        => 'Manage Webhooks',
        'description' => 'Configure outbound webhook destinations and policies',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integrations.sync',
        'name'        => 'Run Integration Sync',
        'description' => 'Preview and apply controlled synchronization jobs',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integration.rest.read',
        'name'        => 'Use REST Read Capabilities',
        'description' => 'Execute registered external read-only REST capabilities',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integration.rest.write',
        'name'        => 'Use REST Write Previews',
        'description' => 'Execute controlled external write previews requiring confirmation',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.integration.webhook.send',
        'name'        => 'Send Integration Webhooks',
        'description' => 'Send approved outbound webhook events',
        'module'      => 'ai',
        'group'       => 'Integrations',
    ]);

    registerPermission([
        'id'          => 'ai.observability.view',
        'name'        => 'View AI Observability',
        'description' => 'View AI observability dashboards and summaries',
        'module'      => 'ai',
        'group'       => 'Observability',
    ]);

    registerPermission([
        'id'          => 'ai.observability.traces',
        'name'        => 'View AI Traces',
        'description' => 'Inspect request traces and span timelines',
        'module'      => 'ai',
        'group'       => 'Observability',
    ]);

    registerPermission([
        'id'          => 'ai.observability.evaluations',
        'name'        => 'Run AI Evaluations',
        'description' => 'Manage evaluation datasets, runs, and regression checks',
        'module'      => 'ai',
        'group'       => 'Observability',
    ]);

    registerPermission([
        'id'          => 'ai.observability.feedback.review',
        'name'        => 'Review AI Feedback',
        'description' => 'Review and resolve AI feedback reports',
        'module'      => 'ai',
        'group'       => 'Observability',
    ]);

    registerPermission([
        'id'          => 'ai.observability.experiments',
        'name'        => 'Manage AI Experiments',
        'description' => 'Create and review controlled AI experiments',
        'module'      => 'ai',
        'group'       => 'Observability',
    ]);

    registerPermission([
        'id'          => 'ai.observability.manage',
        'name'        => 'Manage AI Observability',
        'description' => 'Manage observability policies, reports, and improvements',
        'module'      => 'ai',
        'group'       => 'Observability',
    ]);
}

/**
 * Register AI sidebar menu.
 */
if (function_exists('registerMenuItem')) {
    registerMenuItem([
        'id'          => 'ai',
        'label'       => 'AI',
        'icon'        => 'fas fa-brain',
        'route'       => '#',
        'module'      => 'ai',
        'permission'  => 'ai.assistant.access',
        'children'    => [
            [
                'id'         => 'ai.copilot',
                'label'      => 'AI Copilot',
                'icon'       => 'fas fa-brain',
                'route'      => '/modules/ai/copilot/index.php',
                'permission' => 'ai.copilot.use',
            ],
            [
                'id'         => 'ai.analytics',
                'label'      => 'Analytics',
                'icon'       => 'fas fa-chart-line',
                'route'      => '/modules/ai/analytics/index.php',
                'permission' => 'ai.analytics.view',
            ],
            [
                'id'         => 'ai.process_intelligence',
                'label'      => 'Process Intelligence',
                'icon'       => 'fas fa-diagram-project',
                'route'      => '/modules/ai/process-intelligence/index.php',
                'permission' => 'ai.process_intelligence.view',
            ],
            [
                'id'         => 'ai.assistant',
                'label'      => 'Assistant',
                'icon'       => 'fas fa-comment-dots',
                'route'      => '/modules/ai/assistant/index.php',
                'permission' => 'ai.assistant.access',
            ],
            [
                'id'         => 'ai.management_center',
                'label'      => 'Management Center',
                'icon'       => 'fas fa-cogs',
                'route'      => '/modules/ai/management/index.php',
                'permission' => 'ai.settings.manage',
            ],
            [
                'id'         => 'ai.providers',
                'label'      => 'Providers',
                'icon'       => 'fas fa-server',
                'route'      => '/modules/ai/providers/index.php',
                'permission' => 'ai.providers.view',
            ],
            [
                'id'         => 'ai.models',
                'label'      => 'Models',
                'icon'       => 'fas fa-cube',
                'route'      => '/modules/ai/models/index.php',
                'permission' => 'ai.models.view',
            ],
            [
                'id'         => 'ai.agents',
                'label'      => 'Agents',
                'icon'       => 'fas fa-robot',
                'route'      => '/modules/ai/agents/index.php',
                'permission' => 'ai.settings.manage',
            ],
            [
                'id'         => 'ai.knowledge',
                'label'      => 'Knowledge',
                'icon'       => 'fas fa-book',
                'route'      => '/modules/ai/knowledge/index.php',
                'permission' => 'ai.knowledge.view',
            ],
            [
                'id'         => 'ai.tools',
                'label'      => 'Tools',
                'icon'       => 'fas fa-wrench',
                'route'      => '/modules/ai/tools/index.php',
                'permission' => 'ai.settings.manage',
            ],
            [
                'id'         => 'ai.tasks',
                'label'      => 'Tasks',
                'icon'       => 'fas fa-tasks',
                'route'      => '/modules/ai/tasks/index.php',
                'permission' => 'ai.assistant.access',
            ],
            [
                'id'         => 'ai.automations',
                'label'      => 'Automations',
                'icon'       => 'fas fa-bolt',
                'route'      => '/modules/ai/automations/index.php',
                'permission' => 'ai.assistant.access',
            ],
            [
                'id'         => 'ai.insights',
                'label'      => 'Insights',
                'icon'       => 'fas fa-lightbulb',
                'route'      => '/modules/ai/insights/index.php',
                'permission' => 'ai.insights.view',
            ],
            [
                'id'         => 'ai.command_center',
                'label'      => 'Command Center',
                'icon'       => 'fas fa-terminal',
                'route'      => '/modules/ai/command-center/index.php',
                'permission' => 'ai.assistant.access',
            ],
            [
                'id'         => 'ai.workflows',
                'label'      => 'Workflow Studio',
                'icon'       => 'fas fa-diagram-project',
                'route'      => '/modules/ai/workflows/index.php',
                'permission' => 'ai.workflows.view',
            ],
            [
                'id'         => 'ai.skills',
                'label'      => 'Skills',
                'icon'       => 'fas fa-star',
                'route'      => '/modules/ai/skills/index.php',
                'permission' => 'ai.skills.view',
            ],
            [
                'id'         => 'ai.workspaces',
                'label'      => 'Workspaces',
                'icon'       => 'fas fa-users',
                'route'      => '/modules/ai/workspaces/index.php',
                'permission' => 'ai.workspaces.view',
            ],
            [
                'id'         => 'ai.documents',
                'label'      => 'Document Intelligence',
                'icon'       => 'fas fa-file-alt',
                'route'      => '/modules/ai/documents/index.php',
                'permission' => 'ai.documents.view',
            ],
            [
                'id'         => 'ai.voice',
                'label'      => 'Voice Interface',
                'icon'       => 'fas fa-microphone',
                'route'      => '/modules/ai/voice/index.php',
                'permission' => 'ai.voice.use',
            ],
            [
                'id'         => 'ai.integrations',
                'label'      => 'Integrations',
                'icon'       => 'fas fa-plug',
                'route'      => '/modules/ai/integrations/index.php',
                'permission' => 'ai.integrations.view',
            ],
            [
                'id'         => 'ai.observability',
                'label'      => 'Observability',
                'icon'       => 'fas fa-wave-square',
                'route'      => '/modules/ai/observability/index.php',
                'permission' => 'ai.observability.view',
            ],
            [
                'id'         => 'ai.intelligence',
                'label'      => 'Intelligence',
                'icon'       => 'fas fa-radar',
                'route'      => '/modules/ai/intelligence/index.php',
                'permission' => 'ai.intelligence.view',
            ],
            [
                'id'         => 'ai.communications',
                'label'      => 'Communications',
                'icon'       => 'fas fa-comments',
                'route'      => '/modules/ai/communications/index.php',
                'permission' => 'ai.communications.view',
            ],
            [
                'id'         => 'ai.management',
                'label'      => 'Management Center',
                'icon'       => 'fas fa-shield-alt',
                'route'      => '/modules/ai/management/index.php',
                'permission' => 'ai.management.view',
            ],
            [
                'id'         => 'ai.monitoring',
                'label'      => 'Monitoring',
                'icon'       => 'fas fa-chart-bar',
                'route'      => '/modules/ai/monitoring/index.php',
                'permission' => 'ai.settings.manage',
            ],
            [
                'id'         => 'ai.settings',
                'label'      => 'Settings',
                'icon'       => 'fas fa-sliders-h',
                'route'      => '/modules/ai/settings/index.php',
                'permission' => 'ai.settings.manage',
            ],
        ],
    ]);
}

/**
 * Get AI module configuration.
 */
function getAIConfig() {
    global $AI_CONFIG;
    return $AI_CONFIG ?? [];
}

/**
 * Check if AI module is enabled.
 */
function isAIEnabled() {
    $config = getAIConfig();
    return isset($config['enabled']) && $config['enabled'] === true;
}

/**
 * Get AI assistant URL.
 */
function getAIAssistantUrl() {
    return '/modules/ai/assistant/index.php';
}

/**
 * Check if a user has AI access.
 */
function userHasAIAccess($userId = null) {
    if (function_exists('hasPermission')) {
        return hasPermission('ai.assistant.access', $userId);
    }
    return false;
}
