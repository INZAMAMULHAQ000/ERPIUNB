<?php
/**
 * Migration Inspector - View missing tables and run migrations
 * 
 * Visiting this URL shows which migration-created tables are missing
 * from the database, with buttons to run the missing migration SQL.
 * 
 * ONLY usable on localhost for safety.
 */

// ── Environment Detection & Safety ─────────────────────────────────
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (php_sapi_name() === 'cli')
    || ($serverName !== '' && in_array($serverName, ['localhost', '127.0.0.1', '::1']))
    || ($httpHost !== '' && in_array($httpHost, ['localhost', '127.0.0.1', '::1']));

if (!$isLocal) {
    die('<h1 style="color:red;text-align:center;margin-top:4rem">This inspector can only run on localhost.</h1>');
}

// ── Bootstrap ──────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';

// ── Step 1: Get all existing tables ────────────────────────────────
$db = getDB();

$result = $db->query("SHOW TABLES");
$existingTables = [];
foreach ($result->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $existingTables[strtolower($table)] = $table;
}

// ── Step 2: Define expected tables from ALL migrations ──────────────
$expectedTables = [
    "widget_definitions",
    "user_dashboard_layouts",
    "template_categories",
    "templates",
    "template_versions",
    "template_module_mappings",
    "template_assets",
    "template_history",
    "template_preview_cache",
    "financial_years",
    "account_groups",
    "account_types",
    "chart_of_accounts",
    "opening_balance_history",
    "voucher_types",
    "journal_entries",
    "journal_entry_details",
    "general_ledger",
    "bank_accounts",
    "cash_accounts",
    "bank_transactions",
    "cash_transactions",
    "vendor_bills",
    "vendor_bill_items",
    "money_transfers",
    "bank_reconciliation",
    "petty_cash_funds",
    "petty_cash_transactions",
    "customer_invoices",
    "invoice_items",
    "customer_receipts",
    "vendors",
    "purchase_orders",
    "purchase_order_items",
    "goods_receipt_notes",
    "goods_receipt_items",
    "vendor_return_items",
    "vendor_returns",
    "vendor_payments",
    "debit_notes",
    "vendor_statements",
    "ap_aging",
    "expense_categories",
    "company_expenses",
    "budgets",
    "budget_versions",
    "budget_lines",
    "budget_transfers",
    "budget_revisions",
    "budget_approval_rules",
    "budget_monitoring_snapshots",
    "variance_analysis",
    "customer_invoices",
    "invoice_items",
    "customer_receipts",
    "tax_types",
    "tax_rates",
    "tax_groups",
    "tax_group_lines",
    "tax_exemptions",
    "tax_registrations",
    "tax_periods",
    "tax_transactions",
    "tax_adjustments",
    "depreciation_methods",
    "fixed_asset_categories",
    "fixed_asset_financials",
    "asset_depreciation_schedules",
    "asset_maintenance",
    "asset_impairment",
    "asset_returns",
    "asset_transfers",
    "payroll_finance_mappings",
    "payroll_finance_postings",
    "payroll_payment_batches",
    "payroll_reconciliation",
    "payroll_payments",
    "accounting_periods",
    "report_audit_log",
    "cash_flow_classifications",
    "report_templates",
    "saved_reports",
    "period_close_settings",
    "year_end_closings",
    "period_close_audit",
    "period_checklist_items",
    "period_checklist_templates",
    "period_opening_balances",
    "companies",
    "company_groups",
    "company_relationships",
    "consolidation_journals",
    "consolidation_journal_lines",
    "consolidation_run_companies",
    "finance_group_audit",
    "cost_centers",
    "procurement_settings",
    "procurement_sequences",
    "purchase_requisitions",
    "purchase_requisition_approvals",
    "purchase_requisition_attachments",
    "purchase_requisition_items",
    "procurement_approval_rules",
    "procurement_audit",
    "request_for_quotations",
    "rfq_items",
    "rfq_vendors",
    "rfq_evaluation_criteria",
    "quotation_selection",
    "quote_evaluations",
    "quote_other_charges",
    "vendor_quotations",
    "vendor_quotation_items",
    "purchase_orders",
    "purchase_order_items",
    "purchase_order_communications",
    "purchase_order_approvals",
    "purchase_order_attachments",
    "purchase_order_charges",
    "purchase_order_revision_items",
    "purchase_order_revisions",
    "po_receiving_summary",
    "po_status_history",
    "ai_agents",
    "ai_agent_runs",
    "ai_agent_steps",
    "goods_receipts",
    "goods_receipt_items",
    "ai_automation_triggers",
    "ai_scheduler_locks",
    "ai_event_registry",
    "ai_event_subscriptions",
    "ai_event_log",
    "ai_automation_notifications",
    "ai_automations",
    "ai_insight_definitions",
    "ai_insight_records",
    "ai_insight_recipients",
    "ai_insight_evaluations",
    "ai_insight_feedback",
    "ai_insight_preferences",
    "ai_command_conversations",
    "ai_command_messages",
    "ai_command_executions",
    "ai_command_suggestions",
    "ai_usage_logs",
    "ai_health_checks",
    "ai_kill_switches",
    "ai_cost_controls",
    "ai_rate_limits",
    "ai_masking_policies",
    "ai_masking_rules",
    "ai_skills",
    "ai_skill_runs",
    "ai_skill_step_results",
    "ai_user_preferences",
    "ai_skill_feedback",
    "ai_skill_analytics",
    "ai_skill_suggestions",
    "ai_skill_templates",
    "ai_workflows",
    "ai_workflow_versions",
    "ai_workflow_runs",
    "ai_workflow_run_nodes",
    "ai_workflow_approvals",
    "ai_workflow_templates",
    "ai_workspaces",
    "ai_workspace_members",
    "ai_workspace_conversations",
    "ai_workspace_messages",
    "ai_workspace_pins",
    "ai_workspace_instructions",
    "ai_workspace_goals",
    "ai_workspace_knowledge",
    "ai_workspace_notifications",
    "ai_workspace_presence",
    "ai_workspace_shared_agent_runs",
    "ai_conversation_shares",
    "ai_documents",
    "ai_document_classifications",
    "ai_document_extractions",
    "ai_document_comparisons",
    "ai_extraction_templates",
    "ai_document_queue",
    "ai_document_audit",
    "ai_document_relationships",
    "ai_document_chunks",
    "ai_document_summaries",
    "ai_sensitive_doc_policies",
    "ai_document_expiry_reminders",
    "ai_document_qna_sessions",
    "ai_document_action_requests",
    "ai_document_version_suggestions",
    "ai_voice_transcriptions",
    "ai_voice_tts_cache",
    "ai_voice_shortcuts",
    "ai_voice_usage",
    "ai_voice_retention_policies",
    "ai_voice_audit",
    "ai_voice_config",
    "ai_connectors",
    "ai_connector_capabilities",
    "ai_integration_connections",
    "ai_integration_credentials",
    "ai_integration_policies",
    "ai_observability_requests",
    "ai_observability_spans",
    "ai_retention_policies",
    "ai_policies",
    "ai_policy_versions",
    "ai_risk_classifications",
    "ai_risk_rules",
    "ai_provider_rules",
    "ai_governance_config",
    "ai_security_events",
    "ai_injection_attempts",
    "ai_masking_fields",
    "ai_emergency_controls",
    "ai_sql_allowed_tables",
    "ai_sql_allowed_fields",
    "ai_tool_configs",
    "ai_policy_simulations",
    "ai_loop_detection_events",
    "ai_agent_definitions",
    "ai_agent_tasks",
    "ai_agent_task_runs",
    "ai_agent_step_executions",
    "ai_intelligence_events",
    "ai_intelligence_evidence",
    "ai_intelligence_rules",
    "ai_intelligence_monitors",
    "ai_intelligence_preferences",
    "ai_intelligence_locks",
    "ai_document_field_confidence",
    "ai_document_matches",
    "ai_document_three_way_matches",
    "ai_document_field_confidence",
    "ai_document_retention_policies",
    "ai_voice_sessions",
    "ai_voice_messages",
    "ai_voice_confirmations",
    "ai_voice_preferences",
    "ai_voice_injection_log",
    "ai_copilot_capabilities",
    "ai_copilot_routes",
    "ai_copilot_sessions",
    "ai_copilot_messages",
    "ai_metric_definitions",
    "ai_dimension_definitions",
    "ai_saved_analyses",
    "ai_forecasts",
    "ai_scenario_variables",
    "ai_scenarios",
    "ai_parameter_definitions",
    "ai_variable_definitions",
    "ai_process_definitions",
    "ai_process_events",
    "ai_process_runs",
    "ai_process_alerts",
    "ai_process_findings",
    "ai_process_metrics",
    "ai_process_versions",
    "ai_process_recommendations",
    "warehouses",
    "locations",
    "units_of_measure",
    "inventory_settings",
    "inventory_stock_ledger",
    "inventory_stock_transfers",
    "inventory_adjustments",
    "inventory_adjustment_attachments",
    "inventory_sequences",
    "inventory_reservations",
    "inventory_reconciliations",
    "inventory_reconciliation_items",
];

// ── Step 3: Find missing tables ────────────────────────────────────
$missingTables = [];
foreach ($expectedTables as $tbl) {
    $lc = strtolower($tbl);
    if (!isset($existingTables[$lc])) {
        $missingTables[] = $tbl;
    }
}

// Calculate stats
$totalExpected = count($expectedTables);
$totalExisting = count($existingTables);
$totalMissing = count($missingTables);
$pctComplete = $totalExpected > 0 ? round(($totalExisting / $totalExpected) * 100, 1) : 0;
$pctMissing = $totalExpected > 0 ? round(($totalMissing / $totalExpected) * 100, 1) : 0;

// ── Step 4: Handle migration execution ─────────────────────────────
$output = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $migrationFile = basename($_POST['run_migration']);
    
    // Security: only allow specific migration files
    $allowedMigrations = [
        "001_dashboard_customization.sql",
        "002_template_designer.sql",
        "003_finance_foundation.sql",
        "004_finance_accounting_engine.sql",
        "005_finance_banking.sql",
        "006_finance_petty_cash.sql",
        "007_finance_ar.sql",
        "008_finance_ap.sql",
        "009_finance_ap_vendor_invoice.sql",
        "010_finance_expense_management.sql",
        "011_finance_budget_management.sql",
        "012_carbon_charts_catalog.sql",
        "013_finance_tax_management.sql",
        "014_fixed_assets_f9.sql",
        "015_finance_payroll_integration.sql",
        "016_financial_reports_f11.sql",
        "017_period_close_f12.sql",
        "018_multicompany_f13.sql",
        "018_user_theme_preference.sql",
        "019_procurement_p1.sql",
        "020_rfq_p2.sql",
        "021_po_p3.sql",
        "022_ai_agents_f12.sql",
        "022_receipts_p4.sql",
        "023_ai_agents_f12.sql",
        "024_ai_automation_f13.sql",
        "025_ai_insights_f14.sql",
        "026_ai_command_center_f15.sql",
        "027_ai_management_f16.sql",
        "028_ai_skills_f17.sql",
        "029_ai_workflows_f18.sql",
        "030_ai_workspaces_f19.sql",
        "031_ai_doc_intelligence_f20.sql",
        "031_ai_workspaces_hardening.sql",
        "032_ai_permissions_backfill.sql",
        "032_ai_voice_f21.sql",
        "033_ai_doc_intelligence_prod.sql",
        "034_ai_doc_audit_enums.sql",
        "035_ai_integrations_f22.sql",
        "036_ai_observability_f23.sql",
        "037_ai_governance_f24.sql",
        "038_ai_security_f24.sql",
        "039_ai_f24_complete.sql",
        "040_ai_agent_runtime_f25.sql",
        "041_ai_intelligence_f26.sql",
        "042_ai_document_intelligence_f27.sql",
        "043_ai_voice_conversational_f28.sql",
        "044_ai_copilot_f29.sql",
        "045_ai_analytics_f30.sql",
        "046_ai_process_intelligence_f31.sql",
        "047_ai_copilot_permissions_fix.sql",
        "047_ai_communications_f32.sql",
        "048_ai_knowledge_rag_f33.sql",
        "049_ai_knowledge_tenant_scope_f34.sql",
        "050_inventory_core_i1.sql",
        "051_inventory_i2_costing_valuation.sql",
        "052_inventory_i3_controls_reconciliation.sql",
    ];
    
    if (in_array($migrationFile, $allowedMigrations)) {
        $db = getDB(); // Fresh connection
        $sql = file_get_contents("config/migrations/$migrationFile");
        if ($sql) {
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s)
            );
            
            $count = 0;
            $errors = [];
            foreach ($statements as $stmt) {
                try {
                    $db->exec($stmt);
                    $count++;
                } catch (PDOException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            
            if ($count > 0) {
                $output[] = ['type' => 'success', 'msg' => "Executed $count statement(s) from $migrationFile"];
            }
            if (!empty($errors)) {
                $output[] = ['type' => 'warning', 'msg' => "Errors: " . implode('; ', array_slice($errors, 0, 3))];
            }
            if ($count === 0 && empty($errors)) {
                $output[] = ['type' => 'info', 'msg' => "No statements executed from $migrationFile"];
            }
        } else {
            $output[] = ['type' => 'error', 'msg' => "File not found: config/migrations/$migrationFile"];
        }
    } else {
        $output[] = ['type' => 'error', 'msg' => "Invalid migration file selected"];
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Migration Inspector</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --bg-secondary: #11111a;
            --card: #1a1a24;
            --border: #2a2a3a;
            --fg: #f0f0f5;
            --muted: #737380;
            --accent: #10b981;
            --accent-hover: #059669;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--fg);
            min-height: 100vh;
            margin: 0;
        }
        
        .navbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
        }
        
        .navbar .title {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card .label {
            color: var(--muted);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.5rem;
        }
        
        .main-content {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            min-height: 400px;
        }
        
        .header-bar {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-good { background: rgba(16, 185, 129, 0.15); color: var(--accent); }
        .status-warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .status-critical { background: rgba(239, 68, 68, 0.15); color: var(--error); }
        
        .table-section {
            padding: 1.5rem 2rem;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--accent);
        }
        
        .missing-tables-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .missing-table-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        
        .missing-table-item:hover {
            border-color: var(--accent);
        }
        
        .missing-table-item .table-name {
            font-weight: 600;
            color: var(--fg);
            font-family: monospace;
        }
        
        .missing-table-item .migration-source {
            font-size: 0.75rem;
            color: var(--muted);
            font-family: monospace;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }
        
        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.25rem 0.75rem;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .filter-btn:not(.active):hover {
            background: var(--bg-secondary);
            color: var(--fg);
        }
        
        .migration-form {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .migration-form h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .migration-form select {
            width: 100%;
            padding: 0.75rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--fg);
            font-size: 1rem;
            cursor: pointer;
        }
        
        .migration-form button {
            width: 100%;
            margin-top: 1rem;
        }
        
        .output-messages {
            margin-top: 1rem;
        }
        
        .output-message {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .output-message.success { background: rgba(16, 185, 129, 0.15); color: var(--accent); }
        .output-message.warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .output-message.error { background: rgba(239, 68, 68, 0.15); color: var(--error); }
        .output-message.info { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        
        .no-missing {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }
        
        @media (max-width: 640px) {
            .navbar .title { display: none; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <span class="title">ERP Migration Inspector</span>
        </div>
    </nav>

    <div class="container">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $totalExpected; ?></div>
                <div class="label">Expected Tables</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $totalExisting; ?></div>
                <div class="label">Existing Tables</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $totalMissing; ?></div>
                <div class="label">Missing Tables</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: var(<?php echo $pctComplete >= 90 ? '--accent' : ($pctComplete >= 75 ? '--warning' : '--error'); ?>);">
                    <?php echo number_format($pctComplete, 1) . '%'; ?>
                </div>
                <div class="label">Completion</div>
            </div>
        </div>

        <!-- Status Badge -->
        <div class="status-badge status-<?php echo $pctComplete >= 90 ? 'good' : ($pctComplete >= 75 ? 'warning' : 'critical'); ?>">
            <?php echo number_format($pctComplete, 1) ?>% Complete | <?php echo $totalMissing ?> Missing
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Summary Section -->
            <div class="table-section">
                <h1 class="header-bar">Migration Table Status</h1>
                
                <!-- Migration Form -->
                <form method="POST" class="migration-form">
                    <h3>Run Missing Migration</h3>
                    <select name="run_migration" onchange="this.form.submit()">
                        <option value="">-- Select Migration File --</option>
                        <?php
                        $groups = [
                            'Dashboard & Template' => ['001_dashboard_customization.sql', '002_template_designer.sql'],
                            'Finance Foundation' => ['003_finance_foundation.sql', '004_finance_accounting_engine.sql'],
                            'Finance Banking' => ['005_finance_banking.sql', '006_finance_petty_cash.sql', '007_finance_ar.sql'],
                            'Finance AP' => ['008_finance_ap.sql', '009_finance_ap_vendor_invoice.sql', '010_finance_expense_management.sql'],
                            'Budget & Tax' => ['011_finance_budget_management.sql', '013_finance_tax_management.sql'],
                            'Fixed Assets' => ['014_fixed_assets_f9.sql'],
                            'Payroll & Reports' => ['015_finance_payroll_integration.sql', '016_financial_reports_f11.sql'],
                            'Period Close (F12)' => ['017_period_close_f12.sql'],
                            'Multi-company (F13)' => ['018_multicompany_f13.sql', '018_user_theme_preference.sql'],
                            'Procurement' => ['019_procurement_p1.sql', '020_rfq_p2.sql', '021_po_p3.sql'],
                            'AI Agents' => ['022_ai_agents_f12.sql', '022_receipts_p4.sql'],
                            'AI Automation' => ['023_ai_automation_f13.sql', '024_ai_automation_f13.sql'],
                            'AI Insights' => ['025_ai_insights_f14.sql'],
                            'AI Command Center' => ['026_ai_command_center_f15.sql'],
                            'AI Skills' => ['027_ai_management_f16.sql'],
                            'AI Workflows' => ['028_ai_skills_f17.sql', '029_ai_workflows_f18.sql'],
                            'AI Workspaces' => ['030_ai_workspaces_f19.sql'],
                            'AI Document Intelligence' => ['031_ai_doc_intelligence_f20.sql', '033_ai_doc_intelligence_prod.sql'],
                            'AI Voice' => ['032_ai_voice_f21.sql', '033_ai_doc_intelligence_prod.sql'],
                            'AI Governance' => ['034_ai_doc_audit_enums.sql', '035_ai_integrations_f22.sql', '036_ai_observability_f23.sql', '037_ai_governance_f24.sql', '038_ai_security_f24.sql'],
                            'AI F24 Complete' => ['039_ai_f24_complete.sql'],
                            'AI Agent Runtime' => ['040_ai_agent_runtime_f25.sql'],
                            'AI Intelligence' => ['041_ai_intelligence_f26.sql'],
                            'AI Doc Intelligence' => ['042_ai_document_intelligence_f27.sql', '043_ai_voice_conversational_f28.sql'],
                            'AI Copilot' => ['044_ai_copilot_f29.sql'],
                            'AI Analytics' => ['045_ai_analytics_f30.sql'],
                            'AI Process Intelligence' => ['046_ai_process_intelligence_f31.sql'],
                            'AI Communications' => ['047_ai_copilot_permissions_fix.sql', '047_ai_communications_f32.sql'],
                            'AI Knowledge' => ['048_ai_knowledge_rag_f33.sql', '049_ai_knowledge_tenant_scope_f34.sql'],
                            'Inventory' => ['050_inventory_core_i1.sql', '051_inventory_i2_costing_valuation.sql', '052_inventory_i3_controls_reconciliation.sql'],
                        ];
                        
                        foreach ($groups as $groupName => $files) {
                            echo '<optgroup label="' . htmlspecialchars($groupName) . '">';
                            foreach ($files as $f) {
                                $selected = (isset($_POST['run_migration']) && $_POST['run_migration'] === $f) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($f) . '" ' . $selected . '>' . htmlspecialchars($f) . '</option>';
                            }
                            echo '</optgroup>';
                        }
                        ?>
                    </select>
                    <p style="font-size: 0.75rem; color: var(--muted); margin-top: 0.5rem;">
                        Note: Some migrations are ALTER TABLE operations, not CREATE TABLE.
                    </p>
                </form>

                <!-- Missing Tables -->
                <div class="section-title">Missing Tables (<php echo count($missingTables); ?>)</div>
                
                <?php if (empty($missingTables)): ?>
                    <div class="no-missing">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        <p>All expected tables exist! ✅</p>
                    </div>
                <?php else: ?>
                    <div class="missing-tables-list">
                        <?php foreach ($missingTables as $tbl): 
                            $sourceFile = '';
                            foreach ($groups as $group => $fileList) {
                                if (in_array($tbl, $fileList)) {
                                    $sourceFile = $group;
                                    break;
                                }
                            }
                        ?>
                            <div class="missing-table-item">
                                <div class="table-name">`<?php echo $tbl; ?>`</div>
                                <div class="migration-source">From: <?php echo $sourceFile ? htmlspecialchars($sourceFile) : 'Various migrations'; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Output Messages -->
        <div class="output-messages">
            <?php foreach ($output ?? [] as $msg): ?>
                <div class="output-message <?php echo $msg['type']; ?>">
                    <?php echo htmlspecialchars($msg['msg']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.querySelector('select');
            if (select) {
                const urlParams = new URLSearchParams(window.location.search);
                const selected = urlParams.get('migration');
                if (selected) {
                    select.value = selected;
                }
            }
        });
    </script>
</body>
</html>