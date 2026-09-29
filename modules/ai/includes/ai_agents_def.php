<?php
/**
 * AI F12 — Default Agent Definitions.
 *
 * Registers the initial agents: ERP General Assistant and Procurement Specialist.
 * These are the controlled, safe agents available out of the box.
 */

// ── A. ERP General Assistant Agent ──
AIAgentRegistry::register([
    'agent_id'                  => 'erp_general_assistant',
    'agent_type'                => 'general',
    'name'                      => 'ERP General Assistant',
    'description'               => 'A general-purpose agent that can analyze ERP data across modules, search knowledge, navigate the system, and perform multi-step read-only analysis.',
    'module'                    => 'erp',
    'enabled'                   => true,
    'allowed_tools'             => [
        // Procurement tools
        'procurement.search_suppliers',
        'procurement.search_purchase_requisitions',
        'procurement.search_purchase_orders',
        // Knowledge
        'knowledge.search',
    ],
    'allowed_actions'           => [],
    'allowed_knowledge'         => ['*'],
    'max_steps'                 => 10,
    'max_tool_calls'            => 15,
    'max_runtime_seconds'       => 120,
    'max_working_memory_chars'  => 8000,
    'risk_level'                => 'low',
    'required_permissions'      => ['ai.assistant.access'],
    'system_instructions'       => 'You are an ERP General Assistant. You help users analyze ERP data, answer questions about business processes, and navigate the system. You can use approved read-only tools to search for data. You always ground your answers in real tool results. You never fabricate data. You clearly attribute sources. You never modify data without explicit user confirmation.',
    'human_confirmation_policy' => 'never', // Read-only agent
]);

// ── B. Procurement Specialist Agent ──
AIAgentRegistry::register([
    'agent_id'                  => 'procurement_specialist',
    'agent_type'                => 'specialist',
    'name'                      => 'Procurement Specialist',
    'description'               => 'A domain-specific agent focused on procurement operations: supplier lookup, purchase requisition analysis, quotation drafting, purchase order tracking, and procurement knowledge retrieval.',
    'module'                    => 'procurement',
    'enabled'                   => true,
    'allowed_tools'             => [
        'procurement.search_suppliers',
        'procurement.search_purchase_requisitions',
        'procurement.search_purchase_orders',
        'knowledge.search',
    ],
    'allowed_actions'           => [
        'procurement.create_supplier',
        'procurement.create_purchase_requisition',
        'procurement.create_quotation_draft',
    ],
    'allowed_knowledge'         => ['procurement'],
    'max_steps'                 => 8,
    'max_tool_calls'            => 12,
    'max_runtime_seconds'       => 90,
    'max_working_memory_chars'  => 6000,
    'risk_level'                => 'medium',
    'required_permissions'      => ['ai.assistant.access'],
    'system_instructions'       => 'You are a Procurement Specialist agent. You help users with procurement-related tasks: analyzing suppliers, reviewing purchase requisitions and orders, drafting quotations, checking procurement policies, and suggesting improvements. You can search procurement data and knowledge. You can propose creating suppliers, purchase requisitions, and quotation drafts, but these always require user confirmation. You never fabricate procurement data. You attribute all sources.',
    'human_confirmation_policy' => 'write_only', // Write actions require confirmation
]);
