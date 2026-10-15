# ERP database structure — `erp_db`

Full data dictionary generated **2026-10-15 04:55:19** from the live database by
`php newdump/generate_structure.php`. Read straight from `information_schema` — this file is never
hand-maintained, so it always reflects the real schema.

| | |
|---|---|
| Tables | **469** |
| Columns | **6,119** |
| Total rows | **12,542** |
| Engine | InnoDB |
| Character set | utf8mb4 |

Legend for the Key column below: `PK` primary, `UQ` unique, `IDX` indexed, `FK` foreign key (target shown in Notes).

## Domains

| Domain | Tables | Rows | |
|---|---:|---:|---|
| [Contractor master (new)](#contractor-master-new) | 4 | 0 | **added by `2026_10_14_contractor_master.php`**|
| [Employee & HR](#employee-hr) | 26 | 429 ||
| [Finance & General Ledger](#finance-general-ledger) | 91 | 2,295 ||
| [Procurement & Vendors](#procurement-vendors) | 34 | 2,366 ||
| [Sales & CRM](#sales-crm) | 7 | 28 ||
| [Projects & Tasks](#projects-tasks) | 14 | 164 ||
| [Inventory & Assets](#inventory-assets) | 12 | 29 ||
| [Helpdesk](#helpdesk) | 9 | 35 ||
| [AI module](#ai-module) | 217 | 2,988 ||
| [Configuration & Lookups](#configuration-lookups) | 21 | 456 ||
| [System & Security](#system-security) | 10 | 3,650 ||
| [Other](#other) | 24 | 102 ||

## Table index

| Table | Cols | Rows | Domain |
|---|---:|---:|---|
| [`accounting_periods`](#accounting-periods) | 11 | 42 | Finance & General Ledger |
| [`account_groups`](#account-groups) | 7 | 48 | Finance & General Ledger |
| [`account_types`](#account-types) | 5 | 27 | Finance & General Ledger |
| [`ai_agents`](#ai-agents) | 22 | 0 | AI module |
| [`ai_agent_approvals`](#ai-agent-approvals) | 21 | 6 | AI module |
| [`ai_agent_artifacts`](#ai-agent-artifacts) | 16 | 0 | AI module |
| [`ai_agent_checkpoints`](#ai-agent-checkpoints) | 12 | 0 | AI module |
| [`ai_agent_definitions`](#ai-agent-definitions) | 33 | 3 | AI module |
| [`ai_agent_events`](#ai-agent-events) | 14 | 18 | AI module |
| [`ai_agent_plans`](#ai-agent-plans) | 19 | 3 | AI module |
| [`ai_agent_plan_steps`](#ai-agent-plan-steps) | 22 | 3 | AI module |
| [`ai_agent_runs`](#ai-agent-runs) | 19 | 0 | AI module |
| [`ai_agent_steps`](#ai-agent-steps) | 13 | 0 | AI module |
| [`ai_agent_step_executions`](#ai-agent-step-executions) | 26 | 7 | AI module |
| [`ai_agent_tasks`](#ai-agent-tasks) | 24 | 11 | Projects & Tasks |
| [`ai_agent_task_runs`](#ai-agent-task-runs) | 20 | 12 | Projects & Tasks |
| [`ai_analytics_runs`](#ai-analytics-runs) | 23 | 108 | AI module |
| [`ai_audit_log`](#ai-audit-log) | 8 | 1,001 | AI module |
| [`ai_automations`](#ai-automations) | 20 | 11 | AI module |
| [`ai_automation_notifications`](#ai-automation-notifications) | 9 | 4 | AI module |
| [`ai_automation_runs`](#ai-automation-runs) | 16 | 10 | AI module |
| [`ai_automation_triggers`](#ai-automation-triggers) | 7 | 12 | AI module |
| [`ai_autonomy_settings`](#ai-autonomy-settings) | 16 | 0 | AI module |
| [`ai_bulk_action_thresholds`](#ai-bulk-action-thresholds) | 8 | 0 | AI module |
| [`ai_command_conversations`](#ai-command-conversations) | 10 | 8 | AI module |
| [`ai_command_executions`](#ai-command-executions) | 17 | 11 | AI module |
| [`ai_command_messages`](#ai-command-messages) | 10 | 22 | AI module |
| [`ai_command_suggestions`](#ai-command-suggestions) | 9 | 49 | AI module |
| [`ai_communication_actions`](#ai-communication-actions) | 25 | 0 | AI module |
| [`ai_communication_batch_jobs`](#ai-communication-batch-jobs) | 18 | 0 | AI module |
| [`ai_communication_classifications`](#ai-communication-classifications) | 17 | 0 | AI module |
| [`ai_communication_context`](#ai-communication-context) | 12 | 0 | AI module |
| [`ai_communication_drafts`](#ai-communication-drafts) | 38 | 0 | AI module |
| [`ai_communication_events`](#ai-communication-events) | 15 | 0 | AI module |
| [`ai_communication_followups`](#ai-communication-followups) | 17 | 0 | AI module |
| [`ai_communication_rules`](#ai-communication-rules) | 19 | 5 | AI module |
| [`ai_communication_summaries`](#ai-communication-summaries) | 22 | 0 | AI module |
| [`ai_communication_templates`](#ai-communication-templates) | 22 | 6 | AI module |
| [`ai_communication_template_versions`](#ai-communication-template-versions) | 10 | 0 | AI module |
| [`ai_config_audit`](#ai-config-audit) | 8 | 20 | AI module |
| [`ai_confirmation_tokens`](#ai-confirmation-tokens) | 16 | 0 | AI module |
| [`ai_connectors`](#ai-connectors) | 18 | 3 | AI module |
| [`ai_connector_capabilities`](#ai-connector-capabilities) | 19 | 4 | AI module |
| [`ai_connector_rest_endpoints`](#ai-connector-rest-endpoints) | 18 | 6 | AI module |
| [`ai_conversations`](#ai-conversations) | 10 | 47 | AI module |
| [`ai_conversation_shares`](#ai-conversation-shares) | 6 | 0 | AI module |
| [`ai_copilot_capabilities`](#ai-copilot-capabilities) | 17 | 16 | AI module |
| [`ai_copilot_feedback`](#ai-copilot-feedback) | 8 | 1 | AI module |
| [`ai_copilot_intents`](#ai-copilot-intents) | 13 | 10 | AI module |
| [`ai_copilot_messages`](#ai-copilot-messages) | 24 | 20 | AI module |
| [`ai_copilot_routes`](#ai-copilot-routes) | 10 | 22 | AI module |
| [`ai_copilot_sessions`](#ai-copilot-sessions) | 16 | 15 | AI module |
| [`ai_cost_controls`](#ai-cost-controls) | 10 | 11 | AI module |
| [`ai_data_classifications`](#ai-data-classifications) | 9 | 0 | AI module |
| [`ai_data_classification_fields`](#ai-data-classification-fields) | 9 | 0 | AI module |
| [`ai_dimension_definitions`](#ai-dimension-definitions) | 7 | 9 | AI module |
| [`ai_documents`](#ai-documents) | 28 | 18 | AI module |
| [`ai_document_action_requests`](#ai-document-action-requests) | 10 | 0 | AI module |
| [`ai_document_admin_config`](#ai-document-admin-config) | 8 | 12 | AI module |
| [`ai_document_audit`](#ai-document-audit) | 7 | 27 | AI module |
| [`ai_document_chunks`](#ai-document-chunks) | 9 | 0 | AI module |
| [`ai_document_classifications`](#ai-document-classifications) | 9 | 3 | AI module |
| [`ai_document_comparisons`](#ai-document-comparisons) | 11 | 3 | AI module |
| [`ai_document_expiry_reminders`](#ai-document-expiry-reminders) | 7 | 0 | AI module |
| [`ai_document_extractions`](#ai-document-extractions) | 16 | 3 | AI module |
| [`ai_document_field_confidence`](#ai-document-field-confidence) | 15 | 0 | AI module |
| [`ai_document_matches`](#ai-document-matches) | 15 | 0 | AI module |
| [`ai_document_qna_sessions`](#ai-document-qna-sessions) | 9 | 0 | AI module |
| [`ai_document_queue`](#ai-document-queue) | 12 | 45 | AI module |
| [`ai_document_relationships`](#ai-document-relationships) | 11 | 0 | AI module |
| [`ai_document_retention_policies`](#ai-document-retention-policies) | 12 | 6 | AI module |
| [`ai_document_security_events`](#ai-document-security-events) | 12 | 0 | AI module |
| [`ai_document_summaries`](#ai-document-summaries) | 10 | 3 | AI module |
| [`ai_document_three_way_matches`](#ai-document-three-way-matches) | 21 | 0 | AI module |
| [`ai_document_type_configs`](#ai-document-type-configs) | 10 | 12 | AI module |
| [`ai_document_version_suggestions`](#ai-document-version-suggestions) | 9 | 0 | AI module |
| [`ai_emergency_controls`](#ai-emergency-controls) | 13 | 10 | AI module |
| [`ai_error_registry`](#ai-error-registry) | 14 | 0 | AI module |
| [`ai_event_log`](#ai-event-log) | 8 | 8 | AI module |
| [`ai_event_registry`](#ai-event-registry) | 7 | 23 | AI module |
| [`ai_event_subscriptions`](#ai-event-subscriptions) | 6 | 1 | AI module |
| [`ai_extraction_templates`](#ai-extraction-templates) | 13 | 3 | AI module |
| [`ai_financial_thresholds`](#ai-financial-thresholds) | 9 | 0 | AI module |
| [`ai_findings`](#ai-findings) | 12 | 6 | AI module |
| [`ai_finding_evidence`](#ai-finding-evidence) | 7 | 4 | AI module |
| [`ai_forecasts`](#ai-forecasts) | 20 | 3 | AI module |
| [`ai_governance_audit`](#ai-governance-audit) | 12 | 138 | AI module |
| [`ai_governance_config`](#ai-governance-config) | 6 | 0 | AI module |
| [`ai_health_checks`](#ai-health-checks) | 11 | 17 | AI module |
| [`ai_incoming_webhook_events`](#ai-incoming-webhook-events) | 10 | 0 | AI module |
| [`ai_injection_attempts`](#ai-injection-attempts) | 15 | 0 | AI module |
| [`ai_insight_definitions`](#ai-insight-definitions) | 19 | 4 | AI module |
| [`ai_insight_evaluations`](#ai-insight-evaluations) | 12 | 0 | AI module |
| [`ai_insight_feedback`](#ai-insight-feedback) | 6 | 4 | AI module |
| [`ai_insight_preferences`](#ai-insight-preferences) | 11 | 1 | AI module |
| [`ai_insight_recipients`](#ai-insight-recipients) | 8 | 13 | AI module |
| [`ai_insight_records`](#ai-insight-records) | 19 | 17 | AI module |
| [`ai_integration_activity`](#ai-integration-activity) | 14 | 15 | AI module |
| [`ai_integration_connections`](#ai-integration-connections) | 17 | 5 | AI module |
| [`ai_integration_credentials`](#ai-integration-credentials) | 15 | 5 | AI module |
| [`ai_integration_health`](#ai-integration-health) | 15 | 5 | AI module |
| [`ai_integration_policies`](#ai-integration-policies) | 9 | 6 | AI module |
| [`ai_intelligence_events`](#ai-intelligence-events) | 31 | 8 | AI module |
| [`ai_intelligence_evidence`](#ai-intelligence-evidence) | 12 | 0 | AI module |
| [`ai_intelligence_feedback`](#ai-intelligence-feedback) | 6 | 0 | AI module |
| [`ai_intelligence_locks`](#ai-intelligence-locks) | 4 | 0 | AI module |
| [`ai_intelligence_monitors`](#ai-intelligence-monitors) | 19 | 0 | AI module |
| [`ai_intelligence_preferences`](#ai-intelligence-preferences) | 11 | 0 | AI module |
| [`ai_intelligence_rules`](#ai-intelligence-rules) | 17 | 5 | AI module |
| [`ai_intelligence_runs`](#ai-intelligence-runs) | 12 | 0 | AI module |
| [`ai_intelligence_watchlists`](#ai-intelligence-watchlists) | 11 | 0 | AI module |
| [`ai_kill_switches`](#ai-kill-switches) | 10 | 42 | AI module |
| [`ai_knowledge_chunks`](#ai-knowledge-chunks) | 13 | 8 | AI module |
| [`ai_knowledge_citations`](#ai-knowledge-citations) | 11 | 0 | AI module |
| [`ai_knowledge_collections`](#ai-knowledge-collections) | 10 | 31 | AI module |
| [`ai_knowledge_collection_access`](#ai-knowledge-collection-access) | 5 | 0 | AI module |
| [`ai_knowledge_drafts`](#ai-knowledge-drafts) | 18 | 0 | AI module |
| [`ai_knowledge_events`](#ai-knowledge-events) | 13 | 0 | AI module |
| [`ai_knowledge_freshness_rules`](#ai-knowledge-freshness-rules) | 9 | 12 | AI module |
| [`ai_knowledge_gaps`](#ai-knowledge-gaps) | 16 | 0 | AI module |
| [`ai_knowledge_permissions`](#ai-knowledge-permissions) | 7 | 0 | AI module |
| [`ai_knowledge_queries`](#ai-knowledge-queries) | 24 | 0 | AI module |
| [`ai_knowledge_retrieval_logs`](#ai-knowledge-retrieval-logs) | 8 | 4 | AI module |
| [`ai_knowledge_reviews`](#ai-knowledge-reviews) | 18 | 0 | AI module |
| [`ai_knowledge_sources`](#ai-knowledge-sources) | 23 | 8 | AI module |
| [`ai_knowledge_versions`](#ai-knowledge-versions) | 18 | 0 | AI module |
| [`ai_loop_detection`](#ai-loop-detection) | 18 | 0 | AI module |
| [`ai_loop_detection_events`](#ai-loop-detection-events) | 6 | 0 | AI module |
| [`ai_masking_policies`](#ai-masking-policies) | 8 | 8 | AI module |
| [`ai_masking_rules`](#ai-masking-rules) | 13 | 0 | AI module |
| [`ai_messages`](#ai-messages) | 8 | 179 | AI module |
| [`ai_metric_definitions`](#ai-metric-definitions) | 20 | 12 | AI module |
| [`ai_models`](#ai-models) | 21 | 9 | AI module |
| [`ai_observability_benchmarks`](#ai-observability-benchmarks) | 11 | 8 | AI module |
| [`ai_observability_benchmark_results`](#ai-observability-benchmark-results) | 11 | 0 | AI module |
| [`ai_observability_daily_aggregates`](#ai-observability-daily-aggregates) | 24 | 8 | AI module |
| [`ai_observability_eval_cases`](#ai-observability-eval-cases) | 19 | 4 | AI module |
| [`ai_observability_eval_datasets`](#ai-observability-eval-datasets) | 12 | 1 | AI module |
| [`ai_observability_eval_results`](#ai-observability-eval-results) | 16 | 4 | AI module |
| [`ai_observability_eval_runs`](#ai-observability-eval-runs) | 19 | 1 | AI module |
| [`ai_observability_experiments`](#ai-observability-experiments) | 15 | 0 | AI module |
| [`ai_observability_feedback`](#ai-observability-feedback) | 14 | 4 | AI module |
| [`ai_observability_feedback_reviews`](#ai-observability-feedback-reviews) | 9 | 0 | AI module |
| [`ai_observability_findings`](#ai-observability-findings) | 16 | 1 | AI module |
| [`ai_observability_reports`](#ai-observability-reports) | 9 | 1 | AI module |
| [`ai_observability_requests`](#ai-observability-requests) | 36 | 36 | AI module |
| [`ai_observability_spans`](#ai-observability-spans) | 19 | 102 | AI module |
| [`ai_parameter_schemas`](#ai-parameter-schemas) | 10 | 0 | AI module |
| [`ai_pending_actions`](#ai-pending-actions) | 14 | 19 | AI module |
| [`ai_policies`](#ai-policies) | 16 | 2 | AI module |
| [`ai_policy_simulations`](#ai-policy-simulations) | 5 | 0 | AI module |
| [`ai_policy_versions`](#ai-policy-versions) | 11 | 2 | AI module |
| [`ai_process_alerts`](#ai-process-alerts) | 12 | 1 | AI module |
| [`ai_process_definitions`](#ai-process-definitions) | 20 | 4 | AI module |
| [`ai_process_events`](#ai-process-events) | 22 | 53 | AI module |
| [`ai_process_findings`](#ai-process-findings) | 18 | 5 | AI module |
| [`ai_process_metrics`](#ai-process-metrics) | 21 | 8 | AI module |
| [`ai_process_proposals`](#ai-process-proposals) | 17 | 1 | AI module |
| [`ai_process_recommendations`](#ai-process-recommendations) | 16 | 5 | AI module |
| [`ai_process_runs`](#ai-process-runs) | 31 | 22 | AI module |
| [`ai_process_simulations`](#ai-process-simulations) | 10 | 6 | AI module |
| [`ai_process_versions`](#ai-process-versions) | 7 | 4 | AI module |
| [`ai_prompt_versions`](#ai-prompt-versions) | 12 | 1 | AI module |
| [`ai_providers`](#ai-providers) | 19 | 11 | AI module |
| [`ai_provider_rules`](#ai-provider-rules) | 13 | 0 | AI module |
| [`ai_rate_limits`](#ai-rate-limits) | 10 | 6 | AI module |
| [`ai_rate_limit_configs`](#ai-rate-limit-configs) | 12 | 0 | AI module |
| [`ai_rate_limit_counters`](#ai-rate-limit-counters) | 11 | 36 | AI module |
| [`ai_research_notes`](#ai-research-notes) | 7 | 6 | AI module |
| [`ai_research_sessions`](#ai-research-sessions) | 8 | 2 | AI module |
| [`ai_retention_policies`](#ai-retention-policies) | 7 | 14 | AI module |
| [`ai_review_queue`](#ai-review-queue) | 16 | 0 | AI module |
| [`ai_risk_classifications`](#ai-risk-classifications) | 11 | 0 | AI module |
| [`ai_risk_rules`](#ai-risk-rules) | 11 | 0 | AI module |
| [`ai_safety_policies`](#ai-safety-policies) | 11 | 12 | AI module |
| [`ai_saved_analyses`](#ai-saved-analyses) | 16 | 13 | AI module |
| [`ai_scenarios`](#ai-scenarios) | 15 | 4 | AI module |
| [`ai_scenario_variables`](#ai-scenario-variables) | 9 | 8 | AI module |
| [`ai_scheduler_heartbeats`](#ai-scheduler-heartbeats) | 12 | 0 | AI module |
| [`ai_scheduler_locks`](#ai-scheduler-locks) | 6 | 0 | AI module |
| [`ai_security_events`](#ai-security-events) | 19 | 33 | AI module |
| [`ai_sensitive_doc_policies`](#ai-sensitive-doc-policies) | 11 | 0 | AI module |
| [`ai_skills`](#ai-skills) | 28 | 5 | Employee & HR |
| [`ai_skill_analytics`](#ai-skill-analytics) | 10 | 2 | Employee & HR |
| [`ai_skill_feedback`](#ai-skill-feedback) | 7 | 2 | Employee & HR |
| [`ai_skill_runs`](#ai-skill-runs) | 16 | 7 | Employee & HR |
| [`ai_skill_step_results`](#ai-skill-step-results) | 11 | 0 | Employee & HR |
| [`ai_skill_suggestions`](#ai-skill-suggestions) | 9 | 0 | Employee & HR |
| [`ai_skill_templates`](#ai-skill-templates) | 8 | 6 | Employee & HR |
| [`ai_sql_allowed_fields`](#ai-sql-allowed-fields) | 7 | 0 | AI module |
| [`ai_sql_allowed_tables`](#ai-sql-allowed-tables) | 6 | 18 | AI module |
| [`ai_sync_jobs`](#ai-sync-jobs) | 19 | 1 | AI module |
| [`ai_sync_job_rows`](#ai-sync-job-rows) | 11 | 3 | Employee & HR |
| [`ai_sync_mappings`](#ai-sync-mappings) | 15 | 1 | AI module |
| [`ai_tasks`](#ai-tasks) | 18 | 15 | Projects & Tasks |
| [`ai_task_steps`](#ai-task-steps) | 17 | 16 | Projects & Tasks |
| [`ai_tool_configs`](#ai-tool-configs) | 7 | 0 | AI module |
| [`ai_usage_daily_aggregates`](#ai-usage-daily-aggregates) | 15 | 0 | AI module |
| [`ai_usage_logs`](#ai-usage-logs) | 15 | 53 | AI module |
| [`ai_user_preferences`](#ai-user-preferences) | 10 | 0 | AI module |
| [`ai_voice_audit`](#ai-voice-audit) | 7 | 51 | AI module |
| [`ai_voice_config`](#ai-voice-config) | 8 | 0 | AI module |
| [`ai_voice_confirmations`](#ai-voice-confirmations) | 17 | 0 | AI module |
| [`ai_voice_injection_log`](#ai-voice-injection-log) | 9 | 0 | AI module |
| [`ai_voice_messages`](#ai-voice-messages) | 24 | 0 | AI module |
| [`ai_voice_preferences`](#ai-voice-preferences) | 12 | 0 | AI module |
| [`ai_voice_retention_policies`](#ai-voice-retention-policies) | 10 | 1 | AI module |
| [`ai_voice_sessions`](#ai-voice-sessions) | 12 | 0 | AI module |
| [`ai_voice_shortcuts`](#ai-voice-shortcuts) | 9 | 6 | AI module |
| [`ai_voice_transcriptions`](#ai-voice-transcriptions) | 17 | 15 | AI module |
| [`ai_voice_tts_cache`](#ai-voice-tts-cache) | 15 | 5 | AI module |
| [`ai_voice_usage`](#ai-voice-usage) | 9 | 6 | AI module |
| [`ai_webhook_definitions`](#ai-webhook-definitions) | 21 | 1 | AI module |
| [`ai_webhook_deliveries`](#ai-webhook-deliveries) | 12 | 3 | AI module |
| [`ai_workflows`](#ai-workflows) | 17 | 1 | AI module |
| [`ai_workflow_approvals`](#ai-workflow-approvals) | 12 | 0 | AI module |
| [`ai_workflow_runs`](#ai-workflow-runs) | 19 | 0 | AI module |
| [`ai_workflow_run_nodes`](#ai-workflow-run-nodes) | 11 | 0 | AI module |
| [`ai_workflow_templates`](#ai-workflow-templates) | 9 | 5 | AI module |
| [`ai_workflow_versions`](#ai-workflow-versions) | 14 | 1 | AI module |
| [`ai_workspaces`](#ai-workspaces) | 9 | 10 | AI module |
| [`ai_workspace_activity`](#ai-workspace-activity) | 8 | 61 | AI module |
| [`ai_workspace_conversations`](#ai-workspace-conversations) | 8 | 3 | AI module |
| [`ai_workspace_goals`](#ai-workspace-goals) | 9 | 0 | AI module |
| [`ai_workspace_instructions`](#ai-workspace-instructions) | 8 | 0 | AI module |
| [`ai_workspace_knowledge`](#ai-workspace-knowledge) | 5 | 2 | AI module |
| [`ai_workspace_members`](#ai-workspace-members) | 6 | 17 | AI module |
| [`ai_workspace_messages`](#ai-workspace-messages) | 7 | 6 | AI module |
| [`ai_workspace_notifications`](#ai-workspace-notifications) | 10 | 48 | AI module |
| [`ai_workspace_pins`](#ai-workspace-pins) | 8 | 2 | AI module |
| [`ai_workspace_presence`](#ai-workspace-presence) | 4 | 4 | AI module |
| [`ai_workspace_shared_agent_runs`](#ai-workspace-shared-agent-runs) | 7 | 2 | AI module |
| [`app_settings`](#app-settings) | 9 | 8 | Configuration & Lookups |
| [`assets`](#assets) | 16 | 9 | Finance & General Ledger |
| [`asset_assignments`](#asset-assignments) | 11 | 5 | Finance & General Ledger |
| [`asset_categories`](#asset-categories) | 6 | 9 | Finance & General Ledger |
| [`asset_depreciation_schedules`](#asset-depreciation-schedules) | 12 | 0 | Finance & General Ledger |
| [`asset_disposal`](#asset-disposal) | 12 | 0 | Finance & General Ledger |
| [`asset_disposals`](#asset-disposals) | 13 | 0 | Finance & General Ledger |
| [`asset_impairment`](#asset-impairment) | 12 | 0 | Finance & General Ledger |
| [`asset_impairments`](#asset-impairments) | 12 | 0 | Finance & General Ledger |
| [`asset_maintenance`](#asset-maintenance) | 11 | 4 | Finance & General Ledger |
| [`asset_returns`](#asset-returns) | 7 | 0 | Finance & General Ledger |
| [`asset_transfers`](#asset-transfers) | 14 | 0 | Finance & General Ledger |
| [`asset_transfer_history`](#asset-transfer-history) | 16 | 0 | Finance & General Ledger |
| [`attendance`](#attendance) | 10 | 218 | Employee & HR |
| [`audit_logs`](#audit-logs) | 6 | 2,147 | System & Security |
| [`bank_accounts`](#bank-accounts) | 13 | 1 | Finance & General Ledger |
| [`bank_reconciliation`](#bank-reconciliation) | 10 | 0 | Finance & General Ledger |
| [`bank_reconciliation_items`](#bank-reconciliation-items) | 7 | 0 | Finance & General Ledger |
| [`bank_transactions`](#bank-transactions) | 10 | 1 | Finance & General Ledger |
| [`bill_status_history`](#bill-status-history) | 7 | 2 | Other |
| [`budgets`](#budgets) | 12 | 1 | Finance & General Ledger |
| [`budget_lines`](#budget-lines) | 11 | 8 | Finance & General Ledger |
| [`budget_monitoring_snapshots`](#budget-monitoring-snapshots) | 13 | 0 | Finance & General Ledger |
| [`budget_revisions`](#budget-revisions) | 11 | 1 | Finance & General Ledger |
| [`budget_transfers`](#budget-transfers) | 16 | 1 | Finance & General Ledger |
| [`budget_versions`](#budget-versions) | 12 | 2 | Finance & General Ledger |
| [`cash_accounts`](#cash-accounts) | 9 | 1 | Finance & General Ledger |
| [`cash_flow_classifications`](#cash-flow-classifications) | 6 | 117 | Finance & General Ledger |
| [`cash_transactions`](#cash-transactions) | 10 | 0 | Finance & General Ledger |
| [`chart_of_accounts`](#chart-of-accounts) | 13 | 39 | Finance & General Ledger |
| [`clients`](#clients) | 98 | 7 | Sales & CRM |
| [`clients_pre_customer_master_20261012`](#clients-pre-customer-master-20261012) | 15 | 6 | Sales & CRM |
| [`client_contacts`](#client-contacts) | 8 | 0 | Sales & CRM |
| [`client_documents`](#client-documents) | 9 | 0 | Sales & CRM |
| [`client_notes`](#client-notes) | 5 | 0 | Sales & CRM |
| [`closing_adjustments`](#closing-adjustments) | 16 | 0 | Other |
| [`companies`](#companies) | 135 | 3 | Configuration & Lookups |
| [`companies_pre_company_master_20261012`](#companies-pre-company-master-20261012) | 14 | 3 | Configuration & Lookups |
| [`company_bank_accounts`](#company-bank-accounts) | 18 | 0 | Finance & General Ledger |
| [`company_expenses`](#company-expenses) | 27 | 1 | Finance & General Ledger |
| [`company_groups`](#company-groups) | 8 | 2 | Configuration & Lookups |
| [`company_relationships`](#company-relationships) | 10 | 3 | Configuration & Lookups |
| [`consolidation_journals`](#consolidation-journals) | 11 | 38 | Finance & General Ledger |
| [`consolidation_journal_lines`](#consolidation-journal-lines) | 6 | 76 | Finance & General Ledger |
| [`consolidation_runs`](#consolidation-runs) | 22 | 20 | Other |
| [`consolidation_run_companies`](#consolidation-run-companies) | 7 | 20 | Configuration & Lookups |
| [`contractors`](#contractors) | 39 | 0 | Contractor master (new) |
| [`contractor_contracts`](#contractor-contracts) | 47 | 0 | Contractor master (new) |
| [`contractor_documents`](#contractor-documents) | 12 | 0 | Contractor master (new) |
| [`contractor_rate_schedules`](#contractor-rate-schedules) | 11 | 0 | Contractor master (new) |
| [`cost_centers`](#cost-centers) | 7 | 7 | Other |
| [`credit_notes`](#credit-notes) | 21 | 1 | Finance & General Ledger |
| [`currencies`](#currencies) | 9 | 12 | Configuration & Lookups |
| [`currency_rates`](#currency-rates) | 7 | 12 | Configuration & Lookups |
| [`customer_addresses`](#customer-addresses) | 21 | 0 | Other |
| [`customer_invoices`](#customer-invoices) | 22 | 7 | Finance & General Ledger |
| [`customer_receipts`](#customer-receipts) | 15 | 0 | Finance & General Ledger |
| [`deals`](#deals) | 12 | 7 | Sales & CRM |
| [`debit_notes`](#debit-notes) | 21 | 0 | Finance & General Ledger |
| [`departments`](#departments) | 7 | 8 | Employee & HR |
| [`depreciation_methods`](#depreciation-methods) | 6 | 6 | Finance & General Ledger |
| [`designations`](#designations) | 7 | 10 | Employee & HR |
| [`document_sequences`](#document-sequences) | 11 | 47 | Configuration & Lookups |
| [`employees`](#employees) | 32 | 14 | Employee & HR |
| [`employee_documents`](#employee-documents) | 9 | 3 | Employee & HR |
| [`employee_expense_claims`](#employee-expense-claims) | 19 | 0 | Employee & HR |
| [`exchange_rates`](#exchange-rates) | 7 | 6 | Other |
| [`expenses`](#expenses) | 13 | 10 | Finance & General Ledger |
| [`expense_approval_history`](#expense-approval-history) | 8 | 2 | Finance & General Ledger |
| [`expense_attachments`](#expense-attachments) | 10 | 0 | Finance & General Ledger |
| [`expense_categories`](#expense-categories) | 8 | 22 | Finance & General Ledger |
| [`expense_categories_old`](#expense-categories-old) | 6 | 10 | Finance & General Ledger |
| [`expense_items`](#expense-items) | 10 | 0 | Finance & General Ledger |
| [`expense_reimbursements`](#expense-reimbursements) | 16 | 0 | Finance & General Ledger |
| [`finance_goods_receipt_items`](#finance-goods-receipt-items) | 9 | 0 | Finance & General Ledger |
| [`finance_group_audit`](#finance-group-audit) | 9 | 12 | System & Security |
| [`financial_years`](#financial-years) | 12 | 4 | Finance & General Ledger |
| [`fixed_asset_categories`](#fixed-asset-categories) | 11 | 9 | Finance & General Ledger |
| [`fixed_asset_financials`](#fixed-asset-financials) | 17 | 0 | Finance & General Ledger |
| [`fixed_asset_history`](#fixed-asset-history) | 6 | 0 | Finance & General Ledger |
| [`general_ledger`](#general-ledger) | 12 | 40 | Finance & General Ledger |
| [`goods_receipts`](#goods-receipts) | 36 | 2 | Finance & General Ledger |
| [`goods_receipt_batches`](#goods-receipt-batches) | 9 | 0 | Finance & General Ledger |
| [`goods_receipt_damages`](#goods-receipt-damages) | 10 | 0 | Finance & General Ledger |
| [`goods_receipt_inspections`](#goods-receipt-inspections) | 12 | 0 | Finance & General Ledger |
| [`goods_receipt_items`](#goods-receipt-items) | 31 | 0 | Finance & General Ledger |
| [`goods_receipt_notes`](#goods-receipt-notes) | 11 | 0 | Finance & General Ledger |
| [`goods_receipt_rejections`](#goods-receipt-rejections) | 10 | 0 | Finance & General Ledger |
| [`goods_receipt_serials`](#goods-receipt-serials) | 9 | 0 | Finance & General Ledger |
| [`holidays`](#holidays) | 7 | 5 | Employee & HR |
| [`intercompany_accounts`](#intercompany-accounts) | 6 | 4 | Finance & General Ledger |
| [`intercompany_transactions`](#intercompany-transactions) | 19 | 0 | Other |
| [`inventory_adjustments`](#inventory-adjustments) | 35 | 0 | Inventory & Assets |
| [`inventory_adjustment_attachments`](#inventory-adjustment-attachments) | 8 | 0 | Inventory & Assets |
| [`inventory_items`](#inventory-items) | 19 | 8 | Inventory & Assets |
| [`inventory_logs`](#inventory-logs) | 8 | 10 | Inventory & Assets |
| [`inventory_reconciliations`](#inventory-reconciliations) | 16 | 0 | Inventory & Assets |
| [`inventory_reconciliation_items`](#inventory-reconciliation-items) | 17 | 0 | Inventory & Assets |
| [`inventory_reservations`](#inventory-reservations) | 15 | 1 | Inventory & Assets |
| [`inventory_sequences`](#inventory-sequences) | 4 | 0 | Inventory & Assets |
| [`inventory_settings`](#inventory-settings) | 13 | 1 | Inventory & Assets |
| [`inventory_stock_balances`](#inventory-stock-balances) | 11 | 8 | Inventory & Assets |
| [`inventory_stock_ledger`](#inventory-stock-ledger) | 25 | 0 | Finance & General Ledger |
| [`inventory_stock_transfers`](#inventory-stock-transfers) | 30 | 0 | Inventory & Assets |
| [`invoices`](#invoices) | 16 | 8 | Finance & General Ledger |
| [`invoice_items`](#invoice-items) | 20 | 7 | Finance & General Ledger |
| [`invoice_status_history`](#invoice-status-history) | 7 | 13 | Finance & General Ledger |
| [`journal_entries`](#journal-entries) | 12 | 20 | Finance & General Ledger |
| [`journal_entry_details`](#journal-entry-details) | 6 | 41 | Finance & General Ledger |
| [`knowledge_base_articles`](#knowledge-base-articles) | 11 | 5 | Helpdesk |
| [`knowledge_base_categories`](#knowledge-base-categories) | 8 | 5 | Helpdesk |
| [`leads`](#leads) | 15 | 8 | Sales & CRM |
| [`lead_followups`](#lead-followups) | 9 | 0 | Other |
| [`leave_balances`](#leave-balances) | 8 | 60 | Employee & HR |
| [`leave_requests`](#leave-requests) | 15 | 7 | Employee & HR |
| [`leave_types`](#leave-types) | 9 | 6 | Employee & HR |
| [`license_assignments`](#license-assignments) | 5 | 0 | Other |
| [`locations`](#locations) | 10 | 1 | Other |
| [`lookup_types`](#lookup-types) | 6 | 32 | Configuration & Lookups |
| [`lookup_values`](#lookup-values) | 9 | 198 | Configuration & Lookups |
| [`milestones`](#milestones) | 8 | 10 | Projects & Tasks |
| [`money_transfers`](#money-transfers) | 12 | 0 | Other |
| [`opening_balance_history`](#opening-balance-history) | 8 | 6 | Other |
| [`payments`](#payments) | 9 | 6 | Finance & General Ledger |
| [`payment_allocations`](#payment-allocations) | 5 | 0 | Finance & General Ledger |
| [`payroll_finance_mappings`](#payroll-finance-mappings) | 7 | 5 | Employee & HR |
| [`payroll_finance_postings`](#payroll-finance-postings) | 10 | 1 | Employee & HR |
| [`payroll_payments`](#payroll-payments) | 8 | 1 | Employee & HR |
| [`payroll_payment_batches`](#payroll-payment-batches) | 9 | 1 | Employee & HR |
| [`payroll_reconciliation`](#payroll-reconciliation) | 9 | 1 | Employee & HR |
| [`payroll_runs`](#payroll-runs) | 20 | 3 | Employee & HR |
| [`people`](#people) | 18 | 2 | Employee & HR |
| [`period_checklist_items`](#period-checklist-items) | 12 | 28 | Finance & General Ledger |
| [`period_checklist_templates`](#period-checklist-templates) | 7 | 70 | Finance & General Ledger |
| [`period_close_audit`](#period-close-audit) | 10 | 1,503 | Finance & General Ledger |
| [`period_close_settings`](#period-close-settings) | 9 | 1 | Finance & General Ledger |
| [`period_opening_balances`](#period-opening-balances) | 8 | 0 | Finance & General Ledger |
| [`permissions`](#permissions) | 5 | 517 | System & Security |
| [`person_roles`](#person-roles) | 8 | 2 | System & Security |
| [`petty_cash_funds`](#petty-cash-funds) | 9 | 0 | Finance & General Ledger |
| [`petty_cash_transactions`](#petty-cash-transactions) | 10 | 0 | Finance & General Ledger |
| [`po_receiving_summary`](#po-receiving-summary) | 11 | 0 | Other |
| [`po_status_history`](#po-status-history) | 7 | 0 | Other |
| [`procurement_approval_rules`](#procurement-approval-rules) | 9 | 2 | Procurement & Vendors |
| [`procurement_audit`](#procurement-audit) | 8 | 804 | Procurement & Vendors |
| [`procurement_sequences`](#procurement-sequences) | 4 | 5 | Procurement & Vendors |
| [`procurement_settings`](#procurement-settings) | 3 | 14 | Procurement & Vendors |
| [`projects`](#projects) | 13 | 10 | Projects & Tasks |
| [`project_members`](#project-members) | 5 | 17 | Projects & Tasks |
| [`purchase_orders`](#purchase-orders) | 52 | 29 | Procurement & Vendors |
| [`purchase_order_approvals`](#purchase-order-approvals) | 8 | 27 | Procurement & Vendors |
| [`purchase_order_attachments`](#purchase-order-attachments) | 8 | 0 | Procurement & Vendors |
| [`purchase_order_charges`](#purchase-order-charges) | 4 | 0 | Procurement & Vendors |
| [`purchase_order_communications`](#purchase-order-communications) | 9 | 4 | Procurement & Vendors |
| [`purchase_order_items`](#purchase-order-items) | 27 | 52 | Procurement & Vendors |
| [`purchase_order_revisions`](#purchase-order-revisions) | 8 | 4 | Procurement & Vendors |
| [`purchase_order_revision_items`](#purchase-order-revision-items) | 9 | 8 | Procurement & Vendors |
| [`purchase_requisitions`](#purchase-requisitions) | 29 | 3 | Procurement & Vendors |
| [`purchase_requisition_approvals`](#purchase-requisition-approvals) | 8 | 5 | Procurement & Vendors |
| [`purchase_requisition_attachments`](#purchase-requisition-attachments) | 8 | 0 | Procurement & Vendors |
| [`purchase_requisition_items`](#purchase-requisition-items) | 20 | 3 | Procurement & Vendors |
| [`quotation_attachments`](#quotation-attachments) | 8 | 0 | Procurement & Vendors |
| [`quotation_evaluations`](#quotation-evaluations) | 9 | 354 | Procurement & Vendors |
| [`quotation_other_charges`](#quotation-other-charges) | 4 | 0 | Procurement & Vendors |
| [`quotation_selection`](#quotation-selection) | 11 | 74 | Procurement & Vendors |
| [`receipt_allocations`](#receipt-allocations) | 5 | 0 | Finance & General Ledger |
| [`receipt_attachments`](#receipt-attachments) | 9 | 0 | Finance & General Ledger |
| [`report_audit_log`](#report-audit-log) | 9 | 19 | System & Security |
| [`report_exports`](#report-exports) | 6 | 9 | Other |
| [`report_templates`](#report-templates) | 8 | 5 | Configuration & Lookups |
| [`request_for_quotations`](#request-for-quotations) | 24 | 37 | Procurement & Vendors |
| [`rfq_evaluation_criteria`](#rfq-evaluation-criteria) | 5 | 138 | Procurement & Vendors |
| [`rfq_items`](#rfq-items) | 14 | 151 | Procurement & Vendors |
| [`rfq_vendors`](#rfq-vendors) | 8 | 98 | Procurement & Vendors |
| [`rfq_vendor_communications`](#rfq-vendor-communications) | 8 | 98 | Procurement & Vendors |
| [`roles`](#roles) | 5 | 3 | System & Security |
| [`role_permissions`](#role-permissions) | 2 | 750 | System & Security |
| [`saved_reports`](#saved-reports) | 6 | 1 | Other |
| [`scheduled_reports`](#scheduled-reports) | 12 | 0 | Other |
| [`service_receipts`](#service-receipts) | 31 | 0 | Finance & General Ledger |
| [`service_receipt_items`](#service-receipt-items) | 16 | 0 | Finance & General Ledger |
| [`settings`](#settings) | 5 | 27 | Configuration & Lookups |
| [`sidebar_menu`](#sidebar-menu) | 13 | 172 | System & Security |
| [`slas`](#slas) | 9 | 4 | Helpdesk |
| [`software_licenses`](#software-licenses) | 12 | 5 | Other |
| [`tasks`](#tasks) | 14 | 13 | Projects & Tasks |
| [`task_activities`](#task-activities) | 8 | 11 | Projects & Tasks |
| [`task_assignees`](#task-assignees) | 4 | 12 | Projects & Tasks |
| [`task_attachments`](#task-attachments) | 8 | 0 | Projects & Tasks |
| [`task_comments`](#task-comments) | 5 | 11 | Projects & Tasks |
| [`task_subtasks`](#task-subtasks) | 7 | 16 | Projects & Tasks |
| [`task_tags`](#task-tags) | 5 | 10 | Projects & Tasks |
| [`tax_adjustments`](#tax-adjustments) | 18 | 0 | Finance & General Ledger |
| [`tax_exemptions`](#tax-exemptions) | 8 | 4 | Finance & General Ledger |
| [`tax_groups`](#tax-groups) | 6 | 3 | Finance & General Ledger |
| [`tax_group_lines`](#tax-group-lines) | 5 | 3 | Finance & General Ledger |
| [`tax_periods`](#tax-periods) | 9 | 13 | Finance & General Ledger |
| [`tax_rates`](#tax-rates) | 10 | 8 | Finance & General Ledger |
| [`tax_registrations`](#tax-registrations) | 11 | 1 | Finance & General Ledger |
| [`tax_settings`](#tax-settings) | 7 | 2 | Finance & General Ledger |
| [`tax_transactions`](#tax-transactions) | 22 | 8 | Finance & General Ledger |
| [`tax_types`](#tax-types) | 7 | 8 | Finance & General Ledger |
| [`templates`](#templates) | 14 | 1 | Configuration & Lookups |
| [`template_assets`](#template-assets) | 9 | 0 | Finance & General Ledger |
| [`template_categories`](#template-categories) | 6 | 6 | Configuration & Lookups |
| [`template_history`](#template-history) | 7 | 1 | Configuration & Lookups |
| [`template_module_mappings`](#template-module-mappings) | 6 | 0 | Configuration & Lookups |
| [`template_preview_cache`](#template-preview-cache) | 6 | 0 | Configuration & Lookups |
| [`template_versions`](#template-versions) | 9 | 1 | Configuration & Lookups |
| [`test_order`](#test-order) | 4 | 1 | Other |
| [`test_order2`](#test-order2) | 4 | 1 | Other |
| [`tickets`](#tickets) | 19 | 5 | Helpdesk |
| [`ticket_assignments`](#ticket-assignments) | 7 | 0 | Helpdesk |
| [`ticket_attachments`](#ticket-attachments) | 9 | 0 | Helpdesk |
| [`ticket_categories`](#ticket-categories) | 8 | 8 | Helpdesk |
| [`ticket_internal_notes`](#ticket-internal-notes) | 7 | 0 | Helpdesk |
| [`ticket_replies`](#ticket-replies) | 7 | 8 | Helpdesk |
| [`timesheets`](#timesheets) | 9 | 59 | Employee & HR |
| [`units_of_measure`](#units-of-measure) | 6 | 12 | Other |
| [`users`](#users) | 11 | 14 | System & Security |
| [`user_companies`](#user-companies) | 2 | 15 | Configuration & Lookups |
| [`user_dashboard_layouts`](#user-dashboard-layouts) | 7 | 0 | Other |
| [`user_roles`](#user-roles) | 2 | 14 | System & Security |
| [`vendors`](#vendors) | 17 | 26 | Procurement & Vendors |
| [`vendor_bills`](#vendor-bills) | 37 | 2 | Procurement & Vendors |
| [`vendor_bill_approvals`](#vendor-bill-approvals) | 9 | 1 | Procurement & Vendors |
| [`vendor_bill_attachments`](#vendor-bill-attachments) | 8 | 0 | Procurement & Vendors |
| [`vendor_bill_exceptions`](#vendor-bill-exceptions) | 20 | 0 | Procurement & Vendors |
| [`vendor_bill_items`](#vendor-bill-items) | 29 | 3 | Procurement & Vendors |
| [`vendor_bill_matches`](#vendor-bill-matches) | 16 | 0 | Procurement & Vendors |
| [`vendor_payments`](#vendor-payments) | 15 | 0 | Finance & General Ledger |
| [`vendor_quotations`](#vendor-quotations) | 24 | 85 | Procurement & Vendors |
| [`vendor_quotation_items`](#vendor-quotation-items) | 17 | 339 | Procurement & Vendors |
| [`voucher_types`](#voucher-types) | 6 | 31 | Other |
| [`warehouses`](#warehouses) | 9 | 1 | Inventory & Assets |
| [`widget_definitions`](#widget-definitions) | 19 | 60 | Configuration & Lookups |
| [`year_end_closings`](#year-end-closings) | 21 | 0 | Other |

---

## Contractor master (new)


### contractors

39 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `contractor_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `first_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `last_name` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `gender` | `enum('male','female','other')` | yes | — | `NULL` | — |
| 6 | `date_of_birth` | `date` | yes | — | `NULL` | — |
| 7 | `nationality` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `email` | `varchar(255)` | yes | UQ | `NULL` | — |
| 9 | `phone` | `varchar(20)` | yes | — | `NULL` | — |
| 10 | `address` | `varchar(500)` | yes | — | `NULL` | — |
| 11 | `work_location` | `varchar(100)` | yes | — | `NULL` | — |
| 12 | `photo_path` | `varchar(500)` | yes | — | `NULL` | — |
| 13 | `contractor_category` | `varchar(60)` | yes | IDX | `NULL` | — |
| 14 | `specialisation` | `varchar(200)` | yes | — | `NULL` | — |
| 15 | `emirates_id_number` | `varchar(20)` | yes | IDX | `NULL` | — |
| 16 | `emirates_id_expiry` | `date` | yes | — | `NULL` | — |
| 17 | `passport_number` | `varchar(50)` | yes | IDX | `NULL` | — |
| 18 | `passport_expiry` | `date` | yes | — | `NULL` | — |
| 19 | `visa_number` | `varchar(50)` | yes | — | `NULL` | — |
| 20 | `visa_type` | `varchar(30)` | yes | — | `NULL` | — |
| 21 | `visa_expiry` | `date` | yes | — | `NULL` | — |
| 22 | `work_permit_number` | `varchar(50)` | yes | — | `NULL` | — |
| 23 | `work_permit_expiry` | `date` | yes | — | `NULL` | — |
| 24 | `labor_card_number` | `varchar(50)` | yes | — | `NULL` | — |
| 25 | `labor_card_expiry` | `date` | yes | — | `NULL` | — |
| 26 | `trn_number` | `varchar(40)` | yes | — | `NULL` | — |
| 27 | `bank_name` | `varchar(150)` | yes | — | `NULL` | — |
| 28 | `bank_account_number` | `varchar(60)` | yes | — | `NULL` | — |
| 29 | `account_holder_name` | `varchar(150)` | yes | — | `NULL` | — |
| 30 | `status` | `enum('active','suspended','terminated')` | **no** | IDX | `'active'` | — |
| 31 | `user_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 32 | `termination_date` | `date` | yes | — | `NULL` | — |
| 33 | `termination_reason` | `varchar(255)` | yes | — | `NULL` | — |
| 34 | `notes` | `text` | yes | — | `NULL` | — |
| 35 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 36 | `updated_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 37 | `deleted_at` | `timestamp` | yes | IDX | `NULL` | — |
| 38 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 39 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### contractor_contracts

47 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `contractor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | contractors.id (on delete cascade) |
| 3 | `contract_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 4 | `company_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | companies.id (on delete restrict) |
| 5 | `title` | `varchar(200)` | **no** | — | *NULL* | — |
| 6 | `contract_type` | `varchar(60)` | yes | — | `NULL` | — |
| 7 | `scope_of_work` | `text` | yes | — | `NULL` | — |
| 8 | `status` | `enum('draft','active','completed','terminated')` | **no** | IDX | `'draft'` | — |
| 9 | `is_current` | `tinyint(1) unsigned` | yes | — | `NULL` | — |
| 10 | `supply_source` | `enum('direct','agency')` | **no** | — | `'direct'` | — |
| 11 | `agency_vendor_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendors.id (on delete set null) |
| 12 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 13 | `designation_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | designations.id (on delete set null) |
| 14 | `contractual_job_title` | `varchar(150)` | yes | — | `NULL` | — |
| 15 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 16 | `manager_employee_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 17 | `purchase_order_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | purchase_orders.id (on delete set null) |
| 18 | `po_reference` | `varchar(60)` | yes | — | `NULL` | — |
| 19 | `start_date` | `date` | **no** | — | *NULL* | — |
| 20 | `end_date` | `date` | yes | — | `NULL` | — |
| 21 | `work_arrangement` | `varchar(40)` | yes | — | `NULL` | — |
| 22 | `fte_allocation` | `decimal(5,2)` | yes | — | `NULL` | — |
| 23 | `rate_basis` | `varchar(40)` | yes | — | `NULL` | — |
| 24 | `rate_amount` | `decimal(15,2)` | yes | — | `NULL` | — |
| 25 | `currency_code` | `char(3)` | yes | IDX FK | `NULL` | currencies.code (on delete set null) |
| 26 | `estimated_value` | `decimal(15,2)` | yes | — | `NULL` | — |
| 27 | `not_to_exceed_amount` | `decimal(15,2)` | yes | — | `NULL` | — |
| 28 | `billing_frequency` | `varchar(40)` | yes | — | `NULL` | — |
| 29 | `payment_terms` | `varchar(60)` | yes | — | `NULL` | — |
| 30 | `timesheet_required` | `tinyint(1)` | **no** | — | `0` | — |
| 31 | `security_clearance` | `varchar(60)` | yes | — | `NULL` | — |
| 32 | `badge_number` | `varchar(60)` | yes | — | `NULL` | — |
| 33 | `extension_count` | `smallint(5) unsigned` | **no** | — | `0` | — |
| 34 | `extension_limit` | `smallint(5) unsigned` | yes | — | `NULL` | — |
| 35 | `extension_period_days` | `smallint(5) unsigned` | yes | — | `NULL` | — |
| 36 | `last_extended_on` | `date` | yes | — | `NULL` | — |
| 37 | `last_extension_reason` | `varchar(255)` | yes | — | `NULL` | — |
| 38 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 39 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 40 | `ended_on` | `date` | yes | — | `NULL` | — |
| 41 | `end_reason` | `varchar(255)` | yes | — | `NULL` | — |
| 42 | `notes` | `text` | yes | — | `NULL` | — |
| 43 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 44 | `updated_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 45 | `deleted_at` | `timestamp` | yes | — | `NULL` | — |
| 46 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 47 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### contractor_documents

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `contractor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | contractors.id (on delete cascade) |
| 3 | `contract_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | contractor_contracts.id (on delete set null) |
| 4 | `document_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `document_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 7 | `file_size` | `int(11)` | yes | — | `NULL` | — |
| 8 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `expires_on` | `date` | yes | IDX | `NULL` | — |
| 10 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 11 | `uploaded_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### contractor_rate_schedules

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `contract_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | contractor_contracts.id (on delete cascade) |
| 3 | `valid_from` | `date` | **no** | — | *NULL* | — |
| 4 | `valid_to` | `date` | yes | — | `NULL` | — |
| 5 | `rate_basis` | `varchar(40)` | yes | — | `NULL` | — |
| 6 | `rate_amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 7 | `currency_code` | `char(3)` | yes | IDX FK | `NULL` | currencies.code (on delete set null) |
| 8 | `reason` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## Employee & HR


### ai_skills

28 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `skill_id` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `module` | `varchar(50)` | yes | IDX | `NULL` | — |
| 6 | `category` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `skill_type` | `enum('guidance','navigation','workflow','analysis','knowledge','composite')` | **no** | IDX | `'guidance'` | — |
| 8 | `status` | `enum('draft','testing','approved','published','deprecated','archived')` | **no** | IDX | `'draft'` | — |
| 9 | `version` | `int(11)` | **no** | — | `1` | — |
| 10 | `required_permissions_json` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `required_capabilities_json` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `input_schema_json` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `steps_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 14 | `output_schema_json` | `longtext` | yes | — | `NULL` | binary collation |
| 15 | `safety_classification` | `enum('read_only','confirmation_required','blocked')` | yes | — | `'read_only'` | — |
| 16 | `mode` | `enum('guide','assist')` | yes | — | `'guide'` | — |
| 17 | `max_steps` | `int(11)` | yes | — | `20` | — |
| 18 | `max_runtime_seconds` | `int(11)` | yes | — | `300` | — |
| 19 | `max_tool_calls` | `int(11)` | yes | — | `10` | — |
| 20 | `parent_skill_id` | `varchar(100)` | yes | — | `NULL` | — |
| 21 | `nesting_depth` | `int(11)` | yes | — | `0` | — |
| 22 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 23 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 24 | `published_at` | `datetime` | yes | — | `NULL` | — |
| 25 | `deprecated_at` | `datetime` | yes | — | `NULL` | — |
| 26 | `is_active` | `tinyint(1)` | yes | IDX | `1` | — |
| 27 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 28 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_skill_analytics

10 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `skill_id` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `date` | `date` | **no** | — | *NULL* | — |
| 4 | `total_starts` | `int(11)` | yes | — | `0` | — |
| 5 | `total_completed` | `int(11)` | yes | — | `0` | — |
| 6 | `total_cancelled` | `int(11)` | yes | — | `0` | — |
| 7 | `total_failed` | `int(11)` | yes | — | `0` | — |
| 8 | `avg_duration_seconds` | `float` | yes | — | `NULL` | — |
| 9 | `total_feedback` | `int(11)` | yes | — | `0` | — |
| 10 | `positive_feedback` | `int(11)` | yes | — | `0` | — |

### ai_skill_feedback

7 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `skill_id` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `rating` | `enum('helpful','not_helpful','too_long','incorrect')` | **no** | — | *NULL* | — |
| 6 | `comment` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_skill_runs

16 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `skill_id` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `skill_version` | `int(11)` | **no** | — | `1` | — |
| 5 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `current_step` | `int(11)` | yes | — | `0` | — |
| 9 | `total_steps` | `int(11)` | yes | — | `0` | — |
| 10 | `status` | `enum('started','in_progress','waiting_for_user','waiting_for_confirmation','completed','failed','cancelled','expired')` | **no** | IDX | `'started'` | — |
| 11 | `context_json` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `result_summary` | `text` | yes | — | `NULL` | — |
| 13 | `error_message` | `text` | yes | — | `NULL` | — |
| 14 | `started_at` | `datetime` | yes | IDX | current_timestamp() | — |
| 15 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_skill_step_results

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `step_index` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `step_type` | `varchar(50)` | yes | — | `NULL` | — |
| 5 | `status` | `enum('pending','in_progress','completed','failed','skipped')` | yes | — | `'pending'` | — |
| 6 | `input_json` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `output_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `tool_used` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 10 | `error_message` | `text` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_skill_suggestions

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 3 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `suggested_skill_id` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `reason` | `text` | yes | — | `NULL` | — |
| 6 | `confidence` | `float` | yes | — | `0.5` | — |
| 7 | `is_accepted` | `tinyint(1)` | yes | — | `0` | — |
| 8 | `is_dismissed` | `tinyint(1)` | yes | — | `0` | — |
| 9 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_skill_templates

8 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `template_type` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `steps_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 7 | `is_active` | `tinyint(1)` | yes | — | `1` | — |
| 8 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_sync_job_rows

11 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `sync_job_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `external_record_id` | `varchar(150)` | yes | — | `NULL` | — |
| 4 | `preview_action` | `enum('create','update','skip','conflict','error')` | **no** | IDX | `'skip'` | — |
| 5 | `conflict_status` | `enum('external_newer','erp_newer','conflict','identical')` | **no** | — | `'identical'` | — |
| 6 | `resolution_status` | `enum('pending','approved','skipped','failed')` | **no** | — | `'pending'` | — |
| 7 | `source_data_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `mapped_data_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `validation_errors_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### attendance

10 columns · 218 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 3 | `attendance_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `check_in` | `time` | yes | — | `NULL` | — |
| 5 | `check_out` | `time` | yes | — | `NULL` | — |
| 6 | `status` | `enum('present','absent','half_day','leave','holiday','weekend')` | yes | IDX | `'present'` | — |
| 7 | `late_minutes` | `int(11)` | yes | — | `0` | — |
| 8 | `remarks` | `text` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### departments

7 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `department_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `department_code` | `varchar(20)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### designations

7 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `designation_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `department_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | departments.id (on delete cascade) |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### employees

32 columns · 14 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `employee_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `first_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `last_name` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `gender` | `enum('male','female','other')` | **no** | — | *NULL* | — |
| 6 | `date_of_birth` | `date` | yes | — | `NULL` | — |
| 7 | `marital_status` | `enum('single','married','divorced','widowed')` | yes | — | `'single'` | — |
| 8 | `nationality` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `passport_number` | `varchar(50)` | yes | IDX | `NULL` | — |
| 10 | `passport_expiry` | `date` | yes | — | `NULL` | — |
| 11 | `visa_type` | `varchar(30)` | yes | — | `NULL` | — |
| 12 | `visa_number` | `varchar(50)` | yes | — | `NULL` | — |
| 13 | `visa_expiry` | `date` | yes | — | `NULL` | — |
| 14 | `emirates_id_number` | `varchar(20)` | yes | IDX | `NULL` | — |
| 15 | `emirates_id_expiry` | `date` | yes | — | `NULL` | — |
| 16 | `phone` | `varchar(20)` | yes | — | `NULL` | — |
| 17 | `email` | `varchar(255)` | **no** | UQ | *NULL* | — |
| 18 | `address` | `text` | yes | — | `NULL` | — |
| 19 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 20 | `designation_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | designations.id (on delete set null) |
| 21 | `joining_date` | `date` | yes | — | `NULL` | — |
| 22 | `contract_number` | `varchar(50)` | yes | IDX | `NULL` | — |
| 23 | `contract_start_date` | `date` | yes | — | `NULL` | — |
| 24 | `contract_end_date` | `date` | yes | IDX | `NULL` | — |
| 25 | `employment_type` | `enum('full_time','part_time','contract','intern')` | yes | — | `'full_time'` | — |
| 26 | `reporting_manager_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 27 | `work_location` | `varchar(255)` | yes | — | `NULL` | — |
| 28 | `user_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 29 | `photo_path` | `varchar(500)` | yes | — | `NULL` | — |
| 30 | `status` | `enum('active','inactive','terminated','resigned')` | yes | IDX | `'active'` | — |
| 31 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 32 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### employee_documents

9 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 3 | `document_type` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `document_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(11)` | yes | — | `NULL` | — |
| 7 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `uploaded_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### employee_expense_claims

19 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `claim_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete restrict) |
| 4 | `expense_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `category_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | expense_categories.id (on delete restrict) |
| 6 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 7 | `description` | `text` | yes | — | `NULL` | — |
| 8 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 10 | `total_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 11 | `status` | `enum('draft','pending_manager','pending_finance','approved','rejected','reimbursed','cancelled')` | yes | IDX | `'draft'` | — |
| 12 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 13 | `manager_id` | `int(10) unsigned` | yes | — | `NULL` | Manager who approves level 1 |
| 14 | `manager_approved_at` | `timestamp` | yes | — | `NULL` | — |
| 15 | `finance_approved_at` | `timestamp` | yes | — | `NULL` | — |
| 16 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 17 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 18 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### holidays

7 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `holiday_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 3 | `holiday_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `holiday_type` | `enum('national','festival','optional')` | yes | — | `'national'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### leave_balances

8 columns · 60 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 3 | `leave_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | leave_types.id (on delete cascade) |
| 4 | `year` | `year(4)` | **no** | — | *NULL* | — |
| 5 | `total_days` | `decimal(5,1)` | yes | — | `0.0` | — |
| 6 | `used_days` | `decimal(5,1)` | yes | — | `0.0` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### leave_requests

15 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 3 | `leave_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | leave_types.id (on delete cascade) |
| 4 | `from_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `to_date` | `date` | **no** | — | *NULL* | — |
| 6 | `total_days` | `decimal(5,1)` | **no** | — | *NULL* | — |
| 7 | `is_lop` | `tinyint(1)` | yes | — | `0` | — |
| 8 | `half_day` | `tinyint(1)` | yes | — | `0` | — |
| 9 | `reason` | `text` | yes | — | `NULL` | — |
| 10 | `status` | `enum('pending','approved','rejected','cancelled')` | yes | IDX | `'pending'` | — |
| 11 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 12 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 13 | `approval_remarks` | `text` | yes | — | `NULL` | — |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### leave_types

9 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `leave_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `leave_code` | `varchar(20)` | **no** | UQ | *NULL* | — |
| 4 | `total_days` | `int(11)` | **no** | — | `0` | — |
| 5 | `carry_forward` | `tinyint(1)` | yes | — | `0` | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### payroll_finance_mappings

7 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payroll_component` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `chart_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 4 | `mapping_type` | `enum('expense','liability','asset')` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('Active','Inactive')` | yes | IDX | `'Active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### payroll_finance_postings

10 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payroll_run_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 4 | `posting_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `total_gross` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `total_deductions` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `total_net` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `status` | `enum('Draft','Posted','Reversed')` | yes | IDX | `'Draft'` | — |
| 9 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### payroll_payments

8 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payroll_run_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 3 | `amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 4 | `payment_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `reference_number` | `varchar(50)` | **no** | — | *NULL* | — |
| 6 | `journal_entry_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 7 | `created_by` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### payroll_payment_batches

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payroll_run_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `bank_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | bank_accounts.id (on delete restrict) |
| 4 | `payment_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `total_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `reference_number` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `status` | `enum('Pending','Approved','Paid','Failed')` | yes | IDX | `'Pending'` | — |
| 8 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### payroll_reconciliation

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payroll_run_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `payroll_total` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 4 | `posted_total` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 5 | `paid_total` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `difference` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `status` | `enum('Reconciled','Unreconciled','Investigation')` | yes | IDX | `'Unreconciled'` | — |
| 8 | `checked_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 9 | `checked_at` | `timestamp` | yes | — | `NULL` | — |

### payroll_runs

20 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `run_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `period_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `payroll_period_start` | `date` | **no** | IDX | *NULL* | — |
| 5 | `payroll_period_end` | `date` | **no** | — | *NULL* | — |
| 6 | `pay_date` | `date` | **no** | IDX | *NULL* | — |
| 7 | `basic_salary` | `decimal(15,2)` | yes | — | `0.00` | — |
| 8 | `allowances` | `decimal(15,2)` | yes | — | `0.00` | — |
| 9 | `overtime` | `decimal(15,2)` | yes | — | `0.00` | — |
| 10 | `employer_contributions` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `employee_deductions` | `decimal(15,2)` | yes | — | `0.00` | — |
| 12 | `gross_salary` | `decimal(15,2)` | yes | — | `0.00` | — |
| 13 | `net_salary` | `decimal(15,2)` | yes | — | `0.00` | — |
| 14 | `status` | `enum('Draft','Approved','Posted','Paid')` | yes | IDX | `'Draft'` | — |
| 15 | `description` | `text` | yes | — | `NULL` | — |
| 16 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 17 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 19 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### people

18 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `person_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `first_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `last_name` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `email` | `varchar(255)` | yes | UQ | `NULL` | — |
| 6 | `phone` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `address` | `text` | yes | — | `NULL` | — |
| 8 | `gender` | `enum('male','female','other')` | yes | — | `NULL` | — |
| 9 | `date_of_birth` | `date` | yes | — | `NULL` | — |
| 10 | `category` | `enum('employee','customer','contractor')` | **no** | IDX | *NULL* | — |
| 11 | `user_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 12 | `employee_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 13 | `customer_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | clients.id (on delete set null) |
| 14 | `contractor_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | contractors.id (on delete set null) |
| 15 | `notes` | `text` | yes | — | `NULL` | — |
| 16 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 17 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 18 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### timesheets

9 columns · 59 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 3 | `project_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | projects.id (on delete cascade) |
| 4 | `task_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | tasks.id (on delete set null) |
| 5 | `work_date` | `date` | **no** | IDX | *NULL* | — |
| 6 | `hours_logged` | `decimal(5,2)` | **no** | — | *NULL* | — |
| 7 | `work_description` | `text` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## Finance & General Ledger


### accounting_periods

11 columns · 42 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_year_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `period_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `start_date` | `date` | **no** | — | *NULL* | — |
| 5 | `end_date` | `date` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('open','closing','closed')` | **no** | — | `'open'` | — |
| 7 | `closed_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `closed_at` | `datetime` | yes | — | `NULL` | — |
| 9 | `reopen_count` | `int(11)` | **no** | — | `0` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### account_groups

7 columns · 48 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `group_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `parent_group_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | account_groups.id (on delete set null) |
| 4 | `nature` | `enum('asset','liability','equity','income','expense')` | **no** | IDX | *NULL* | — |
| 5 | `display_order` | `int(11)` | yes | — | `0` | — |
| 6 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### account_types

5 columns · 27 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `type_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `group_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | account_groups.id (on delete restrict) |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### assets

16 columns · 9 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `asset_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `category_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | asset_categories.id (on delete restrict) |
| 5 | `serial_number` | `varchar(100)` | yes | UQ | `NULL` | — |
| 6 | `brand` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `purchase_date` | `date` | yes | — | `NULL` | — |
| 9 | `purchase_cost` | `decimal(15,2)` | yes | — | `NULL` | — |
| 10 | `vendor_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendors.id (on delete set null) |
| 11 | `warranty_expiry` | `date` | yes | — | `NULL` | — |
| 12 | `asset_condition` | `enum('new','good','fair','damaged')` | yes | IDX | `'new'` | — |
| 13 | `status` | `enum('available','assigned','maintenance','damaged','lost','retired')` | yes | IDX | `'available'` | — |
| 14 | `notes` | `text` | yes | — | `NULL` | — |
| 15 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_assignments

11 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 4 | `assigned_date` | `date` | **no** | — | *NULL* | — |
| 5 | `expected_return_date` | `date` | yes | — | `NULL` | — |
| 6 | `actual_return_date` | `date` | yes | — | `NULL` | — |
| 7 | `assigned_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 8 | `assignment_notes` | `text` | yes | — | `NULL` | — |
| 9 | `status` | `enum('active','returned','overdue')` | yes | IDX | `'active'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_categories

6 columns · 9 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `category_name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `category_code` | `varchar(20)` | yes | — | `NULL` | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### asset_depreciation_schedules

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | fixed_asset_financials.id (on delete cascade) |
| 3 | `period_date` | `date` | **no** | — | *NULL* | — |
| 4 | `opening_book_value` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 5 | `depreciation_amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 6 | `accumulated_depreciation` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 7 | `closing_book_value` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 8 | `status` | `enum('scheduled','posted')` | yes | IDX | `'scheduled'` | — |
| 9 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 10 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_disposal

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | UQ FK | *NULL* | assets.id (on delete cascade) |
| 3 | `disposal_date` | `date` | **no** | — | *NULL* | — |
| 4 | `disposal_type` | `enum('sale','scrap','write-off','donation')` | **no** | — | *NULL* | — |
| 5 | `disposal_proceeds` | `decimal(15,2)` | yes | — | `0.00` | — |
| 6 | `original_cost` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 7 | `accumulated_depreciation` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 8 | `net_book_value` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 9 | `gain_loss` | `decimal(15,2)` | yes | — | `NULL` | STORED GENERATED |
| 10 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_disposals

13 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `disposal_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `disposal_method` | `enum('sale','scrapped','donated','other')` | **no** | — | *NULL* | — |
| 5 | `disposal_value` | `decimal(19,4)` | yes | — | `0.0000` | — |
| 6 | `original_cost` | `decimal(19,4)` | **no** | — | *NULL* | — |
| 7 | `accumulated_depreciation` | `decimal(19,4)` | yes | — | `0.0000` | — |
| 8 | `net_book_value` | `decimal(19,4)` | **no** | — | *NULL* | — |
| 9 | `gain_loss` | `decimal(19,4)` | yes | — | `0.0000` | — |
| 10 | `reason` | `text` | yes | — | `NULL` | — |
| 11 | `journal_entry_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_impairment

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `impairment_date` | `date` | **no** | — | *NULL* | — |
| 4 | `carrying_amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 5 | `recoverable_amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 6 | `impairment_loss` | `decimal(15,2)` | yes | — | `NULL` | STORED GENERATED |
| 7 | `reason` | `text` | yes | — | `NULL` | — |
| 8 | `approval_status` | `enum('pending','approved','rejected')` | yes | — | `'pending'` | — |
| 9 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_impairments

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `impairment_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `book_value_before_impairment` | `decimal(19,4)` | **no** | — | *NULL* | — |
| 5 | `recoverable_amount` | `decimal(19,4)` | **no** | — | *NULL* | — |
| 6 | `impairment_loss` | `decimal(19,4)` | **no** | — | *NULL* | — |
| 7 | `reason` | `text` | yes | — | `NULL` | — |
| 8 | `status` | `enum('pending','approved','rejected')` | yes | — | `'pending'` | — |
| 9 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `journal_entry_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_maintenance

11 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `maintenance_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `maintenance_type` | `enum('repair','upgrade','cleaning','replacement','inspection')` | **no** | IDX | *NULL* | — |
| 5 | `vendor_name` | `varchar(200)` | yes | — | `NULL` | — |
| 6 | `cost` | `decimal(15,2)` | yes | — | `NULL` | — |
| 7 | `description` | `text` | yes | — | `NULL` | — |
| 8 | `next_maintenance_date` | `date` | yes | — | `NULL` | — |
| 9 | `status` | `enum('scheduled','in_progress','completed','cancelled')` | yes | — | `'completed'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_returns

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `assignment_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | asset_assignments.id (on delete cascade) |
| 3 | `return_date` | `date` | **no** | — | *NULL* | — |
| 4 | `return_condition` | `enum('good','damaged','needs_repair')` | yes | — | `'good'` | — |
| 5 | `damage_notes` | `text` | yes | — | `NULL` | — |
| 6 | `accepted_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### asset_transfers

14 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `from_department_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `to_department_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `from_cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `to_cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `from_location` | `varchar(255)` | yes | — | `NULL` | — |
| 8 | `to_location` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `transfer_date` | `date` | **no** | IDX | *NULL* | — |
| 10 | `transfer_reason` | `text` | yes | — | `NULL` | — |
| 11 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 12 | `status` | `enum('pending','approved','rejected','completed')` | yes | — | `'pending'` | — |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 14 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### asset_transfer_history

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | assets.id (on delete cascade) |
| 3 | `from_department_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `to_department_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `from_cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `to_cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `from_location_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `to_location_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `from_project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `to_project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 11 | `transfer_date` | `date` | **no** | — | *NULL* | — |
| 12 | `transfer_reason` | `text` | yes | — | `NULL` | — |
| 13 | `approved_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 14 | `status` | `enum('pending','approved','rejected','completed')` | yes | — | `'pending'` | — |
| 15 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### bank_accounts

13 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `account_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 3 | `bank_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `account_number` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `iban` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `swift_code` | `varchar(20)` | yes | — | `NULL` | — |
| 7 | `branch_name` | `varchar(200)` | yes | — | `NULL` | — |
| 8 | `currency` | `varchar(10)` | **no** | — | `'AED'` | — |
| 9 | `opening_balance` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `chart_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 11 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### bank_reconciliation

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bank_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | bank_accounts.id (on delete restrict) |
| 3 | `statement_date` | `date` | **no** | — | *NULL* | — |
| 4 | `statement_balance` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 5 | `book_balance` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `difference` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `status` | `enum('in_progress','completed','cancelled')` | **no** | IDX | `'in_progress'` | — |
| 8 | `notes` | `text` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### bank_reconciliation_items

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `reconciliation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | bank_reconciliation.id (on delete cascade) |
| 3 | `bank_transaction_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | bank_transactions.id (on delete set null) |
| 4 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 5 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `is_matched` | `tinyint(1)` | **no** | — | `0` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### bank_transactions

10 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bank_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | bank_accounts.id (on delete restrict) |
| 3 | `transaction_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `transaction_type` | `enum('deposit','withdrawal','interest','bank_charges','adjustment')` | **no** | IDX | *NULL* | — |
| 5 | `reference_number` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `description` | `text` | yes | — | `NULL` | — |
| 8 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 9 | `status` | `enum('posted','draft','cancelled')` | **no** | — | `'posted'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### budgets

12 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `budget_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `budget_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `financial_year_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | financial_years.id (on delete restrict) |
| 5 | `budget_type` | `enum('annual','quarterly','monthly','project','department')` | **no** | IDX | *NULL* | — |
| 6 | `status` | `enum('draft','pending_approval','approved','active','rejected','closed')` | **no** | IDX | `'draft'` | — |
| 7 | `current_version_id` | `bigint(20) unsigned` | yes | IDX FK | `NULL` | budget_versions.id (on delete set null) · FK to budget_versions ÔÇö the active version |
| 8 | `description` | `text` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 10 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### budget_lines

11 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `budget_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | budgets.id (on delete cascade) |
| 3 | `budget_version_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_versions.id (on delete cascade) |
| 4 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 5 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 6 | `chart_of_accounts_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 7 | `month` | `tinyint(3) unsigned` | yes | — | `NULL` | 1-12 (Jul=1..Jun=12); NULL = whole-year/annual |
| 8 | `budget_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### budget_monitoring_snapshots

13 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `budget_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | budgets.id (on delete cascade) |
| 3 | `budget_version_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_versions.id (on delete cascade) |
| 4 | `financial_year_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | financial_years.id (on delete restrict) |
| 5 | `snapshot_date` | `date` | **no** | — | *NULL* | — |
| 6 | `month` | `tinyint(3) unsigned` | yes | — | `NULL` | — |
| 7 | `allocated` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `spent` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `committed` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `available` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 11 | `utilization_pct` | `decimal(7,2)` | **no** | — | `0.00` | — |
| 12 | `generated_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 13 | `generated_at` | `timestamp` | **no** | — | current_timestamp() | — |

### budget_revisions

11 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `budget_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | budgets.id (on delete cascade) |
| 3 | `budget_version_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_versions.id (on delete cascade) |
| 4 | `line_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_lines.id (on delete restrict) |
| 5 | `revision_type` | `enum('increase','decrease','adjustment')` | **no** | — | *NULL* | — |
| 6 | `old_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `new_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `change_amount` | `decimal(15,2)` | **no** | — | `0.00` | signed delta |
| 9 | `reason` | `text` | **no** | — | *NULL* | — |
| 10 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### budget_transfers

16 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `transfer_no` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `budget_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | budgets.id (on delete cascade) |
| 4 | `budget_version_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_versions.id (on delete cascade) |
| 5 | `from_line_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_lines.id (on delete restrict) |
| 6 | `to_line_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | budget_lines.id (on delete restrict) |
| 7 | `transfer_type` | `enum('department','project','account')` | **no** | — | *NULL* | — |
| 8 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `reason` | `text` | **no** | — | *NULL* | — |
| 10 | `status` | `enum('pending','approved','rejected','cancelled')` | **no** | IDX | `'pending'` | — |
| 11 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 12 | `requested_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 13 | `requested_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 15 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### budget_versions

12 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `budget_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | budgets.id (on delete cascade) |
| 3 | `version_no` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `version_type` | `enum('original','revision','forecast','final')` | **no** | — | `'original'` | — |
| 5 | `version_label` | `varchar(50)` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('draft','approved','active','locked')` | **no** | IDX | `'draft'` | — |
| 7 | `total_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `notes` | `text` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 10 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `approved_at` | `datetime` | yes | — | `NULL` | — |

### cash_accounts

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `cash_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 3 | `location` | `varchar(200)` | yes | — | `NULL` | — |
| 4 | `custodian_employee_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `opening_balance` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `chart_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 7 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### cash_flow_classifications

6 columns · 117 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete cascade) |
| 3 | `classification` | `enum('operating','investing','financing','non_cash')` | **no** | — | *NULL* | — |
| 4 | `is_active` | `tinyint(1)` | yes | — | `1` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 6 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### cash_transactions

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `cash_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | cash_accounts.id (on delete restrict) |
| 3 | `transaction_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `transaction_type` | `enum('receipt','payment','cash_deposit','cash_withdrawal','adjustment')` | **no** | IDX | *NULL* | — |
| 5 | `reference_number` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `description` | `text` | yes | — | `NULL` | — |
| 8 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 9 | `status` | `enum('posted','draft','cancelled')` | **no** | — | `'posted'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### chart_of_accounts

13 columns · 39 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | companies.id (on delete set null) |
| 3 | `account_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 4 | `account_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `group_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | account_groups.id (on delete restrict) |
| 6 | `type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | account_types.id (on delete restrict) |
| 7 | `opening_balance` | `decimal(15,2)` | yes | — | `0.00` | — |
| 8 | `opening_balance_type` | `enum('debit','credit')` | yes | — | `'debit'` | — |
| 9 | `allow_manual_entry` | `tinyint(1)` | yes | — | `1` | — |
| 10 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 13 | `deleted_at` | `timestamp` | yes | — | `NULL` | — |

### company_bank_accounts

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete cascade) |
| 3 | `account_label` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `bank_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `branch` | `varchar(150)` | yes | — | `NULL` | — |
| 6 | `account_name` | `varchar(190)` | yes | — | `NULL` | — |
| 7 | `account_number` | `varchar(60)` | yes | — | `NULL` | — |
| 8 | `iban` | `varchar(40)` | yes | — | `NULL` | — |
| 9 | `swift_bic` | `varchar(20)` | yes | — | `NULL` | — |
| 10 | `currency_code` | `char(3)` | **no** | — | `'USD'` | — |
| 11 | `account_type` | `enum('current','savings','margin','escrow','fx','fixed_deposit')` | **no** | — | `'current'` | — |
| 12 | `is_primary` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `gl_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `opening_balance` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 15 | `status` | `enum('active','inactive','closed')` | **no** | — | `'active'` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 18 | `deleted_at` | `timestamp` | yes | — | `NULL` | — |

### company_expenses

27 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `expense_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `expense_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `category_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | expense_categories.id (on delete restrict) |
| 5 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 6 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 7 | `vendor_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendors.id (on delete set null) |
| 8 | `description` | `text` | yes | — | `NULL` | — |
| 9 | `payment_method` | `enum('cash','bank_transfer','cheque','credit_card')` | yes | — | `NULL` | — |
| 10 | `cash_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | cash_accounts.id (on delete set null) |
| 11 | `bank_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | bank_accounts.id (on delete set null) |
| 12 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 13 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 14 | `total_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 15 | `tax_type_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 16 | `tax_rate_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 17 | `tax_group_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 18 | `tax_status` | `enum('taxable','zero_rated','exempt','out_of_scope')` | **no** | — | `'taxable'` | — |
| 19 | `taxable_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 20 | `tax_exemption_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 21 | `status` | `enum('draft','pending_approval','approved','rejected','paid','cancelled')` | yes | IDX | `'draft'` | — |
| 22 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 23 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 24 | `payment_journal_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) · GL journal for the payment posting (DR Expense Payable, CR Bank/Cash) |
| 25 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 26 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 27 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### consolidation_journals

11 columns · 38 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | consolidation_runs.id (on delete cascade) |
| 3 | `adjustment_type` | `enum('intercompany_elimination','currency_translation','reclassification','manual','other')` | **no** | — | *NULL* | — |
| 4 | `description` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `reason` | `text` | yes | — | `NULL` | — |
| 6 | `amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 7 | `source` | `varchar(50)` | **no** | — | `'system'` | — |
| 8 | `partner_pair_key` | `varchar(120)` | yes | — | `NULL` | idempotency: one elimination per IC pair per run |
| 9 | `status` | `enum('draft','approved','posted')` | **no** | — | `'posted'` | — |
| 10 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### consolidation_journal_lines

6 columns · 76 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `journal_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | consolidation_journals.id (on delete cascade) |
| 3 | `account_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `debit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 5 | `credit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 6 | `memo` | `varchar(255)` | yes | — | `NULL` | — |

### credit_notes

21 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `credit_note_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `invoice_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | customer_invoices.id (on delete set null) |
| 4 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete restrict) |
| 5 | `credit_note_date` | `date` | **no** | — | *NULL* | — |
| 6 | `credit_note_type` | `enum('price_correction','return','discount_adjustment','cancellation')` | **no** | — | `'price_correction'` | — |
| 7 | `reason` | `text` | yes | — | `NULL` | — |
| 8 | `subtotal` | `decimal(15,2)` | yes | — | `0.00` | — |
| 9 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 10 | `total_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `tax_type_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 12 | `tax_rate_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 13 | `tax_group_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `tax_status` | `enum('taxable','zero_rated','exempt','out_of_scope')` | **no** | — | `'taxable'` | — |
| 15 | `taxable_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 16 | `tax_exemption_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 17 | `status` | `enum('draft','issued','applied','cancelled')` | yes | IDX | `'draft'` | — |
| 18 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 19 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 20 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 21 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### customer_invoices

22 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `invoice_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete restrict) |
| 4 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 5 | `invoice_date` | `date` | **no** | — | *NULL* | — |
| 6 | `due_date` | `date` | **no** | IDX | *NULL* | — |
| 7 | `currency` | `varchar(10)` | yes | — | `'AED'` | — |
| 8 | `payment_terms` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `status` | `enum('draft','approved','sent','partially_paid','paid','overdue','cancelled')` | yes | IDX | `'draft'` | — |
| 10 | `subtotal` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `discount_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 12 | `discount_type` | `enum('percentage','fixed')` | yes | — | `NULL` | — |
| 13 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 14 | `total_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 15 | `paid_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 16 | `balance_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 17 | `notes` | `text` | yes | — | `NULL` | — |
| 18 | `terms_conditions` | `text` | yes | — | `NULL` | — |
| 19 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 20 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 21 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 22 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### customer_receipts

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `receipt_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete restrict) |
| 4 | `receipt_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `payment_method` | `enum('cash','bank_transfer','cheque','credit_card','online')` | **no** | — | `'bank_transfer'` | — |
| 6 | `bank_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | bank_accounts.id (on delete set null) |
| 7 | `cash_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | cash_accounts.id (on delete set null) |
| 8 | `reference_number` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `unallocated_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `remarks` | `text` | yes | — | `NULL` | — |
| 12 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 13 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### debit_notes

21 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `debit_note_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `bill_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendor_bills.id (on delete set null) |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 5 | `debit_note_date` | `date` | **no** | — | *NULL* | — |
| 6 | `debit_note_type` | `enum('price_correction','return','discount_adjustment','cancellation')` | **no** | — | `'price_correction'` | — |
| 7 | `reason` | `text` | yes | — | `NULL` | — |
| 8 | `subtotal` | `decimal(15,2)` | yes | — | `0.00` | — |
| 9 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 10 | `total_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `tax_type_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 12 | `tax_rate_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 13 | `tax_group_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `tax_status` | `enum('taxable','zero_rated','exempt','out_of_scope')` | **no** | — | `'taxable'` | — |
| 15 | `taxable_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 16 | `tax_exemption_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 17 | `status` | `enum('draft','issued','applied','cancelled')` | yes | IDX | `'draft'` | — |
| 18 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 19 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 20 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 21 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### depreciation_methods

6 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `method_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `method_code` | `varchar(10)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### expenses

13 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `expense_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `category_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | expense_categories_old.id (on delete restrict) |
| 4 | `expense_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 6 | `payment_method` | `enum('cash','bank_transfer','upi','credit_card')` | yes | — | `'bank_transfer'` | — |
| 7 | `vendor_name` | `varchar(200)` | yes | — | `NULL` | — |
| 8 | `description` | `text` | yes | — | `NULL` | — |
| 9 | `receipt_file` | `varchar(500)` | yes | — | `NULL` | — |
| 10 | `status` | `enum('pending','approved','rejected')` | yes | IDX | `'pending'` | — |
| 11 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### expense_approval_history

8 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `expense_type` | `enum('company_expense','employee_claim')` | **no** | IDX | *NULL* | — |
| 3 | `expense_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `approval_level` | `varchar(50)` | **no** | — | *NULL* | e.g. manager_approval, finance_approval, final_approval |
| 5 | `action` | `enum('approved','rejected','submitted','cancelled')` | **no** | — | *NULL* | — |
| 6 | `comments` | `text` | yes | — | `NULL` | — |
| 7 | `action_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### expense_attachments

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `expense_type` | `enum('company_expense','employee_claim')` | **no** | IDX | *NULL* | — |
| 3 | `expense_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `file_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 7 | `file_size` | `int(10) unsigned` | yes | — | `0` | — |
| 8 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `uploaded_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### expense_categories

8 columns · 22 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `description` | `text` | yes | — | `NULL` | — |
| 4 | `default_expense_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | chart_of_accounts.id (on delete set null) · FK to chart_of_accounts — default GL account to DR |
| 5 | `is_active` | `tinyint(1)` | yes | IDX | `1` | — |
| 6 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### expense_categories_old

6 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `category_name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `category_code` | `varchar(20)` | yes | — | `NULL` | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### expense_items

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `expense_type` | `enum('company_expense','employee_claim')` | **no** | IDX | *NULL* | — |
| 3 | `expense_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `description` | `varchar(500)` | **no** | — | *NULL* | — |
| 5 | `quantity` | `decimal(10,2)` | yes | — | `1.00` | — |
| 6 | `unit_price` | `decimal(15,4)` | yes | — | `0.0000` | — |
| 7 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 9 | `line_total` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `sort_order` | `int(11)` | yes | — | `0` | — |

### expense_reimbursements

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `reimbursement_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `claim_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employee_expense_claims.id (on delete restrict) |
| 4 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete restrict) |
| 5 | `payment_method` | `enum('cash','bank_transfer','cheque')` | **no** | — | `'bank_transfer'` | — |
| 6 | `bank_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | bank_accounts.id (on delete set null) |
| 7 | `cash_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | cash_accounts.id (on delete set null) |
| 8 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `reference_number` | `varchar(100)` | yes | — | `NULL` | — |
| 10 | `payment_date` | `date` | **no** | IDX | *NULL* | — |
| 11 | `remarks` | `text` | yes | — | `NULL` | — |
| 12 | `status` | `enum('draft','paid','cancelled')` | yes | IDX | `'draft'` | — |
| 13 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 14 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 15 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### finance_goods_receipt_items

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `grn_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipt_notes.id (on delete cascade) |
| 3 | `po_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_order_items.id (on delete restrict) |
| 4 | `description` | `varchar(500)` | yes | — | `NULL` | — |
| 5 | `quantity_received` | `decimal(10,2)` | **no** | — | `0.00` | — |
| 6 | `unit` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `unit_price` | `decimal(15,4)` | **no** | — | `0.0000` | — |
| 8 | `line_total` | `decimal(15,2)` | yes | — | `0.00` | — |
| 9 | `sort_order` | `int(11)` | yes | — | `0` | — |

### financial_years

12 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 3 | `year_name` | `varchar(50)` | **no** | — | *NULL* | — |
| 4 | `year_code` | `varchar(20)` | yes | — | `NULL` | — |
| 5 | `start_date` | `date` | **no** | IDX | *NULL* | — |
| 6 | `end_date` | `date` | **no** | — | *NULL* | — |
| 7 | `status` | `enum('active','closing','closed','archived')` | **no** | IDX | `'closed'` | — |
| 8 | `closed_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `closed_at` | `datetime` | yes | — | `NULL` | — |
| 10 | `is_default` | `tinyint(1)` | yes | — | `0` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### fixed_asset_categories

11 columns · 9 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_category_id` | `int(10) unsigned` | **no** | UQ FK | *NULL* | asset_categories.id (on delete restrict) |
| 3 | `depreciation_method_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | depreciation_methods.id (on delete restrict) |
| 4 | `useful_life_months` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `residual_value_percentage` | `decimal(5,2)` | yes | — | `0.00` | — |
| 6 | `asset_gl_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 7 | `accumulated_depreciation_gl_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 8 | `depreciation_expense_gl_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 9 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### fixed_asset_financials

17 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `asset_id` | `int(10) unsigned` | **no** | UQ FK | *NULL* | assets.id (on delete cascade) |
| 3 | `asset_category_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | fixed_asset_categories.id (on delete restrict) |
| 4 | `capitalization_date` | `date` | **no** | — | *NULL* | — |
| 5 | `in_service_date` | `date` | yes | — | `NULL` | — |
| 6 | `acquisition_cost` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 7 | `capitalized_cost` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 8 | `residual_value` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 9 | `useful_life_months` | `int(11)` | **no** | — | *NULL* | — |
| 10 | `depreciation_method_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | depreciation_methods.id (on delete restrict) |
| 11 | `depreciation_start_date` | `date` | **no** | — | *NULL* | — |
| 12 | `accumulated_depreciation` | `decimal(15,2)` | yes | — | `0.00` | — |
| 13 | `impairment_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 14 | `net_book_value` | `decimal(15,2)` | yes | — | `NULL` | STORED GENERATED |
| 15 | `status` | `enum('active','disposed','impaired')` | yes | — | `'active'` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### fixed_asset_history

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_asset_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | fixed_asset_financials.id (on delete restrict) |
| 3 | `event_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 4 | `amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 5 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### general_ledger

12 columns · 40 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `posting_date` | `date` | **no** | IDX | *NULL* | — |
| 3 | `journal_entry_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | journal_entries.id (on delete restrict) |
| 4 | `account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 5 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 6 | `voucher_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 7 | `voucher_no` | `varchar(50)` | **no** | — | *NULL* | — |
| 8 | `debit` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `credit` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `running_balance` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 11 | `financial_year_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | financial_years.id (on delete restrict) |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### goods_receipts

36 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete restrict) |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 5 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 6 | `warehouse_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 7 | `location_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `receipt_date` | `date` | **no** | IDX | *NULL* | — |
| 9 | `delivery_note_number` | `varchar(80)` | yes | — | `NULL` | — |
| 10 | `vendor_delivery_date` | `date` | yes | — | `NULL` | — |
| 11 | `received_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 12 | `inspection_required` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `notes` | `text` | yes | — | `NULL` | — |
| 14 | `status` | `enum('draft','pending_inspection','partially_accepted','accepted','rejected','cancelled')` | **no** | IDX | `'draft'` | — |
| 15 | `received_quantity_total` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 16 | `accepted_quantity_total` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 17 | `rejected_quantity_total` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 18 | `damaged_quantity_total` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 19 | `short_quantity_total` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 20 | `backorder_quantity_total` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 21 | `submitted_at` | `datetime` | yes | — | `NULL` | — |
| 22 | `submitted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 23 | `accepted_at` | `datetime` | yes | — | `NULL` | — |
| 24 | `accepted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 25 | `rejected_at` | `datetime` | yes | — | `NULL` | — |
| 26 | `rejected_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 27 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 28 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 29 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 30 | `inspection_status` | `enum('pending','passed','partially_passed','failed','not_required')` | **no** | — | `'not_required'` | — |
| 31 | `inspection_date` | `datetime` | yes | — | `NULL` | — |
| 32 | `inspected_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 33 | `inventory_movement_id` | `int(10) unsigned` | yes | — | `NULL` | link to inventory movement if posted |
| 34 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 35 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 36 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### goods_receipt_batches

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipts.id (on delete cascade) |
| 3 | `gr_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipt_items.id (on delete cascade) |
| 4 | `batch_number` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 5 | `quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 6 | `expiry_date` | `date` | yes | — | `NULL` | — |
| 7 | `manufacturing_date` | `date` | yes | — | `NULL` | — |
| 8 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### goods_receipt_damages

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipts.id (on delete cascade) |
| 3 | `gr_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipt_items.id (on delete cascade) |
| 4 | `quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 5 | `damage_reason` | `text` | yes | — | `NULL` | — |
| 6 | `damage_type` | `varchar(80)` | yes | — | `NULL` | — |
| 7 | `notes` | `text` | yes | — | `NULL` | — |
| 8 | `photos_documents` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### goods_receipt_inspections

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipts.id (on delete cascade) |
| 3 | `gr_item_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | goods_receipt_items.id (on delete set null) |
| 4 | `inspector_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 5 | `inspection_status` | `enum('pending','passed','partially_passed','failed','not_required')` | **no** | — | `'pending'` | — |
| 6 | `accepted_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 7 | `rejected_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 8 | `inspection_date` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `reason` | `text` | yes | — | `NULL` | — |
| 10 | `comments` | `text` | yes | — | `NULL` | — |
| 11 | `supporting_document` | `varchar(255)` | yes | — | `NULL` | — |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### goods_receipt_items

31 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipts.id (on delete cascade) |
| 3 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete restrict) |
| 4 | `po_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_order_items.id (on delete restrict) |
| 5 | `item_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 6 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 7 | `description` | `text` | yes | — | `NULL` | — |
| 8 | `item_type` | `enum('stock','non_stock','service','asset','expense')` | **no** | — | `'non_stock'` | — |
| 9 | `ordered_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `previously_received_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 11 | `remaining_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 12 | `received_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 13 | `accepted_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 14 | `rejected_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 15 | `damaged_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 16 | `short_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 17 | `backorder_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 18 | `unit` | `varchar(30)` | **no** | — | `'pcs'` | — |
| 19 | `unit_price` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 20 | `warehouse_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 21 | `location_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 22 | `batch_number` | `varchar(80)` | yes | IDX | `NULL` | — |
| 23 | `serial_number` | `varchar(80)` | yes | IDX | `NULL` | — |
| 24 | `expiry_date` | `date` | yes | — | `NULL` | — |
| 25 | `manufacturing_date` | `date` | yes | — | `NULL` | — |
| 26 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 27 | `damage_reason` | `text` | yes | — | `NULL` | — |
| 28 | `damage_type` | `varchar(80)` | yes | — | `NULL` | — |
| 29 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 30 | `sort_order` | `int(11)` | **no** | — | `1` | — |
| 31 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### goods_receipt_notes

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `grn_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete restrict) |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 5 | `receipt_date` | `date` | **no** | — | *NULL* | — |
| 6 | `delivery_note_number` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `status` | `enum('draft','completed','cancelled')` | yes | IDX | `'draft'` | — |
| 8 | `notes` | `text` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### goods_receipt_rejections

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipts.id (on delete cascade) |
| 3 | `gr_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipt_items.id (on delete cascade) |
| 4 | `quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 5 | `reason` | `text` | **no** | — | *NULL* | — |
| 6 | `inspection_result` | `enum('pending','passed','partially_passed','failed','not_required')` | yes | — | `'pending'` | — |
| 7 | `notes` | `text` | yes | — | `NULL` | — |
| 8 | `supporting_document` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### goods_receipt_serials

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipts.id (on delete cascade) |
| 3 | `gr_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | goods_receipt_items.id (on delete cascade) |
| 4 | `serial_number` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 5 | `batch_number` | `varchar(80)` | yes | — | `NULL` | — |
| 6 | `expiry_date` | `date` | yes | — | `NULL` | — |
| 7 | `status` | `enum('received','accepted','rejected','damaged')` | **no** | — | `'received'` | — |
| 8 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### intercompany_accounts

6 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `ic_type` | `enum('receivable','payable','revenue','expense','loan_given','loan_taken','interest_income','interest_expense','other')` | **no** | — | *NULL* | — |
| 4 | `account_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 5 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### inventory_stock_ledger

25 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 3 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete restrict) |
| 4 | `warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete restrict) |
| 5 | `location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 6 | `batch_number` | `varchar(80)` | yes | — | `NULL` | — |
| 7 | `serial_number` | `varchar(80)` | yes | — | `NULL` | — |
| 8 | `movement_type` | `enum('purchase_receipt','sales_issue','adjustment_in','adjustment_out','transfer_out','transfer_in','opening_stock','reversal')` | **no** | IDX | *NULL* | — |
| 9 | `qty_in` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `qty_out` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 11 | `unit_cost` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 12 | `cost_before` | `decimal(18,4)` | yes | — | `NULL` | avg unit cost before this movement |
| 13 | `cost_after` | `decimal(18,4)` | yes | — | `NULL` | avg unit cost after this movement |
| 14 | `value_before` | `decimal(18,4)` | yes | — | `NULL` | total stock value before this movement |
| 15 | `value_after` | `decimal(18,4)` | yes | — | `NULL` | total stock value after this movement |
| 16 | `total_value` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 17 | `reference_type` | `varchar(40)` | yes | IDX | `NULL` | — |
| 18 | `reference_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 19 | `reference_number` | `varchar(80)` | yes | — | `NULL` | — |
| 20 | `movement_datetime` | `datetime` | **no** | IDX | current_timestamp() | — |
| 21 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 22 | `notes` | `text` | yes | — | `NULL` | — |
| 23 | `status` | `enum('draft','posted','cancelled','reversed')` | **no** | — | `'posted'` | — |
| 24 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 25 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### invoices

16 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `invoice_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `client_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | clients.id (on delete set null) |
| 4 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 5 | `invoice_date` | `date` | **no** | IDX | *NULL* | — |
| 6 | `due_date` | `date` | yes | — | `NULL` | — |
| 7 | `subtotal` | `decimal(15,2)` | yes | — | `0.00` | — |
| 8 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 9 | `discount_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 10 | `total_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `paid_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 12 | `balance_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 13 | `status` | `enum('draft','sent','partially_paid','paid','overdue','cancelled')` | yes | IDX | `'draft'` | — |
| 14 | `notes` | `text` | yes | — | `NULL` | — |
| 15 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### invoice_items

20 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `invoice_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `quantity` | `decimal(10,2)` | yes | — | `1.00` | — |
| 6 | `unit` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `unit_price` | `decimal(15,2)` | yes | — | `0.00` | — |
| 8 | `discount_percent` | `decimal(5,2)` | yes | — | `0.00` | — |
| 9 | `tax_percent` | `decimal(5,2)` | yes | — | `0.00` | — |
| 10 | `tax_type_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 11 | `tax_rate_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 12 | `tax_group_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 13 | `tax_status` | `enum('taxable','zero_rated','exempt','out_of_scope')` | **no** | — | `'taxable'` | — |
| 14 | `taxable_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 15 | `tax_exemption_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 16 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 17 | `line_total` | `decimal(15,2)` | yes | — | `0.00` | — |
| 18 | `sort_order` | `int(11)` | yes | — | `0` | — |
| 19 | `tax_percentage` | `decimal(5,2)` | yes | — | `0.00` | — |
| 20 | `total` | `decimal(15,2)` | yes | — | `0.00` | — |

### invoice_status_history

7 columns · 13 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `invoice_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | customer_invoices.id (on delete cascade) |
| 3 | `from_status` | `varchar(50)` | yes | — | `NULL` | — |
| 4 | `to_status` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `changed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 6 | `change_note` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### journal_entries

12 columns · 20 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `voucher_no` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 4 | `voucher_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | voucher_types.id (on delete restrict) |
| 5 | `voucher_date` | `date` | **no** | IDX | *NULL* | — |
| 6 | `reference_no` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `description` | `text` | yes | — | `NULL` | — |
| 8 | `status` | `enum('draft','posted','reversed')` | **no** | IDX | `'draft'` | — |
| 9 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 10 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### journal_entry_details

6 columns · 41 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `journal_entry_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | journal_entries.id (on delete cascade) |
| 3 | `account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete restrict) |
| 4 | `debit_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 5 | `credit_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |

### payments

9 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `invoice_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | invoices.id (on delete cascade) |
| 3 | `payment_date` | `date` | **no** | IDX | *NULL* | — |
| 4 | `amount` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 5 | `payment_method` | `enum('cash','bank_transfer','upi','credit_card','paypal')` | yes | — | `'bank_transfer'` | — |
| 6 | `reference_number` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `notes` | `text` | yes | — | `NULL` | — |
| 8 | `received_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### payment_allocations

5 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payment_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_payments.id (on delete cascade) |
| 3 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete restrict) |
| 4 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### period_checklist_items

12 columns · 28 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_year_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `period_id` | `int(10) unsigned` | **no** | — | `0` | — |
| 4 | `template_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `item_text` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('pending','in_progress','completed','exception')` | **no** | IDX | `'pending'` | — |
| 7 | `comments` | `text` | yes | — | `NULL` | — |
| 8 | `attachment_path` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `completed_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### period_checklist_templates

7 columns · 70 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `item_text` | `varchar(255)` | **no** | — | *NULL* | — |
| 3 | `module_area` | `varchar(50)` | **no** | — | `'general'` | — |
| 4 | `applies_to` | `enum('period','year','both')` | **no** | — | `'both'` | — |
| 5 | `sort_order` | `int(11)` | **no** | — | `0` | — |
| 6 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### period_close_audit

10 columns · 1,503 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `action` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `financial_year_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `period_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `reference_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `details` | `text` | yes | — | `NULL` | — |
| 7 | `reason` | `text` | yes | — | `NULL` | — |
| 8 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 9 | `user_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### period_close_settings

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `retained_earnings_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 3 | `income_summary_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `closing_method` | `enum('direct_to_retained','via_income_summary')` | **no** | — | `'direct_to_retained'` | — |
| 5 | `require_checklist_before_period_close` | `tinyint(1)` | **no** | — | `0` | — |
| 6 | `require_checklist_before_year_close` | `tinyint(1)` | **no** | — | `1` | — |
| 7 | `allow_year_reopen` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### period_opening_balances

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_year_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | financial_years.id (on delete cascade) |
| 3 | `account_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `debit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 5 | `credit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 6 | `source_closing_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `generated_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `generated_at` | `datetime` | yes | — | `NULL` | — |

### petty_cash_funds

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `cash_account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | cash_accounts.id (on delete restrict) |
| 3 | `fund_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `custodian_employee_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `imprest_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 6 | `current_balance` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 7 | `status` | `enum('open','closed')` | **no** | IDX | `'open'` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### petty_cash_transactions

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `petty_cash_fund_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | petty_cash_funds.id (on delete cascade) |
| 3 | `transaction_type` | `enum('issue','expense','topup','close')` | **no** | IDX | *NULL* | — |
| 4 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 5 | `expense_category` | `varchar(200)` | yes | — | `NULL` | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `voucher_number` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 9 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### receipt_allocations

5 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `receipt_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | customer_receipts.id (on delete cascade) |
| 3 | `invoice_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | customer_invoices.id (on delete restrict) |
| 4 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### receipt_attachments

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `gr_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | goods_receipts.id (on delete cascade) |
| 3 | `sr_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | service_receipts.id (on delete cascade) |
| 4 | `doc_label` | `varchar(100)` | **no** | — | `'Supporting Document'` | — |
| 5 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `stored_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 7 | `file_size` | `int(10) unsigned` | **no** | — | `0` | — |
| 8 | `uploaded_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### service_receipts

31 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `sr_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete restrict) |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 5 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 6 | `receipt_date` | `date` | **no** | — | *NULL* | — |
| 7 | `service_description` | `text` | yes | — | `NULL` | — |
| 8 | `service_period_start` | `date` | yes | — | `NULL` | — |
| 9 | `service_period_end` | `date` | yes | — | `NULL` | — |
| 10 | `completion_date` | `date` | yes | — | `NULL` | — |
| 11 | `ordered_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 12 | `completed_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 13 | `accepted_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 14 | `rejected_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 15 | `project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 16 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 17 | `received_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 18 | `notes` | `text` | yes | — | `NULL` | — |
| 19 | `status` | `enum('draft','pending_acceptance','partially_accepted','accepted','rejected','cancelled')` | **no** | IDX | `'draft'` | — |
| 20 | `submitted_at` | `datetime` | yes | — | `NULL` | — |
| 21 | `submitted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 22 | `accepted_at` | `datetime` | yes | — | `NULL` | — |
| 23 | `accepted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 24 | `rejected_at` | `datetime` | yes | — | `NULL` | — |
| 25 | `rejected_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 26 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 27 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 28 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 29 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 30 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 31 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### service_receipt_items

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `sr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | service_receipts.id (on delete cascade) |
| 3 | `po_item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_order_items.id (on delete restrict) |
| 4 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `ordered_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 7 | `completed_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 8 | `accepted_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 9 | `rejected_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `unit` | `varchar(30)` | **no** | — | `'pcs'` | — |
| 11 | `unit_price` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 12 | `project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 13 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 15 | `sort_order` | `int(11)` | **no** | — | `1` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### tax_adjustments

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `adjustment_no` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `tax_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_types.id (on delete restrict) |
| 4 | `tax_period_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | tax_periods.id (on delete set null) |
| 5 | `adjustment_date` | `date` | **no** | IDX | *NULL* | — |
| 6 | `direction` | `enum('output','input')` | **no** | IDX | `'output'` | — |
| 7 | `taxable_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `tax_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `reason` | `text` | **no** | — | *NULL* | — |
| 10 | `reference` | `varchar(200)` | yes | — | `NULL` | — |
| 11 | `status` | `enum('draft','approved','rejected')` | **no** | IDX | `'draft'` | — |
| 12 | `approval_notes` | `text` | yes | — | `NULL` | — |
| 13 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 14 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 15 | `approved_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 16 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 18 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tax_exemptions

8 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `exemption_code` | `varchar(30)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `reason` | `text` | yes | — | `NULL` | — |
| 6 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tax_groups

6 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `group_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `description` | `text` | yes | — | `NULL` | — |
| 4 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 6 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tax_group_lines

5 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `tax_group_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_groups.id (on delete cascade) |
| 3 | `tax_rate_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_rates.id (on delete restrict) |
| 4 | `sequence` | `int(11)` | **no** | — | `0` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### tax_periods

9 columns · 13 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `tax_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_types.id (on delete restrict) |
| 3 | `period_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `start_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `end_date` | `date` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('open','submitted','closed')` | **no** | IDX | `'open'` | — |
| 7 | `closed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 8 | `closed_at` | `datetime` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### tax_rates

10 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `tax_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_types.id (on delete restrict) |
| 3 | `rate_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `rate_percentage` | `decimal(8,4)` | **no** | — | `0.0000` | — |
| 5 | `effective_from` | `date` | yes | IDX | `NULL` | — |
| 6 | `effective_to` | `date` | yes | — | `NULL` | — |
| 7 | `is_default` | `tinyint(1)` | **no** | — | `0` | — |
| 8 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tax_registrations

11 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 3 | `tax_number` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `tax_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_types.id (on delete restrict) |
| 5 | `country` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `registration_date` | `date` | yes | — | `NULL` | — |
| 7 | `effective_from` | `date` | yes | — | `NULL` | — |
| 8 | `effective_to` | `date` | yes | — | `NULL` | — |
| 9 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tax_settings

7 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `tax_name` | `varchar(50)` | **no** | — | *NULL* | — |
| 3 | `tax_percentage` | `decimal(5,2)` | yes | — | `0.00` | — |
| 4 | `tax_type` | `enum('gst','vat','tax')` | yes | — | `'gst'` | — |
| 5 | `is_active` | `tinyint(1)` | yes | — | `1` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tax_transactions

22 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `tax_type_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tax_types.id (on delete restrict) |
| 3 | `tax_period_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | tax_periods.id (on delete set null) |
| 4 | `source_module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 5 | `source_record_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 6 | `source_document_no` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `transaction_date` | `date` | **no** | IDX | *NULL* | — |
| 8 | `taxable_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 9 | `tax_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `tax_direction` | `enum('output','input','adjustment')` | **no** | IDX | `'output'` | — |
| 11 | `tax_rate` | `decimal(8,4)` | **no** | — | `0.0000` | — |
| 12 | `tax_rate_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | tax_rates.id (on delete set null) |
| 13 | `tax_group_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | tax_groups.id (on delete set null) |
| 14 | `tax_status` | `enum('taxable','zero_rated','exempt','out_of_scope')` | **no** | — | `'taxable'` | — |
| 15 | `exemption_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | tax_exemptions.id (on delete set null) |
| 16 | `counterparty_type` | `enum('customer','vendor','none')` | **no** | IDX | `'none'` | — |
| 17 | `counterparty_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 18 | `status` | `enum('active','reversed')` | **no** | — | `'active'` | — |
| 19 | `reversal_of` | `int(10) unsigned` | yes | IDX FK | `NULL` | tax_transactions.id (on delete set null) |
| 20 | `reference` | `varchar(200)` | yes | — | `NULL` | — |
| 21 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 22 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### tax_types

7 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `code` | `varchar(20)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### template_assets

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | templates.id (on delete cascade) |
| 3 | `asset_type` | `enum('logo','background','watermark','image','stamp','signature','barcode','qrcode')` | **no** | IDX | *NULL* | — |
| 4 | `asset_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### vendor_payments

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `payment_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 4 | `payment_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `payment_method` | `enum('cash','bank_transfer','cheque','credit_card','online')` | **no** | — | `'bank_transfer'` | — |
| 6 | `bank_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | bank_accounts.id (on delete set null) |
| 7 | `cash_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | cash_accounts.id (on delete set null) |
| 8 | `reference_number` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 10 | `unallocated_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 11 | `remarks` | `text` | yes | — | `NULL` | — |
| 12 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 13 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## Procurement & Vendors


### procurement_approval_rules

9 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | yes | IDX | `NULL` | NULL = any company |
| 3 | `department_id` | `int(10) unsigned` | yes | — | `NULL` | NULL = any department |
| 4 | `priority` | `enum('low','normal','high','urgent')` | yes | — | `NULL` | NULL = any |
| 5 | `max_amount` | `decimal(18,2)` | yes | — | `NULL` | applies when total <= this; NULL = unlimited |
| 6 | `approver_role_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | roles.id (on delete cascade) |
| 7 | `rule_sequence` | `int(10) unsigned` | **no** | — | `1` | — |
| 8 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### procurement_audit

8 columns · 804 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `action` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `pr_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `details` | `text` | yes | — | `NULL` | — |
| 5 | `reason` | `text` | yes | — | `NULL` | — |
| 6 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 7 | `user_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### procurement_sequences

4 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `seq_type` | `varchar(20)` | **no** | IDX | *NULL* | — |
| 3 | `seq_year` | `smallint(5) unsigned` | **no** | — | *NULL* | — |
| 4 | `last_number` | `int(10) unsigned` | **no** | — | `0` | — |

### procurement_settings

3 columns · 14 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `setting_key` | `varchar(60)` | **no** | UQ | *NULL* | — |
| 3 | `setting_value` | `varchar(255)` | **no** | — | `''` | — |

### purchase_orders

52 columns · 29 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `award_id` | `int(10) unsigned` | yes | IDX | `NULL` | source quotation_selection id |
| 4 | `quotation_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendor_quotations.id (on delete set null) · source vendor_quotations id |
| 5 | `rfq_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | request_for_quotations.id (on delete set null) · source request_for_quotations id |
| 6 | `pr_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | purchase_requisitions.id (on delete set null) · source purchase_requisitions id |
| 7 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 8 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 9 | `department_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 11 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 12 | `requester_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 13 | `buyer_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `po_date` | `date` | **no** | — | *NULL* | — |
| 15 | `expected_delivery_date` | `date` | yes | — | `NULL` | — |
| 16 | `currency` | `char(3)` | **no** | — | `'AED'` | — |
| 17 | `payment_terms` | `varchar(120)` | yes | — | `NULL` | — |
| 18 | `delivery_terms` | `varchar(120)` | yes | — | `NULL` | — |
| 19 | `delivery_location` | `varchar(200)` | yes | — | `NULL` | — |
| 20 | `incoterms` | `varchar(60)` | yes | — | `NULL` | — |
| 21 | `warranty_terms` | `varchar(160)` | yes | — | `NULL` | — |
| 22 | `shipping_method` | `varchar(60)` | yes | — | `NULL` | — |
| 23 | `special_instructions` | `text` | yes | — | `NULL` | — |
| 24 | `vendor_notes` | `text` | yes | — | `NULL` | — |
| 25 | `internal_notes` | `text` | yes | — | `NULL` | — |
| 26 | `subtotal` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 27 | `discount_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 28 | `tax_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 29 | `other_charges_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 30 | `grand_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 31 | `status` | `enum('draft','pending_approval','approved','rejected','sent','acknowledged','partially_received','fully_received','partially_billed','fully_billed','closed','cancelled')` | **no** | IDX | `'draft'` | — |
| 32 | `revision_number` | `int(10) unsigned` | **no** | — | `0` | — |
| 33 | `submitted_at` | `datetime` | yes | — | `NULL` | — |
| 34 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 35 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 36 | `sent_at` | `datetime` | yes | — | `NULL` | — |
| 37 | `sent_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 38 | `acknowledged_at` | `datetime` | yes | — | `NULL` | — |
| 39 | `acknowledged_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 40 | `vendor_reference` | `varchar(100)` | yes | — | `NULL` | — |
| 41 | `expected_delivery_date_ack` | `date` | yes | — | `NULL` | — |
| 42 | `vendor_comments` | `text` | yes | — | `NULL` | — |
| 43 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 44 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 45 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 46 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 47 | `closed_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 48 | `closed_at` | `datetime` | yes | — | `NULL` | — |
| 49 | `close_reason` | `text` | yes | — | `NULL` | — |
| 50 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 51 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 52 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### purchase_order_approvals

8 columns · 27 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `approver_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `action` | `varchar(30)` | **no** | — | *NULL* | submitted/approved/rejected/resubmitted |
| 5 | `comments` | `text` | yes | — | `NULL` | — |
| 6 | `previous_status` | `varchar(30)` | yes | — | `NULL` | — |
| 7 | `new_status` | `varchar(30)` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### purchase_order_attachments

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `doc_label` | `varchar(100)` | **no** | — | `'Supporting Document'` | — |
| 4 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `stored_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(10) unsigned` | **no** | — | `0` | — |
| 7 | `uploaded_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### purchase_order_charges

4 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `charge_name` | `varchar(80)` | **no** | — | *NULL* | — |
| 4 | `amount` | `decimal(18,2)` | **no** | — | `0.00` | — |

### purchase_order_communications

9 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `method` | `enum('email','pdf','print','manual')` | **no** | — | `'manual'` | — |
| 4 | `recipient` | `varchar(200)` | yes | — | `NULL` | — |
| 5 | `subject` | `varchar(200)` | yes | — | `NULL` | — |
| 6 | `status` | `enum('queued','sent','failed')` | **no** | — | `'sent'` | — |
| 7 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 8 | `sent_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 9 | `sent_at` | `datetime` | **no** | — | current_timestamp() | — |

### purchase_order_items

27 columns · 52 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `po_item_number` | `int(10) unsigned` | **no** | — | `1` | — |
| 4 | `award_item_id` | `int(10) unsigned` | yes | — | `NULL` | source quotation_selection rfq_item_id |
| 5 | `rfq_item_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | rfq_items.id (on delete set null) |
| 6 | `quotation_item_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `pr_item_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 8 | `item_type` | `enum('stock','non_stock','service','asset','expense')` | **no** | — | `'non_stock'` | — |
| 9 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 10 | `description` | `text` | yes | — | `NULL` | — |
| 11 | `quantity` | `decimal(18,3)` | **no** | — | `1.000` | — |
| 12 | `unit` | `varchar(30)` | **no** | — | `'pcs'` | — |
| 13 | `unit_price` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 14 | `discount_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 15 | `tax_percent` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 16 | `line_subtotal` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 17 | `line_tax` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 18 | `line_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 19 | `required_date` | `date` | yes | — | `NULL` | — |
| 20 | `delivery_location` | `varchar(200)` | yes | — | `NULL` | — |
| 21 | `project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 22 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 23 | `awarded_quantity` | `decimal(18,3)` | **no** | — | `0.000` | from award |
| 24 | `ordered_quantity` | `decimal(18,3)` | **no** | — | `0.000` | this PO qty |
| 25 | `remaining_quantity` | `decimal(18,3)` | **no** | — | `0.000` | awarded - ordered |
| 26 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 27 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### purchase_order_revisions

8 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `revision_number` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `changed_fields` | `longtext` | yes | — | `NULL` | {"field":"old→new",...} · binary collation |
| 5 | `reason` | `text` | **no** | — | *NULL* | — |
| 6 | `changed_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 7 | `changed_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `approval_status` | `enum('pending','approved','rejected')` | yes | — | `'pending'` | — |

### purchase_order_revision_items

9 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `revision_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_order_revisions.id (on delete cascade) |
| 3 | `po_item_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `quantity` | `decimal(18,3)` | **no** | — | *NULL* | — |
| 6 | `unit_price` | `decimal(18,2)` | **no** | — | *NULL* | — |
| 7 | `discount_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 8 | `tax_percent` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 9 | `line_total` | `decimal(18,2)` | **no** | — | *NULL* | — |

### purchase_requisitions

29 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `pr_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 4 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 5 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 6 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `branch` | `varchar(120)` | yes | — | `NULL` | — |
| 8 | `requester_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 9 | `request_date` | `date` | **no** | — | *NULL* | — |
| 10 | `required_date` | `date` | **no** | — | *NULL* | — |
| 11 | `priority` | `enum('low','normal','high','urgent')` | **no** | — | `'normal'` | — |
| 12 | `purpose` | `varchar(255)` | yes | — | `NULL` | — |
| 13 | `description` | `text` | yes | — | `NULL` | — |
| 14 | `currency` | `char(3)` | **no** | — | `'AED'` | — |
| 15 | `status` | `enum('draft','submitted','pending_approval','approved','rejected','cancelled','closed')` | **no** | IDX | `'draft'` | — |
| 16 | `subtotal` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 17 | `tax_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 18 | `discount_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 19 | `grand_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 20 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 21 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 22 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 23 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 24 | `submitted_at` | `datetime` | yes | — | `NULL` | — |
| 25 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 26 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 27 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 28 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 29 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### purchase_requisition_approvals

8 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `pr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_requisitions.id (on delete cascade) |
| 3 | `approver_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 4 | `action` | `enum('submitted','approved','rejected','resubmitted','cancelled','reopened')` | **no** | — | *NULL* | — |
| 5 | `comments` | `text` | yes | — | `NULL` | — |
| 6 | `previous_status` | `varchar(30)` | yes | — | `NULL` | — |
| 7 | `new_status` | `varchar(30)` | yes | — | `NULL` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### purchase_requisition_attachments

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `pr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_requisitions.id (on delete cascade) |
| 3 | `doc_label` | `varchar(100)` | **no** | — | `'Supporting Document'` | — |
| 4 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `stored_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(10) unsigned` | **no** | — | `0` | — |
| 7 | `uploaded_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### purchase_requisition_items

20 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `pr_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_requisitions.id (on delete cascade) |
| 3 | `item_type` | `enum('stock','non_stock','service','asset','expense')` | **no** | — | `'non_stock'` | — |
| 4 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `quantity` | `decimal(18,3)` | **no** | — | `1.000` | — |
| 7 | `unit` | `varchar(30)` | **no** | — | `'pcs'` | — |
| 8 | `est_unit_price` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 9 | `discount_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 10 | `tax_percent` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 11 | `line_subtotal` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 12 | `line_tax` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 13 | `line_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 14 | `required_date` | `date` | yes | — | `NULL` | — |
| 15 | `preferred_vendor_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendors.id (on delete set null) |
| 16 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 17 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 18 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 19 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 20 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### quotation_attachments

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `quotation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_quotations.id (on delete cascade) |
| 3 | `doc_label` | `varchar(100)` | **no** | — | `'Supporting Document'` | — |
| 4 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `stored_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(10) unsigned` | **no** | — | `0` | — |
| 7 | `uploaded_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### quotation_evaluations

9 columns · 354 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `quotation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_quotations.id (on delete cascade) |
| 3 | `criterion` | `varchar(80)` | **no** | — | *NULL* | — |
| 4 | `weight` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 5 | `score` | `decimal(5,2)` | **no** | — | `0.00` | 0-10 |
| 6 | `weighted_score` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 7 | `comments` | `varchar(255)` | yes | — | `NULL` | — |
| 8 | `evaluated_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 9 | `evaluated_at` | `datetime` | **no** | — | current_timestamp() | — |

### quotation_other_charges

4 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `quotation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_quotations.id (on delete cascade) |
| 3 | `charge_name` | `varchar(80)` | **no** | — | *NULL* | — |
| 4 | `amount` | `decimal(18,2)` | **no** | — | `0.00` | — |

### quotation_selection

11 columns · 74 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `rfq_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | request_for_quotations.id (on delete cascade) |
| 3 | `quotation_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendor_quotations.id (on delete cascade) |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 5 | `rfq_item_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | rfq_items.id (on delete cascade) |
| 6 | `award_level` | `enum('rfq','item')` | **no** | — | `'rfq'` | — |
| 7 | `reason` | `text` | **no** | — | *NULL* | — |
| 8 | `is_override` | `tinyint(1)` | **no** | — | `0` | — |
| 9 | `override_reason` | `text` | yes | — | `NULL` | — |
| 10 | `selected_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 11 | `selected_at` | `datetime` | **no** | — | current_timestamp() | — |

### request_for_quotations

24 columns · 37 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `rfq_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `pr_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | purchase_requisitions.id (on delete set null) · source approved requisition |
| 4 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 5 | `department_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `project_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `requester_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 9 | `rfq_date` | `date` | **no** | — | *NULL* | — |
| 10 | `required_date` | `date` | yes | — | `NULL` | — |
| 11 | `response_due` | `date` | **no** | — | *NULL* | — |
| 12 | `currency` | `char(3)` | **no** | — | `'AED'` | — |
| 13 | `delivery_location` | `varchar(200)` | yes | — | `NULL` | — |
| 14 | `payment_terms` | `varchar(120)` | yes | — | `NULL` | — |
| 15 | `delivery_terms` | `varchar(120)` | yes | — | `NULL` | — |
| 16 | `notes` | `text` | yes | — | `NULL` | — |
| 17 | `status` | `enum('draft','open','sent','partially_responded','fully_responded','evaluation','awarded','cancelled','closed')` | **no** | IDX | `'draft'` | — |
| 18 | `estimated_pr_value` | `decimal(18,2)` | **no** | — | `0.00` | informational only |
| 19 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 20 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 21 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 22 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 23 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 24 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### rfq_evaluation_criteria

5 columns · 138 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `rfq_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | request_for_quotations.id (on delete cascade) |
| 3 | `criterion` | `varchar(80)` | **no** | — | *NULL* | — |
| 4 | `weight` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 5 | `sort_order` | `int(11)` | **no** | — | `1` | — |

### rfq_items

14 columns · 151 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `rfq_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | request_for_quotations.id (on delete cascade) |
| 3 | `pr_item_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | purchase_requisition_items.id (on delete set null) |
| 4 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `specification` | `text` | yes | — | `NULL` | — |
| 7 | `quantity` | `decimal(18,3)` | **no** | — | `1.000` | — |
| 8 | `unit` | `varchar(30)` | **no** | — | `'pcs'` | — |
| 9 | `required_date` | `date` | yes | — | `NULL` | — |
| 10 | `delivery_location` | `varchar(200)` | yes | — | `NULL` | — |
| 11 | `tax_percent` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 12 | `est_unit_price` | `decimal(18,2)` | **no** | — | `0.00` | PR estimate, informational for savings |
| 13 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### rfq_vendors

8 columns · 98 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `rfq_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | request_for_quotations.id (on delete cascade) |
| 3 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 4 | `status` | `enum('selected','sent','viewed','responded','declined','expired')` | **no** | — | `'selected'` | — |
| 5 | `sent_at` | `datetime` | yes | — | `NULL` | — |
| 6 | `sent_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `responded_at` | `datetime` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### rfq_vendor_communications

8 columns · 98 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `rfq_vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | rfq_vendors.id (on delete cascade) |
| 3 | `method` | `enum('email','pdf','print','manual')` | **no** | — | `'manual'` | — |
| 4 | `recipient` | `varchar(200)` | yes | — | `NULL` | — |
| 5 | `status` | `enum('queued','sent','failed')` | **no** | — | `'sent'` | — |
| 6 | `notes` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `sent_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 8 | `sent_at` | `datetime` | **no** | — | current_timestamp() | — |

### vendors

17 columns · 26 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `vendor_code` | `varchar(50)` | yes | UQ | `NULL` | — |
| 3 | `vendor_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `contact_person` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `vat_number` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `payment_terms` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `currency` | `varchar(10)` | yes | — | `'AED'` | — |
| 8 | `default_expense_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `default_payable_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `email` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `phone` | `varchar(20)` | yes | — | `NULL` | — |
| 12 | `address` | `text` | yes | — | `NULL` | — |
| 13 | `website` | `varchar(200)` | yes | — | `NULL` | — |
| 14 | `notes` | `text` | yes | — | `NULL` | — |
| 15 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### vendor_bills

37 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `vendor_invoice_number` | `varchar(100)` | yes | IDX | `NULL` | — |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 5 | `company_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | companies.id (on delete set null) |
| 6 | `department_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | departments.id (on delete set null) |
| 7 | `project_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | projects.id (on delete set null) |
| 8 | `cost_center_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | cost_centers.id (on delete set null) |
| 9 | `po_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | purchase_orders.id (on delete set null) |
| 10 | `procurement_po_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 11 | `procurement_gr_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 12 | `procurement_sr_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 13 | `match_status` | `enum('pending','matched','exception','overridden')` | **no** | IDX | `'pending'` | — |
| 14 | `tolerance_quantity` | `decimal(6,3)` | yes | — | `NULL` | — |
| 15 | `tolerance_price` | `decimal(18,4)` | yes | — | `NULL` | — |
| 16 | `tolerance_amount` | `decimal(18,2)` | yes | — | `NULL` | — |
| 17 | `is_procurement_bill` | `tinyint(1)` | **no** | IDX | `0` | — |
| 18 | `grn_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | goods_receipt_notes.id (on delete set null) |
| 19 | `bill_date` | `date` | **no** | — | *NULL* | — |
| 20 | `due_date` | `date` | **no** | IDX | *NULL* | — |
| 21 | `currency` | `varchar(10)` | yes | — | `'AED'` | — |
| 22 | `payment_terms` | `varchar(100)` | yes | — | `NULL` | — |
| 23 | `status` | `enum('draft','approved','partially_paid','paid','overdue','cancelled')` | yes | IDX | `'draft'` | — |
| 24 | `subtotal` | `decimal(15,2)` | yes | — | `0.00` | — |
| 25 | `discount_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 26 | `discount_type` | `enum('percentage','fixed')` | yes | — | `NULL` | — |
| 27 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 28 | `total_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 29 | `paid_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 30 | `balance_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 31 | `notes` | `text` | yes | — | `NULL` | — |
| 32 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 33 | `posted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 34 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 35 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 36 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 37 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### vendor_bill_approvals

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete cascade) |
| 3 | `approver_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `action` | `enum('submitted','approved','rejected','overridden','cancelled')` | **no** | — | *NULL* | — |
| 5 | `comments` | `text` | yes | — | `NULL` | — |
| 6 | `override_reason` | `text` | yes | — | `NULL` | — |
| 7 | `previous_status` | `varchar(30)` | yes | — | `NULL` | — |
| 8 | `new_status` | `varchar(30)` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### vendor_bill_attachments

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete cascade) |
| 3 | `doc_label` | `varchar(100)` | **no** | — | `'Supporting Document'` | — |
| 4 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `stored_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(10) unsigned` | **no** | — | `0` | — |
| 7 | `uploaded_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### vendor_bill_exceptions

20 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete cascade) |
| 3 | `match_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendor_bill_matches.id (on delete set null) |
| 4 | `exception_type` | `enum('quantity_mismatch','price_mismatch','tax_mismatch','missing_receipt','over_billing','under_billing','duplicate_invoice')` | **no** | — | *NULL* | — |
| 5 | `severity` | `enum('low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 6 | `po_value` | `decimal(18,2)` | yes | — | `NULL` | — |
| 7 | `received_value` | `decimal(18,2)` | yes | — | `NULL` | — |
| 8 | `bill_value` | `decimal(18,2)` | yes | — | `NULL` | — |
| 9 | `difference` | `decimal(18,2)` | yes | — | `NULL` | — |
| 10 | `difference_percent` | `decimal(6,3)` | yes | — | `NULL` | — |
| 11 | `reason` | `text` | yes | — | `NULL` | — |
| 12 | `affected_items` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `required_action` | `enum('review','adjust','reject','approve_override')` | **no** | — | `'review'` | — |
| 14 | `status` | `enum('open','resolved','overridden','rejected')` | **no** | IDX | `'open'` | — |
| 15 | `resolved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 16 | `resolved_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `override_reason` | `text` | yes | — | `NULL` | — |
| 18 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 19 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### vendor_bill_items

29 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete cascade) |
| 3 | `description` | `varchar(500)` | **no** | — | *NULL* | — |
| 4 | `quantity` | `decimal(10,2)` | **no** | — | `1.00` | — |
| 5 | `billed_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 6 | `remaining_billable_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 7 | `match_quantity_status` | `enum('pending','matched','short','over','exception')` | **no** | — | `'pending'` | — |
| 8 | `match_price_status` | `enum('pending','matched','variance','exception')` | **no** | — | `'pending'` | — |
| 9 | `quantity_difference` | `decimal(18,3)` | yes | — | `0.000` | — |
| 10 | `price_difference` | `decimal(18,4)` | yes | — | `0.0000` | — |
| 11 | `amount_difference` | `decimal(18,2)` | yes | — | `0.00` | — |
| 12 | `unit` | `varchar(50)` | yes | — | `NULL` | — |
| 13 | `unit_price` | `decimal(15,4)` | **no** | — | `0.0000` | — |
| 14 | `discount_amount` | `decimal(18,2)` | yes | — | `0.00` | — |
| 15 | `discount_percent` | `decimal(5,2)` | yes | — | `0.00` | — |
| 16 | `tax_percent` | `decimal(5,2)` | yes | — | `0.00` | — |
| 17 | `tax_type_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 18 | `tax_rate_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 19 | `tax_group_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 20 | `tax_status` | `enum('taxable','zero_rated','exempt','out_of_scope')` | **no** | — | `'taxable'` | — |
| 21 | `taxable_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 22 | `tax_exemption_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 23 | `tax_amount` | `decimal(15,2)` | yes | — | `0.00` | — |
| 24 | `line_total` | `decimal(15,2)` | yes | — | `0.00` | — |
| 25 | `expense_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | chart_of_accounts.id (on delete set null) · FK to chart_of_accounts -- which expense account to DR |
| 26 | `procurement_po_item_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 27 | `procurement_gr_item_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 28 | `procurement_sr_item_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 29 | `sort_order` | `int(11)` | yes | — | `0` | — |

### vendor_bill_matches

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete cascade) |
| 3 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete restrict) |
| 4 | `gr_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | goods_receipts.id (on delete set null) |
| 5 | `sr_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | service_receipts.id (on delete set null) |
| 6 | `match_status` | `enum('pending','matched','exception','overridden')` | **no** | IDX | `'pending'` | — |
| 7 | `total_po_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 8 | `total_gr_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 9 | `total_bill_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 10 | `variance_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 11 | `variance_percent` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 12 | `exception_details` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `matched_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `matched_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### vendor_quotations

24 columns · 85 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `quotation_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `rfq_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | request_for_quotations.id (on delete cascade) |
| 4 | `vendor_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendors.id (on delete restrict) |
| 5 | `quotation_date` | `date` | **no** | — | *NULL* | — |
| 6 | `valid_until` | `date` | yes | — | `NULL` | — |
| 7 | `currency` | `char(3)` | **no** | — | `'AED'` | — |
| 8 | `payment_terms` | `varchar(120)` | yes | — | `NULL` | — |
| 9 | `delivery_terms` | `varchar(120)` | yes | — | `NULL` | — |
| 10 | `incoterms` | `varchar(60)` | yes | — | `NULL` | — |
| 11 | `warranty_terms` | `varchar(160)` | yes | — | `NULL` | — |
| 12 | `vendor_reference` | `varchar(100)` | yes | — | `NULL` | — |
| 13 | `lead_time_days` | `int(10) unsigned` | yes | — | `NULL` | — |
| 14 | `notes` | `text` | yes | — | `NULL` | — |
| 15 | `other_charges_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 16 | `subtotal` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 17 | `discount_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 18 | `tax_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 19 | `grand_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 20 | `status` | `enum('draft','received','under_evaluation','shortlisted','rejected','selected','expired','cancelled')` | **no** | — | `'draft'` | — |
| 21 | `received_at` | `datetime` | yes | — | `NULL` | — |
| 22 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 23 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 24 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### vendor_quotation_items

17 columns · 339 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `quotation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_quotations.id (on delete cascade) |
| 3 | `rfq_item_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | rfq_items.id (on delete set null) |
| 4 | `is_alternative` | `tinyint(1)` | **no** | — | `0` | — |
| 5 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `quantity` | `decimal(18,3)` | **no** | — | `1.000` | — |
| 8 | `unit` | `varchar(30)` | **no** | — | `'pcs'` | — |
| 9 | `unit_price` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 10 | `discount_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 11 | `tax_percent` | `decimal(6,3)` | **no** | — | `0.000` | — |
| 12 | `line_subtotal` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 13 | `line_tax` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 14 | `line_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 15 | `delivery_days` | `int(10) unsigned` | yes | — | `NULL` | — |
| 16 | `warranty_months` | `int(10) unsigned` | yes | — | `NULL` | — |
| 17 | `notes` | `varchar(255)` | yes | — | `NULL` | — |

## Sales & CRM


### clients

98 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `client_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `customer_type` | `enum('company','individual','partnership','sole_proprietorship','government','trust')` | **no** | IDX | `'company'` | — |
| 4 | `account_group` | `varchar(50)` | yes | — | `NULL` | — |
| 5 | `legal_name` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `trade_name` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `search_term` | `varchar(120)` | yes | IDX | `NULL` | — |
| 8 | `segment` | `varchar(60)` | yes | IDX | `NULL` | — |
| 9 | `parent_client_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | clients.id (on delete set null) |
| 10 | `is_one_time` | `tinyint(1)` | **no** | — | `0` | — |
| 11 | `customer_since` | `date` | yes | — | `NULL` | — |
| 12 | `lead_source` | `varchar(80)` | yes | — | `NULL` | — |
| 13 | `company_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 14 | `contact_person` | `varchar(100)` | yes | — | `NULL` | — |
| 15 | `email` | `varchar(255)` | yes | — | `NULL` | — |
| 16 | `phone` | `varchar(50)` | yes | — | `NULL` | — |
| 17 | `alternate_phone` | `varchar(40)` | yes | — | `NULL` | — |
| 18 | `whatsapp` | `varchar(40)` | yes | — | `NULL` | — |
| 19 | `address` | `text` | yes | — | `NULL` | — |
| 20 | `address_line1` | `varchar(255)` | yes | — | `NULL` | — |
| 21 | `address_line2` | `varchar(255)` | yes | — | `NULL` | — |
| 22 | `city` | `varchar(120)` | yes | — | `NULL` | — |
| 23 | `state` | `varchar(120)` | yes | — | `NULL` | — |
| 24 | `postal_code` | `varchar(20)` | yes | — | `NULL` | — |
| 25 | `currency_code` | `char(3)` | **no** | — | `'USD'` | — |
| 26 | `payment_terms` | `varchar(50)` | yes | — | `NULL` | — |
| 27 | `payment_term_days` | `smallint(5) unsigned` | **no** | — | `30` | — |
| 28 | `payment_method` | `varchar(40)` | yes | — | `NULL` | — |
| 29 | `default_receivable_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | chart_of_accounts.id (on delete set null) |
| 30 | `credit_limit` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 31 | `credit_rating` | `varchar(10)` | yes | — | `NULL` | — |
| 32 | `credit_hold` | `enum('no','manual','automatic')` | **no** | — | `'no'` | — |
| 33 | `collection_priority` | `tinyint(3) unsigned` | **no** | — | `3` | — |
| 34 | `interest_rate` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 35 | `cash_discount_percent` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 36 | `discount_days` | `smallint(5) unsigned` | **no** | — | `0` | — |
| 37 | `billing_cycle` | `enum('per_transaction','weekly','monthly','quarterly','annual')` | **no** | — | `'per_transaction'` | — |
| 38 | `consolidated_billing` | `tinyint(1)` | **no** | — | `0` | — |
| 39 | `allow_partial_payment` | `tinyint(1)` | **no** | — | `1` | — |
| 40 | `price_list` | `varchar(60)` | yes | — | `NULL` | — |
| 41 | `tax_inclusive` | `tinyint(1)` | **no** | — | `0` | — |
| 42 | `invoice_delivery` | `enum('email','print','portal','courier')` | **no** | — | `'email'` | — |
| 43 | `invoice_email` | `varchar(190)` | yes | — | `NULL` | — |
| 44 | `invoice_cc_emails` | `varchar(500)` | yes | — | `NULL` | — |
| 45 | `bank_name` | `varchar(150)` | yes | — | `NULL` | — |
| 46 | `bank_account_name` | `varchar(190)` | yes | — | `NULL` | — |
| 47 | `bank_account_number` | `varchar(60)` | yes | — | `NULL` | — |
| 48 | `bank_branch` | `varchar(150)` | yes | — | `NULL` | — |
| 49 | `bank_identifier` | `varchar(60)` | yes | — | `NULL` | — |
| 50 | `country` | `varchar(100)` | yes | — | `NULL` | — |
| 51 | `website` | `varchar(255)` | yes | — | `NULL` | — |
| 52 | `linkedin_url` | `varchar(255)` | yes | — | `NULL` | — |
| 53 | `preferred_language` | `varchar(40)` | yes | — | `NULL` | — |
| 54 | `timezone` | `varchar(60)` | yes | — | `NULL` | — |
| 55 | `preferred_contact_method` | `enum('email','phone','whatsapp','mail')` | **no** | — | `'email'` | — |
| 56 | `gst_vat_number` | `varchar(50)` | yes | — | `NULL` | — |
| 57 | `industry` | `varchar(100)` | yes | IDX | `NULL` | — |
| 58 | `registration_number` | `varchar(80)` | yes | — | `NULL` | — |
| 59 | `tax_id` | `varchar(80)` | yes | — | `NULL` | — |
| 60 | `tax_id_secondary` | `varchar(80)` | yes | — | `NULL` | — |
| 61 | `tax_category` | `enum('regular','composition','unregistered','sez','export','deemed_export','overseas')` | **no** | — | `'regular'` | — |
| 62 | `tax_jurisdiction` | `varchar(120)` | yes | — | `NULL` | — |
| 63 | `state_code` | `varchar(12)` | yes | — | `NULL` | — |
| 64 | `tax_registration_date` | `date` | yes | — | `NULL` | — |
| 65 | `tax_exemption_ref` | `varchar(120)` | yes | — | `NULL` | — |
| 66 | `tax_exemption_upto` | `date` | yes | — | `NULL` | — |
| 67 | `withholding_tax_rate` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 68 | `export_code` | `varchar(80)` | yes | — | `NULL` | — |
| 69 | `assigned_manager` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 70 | `sales_representative_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 71 | `support_representative_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 72 | `sales_region` | `varchar(120)` | yes | — | `NULL` | — |
| 73 | `distribution_channel` | `varchar(60)` | yes | — | `NULL` | — |
| 74 | `shipping_condition` | `varchar(60)` | yes | — | `NULL` | — |
| 75 | `incoterms` | `varchar(10)` | yes | — | `NULL` | — |
| 76 | `incoterms_location` | `varchar(150)` | yes | — | `NULL` | — |
| 77 | `delivery_lead_time_days` | `smallint(5) unsigned` | **no** | — | `0` | — |
| 78 | `default_discount_percent` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 79 | `commission_rate` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 80 | `contract_start_date` | `date` | yes | — | `NULL` | — |
| 81 | `contract_end_date` | `date` | yes | — | `NULL` | — |
| 82 | `company_size` | `enum('micro','small','medium','large','enterprise')` | yes | — | `NULL` | — |
| 83 | `number_of_employees` | `int(10) unsigned` | yes | — | `NULL` | — |
| 84 | `annual_revenue` | `decimal(18,2)` | yes | — | `NULL` | — |
| 85 | `year_established` | `smallint(5) unsigned` | yes | — | `NULL` | — |
| 86 | `status` | `enum('active','inactive','prospect','blocked','archived')` | **no** | IDX | `'active'` | — |
| 87 | `kyc_status` | `enum('not_started','pending','submitted','verified','rejected')` | **no** | — | `'not_started'` | — |
| 88 | `kyc_verified_at` | `datetime` | yes | — | `NULL` | — |
| 89 | `kyc_verified_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 90 | `risk_level` | `enum('low','medium','high')` | **no** | — | `'low'` | — |
| 91 | `marketing_consent` | `tinyint(1)` | **no** | — | `0` | — |
| 92 | `data_processing_consent` | `tinyint(1)` | **no** | — | `0` | — |
| 93 | `internal_notes` | `text` | yes | — | `NULL` | — |
| 94 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 95 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 96 | `deleted_at` | `timestamp` | yes | — | `NULL` | — |
| 97 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 98 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### clients_pre_customer_master_20261012

15 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | — | `0` | — |
| 2 | `client_code` | `varchar(50)` | **no** | — | *NULL* | — |
| 3 | `company_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `contact_person` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `email` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `phone` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `address` | `text` | yes | — | `NULL` | — |
| 8 | `country` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `website` | `varchar(255)` | yes | — | `NULL` | — |
| 10 | `gst_vat_number` | `varchar(50)` | yes | — | `NULL` | — |
| 11 | `industry` | `varchar(100)` | yes | — | `NULL` | — |
| 12 | `assigned_manager` | `int(10) unsigned` | yes | — | `NULL` | — |
| 13 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### client_contacts

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete cascade) |
| 3 | `name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `designation` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `email` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `phone` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `is_primary` | `tinyint(1)` | yes | — | `0` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### client_documents

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete cascade) |
| 3 | `uploaded_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `document_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(11)` | yes | — | `NULL` | — |
| 7 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `document_type` | `varchar(50)` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### client_notes

5 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `note_text` | `text` | **no** | — | *NULL* | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### deals

12 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `deal_title` | `varchar(200)` | **no** | — | *NULL* | — |
| 3 | `lead_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | leads.id (on delete set null) |
| 4 | `client_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | clients.id (on delete set null) |
| 5 | `assigned_to` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 6 | `deal_value` | `decimal(15,2)` | yes | — | `NULL` | — |
| 7 | `stage` | `enum('inquiry','qualified','proposal','negotiation','closed_won','closed_lost')` | yes | IDX | `'inquiry'` | — |
| 8 | `expected_closing_date` | `date` | yes | IDX | `NULL` | — |
| 9 | `probability` | `int(11)` | yes | — | `0` | — |
| 10 | `notes` | `text` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### leads

15 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `lead_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `company_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `contact_person` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `email` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `phone` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `country` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `source` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `assigned_to` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 10 | `status` | `enum('new','contacted','qualified','proposal_sent','negotiation','won','lost')` | yes | IDX | `'new'` | — |
| 11 | `priority` | `enum('low','medium','high','critical')` | yes | IDX | `'medium'` | — |
| 12 | `expected_value` | `decimal(15,2)` | yes | — | `NULL` | — |
| 13 | `notes` | `text` | yes | — | `NULL` | — |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## Projects & Tasks


### ai_agent_tasks

24 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `task_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `agent_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_definitions.id (on delete cascade) |
| 4 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 5 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `original_request` | `text` | **no** | — | *NULL* | — |
| 7 | `normalized_intent` | `text` | yes | — | `NULL` | — |
| 8 | `priority` | `enum('low','medium','high','critical')` | **no** | IDX | `'medium'` | — |
| 9 | `status` | `enum('pending','planning','awaiting_confirmation','awaiting_review','running','paused','failed','cancelled','completed','partially_completed')` | **no** | IDX | `'pending'` | — |
| 10 | `current_step` | `int(11)` | **no** | — | `0` | — |
| 11 | `total_steps` | `int(11)` | **no** | — | `0` | — |
| 12 | `progress_pct` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 13 | `result_summary` | `text` | yes | — | `NULL` | — |
| 14 | `error_message` | `text` | yes | — | `NULL` | — |
| 15 | `cancelled_by` | `int(11)` | yes | — | `NULL` | — |
| 16 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `cancellation_reason` | `text` | yes | — | `NULL` | — |
| 18 | `source` | `enum('chat','command_center','dashboard','workflow','scheduler','integration','api')` | **no** | — | `'chat'` | — |
| 19 | `source_id` | `varchar(100)` | yes | — | `NULL` | — |
| 20 | `conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 21 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 22 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 23 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 24 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_task_runs

20 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_tasks.id (on delete cascade) |
| 3 | `plan_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `agent_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_definitions.id (on delete cascade) |
| 5 | `company_id` | `int(11)` | **no** | — | *NULL* | — |
| 6 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 7 | `status` | `enum('queued','running','awaiting_confirmation','awaiting_review','paused','completed','failed','cancelled','limit_reached')` | **no** | IDX | `'queued'` | — |
| 8 | `current_step` | `int(11)` | **no** | — | `0` | — |
| 9 | `total_steps` | `int(11)` | **no** | — | `0` | — |
| 10 | `tool_calls_made` | `int(11)` | **no** | — | `0` | — |
| 11 | `actions_executed` | `int(11)` | **no** | — | `0` | — |
| 12 | `estimated_cost` | `decimal(12,4)` | **no** | — | `0.0000` | — |
| 13 | `actual_cost` | `decimal(12,4)` | **no** | — | `0.0000` | — |
| 14 | `working_memory_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `result_summary` | `text` | yes | — | `NULL` | — |
| 16 | `error_message` | `text` | yes | — | `NULL` | — |
| 17 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 19 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_tasks

18 columns · 15 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `conversation_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `original_request` | `text` | yes | — | `NULL` | — |
| 7 | `task_type` | `enum('manual','automation')` | **no** | — | `'manual'` | — |
| 8 | `status` | `enum('draft','planned','awaiting_confirmation','running','paused','completed','failed','cancelled')` | **no** | IDX | `'draft'` | — |
| 9 | `priority` | `enum('low','normal','high')` | **no** | — | `'normal'` | — |
| 10 | `current_step` | `int(10) unsigned` | yes | — | `0` | — |
| 11 | `total_steps` | `int(10) unsigned` | yes | — | `0` | — |
| 12 | `plan_json` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `result_summary` | `text` | yes | — | `NULL` | — |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 16 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `failed_at` | `datetime` | yes | — | `NULL` | — |

### ai_task_steps

17 columns · 16 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_tasks.id (on delete cascade) |
| 3 | `step_number` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `step_type` | `enum('read','action','reasoning')` | **no** | — | `'read'` | — |
| 7 | `tool_id` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `action_id` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `input_json` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `output_json` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `status` | `enum('pending','running','awaiting_confirmation','completed','failed','skipped','cancelled')` | **no** | IDX | `'pending'` | — |
| 12 | `requires_confirmation` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `error_message` | `text` | yes | — | `NULL` | — |
| 14 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### milestones

8 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `project_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | projects.id (on delete cascade) |
| 3 | `milestone_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `due_date` | `date` | yes | — | `NULL` | — |
| 6 | `status` | `enum('pending','in_progress','completed','cancelled')` | yes | IDX | `'pending'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### projects

13 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `project_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `project_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `client_name` | `varchar(200)` | yes | — | `NULL` | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `start_date` | `date` | yes | — | `NULL` | — |
| 7 | `end_date` | `date` | yes | — | `NULL` | — |
| 8 | `estimated_hours` | `decimal(10,2)` | yes | — | `NULL` | — |
| 9 | `project_manager_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 10 | `status` | `enum('planning','active','on_hold','completed','cancelled')` | yes | IDX | `'planning'` | — |
| 11 | `priority` | `enum('low','medium','high','critical')` | yes | IDX | `'medium'` | — |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### project_members

5 columns · 17 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `project_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | projects.id (on delete cascade) |
| 3 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 4 | `member_role` | `varchar(50)` | yes | — | `'Member'` | — |
| 5 | `assigned_at` | `timestamp` | **no** | — | current_timestamp() | — |

### tasks

14 columns · 13 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `project_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | projects.id (on delete cascade) |
| 3 | `milestone_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | milestones.id (on delete set null) |
| 4 | `assigned_to` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 5 | `task_title` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `task_description` | `text` | yes | — | `NULL` | — |
| 7 | `start_date` | `date` | yes | — | `NULL` | — |
| 8 | `due_date` | `date` | yes | — | `NULL` | — |
| 9 | `estimated_hours` | `decimal(10,2)` | yes | — | `NULL` | — |
| 10 | `status` | `enum('todo','in_progress','review','testing','completed','blocked')` | yes | IDX | `'todo'` | — |
| 11 | `priority` | `enum('low','medium','high','urgent')` | yes | IDX | `'medium'` | — |
| 12 | `progress_percentage` | `int(11)` | yes | — | `0` | — |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 14 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### task_activities

8 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tasks.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `activity_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `field_name` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `old_value` | `text` | yes | — | `NULL` | — |
| 7 | `new_value` | `text` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### task_assignees

4 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tasks.id (on delete cascade) |
| 3 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 4 | `assigned_at` | `timestamp` | **no** | — | current_timestamp() | — |

### task_attachments

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tasks.id (on delete cascade) |
| 3 | `uploaded_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `file_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(11)` | yes | — | `NULL` | — |
| 7 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### task_comments

5 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tasks.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `comment` | `text` | **no** | — | *NULL* | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### task_subtasks

7 columns · 16 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tasks.id (on delete cascade) |
| 3 | `title` | `varchar(500)` | **no** | — | *NULL* | — |
| 4 | `is_completed` | `tinyint(1)` | yes | — | `0` | — |
| 5 | `position` | `int(11)` | yes | — | `0` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `completed_at` | `timestamp` | yes | — | `NULL` | — |

### task_tags

5 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tasks.id (on delete cascade) |
| 3 | `tag_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `tag_color` | `varchar(7)` | yes | — | `'#6366f1'` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

## Inventory & Assets


### inventory_adjustments

35 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `adjustment_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 4 | `warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete restrict) |
| 5 | `location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 6 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete restrict) |
| 7 | `batch_number` | `varchar(80)` | yes | — | `NULL` | — |
| 8 | `serial_number` | `varchar(80)` | yes | — | `NULL` | — |
| 9 | `current_qty` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `counted_qty` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 11 | `variance_qty` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 12 | `adjustment_type` | `enum('gain','loss')` | **no** | — | *NULL* | — |
| 13 | `unit_cost` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 14 | `variance_value` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 15 | `reason` | `varchar(200)` | **no** | — | *NULL* | — |
| 16 | `notes` | `text` | yes | — | `NULL` | — |
| 17 | `attachment_path` | `varchar(255)` | yes | — | `NULL` | — |
| 18 | `status` | `enum('draft','pending_approval','approved','rejected','finalised','cancelled')` | **no** | IDX | `'draft'` | — |
| 19 | `movement_id` | `bigint(20) unsigned` | yes | — | `NULL` | — |
| 20 | `requires_approval` | `tinyint(1)` | **no** | — | `0` | — |
| 21 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 22 | `submitted_at` | `datetime` | yes | — | `NULL` | — |
| 23 | `submitted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 24 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 25 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 26 | `rejected_at` | `datetime` | yes | — | `NULL` | — |
| 27 | `rejected_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 28 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 29 | `finalised_at` | `datetime` | yes | — | `NULL` | — |
| 30 | `finalised_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 31 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 32 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 33 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 34 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 35 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### inventory_adjustment_attachments

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `adjustment_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_adjustments.id (on delete cascade) |
| 3 | `doc_label` | `varchar(100)` | **no** | — | `'Supporting Document'` | — |
| 4 | `original_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `stored_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_size` | `int(10) unsigned` | **no** | — | `0` | — |
| 7 | `uploaded_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### inventory_items

19 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `item_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 3 | `item_code` | `varchar(50)` | yes | UQ | `NULL` | — |
| 4 | `category` | `varchar(100)` | yes | IDX | `NULL` | — |
| 5 | `warehouse_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 6 | `location_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 7 | `uom_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 8 | `costing_method` | `enum('FIFO','LIFO','AVG','STD','MA')` | yes | — | `'AVG'` | — |
| 9 | `reorder_point` | `int(10) unsigned` | yes | — | `0` | — |
| 10 | `is_serial_required` | `tinyint(1)` | **no** | — | `0` | — |
| 11 | `is_batch_required` | `tinyint(1)` | **no** | — | `0` | — |
| 12 | `quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 13 | `min_quantity` | `int(10) unsigned` | yes | — | `5` | — |
| 14 | `unit_price` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 15 | `vendor_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | vendors.id (on delete set null) |
| 16 | `description` | `text` | yes | — | `NULL` | — |
| 17 | `status` | `enum('active','inactive')` | yes | — | `'active'` | — |
| 18 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### inventory_logs

8 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete cascade) |
| 3 | `movement_type` | `enum('in','out','adjustment')` | **no** | IDX | *NULL* | — |
| 4 | `quantity` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `reference_number` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `notes` | `text` | yes | — | `NULL` | — |
| 7 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 8 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### inventory_reconciliations

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete cascade) |
| 3 | `warehouse_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | warehouses.id (on delete set null) |
| 4 | `reconciliation_date` | `date` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('draft','in_progress','completed','cancelled')` | **no** | IDX | `'draft'` | — |
| 6 | `total_items` | `int(10) unsigned` | yes | — | `0` | — |
| 7 | `matched_items` | `int(10) unsigned` | yes | — | `0` | — |
| 8 | `discrepancy_items` | `int(10) unsigned` | yes | — | `0` | — |
| 9 | `total_ledger_value` | `decimal(18,4)` | yes | — | `0.0000` | — |
| 10 | `total_physical_value` | `decimal(18,4)` | yes | — | `0.0000` | — |
| 11 | `difference_value` | `decimal(18,4)` | yes | — | `0.0000` | — |
| 12 | `notes` | `text` | yes | — | `NULL` | — |
| 13 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `completed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 16 | `completed_at` | `datetime` | yes | — | `NULL` | — |

### inventory_reconciliation_items

17 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `reconciliation_id` | `bigint(20) unsigned` | **no** | IDX FK | *NULL* | inventory_reconciliations.id (on delete cascade) |
| 3 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete cascade) |
| 4 | `warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete cascade) |
| 5 | `location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 6 | `ledger_qty` | `decimal(18,3)` | **no** | — | *NULL* | — |
| 7 | `physical_qty` | `decimal(18,3)` | yes | — | `NULL` | — |
| 8 | `reserved_qty` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 9 | `available_qty` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `ledger_value` | `decimal(18,4)` | **no** | — | *NULL* | — |
| 11 | `physical_value` | `decimal(18,4)` | yes | — | `NULL` | — |
| 12 | `unit_cost` | `decimal(18,4)` | **no** | — | *NULL* | — |
| 13 | `discrepancy_qty` | `decimal(18,3)` | yes | — | `0.000` | — |
| 14 | `discrepancy_value` | `decimal(18,4)` | yes | — | `0.0000` | — |
| 15 | `discrepancy_reason` | `enum('none','count_error','damage','theft','data_error','other')` | yes | — | `'none'` | — |
| 16 | `adjustment_id` | `bigint(20) unsigned` | yes | — | `NULL` | linked adjustment if reconciled |
| 17 | `notes` | `text` | yes | — | `NULL` | — |

### inventory_reservations

15 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete cascade) |
| 3 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete cascade) |
| 4 | `warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete cascade) |
| 5 | `location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 6 | `quantity` | `decimal(18,3)` | **no** | — | *NULL* | — |
| 7 | `reference_type` | `varchar(50)` | yes | IDX | `NULL` | e.g. sales_order, work_order |
| 8 | `reference_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `reference_number` | `varchar(80)` | yes | — | `NULL` | — |
| 10 | `status` | `enum('reserved','released','consumed','cancelled')` | **no** | IDX | `'reserved'` | — |
| 11 | `notes` | `text` | yes | — | `NULL` | — |
| 12 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 14 | `released_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 15 | `released_at` | `datetime` | yes | — | `NULL` | — |

### inventory_sequences

4 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `seq_type` | `varchar(30)` | **no** | IDX | *NULL* | — |
| 3 | `seq_year` | `smallint(5) unsigned` | **no** | — | *NULL* | — |
| 4 | `last_number` | `int(10) unsigned` | **no** | — | `0` | — |

### inventory_settings

13 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `allow_negative_stock` | `tinyint(1)` | **no** | — | `0` | — |
| 3 | `default_costing_method` | `enum('FIFO','LIFO','AVG','STD','MA')` | **no** | — | `'AVG'` | — |
| 4 | `default_warehouse_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `default_location_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `inventory_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | chart_of_accounts.id (on delete set null) · GL account for inventory asset |
| 7 | `cogs_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | chart_of_accounts.id (on delete set null) · GL account for cost of goods sold |
| 8 | `adjustment_account_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | chart_of_accounts.id (on delete set null) · GL account for inventory adjustments |
| 9 | `stock_movement_prefix` | `varchar(20)` | **no** | — | `'SM'` | — |
| 10 | `require_adj_approval` | `tinyint(1)` | **no** | — | `0` | — |
| 11 | `require_transfer_approval` | `tinyint(1)` | **no** | — | `0` | — |
| 12 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 13 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### inventory_stock_balances

11 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 3 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete restrict) |
| 4 | `warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete restrict) |
| 5 | `location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 6 | `qty_on_hand` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 7 | `qty_reserved` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 8 | `avg_unit_cost` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 9 | `stock_value` | `decimal(18,4)` | **no** | IDX | `0.0000` | — |
| 10 | `last_movement_id` | `bigint(20) unsigned` | yes | — | `NULL` | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### inventory_stock_transfers

30 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `transfer_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 4 | `source_warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete restrict) |
| 5 | `source_location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 6 | `dest_warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete restrict) |
| 7 | `dest_location_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | locations.id (on delete set null) |
| 8 | `item_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | inventory_items.id (on delete restrict) |
| 9 | `quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `unit_cost` | `decimal(18,4)` | **no** | — | `0.0000` | — |
| 11 | `notes` | `text` | yes | — | `NULL` | — |
| 12 | `transfer_reason` | `varchar(200)` | yes | — | `NULL` | — |
| 13 | `status` | `enum('draft','pending_approval','approved','posted','rejected','cancelled')` | **no** | IDX | `'draft'` | — |
| 14 | `movement_out_id` | `bigint(20) unsigned` | yes | — | `NULL` | — |
| 15 | `movement_in_id` | `bigint(20) unsigned` | yes | — | `NULL` | — |
| 16 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 17 | `submitted_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `submitted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 19 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 20 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 21 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 22 | `posted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 23 | `rejected_at` | `datetime` | yes | — | `NULL` | — |
| 24 | `rejected_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 25 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 26 | `cancelled_at` | `datetime` | yes | — | `NULL` | — |
| 27 | `cancelled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 28 | `cancel_reason` | `text` | yes | — | `NULL` | — |
| 29 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 30 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### warehouses

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete restrict) |
| 3 | `warehouse_code` | `varchar(30)` | **no** | IDX | *NULL* | — |
| 4 | `warehouse_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `address` | `text` | yes | — | `NULL` | — |
| 6 | `manager_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `status` | `enum('active','inactive')` | **no** | IDX | `'active'` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## Helpdesk


### knowledge_base_articles

11 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `category_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | knowledge_base_categories.id (on delete set null) |
| 3 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `slug` | `varchar(255)` | **no** | UQ | *NULL* | — |
| 5 | `content` | `text` | **no** | — | *NULL* | — |
| 6 | `tags` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `status` | `enum('draft','published','archived')` | yes | IDX | `'draft'` | — |
| 8 | `created_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 9 | `views` | `int(10) unsigned` | yes | — | `0` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### knowledge_base_categories

8 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `slug` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `icon` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### slas

9 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `sla_name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `response_time_hours` | `int(11)` | **no** | — | `24` | — |
| 4 | `resolution_time_hours` | `int(11)` | **no** | — | `72` | — |
| 5 | `priority` | `enum('low','medium','high','critical')` | yes | IDX | `'medium'` | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### tickets

19 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `ticket_number` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 3 | `client_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | clients.id (on delete set null) |
| 4 | `employee_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 5 | `category_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | ticket_categories.id (on delete set null) |
| 6 | `sla_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | slas.id (on delete set null) |
| 7 | `subject` | `varchar(255)` | **no** | — | *NULL* | — |
| 8 | `description` | `text` | **no** | — | *NULL* | — |
| 9 | `priority` | `enum('low','medium','high','critical')` | yes | IDX | `'medium'` | — |
| 10 | `status` | `enum('open','in_progress','waiting_client','resolved','closed','reopened')` | yes | IDX | `'open'` | — |
| 11 | `source` | `enum('web','email','phone','internal')` | yes | — | `'web'` | — |
| 12 | `assigned_to` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 13 | `due_date` | `datetime` | yes | IDX | `NULL` | — |
| 14 | `first_response_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `resolved_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `closed_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `sla_breached` | `tinyint(1)` | yes | — | `0` | — |
| 18 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ticket_assignments

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `ticket_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tickets.id (on delete cascade) |
| 3 | `assigned_from` | `int(10) unsigned` | yes | IDX FK | `NULL` | employees.id (on delete set null) |
| 4 | `assigned_to` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 5 | `assigned_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 6 | `reason` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ticket_attachments

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `ticket_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tickets.id (on delete cascade) |
| 3 | `reply_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | ticket_replies.id (on delete set null) |
| 4 | `uploaded_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 5 | `file_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `file_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 7 | `file_size` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ticket_categories

8 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `category_name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `category_code` | `varchar(50)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `priority_default` | `enum('low','medium','high','critical')` | yes | — | `'medium'` | — |
| 6 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ticket_internal_notes

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `ticket_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tickets.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `note` | `text` | **no** | — | *NULL* | — |
| 5 | `mentioned_user_ids` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ticket_replies

7 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `ticket_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | tickets.id (on delete cascade) |
| 3 | `replied_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 4 | `reply_type` | `enum('public','internal')` | yes | IDX | `'public'` | — |
| 5 | `message` | `text` | **no** | — | *NULL* | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## AI module


### ai_agents

22 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `agent_id` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `agent_type` | `enum('specialist','general','monitoring','automation')` | **no** | IDX | `'specialist'` | — |
| 4 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `module` | `varchar(50)` | yes | IDX | `NULL` | — |
| 7 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 8 | `configuration_json` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `allowed_tools_json` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `allowed_actions_json` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `allowed_knowledge_json` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `max_steps` | `int(10) unsigned` | **no** | — | `10` | — |
| 13 | `max_tool_calls` | `int(10) unsigned` | **no** | — | `20` | — |
| 14 | `max_runtime_seconds` | `int(10) unsigned` | **no** | — | `120` | — |
| 15 | `max_working_memory_chars` | `int(10) unsigned` | **no** | — | `8000` | — |
| 16 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 17 | `required_permissions_json` | `longtext` | yes | — | `NULL` | binary collation |
| 18 | `system_instructions` | `text` | yes | — | `NULL` | — |
| 19 | `human_confirmation_policy` | `enum('always','write_only','never')` | **no** | — | `'write_only'` | — |
| 20 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 21 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 22 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_approvals

21 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `approval_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `task_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_tasks.id (on delete cascade) |
| 4 | `run_id` | `int(11)` | yes | IDX FK | `NULL` | ai_agent_task_runs.id (on delete set null) |
| 5 | `plan_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `step_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 8 | `requesting_user_id` | `int(11)` | **no** | — | *NULL* | — |
| 9 | `action_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 10 | `action_parameters_json` | `longtext` | **no** | — | *NULL* | — |
| 11 | `parameters_hash` | `varchar(255)` | **no** | — | *NULL* | — |
| 12 | `affected_records_json` | `longtext` | yes | — | `NULL` | — |
| 13 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | *NULL* | — |
| 14 | `status` | `enum('pending','approved','rejected','expired','cancelled','delegated')` | **no** | IDX | `'pending'` | — |
| 15 | `approver_id` | `int(11)` | yes | IDX | `NULL` | — |
| 16 | `approval_decision` | `text` | yes | — | `NULL` | — |
| 17 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `expires_at` | `datetime` | **no** | IDX | *NULL* | — |
| 19 | `delegation_user_id` | `int(11)` | yes | — | `NULL` | — |
| 20 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 21 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_artifacts

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `artifact_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `task_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_tasks.id (on delete cascade) |
| 4 | `run_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 7 | `artifact_type` | `enum('report','draft','summary','document','structured_data','file')` | **no** | IDX | *NULL* | — |
| 8 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 9 | `content` | `longtext` | yes | — | `NULL` | — |
| 10 | `content_type` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `file_path` | `varchar(500)` | yes | — | `NULL` | — |
| 12 | `classification` | `enum('public','internal','confidential','sensitive','restricted')` | **no** | — | `'internal'` | — |
| 13 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 14 | `source_step_number` | `int(11)` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_checkpoints

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_task_runs.id (on delete cascade) |
| 3 | `task_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_tasks.id (on delete cascade) |
| 4 | `step_number` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `checkpoint_type` | `enum('auto','manual','approval_required','error','limit_reached')` | **no** | — | `'auto'` | — |
| 6 | `state_json` | `longtext` | **no** | — | *NULL* | — |
| 7 | `completed_steps_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `working_memory_snapshot` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `pending_approvals_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `resume_from_step` | `int(11)` | **no** | — | *NULL* | — |
| 11 | `can_resume` | `tinyint(1)` | **no** | IDX | `1` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_agent_definitions

33 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `agent_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `purpose` | `text` | yes | — | `NULL` | — |
| 6 | `agent_type` | `enum('general','specialist','autonomous','workflow')` | **no** | IDX | `'specialist'` | — |
| 7 | `company_id` | `int(11)` | yes | IDX | `NULL` | — |
| 8 | `is_enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 9 | `allowed_tools_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `allowed_actions_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `allowed_skills_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `allowed_integrations_json` | `longtext` | yes | — | `NULL` | — |
| 13 | `allowed_providers_json` | `longtext` | yes | — | `NULL` | — |
| 14 | `allowed_models_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `autonomy_level` | `enum('assist','suggest','confirm','supervised','restricted')` | **no** | — | `'confirm'` | — |
| 16 | `max_steps` | `int(11)` | **no** | — | `20` | — |
| 17 | `max_runtime_seconds` | `int(11)` | **no** | — | `300` | — |
| 18 | `max_tool_calls` | `int(11)` | **no** | — | `30` | — |
| 19 | `max_cost` | `decimal(12,4)` | **no** | — | `10.0000` | — |
| 20 | `max_records_affected` | `int(11)` | **no** | — | `100` | — |
| 21 | `retry_limit` | `int(11)` | **no** | — | `3` | — |
| 22 | `approval_required_for_writes` | `tinyint(1)` | **no** | — | `1` | — |
| 23 | `approval_required_for_high_risk` | `tinyint(1)` | **no** | — | `1` | — |
| 24 | `allowed_departments_json` | `longtext` | yes | — | `NULL` | — |
| 25 | `allowed_roles_json` | `longtext` | yes | — | `NULL` | — |
| 26 | `allowed_users_json` | `longtext` | yes | — | `NULL` | — |
| 27 | `task_memory_policy` | `enum('full','summary','none')` | **no** | — | `'summary'` | — |
| 28 | `system_instructions` | `text` | yes | — | `NULL` | — |
| 29 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 30 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 31 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 32 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 33 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_events

14 columns · 18 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(11)` | yes | IDX FK | `NULL` | ai_agent_tasks.id (on delete set null) |
| 3 | `run_id` | `int(11)` | yes | IDX FK | `NULL` | ai_agent_task_runs.id (on delete set null) |
| 4 | `step_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `user_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `event_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 8 | `event_category` | `varchar(50)` | **no** | — | *NULL* | — |
| 9 | `severity` | `enum('info','warning','high','critical')` | **no** | IDX | `'info'` | — |
| 10 | `message` | `text` | yes | — | `NULL` | — |
| 11 | `details_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 13 | `request_id` | `int(11)` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_agent_plans

19 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `task_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_tasks.id (on delete cascade) |
| 3 | `plan_version` | `int(11)` | **no** | — | `1` | — |
| 4 | `plan_json` | `longtext` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('draft','validated','approved','executing','completed','failed','cancelled')` | **no** | IDX | `'draft'` | — |
| 6 | `estimated_cost` | `decimal(12,4)` | yes | — | `NULL` | — |
| 7 | `estimated_duration_seconds` | `int(11)` | yes | — | `NULL` | — |
| 8 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'low'` | — |
| 9 | `external_systems_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `requires_approvals` | `tinyint(1)` | **no** | — | `0` | — |
| 11 | `approval_steps_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `validated_by_governance` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `validation_result_json` | `longtext` | yes | — | `NULL` | — |
| 14 | `dry_run` | `tinyint(1)` | **no** | — | `0` | — |
| 15 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 16 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 17 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_plan_steps

22 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `plan_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_plans.id (on delete cascade) |
| 3 | `step_number` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `step_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `objective` | `text` | **no** | — | *NULL* | — |
| 6 | `step_type` | `enum('tool','action','knowledge','reasoning','navigation','verification','checkpoint')` | **no** | — | *NULL* | — |
| 7 | `operation_id` | `varchar(150)` | yes | — | `NULL` | — |
| 8 | `input_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `expected_result` | `text` | yes | — | `NULL` | — |
| 10 | `dependency_step_number` | `int(11)` | yes | — | `NULL` | — |
| 11 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'low'` | — |
| 12 | `requires_approval` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `status` | `enum('pending','running','completed','failed','skipped','cancelled','awaiting_approval')` | **no** | IDX | `'pending'` | — |
| 14 | `retry_count` | `int(11)` | **no** | — | `0` | — |
| 15 | `max_retries` | `int(11)` | **no** | — | `2` | — |
| 16 | `execution_result_json` | `longtext` | yes | — | `NULL` | — |
| 17 | `verification_result_json` | `longtext` | yes | — | `NULL` | — |
| 18 | `error_message` | `text` | yes | — | `NULL` | — |
| 19 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 20 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 21 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 22 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_agent_runs

19 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `agent_id` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `conversation_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `objective` | `text` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('queued','planning','running','awaiting_confirmation','paused','completed','failed','cancelled','limit_reached')` | **no** | IDX | `'queued'` | — |
| 7 | `current_step` | `int(10) unsigned` | yes | — | `0` | — |
| 8 | `total_steps` | `int(10) unsigned` | yes | — | `0` | — |
| 9 | `tool_calls_made` | `int(10) unsigned` | yes | — | `0` | — |
| 10 | `reasoning_cycles` | `int(10) unsigned` | yes | — | `0` | — |
| 11 | `working_memory_json` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `plan_json` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `result_summary` | `text` | yes | — | `NULL` | — |
| 14 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 15 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `failed_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_agent_steps

13 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `agent_run_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_agent_runs.id (on delete cascade) |
| 3 | `step_number` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `step_type` | `enum('tool','action','reasoning','knowledge','navigation')` | **no** | — | *NULL* | — |
| 5 | `operation_id` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `input_json` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `observation_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `status` | `enum('pending','running','awaiting_confirmation','completed','failed','skipped','cancelled')` | **no** | IDX | `'pending'` | — |
| 9 | `duration_ms` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `error_message` | `text` | yes | — | `NULL` | — |
| 11 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_agent_step_executions

26 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_task_runs.id (on delete cascade) |
| 3 | `task_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_agent_tasks.id (on delete cascade) |
| 4 | `step_number` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `step_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 6 | `operation_id` | `varchar(150)` | yes | — | `NULL` | — |
| 7 | `input_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `output_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `status` | `enum('pending','running','completed','failed','skipped','cancelled','awaiting_approval')` | **no** | IDX | `'pending'` | — |
| 10 | `governance_decision` | `varchar(50)` | yes | — | `NULL` | — |
| 11 | `governance_details_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `risk_level` | `varchar(20)` | yes | — | `NULL` | — |
| 13 | `requires_approval` | `tinyint(1)` | **no** | — | `0` | — |
| 14 | `approval_id` | `int(11)` | yes | — | `NULL` | — |
| 15 | `error_message` | `text` | yes | — | `NULL` | — |
| 16 | `retry_count` | `int(11)` | **no** | — | `0` | — |
| 17 | `verification_status` | `varchar(50)` | yes | — | `NULL` | — |
| 18 | `verification_details_json` | `longtext` | yes | — | `NULL` | — |
| 19 | `token_input` | `int(11)` | yes | — | `NULL` | — |
| 20 | `token_output` | `int(11)` | yes | — | `NULL` | — |
| 21 | `estimated_cost` | `decimal(12,4)` | yes | — | `NULL` | — |
| 22 | `actual_cost` | `decimal(12,4)` | yes | — | `NULL` | — |
| 23 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 24 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 25 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 26 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_analytics_runs

23 columns · 108 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `session_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `request_text` | `text` | **no** | — | *NULL* | — |
| 6 | `metric_key` | `varchar(100)` | yes | IDX | `NULL` | — |
| 7 | `dimensions` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `filters` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `operation_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 10 | `query_definition` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `execution_time_ms` | `int(11)` | yes | — | `NULL` | — |
| 12 | `data_volume_rows` | `int(11)` | yes | — | `NULL` | — |
| 13 | `data_volume_bytes` | `int(11)` | yes | — | `NULL` | — |
| 14 | `provider_used` | `varchar(100)` | yes | — | `NULL` | — |
| 15 | `model_used` | `varchar(100)` | yes | — | `NULL` | — |
| 16 | `forecast_method` | `varchar(50)` | yes | — | `NULL` | — |
| 17 | `estimated_cost` | `decimal(10,6)` | yes | — | `NULL` | — |
| 18 | `result_summary` | `text` | yes | — | `NULL` | — |
| 19 | `result_json` | `longtext` | yes | — | `NULL` | binary collation |
| 20 | `governance_decision` | `varchar(50)` | yes | — | `NULL` | — |
| 21 | `risk_level` | `varchar(20)` | yes | — | `NULL` | — |
| 22 | `feedback` | `enum('helpful','not_helpful','incorrect','incomplete')` | yes | — | `NULL` | — |
| 23 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_audit_log

8 columns · 1,001 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 3 | `action` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `entity_type` | `varchar(50)` | yes | IDX | `NULL` | provider|model|agent|knowledge|tool |
| 5 | `entity_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `details` | `text` | yes | — | `NULL` | — |
| 7 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_automations

20 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 3 | `description` | `text` | yes | — | `NULL` | — |
| 4 | `created_by` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 5 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 6 | `trigger_type` | `enum('manual','scheduled','event')` | **no** | — | `'manual'` | — |
| 7 | `schedule_type` | `enum('none','interval','daily','weekly')` | yes | — | `'none'` | — |
| 8 | `schedule_config_json` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `schedule_timezone` | `varchar(50)` | yes | — | `'UTC'` | — |
| 10 | `trigger_config_json` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `workflow_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 12 | `last_run_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `next_run_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `safety_classification` | `enum('read_only','confirmation_required','blocked')` | yes | — | `'read_only'` | — |
| 15 | `max_retries` | `int(10) unsigned` | yes | — | `3` | — |
| 16 | `retry_delay_seconds` | `int(10) unsigned` | yes | — | `60` | — |
| 17 | `max_concurrent_runs` | `int(10) unsigned` | yes | — | `1` | — |
| 18 | `retry_count` | `int(10) unsigned` | yes | — | `0` | — |
| 19 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_automation_notifications

9 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `automation_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 3 | `run_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 5 | `notification_type` | `enum('in_app','email','webhook')` | **no** | — | `'in_app'` | — |
| 6 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 7 | `message` | `text` | yes | — | `NULL` | — |
| 8 | `read_at` | `datetime` | yes | IDX | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_automation_runs

16 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `automation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_automations.id (on delete cascade) |
| 3 | `task_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `status` | `enum('running','completed','failed','cancelled')` | **no** | IDX | `'running'` | — |
| 5 | `trigger_type` | `enum('manual','scheduled','event')` | yes | — | `'manual'` | — |
| 6 | `event_name` | `varchar(150)` | yes | — | `NULL` | — |
| 7 | `event_record_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `lock_key` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `retry_attempt` | `int(10) unsigned` | yes | — | `0` | — |
| 10 | `notification_sent` | `tinyint(1)` | yes | — | `0` | — |
| 11 | `started_at` | `datetime` | **no** | — | *NULL* | — |
| 12 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `result_json` | `longtext` | yes | — | `NULL` | binary collation |
| 14 | `error_message` | `text` | yes | — | `NULL` | — |
| 15 | `triggered_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_automation_triggers

7 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `automation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_automations.id (on delete cascade) |
| 3 | `trigger_type` | `enum('manual','scheduled','event')` | **no** | IDX | `'manual'` | — |
| 4 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 5 | `config_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_autonomy_settings

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `role_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 5 | `autonomy_level` | `enum('assist','suggest','confirm','supervised','restricted')` | **no** | — | `'suggest'` | — |
| 6 | `max_steps_per_task` | `int(11)` | **no** | — | `20` | — |
| 7 | `max_tool_calls_per_run` | `int(11)` | **no** | — | `20` | — |
| 8 | `max_runtime_seconds` | `int(11)` | **no** | — | `120` | — |
| 9 | `max_cost_per_day` | `decimal(12,2)` | **no** | — | `10.00` | — |
| 10 | `max_records_per_action` | `int(11)` | **no** | — | `100` | — |
| 11 | `allowed_risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 12 | `require_confirmation_for_writes` | `tinyint(1)` | **no** | — | `1` | — |
| 13 | `require_review_for_high_risk` | `tinyint(1)` | **no** | — | `1` | — |
| 14 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_bulk_action_thresholds

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `action_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `threshold` | `int(11)` | **no** | — | `100` | — |
| 4 | `required_action` | `enum('confirm','review','deny')` | **no** | — | `'review'` | — |
| 5 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_command_conversations

10 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 4 | `title` | `varchar(255)` | yes | — | `NULL` | — |
| 5 | `context_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `status` | `enum('active','archived')` | **no** | IDX | `'active'` | — |
| 7 | `started_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `last_message_at` | `timestamp` | **no** | IDX | current_timestamp() | on update current_timestamp() |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_command_executions

17 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `conversation_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | ai_command_conversations.id (on delete set null) |
| 3 | `message_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 5 | `company_id` | `int(10) unsigned` | **no** | — | `1` | — |
| 6 | `command_summary` | `varchar(500)` | yes | — | `NULL` | — |
| 7 | `intent` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 8 | `capability_chain` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `status` | `enum('running','completed','failed','cancelled')` | **no** | IDX | `'running'` | — |
| 10 | `result_summary` | `text` | yes | — | `NULL` | — |
| 11 | `error_message` | `text` | yes | — | `NULL` | — |
| 12 | `duration_ms` | `int(10) unsigned` | yes | — | `0` | — |
| 13 | `tools_used` | `longtext` | yes | — | `NULL` | binary collation |
| 14 | `records_affected` | `int(10) unsigned` | yes | — | `0` | — |
| 15 | `started_at` | `timestamp` | **no** | IDX | current_timestamp() | — |
| 16 | `completed_at` | `timestamp` | yes | — | `NULL` | — |
| 17 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_command_messages

10 columns · 22 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `conversation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_command_conversations.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `role` | `enum('user','assistant','system')` | **no** | IDX | *NULL* | — |
| 5 | `content` | `text` | **no** | — | *NULL* | — |
| 6 | `intent` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `capabilities_used` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `response_type` | `varchar(50)` | yes | — | `NULL` | — |
| 9 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_command_suggestions

9 columns · 49 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `module` | `varchar(50)` | yes | IDX | `NULL` | — |
| 3 | `category` | `varchar(50)` | **no** | — | `'general'` | — |
| 4 | `suggestion_text` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `intent_hint` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `required_permission` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 8 | `sort_order` | `int(10) unsigned` | yes | — | `0` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_communication_actions

25 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `action_key` | `varchar(64)` | **no** | — | *NULL* | — |
| 5 | `action_type` | `varchar(50)` | **no** | — | *NULL* | send,draft,bulk_draft,rule_execute,template_activate,follow_up_create |
| 6 | `draft_id` | `int(11)` | yes | IDX | `NULL` | — |
| 7 | `template_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `followup_id` | `int(11)` | yes | — | `NULL` | — |
| 9 | `task_id` | `int(11)` | yes | — | `NULL` | — |
| 10 | `status` | `varchar(30)` | yes | IDX | `'pending'` | pending,approved,sending,sent,failed,cancelled,rejected |
| 11 | `risk_level` | `varchar(20)` | yes | — | `'low'` | — |
| 12 | `approval_required` | `tinyint(1)` | yes | — | `0` | — |
| 13 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `recipient_count` | `int(11)` | yes | — | `1` | — |
| 16 | `error_message` | `text` | yes | — | `NULL` | — |
| 17 | `governance_decision` | `longtext` | yes | — | `NULL` | binary collation |
| 18 | `confirmation_token` | `varchar(64)` | yes | IDX | `NULL` | — |
| 19 | `confirmation_parameters_hash` | `varchar(64)` | yes | — | `NULL` | — |
| 20 | `confirmed_at` | `datetime` | yes | — | `NULL` | — |
| 21 | `confirmed_by` | `int(11)` | yes | — | `NULL` | — |
| 22 | `sent_at` | `datetime` | yes | — | `NULL` | — |
| 23 | `external_ids` | `longtext` | yes | — | `NULL` | binary collation |
| 24 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 25 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_communication_batch_jobs

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `job_key` | `varchar(64)` | **no** | — | *NULL* | — |
| 5 | `job_type` | `varchar(50)` | **no** | — | *NULL* | bulk_draft,bulk_classify,bulk_followup |
| 6 | `criteria` | `longtext` | **no** | — | *NULL* | selection criteria · binary collation |
| 7 | `total_records` | `int(11)` | yes | — | `0` | — |
| 8 | `processed_records` | `int(11)` | yes | — | `0` | — |
| 9 | `success_records` | `int(11)` | yes | — | `0` | — |
| 10 | `failed_records` | `int(11)` | yes | — | `0` | — |
| 11 | `preview_drafts` | `longtext` | yes | — | `NULL` | sample drafts for review · binary collation |
| 12 | `status` | `varchar(30)` | yes | — | `'pending'` | pending,previewing,approved,running,completed,failed,cancelled |
| 13 | `approval_required` | `tinyint(1)` | yes | — | `1` | — |
| 14 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `error_message` | `text` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_communication_classifications

17 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `communication_id` | `int(11)` | yes | IDX | `NULL` | reference to ERP communication record |
| 4 | `communication_type` | `varchar(50)` | yes | — | `NULL` | — |
| 5 | `message_text` | `longtext` | yes | — | `NULL` | — |
| 6 | `classification` | `varchar(50)` | **no** | — | *NULL* | inquiry,complaint,payment,delivery,quotation,support,sales,procurement,approval,urgent,informational,spam |
| 7 | `confidence` | `decimal(5,4)` | **no** | — | `0.5000` | — |
| 8 | `sub_classification` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `priority` | `varchar(20)` | yes | IDX | `'normal'` | low,normal,high,urgent |
| 10 | `priority_reason` | `text` | yes | — | `NULL` | — |
| 11 | `priority_evidence` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `auto_classified` | `tinyint(1)` | yes | — | `1` | — |
| 13 | `corrected_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `corrected_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `correction_reason` | `text` | yes | — | `NULL` | — |
| 16 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_communication_context

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `draft_id` | `int(11)` | yes | IDX FK | `NULL` | ai_communication_drafts.id (on delete cascade) |
| 4 | `session_id` | `varchar(64)` | yes | IDX | `NULL` | — |
| 5 | `context_type` | `varchar(50)` | **no** | IDX | *NULL* | erp_record,communication_history,document,intelligence,workflow |
| 6 | `context_key` | `varchar(255)` | **no** | — | *NULL* | — |
| 7 | `context_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `context_data` | `longtext` | **no** | — | *NULL* | assembled context with F24 filtered data · binary collation |
| 9 | `data_classification` | `varchar(30)` | yes | — | `'internal'` | public,internal,confidential,restricted |
| 10 | `permission_required` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `included_by_user` | `tinyint(1)` | yes | — | `0` | — |
| 12 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_communication_drafts

38 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `draft_key` | `varchar(64)` | **no** | IDX | *NULL* | — |
| 5 | `context_type` | `varchar(50)` | **no** | IDX | *NULL* | customer,supplier,employee,invoice,po,quotation,ticket,document,manual |
| 6 | `context_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `communication_type` | `varchar(50)` | **no** | — | *NULL* | email,internal_message,customer_reply,supplier_reply,notice,follow_up,reminder,meeting_summary,escalation,approval_request,business_letter |
| 8 | `subject` | `varchar(500)` | yes | — | `NULL` | — |
| 9 | `recipient` | `varchar(500)` | yes | — | `NULL` | — |
| 10 | `recipient_type` | `varchar(50)` | yes | — | `NULL` | customer,supplier,employee,internal |
| 11 | `recipient_email` | `varchar(255)` | yes | — | `NULL` | — |
| 12 | `cc` | `varchar(500)` | yes | — | `NULL` | — |
| 13 | `tone` | `varchar(50)` | yes | — | `'professional'` | — |
| 14 | `formality` | `varchar(50)` | yes | — | `'standard'` | — |
| 15 | `language` | `varchar(10)` | yes | — | `'en'` | — |
| 16 | `length` | `varchar(20)` | yes | — | `'standard'` | — |
| 17 | `urgency` | `varchar(20)` | yes | — | `'normal'` | — |
| 18 | `body` | `longtext` | **no** | — | *NULL* | — |
| 19 | `body_html` | `longtext` | yes | — | `NULL` | — |
| 20 | `plain_text` | `longtext` | yes | — | `NULL` | — |
| 21 | `verified_facts` | `longtext` | yes | — | `NULL` | ERP facts used in draft · binary collation |
| 22 | `source_references` | `longtext` | yes | — | `NULL` | source records referenced · binary collation |
| 23 | `placeholders` | `longtext` | yes | — | `NULL` | missing info placeholders · binary collation |
| 24 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 25 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 26 | `tokens_used` | `int(11)` | yes | — | `0` | — |
| 27 | `cost_usd` | `decimal(10,6)` | yes | — | `0.000000` | — |
| 28 | `status` | `varchar(30)` | yes | IDX | `'draft'` | draft,editing,sent,failed,cancelled |
| 29 | `risk_level` | `varchar(20)` | yes | — | `'low'` | low,medium,high,critical |
| 30 | `approval_required` | `tinyint(1)` | yes | — | `0` | — |
| 31 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 32 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 33 | `send_status` | `varchar(30)` | yes | — | `NULL` | pending,sending,sent,delivery_failed,bounced |
| 34 | `sent_at` | `datetime` | yes | — | `NULL` | — |
| 35 | `external_message_id` | `varchar(255)` | yes | — | `NULL` | — |
| 36 | `created_at` | `datetime` | yes | IDX | current_timestamp() | — |
| 37 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |
| 38 | `deleted_at` | `datetime` | yes | — | `NULL` | — |

### ai_communication_events

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `event_type` | `varchar(50)` | **no** | — | *NULL* | draft_created,draft_edited,draft_sent,classify,translate,summarize,followup_detected,template_activated,rule_executed,approval_requested,approval_granted,approval_denied,send_attempt,send_success,send_failed,feedback |
| 4 | `entity_type` | `varchar(50)` | yes | IDX | `NULL` | draft,template,followup,classification,summary |
| 5 | `entity_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 7 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `tokens_used` | `int(11)` | yes | — | `0` | — |
| 10 | `cost_usd` | `decimal(10,6)` | yes | — | `0.000000` | — |
| 11 | `latency_ms` | `int(11)` | yes | — | `0` | — |
| 12 | `context_sources` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `governance_decision` | `longtext` | yes | — | `NULL` | binary collation |
| 14 | `metadata` | `longtext` | yes | — | `NULL` | binary collation |
| 15 | `created_at` | `datetime` | yes | IDX | current_timestamp() | — |

### ai_communication_followups

17 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `followup_key` | `varchar(64)` | **no** | — | *NULL* | — |
| 5 | `context_type` | `varchar(50)` | **no** | IDX | *NULL* | unanswered_message,overdue_commitment,pending_quotation,overdue_invoice,unresolved_ticket,supplier_delay |
| 6 | `context_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `context_data` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `reason` | `text` | **no** | — | *NULL* | — |
| 9 | `evidence` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `suggested_action` | `varchar(50)` | yes | — | `'draft'` | draft,schedule,task,ignore,snooze |
| 11 | `status` | `varchar(30)` | yes | — | `'detected'` | detected,drafting,scheduled,tasked,ignored,snoozed,completed |
| 12 | `snoozed_until` | `datetime` | yes | — | `NULL` | — |
| 13 | `draft_id` | `int(11)` | yes | — | `NULL` | — |
| 14 | `task_id` | `int(11)` | yes | — | `NULL` | — |
| 15 | `priority` | `varchar(20)` | yes | — | `'normal'` | — |
| 16 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 17 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_communication_rules

19 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `rule_key` | `varchar(64)` | **no** | — | *NULL* | — |
| 4 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `rule_type` | `varchar(50)` | **no** | — | *NULL* | follow_up,escalate,notify,draft,task,flag |
| 7 | `trigger_condition` | `varchar(100)` | **no** | IDX | *NULL* | unanswered_days,overdue_days,no_response,pending_quota,inactive |
| 8 | `trigger_value` | `int(11)` | **no** | — | `7` | — |
| 9 | `target_context` | `varchar(50)` | **no** | — | *NULL* | customer,supplier,invoice,po,quotation,ticket,all |
| 10 | `action_type` | `varchar(50)` | **no** | — | *NULL* | draft_followup,create_task,notify_owner,escalate,flag_urgent |
| 11 | `action_config` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `enabled` | `tinyint(1)` | yes | — | `1` | — |
| 13 | `ai_recommended` | `tinyint(1)` | yes | — | `0` | — |
| 14 | `activated_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `activated_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `last_run_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `run_count` | `int(11)` | yes | — | `0` | — |
| 18 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_communication_summaries

22 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `summary_key` | `varchar(64)` | **no** | IDX | *NULL* | — |
| 5 | `context_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 6 | `context_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `summary_text` | `longtext` | **no** | — | *NULL* | — |
| 8 | `key_topics` | `longtext` | yes | — | `NULL` | extracted key topics · binary collation |
| 9 | `unresolved_issues` | `longtext` | yes | — | `NULL` | unresolved issues found · binary collation |
| 10 | `decisions` | `longtext` | yes | — | `NULL` | decisions mentioned · binary collation |
| 11 | `commitments` | `longtext` | yes | — | `NULL` | commitments made · binary collation |
| 12 | `dates` | `longtext` | yes | — | `NULL` | important dates mentioned · binary collation |
| 13 | `action_items` | `longtext` | yes | — | `NULL` | action items extracted · binary collation |
| 14 | `sentiment` | `varchar(30)` | yes | — | `NULL` | positive,neutral,negative,mixed - AI interpretation |
| 15 | `next_recommended_step` | `text` | yes | — | `NULL` | — |
| 16 | `message_count` | `int(11)` | yes | — | `0` | — |
| 17 | `date_range_start` | `date` | yes | — | `NULL` | — |
| 18 | `date_range_end` | `date` | yes | — | `NULL` | — |
| 19 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 20 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 21 | `tokens_used` | `int(11)` | yes | — | `0` | — |
| 22 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_communication_templates

22 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `template_key` | `varchar(64)` | **no** | — | *NULL* | — |
| 4 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `communication_type` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 7 | `category` | `varchar(100)` | yes | — | `NULL` | payment,quotation,order,support,onboarding,renewal,custom |
| 8 | `subject_template` | `varchar(500)` | yes | — | `NULL` | — |
| 9 | `body_template` | `longtext` | **no** | — | *NULL* | — |
| 10 | `body_html_template` | `longtext` | yes | — | `NULL` | — |
| 11 | `variables` | `longtext` | yes | — | `NULL` | allowed template variables · binary collation |
| 12 | `tone` | `varchar(50)` | yes | — | `'professional'` | — |
| 13 | `language` | `varchar(10)` | yes | — | `'en'` | — |
| 14 | `version` | `int(11)` | yes | — | `1` | — |
| 15 | `status` | `varchar(30)` | yes | IDX | `'draft'` | draft,active,inactive,archived |
| 16 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 17 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 18 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 19 | `ai_generated` | `tinyint(1)` | yes | — | `0` | — |
| 20 | `usage_count` | `int(11)` | yes | — | `0` | — |
| 21 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 22 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_communication_template_versions

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_communication_templates.id (on delete cascade) |
| 3 | `version` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `subject_template` | `varchar(500)` | yes | — | `NULL` | — |
| 5 | `body_template` | `longtext` | **no** | — | *NULL* | — |
| 6 | `body_html_template` | `longtext` | yes | — | `NULL` | — |
| 7 | `variables` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `changed_by` | `int(11)` | **no** | — | *NULL* | — |
| 9 | `change_reason` | `text` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_config_audit

8 columns · 20 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `config_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `config_id` | `varchar(100)` | yes | — | `NULL` | — |
| 4 | `action` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `previous_value` | `text` | yes | — | `NULL` | — |
| 6 | `new_value` | `text` | yes | — | `NULL` | — |
| 7 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 8 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_confirmation_tokens

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `token_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `request_id` | `int(11)` | yes | IDX FK | `NULL` | ai_observability_requests.id (on delete set null) |
| 4 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 5 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 7 | `action_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `action_data_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `parameters_hash` | `varchar(255)` | yes | — | `NULL` | — |
| 10 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | *NULL* | — |
| 11 | `status` | `enum('pending','confirmed','expired','cancelled')` | **no** | IDX | `'pending'` | — |
| 12 | `expires_at` | `datetime` | **no** | IDX | *NULL* | — |
| 13 | `confirmed_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `confirmed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_connectors

18 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `connector_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `category` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 6 | `version` | `varchar(30)` | **no** | — | `'1.0.0'` | — |
| 7 | `auth_types_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `supported_capabilities_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `configuration_schema_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `status` | `enum('available','disabled','deprecated','maintenance')` | **no** | IDX | `'available'` | — |
| 11 | `safety_classification` | `enum('normal','sensitive','restricted')` | **no** | — | `'normal'` | — |
| 12 | `supports_webhooks` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `supports_sync` | `tinyint(1)` | **no** | — | `0` | — |
| 14 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 16 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_connector_capabilities

19 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `capability_key` | `varchar(150)` | **no** | UQ | *NULL* | — |
| 3 | `connector_key` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `input_schema_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `output_schema_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `required_permission` | `varchar(150)` | yes | — | `NULL` | — |
| 9 | `safety_classification` | `enum('normal','sensitive','restricted')` | **no** | — | `'normal'` | — |
| 10 | `operation_type` | `enum('read','write','destructive','restricted')` | **no** | IDX | `'read'` | — |
| 11 | `requires_confirmation` | `tinyint(1)` | **no** | — | `0` | — |
| 12 | `rate_limit_per_minute` | `int(11)` | **no** | — | `30` | — |
| 13 | `rate_limit_per_hour` | `int(11)` | **no** | — | `500` | — |
| 14 | `rate_limit_per_day` | `int(11)` | **no** | — | `5000` | — |
| 15 | `concurrent_limit` | `int(11)` | **no** | — | `3` | — |
| 16 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 17 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_connector_rest_endpoints

18 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `connection_id` | `int(11)` | yes | IDX | `NULL` | — |
| 3 | `connector_key` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `capability_key` | `varchar(150)` | **no** | IDX | *NULL* | — |
| 5 | `endpoint_key` | `varchar(150)` | **no** | UQ | *NULL* | — |
| 6 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 7 | `approved_base_url` | `varchar(255)` | **no** | — | *NULL* | — |
| 8 | `approved_path` | `varchar(255)` | **no** | — | *NULL* | — |
| 9 | `http_method` | `enum('GET','POST','PUT','PATCH','DELETE')` | **no** | — | `'GET'` | — |
| 10 | `allowed_query_params_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `request_schema_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `output_schema_json` | `longtext` | yes | — | `NULL` | — |
| 13 | `auth_strategy` | `varchar(50)` | **no** | — | `'api_key'` | — |
| 14 | `safety_classification` | `enum('normal','sensitive','restricted')` | **no** | — | `'normal'` | — |
| 15 | `rate_limit_per_minute` | `int(11)` | **no** | — | `30` | — |
| 16 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 17 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_conversations

10 columns · 47 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 3 | `title` | `varchar(255)` | **no** | — | `'New Conversation'` | — |
| 4 | `provider_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_providers.id (on delete cascade) |
| 5 | `model_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_models.id (on delete cascade) |
| 6 | `status` | `varchar(20)` | **no** | IDX | `'active'` | active|archived |
| 7 | `message_count` | `int(10) unsigned` | **no** | — | `0` | — |
| 8 | `last_message_at` | `timestamp` | yes | IDX | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_conversation_shares

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `share_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `conversation_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `original_type` | `enum('private','shared_workspace')` | **no** | — | `'private'` | — |
| 4 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 5 | `shared_by` | `int(11)` | **no** | — | *NULL* | — |
| 6 | `shared_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_copilot_capabilities

17 columns · 16 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `capability_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 6 | `feature` | `varchar(50)` | **no** | — | *NULL* | — |
| 7 | `intent_types` | `longtext` | **no** | — | *NULL* | binary collation |
| 8 | `is_enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 9 | `requires_confirmation` | `tinyint(1)` | **no** | — | `0` | — |
| 10 | `requires_review` | `tinyint(1)` | **no** | — | `0` | — |
| 11 | `max_risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 12 | `priority` | `int(11)` | **no** | — | `50` | — |
| 13 | `handler_class` | `varchar(200)` | yes | — | `NULL` | — |
| 14 | `handler_method` | `varchar(200)` | yes | — | `NULL` | — |
| 15 | `metadata` | `longtext` | yes | — | `NULL` | binary collation |
| 16 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_copilot_feedback

8 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `session_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `message_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `feedback_type` | `enum('helpful','not_helpful','incorrect','incomplete','successful','partial','unsafe')` | **no** | IDX | *NULL* | — |
| 7 | `feedback_text` | `text` | yes | — | `NULL` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_copilot_intents

13 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `session_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `message_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `raw_text` | `text` | **no** | — | *NULL* | — |
| 7 | `classified_intent` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 8 | `confidence` | `decimal(5,4)` | yes | — | `NULL` | — |
| 9 | `parameters` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `routing_result` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `execution_result` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `latency_ms` | `int(11)` | yes | — | `NULL` | — |
| 13 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_copilot_messages

24 columns · 20 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `session_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_copilot_sessions.id (on delete cascade) |
| 3 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `role` | `enum('user','assistant','system')` | **no** | — | *NULL* | — |
| 6 | `content_text` | `longtext` | **no** | — | *NULL* | — |
| 7 | `intent_type` | `varchar(100)` | yes | IDX | `NULL` | — |
| 8 | `intent_confidence` | `decimal(5,4)` | yes | — | `NULL` | — |
| 9 | `intent_parameters` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `capabilities_used` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `routing_decision` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `governance_decision` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `provider_used` | `varchar(100)` | yes | — | `NULL` | — |
| 14 | `model_used` | `varchar(100)` | yes | — | `NULL` | — |
| 15 | `tokens_used` | `int(11)` | yes | — | `NULL` | — |
| 16 | `estimated_cost` | `decimal(10,6)` | yes | — | `NULL` | — |
| 17 | `latency_ms` | `int(11)` | yes | — | `NULL` | — |
| 18 | `response_components` | `longtext` | yes | — | `NULL` | binary collation |
| 19 | `citations` | `longtext` | yes | — | `NULL` | binary collation |
| 20 | `actionable_items` | `longtext` | yes | — | `NULL` | binary collation |
| 21 | `f25_task_id` | `int(11)` | yes | — | `NULL` | — |
| 22 | `feedback` | `enum('helpful','not_helpful','incorrect','incomplete')` | yes | — | `NULL` | — |
| 23 | `feedback_at` | `datetime` | yes | — | `NULL` | — |
| 24 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_copilot_routes

10 columns · 22 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `route_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `route_path` | `varchar(500)` | **no** | — | *NULL* | — |
| 4 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 5 | `page_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `nav_keywords` | `longtext` | **no** | — | *NULL* | binary collation |
| 8 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 9 | `permission_required` | `varchar(100)` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_copilot_sessions

16 columns · 15 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `source` | `enum('web','voice','notification','intelligence','document','workflow','command_center')` | **no** | — | `'web'` | — |
| 5 | `context_module` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `context_page` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `context_record_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `context_record_id` | `int(11)` | yes | — | `NULL` | — |
| 9 | `context_metadata` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `status` | `enum('active','completed','abandoned')` | **no** | IDX | `'active'` | — |
| 11 | `message_count` | `int(11)` | **no** | — | `0` | — |
| 12 | `last_intent` | `varchar(100)` | yes | — | `NULL` | — |
| 13 | `total_cost` | `decimal(10,6)` | **no** | — | `0.000000` | — |
| 14 | `started_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 15 | `last_activity_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |
| 16 | `ended_at` | `datetime` | yes | — | `NULL` | — |

### ai_cost_controls

10 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `control_type` | `enum('per_user_daily','per_company_daily','per_model_daily','monthly_company','per_agent_run','per_automation_run')` | **no** | IDX | *NULL* | — |
| 3 | `target_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `max_cost` | `decimal(10,2)` | **no** | — | *NULL* | — |
| 5 | `current_cost` | `decimal(10,2)` | yes | — | `0.00` | — |
| 6 | `period_start` | `date` | **no** | IDX | *NULL* | — |
| 7 | `period_end` | `date` | **no** | — | *NULL* | — |
| 8 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_data_classifications

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `classification` | `enum('public','internal','confidential','sensitive','restricted')` | **no** | — | *NULL* | — |
| 3 | `name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `masking_required` | `tinyint(1)` | **no** | — | `0` | — |
| 6 | `allowed_providers_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_data_classification_fields

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `classification_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `module_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `table_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 5 | `field_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 6 | `classification` | `enum('public','internal','confidential','sensitive','restricted')` | **no** | — | *NULL* | — |
| 7 | `mask_at_output` | `tinyint(1)` | **no** | — | `0` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_dimension_definitions

7 columns · 9 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `dimension_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `source_table` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `source_column` | `varchar(100)` | **no** | — | *NULL* | — |
| 6 | `label_column` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |

### ai_documents

28 columns · 18 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `document_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `file_reference` | `varchar(500)` | yes | — | `NULL` | — |
| 4 | `file_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `file_size` | `bigint(20)` | yes | — | `0` | — |
| 7 | `file_hash` | `varchar(64)` | yes | IDX | `NULL` | — |
| 8 | `source_module` | `varchar(100)` | yes | IDX | `NULL` | — |
| 9 | `related_record_type` | `varchar(100)` | yes | — | `NULL` | — |
| 10 | `related_record_id` | `int(11)` | yes | — | `NULL` | — |
| 11 | `document_type` | `enum('unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other')` | **no** | IDX | `'unknown'` | — |
| 12 | `classification_confidence` | `decimal(3,2)` | yes | — | `NULL` | — |
| 13 | `classification_reason` | `text` | yes | — | `NULL` | — |
| 14 | `processing_status` | `enum('uploaded','queued','processing','needs_review','ready','failed','unsupported')` | **no** | IDX | `'uploaded'` | — |
| 15 | `extraction_status` | `enum('pending','extracting','extracted','validated','reviewed','failed')` | **no** | IDX | `'pending'` | — |
| 16 | `extracted_text` | `longtext` | yes | — | `NULL` | — |
| 17 | `ocr_confidence` | `decimal(3,2)` | yes | — | `NULL` | — |
| 18 | `ocr_language` | `varchar(10)` | yes | — | `NULL` | — |
| 19 | `duplicate_status` | `enum('unique','possible_duplicate','duplicate')` | **no** | — | `'unique'` | — |
| 20 | `duplicate_of_id` | `int(11)` | yes | — | `NULL` | — |
| 21 | `is_sensitive` | `tinyint(1)` | **no** | — | `0` | — |
| 22 | `sensitive_category` | `varchar(100)` | yes | — | `NULL` | — |
| 23 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 24 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 25 | `reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 26 | `status` | `enum('active','archived','deleted')` | **no** | IDX | `'active'` | — |
| 27 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 28 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_action_requests

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `request_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `document_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `action_id` | `varchar(200)` | **no** | — | *NULL* | — |
| 6 | `pending_action_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `proposed_params_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `status` | `enum('pending_confirmation','confirmed','executed','cancelled','failed')` | **no** | IDX | `'pending_confirmation'` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_admin_config

8 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `config_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `config_value` | `text` | yes | — | `NULL` | — |
| 5 | `config_type` | `enum('string','integer','boolean','json')` | **no** | — | `'string'` | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 8 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_audit

7 columns · 27 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `audit_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `document_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `action_type` | `enum('registered','processing_started','ocr_completed','classification_suggested','extraction_completed','extraction_reviewed','document_linked','knowledge_indexed','comparison_generated','erp_draft_proposed','action_confirmed','qna_asked','version_suggested','relationship_suggested','action_proposed','deleted')` | **no** | IDX | *NULL* | — |
| 5 | `actor_user_id` | `int(11)` | **no** | — | *NULL* | — |
| 6 | `metadata_json` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_chunks

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `chunk_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `chunk_index` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `content` | `text` | **no** | — | *NULL* | — |
| 5 | `page_number` | `int(11)` | yes | — | `NULL` | — |
| 6 | `start_offset` | `int(11)` | yes | — | `NULL` | — |
| 7 | `end_offset` | `int(11)` | yes | — | `NULL` | — |
| 8 | `embedding_id` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_classifications

9 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `classification_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `suggested_type` | `enum('unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other')` | **no** | — | *NULL* | — |
| 4 | `confidence` | `decimal(3,2)` | **no** | — | *NULL* | — |
| 5 | `reason_summary` | `text` | yes | — | `NULL` | — |
| 6 | `method` | `enum('rule','ai','hybrid')` | **no** | — | `'ai'` | — |
| 7 | `is_accepted` | `tinyint(1)` | yes | — | `NULL` | — |
| 8 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_comparisons

11 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `comparison_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `comparison_type` | `enum('quotation','invoice','contract','general')` | **no** | — | `'general'` | — |
| 5 | `document_ids` | `longtext` | **no** | — | *NULL* | binary collation |
| 6 | `comparison_result` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `summary` | `text` | yes | — | `NULL` | — |
| 8 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 9 | `status` | `enum('pending','completed','failed')` | **no** | IDX | `'pending'` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_expiry_reminders

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `reminder_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `reminder_type` | `enum('90_days','60_days','30_days','7_days','expired')` | **no** | — | *NULL* | — |
| 4 | `expiry_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `is_sent` | `tinyint(1)` | **no** | — | `0` | — |
| 6 | `sent_at` | `datetime` | yes | — | `NULL` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_extractions

16 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `extraction_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `template_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `extraction_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `extracted_data` | `longtext` | **no** | — | *NULL* | binary collation |
| 6 | `confidence` | `decimal(3,2)` | yes | — | `NULL` | — |
| 7 | `validation_status` | `enum('pending','valid','invalid','needs_review')` | **no** | — | `'pending'` | — |
| 8 | `validation_errors` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `evidence_json` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `extraction_method` | `varchar(100)` | yes | — | `NULL` | — |
| 12 | `review_status` | `enum('pending','accepted','partially_accepted','rejected')` | **no** | — | `'pending'` | — |
| 13 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_field_confidence

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `extraction_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `field_name` | `varchar(150)` | **no** | IDX | *NULL* | — |
| 5 | `field_value` | `text` | yes | — | `NULL` | — |
| 6 | `confidence` | `decimal(5,4)` | **no** | — | `0.5000` | — |
| 7 | `source_page` | `int(11)` | yes | — | `NULL` | — |
| 8 | `source_section` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `source_text` | `text` | yes | — | `NULL` | — |
| 10 | `extraction_method` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `verification_status` | `enum('ai_extracted','human_verified','human_edited','rejected')` | **no** | IDX | `'ai_extracted'` | — |
| 12 | `verified_by` | `int(11)` | yes | — | `NULL` | — |
| 13 | `verified_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_matches

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 4 | `field_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `field_value` | `varchar(500)` | yes | — | `NULL` | — |
| 6 | `target_module` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 7 | `target_table` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `target_record_id` | `int(11)` | **no** | — | *NULL* | — |
| 9 | `target_record_label` | `varchar(255)` | yes | — | `NULL` | — |
| 10 | `match_confidence` | `decimal(5,4)` | **no** | — | `0.0000` | — |
| 11 | `match_method` | `varchar(100)` | yes | — | `NULL` | — |
| 12 | `match_status` | `enum('suggested','accepted','rejected','auto_matched')` | **no** | IDX | `'suggested'` | — |
| 13 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_qna_sessions

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `qna_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 4 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 5 | `question` | `text` | **no** | — | *NULL* | — |
| 6 | `answer` | `longtext` | **no** | — | *NULL* | — |
| 7 | `confidence` | `decimal(3,2)` | yes | — | `NULL` | — |
| 8 | `evidence_json` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_queue

12 columns · 45 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `queue_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `company_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `priority` | `int(11)` | **no** | — | `5` | — |
| 5 | `task_type` | `enum('extract_text','ocr','classify','structured_extract','validate','index','summarize','compare')` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('pending','processing','completed','failed','cancelled')` | **no** | IDX | `'pending'` | — |
| 7 | `attempts` | `int(11)` | **no** | — | `0` | — |
| 8 | `max_attempts` | `int(11)` | **no** | — | `3` | — |
| 9 | `error_message` | `text` | yes | — | `NULL` | — |
| 10 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 11 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_relationships

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `relationship_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | — | *NULL* | — |
| 3 | `source_document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 4 | `target_document_id` | `int(11)` | yes | IDX | `NULL` | — |
| 5 | `target_module` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `target_record_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `relationship_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `confidence` | `decimal(3,2)` | yes | — | `NULL` | — |
| 9 | `method` | `enum('manual','ai_suggested','verified')` | **no** | — | `'manual'` | — |
| 10 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_retention_policies

12 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `document_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 5 | `retention_days` | `int(11)` | **no** | — | `365` | — |
| 6 | `archive_after_days` | `int(11)` | yes | — | `NULL` | — |
| 7 | `delete_after_days` | `int(11)` | yes | — | `NULL` | — |
| 8 | `legal_hold_enabled` | `tinyint(1)` | **no** | — | `0` | — |
| 9 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 10 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_security_events

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `document_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `event_type` | `enum('injection_detected','malicious_file','unauthorized_access','classification_violation','retention_violation','external_processing_blocked','secret_detected')` | **no** | IDX | *NULL* | — |
| 5 | `severity` | `enum('low','medium','high','critical')` | **no** | IDX | `'medium'` | — |
| 6 | `details_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `file_name` | `varchar(255)` | yes | — | `NULL` | — |
| 8 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 10 | `user_agent` | `varchar(255)` | yes | — | `NULL` | — |
| 11 | `blocked` | `tinyint(1)` | **no** | — | `0` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_summaries

10 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `summary_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 3 | `summary_type` | `enum('short','executive','key_terms','risks','dates','missing_info')` | **no** | — | *NULL* | — |
| 4 | `content` | `text` | **no** | — | *NULL* | — |
| 5 | `facts_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `interpretations_json` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `risks_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `created_by_ai` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_three_way_matches

21 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `invoice_document_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `po_document_id` | `int(11)` | yes | IDX | `NULL` | — |
| 5 | `delivery_document_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `invoice_record_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `po_record_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `delivery_record_id` | `int(11)` | yes | — | `NULL` | — |
| 9 | `match_status` | `enum('pending','matched','discrepancy','manual_review')` | **no** | IDX | `'pending'` | — |
| 10 | `discrepancies_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `match_details_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `total_invoice` | `decimal(15,2)` | yes | — | `NULL` | — |
| 13 | `total_po` | `decimal(15,2)` | yes | — | `NULL` | — |
| 14 | `total_delivery` | `decimal(15,2)` | yes | — | `NULL` | — |
| 15 | `quantity_match` | `tinyint(1)` | yes | — | `NULL` | — |
| 16 | `price_match` | `tinyint(1)` | yes | — | `NULL` | — |
| 17 | `tax_match` | `tinyint(1)` | yes | — | `NULL` | — |
| 18 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 19 | `reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 20 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 21 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_document_type_configs

10 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `type_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `type_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `extraction_schema_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `auto_extract` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `requires_review` | `tinyint(1)` | **no** | — | `0` | — |
| 9 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_document_version_suggestions

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `suggestion_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `source_document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 4 | `target_document_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_documents.document_id (on delete cascade) |
| 5 | `confidence` | `decimal(3,2)` | **no** | — | *NULL* | — |
| 6 | `status` | `enum('pending_review','accepted','rejected')` | **no** | — | `'pending_review'` | — |
| 7 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 8 | `reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_emergency_controls

13 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `control_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `category` | `varchar(50)` | **no** | — | `'global'` | — |
| 6 | `is_active` | `tinyint(1)` | **no** | IDX | `0` | — |
| 7 | `activated_by` | `int(11)` | yes | — | `NULL` | — |
| 8 | `activated_at` | `datetime` | yes | — | `NULL` | — |
| 9 | `deactivated_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `deactivated_at` | `datetime` | yes | — | `NULL` | — |
| 11 | `reason` | `text` | yes | — | `NULL` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_error_registry

14 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `feature` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `operation_type` | `varchar(50)` | yes | — | `NULL` | — |
| 4 | `error_category` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 5 | `error_message` | `text` | yes | — | `NULL` | — |
| 6 | `provider_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `model_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `company_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `user_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 10 | `retry_count` | `int(10) unsigned` | yes | — | `0` | — |
| 11 | `resolution_status` | `enum('open','investigating','resolved','ignored')` | **no** | IDX | `'open'` | — |
| 12 | `sanitized_details` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |
| 14 | `resolved_at` | `timestamp` | yes | — | `NULL` | — |

### ai_event_log

8 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `event_name` | `varchar(150)` | **no** | IDX | *NULL* | — |
| 3 | `record_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `record_type` | `varchar(50)` | yes | — | `NULL` | — |
| 5 | `payload_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `event_hash` | `varchar(64)` | **no** | UQ | *NULL* | — |
| 7 | `processed` | `tinyint(1)` | **no** | IDX | `0` | — |
| 8 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_event_registry

7 columns · 23 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `event_name` | `varchar(150)` | **no** | UQ | *NULL* | — |
| 3 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `payload_schema` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_event_subscriptions

6 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `automation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_automations.id (on delete cascade) |
| 3 | `event_name` | `varchar(150)` | **no** | IDX | *NULL* | — |
| 4 | `filter_json` | `longtext` | yes | — | `NULL` | binary collation |
| 5 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_extraction_templates

13 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `template_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `document_type` | `enum('unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other')` | **no** | IDX | *NULL* | — |
| 5 | `schema_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 6 | `required_fields` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `optional_fields` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `validation_rules` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `version` | `int(11)` | **no** | — | `1` | — |
| 10 | `status` | `enum('draft','testing','approved','published','archived')` | **no** | IDX | `'draft'` | — |
| 11 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_financial_thresholds

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `action_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `min_amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 4 | `max_amount` | `decimal(15,2)` | **no** | — | `999999999.99` | — |
| 5 | `required_action` | `enum('confirm','review')` | **no** | — | `'confirm'` | — |
| 6 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_findings

12 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `finding_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `summary` | `text` | **no** | — | *NULL* | — |
| 5 | `confidence` | `decimal(3,2)` | yes | — | `NULL` | — |
| 6 | `source_conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `created_by_user_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `created_by_ai` | `tinyint(1)` | **no** | — | `0` | — |
| 9 | `reviewed_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `status` | `enum('draft','reviewed','accepted','dismissed','archived')` | **no** | IDX | `'draft'` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_finding_evidence

7 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `evidence_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `finding_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_findings.finding_id (on delete cascade) |
| 3 | `reference_type` | `enum('erp_record','tool_result','knowledge_document','insight','conversation')` | **no** | — | *NULL* | — |
| 4 | `reference_id` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `access_revalidated` | `tinyint(1)` | **no** | — | `0` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_forecasts

20 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `metric_key` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 5 | `forecast_method` | `enum('moving_average','weighted_moving_average','historical_trend','linear_regression')` | **no** | — | `'moving_average'` | — |
| 6 | `forecast_value` | `decimal(20,4)` | **no** | — | *NULL* | — |
| 7 | `forecast_unit` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `forecast_currency` | `varchar(10)` | yes | — | `NULL` | — |
| 9 | `forecast_period_start` | `date` | **no** | — | *NULL* | — |
| 10 | `forecast_period_end` | `date` | **no** | — | *NULL* | — |
| 11 | `historical_data` | `longtext` | **no** | — | *NULL* | binary collation |
| 12 | `historical_range_start` | `date` | **no** | — | *NULL* | — |
| 13 | `historical_range_end` | `date` | **no** | — | *NULL* | — |
| 14 | `confidence_level` | `enum('low','medium','high')` | **no** | — | `'medium'` | — |
| 15 | `confidence_score` | `decimal(5,4)` | yes | — | `NULL` | — |
| 16 | `assumptions` | `text` | yes | — | `NULL` | — |
| 17 | `limitations` | `text` | yes | — | `NULL` | — |
| 18 | `parameters` | `longtext` | yes | — | `NULL` | binary collation |
| 19 | `status` | `enum('active','expired','archived')` | **no** | IDX | `'active'` | — |
| 20 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_governance_audit

12 columns · 138 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `action` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `actor_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `actor_type` | `enum('user','system','ai')` | **no** | — | `'user'` | — |
| 5 | `target_type` | `varchar(50)` | yes | IDX | `NULL` | — |
| 6 | `target_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `previous_value_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `new_value_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `change_reason` | `text` | yes | — | `NULL` | — |
| 10 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 11 | `user_agent` | `varchar(255)` | yes | — | `NULL` | — |
| 12 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_governance_config

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `config_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `config_value_json` | `longtext` | yes | — | `NULL` | — |
| 4 | `company_id` | `int(11)` | yes | IDX | `NULL` | — |
| 5 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 6 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_health_checks

11 columns · 17 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `component` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `component_type` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `status` | `enum('healthy','warning','degraded','unavailable','unknown')` | **no** | IDX | `'unknown'` | — |
| 5 | `message` | `text` | yes | — | `NULL` | — |
| 6 | `last_checked_at` | `timestamp` | yes | — | `NULL` | — |
| 7 | `last_healthy_at` | `timestamp` | yes | — | `NULL` | — |
| 8 | `failure_count` | `int(10) unsigned` | yes | — | `0` | — |
| 9 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_incoming_webhook_events

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `endpoint_token` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 4 | `event_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `signature_status` | `enum('verified','failed','missing')` | **no** | — | `'missing'` | — |
| 6 | `replay_key` | `varchar(150)` | yes | — | `NULL` | — |
| 7 | `payload_size_bytes` | `int(11)` | **no** | — | `0` | — |
| 8 | `status` | `enum('received','rejected','queued')` | **no** | IDX | `'received'` | — |
| 9 | `payload_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_injection_attempts

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `request_id` | `int(11)` | yes | IDX FK | `NULL` | ai_observability_requests.id (on delete set null) |
| 3 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 4 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 5 | `user_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `source` | `enum('user_input','retrieved_content','tool_output','external_api','document','connector')` | **no** | — | *NULL* | — |
| 7 | `injection_type` | `enum('system_prompt','jailbreak','role_playing','data_exfiltration','command_injection','prompt_injection','unknown')` | **no** | IDX | *NULL* | — |
| 8 | `detection_method` | `enum('pattern_match','heuristic','ai_detection','rule_based')` | **no** | — | *NULL* | — |
| 9 | `matched_pattern` | `varchar(255)` | yes | — | `NULL` | — |
| 10 | `confidence_score` | `decimal(5,4)` | yes | — | `NULL` | — |
| 11 | `input_snippet` | `text` | yes | — | `NULL` | — |
| 12 | `sanitized_input` | `text` | yes | — | `NULL` | — |
| 13 | `blocked` | `tinyint(1)` | **no** | IDX | `1` | — |
| 14 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_insight_definitions

19 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `definition_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 6 | `category` | `varchar(50)` | **no** | IDX | `'general'` | — |
| 7 | `insight_type` | `enum('rule_based','ai_analyzed','hybrid')` | **no** | — | `'rule_based'` | — |
| 8 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 9 | `required_permissions` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `evaluation_config` | `longtext` | **no** | — | *NULL* | binary collation |
| 11 | `severity_rules` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `target_users_config` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `navigation_config` | `longtext` | yes | — | `NULL` | binary collation |
| 14 | `dedup_strategy` | `enum('same_day','same_records','same_hash','always_new')` | **no** | — | `'same_hash'` | — |
| 15 | `evaluation_frequency` | `enum('hourly','daily','weekly','on_event')` | **no** | IDX | `'hourly'` | — |
| 16 | `max_records` | `int(10) unsigned` | yes | — | `100` | — |
| 17 | `max_runtime_seconds` | `int(10) unsigned` | yes | — | `30` | — |
| 18 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_insight_evaluations

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `definition_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_insight_definitions.id (on delete cascade) |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 4 | `status` | `enum('success','partial','failed')` | **no** | IDX | *NULL* | — |
| 5 | `evidence_summary` | `text` | yes | — | `NULL` | — |
| 6 | `records_evaluated` | `int(10) unsigned` | yes | — | `0` | — |
| 7 | `insights_created` | `int(10) unsigned` | yes | — | `0` | — |
| 8 | `insights_updated` | `int(10) unsigned` | yes | — | `0` | — |
| 9 | `insights_resolved` | `int(10) unsigned` | yes | — | `0` | — |
| 10 | `duration_ms` | `int(10) unsigned` | yes | — | `0` | — |
| 11 | `error_message` | `text` | yes | — | `NULL` | — |
| 12 | `evaluated_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_insight_feedback

6 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `insight_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_insight_records.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `feedback_type` | `enum('helpful','not_helpful','dismiss')` | **no** | — | *NULL* | — |
| 5 | `reason` | `text` | yes | — | `NULL` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_insight_preferences

11 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `module` | `varchar(50)` | yes | — | `NULL` | — |
| 4 | `insights_enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 5 | `info_enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 6 | `notice_enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 7 | `warning_enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `critical_enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `notification_frequency` | `enum('immediate','hourly_digest','daily_digest')` | **no** | — | `'immediate'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_insight_recipients

8 columns · 13 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `insight_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_insight_records.id (on delete cascade) |
| 3 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `status` | `enum('pending','seen','dismissed','acknowledged')` | **no** | IDX | `'pending'` | — |
| 5 | `seen_at` | `timestamp` | yes | — | `NULL` | — |
| 6 | `dismissed_at` | `timestamp` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_insight_records

19 columns · 17 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `definition_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_insight_definitions.id (on delete cascade) |
| 3 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 4 | `title` | `varchar(500)` | **no** | — | *NULL* | — |
| 5 | `summary` | `text` | yes | — | `NULL` | — |
| 6 | `severity` | `enum('info','notice','warning','critical')` | **no** | IDX | `'info'` | — |
| 7 | `category` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `source_type` | `varchar(50)` | yes | — | `NULL` | — |
| 9 | `source_ids` | `longtext` | yes | — | `NULL` | binary collation |
| 10 | `evidence_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 11 | `ai_narrative` | `text` | yes | — | `NULL` | — |
| 12 | `navigation_json` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `dedup_hash` | `varchar(64)` | yes | IDX | `NULL` | — |
| 14 | `status` | `enum('active','seen','dismissed','resolved','expired')` | **no** | IDX | `'active'` | — |
| 15 | `first_detected_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `last_detected_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 17 | `resolved_at` | `timestamp` | yes | — | `NULL` | — |
| 18 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_integration_activity

14 columns · 15 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `connector_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `connection_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `capability_key` | `varchar(150)` | yes | IDX | `NULL` | — |
| 5 | `actor_type` | `enum('user','system','workflow','webhook')` | **no** | — | `'user'` | — |
| 6 | `actor_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `status` | `enum('executed','failed','blocked','queued','previewed','confirmed')` | **no** | IDX | `'executed'` | — |
| 9 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 10 | `safe_input_summary` | `text` | yes | — | `NULL` | — |
| 11 | `safe_output_summary` | `text` | yes | — | `NULL` | — |
| 12 | `error_category` | `varchar(50)` | yes | — | `NULL` | — |
| 13 | `error_message` | `varchar(500)` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_integration_connections

17 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `connector_key` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `display_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('draft','connecting','active','expired','failed','disabled','revoked')` | **no** | IDX | `'draft'` | — |
| 6 | `authentication_state` | `enum('unconfigured','configured','expired','failed','revoked')` | **no** | — | `'unconfigured'` | — |
| 7 | `configuration_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `enabled_capabilities_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `connection_classification` | `enum('normal','sensitive','restricted')` | **no** | — | `'normal'` | — |
| 10 | `last_health_status` | `enum('healthy','degraded','failed','expired','disabled')` | yes | — | `NULL` | — |
| 11 | `last_health_check_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `last_success_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `revoked_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_integration_credentials

15 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `connection_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `credential_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 4 | `auth_strategy` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `secret_encrypted` | `longtext` | yes | — | `NULL` | — |
| 6 | `secret_iv` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `secondary_secret_encrypted` | `longtext` | yes | — | `NULL` | — |
| 8 | `secondary_secret_iv` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `version_no` | `int(11)` | **no** | — | `1` | — |
| 11 | `status` | `enum('active','rotated','revoked')` | **no** | IDX | `'active'` | — |
| 12 | `rotated_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `rotated_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_integration_health

15 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `connection_id` | `int(11)` | **no** | UQ | *NULL* | — |
| 3 | `connector_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `status` | `enum('healthy','degraded','failed','expired','disabled')` | **no** | IDX | `'healthy'` | — |
| 5 | `auth_status` | `enum('valid','expired','failed','revoked','missing')` | **no** | — | `'missing'` | — |
| 6 | `api_status` | `enum('available','degraded','failed','unknown')` | **no** | — | `'unknown'` | — |
| 7 | `last_success_at` | `datetime` | yes | — | `NULL` | — |
| 8 | `last_error_at` | `datetime` | yes | — | `NULL` | — |
| 9 | `error_rate` | `decimal(8,2)` | **no** | — | `0.00` | — |
| 10 | `rate_limit_events` | `int(11)` | **no** | — | `0` | — |
| 11 | `consecutive_failures` | `int(11)` | **no** | — | `0` | — |
| 12 | `circuit_state` | `enum('healthy','degraded','temporarily_disabled')` | **no** | IDX | `'healthy'` | — |
| 13 | `token_expires_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_integration_policies

9 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `policy_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `policy_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 6 | `config_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 8 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_intelligence_events

31 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `event_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 5 | `source_module` | `varchar(100)` | yes | IDX | `NULL` | — |
| 6 | `source_record_type` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `source_record_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `detection_type` | `enum('rule','threshold','trend','anomaly','watchlist','manual')` | **no** | — | `'rule'` | — |
| 9 | `detector_key` | `varchar(100)` | yes | IDX | `NULL` | — |
| 10 | `severity` | `enum('info','low','medium','high','critical')` | **no** | IDX | `'medium'` | — |
| 11 | `confidence` | `decimal(5,4)` | **no** | — | `0.8000` | — |
| 12 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 13 | `explanation` | `text` | yes | — | `NULL` | — |
| 14 | `evidence_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `recommendation` | `text` | yes | — | `NULL` | — |
| 16 | `suggested_action` | `text` | yes | — | `NULL` | — |
| 17 | `suggested_action_type` | `varchar(100)` | yes | — | `NULL` | — |
| 18 | `urgency` | `enum('low','medium','high','immediate')` | **no** | — | `'medium'` | — |
| 19 | `status` | `enum('new','acknowledged','snoozed','action_pending','actioned','dismissed','expired')` | **no** | IDX | `'new'` | — |
| 20 | `assigned_user_id` | `int(11)` | yes | — | `NULL` | — |
| 21 | `assigned_team` | `varchar(100)` | yes | — | `NULL` | — |
| 22 | `related_task_id` | `int(11)` | yes | — | `NULL` | — |
| 23 | `related_task_key` | `varchar(100)` | yes | — | `NULL` | — |
| 24 | `fingerprint` | `varchar(255)` | yes | IDX | `NULL` | — |
| 25 | `expires_at` | `datetime` | yes | — | `NULL` | — |
| 26 | `snoozed_until` | `datetime` | yes | — | `NULL` | — |
| 27 | `dismissed_at` | `datetime` | yes | — | `NULL` | — |
| 28 | `dismissed_reason` | `text` | yes | — | `NULL` | — |
| 29 | `actioned_at` | `datetime` | yes | — | `NULL` | — |
| 30 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 31 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_intelligence_evidence

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `event_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_intelligence_events.id (on delete cascade) |
| 3 | `record_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `record_id` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `record_label` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `record_data_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `amount` | `decimal(15,2)` | yes | — | `NULL` | — |
| 8 | `date_value` | `date` | yes | — | `NULL` | — |
| 9 | `status_value` | `varchar(50)` | yes | — | `NULL` | — |
| 10 | `comparison_value` | `text` | yes | — | `NULL` | — |
| 11 | `classification` | `enum('public','internal','confidential','sensitive','restricted')` | **no** | — | `'internal'` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_intelligence_feedback

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `event_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_intelligence_events.id (on delete cascade) |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `feedback_type` | `enum('useful','not_useful','incorrect','duplicate','already_handled')` | **no** | IDX | *NULL* | — |
| 5 | `comment` | `text` | yes | — | `NULL` | — |
| 6 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_intelligence_locks

4 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `lock_name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `locked_by` | `int(11)` | yes | — | `NULL` | — |
| 4 | `locked_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_intelligence_monitors

19 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `monitor_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `module` | `varchar(100)` | **no** | — | *NULL* | — |
| 6 | `data_source` | `varchar(100)` | **no** | — | *NULL* | — |
| 7 | `condition_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 8 | `condition_config_json` | `longtext` | **no** | — | *NULL* | — |
| 9 | `threshold_value` | `decimal(15,4)` | yes | — | `NULL` | — |
| 10 | `frequency` | `enum('hourly','daily','weekly','on_change')` | **no** | IDX | `'daily'` | — |
| 11 | `severity` | `enum('info','low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 12 | `notification_target` | `varchar(100)` | yes | — | `NULL` | — |
| 13 | `recommendation_template` | `text` | yes | — | `NULL` | — |
| 14 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 15 | `last_run_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 17 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_intelligence_preferences

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `topics_json` | `longtext` | yes | — | `NULL` | — |
| 5 | `min_severity` | `enum('info','low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 6 | `notification_frequency` | `enum('realtime','daily','weekly','never')` | **no** | — | `'daily'` | — |
| 7 | `briefing_enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `briefing_time` | `time` | yes | — | `NULL` | — |
| 9 | `snooze_hours` | `int(11)` | **no** | — | `0` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_intelligence_rules

17 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `rule_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `module` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 6 | `detection_type` | `enum('rule','threshold','trend','anomaly')` | **no** | — | `'rule'` | — |
| 7 | `config_json` | `longtext` | **no** | — | *NULL* | — |
| 8 | `severity_default` | `enum('info','low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 9 | `urgency_default` | `enum('low','medium','high','immediate')` | **no** | — | `'medium'` | — |
| 10 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 11 | `last_run_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `last_run_status` | `varchar(50)` | yes | — | `NULL` | — |
| 13 | `last_run_events` | `int(11)` | **no** | — | `0` | — |
| 14 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_intelligence_runs

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_type` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `detector_key` | `varchar(100)` | yes | IDX | `NULL` | — |
| 4 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `status` | `enum('running','completed','failed','partial')` | **no** | IDX | `'running'` | — |
| 6 | `events_created` | `int(11)` | **no** | — | `0` | — |
| 7 | `events_updated` | `int(11)` | **no** | — | `0` | — |
| 8 | `events_deduplicated` | `int(11)` | **no** | — | `0` | — |
| 9 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 10 | `error_message` | `text` | yes | — | `NULL` | — |
| 11 | `started_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 12 | `completed_at` | `datetime` | yes | — | `NULL` | — |

### ai_intelligence_watchlists

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `entity_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 5 | `entity_id` | `int(11)` | **no** | — | *NULL* | — |
| 6 | `entity_label` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `watch_config_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 9 | `last_checked_at` | `datetime` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_kill_switches

10 columns · 42 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `switch_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 6 | `disabled_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `disabled_at` | `timestamp` | yes | — | `NULL` | — |
| 8 | `reason` | `text` | yes | — | `NULL` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_knowledge_chunks

13 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `source_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_knowledge_sources.id (on delete cascade) |
| 3 | `collection_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_knowledge_collections.id (on delete cascade) |
| 4 | `chunk_index` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 5 | `content` | `text` | **no** | — | *NULL* | — |
| 6 | `content_hash` | `varchar(64)` | **no** | IDX | *NULL* | — |
| 7 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `version_id` | `int(11)` | yes | — | `NULL` | — |
| 9 | `effective_date` | `date` | yes | — | `NULL` | — |
| 10 | `section_heading` | `varchar(500)` | yes | — | `NULL` | — |
| 11 | `page_number` | `int(11)` | yes | — | `NULL` | — |
| 12 | `embedding_vector` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_knowledge_citations

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `query_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_knowledge_queries.id (on delete cascade) |
| 3 | `source_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `chunk_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `version_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `citation_text` | `text` | yes | — | `NULL` | — |
| 7 | `section_name` | `varchar(255)` | yes | — | `NULL` | — |
| 8 | `page_number` | `int(11)` | yes | — | `NULL` | — |
| 9 | `relevance` | `decimal(5,4)` | yes | — | `NULL` | — |
| 10 | `used_in_answer` | `tinyint(1)` | yes | — | `1` | — |
| 11 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_knowledge_collections

10 columns · 31 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 3 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `module` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `visibility` | `enum('private','company','restricted')` | **no** | — | `'company'` | — |
| 7 | `status` | `enum('active','archived')` | **no** | IDX | `'active'` | — |
| 8 | `created_by` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_knowledge_collection_access

5 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `collection_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_knowledge_collections.id (on delete cascade) |
| 3 | `role` | `varchar(100)` | yes | — | `NULL` | — |
| 4 | `user_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_knowledge_drafts

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `draft_type` | `varchar(50)` | **no** | — | *NULL* | faq,sop_outline,article,procedure_summary,training_material |
| 5 | `title` | `varchar(500)` | **no** | — | *NULL* | — |
| 6 | `content` | `longtext` | **no** | — | *NULL* | — |
| 7 | `source_query` | `text` | yes | — | `NULL` | — |
| 8 | `source_citations` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `collection_id` | `int(11)` | yes | IDX | `NULL` | — |
| 10 | `status` | `varchar(30)` | yes | IDX | `'draft'` | draft,review,approved,published,rejected |
| 11 | `is_ai_generated` | `tinyint(1)` | yes | — | `1` | — |
| 12 | `is_authoritative` | `tinyint(1)` | yes | — | `0` | Only true after human approval |
| 13 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `published_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 17 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_knowledge_events

13 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `event_type` | `varchar(50)` | **no** | — | *NULL* | ingest,search,ask,version_create,version_approve,review,resolution,gap_detected,conflict_detected,freshness_alert,draft_generated,feedback |
| 4 | `entity_type` | `varchar(50)` | yes | IDX | `NULL` | source,collection,version,query,gap,review |
| 5 | `entity_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `user_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `tokens_used` | `int(11)` | yes | — | `0` | — |
| 10 | `cost_usd` | `decimal(10,6)` | yes | — | `0.000000` | — |
| 11 | `latency_ms` | `int(11)` | yes | — | `0` | — |
| 12 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `created_at` | `datetime` | yes | IDX | current_timestamp() | — |

### ai_knowledge_freshness_rules

9 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `rule_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `document_type` | `varchar(100)` | yes | — | `NULL` | policy,sop,procedure,manual,contract,general |
| 5 | `max_age_days` | `int(11)` | yes | — | `NULL` | Maximum age before considered stale |
| 6 | `review_interval_days` | `int(11)` | yes | — | `NULL` | How often to review |
| 7 | `auto_flag` | `tinyint(1)` | yes | — | `1` | — |
| 8 | `enabled` | `tinyint(1)` | yes | — | `1` | — |
| 9 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_knowledge_gaps

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `topic` | `varchar(500)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `evidence` | `longtext` | yes | — | `NULL` | unanswered queries, low-confidence results · binary collation |
| 6 | `affected_users` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `affected_departments` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `frequency` | `int(11)` | yes | IDX | `1` | — |
| 9 | `recommendation` | `text` | yes | — | `NULL` | — |
| 10 | `suggested_owner` | `int(11)` | yes | — | `NULL` | — |
| 11 | `status` | `varchar(30)` | yes | — | `'detected'` | detected,acknowledged,in_progress,resolved,dismissed |
| 12 | `resolution_notes` | `text` | yes | — | `NULL` | — |
| 13 | `resolved_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `resolved_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_knowledge_permissions

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `source_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 3 | `collection_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `permission_type` | `varchar(30)` | **no** | IDX | *NULL* | — |
| 5 | `permission_value` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `access_level` | `varchar(20)` | yes | — | `'read'` | — |
| 7 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_knowledge_queries

24 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `query_text` | `text` | **no** | — | *NULL* | — |
| 5 | `query_type` | `varchar(30)` | yes | IDX | `'search'` | search,ask,copilot,record_level,process_check |
| 6 | `context_type` | `varchar(50)` | yes | — | `NULL` | customer,invoice,po,process,manual |
| 7 | `context_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `results_count` | `int(11)` | yes | — | `0` | — |
| 9 | `top_relevance` | `decimal(5,4)` | yes | — | `NULL` | — |
| 10 | `answer_provided` | `tinyint(1)` | yes | — | `0` | — |
| 11 | `answer_confidence` | `decimal(5,4)` | yes | — | `NULL` | — |
| 12 | `citations_count` | `int(11)` | yes | — | `0` | — |
| 13 | `source_ids` | `longtext` | yes | — | `NULL` | binary collation |
| 14 | `collection_ids` | `longtext` | yes | — | `NULL` | binary collation |
| 15 | `provider` | `varchar(100)` | yes | — | `NULL` | — |
| 16 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 17 | `tokens_used` | `int(11)` | yes | — | `0` | — |
| 18 | `cost_usd` | `decimal(10,6)` | yes | — | `0.000000` | — |
| 19 | `latency_ms` | `int(11)` | yes | — | `0` | — |
| 20 | `no_answer_reason` | `varchar(255)` | yes | — | `NULL` | — |
| 21 | `feedback_rating` | `tinyint(4)` | yes | IDX | `NULL` | 1=helpful,0=neutral,-1=not helpful |
| 22 | `feedback_text` | `text` | yes | — | `NULL` | — |
| 23 | `feedback_type` | `varchar(50)` | yes | — | `NULL` | — |
| 24 | `created_at` | `datetime` | yes | IDX | current_timestamp() | — |

### ai_knowledge_retrieval_logs

8 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `conversation_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `collection_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 5 | `source_ids_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `query_summary` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `chunks_used` | `int(10) unsigned` | yes | — | `0` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### ai_knowledge_reviews

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `source_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `version_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `collection_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `review_type` | `varchar(50)` | **no** | IDX | *NULL* | expired,conflicting,low_quality,low_retrieval,unapproved,outdated,incorrect_answer,gap |
| 7 | `severity` | `varchar(20)` | yes | — | `'normal'` | low,normal,high,urgent |
| 8 | `title` | `varchar(500)` | **no** | — | *NULL* | — |
| 9 | `description` | `text` | yes | — | `NULL` | — |
| 10 | `evidence` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `status` | `varchar(30)` | yes | — | `'pending'` | pending,in_review,resolved,dismissed |
| 12 | `assigned_to` | `int(11)` | yes | IDX | `NULL` | — |
| 13 | `resolution` | `varchar(50)` | yes | — | `NULL` | approved,rejected,replaced,archived,authoritative,owner_assigned |
| 14 | `resolution_notes` | `text` | yes | — | `NULL` | — |
| 15 | `resolved_by` | `int(11)` | yes | — | `NULL` | — |
| 16 | `resolved_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_knowledge_sources

23 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `collection_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_knowledge_collections.id (on delete cascade) |
| 3 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `source_type` | `enum('file','text')` | **no** | — | `'text'` | — |
| 5 | `original_filename` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `storage_path` | `varchar(500)` | yes | — | `NULL` | — |
| 7 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `file_size` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `checksum` | `varchar(64)` | yes | — | `NULL` | — |
| 10 | `status` | `enum('uploaded','extracting','chunking','embedding','indexed','failed')` | **no** | IDX | `'uploaded'` | — |
| 11 | `classification` | `varchar(30)` | yes | — | `'internal'` | — |
| 12 | `effective_date` | `date` | yes | — | `NULL` | — |
| 13 | `expiration_date` | `date` | yes | — | `NULL` | — |
| 14 | `review_due_date` | `date` | yes | — | `NULL` | — |
| 15 | `last_reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `source_authority` | `varchar(100)` | yes | — | `NULL` | — |
| 17 | `tags` | `longtext` | yes | — | `NULL` | binary collation |
| 18 | `extraction_status` | `varchar(255)` | yes | — | `NULL` | — |
| 19 | `chunk_count` | `int(10) unsigned` | yes | — | `0` | — |
| 20 | `current_version_id` | `int(11)` | yes | — | `NULL` | — |
| 21 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 22 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 23 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_knowledge_versions

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `source_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `version_number` | `int(11)` | **no** | — | `1` | — |
| 4 | `title` | `varchar(500)` | **no** | — | *NULL* | — |
| 5 | `content` | `longtext` | **no** | — | *NULL* | — |
| 6 | `content_hash` | `varchar(64)` | **no** | — | *NULL* | — |
| 7 | `effective_date` | `date` | yes | IDX | `NULL` | — |
| 8 | `expiration_date` | `date` | yes | IDX | `NULL` | — |
| 9 | `superseded_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `approval_status` | `varchar(30)` | yes | IDX | `'draft'` | — |
| 11 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 12 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `published_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 15 | `change_summary` | `text` | yes | — | `NULL` | — |
| 16 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 17 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_loop_detection

18 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `session_key` | `varchar(120)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `user_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 6 | `tool_name` | `varchar(120)` | yes | — | `NULL` | — |
| 7 | `action_type` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `call_count` | `int(11)` | **no** | — | `0` | — |
| 9 | `consecutive_failures` | `int(11)` | **no** | — | `0` | — |
| 10 | `last_call_at` | `datetime` | **no** | — | *NULL* | — |
| 11 | `first_call_at` | `datetime` | **no** | — | *NULL* | — |
| 12 | `pattern_type` | `enum('repeated_call','cyclic_execution','failure_spiral','no_progress','stuck')` | yes | IDX | `NULL` | — |
| 13 | `detection_method` | `enum('identical_calls','similar_calls','failure_rate','time_based','entropy')` | yes | — | `NULL` | — |
| 14 | `threshold_exceeded` | `tinyint(1)` | **no** | IDX | `0` | — |
| 15 | `paused` | `tinyint(1)` | **no** | IDX | `0` | — |
| 16 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_loop_detection_events

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `conversation_id` | `int(11)` | yes | IDX | `NULL` | — |
| 3 | `loop_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `loop_count` | `int(11)` | **no** | — | `0` | — |
| 5 | `detected_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 6 | `context_json` | `longtext` | yes | — | `NULL` | — |

### ai_masking_policies

8 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `field_pattern` | `varchar(255)` | **no** | — | *NULL* | — |
| 3 | `masking_type` | `enum('none','partial','full','exclude')` | **no** | — | `'none'` | — |
| 4 | `module` | `varchar(50)` | yes | IDX | `NULL` | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_masking_rules

13 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `rule_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `field_pattern` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `field_type` | `enum('email','phone','bank','tax_id','personal','credential','api_key','token','password','ssn','ip_address','custom')` | **no** | IDX | *NULL* | — |
| 5 | `masking_type` | `enum('full','partial','hash','redact','replace')` | **no** | — | `'full'` | — |
| 6 | `mask_character` | `varchar(10)` | **no** | — | `'*'` | — |
| 7 | `visible_prefix` | `int(11)` | **no** | — | `0` | — |
| 8 | `visible_suffix` | `int(11)` | **no** | — | `0` | — |
| 9 | `replacement_text` | `varchar(50)` | yes | — | `'[REDACTED]'` | — |
| 10 | `apply_to_contexts` | `longtext` | **no** | — | `'["ai_provider","external_connector","logs","observability","analytics"]'` | binary collation |
| 11 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_messages

8 columns · 179 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `conversation_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_conversations.id (on delete cascade) |
| 3 | `role` | `varchar(20)` | **no** | — | *NULL* | system|user|assistant |
| 4 | `content` | `text` | **no** | — | *NULL* | — |
| 5 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `input_tokens` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `output_tokens` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_metric_definitions

20 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `metric_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `category` | `enum('finance','procurement','inventory','sales','hr','operational','custom')` | **no** | IDX | `'finance'` | — |
| 6 | `source_table` | `varchar(100)` | **no** | — | *NULL* | — |
| 7 | `source_column` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `calculation_type` | `enum('sum','count','avg','min','max','ratio','percentage','custom')` | **no** | — | `'sum'` | — |
| 9 | `calculation_sql` | `text` | yes | — | `NULL` | — |
| 10 | `allowed_dimensions` | `longtext` | **no** | — | *NULL* | binary collation |
| 11 | `allowed_filters` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `data_classification` | `enum('public','internal','confidential','restricted')` | **no** | — | `'internal'` | — |
| 13 | `currency_dependent` | `tinyint(1)` | **no** | — | `0` | — |
| 14 | `base_currency_column` | `varchar(100)` | yes | — | `NULL` | — |
| 15 | `aggregation_behavior` | `enum('sum','average','latest','weighted')` | **no** | — | `'sum'` | — |
| 16 | `permissions_required` | `varchar(100)` | yes | — | `NULL` | — |
| 17 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 18 | `metadata` | `longtext` | yes | — | `NULL` | binary collation |
| 19 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_models

21 columns · 9 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `provider_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | ai_providers.id (on delete cascade) |
| 3 | `model_id` | `varchar(150)` | **no** | — | *NULL* | Provider's model identifier (e.g. gpt-4o) |
| 4 | `display_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `model_type` | `varchar(50)` | yes | IDX | `'chat'` | Primary type: chat, reasoning, embedding, vision, multimodal |
| 7 | `embedding_dimensions` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `context_window` | `int(10) unsigned` | yes | — | `NULL` | Max context tokens |
| 9 | `max_output_tokens` | `int(10) unsigned` | yes | — | `NULL` | Max output tokens |
| 10 | `supports_chat` | `tinyint(1)` | **no** | — | `1` | — |
| 11 | `supports_tool_calling` | `tinyint(1)` | **no** | — | `0` | — |
| 12 | `supports_vision` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `supports_embeddings` | `tinyint(1)` | **no** | — | `0` | — |
| 14 | `supports_structured_output` | `tinyint(1)` | **no** | — | `0` | — |
| 15 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 16 | `is_default` | `tinyint(1)` | **no** | IDX | `0` | — |
| 17 | `configuration_json` | `longtext` | yes | — | `NULL` | binary collation |
| 18 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 20 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 21 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |

### ai_observability_benchmarks

11 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `benchmark_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(180)` | **no** | — | *NULL* | — |
| 4 | `category` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `status` | `enum('draft','active','archived')` | **no** | IDX | `'draft'` | — |
| 7 | `tags_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 9 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_benchmark_results

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `benchmark_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `evaluation_run_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `provider_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `model_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `result_date` | `date` | **no** | IDX | *NULL* | — |
| 7 | `score` | `decimal(6,4)` | **no** | — | `0.0000` | — |
| 8 | `latency_ms` | `int(11)` | **no** | — | `0` | — |
| 9 | `cost_estimate` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 10 | `summary_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_observability_daily_aggregates

24 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `aggregate_date` | `date` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `provider_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `model_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `feature` | `varchar(80)` | **no** | — | `'ai_chat'` | — |
| 7 | `tool_name` | `varchar(120)` | yes | — | `NULL` | — |
| 8 | `status` | `varchar(40)` | **no** | — | `'success'` | — |
| 9 | `request_count` | `int(11)` | **no** | — | `0` | — |
| 10 | `success_count` | `int(11)` | **no** | — | `0` | — |
| 11 | `failure_count` | `int(11)` | **no** | — | `0` | — |
| 12 | `blocked_count` | `int(11)` | **no** | — | `0` | — |
| 13 | `cancelled_count` | `int(11)` | **no** | — | `0` | — |
| 14 | `avg_latency_ms` | `decimal(12,2)` | **no** | — | `0.00` | — |
| 15 | `p50_latency_ms` | `decimal(12,2)` | **no** | — | `0.00` | — |
| 16 | `p95_latency_ms` | `decimal(12,2)` | **no** | — | `0.00` | — |
| 17 | `p99_latency_ms` | `decimal(12,2)` | **no** | — | `0.00` | — |
| 18 | `total_input_tokens` | `bigint(20)` | **no** | — | `0` | — |
| 19 | `total_output_tokens` | `bigint(20)` | **no** | — | `0` | — |
| 20 | `total_cost` | `decimal(14,6)` | **no** | — | `0.000000` | — |
| 21 | `quality_score_avg` | `decimal(6,4)` | **no** | — | `0.0000` | — |
| 22 | `feedback_total` | `int(11)` | **no** | — | `0` | — |
| 23 | `negative_feedback_total` | `int(11)` | **no** | — | `0` | — |
| 24 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_observability_eval_cases

19 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `dataset_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `case_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 4 | `name` | `varchar(180)` | **no** | — | *NULL* | — |
| 5 | `category` | `varchar(80)` | **no** | IDX | `'response_quality'` | — |
| 6 | `input_text` | `mediumtext` | **no** | — | *NULL* | — |
| 7 | `expected_output_criteria` | `text` | yes | — | `NULL` | — |
| 8 | `expected_tool` | `varchar(120)` | yes | — | `NULL` | — |
| 9 | `expected_intent` | `varchar(120)` | yes | — | `NULL` | — |
| 10 | `expected_schema_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `expected_response_contains_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `safety_requirements_json` | `longtext` | yes | — | `NULL` | — |
| 13 | `tags_json` | `longtext` | yes | — | `NULL` | — |
| 14 | `version_label` | `varchar(40)` | **no** | — | `'1.0'` | — |
| 15 | `status` | `enum('draft','active','deprecated')` | **no** | IDX | `'draft'` | — |
| 16 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 17 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_eval_datasets

12 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `dataset_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(180)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `evaluation_type` | `varchar(80)` | **no** | IDX | `'response_quality'` | — |
| 6 | `version_no` | `int(11)` | **no** | — | `1` | — |
| 7 | `status` | `enum('draft','active','deprecated')` | **no** | IDX | `'draft'` | — |
| 8 | `tags_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_eval_results

16 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `case_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `status` | `enum('success','partial_success','failed','blocked','cancelled','timeout')` | **no** | — | `'success'` | — |
| 5 | `actual_intent` | `varchar(120)` | yes | — | `NULL` | — |
| 6 | `actual_tool` | `varchar(120)` | yes | — | `NULL` | — |
| 7 | `actual_response_summary` | `text` | yes | — | `NULL` | — |
| 8 | `schema_valid` | `tinyint(1)` | **no** | — | `0` | — |
| 9 | `grounded_status` | `enum('grounded','partially_grounded','ungrounded')` | **no** | — | `'partially_grounded'` | — |
| 10 | `safety_status` | `enum('passed','warning','failed')` | **no** | — | `'passed'` | — |
| 11 | `score` | `decimal(6,4)` | **no** | IDX | `0.0000` | — |
| 12 | `scoring_breakdown_json` | `longtext` | yes | — | `NULL` | — |
| 13 | `latency_ms` | `int(11)` | **no** | — | `0` | — |
| 14 | `estimated_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 15 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_observability_eval_runs

19 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `dataset_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `provider_id` | `int(11)` | yes | IDX | `NULL` | — |
| 6 | `model_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `started_by` | `int(11)` | yes | — | `NULL` | — |
| 8 | `run_mode` | `enum('read_only','dry_run','mock_tools','test_company')` | **no** | — | `'read_only'` | — |
| 9 | `status` | `enum('queued','running','completed','failed','cancelled')` | **no** | IDX | `'queued'` | — |
| 10 | `total_cases` | `int(11)` | **no** | — | `0` | — |
| 11 | `completed_cases` | `int(11)` | **no** | — | `0` | — |
| 12 | `average_score` | `decimal(6,4)` | **no** | — | `0.0000` | — |
| 13 | `average_latency_ms` | `decimal(12,2)` | **no** | — | `0.00` | — |
| 14 | `estimated_total_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 15 | `summary_json` | `longtext` | yes | — | `NULL` | — |
| 16 | `started_at` | `datetime` | **no** | — | current_timestamp() | — |
| 17 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_experiments

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `experiment_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(180)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `experiment_type` | `varchar(80)` | **no** | — | `'model_comparison'` | — |
| 6 | `status` | `enum('draft','testing','active','archived')` | **no** | IDX | `'draft'` | — |
| 7 | `variant_a_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `variant_b_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `metrics_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 11 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 12 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_feedback

14 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `request_id` | `int(11)` | yes | IDX | `NULL` | — |
| 3 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 4 | `conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `company_id` | `int(11)` | **no** | IDX | `1` | — |
| 6 | `user_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `feedback_type` | `enum('helpful','not_helpful','report_problem')` | **no** | — | *NULL* | — |
| 8 | `reason_code` | `enum('incorrect','incomplete','unsafe','slow','irrelevant','other')` | yes | — | `NULL` | — |
| 9 | `category` | `enum('quality','safety','performance','provider','tool','knowledge','other')` | yes | — | `NULL` | — |
| 10 | `comment` | `text` | yes | — | `NULL` | — |
| 11 | `status` | `enum('new','reviewing','resolved','dismissed')` | **no** | IDX | `'new'` | — |
| 12 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 13 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 14 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_feedback_reviews

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `feedback_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `reviewer_user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `review_status` | `enum('queued','categorized','resolved')` | **no** | — | `'queued'` | — |
| 5 | `reviewer_notes` | `text` | yes | — | `NULL` | — |
| 6 | `linked_eval_case_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `resolution_notes` | `text` | yes | — | `NULL` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_findings

16 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `finding_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `title` | `varchar(180)` | **no** | — | *NULL* | — |
| 4 | `finding_type` | `varchar(80)` | **no** | — | `'anomaly'` | — |
| 5 | `severity` | `enum('info','warning','critical')` | **no** | IDX | `'warning'` | — |
| 6 | `affected_type` | `varchar(80)` | yes | — | `NULL` | — |
| 7 | `affected_reference` | `varchar(120)` | yes | — | `NULL` | — |
| 8 | `status` | `enum('open','acknowledged','investigating','resolved','ignored')` | **no** | IDX | `'open'` | — |
| 9 | `dedupe_key` | `varchar(180)` | yes | IDX | `NULL` | — |
| 10 | `details_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `recommendation_text` | `text` | yes | — | `NULL` | — |
| 12 | `first_detected_at` | `datetime` | **no** | — | current_timestamp() | — |
| 13 | `last_detected_at` | `datetime` | **no** | — | current_timestamp() | — |
| 14 | `cooldown_until` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_reports

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `report_key` | `varchar(120)` | **no** | UQ | *NULL* | — |
| 3 | `report_type` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 4 | `title` | `varchar(180)` | **no** | — | *NULL* | — |
| 5 | `scope_company_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `generated_by` | `int(11)` | yes | — | `NULL` | — |
| 7 | `report_date` | `date` | **no** | IDX | *NULL* | — |
| 8 | `summary_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_observability_requests

36 columns · 36 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `request_key` | `varchar(80)` | **no** | UQ | *NULL* | — |
| 3 | `trace_id` | `varchar(80)` | **no** | — | *NULL* | — |
| 4 | `company_id` | `int(11)` | **no** | IDX | `1` | — |
| 5 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 6 | `conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `command_conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `session_reference` | `varchar(120)` | yes | — | `NULL` | — |
| 9 | `entry_point` | `varchar(80)` | **no** | — | `'assistant'` | — |
| 10 | `provider_id` | `int(11)` | yes | IDX | `NULL` | — |
| 11 | `model_id` | `int(11)` | yes | IDX | `NULL` | — |
| 12 | `feature` | `varchar(80)` | **no** | IDX | `'ai_chat'` | — |
| 13 | `started_at` | `datetime` | **no** | IDX | *NULL* | — |
| 14 | `finished_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 16 | `queue_wait_ms` | `int(11)` | **no** | — | `0` | — |
| 17 | `status` | `enum('success','partial_success','failed','blocked','cancelled','timeout')` | **no** | IDX | `'success'` | — |
| 18 | `error_category` | `enum('provider_error','timeout','rate_limit','validation_error','permission_denied','policy_blocked','tool_failure','context_failure','unknown')` | yes | — | `NULL` | — |
| 19 | `input_tokens` | `int(11)` | **no** | — | `0` | — |
| 20 | `output_tokens` | `int(11)` | **no** | — | `0` | — |
| 21 | `total_tokens` | `int(11)` | **no** | — | `0` | — |
| 22 | `estimated_input_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 23 | `estimated_output_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 24 | `estimated_tool_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 25 | `estimated_ocr_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 26 | `estimated_stt_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 27 | `estimated_tts_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 28 | `estimated_embedding_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 29 | `estimated_total_cost` | `decimal(12,6)` | **no** | — | `0.000000` | — |
| 30 | `content_mode` | `enum('metadata_only','masked_content','full_content')` | **no** | — | `'metadata_only'` | — |
| 31 | `sampled_content` | `tinyint(1)` | **no** | — | `0` | — |
| 32 | `prompt_summary` | `mediumtext` | yes | — | `NULL` | — |
| 33 | `response_summary` | `mediumtext` | yes | — | `NULL` | — |
| 34 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 35 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 36 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_observability_spans

19 columns · 102 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `request_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `trace_id` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 4 | `span_key` | `varchar(80)` | **no** | UQ | *NULL* | — |
| 5 | `parent_span_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `span_type` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 7 | `span_name` | `varchar(120)` | **no** | — | *NULL* | — |
| 8 | `feature` | `varchar(80)` | yes | — | `NULL` | — |
| 9 | `tool_name` | `varchar(120)` | yes | IDX | `NULL` | — |
| 10 | `started_at` | `datetime` | **no** | — | *NULL* | — |
| 11 | `finished_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 13 | `status` | `enum('success','partial_success','failed','blocked','cancelled','timeout')` | **no** | — | `'success'` | — |
| 14 | `error_category` | `enum('provider_error','timeout','rate_limit','validation_error','permission_denied','policy_blocked','tool_failure','context_failure','unknown')` | yes | — | `NULL` | — |
| 15 | `safe_input_summary` | `mediumtext` | yes | — | `NULL` | — |
| 16 | `safe_output_summary` | `mediumtext` | yes | — | `NULL` | — |
| 17 | `safe_metadata_json` | `longtext` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_parameter_schemas

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `schema_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `action_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `tool_name` | `varchar(120)` | yes | IDX | `NULL` | — |
| 5 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `schema_json` | `longtext` | **no** | — | *NULL* | — |
| 7 | `validation_rules_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_pending_actions

14 columns · 19 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `conversation_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `action_id` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `action_input_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 6 | `action_preview_json` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `status` | `enum('pending','confirmed','executing','completed','cancelled','expired','failed')` | **no** | — | `'pending'` | — |
| 8 | `confirmation_token` | `varchar(64)` | **no** | — | *NULL* | — |
| 9 | `confirmation_token_hash` | `varchar(64)` | **no** | IDX | *NULL* | — |
| 10 | `expires_at` | `datetime` | **no** | IDX | *NULL* | — |
| 11 | `executed_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `execution_result_json` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 14 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_policies

16 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `policy_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `type` | `enum('ai_usage','data_access','data_transmission','tool_usage','action_execution','external_provider','document','voice','integration','automation','knowledge','logging','retention')` | **no** | IDX | *NULL* | — |
| 6 | `scope` | `enum('global','company','department','role','user','feature','tool','provider','document_type','data_classification','integration')` | **no** | IDX | *NULL* | — |
| 7 | `scope_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `conditions_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `effect` | `enum('allow','deny','require_confirmation','require_review','mask_data','restrict_provider','log_only')` | **no** | — | `'allow'` | — |
| 10 | `priority` | `int(11)` | **no** | IDX | `0` | — |
| 11 | `status` | `enum('active','inactive','draft','archived')` | **no** | IDX | `'draft'` | — |
| 12 | `version` | `int(11)` | **no** | — | `1` | — |
| 13 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_policy_simulations

5 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `input_json` | `longtext` | yes | — | `NULL` | — |
| 3 | `result_json` | `longtext` | yes | — | `NULL` | — |
| 4 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 5 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_policy_versions

11 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `policy_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_policies.id (on delete cascade) |
| 3 | `version` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `change_reason` | `text` | yes | — | `NULL` | — |
| 5 | `author_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `previous_version` | `int(11)` | yes | — | `NULL` | — |
| 7 | `conditions_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `effect` | `enum('allow','deny','require_confirmation','require_review','mask_data','restrict_provider','log_only')` | **no** | — | *NULL* | — |
| 9 | `priority` | `int(11)` | **no** | — | `0` | — |
| 10 | `status` | `enum('active','inactive','draft','archived')` | **no** | — | *NULL* | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_process_alerts

12 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `alert_type` | `varchar(80)` | **no** | — | *NULL* | — |
| 5 | `threshold_config_json` | `longtext` | yes | — | `NULL` | — |
| 6 | `schedule_json` | `longtext` | yes | — | `NULL` | — |
| 7 | `last_evaluated_at` | `datetime` | yes | — | `NULL` | — |
| 8 | `last_triggered_at` | `datetime` | yes | — | `NULL` | — |
| 9 | `status` | `varchar(40)` | **no** | — | `'active'` | — |
| 10 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_definitions

20 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `source_modules` | `longtext` | yes | — | `NULL` | — |
| 7 | `entity_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `start_event` | `varchar(120)` | **no** | — | *NULL* | — |
| 9 | `completion_event` | `varchar(120)` | yes | — | `NULL` | — |
| 10 | `expected_stages` | `longtext` | yes | — | `NULL` | — |
| 11 | `sla_target_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 12 | `business_dimensions` | `longtext` | yes | — | `NULL` | — |
| 13 | `visibility` | `varchar(50)` | **no** | — | `'company'` | — |
| 14 | `permissions_required` | `varchar(120)` | yes | — | `NULL` | — |
| 15 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 16 | `metadata` | `longtext` | yes | — | `NULL` | — |
| 17 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 18 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 19 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_events

22 columns · 53 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `process_instance_id` | `varchar(190)` | **no** | — | *NULL* | — |
| 5 | `entity_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 6 | `entity_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `event_name` | `varchar(120)` | **no** | — | *NULL* | — |
| 8 | `event_type` | `varchar(80)` | **no** | — | `'status_change'` | — |
| 9 | `actor_user_id` | `int(11)` | yes | — | `NULL` | — |
| 10 | `actor_name` | `varchar(150)` | yes | — | `NULL` | — |
| 11 | `department_id` | `int(11)` | yes | — | `NULL` | — |
| 12 | `branch` | `varchar(120)` | yes | — | `NULL` | — |
| 13 | `role_name` | `varchar(120)` | yes | — | `NULL` | — |
| 14 | `event_time` | `datetime` | **no** | — | *NULL* | — |
| 15 | `status` | `varchar(80)` | yes | — | `NULL` | — |
| 16 | `source_module` | `varchar(80)` | **no** | — | *NULL* | — |
| 17 | `source_table` | `varchar(120)` | **no** | IDX | *NULL* | — |
| 18 | `source_row_id` | `int(11)` | yes | — | `NULL` | — |
| 19 | `currency_code` | `varchar(10)` | yes | — | `NULL` | — |
| 20 | `amount_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 21 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 22 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_process_findings

18 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `finding_type` | `varchar(80)` | **no** | — | *NULL* | — |
| 5 | `severity` | `varchar(20)` | **no** | — | `'medium'` | — |
| 6 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 7 | `summary` | `text` | **no** | — | *NULL* | — |
| 8 | `stage_name` | `varchar(120)` | yes | — | `NULL` | — |
| 9 | `transition_name` | `varchar(190)` | yes | — | `NULL` | — |
| 10 | `evidence_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `metric_snapshot_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `recommendation_text` | `text` | yes | — | `NULL` | — |
| 13 | `status` | `varchar(40)` | **no** | — | `'open'` | — |
| 14 | `fingerprint` | `varchar(255)` | **no** | — | *NULL* | — |
| 15 | `detected_at` | `datetime` | **no** | — | *NULL* | — |
| 16 | `resolved_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 18 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_metrics

21 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `metric_date` | `date` | **no** | — | *NULL* | — |
| 5 | `dimension_key` | `varchar(80)` | **no** | — | `'all'` | — |
| 6 | `dimension_value` | `varchar(190)` | **no** | — | `'all'` | — |
| 7 | `instance_count` | `int(11)` | **no** | — | `0` | — |
| 8 | `completed_count` | `int(11)` | **no** | — | `0` | — |
| 9 | `active_count` | `int(11)` | **no** | — | `0` | — |
| 10 | `abandoned_count` | `int(11)` | **no** | — | `0` | — |
| 11 | `breach_count` | `int(11)` | **no** | — | `0` | — |
| 12 | `avg_cycle_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 13 | `median_cycle_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 14 | `p90_cycle_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 15 | `avg_approval_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 16 | `avg_rework_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 17 | `avg_queue_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 18 | `sample_size` | `int(11)` | **no** | — | `0` | — |
| 19 | `metric_payload_json` | `longtext` | yes | — | `NULL` | — |
| 20 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 21 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_proposals

17 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `recommendation_id` | `bigint(20)` | yes | — | `NULL` | — |
| 5 | `workflow_id` | `int(11)` | yes | IDX | `NULL` | — |
| 6 | `workflow_version_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 8 | `summary` | `text` | yes | — | `NULL` | — |
| 9 | `proposal_payload_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `simulation_result_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `approval_status` | `varchar(40)` | **no** | — | `'draft'` | — |
| 12 | `governance_status` | `varchar(40)` | **no** | — | `'pending_review'` | — |
| 13 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 14 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_recommendations

16 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `finding_id` | `bigint(20)` | yes | — | `NULL` | — |
| 5 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `recommendation_text` | `text` | **no** | — | *NULL* | — |
| 7 | `expected_impact` | `varchar(255)` | yes | — | `NULL` | — |
| 8 | `rationale` | `text` | yes | — | `NULL` | — |
| 9 | `evidence_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `advisory_only` | `tinyint(1)` | **no** | — | `1` | — |
| 11 | `priority_level` | `varchar(20)` | **no** | — | `'medium'` | — |
| 12 | `status` | `varchar(40)` | **no** | — | `'open'` | — |
| 13 | `fingerprint` | `varchar(255)` | **no** | — | *NULL* | — |
| 14 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_runs

31 columns · 22 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `process_instance_id` | `varchar(190)` | **no** | — | *NULL* | — |
| 5 | `entity_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 6 | `entity_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `department_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `branch` | `varchar(120)` | yes | — | `NULL` | — |
| 9 | `currency_code` | `varchar(10)` | yes | — | `NULL` | — |
| 10 | `process_version` | `varchar(80)` | yes | — | `NULL` | — |
| 11 | `run_status` | `varchar(80)` | **no** | — | `'active'` | — |
| 12 | `started_at` | `datetime` | **no** | — | *NULL* | — |
| 13 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `cycle_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 15 | `waiting_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 16 | `active_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 17 | `approval_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 18 | `queue_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 19 | `handoff_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 20 | `rework_time_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 21 | `handoff_count` | `int(11)` | **no** | — | `0` | — |
| 22 | `rework_count` | `int(11)` | **no** | — | `0` | — |
| 23 | `sla_target_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 24 | `sla_status` | `varchar(40)` | **no** | — | `'unknown'` | — |
| 25 | `breach_hours` | `decimal(12,2)` | yes | — | `NULL` | — |
| 26 | `event_count` | `int(11)` | **no** | — | `0` | — |
| 27 | `last_event_name` | `varchar(120)` | yes | — | `NULL` | — |
| 28 | `last_event_at` | `datetime` | yes | — | `NULL` | — |
| 29 | `dimensions_json` | `longtext` | yes | — | `NULL` | — |
| 30 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 31 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_process_simulations

10 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `variables_json` | `longtext` | **no** | — | *NULL* | — |
| 6 | `baseline_snapshot_json` | `longtext` | **no** | — | *NULL* | — |
| 7 | `simulation_result_json` | `longtext` | **no** | — | *NULL* | — |
| 8 | `safety_label` | `varchar(200)` | **no** | — | `'Simulation / Estimate — not actual ERP results.'` | — |
| 9 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_process_versions

7 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `process_key` | `varchar(120)` | **no** | — | *NULL* | — |
| 4 | `version_number` | `int(11)` | **no** | — | `1` | — |
| 5 | `definition_snapshot_json` | `longtext` | **no** | — | *NULL* | — |
| 6 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_prompt_versions

12 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `prompt_key` | `varchar(120)` | **no** | IDX | *NULL* | — |
| 3 | `version_no` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `feature` | `varchar(80)` | **no** | IDX | *NULL* | — |
| 5 | `status` | `enum('draft','testing','active','archived')` | **no** | IDX | `'draft'` | — |
| 6 | `change_summary` | `text` | yes | — | `NULL` | — |
| 7 | `prompt_text` | `mediumtext` | **no** | — | *NULL* | — |
| 8 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 9 | `activated_by` | `int(11)` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `activated_at` | `datetime` | yes | — | `NULL` | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_providers

19 columns · 11 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 3 | `slug` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 4 | `provider_type` | `varchar(50)` | **no** | IDX | `'openai'` | — |
| 5 | `base_url` | `varchar(500)` | yes | — | `NULL` | — |
| 6 | `api_key_encrypted` | `varbinary(1024)` | yes | — | `NULL` | — |
| 7 | `api_key_iv` | `varbinary(128)` | yes | — | `NULL` | — |
| 8 | `configuration_json` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 10 | `is_default` | `tinyint(1)` | **no** | IDX | `0` | — |
| 11 | `last_tested_at` | `timestamp` | yes | — | `NULL` | — |
| 12 | `last_test_status` | `varchar(20)` | yes | — | `NULL` | success|failed|pending |
| 13 | `last_connection_status` | `varchar(20)` | yes | — | `'never_tested'` | never_tested|success|failed |
| 14 | `last_connection_at` | `timestamp` | yes | — | `NULL` | — |
| 15 | `last_connection_message` | `varchar(500)` | yes | — | `NULL` | — |
| 16 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 17 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 18 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 19 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |

### ai_provider_rules

13 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `provider_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `role_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `allowed` | `tinyint(1)` | **no** | — | `1` | — |
| 6 | `allowed_for_classification` | `enum('public','internal','confidential','sensitive','restricted')` | yes | — | `NULL` | — |
| 7 | `allowed_features_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `max_cost_per_day` | `decimal(12,2)` | yes | — | `NULL` | — |
| 9 | `max_tokens_per_request` | `int(11)` | yes | — | `NULL` | — |
| 10 | `rate_limit_per_minute` | `int(11)` | yes | — | `NULL` | — |
| 11 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_rate_limits

10 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `limit_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `limit_type` | `enum('per_minute','per_hour','per_day')` | **no** | — | *NULL* | — |
| 4 | `max_requests` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 5 | `user_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 6 | `company_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 7 | `feature` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_rate_limit_configs

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `scope_type` | `enum('user','company','provider','model','feature','tool','integration')` | **no** | IDX | *NULL* | — |
| 3 | `scope_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `requests_per_minute` | `int(11)` | **no** | — | `30` | — |
| 5 | `requests_per_hour` | `int(11)` | **no** | — | `500` | — |
| 6 | `requests_per_day` | `int(11)` | **no** | — | `5000` | — |
| 7 | `concurrent_requests` | `int(11)` | **no** | — | `5` | — |
| 8 | `burst_size` | `int(11)` | **no** | — | `10` | — |
| 9 | `cooldown_seconds` | `int(11)` | **no** | — | `60` | — |
| 10 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_rate_limit_counters

11 columns · 36 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `limit_key` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `scope` | `varchar(50)` | **no** | — | `'user'` | — |
| 4 | `user_id` | `int(10) unsigned` | **no** | — | `0` | — |
| 5 | `company_id` | `int(10) unsigned` | **no** | — | `0` | — |
| 6 | `feature` | `varchar(50)` | **no** | — | `'*'` | — |
| 7 | `window_start` | `datetime` | **no** | — | *NULL* | — |
| 8 | `window_end` | `datetime` | **no** | IDX | *NULL* | — |
| 9 | `request_count` | `int(10) unsigned` | **no** | — | `1` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_research_notes

7 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `note_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `session_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_research_sessions.session_id (on delete cascade) |
| 3 | `author_user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `note_type` | `enum('question','finding','note','summary')` | **no** | — | `'note'` | — |
| 5 | `content` | `text` | **no** | — | *NULL* | — |
| 6 | `finding_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_research_sessions

8 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `session_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `objective` | `text` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','completed','archived')` | **no** | — | `'active'` | — |
| 6 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_retention_policies

7 columns · 14 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `data_type` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `retention_days` | `int(10) unsigned` | **no** | — | `90` | — |
| 4 | `last_cleanup_at` | `timestamp` | yes | — | `NULL` | — |
| 5 | `enabled` | `tinyint(1)` | **no** | — | `1` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_review_queue

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `item_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `request_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `trace_id` | `varchar(80)` | yes | — | `NULL` | — |
| 5 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `user_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `item_type` | `enum('action','automation','integration','document','workflow','other')` | **no** | — | *NULL* | — |
| 8 | `item_data_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `risk_level` | `enum('low','medium','high','critical')` | **no** | IDX | *NULL* | — |
| 10 | `priority` | `enum('low','medium','high','critical')` | **no** | IDX | `'medium'` | — |
| 11 | `status` | `enum('pending','approved','rejected','escalated')` | **no** | IDX | `'pending'` | — |
| 12 | `assigned_to` | `int(11)` | yes | IDX | `NULL` | — |
| 13 | `review_notes` | `text` | yes | — | `NULL` | — |
| 14 | `reviewed_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_risk_classifications

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `rule_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `level` | `enum('low','medium','high','critical')` | **no** | IDX | *NULL* | — |
| 6 | `category` | `enum('action','data','operation','integration','automation')` | **no** | IDX | *NULL* | — |
| 7 | `default_risk` | `enum('low','medium','high','critical')` | **no** | — | `'low'` | — |
| 8 | `auto_detect` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `conditions_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_risk_rules

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `rule_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 3 | `rule_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 4 | `classification_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `match_type` | `enum('keyword','pattern','amount','count','field_value')` | **no** | — | *NULL* | — |
| 6 | `match_value` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `risk_level` | `enum('low','medium','high','critical')` | **no** | IDX | *NULL* | — |
| 8 | `priority` | `int(11)` | **no** | — | `0` | — |
| 9 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_safety_policies

11 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `policy_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `policy_name` | `varchar(255)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `max_value` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 6 | `current_value` | `int(10) unsigned` | yes | — | `0` | — |
| 7 | `scope` | `enum('system','company','feature')` | **no** | IDX | `'system'` | — |
| 8 | `target_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `enabled` | `tinyint(1)` | **no** | IDX | `1` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_saved_analyses

16 columns · 13 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `metric_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 7 | `dimensions` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `filters` | `longtext` | yes | — | `NULL` | binary collation |
| 9 | `comparison_type` | `varchar(50)` | yes | — | `NULL` | — |
| 10 | `forecast_settings` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `scenario_settings` | `longtext` | yes | — | `NULL` | binary collation |
| 12 | `visibility` | `enum('private','team','company')` | **no** | — | `'private'` | — |
| 13 | `refresh_schedule` | `text` | yes | — | `NULL` | — |
| 14 | `last_run_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_scenarios

15 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `base_metric_key` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 7 | `variables` | `longtext` | **no** | — | *NULL* | binary collation |
| 8 | `baseline_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 9 | `scenario_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 10 | `impact_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 11 | `impact_percentage` | `decimal(10,4)` | yes | — | `NULL` | — |
| 12 | `affected_records` | `longtext` | yes | — | `NULL` | binary collation |
| 13 | `simulation_result` | `longtext` | yes | — | `NULL` | binary collation |
| 14 | `safety_label` | `varchar(200)` | **no** | — | `'Simulation — no ERP data has been changed'` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_scenario_variables

9 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `variable_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `data_type` | `enum('percentage','number','currency','days')` | **no** | — | `'percentage'` | — |
| 6 | `min_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 7 | `max_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 8 | `default_value` | `decimal(20,4)` | yes | — | `NULL` | — |
| 9 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |

### ai_scheduler_heartbeats

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `scheduler_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `status` | `enum('healthy','warning','degraded','unavailable','unknown')` | **no** | — | `'unknown'` | — |
| 4 | `last_heartbeat_at` | `timestamp` | yes | — | `NULL` | — |
| 5 | `last_successful_cycle_at` | `timestamp` | yes | — | `NULL` | — |
| 6 | `last_failure_at` | `timestamp` | yes | — | `NULL` | — |
| 7 | `expected_interval_seconds` | `int(10) unsigned` | **no** | — | `60` | — |
| 8 | `due_jobs` | `int(10) unsigned` | **no** | — | `0` | — |
| 9 | `delayed_jobs` | `int(10) unsigned` | **no** | — | `0` | — |
| 10 | `last_result_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_scheduler_locks

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `automation_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 3 | `lock_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 4 | `locked_by` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `locked_at` | `datetime` | **no** | — | *NULL* | — |
| 6 | `expires_at` | `datetime` | **no** | IDX | *NULL* | — |

### ai_security_events

19 columns · 33 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `event_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `event_type` | `enum('policy_violation','permission_denied','prompt_injection_attempt','unauthorized_tool','blocked_provider','sensitive_data_leak_attempt','repeated_failed_auth','abnormal_usage','autonomy_limit_exceeded','cost_limit_exceeded','rate_limit_exceeded','injection_detected','data_masking_triggered','confirmation_required','review_required','kill_switch_triggered','provider_restricted','action_blocked','sql_safety_violation','parameter_validation_failed')` | **no** | IDX | *NULL* | — |
| 4 | `severity` | `enum('info','warning','high','critical')` | **no** | IDX | *NULL* | — |
| 5 | `request_id` | `int(11)` | yes | IDX FK | `NULL` | ai_observability_requests.id (on delete set null) |
| 6 | `trace_id` | `varchar(80)` | yes | IDX | `NULL` | — |
| 7 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 8 | `user_id` | `int(11)` | yes | IDX | `NULL` | — |
| 9 | `event_category` | `varchar(50)` | yes | IDX | `NULL` | — |
| 10 | `event_data_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `safe_data_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `status` | `enum('open','acknowledged','investigating','resolved','ignored')` | **no** | IDX | `'open'` | — |
| 13 | `acknowledged_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `acknowledged_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `resolved_by` | `int(11)` | yes | — | `NULL` | — |
| 16 | `resolved_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `resolution_notes` | `text` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_sensitive_doc_policies

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `policy_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `category` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `ai_processing_allowed` | `tinyint(1)` | **no** | — | `1` | — |
| 5 | `external_provider_allowed` | `tinyint(1)` | **no** | — | `0` | — |
| 6 | `masking_required` | `tinyint(1)` | **no** | — | `0` | — |
| 7 | `retention_days` | `int(11)` | yes | — | `365` | — |
| 8 | `export_allowed` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `sharing_allowed` | `tinyint(1)` | **no** | — | `1` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_sql_allowed_fields

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `table_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `field_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 6 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_sql_allowed_tables

6 columns · 18 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `table_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 5 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 6 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_sync_jobs

19 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `connection_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `connector_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `job_key` | `varchar(150)` | **no** | UQ | *NULL* | — |
| 6 | `job_type` | `enum('manual','scheduled','event_triggered')` | **no** | — | `'manual'` | — |
| 7 | `target_entity` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `status` | `enum('queued','running','completed','failed','partial','cancelled','preview_ready','awaiting_confirmation')` | **no** | IDX | `'queued'` | — |
| 9 | `mapping_id` | `int(11)` | yes | — | `NULL` | — |
| 10 | `preview_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `apply_summary_json` | `longtext` | yes | — | `NULL` | — |
| 12 | `conflict_strategy` | `enum('manual_review','prefer_erp','prefer_external')` | **no** | — | `'manual_review'` | — |
| 13 | `requested_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 16 | `started_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |
| 19 | `error_message` | `text` | yes | — | `NULL` | — |

### ai_sync_mappings

15 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `connection_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `mapping_key` | `varchar(150)` | **no** | UQ | *NULL* | — |
| 5 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 6 | `target_entity` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 7 | `source_schema_json` | `longtext` | yes | — | `NULL` | — |
| 8 | `target_schema_json` | `longtext` | yes | — | `NULL` | — |
| 9 | `rules_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `validation_json` | `longtext` | yes | — | `NULL` | — |
| 11 | `status` | `enum('draft','active','disabled','archived')` | **no** | — | `'draft'` | — |
| 12 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 13 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_tool_configs

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `tool_name` | `varchar(120)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 5 | `config_json` | `longtext` | yes | — | `NULL` | — |
| 6 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_usage_daily_aggregates

15 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `usage_date` | `date` | **no** | IDX | *NULL* | — |
| 3 | `provider_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `model_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `feature` | `varchar(50)` | **no** | IDX | `'unknown'` | — |
| 6 | `user_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 8 | `status` | `varchar(20)` | **no** | — | `'success'` | — |
| 9 | `request_count` | `int(10) unsigned` | **no** | — | `0` | — |
| 10 | `input_tokens` | `int(10) unsigned` | **no** | — | `0` | — |
| 11 | `output_tokens` | `int(10) unsigned` | **no** | — | `0` | — |
| 12 | `estimated_cost` | `decimal(10,6)` | **no** | — | `0.000000` | — |
| 13 | `avg_duration_ms` | `int(10) unsigned` | **no** | — | `0` | — |
| 14 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 15 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_usage_logs

15 columns · 53 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `provider_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 3 | `model_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `feature` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 5 | `user_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 6 | `company_id` | `int(10) unsigned` | **no** | IDX | `1` | — |
| 7 | `request_type` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `input_tokens` | `int(10) unsigned` | yes | — | `0` | — |
| 9 | `output_tokens` | `int(10) unsigned` | yes | — | `0` | — |
| 10 | `estimated_cost` | `decimal(10,6)` | yes | — | `0.000000` | — |
| 11 | `duration_ms` | `int(10) unsigned` | yes | — | `0` | — |
| 12 | `status` | `enum('success','failed','timeout')` | **no** | IDX | `'success'` | — |
| 13 | `error_category` | `varchar(50)` | yes | — | `NULL` | — |
| 14 | `metadata_json` | `longtext` | yes | — | `NULL` | binary collation |
| 15 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### ai_user_preferences

10 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `preference_key` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `preference_value` | `text` | yes | — | `NULL` | — |
| 6 | `category` | `varchar(50)` | yes | — | `'general'` | — |
| 7 | `source` | `enum('user','ai_suggested','learned')` | yes | — | `'user'` | — |
| 8 | `is_active` | `tinyint(1)` | yes | — | `1` | — |
| 9 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 10 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_voice_audit

7 columns · 51 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `event_type` | `enum('recording_uploaded','transcription_completed','transcription_failed','voice_command_sent','tts_generated','tts_failed','voice_action_proposed','voice_navigation_requested','dictation_used','shortcuts_configured','settings_changed')` | **no** | IDX | *NULL* | — |
| 5 | `transcription_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `details` | `longtext` | yes | — | `NULL` | binary collation |
| 7 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_voice_config

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `provider` | `varchar(50)` | **no** | — | *NULL* | — |
| 4 | `provider_type` | `enum('stt','tts')` | **no** | — | *NULL* | — |
| 5 | `config_data` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 7 | `priority` | `int(11)` | **no** | — | `0` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_voice_confirmations

17 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `session_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `message_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `confirmation_key` | `varchar(128)` | **no** | UQ | *NULL* | — |
| 7 | `action_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 8 | `action_id` | `varchar(100)` | yes | — | `NULL` | — |
| 9 | `parameters_json` | `longtext` | **no** | — | *NULL* | — |
| 10 | `parameters_hash` | `varchar(64)` | **no** | — | *NULL* | — |
| 11 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'medium'` | — |
| 12 | `status` | `enum('pending','confirmed','denied','expired','invalidated')` | **no** | IDX | `'pending'` | — |
| 13 | `confirmed_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `denied_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `expires_at` | `datetime` | **no** | IDX | *NULL* | — |
| 16 | `invalidated_reason` | `text` | yes | — | `NULL` | — |
| 17 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_voice_injection_log

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `session_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `transcription_text` | `text` | **no** | — | *NULL* | — |
| 6 | `injection_type` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `confidence` | `decimal(5,4)` | yes | — | `NULL` | — |
| 8 | `action_taken` | `enum('passed','warned','blocked')` | **no** | IDX | `'passed'` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_voice_messages

24 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `session_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_voice_sessions.id (on delete cascade) |
| 3 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 5 | `role` | `enum('user','assistant','system')` | **no** | IDX | *NULL* | — |
| 6 | `content_text` | `text` | yes | — | `NULL` | — |
| 7 | `transcription_text` | `text` | yes | — | `NULL` | — |
| 8 | `transcription_confidence` | `decimal(5,4)` | yes | — | `NULL` | — |
| 9 | `transcription_language` | `varchar(10)` | yes | — | `NULL` | — |
| 10 | `audio_duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 11 | `tts_generated` | `tinyint(1)` | **no** | — | `0` | — |
| 12 | `tts_provider` | `varchar(100)` | yes | — | `NULL` | — |
| 13 | `intent_type` | `varchar(100)` | yes | — | `NULL` | — |
| 14 | `action_type` | `varchar(100)` | yes | — | `NULL` | — |
| 15 | `action_id` | `int(11)` | yes | — | `NULL` | — |
| 16 | `action_status` | `varchar(50)` | yes | — | `NULL` | — |
| 17 | `risk_level` | `enum('low','medium','high','critical')` | **no** | — | `'low'` | — |
| 18 | `governance_decision` | `varchar(50)` | yes | — | `NULL` | — |
| 19 | `f25_task_id` | `int(11)` | yes | — | `NULL` | — |
| 20 | `f25_task_key` | `varchar(100)` | yes | — | `NULL` | — |
| 21 | `f26_event_id` | `int(11)` | yes | — | `NULL` | — |
| 22 | `f27_document_id` | `int(11)` | yes | — | `NULL` | — |
| 23 | `metadata_json` | `longtext` | yes | — | `NULL` | — |
| 24 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_voice_preferences

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `preferred_language` | `varchar(10)` | **no** | — | `'en'` | — |
| 5 | `preferred_voice` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `tts_speed` | `decimal(3,2)` | **no** | — | `1.00` | — |
| 7 | `auto_tts` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `push_to_talk` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `noise_cancellation` | `tinyint(1)` | **no** | — | `0` | — |
| 10 | `max_recording_seconds` | `int(11)` | **no** | — | `120` | — |
| 11 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_voice_retention_policies

10 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | UQ | *NULL* | — |
| 3 | `audio_retention_days` | `int(11)` | **no** | — | `7` | — |
| 4 | `transcript_retention_days` | `int(11)` | **no** | — | `90` | — |
| 5 | `tts_cache_retention_days` | `int(11)` | **no** | — | `1` | — |
| 6 | `metadata_retention_days` | `int(11)` | **no** | — | `365` | — |
| 7 | `auto_delete_audio` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `auto_delete_transcripts` | `tinyint(1)` | **no** | — | `0` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 10 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_voice_sessions

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `conversation_id` | `int(11)` | yes | — | `NULL` | — |
| 5 | `language` | `varchar(10)` | yes | — | `'en'` | — |
| 6 | `stt_provider` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `tts_provider` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `status` | `enum('active','paused','completed','expired')` | **no** | IDX | `'active'` | — |
| 9 | `message_count` | `int(11)` | **no** | — | `0` | — |
| 10 | `started_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 11 | `last_activity_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |
| 12 | `ended_at` | `datetime` | yes | — | `NULL` | — |

### ai_voice_shortcuts

9 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `command` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `target_action` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `target_url` | `varchar(500)` | yes | — | `NULL` | — |
| 6 | `description` | `varchar(300)` | yes | — | `NULL` | — |
| 7 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 8 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 9 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_voice_transcriptions

17 columns · 15 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `provider` | `varchar(50)` | **no** | — | `'browser'` | — |
| 5 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 6 | `language` | `varchar(10)` | yes | — | `NULL` | — |
| 7 | `detected_language` | `varchar(10)` | yes | — | `NULL` | — |
| 8 | `duration_ms` | `int(11)` | yes | — | `0` | — |
| 9 | `file_size_bytes` | `int(11)` | yes | — | `0` | — |
| 10 | `mime_type` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `status` | `enum('pending','processing','completed','failed','cancelled')` | **no** | IDX | `'pending'` | — |
| 12 | `transcript_text` | `text` | yes | — | `NULL` | — |
| 13 | `confidence` | `decimal(5,4)` | yes | — | `NULL` | — |
| 14 | `raw_metadata` | `longtext` | yes | — | `NULL` | binary collation |
| 15 | `error_message` | `varchar(500)` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |
| 17 | `completed_at` | `datetime` | yes | — | `NULL` | — |

### ai_voice_tts_cache

15 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `provider` | `varchar(50)` | **no** | — | `'browser'` | — |
| 5 | `text_hash` | `varchar(64)` | **no** | IDX | *NULL* | — |
| 6 | `text_preview` | `varchar(200)` | yes | — | `NULL` | — |
| 7 | `language` | `varchar(10)` | yes | — | `NULL` | — |
| 8 | `voice` | `varchar(50)` | yes | — | `NULL` | — |
| 9 | `speed` | `decimal(3,2)` | yes | — | `1.00` | — |
| 10 | `file_path` | `varchar(500)` | yes | — | `NULL` | — |
| 11 | `file_size_bytes` | `int(11)` | yes | — | `0` | — |
| 12 | `duration_ms` | `int(11)` | yes | — | `0` | — |
| 13 | `status` | `enum('pending','generating','completed','failed')` | **no** | — | `'pending'` | — |
| 14 | `expires_at` | `datetime` | yes | IDX | `NULL` | — |
| 15 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_voice_usage

9 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `feature` | `enum('stt','tts')` | **no** | IDX | *NULL* | — |
| 5 | `provider` | `varchar(50)` | **no** | — | *NULL* | — |
| 6 | `model` | `varchar(100)` | yes | — | `NULL` | — |
| 7 | `duration_ms` | `int(11)` | yes | — | `0` | — |
| 8 | `estimated_cost` | `decimal(10,6)` | yes | — | `0.000000` | — |
| 9 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_webhook_definitions

21 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `connection_id` | `int(11)` | yes | — | `NULL` | — |
| 4 | `webhook_key` | `varchar(150)` | **no** | UQ | *NULL* | — |
| 5 | `name` | `varchar(150)` | **no** | — | *NULL* | — |
| 6 | `destination_url` | `varchar(255)` | **no** | — | *NULL* | — |
| 7 | `allowed_host` | `varchar(190)` | **no** | — | *NULL* | — |
| 8 | `event_source` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 9 | `payload_schema_json` | `longtext` | yes | — | `NULL` | — |
| 10 | `secret_encrypted` | `longtext` | yes | — | `NULL` | — |
| 11 | `secret_iv` | `varchar(255)` | yes | — | `NULL` | — |
| 12 | `signature_header` | `varchar(100)` | **no** | — | `'X-AI-Signature'` | — |
| 13 | `status` | `enum('draft','active','disabled','failed','revoked')` | **no** | IDX | `'draft'` | — |
| 14 | `retry_policy_json` | `longtext` | yes | — | `NULL` | — |
| 15 | `allow_internal_destination` | `tinyint(1)` | **no** | — | `0` | — |
| 16 | `last_sent_at` | `datetime` | yes | — | `NULL` | — |
| 17 | `last_error_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `created_by` | `int(11)` | yes | — | `NULL` | — |
| 19 | `updated_by` | `int(11)` | yes | — | `NULL` | — |
| 20 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 21 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_webhook_deliveries

12 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `webhook_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `event_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `request_id` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('queued','sent','failed','blocked')` | **no** | IDX | `'queued'` | — |
| 6 | `http_status` | `int(11)` | yes | — | `NULL` | — |
| 7 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 8 | `safe_payload_summary` | `text` | yes | — | `NULL` | — |
| 9 | `response_summary` | `text` | yes | — | `NULL` | — |
| 10 | `error_category` | `varchar(50)` | yes | — | `NULL` | — |
| 11 | `attempt_count` | `int(11)` | **no** | — | `0` | — |
| 12 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workflows

17 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workflow_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `company_id` | `int(11)` | yes | IDX | `NULL` | — |
| 4 | `module` | `varchar(50)` | yes | IDX | `'ai'` | — |
| 5 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 6 | `description` | `text` | yes | — | `NULL` | — |
| 7 | `status` | `enum('draft','testing','approved','published','enabled','disabled','archived')` | **no** | IDX | `'draft'` | — |
| 8 | `safety_classification` | `enum('low','medium','high','restricted')` | **no** | — | `'low'` | — |
| 9 | `trigger_type` | `enum('manual','scheduled','event')` | **no** | — | `'manual'` | — |
| 10 | `current_version_number` | `int(11)` | **no** | — | `1` | — |
| 11 | `current_version_id` | `int(11)` | yes | — | `NULL` | — |
| 12 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 13 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 14 | `last_run_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `enabled_at` | `datetime` | yes | — | `NULL` | — |
| 16 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 17 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_workflow_approvals

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `workflow_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `workflow_version_id` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `node_id` | `varchar(100)` | **no** | — | *NULL* | — |
| 6 | `requested_by` | `int(11)` | **no** | — | *NULL* | — |
| 7 | `approver_user_id` | `int(11)` | yes | — | `NULL` | — |
| 8 | `status` | `enum('pending','approved','rejected','expired','cancelled')` | **no** | IDX | `'pending'` | — |
| 9 | `title` | `varchar(200)` | **no** | — | *NULL* | — |
| 10 | `summary` | `text` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 12 | `responded_at` | `datetime` | yes | — | `NULL` | — |

### ai_workflow_runs

19 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workflow_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `workflow_version_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `workflow_version_number` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `company_id` | `int(11)` | yes | — | `NULL` | — |
| 6 | `trigger_type` | `enum('manual','scheduled','event')` | **no** | — | `'manual'` | — |
| 7 | `trigger_context_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `status` | `enum('queued','running','waiting','waiting_approval','completed','failed','cancelled','expired')` | **no** | IDX | `'queued'` | — |
| 9 | `current_node_id` | `varchar(100)` | yes | — | `NULL` | — |
| 10 | `execution_state_json` | `longtext` | yes | — | `NULL` | binary collation |
| 11 | `resume_after` | `datetime` | yes | IDX | `NULL` | — |
| 12 | `retry_attempt` | `int(11)` | **no** | — | `0` | — |
| 13 | `dry_run` | `tinyint(1)` | **no** | — | `0` | — |
| 14 | `started_by` | `int(11)` | yes | — | `NULL` | — |
| 15 | `error_summary` | `varchar(500)` | yes | — | `NULL` | — |
| 16 | `started_at` | `datetime` | yes | — | current_timestamp() | — |
| 17 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `created_at` | `datetime` | yes | — | current_timestamp() | — |
| 19 | `updated_at` | `datetime` | yes | — | current_timestamp() | on update current_timestamp() |

### ai_workflow_run_nodes

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `node_id` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `node_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('pending','running','completed','failed','skipped','waiting')` | **no** | — | `'pending'` | — |
| 6 | `started_at` | `datetime` | yes | — | current_timestamp() | — |
| 7 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 8 | `duration_ms` | `int(11)` | yes | — | `NULL` | — |
| 9 | `safe_output_summary` | `text` | yes | — | `NULL` | — |
| 10 | `error_category` | `varchar(100)` | yes | — | `NULL` | — |
| 11 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_workflow_templates

9 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `template_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `category` | `varchar(50)` | yes | — | `'general'` | — |
| 6 | `trigger_type` | `enum('manual','scheduled','event')` | **no** | — | `'manual'` | — |
| 7 | `graph_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 8 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 9 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_workflow_versions

14 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workflow_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `version_number` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `version_status` | `enum('draft','testing','approved','published','enabled','disabled','archived')` | **no** | IDX | `'draft'` | — |
| 5 | `trigger_config_json` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `graph_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 7 | `validation_json` | `longtext` | yes | — | `NULL` | binary collation |
| 8 | `ai_draft_prompt` | `text` | yes | — | `NULL` | — |
| 9 | `ai_draft_summary` | `text` | yes | — | `NULL` | — |
| 10 | `node_count` | `int(11)` | **no** | — | `0` | — |
| 11 | `created_by` | `int(11)` | **no** | — | *NULL* | — |
| 12 | `approved_by` | `int(11)` | yes | — | `NULL` | — |
| 13 | `published_at` | `datetime` | yes | — | `NULL` | — |
| 14 | `created_at` | `datetime` | yes | — | current_timestamp() | — |

### ai_workspaces

9 columns · 10 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `workspace_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 3 | `owner_user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `status` | `enum('active','archived')` | **no** | IDX | `'active'` | — |
| 7 | `visibility` | `enum('private','restricted','company')` | **no** | — | `'restricted'` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_workspace_activity

8 columns · 61 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `activity_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `actor_user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `action_type` | `enum('workspace_created','workspace_updated','member_added','member_removed','role_changed','conversation_shared','context_pinned','finding_created','finding_reviewed','research_started','agent_result_shared','workspace_archived','goal_created','instruction_set','knowledge_added')` | **no** | IDX | *NULL* | — |
| 5 | `target_type` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `target_id` | `int(11)` | yes | — | `NULL` | — |
| 7 | `metadata_json` | `text` | yes | — | `NULL` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workspace_conversations

8 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `conversation_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `title` | `varchar(255)` | yes | — | `NULL` | — |
| 4 | `context_type` | `enum('shared_workspace')` | **no** | — | `'shared_workspace'` | — |
| 5 | `created_by` | `int(11)` | **no** | IDX | *NULL* | — |
| 6 | `status` | `enum('active','archived')` | **no** | — | `'active'` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_workspace_goals

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `goal_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `set_by` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `success_criteria` | `text` | yes | — | `NULL` | — |
| 7 | `status` | `enum('active','achieved','archived')` | **no** | — | `'active'` | — |
| 8 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_workspace_instructions

8 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `instruction_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `set_by` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `instruction_text` | `text` | **no** | — | *NULL* | — |
| 5 | `priority` | `int(11)` | **no** | — | `50` | — |
| 6 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `datetime` | **no** | — | current_timestamp() | on update current_timestamp() |

### ai_workspace_knowledge

5 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `wk_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `knowledge_collection_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `added_by` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `added_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workspace_members

6 columns · 17 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `member_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `role` | `enum('owner','manager','member','viewer')` | **no** | — | `'member'` | — |
| 5 | `invited_by` | `int(11)` | yes | — | `NULL` | — |
| 6 | `joined_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workspace_messages

7 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `message_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `conversation_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspace_conversations.conversation_id (on delete cascade) |
| 3 | `author_user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `message_type` | `enum('user','ai','system','activity')` | **no** | — | `'user'` | — |
| 5 | `content` | `text` | **no** | — | *NULL* | — |
| 6 | `metadata_json` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workspace_notifications

10 columns · 48 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `notification_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `recipient_user_id` | `int(11)` | **no** | IDX | *NULL* | — |
| 4 | `notification_type` | `enum('member_added','finding_created','task_assigned','research_updated','agent_result_shared','mention')` | **no** | — | *NULL* | — |
| 5 | `title` | `varchar(255)` | **no** | — | *NULL* | — |
| 6 | `message` | `text` | yes | — | `NULL` | — |
| 7 | `reference_type` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `reference_id` | `int(11)` | yes | — | `NULL` | — |
| 9 | `is_read` | `tinyint(1)` | **no** | — | `0` | — |
| 10 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workspace_pins

8 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `pin_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `pinned_by` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `reference_type` | `enum('conversation','task','insight','knowledge','erp_record','agent_result','finding','research_session')` | **no** | — | *NULL* | — |
| 5 | `reference_id` | `int(11)` | **no** | — | *NULL* | — |
| 6 | `title` | `varchar(255)` | yes | — | `NULL` | — |
| 7 | `note` | `text` | yes | — | `NULL` | — |
| 8 | `pinned_at` | `datetime` | **no** | — | current_timestamp() | — |

### ai_workspace_presence

4 columns · 4 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `workspace_id` | `int(11)` | **no** | PK | *NULL* | — |
| 2 | `user_id` | `int(11)` | **no** | PK | *NULL* | — |
| 3 | `status` | `enum('viewing','active_recently','editing')` | **no** | — | `'active_recently'` | — |
| 4 | `last_seen` | `datetime` | **no** | IDX | current_timestamp() | — |

### ai_workspace_shared_agent_runs

7 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `share_id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `workspace_id` | `int(11)` | **no** | IDX FK | *NULL* | ai_workspaces.workspace_id (on delete cascade) |
| 3 | `agent_run_id` | `int(11)` | **no** | — | *NULL* | — |
| 4 | `shared_by` | `int(11)` | **no** | — | *NULL* | — |
| 5 | `shared_summary` | `text` | yes | — | `NULL` | — |
| 6 | `status` | `enum('shared','reviewed','archived')` | **no** | — | `'shared'` | — |
| 7 | `created_at` | `datetime` | **no** | — | current_timestamp() | — |

## Configuration & Lookups


### app_settings

9 columns · 8 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `setting_key` | `varchar(80)` | **no** | PK | *NULL* | — |
| 2 | `setting_value` | `text` | yes | — | `NULL` | — |
| 3 | `setting_group` | `varchar(40)` | **no** | IDX | `'general'` | — |
| 4 | `value_type` | `enum('string','integer','decimal','boolean','json','date')` | **no** | — | `'string'` | — |
| 5 | `label` | `varchar(160)` | yes | — | `NULL` | — |
| 6 | `description` | `varchar(500)` | yes | — | `NULL` | — |
| 7 | `is_public` | `tinyint(1)` | **no** | — | `1` | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 9 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |

### companies

135 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_code` | `varchar(20)` | **no** | UQ | *NULL* | — |
| 3 | `company_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `legal_name` | `varchar(200)` | yes | — | `NULL` | — |
| 5 | `trade_name` | `varchar(255)` | yes | — | `NULL` | — |
| 6 | `short_name` | `varchar(60)` | yes | — | `NULL` | — |
| 7 | `search_term` | `varchar(120)` | yes | IDX | `NULL` | — |
| 8 | `entity_type` | `enum('limited_liability','sole_proprietorship','partnership','free_zone_establishment','branch','subsidiary','holding','ngo','government','trust')` | **no** | IDX | `'limited_liability'` | — |
| 9 | `company_category` | `enum('operating','holding','service_provider','special_purpose','dormant')` | **no** | — | `'operating'` | — |
| 10 | `industry` | `varchar(120)` | yes | — | `NULL` | — |
| 11 | `sub_industry` | `varchar(120)` | yes | — | `NULL` | — |
| 12 | `group_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | company_groups.id (on delete set null) |
| 13 | `parent_company_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | companies.id (on delete set null) |
| 14 | `is_legal_entity` | `tinyint(1)` | **no** | — | `1` | — |
| 15 | `is_operating_unit` | `tinyint(1)` | **no** | — | `1` | — |
| 16 | `is_business_unit` | `tinyint(1)` | **no** | — | `0` | — |
| 17 | `bu_receivables` | `tinyint(1)` | **no** | — | `1` | — |
| 18 | `bu_payables` | `tinyint(1)` | **no** | — | `1` | — |
| 19 | `bu_inventory` | `tinyint(1)` | **no** | — | `1` | — |
| 20 | `bu_purchasing` | `tinyint(1)` | **no** | — | `1` | — |
| 21 | `bu_sales` | `tinyint(1)` | **no** | — | `1` | — |
| 22 | `established_date` | `date` | yes | — | `NULL` | — |
| 23 | `duns_number` | `varchar(20)` | yes | — | `NULL` | — |
| 24 | `lei_number` | `varchar(30)` | yes | — | `NULL` | — |
| 25 | `registration_number` | `varchar(60)` | yes | — | `NULL` | — |
| 26 | `tax_number` | `varchar(60)` | yes | — | `NULL` | — |
| 27 | `vat_number` | `varchar(60)` | yes | — | `NULL` | — |
| 28 | `trn_number` | `varchar(60)` | yes | — | `NULL` | — |
| 29 | `tin_number` | `varchar(60)` | yes | — | `NULL` | — |
| 30 | `ein_number` | `varchar(60)` | yes | — | `NULL` | — |
| 31 | `gst_state_code` | `varchar(12)` | yes | — | `NULL` | — |
| 32 | `free_zone_name` | `varchar(120)` | yes | — | `NULL` | — |
| 33 | `free_zone_license` | `varchar(80)` | yes | — | `NULL` | — |
| 34 | `free_zone_expiry` | `date` | yes | — | `NULL` | — |
| 35 | `license_number` | `varchar(80)` | yes | — | `NULL` | — |
| 36 | `license_expiry` | `date` | yes | — | `NULL` | — |
| 37 | `regulatory_authority` | `varchar(150)` | yes | — | `NULL` | — |
| 38 | `share_capital` | `decimal(18,2)` | yes | — | `NULL` | — |
| 39 | `audit_firm` | `varchar(190)` | yes | — | `NULL` | — |
| 40 | `statutory_auditor` | `varchar(190)` | yes | — | `NULL` | — |
| 41 | `financial_framework` | `enum('IFRS','IFRS_for_SMEs','Local_GAAP','US_GAAP','ISFR','None')` | **no** | — | `'IFRS'` | — |
| 42 | `consolidation_method` | `enum('full','proportionate','equity','none')` | **no** | — | `'full'` | — |
| 43 | `is_reporting_entity` | `tinyint(1)` | **no** | — | `1` | — |
| 44 | `base_currency` | `char(3)` | **no** | — | `'AED'` | — |
| 45 | `country` | `varchar(60)` | yes | — | `NULL` | — |
| 46 | `address_line1` | `varchar(255)` | yes | — | `NULL` | — |
| 47 | `address_line2` | `varchar(255)` | yes | — | `NULL` | — |
| 48 | `city` | `varchar(120)` | yes | — | `NULL` | — |
| 49 | `state` | `varchar(120)` | yes | — | `NULL` | — |
| 50 | `postal_code` | `varchar(20)` | yes | — | `NULL` | — |
| 51 | `region` | `varchar(120)` | yes | — | `NULL` | — |
| 52 | `phone` | `varchar(40)` | yes | — | `NULL` | — |
| 53 | `alternate_phone` | `varchar(40)` | yes | — | `NULL` | — |
| 54 | `fax` | `varchar(40)` | yes | — | `NULL` | — |
| 55 | `email` | `varchar(190)` | yes | — | `NULL` | — |
| 56 | `support_email` | `varchar(190)` | yes | — | `NULL` | — |
| 57 | `website` | `varchar(255)` | yes | — | `NULL` | — |
| 58 | `contact_person` | `varchar(190)` | yes | — | `NULL` | — |
| 59 | `contact_designation` | `varchar(120)` | yes | — | `NULL` | — |
| 60 | `language` | `varchar(40)` | **no** | — | `'English'` | — |
| 61 | `default_receivable_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 62 | `default_payable_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 63 | `default_bank_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 64 | `default_cash_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 65 | `default_inventory_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 66 | `default_cogs_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 67 | `default_sales_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 68 | `default_purchase_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 69 | `default_tax_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 70 | `default_intercompany_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 71 | `default_retained_earnings_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 72 | `default_fx_difference_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 73 | `periods_per_year` | `tinyint(3) unsigned` | **no** | — | `12` | — |
| 74 | `posting_tolerance` | `decimal(12,2)` | **no** | — | `0.01` | — |
| 75 | `allow_posting_to_closed` | `tinyint(1)` | **no** | — | `0` | — |
| 76 | `require_cost_center` | `tinyint(1)` | **no** | — | `0` | — |
| 77 | `require_project` | `tinyint(1)` | **no** | — | `0` | — |
| 78 | `enable_multicurrency` | `tinyint(1)` | **no** | — | `1` | — |
| 79 | `cash_basis_accounting` | `tinyint(1)` | **no** | — | `0` | — |
| 80 | `allow_negative_stock` | `tinyint(1)` | **no** | — | `0` | — |
| 81 | `invoice_prefix` | `varchar(12)` | **no** | — | `'INV'` | — |
| 82 | `invoice_padding` | `smallint(5) unsigned` | **no** | — | `4` | — |
| 83 | `quote_prefix` | `varchar(12)` | **no** | — | `'QTN'` | — |
| 84 | `po_prefix` | `varchar(12)` | **no** | — | `'PO'` | — |
| 85 | `pr_prefix` | `varchar(12)` | **no** | — | `'PR'` | — |
| 86 | `gr_prefix` | `varchar(12)` | **no** | — | `'GRN'` | — |
| 87 | `je_prefix` | `varchar(12)` | **no** | — | `'JV'` | — |
| 88 | `receipt_prefix` | `varchar(12)` | **no** | — | `'RCP'` | — |
| 89 | `vendor_bill_prefix` | `varchar(12)` | **no** | — | `'VB'` | — |
| 90 | `payment_reference_format` | `varchar(60)` | yes | — | `NULL` | — |
| 91 | `invoice_terms` | `text` | yes | — | `NULL` | — |
| 92 | `quote_validity_days` | `smallint(5) unsigned` | **no** | — | `30` | — |
| 93 | `default_payment_terms` | `varchar(50)` | yes | — | `NULL` | — |
| 94 | `default_shipping_condition` | `varchar(60)` | yes | — | `NULL` | — |
| 95 | `default_incoterms` | `varchar(10)` | yes | — | `NULL` | — |
| 96 | `default_price_list` | `varchar(60)` | yes | — | `NULL` | — |
| 97 | `tax_registration_type` | `enum('standard','vat_registered','non_registered','exempt','reverse_charge')` | **no** | — | `'standard'` | — |
| 98 | `tax_authority` | `varchar(150)` | yes | — | `NULL` | — |
| 99 | `tax_office_code` | `varchar(40)` | yes | — | `NULL` | — |
| 100 | `vat_return_frequency` | `enum('monthly','quarterly','semi_annual','annual','none')` | **no** | — | `'monthly'` | — |
| 101 | `vat_return_day` | `tinyint(3) unsigned` | **no** | — | `15` | — |
| 102 | `withholding_agent` | `tinyint(1)` | **no** | — | `1` | — |
| 103 | `default_withholding_rate` | `decimal(5,2)` | **no** | — | `0.00` | — |
| 104 | `e_invoicing_enabled` | `tinyint(1)` | **no** | — | `0` | — |
| 105 | `e_invoicing_system` | `enum('none','zatca_fatoora','gst_e_invoice','fbr_iris','peppol','other')` | **no** | — | `'none'` | — |
| 106 | `e_invoicing_stage` | `enum('phase_1_generation','phase_2_integration','cleared_only')` | yes | — | `NULL` | — |
| 107 | `direct_debit_prefix` | `varchar(30)` | yes | — | `NULL` | — |
| 108 | `logo_path` | `varchar(255)` | yes | — | `NULL` | — |
| 109 | `letterhead_template_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 110 | `signature_text` | `text` | yes | — | `NULL` | — |
| 111 | `document_footer_note` | `text` | yes | — | `NULL` | — |
| 112 | `theme_color` | `varchar(9)` | yes | — | `NULL` | — |
| 113 | `default_warehouse_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | warehouses.id (on delete set null) |
| 114 | `default_cost_center_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 115 | `working_days` | `varchar(40)` | **no** | — | `'1,2,3,4,5'` | — |
| 116 | `weekend_days` | `varchar(20)` | **no** | — | `'5,6'` | — |
| 117 | `working_hours` | `varchar(30)` | **no** | — | `'09:00-18:00'` | — |
| 118 | `employee_count` | `int(10) unsigned` | yes | — | `NULL` | — |
| 119 | `annual_revenue` | `decimal(18,2)` | yes | — | `NULL` | — |
| 120 | `enable_inventory` | `tinyint(1)` | **no** | — | `1` | — |
| 121 | `enable_projects` | `tinyint(1)` | **no** | — | `1` | — |
| 122 | `enable_hr` | `tinyint(1)` | **no** | — | `1` | — |
| 123 | `enable_pos` | `tinyint(1)` | **no** | — | `0` | — |
| 124 | `enable_manufacturing` | `tinyint(1)` | **no** | — | `0` | — |
| 125 | `branch_code` | `varchar(30)` | yes | — | `NULL` | — |
| 126 | `is_branch` | `tinyint(1)` | **no** | — | `0` | — |
| 127 | `timezone` | `varchar(50)` | yes | — | `NULL` | — |
| 128 | `financial_year_start` | `date` | yes | — | `NULL` | — |
| 129 | `status` | `enum('active','inactive','dormant','archived')` | **no** | IDX | `'active'` | — |
| 130 | `is_default` | `tinyint(1)` | **no** | — | `0` | — |
| 131 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 132 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 133 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 134 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 135 | `deleted_at` | `timestamp` | yes | — | `NULL` | — |

### companies_pre_company_master_20261012

14 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | — | `0` | — |
| 2 | `company_code` | `varchar(20)` | **no** | — | *NULL* | — |
| 3 | `company_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `legal_name` | `varchar(200)` | yes | — | `NULL` | — |
| 5 | `registration_number` | `varchar(60)` | yes | — | `NULL` | — |
| 6 | `tax_number` | `varchar(60)` | yes | — | `NULL` | — |
| 7 | `base_currency` | `char(3)` | **no** | — | `'AED'` | — |
| 8 | `country` | `varchar(60)` | yes | — | `NULL` | — |
| 9 | `timezone` | `varchar(50)` | yes | — | `NULL` | — |
| 10 | `financial_year_start` | `date` | yes | — | `NULL` | — |
| 11 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 12 | `is_default` | `tinyint(1)` | **no** | — | `0` | — |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 14 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### company_groups

8 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `group_code` | `varchar(20)` | **no** | UQ | *NULL* | — |
| 3 | `group_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 4 | `parent_company_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `base_currency` | `char(3)` | **no** | — | `'AED'` | — |
| 6 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### company_relationships

10 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `group_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | company_groups.id (on delete cascade) |
| 3 | `parent_company_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `child_company_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 5 | `ownership_percentage` | `decimal(6,3)` | **no** | — | `100.000` | — |
| 6 | `effective_from` | `date` | **no** | — | *NULL* | — |
| 7 | `effective_to` | `date` | yes | — | `NULL` | — |
| 8 | `relationship_type` | `enum('parent','subsidiary','associate','branch','other')` | **no** | — | `'subsidiary'` | — |
| 9 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### consolidation_run_companies

7 columns · 20 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `run_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | consolidation_runs.id (on delete cascade) |
| 3 | `company_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `financial_year_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `exchange_rate` | `decimal(18,8)` | **no** | — | `1.00000000` | — |
| 6 | `average_rate` | `decimal(18,8)` | **no** | — | `1.00000000` | — |
| 7 | `included` | `tinyint(1)` | **no** | — | `1` | — |

### currencies

9 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `code` | `char(3)` | **no** | PK | *NULL* | — |
| 2 | `name` | `varchar(120)` | **no** | — | *NULL* | — |
| 3 | `symbol` | `varchar(12)` | **no** | — | *NULL* | — |
| 4 | `decimal_places` | `tinyint(3) unsigned` | **no** | — | `2` | — |
| 5 | `is_active` | `tinyint(1)` | **no** | IDX | `1` | — |
| 6 | `is_base` | `tinyint(1)` | **no** | — | `0` | — |
| 7 | `sort_order` | `smallint(5) unsigned` | **no** | — | `100` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### currency_rates

7 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `currency_code` | `char(3)` | **no** | IDX FK | *NULL* | currencies.code (on delete cascade) |
| 3 | `rate_to_base` | `decimal(18,8)` | **no** | — | *NULL* | — |
| 4 | `effective_date` | `date` | **no** | — | *NULL* | — |
| 5 | `source` | `varchar(60)` | **no** | — | `'manual'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |

### document_sequences

11 columns · 47 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | companies.id (on delete cascade) |
| 3 | `doc_type` | `varchar(40)` | **no** | — | *NULL* | — |
| 4 | `period_key` | `varchar(20)` | **no** | — | `''` | — |
| 5 | `prefix` | `varchar(20)` | **no** | — | *NULL* | — |
| 6 | `padding` | `tinyint(3) unsigned` | **no** | — | `4` | — |
| 7 | `date_format` | `varchar(20)` | **no** | — | `''` | — |
| 8 | `format` | `varchar(80)` | yes | — | `NULL` | — |
| 9 | `next_number` | `bigint(20) unsigned` | **no** | — | `1` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### lookup_types

6 columns · 32 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `type_key` | `varchar(60)` | **no** | PK | *NULL* | — |
| 2 | `label` | `varchar(160)` | **no** | — | *NULL* | — |
| 3 | `description` | `varchar(500)` | yes | — | `NULL` | — |
| 4 | `group_name` | `varchar(60)` | **no** | — | `'general'` | — |
| 5 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### lookup_values

9 columns · 198 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `type_key` | `varchar(60)` | **no** | IDX FK | *NULL* | lookup_types.type_key (on delete cascade) |
| 3 | `value_key` | `varchar(80)` | **no** | — | *NULL* | — |
| 4 | `value_label` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `extra` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `is_active` | `tinyint(1)` | **no** | — | `1` | — |
| 7 | `sort_order` | `smallint(5) unsigned` | **no** | — | `100` | — |
| 8 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 9 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### report_templates

8 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `template_name` | `varchar(150)` | **no** | IDX | *NULL* | — |
| 3 | `report_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `template_config` | `longtext` | yes | — | `NULL` | binary collation |
| 5 | `is_default` | `tinyint(1)` | **no** | — | `0` | — |
| 6 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### settings

5 columns · 27 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `setting_key` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `setting_value` | `text` | yes | — | `NULL` | — |
| 4 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 5 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### templates

14 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `category_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | template_categories.id (on delete set null) |
| 3 | `template_name` | `varchar(200)` | **no** | — | *NULL* | — |
| 4 | `template_type` | `varchar(100)` | **no** | IDX | *NULL* | invoice, leave_report, employee_report, etc. |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `page_size` | `enum('A4','Letter','Legal')` | yes | — | `'A4'` | — |
| 7 | `page_orientation` | `enum('portrait','landscape')` | yes | — | `'portrait'` | — |
| 8 | `status` | `enum('draft','published','archived')` | yes | IDX | `'draft'` | — |
| 9 | `version` | `int(10) unsigned` | yes | — | `1` | — |
| 10 | `is_system` | `tinyint(1)` | yes | — | `0` | — |
| 11 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 12 | `updated_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 13 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 14 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### template_categories

6 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `slug` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 4 | `description` | `text` | yes | — | `NULL` | — |
| 5 | `sort_order` | `int(11)` | yes | — | `0` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### template_history

7 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | templates.id (on delete cascade) |
| 3 | `action` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `old_data` | `longtext` | yes | — | `NULL` | binary collation |
| 5 | `new_data` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `performed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 7 | `performed_at` | `timestamp` | **no** | — | current_timestamp() | — |

### template_module_mappings

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | templates.id (on delete cascade) |
| 3 | `module_name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `module_action` | `varchar(100)` | yes | — | `NULL` | — |
| 5 | `is_default` | `tinyint(1)` | yes | — | `0` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### template_preview_cache

6 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | templates.id (on delete cascade) |
| 3 | `version_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | template_versions.id (on delete cascade) |
| 4 | `preview_html` | `longtext` | yes | — | `NULL` | — |
| 5 | `preview_data` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `generated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### template_versions

9 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `template_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | templates.id (on delete cascade) |
| 3 | `version_number` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `template_data` | `longtext` | **no** | — | *NULL* | Full template definition JSON · binary collation |
| 5 | `placeholder_definitions` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `change_notes` | `text` | yes | — | `NULL` | — |
| 7 | `status` | `enum('draft','published')` | yes | — | `'draft'` | — |
| 8 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### user_companies

2 columns · 15 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `user_id` | `int(10) unsigned` | **no** | PK | *NULL* | — |
| 2 | `company_id` | `int(10) unsigned` | **no** | PK | *NULL* | — |

### widget_definitions

19 columns · 60 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `widget_key` | `varchar(80)` | **no** | UQ | *NULL* | — |
| 3 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `name` | `varchar(200)` | **no** | — | *NULL* | — |
| 5 | `description` | `text` | yes | — | `NULL` | — |
| 6 | `icon` | `varchar(50)` | yes | — | `'fas fa-chart-bar'` | — |
| 7 | `category` | `varchar(50)` | yes | IDX | `'charts'` | — |
| 8 | `default_width` | `int(10) unsigned` | yes | — | `1` | — |
| 9 | `default_height` | `int(10) unsigned` | yes | — | `1` | — |
| 10 | `min_width` | `int(10) unsigned` | yes | — | `1` | — |
| 11 | `min_height` | `int(10) unsigned` | yes | — | `1` | — |
| 12 | `data_url` | `varchar(500)` | yes | — | `NULL` | — |
| 13 | `render_type` | `enum('server_html','chartjs','hybrid')` | yes | — | `'server_html'` | — |
| 14 | `permission_required` | `varchar(100)` | yes | — | `NULL` | — |
| 15 | `is_system` | `tinyint(1)` | yes | — | `0` | — |
| 16 | `status` | `enum('active','inactive')` | yes | IDX | `'active'` | — |
| 17 | `widget_options` | `longtext` | yes | — | `NULL` | binary collation |
| 18 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

## System & Security


### audit_logs

6 columns · 2,147 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 3 | `action` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 5 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 6 | `created_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### finance_group_audit

9 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `action` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 3 | `company_id` | `int(10) unsigned` | yes | IDX | `NULL` | — |
| 4 | `reference_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `details` | `text` | yes | — | `NULL` | — |
| 6 | `reason` | `text` | yes | — | `NULL` | — |
| 7 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |
| 8 | `user_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 9 | `created_at` | `datetime` | **no** | IDX | current_timestamp() | — |

### permissions

5 columns · 517 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `module` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 5 | `deleted_at` | `timestamp` | yes | IDX | `NULL` | — |

### person_roles

8 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `person_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | people.id (on delete cascade) |
| 3 | `role` | `enum('employee','customer','contractor')` | **no** | IDX | *NULL* | — |
| 4 | `status` | `enum('pending','complete','declined')` | **no** | — | `'pending'` | — |
| 5 | `record_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `completed_at` | `timestamp` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### report_audit_log

9 columns · 19 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `report_type` | `varchar(50)` | **no** | — | *NULL* | — |
| 3 | `action` | `enum('generated','exported')` | **no** | — | `'generated'` | — |
| 4 | `parameters` | `longtext` | yes | — | `NULL` | binary collation |
| 5 | `generated_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 6 | `generated_at` | `datetime` | **no** | — | *NULL* | — |
| 7 | `export_format` | `varchar(20)` | yes | — | `NULL` | — |
| 8 | `file_name` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `ip_address` | `varchar(45)` | yes | — | `NULL` | — |

### roles

5 columns · 3 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(100)` | **no** | UQ | *NULL* | — |
| 3 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 4 | `deleted_at` | `timestamp` | yes | IDX | `NULL` | — |
| 5 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |

### role_permissions

2 columns · 750 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `role_id` | `int(10) unsigned` | **no** | PK FK | *NULL* | roles.id (on delete cascade) |
| 2 | `permission_id` | `int(10) unsigned` | **no** | PK FK | *NULL* | permissions.id (on delete cascade) |

### sidebar_menu

13 columns · 172 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | auto increment |
| 2 | `parent_id` | `int(11)` | yes | — | `NULL` | — |
| 3 | `section` | `varchar(100)` | yes | — | `NULL` | Section group name. NULL = items within the group that has no section label. |
| 4 | `section_order` | `int(11)` | yes | IDX | `0` | Controls the order of sections. |
| 5 | `label` | `varchar(100)` | **no** | — | *NULL* | — |
| 6 | `icon` | `varchar(50)` | **no** | — | `'fas fa-link'` | — |
| 7 | `route` | `varchar(255)` | **no** | UQ | `'#'` | — |
| 8 | `permission` | `varchar(100)` | yes | — | `NULL` | Required permission key (e.g. users.view). NULL = visible to all. |
| 9 | `sort_order` | `int(11)` | yes | — | `0` | Sort order within a section group. |
| 10 | `is_active` | `tinyint(1)` | yes | IDX | `1` | — |
| 11 | `is_indented` | `tinyint(1)` | yes | — | `0` | Visually indent this item (adds pl-10 CSS). |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 13 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### users

11 columns · 14 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 3 | `email` | `varchar(255)` | **no** | UQ | *NULL* | — |
| 4 | `password` | `varchar(255)` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('active','inactive','suspended')` | yes | IDX | `'active'` | — |
| 6 | `theme` | `varchar(20)` | **no** | — | `'ibm-light'` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 8 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 9 | `deleted_at` | `timestamp` | yes | IDX | `NULL` | — |
| 10 | `created_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 11 | `updated_by` | `int(10) unsigned` | yes | — | `NULL` | — |

### user_roles

2 columns · 14 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `user_id` | `int(10) unsigned` | **no** | PK FK | *NULL* | users.id (on delete cascade) |
| 2 | `role_id` | `int(10) unsigned` | **no** | PK FK | *NULL* | roles.id (on delete cascade) |

## Other


### bill_status_history

7 columns · 2 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `bill_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | vendor_bills.id (on delete cascade) |
| 3 | `from_status` | `varchar(50)` | yes | — | `NULL` | — |
| 4 | `to_status` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `changed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 6 | `change_note` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### closing_adjustments

16 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_year_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | financial_years.id (on delete cascade) |
| 3 | `period_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `adjustment_type` | `enum('accrual','prepayment','provision','correction','depreciation','tax','other')` | **no** | — | `'other'` | — |
| 5 | `title` | `varchar(200)` | **no** | — | *NULL* | — |
| 6 | `reason` | `text` | **no** | — | *NULL* | — |
| 7 | `journal_entry_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 9 | `status` | `enum('draft','pending_approval','approved','rejected','posted','reversed')` | **no** | IDX | `'draft'` | — |
| 10 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 11 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 12 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 13 | `rejection_reason` | `text` | yes | — | `NULL` | — |
| 14 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 15 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 16 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### consolidation_runs

22 columns · 20 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `group_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | company_groups.id (on delete cascade) |
| 3 | `period_label` | `varchar(60)` | **no** | — | *NULL* | — |
| 4 | `start_date` | `date` | **no** | — | *NULL* | — |
| 5 | `end_date` | `date` | **no** | — | *NULL* | — |
| 6 | `group_currency` | `char(3)` | **no** | — | `'AED'` | — |
| 7 | `translation_method` | `enum('closing','average','historical')` | **no** | — | `'closing'` | — |
| 8 | `status` | `enum('draft','in_progress','validated','approved','posted','cancelled')` | **no** | — | `'draft'` | — |
| 9 | `total_revenue` | `decimal(18,2)` | yes | — | `NULL` | — |
| 10 | `total_expenses` | `decimal(18,2)` | yes | — | `NULL` | — |
| 11 | `net_profit` | `decimal(18,2)` | yes | — | `NULL` | — |
| 12 | `elimination_total` | `decimal(18,2)` | yes | — | `NULL` | — |
| 13 | `fx_adjustment_total` | `decimal(18,2)` | yes | — | `NULL` | — |
| 14 | `nci_total` | `decimal(18,2)` | yes | — | `NULL` | — |
| 15 | `validation_snapshot` | `longtext` | yes | — | `NULL` | — |
| 16 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 17 | `approved_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 18 | `approved_at` | `datetime` | yes | — | `NULL` | — |
| 19 | `posted_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 20 | `posted_at` | `datetime` | yes | — | `NULL` | — |
| 21 | `completed_at` | `datetime` | yes | — | `NULL` | — |
| 22 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### cost_centers

7 columns · 7 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `company_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 3 | `cost_center_code` | `varchar(30)` | **no** | IDX | *NULL* | — |
| 4 | `cost_center_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### customer_addresses

21 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `client_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | clients.id (on delete cascade) |
| 3 | `address_type` | `enum('billing','shipping','remittance','other')` | **no** | IDX | `'shipping'` | — |
| 4 | `label` | `varchar(120)` | **no** | — | *NULL* | — |
| 5 | `contact_name` | `varchar(190)` | yes | — | `NULL` | — |
| 6 | `phone` | `varchar(40)` | yes | — | `NULL` | — |
| 7 | `email` | `varchar(190)` | yes | — | `NULL` | — |
| 8 | `address_line1` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `address_line2` | `varchar(255)` | yes | — | `NULL` | — |
| 10 | `city` | `varchar(120)` | yes | — | `NULL` | — |
| 11 | `state` | `varchar(120)` | yes | — | `NULL` | — |
| 12 | `postal_code` | `varchar(20)` | yes | — | `NULL` | — |
| 13 | `country` | `varchar(120)` | yes | — | `NULL` | — |
| 14 | `tax_number` | `varchar(80)` | yes | — | `NULL` | — |
| 15 | `is_default` | `tinyint(1)` | **no** | — | `0` | — |
| 16 | `valid_from` | `date` | yes | — | `NULL` | — |
| 17 | `valid_upto` | `date` | yes | — | `NULL` | — |
| 18 | `notes` | `text` | yes | — | `NULL` | — |
| 19 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 20 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |
| 21 | `deleted_at` | `timestamp` | yes | — | `NULL` | — |

### exchange_rates

7 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `from_currency` | `char(3)` | **no** | IDX | *NULL* | — |
| 3 | `to_currency` | `char(3)` | **no** | — | *NULL* | — |
| 4 | `rate` | `decimal(18,8)` | **no** | — | *NULL* | — |
| 5 | `rate_type` | `enum('closing','average','historical','spot')` | **no** | — | `'closing'` | — |
| 6 | `effective_date` | `date` | **no** | — | *NULL* | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### intercompany_transactions

19 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `transaction_number` | `varchar(40)` | **no** | UQ | *NULL* | — |
| 3 | `source_company_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `target_company_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 5 | `transaction_date` | `date` | **no** | — | *NULL* | — |
| 6 | `transaction_type` | `enum('sale','purchase','service','expense','loan','interest','transfer','other')` | **no** | — | `'other'` | — |
| 7 | `reference` | `varchar(100)` | yes | — | `NULL` | — |
| 8 | `description` | `varchar(255)` | yes | — | `NULL` | — |
| 9 | `currency` | `char(3)` | **no** | — | `'AED'` | — |
| 10 | `amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 11 | `tax_amount` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 12 | `match_status` | `enum('unmatched','partially_matched','matched','exception','resolved')` | **no** | IDX | `'unmatched'` | — |
| 13 | `counterpart_id` | `int(10) unsigned` | yes | IDX | `NULL` | linked opposite-side transaction |
| 14 | `root_id` | `int(10) unsigned` | yes | — | `NULL` | shared pair anchor — identical on both sides |
| 15 | `source_journal_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 16 | `target_journal_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 17 | `created_by` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 18 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 19 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### lead_followups

9 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `lead_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | leads.id (on delete cascade) |
| 3 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 4 | `followup_date` | `date` | **no** | IDX | *NULL* | — |
| 5 | `followup_type` | `enum('call','email','meeting','whatsapp','demo')` | yes | — | `'call'` | — |
| 6 | `notes` | `text` | yes | — | `NULL` | — |
| 7 | `next_followup_date` | `date` | yes | — | `NULL` | — |
| 8 | `status` | `enum('completed','pending','cancelled')` | yes | — | `'completed'` | — |
| 9 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### license_assignments

5 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `license_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | software_licenses.id (on delete cascade) |
| 3 | `employee_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | employees.id (on delete cascade) |
| 4 | `assigned_date` | `date` | **no** | — | *NULL* | — |
| 5 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### locations

10 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `warehouse_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | warehouses.id (on delete cascade) |
| 3 | `location_code` | `varchar(30)` | **no** | IDX | *NULL* | — |
| 4 | `location_name` | `varchar(150)` | **no** | — | *NULL* | — |
| 5 | `zone` | `varchar(50)` | yes | — | `NULL` | — |
| 6 | `rack` | `varchar(50)` | yes | — | `NULL` | — |
| 7 | `shelf` | `varchar(50)` | yes | — | `NULL` | — |
| 8 | `bin` | `varchar(50)` | yes | — | `NULL` | — |
| 9 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 10 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### money_transfers

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `from_account_type` | `enum('bank','cash')` | **no** | — | *NULL* | — |
| 3 | `from_account_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 4 | `to_account_type` | `enum('bank','cash')` | **no** | — | *NULL* | — |
| 5 | `to_account_id` | `int(10) unsigned` | **no** | — | *NULL* | — |
| 6 | `transfer_date` | `date` | **no** | IDX | *NULL* | — |
| 7 | `amount` | `decimal(15,2)` | **no** | — | `0.00` | — |
| 8 | `reference_number` | `varchar(50)` | yes | — | `NULL` | — |
| 9 | `description` | `text` | yes | — | `NULL` | — |
| 10 | `journal_entry_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | journal_entries.id (on delete set null) |
| 11 | `status` | `enum('posted','draft','cancelled')` | **no** | IDX | `'posted'` | — |
| 12 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### opening_balance_history

8 columns · 6 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint(20) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `account_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | chart_of_accounts.id (on delete cascade) |
| 3 | `financial_year_id` | `int(10) unsigned` | yes | IDX FK | `NULL` | financial_years.id (on delete set null) |
| 4 | `old_balance` | `decimal(15,2)` | yes | — | `0.00` | — |
| 5 | `new_balance` | `decimal(15,2)` | **no** | — | *NULL* | — |
| 6 | `balance_type` | `enum('debit','credit')` | yes | — | `'debit'` | — |
| 7 | `changed_by` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete restrict) |
| 8 | `changed_at` | `timestamp` | **no** | — | current_timestamp() | — |

### po_receiving_summary

11 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | UQ FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `po_item_id` | `int(10) unsigned` | **no** | IDX | *NULL* | — |
| 4 | `total_received` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 5 | `total_accepted` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 6 | `total_rejected` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 7 | `total_damaged` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 8 | `remaining_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 9 | `outstanding_quantity` | `decimal(18,3)` | **no** | — | `0.000` | — |
| 10 | `last_receipt_date` | `date` | yes | — | `NULL` | — |
| 11 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### po_status_history

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `po_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | purchase_orders.id (on delete cascade) |
| 3 | `from_status` | `varchar(50)` | yes | — | `NULL` | — |
| 4 | `to_status` | `varchar(50)` | **no** | — | *NULL* | — |
| 5 | `changed_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 6 | `change_note` | `text` | yes | — | `NULL` | — |
| 7 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### report_exports

6 columns · 9 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `report_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 3 | `export_format` | `enum('csv','pdf','print')` | **no** | — | *NULL* | — |
| 4 | `filters` | `longtext` | yes | — | `NULL` | binary collation |
| 5 | `exported_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 6 | `exported_at` | `timestamp` | **no** | IDX | current_timestamp() | — |

### saved_reports

6 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 3 | `report_type` | `varchar(100)` | **no** | IDX | *NULL* | — |
| 4 | `filters` | `longtext` | yes | — | `NULL` | binary collation |
| 5 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### scheduled_reports

12 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `name` | `varchar(255)` | **no** | — | *NULL* | — |
| 3 | `report_type` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `frequency` | `enum('daily','weekly','monthly')` | **no** | IDX | `'weekly'` | — |
| 5 | `filters` | `longtext` | yes | — | `NULL` | binary collation |
| 6 | `recipients` | `text` | yes | — | `NULL` | — |
| 7 | `is_active` | `tinyint(1)` | yes | IDX | `1` | — |
| 8 | `last_run` | `timestamp` | yes | — | `NULL` | — |
| 9 | `next_run` | `timestamp` | yes | IDX | `NULL` | — |
| 10 | `created_by` | `int(10) unsigned` | yes | IDX FK | `NULL` | users.id (on delete set null) |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### software_licenses

12 columns · 5 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `software_name` | `varchar(200)` | **no** | IDX | *NULL* | — |
| 3 | `license_key` | `varchar(500)` | yes | — | `NULL` | — |
| 4 | `purchase_date` | `date` | yes | — | `NULL` | — |
| 5 | `expiry_date` | `date` | yes | IDX | `NULL` | — |
| 6 | `seats` | `int(10) unsigned` | yes | — | `1` | — |
| 7 | `assigned_seats` | `int(10) unsigned` | yes | — | `0` | — |
| 8 | `vendor` | `varchar(200)` | yes | — | `NULL` | — |
| 9 | `status` | `enum('active','expired','cancelled')` | yes | IDX | `'active'` | — |
| 10 | `notes` | `text` | yes | — | `NULL` | — |
| 11 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 12 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### test_order

4 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | — |
| 2 | `qty` | `int(11)` | yes | — | `NULL` | — |
| 3 | `val` | `int(11)` | yes | — | `NULL` | — |
| 4 | `avg` | `decimal(10,2)` | yes | — | `NULL` | — |

### test_order2

4 columns · 1 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(11)` | **no** | PK | *NULL* | — |
| 2 | `qty` | `int(11)` | yes | — | `NULL` | — |
| 3 | `val` | `int(11)` | yes | — | `NULL` | — |
| 4 | `avg` | `decimal(10,2)` | yes | — | `NULL` | — |

### units_of_measure

6 columns · 12 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `code` | `varchar(20)` | **no** | UQ | *NULL* | — |
| 3 | `name` | `varchar(100)` | **no** | — | *NULL* | — |
| 4 | `symbol` | `varchar(20)` | yes | — | `NULL` | — |
| 5 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### user_dashboard_layouts

7 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `user_id` | `int(10) unsigned` | **no** | IDX FK | *NULL* | users.id (on delete cascade) |
| 3 | `dashboard_key` | `varchar(50)` | **no** | IDX | *NULL* | — |
| 4 | `layout_json` | `longtext` | **no** | — | *NULL* | binary collation |
| 5 | `is_customized` | `tinyint(1)` | yes | — | `0` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
| 7 | `updated_at` | `timestamp` | **no** | — | current_timestamp() | on update current_timestamp() |

### voucher_types

6 columns · 31 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `voucher_name` | `varchar(100)` | **no** | — | *NULL* | — |
| 3 | `voucher_prefix` | `varchar(10)` | **no** | — | *NULL* | — |
| 4 | `auto_numbering` | `tinyint(1)` | **no** | — | `1` | — |
| 5 | `status` | `enum('active','inactive')` | **no** | — | `'active'` | — |
| 6 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |

### year_end_closings

21 columns · 0 rows

| # | Column | Type | Null | Key | Default | Notes |
|---:|---|---|---|---|---|---|
| 1 | `id` | `int(10) unsigned` | **no** | PK | *NULL* | auto increment |
| 2 | `financial_year_id` | `int(10) unsigned` | **no** | UQ | *NULL* | — |
| 3 | `next_financial_year_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 4 | `closing_journal_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 5 | `opening_journal_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 6 | `net_profit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 7 | `retained_earnings_account_id` | `int(10) unsigned` | yes | — | `NULL` | — |
| 8 | `closing_method` | `varchar(30)` | **no** | — | `'direct_to_retained'` | — |
| 9 | `total_temp_debit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 10 | `total_temp_credit` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 11 | `opening_debit_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 12 | `opening_credit_total` | `decimal(18,2)` | **no** | — | `0.00` | — |
| 13 | `verification_passed` | `tinyint(1)` | **no** | — | `0` | — |
| 14 | `validation_snapshot` | `longtext` | yes | — | `NULL` | — |
| 15 | `status` | `enum('completed','reopened')` | **no** | — | `'completed'` | — |
| 16 | `closed_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 17 | `closed_at` | `datetime` | yes | — | `NULL` | — |
| 18 | `reopened_by` | `int(10) unsigned` | yes | — | `NULL` | — |
| 19 | `reopened_at` | `datetime` | yes | — | `NULL` | — |
| 20 | `reopen_reason` | `text` | yes | — | `NULL` | — |
| 21 | `created_at` | `timestamp` | **no** | — | current_timestamp() | — |
