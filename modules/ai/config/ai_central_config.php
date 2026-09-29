<?php
/**
 * AI Module Central Configuration
 * 
 * Master configuration for the AI module. Controls module behavior,
 * defaults, and non-secret settings. Never store API keys here.
 */

$AI_CONFIG = [
    // Module master switch
    'enabled' => true,

    // Default provider/model settings
    'defaults' => [
        'max_tokens'        => 2048,
        'temperature'       => 0.7,
        'context_window'    => 128000,
    ],

    // Assistant behavior
    'assistant' => [
        'welcome_message'       => 'Hello! I\'m your AI assistant. I can help you navigate the ERP, look up data, create records, and answer questions. How can I help?',
        'max_message_length'    => 4000,
        'max_conversation_history' => 50,
        'typing_indicator'      => true,
    ],

    // Knowledge base settings
    'knowledge' => [
        'default_chunk_size'    => 500,
        'default_overlap'       => 50,
        'max_file_size_mb'      => 10,
        'supported_types'       => ['txt', 'pdf', 'docx', 'md', 'html', 'csv'],
    ],

    // Task/Automation settings
    'tasks' => [
        'max_steps_per_task'        => 20,
        'max_concurrent_tasks'      => 5,
        'max_workflow_steps'        => 10,
        'max_task_execution_time'   => 300,
    ],

    // Navigation settings
    'navigation' => [
        'max_results'           => 5,
        'min_score_threshold'   => 20,
        'current_module_boost'  => 15,
    ],

    // Agent settings
    'agents' => [
        'max_steps_per_run'            => 10,
        'max_tool_calls_per_run'       => 20,
        'max_runtime_seconds'          => 120,
        'max_concurrent_runs'          => 3,
        'max_nested_depth'             => 1,
        'max_working_memory_chars'     => 8000,
        'allow_write_action_proposals' => true,
        'agent_confirmation_timeout'   => 300,
    ],

    // Tool settings
    'tools' => [
        'max_tool_calls_per_request'    => 5,
        'max_records_returned'          => 20,
        'max_execution_time'            => 30,
        'max_iterations'                => 5,
    ],

    // Security settings
    'security' => [
        'require_confirmation_for_writes'   => true,
        'max_pending_actions'               => 50,
        'pending_action_expiry_minutes'     => 30,
        'rate_limit_requests_per_minute'    => 30,
    ],

    // F13: Scheduler & Events settings
    'scheduler' => [
        'lock_duration_minutes'     => 30,
        'max_concurrent_runs_global' => 10,
        'max_runs_per_hour'         => 20,
        'default_max_runtime'       => 300,
        'default_max_retries'       => 3,
        'default_retry_delay'       => 60,
        'max_retry_delay'           => 3600,
        'scheduler_interval_cron'   => '* * * * *',
        'enable_event_dedup'        => true,
        'event_dedup_window_hours'  => 24,
        'notify_on_failure'         => true,
        'notify_on_completion'      => false,
    ],

    // F14: AI Insights settings
    'insights' => [
        'enabled'                   => true,
        'dashboard_enabled'         => true,
        'max_insights_per_user'     => 50,
        'max_notifications_per_hour' => 30,
        'default_evaluation_frequency' => 'hourly',
        'max_evaluation_runtime'    => 30,
        'max_records_per_evaluation' => 100,
        'enable_ai_narrative'       => true,
        'retention_days'            => 90,
        'max_active_insights'       => 50,
    ],

    // Provider settings
    'providers' => [
        'timeout_seconds'       => 60,
        'retry_attempts'        => 2,
        'verify_ssl'            => true,
        'max_payload_size'      => 1024 * 1024, // 1MB
    ],

    // F16: Management, Health & Governance settings
    'management' => [
        'health_check_interval_minutes' => 15,
        'health_check_timeout_seconds'  => 10,
        'usage_retention_days'          => 90,
        'failed_ops_retention_days'     => 30,
        'audit_log_retention_days'      => 90,
        'max_tokens_per_request'        => 4096,
        'max_cost_per_day'              => 10.00,
        'rate_limit_per_minute'         => 60,
        'enable_auto_purge'             => true,
        'purge_interval_hours'          => 24,
        'kill_switch_file'              => 'config/ai_kill_switches.json',
        'mask_sensitive_data'           => true,
        'sensitive_fields'              => ['api_key', 'secret', 'password', 'token', 'authorization'],
    ],

    // F17: Skills, ERP Learning & Adaptive Assistance
    'skills' => [
        'enabled'                       => true,
        'max_steps_per_skill'           => 20,
        'max_runtime_seconds'           => 300,
        'max_tool_calls_per_skill'      => 10,
        'max_nested_skills'             => 3,
        'max_concurrent_runs'           => 5,
        'max_context_size_chars'        => 4000,
        'auto_suggest_enabled'          => true,
        'adaptive_assistance_enabled'   => true,
        'allow_ai_skill_drafts'         => true,
        'require_approval_to_publish'   => true,
        'retention_days'                => 90,
    ],

    // F19: Collaboration, Workspaces & Shared AI
    'workspaces' => [
        'enabled'                       => true,
        'max_workspaces_per_company'    => 50,
        'max_members_per_workspace'     => 25,
        'max_conversations_per_workspace' => 100,
        'max_pins_per_workspace'        => 50,
        'max_instructions_per_workspace' => 20,
        'max_goals_per_workspace'       => 10,
        'max_research_sessions_per_workspace' => 20,
        'max_context_size_chars'        => 4000,
        'max_activity_retention_days'   => 180,
        'max_notification_retention_days' => 90,
        'enable_presence'               => true,
        'presence_timeout_minutes'      => 5,
        'allow_private_to_shared'       => true,
        'require_confirmation_to_share' => true,
        'allow_agent_result_sharing'    => true,
        'allow_research_sessions'       => true,
        'retention_days'                => 180,
    ],

    // F20: Document Intelligence
    'documents' => [
        'enabled'                       => true,
        'max_file_size_mb'              => 50,
        'allowed_extensions'            => ['pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'tiff', 'bmp', 'webp', 'md', 'html'],
        'max_batch_size'                => 25,
        'max_concurrent_processing'     => 3,
        'max_queue_size'                => 100,
        'ocr_enabled'                   => true,
        'external_ai_allowed'           => true,
        'auto_classify'                 => true,
        'classification_threshold'      => 0.7,
        'auto_index'                    => true,
        'chunk_size'                    => 500,
        'chunk_overlap'                 => 50,
        'max_search_results'            => 50,
        'max_comparison_documents'      => 5,
        'retention_extracted_text_days'  => 365,
        'retention_ocr_days'            => 90,
        'retention_analysis_days'       => 180,
        'max_extracted_text_length'     => 1000000,
        'enable_expiry_reminders'       => true,
        'expiry_reminder_days'          => [90, 60, 30, 7],
    ],

    // AI F21: Voice Interface
    'voice' => [
        'enabled'                       => true,
        'default_provider_stt'          => 'browser',
        'default_provider_tts'          => 'browser',
        'allowed_audio_formats'         => ['audio/webm', 'audio/wav', 'audio/mpeg', 'audio/ogg'],
        'max_audio_size_bytes'          => 26214400,  // 25 MB
        'max_audio_duration_ms'         => 120000,    // 2 minutes
        'min_audio_duration_ms'         => 500,       // 0.5 seconds
        'default_language'              => 'en-US',
        'supported_languages'           => ['en-US', 'en-GB', 'ar-AE', 'ar-SA', 'fr-FR', 'de-DE', 'es-ES'],
        'enable_transcription_preview'  => true,
        'require_preview_before_send'   => true,
        'high_risk_voice_confirm'       => true,
        'enable_dictation'              => true,
        'enable_shortcuts'              => true,
        'enable_conversation_mode'      => true,
        'enable_tts'                    => true,
        'tts_default_speed'             => 1.0,
        'tts_default_voice'             => null,
        'enable_browser_tts_fallback'   => true,
        'limits' => [
            'max_audio_duration_ms'     => 120000,
            'max_file_size_bytes'       => 26214400,
            'requests_per_minute'       => 10,
            'requests_per_user_day'     => 100,
            'monthly_stt_budget'        => 100.00,
            'monthly_tts_budget'        => 50.00,
        ],
        'retention' => [
            'audio_retention_days'       => 7,
            'transcript_retention_days'  => 90,
            'tts_cache_retention_days'   => 1,
            'metadata_retention_days'    => 365,
            'auto_delete_audio'         => true,
            'auto_delete_transcripts'   => false,
        ],
        'kill_switches' => [
            'disable_voice_input'       => false,
            'disable_stt'               => false,
            'disable_tts'               => false,
            'disable_dictation'         => false,
            'disable_voice_commands'    => false,
        ],
    ],
];
