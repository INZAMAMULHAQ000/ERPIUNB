
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) NOT NULL,
  `parent_group_id` int(10) unsigned DEFAULT NULL,
  `nature` enum('asset','liability','equity','income','expense') NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ag_parent` (`parent_group_id`),
  KEY `idx_ag_nature` (`nature`),
  KEY `idx_ag_status` (`status`),
  CONSTRAINT `account_groups_ibfk_1` FOREIGN KEY (`parent_group_id`) REFERENCES `account_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_at_group` (`group_id`),
  CONSTRAINT `account_types_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `account_groups` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounting_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `financial_year_id` int(10) unsigned NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','closing','closed') NOT NULL DEFAULT 'open',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopen_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_accounting_period` (`financial_year_id`,`start_date`,`end_date`),
  KEY `idx_accounting_period_fy` (`financial_year_id`),
  KEY `idx_period_status` (`financial_year_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2071 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `approval_key` varchar(120) NOT NULL,
  `task_id` int(11) NOT NULL,
  `run_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `step_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `requesting_user_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_parameters_json` longtext NOT NULL,
  `parameters_hash` varchar(255) NOT NULL,
  `affected_records_json` longtext DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL,
  `status` enum('pending','approved','rejected','expired','cancelled','delegated') NOT NULL DEFAULT 'pending',
  `approver_id` int(11) DEFAULT NULL,
  `approval_decision` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `delegation_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `approval_key` (`approval_key`),
  KEY `idx_ai_approval_task` (`task_id`),
  KEY `idx_ai_approval_status` (`status`),
  KEY `idx_ai_approval_approver` (`approver_id`),
  KEY `idx_ai_approval_expires` (`expires_at`),
  KEY `idx_ai_approval_company` (`company_id`),
  KEY `run_id` (`run_id`),
  CONSTRAINT `ai_agent_approvals_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_agent_approvals_ibfk_2` FOREIGN KEY (`run_id`) REFERENCES `ai_agent_task_runs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_artifacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_artifacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artifact_key` varchar(100) NOT NULL,
  `task_id` int(11) NOT NULL,
  `run_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `artifact_type` enum('report','draft','summary','document','structured_data','file') NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `content_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `classification` enum('public','internal','confidential','sensitive','restricted') NOT NULL DEFAULT 'internal',
  `metadata_json` longtext DEFAULT NULL,
  `source_step_number` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `artifact_key` (`artifact_key`),
  KEY `idx_ai_artifact_task` (`task_id`),
  KEY `idx_ai_artifact_type` (`artifact_type`),
  KEY `idx_ai_artifact_company` (`company_id`),
  KEY `idx_ai_artifact_user` (`user_id`),
  CONSTRAINT `ai_agent_artifacts_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_checkpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_checkpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `checkpoint_type` enum('auto','manual','approval_required','error','limit_reached') NOT NULL DEFAULT 'auto',
  `state_json` longtext NOT NULL,
  `completed_steps_json` longtext DEFAULT NULL,
  `working_memory_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`working_memory_snapshot`)),
  `pending_approvals_json` longtext DEFAULT NULL,
  `resume_from_step` int(11) NOT NULL,
  `can_resume` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_checkpoint_run` (`run_id`),
  KEY `idx_ai_checkpoint_task` (`task_id`),
  KEY `idx_ai_checkpoint_resume` (`can_resume`),
  CONSTRAINT `ai_agent_checkpoints_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `ai_agent_task_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_agent_checkpoints_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `agent_type` enum('general','specialist','autonomous','workflow') NOT NULL DEFAULT 'specialist',
  `company_id` int(11) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_tools_json` longtext DEFAULT NULL,
  `allowed_actions_json` longtext DEFAULT NULL,
  `allowed_skills_json` longtext DEFAULT NULL,
  `allowed_integrations_json` longtext DEFAULT NULL,
  `allowed_providers_json` longtext DEFAULT NULL,
  `allowed_models_json` longtext DEFAULT NULL,
  `autonomy_level` enum('assist','suggest','confirm','supervised','restricted') NOT NULL DEFAULT 'confirm',
  `max_steps` int(11) NOT NULL DEFAULT 20,
  `max_runtime_seconds` int(11) NOT NULL DEFAULT 300,
  `max_tool_calls` int(11) NOT NULL DEFAULT 30,
  `max_cost` decimal(12,4) NOT NULL DEFAULT 10.0000,
  `max_records_affected` int(11) NOT NULL DEFAULT 100,
  `retry_limit` int(11) NOT NULL DEFAULT 3,
  `approval_required_for_writes` tinyint(1) NOT NULL DEFAULT 1,
  `approval_required_for_high_risk` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_departments_json` longtext DEFAULT NULL,
  `allowed_roles_json` longtext DEFAULT NULL,
  `allowed_users_json` longtext DEFAULT NULL,
  `task_memory_policy` enum('full','summary','none') NOT NULL DEFAULT 'summary',
  `system_instructions` text DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_key` (`agent_key`),
  KEY `idx_ai_agent_def_key` (`agent_key`),
  KEY `idx_ai_agent_def_enabled` (`is_enabled`),
  KEY `idx_ai_agent_def_type` (`agent_type`),
  KEY `idx_ai_agent_def_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `run_id` int(11) DEFAULT NULL,
  `step_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_category` varchar(50) NOT NULL,
  `severity` enum('info','warning','high','critical') NOT NULL DEFAULT 'info',
  `message` text DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_agent_event_task` (`task_id`),
  KEY `idx_ai_agent_event_run` (`run_id`),
  KEY `idx_ai_agent_event_type` (`event_type`),
  KEY `idx_ai_agent_event_severity` (`severity`),
  KEY `idx_ai_agent_event_company` (`company_id`),
  KEY `idx_ai_agent_event_created` (`created_at`),
  CONSTRAINT `ai_agent_events_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_agent_events_ibfk_2` FOREIGN KEY (`run_id`) REFERENCES `ai_agent_task_runs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_plan_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_plan_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `step_key` varchar(100) NOT NULL,
  `objective` text NOT NULL,
  `step_type` enum('tool','action','knowledge','reasoning','navigation','verification','checkpoint') NOT NULL,
  `operation_id` varchar(150) DEFAULT NULL,
  `input_json` longtext DEFAULT NULL,
  `expected_result` text DEFAULT NULL,
  `dependency_step_number` int(11) DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','running','completed','failed','skipped','cancelled','awaiting_approval') NOT NULL DEFAULT 'pending',
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `max_retries` int(11) NOT NULL DEFAULT 2,
  `execution_result_json` longtext DEFAULT NULL,
  `verification_result_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_plan_step_plan` (`plan_id`),
  KEY `idx_ai_plan_step_status` (`status`),
  KEY `idx_ai_plan_step_number` (`plan_id`,`step_number`),
  CONSTRAINT `ai_agent_plan_steps_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `ai_agent_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `plan_version` int(11) NOT NULL DEFAULT 1,
  `plan_json` longtext NOT NULL,
  `status` enum('draft','validated','approved','executing','completed','failed','cancelled') NOT NULL DEFAULT 'draft',
  `estimated_cost` decimal(12,4) DEFAULT NULL,
  `estimated_duration_seconds` int(11) DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `external_systems_json` longtext DEFAULT NULL,
  `requires_approvals` tinyint(1) NOT NULL DEFAULT 0,
  `approval_steps_json` longtext DEFAULT NULL,
  `validated_by_governance` tinyint(1) NOT NULL DEFAULT 0,
  `validation_result_json` longtext DEFAULT NULL,
  `dry_run` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_agent_plan_task` (`task_id`),
  KEY `idx_ai_agent_plan_status` (`status`),
  CONSTRAINT `ai_agent_plans_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_runs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` varchar(100) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `conversation_id` int(10) unsigned DEFAULT NULL,
  `objective` text NOT NULL,
  `status` enum('queued','planning','running','awaiting_confirmation','paused','completed','failed','cancelled','limit_reached') NOT NULL DEFAULT 'queued',
  `current_step` int(10) unsigned DEFAULT 0,
  `total_steps` int(10) unsigned DEFAULT 0,
  `tool_calls_made` int(10) unsigned DEFAULT 0,
  `reasoning_cycles` int(10) unsigned DEFAULT 0,
  `working_memory_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`working_memory_json`)),
  `plan_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`plan_json`)),
  `result_summary` text DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agent` (`agent_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_step_executions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_step_executions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `step_type` varchar(50) NOT NULL,
  `operation_id` varchar(150) DEFAULT NULL,
  `input_json` longtext DEFAULT NULL,
  `output_json` longtext DEFAULT NULL,
  `status` enum('pending','running','completed','failed','skipped','cancelled','awaiting_approval') NOT NULL DEFAULT 'pending',
  `governance_decision` varchar(50) DEFAULT NULL,
  `governance_details_json` longtext DEFAULT NULL,
  `risk_level` varchar(20) DEFAULT NULL,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `approval_id` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `verification_status` varchar(50) DEFAULT NULL,
  `verification_details_json` longtext DEFAULT NULL,
  `token_input` int(11) DEFAULT NULL,
  `token_output` int(11) DEFAULT NULL,
  `estimated_cost` decimal(12,4) DEFAULT NULL,
  `actual_cost` decimal(12,4) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_step_exec_run` (`run_id`),
  KEY `idx_ai_step_exec_task` (`task_id`),
  KEY `idx_ai_step_exec_status` (`status`),
  KEY `idx_ai_step_exec_number` (`run_id`,`step_number`),
  CONSTRAINT `ai_agent_step_executions_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `ai_agent_task_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_agent_step_executions_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agent_run_id` int(10) unsigned NOT NULL,
  `step_number` int(10) unsigned NOT NULL,
  `step_type` enum('tool','action','reasoning','knowledge','navigation') NOT NULL,
  `operation_id` varchar(100) DEFAULT NULL,
  `input_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_json`)),
  `observation_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`observation_json`)),
  `status` enum('pending','running','awaiting_confirmation','completed','failed','skipped','cancelled') NOT NULL DEFAULT 'pending',
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_run` (`agent_run_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ai_agent_steps_ibfk_1` FOREIGN KEY (`agent_run_id`) REFERENCES `ai_agent_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_task_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_task_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `agent_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('queued','running','awaiting_confirmation','awaiting_review','paused','completed','failed','cancelled','limit_reached') NOT NULL DEFAULT 'queued',
  `current_step` int(11) NOT NULL DEFAULT 0,
  `total_steps` int(11) NOT NULL DEFAULT 0,
  `tool_calls_made` int(11) NOT NULL DEFAULT 0,
  `actions_executed` int(11) NOT NULL DEFAULT 0,
  `estimated_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `actual_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `working_memory_json` longtext DEFAULT NULL,
  `result_summary` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_agent_run_task` (`task_id`),
  KEY `idx_ai_agent_run_status` (`status`),
  KEY `idx_ai_agent_run_user` (`user_id`),
  KEY `idx_ai_agent_run_agent` (`agent_id`),
  CONSTRAINT `ai_agent_task_runs_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `ai_agent_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_agent_task_runs_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `ai_agent_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agent_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agent_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_key` varchar(100) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `original_request` text NOT NULL,
  `normalized_intent` text DEFAULT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','planning','awaiting_confirmation','awaiting_review','running','paused','failed','cancelled','completed','partially_completed') NOT NULL DEFAULT 'pending',
  `current_step` int(11) NOT NULL DEFAULT 0,
  `total_steps` int(11) NOT NULL DEFAULT 0,
  `progress_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `result_summary` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `source` enum('chat','command_center','dashboard','workflow','scheduler','integration','api') NOT NULL DEFAULT 'chat',
  `source_id` varchar(100) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_key` (`task_key`),
  KEY `idx_ai_agent_task_status` (`status`),
  KEY `idx_ai_agent_task_agent` (`agent_id`),
  KEY `idx_ai_agent_task_user` (`user_id`),
  KEY `idx_ai_agent_task_company` (`company_id`),
  KEY `idx_ai_agent_task_priority` (`priority`),
  KEY `idx_ai_agent_task_created` (`created_at`),
  CONSTRAINT `ai_agent_tasks_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `ai_agent_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_agents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` varchar(100) NOT NULL,
  `agent_type` enum('specialist','general','monitoring','automation') NOT NULL DEFAULT 'specialist',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `configuration_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration_json`)),
  `allowed_tools_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_tools_json`)),
  `allowed_actions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_actions_json`)),
  `allowed_knowledge_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_knowledge_json`)),
  `max_steps` int(10) unsigned NOT NULL DEFAULT 10,
  `max_tool_calls` int(10) unsigned NOT NULL DEFAULT 20,
  `max_runtime_seconds` int(10) unsigned NOT NULL DEFAULT 120,
  `max_working_memory_chars` int(10) unsigned NOT NULL DEFAULT 8000,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `required_permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_permissions_json`)),
  `system_instructions` text DEFAULT NULL,
  `human_confirmation_policy` enum('always','write_only','never') NOT NULL DEFAULT 'write_only',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_id` (`agent_id`),
  KEY `idx_agent_type` (`agent_type`),
  KEY `idx_module` (`module`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_analytics_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_analytics_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `request_text` text NOT NULL,
  `metric_key` varchar(100) DEFAULT NULL,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `operation_type` varchar(50) NOT NULL,
  `query_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`query_definition`)),
  `execution_time_ms` int(11) DEFAULT NULL,
  `data_volume_rows` int(11) DEFAULT NULL,
  `data_volume_bytes` int(11) DEFAULT NULL,
  `provider_used` varchar(100) DEFAULT NULL,
  `model_used` varchar(100) DEFAULT NULL,
  `forecast_method` varchar(50) DEFAULT NULL,
  `estimated_cost` decimal(10,6) DEFAULT NULL,
  `result_summary` text DEFAULT NULL,
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_json`)),
  `governance_decision` varchar(50) DEFAULT NULL,
  `risk_level` varchar(20) DEFAULT NULL,
  `feedback` enum('helpful','not_helpful','incorrect','incomplete') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_run_company` (`company_id`),
  KEY `idx_analytics_run_metric` (`metric_key`),
  KEY `idx_analytics_run_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'provider|model|agent|knowledge|tool',
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_audit_user` (`user_id`),
  KEY `idx_ai_audit_action` (`action`),
  KEY `idx_ai_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_ai_audit_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1091 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_automation_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_automation_notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `automation_id` int(10) unsigned DEFAULT NULL,
  `run_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `notification_type` enum('in_app','email','webhook') NOT NULL DEFAULT 'in_app',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`read_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_automation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_automation_runs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `automation_id` int(10) unsigned NOT NULL,
  `task_id` int(10) unsigned DEFAULT NULL,
  `status` enum('running','completed','failed','cancelled') NOT NULL DEFAULT 'running',
  `trigger_type` enum('manual','scheduled','event') DEFAULT 'manual',
  `event_name` varchar(150) DEFAULT NULL,
  `event_record_id` int(10) unsigned DEFAULT NULL,
  `lock_key` varchar(100) DEFAULT NULL,
  `retry_attempt` int(10) unsigned DEFAULT 0,
  `notification_sent` tinyint(1) DEFAULT 0,
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_json`)),
  `error_message` text DEFAULT NULL,
  `triggered_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_automation` (`automation_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ai_automation_runs_ibfk_1` FOREIGN KEY (`automation_id`) REFERENCES `ai_automations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_automation_triggers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_automation_triggers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `automation_id` int(10) unsigned NOT NULL,
  `trigger_type` enum('manual','scheduled','event') NOT NULL DEFAULT 'manual',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_automation` (`automation_id`),
  KEY `idx_type_enabled` (`trigger_type`,`enabled`),
  CONSTRAINT `ai_automation_triggers_ibfk_1` FOREIGN KEY (`automation_id`) REFERENCES `ai_automations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_automations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_automations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `trigger_type` enum('manual','scheduled','event') NOT NULL DEFAULT 'manual',
  `schedule_type` enum('none','interval','daily','weekly') DEFAULT 'none',
  `schedule_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_config_json`)),
  `schedule_timezone` varchar(50) DEFAULT 'UTC',
  `trigger_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_config_json`)),
  `workflow_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`workflow_json`)),
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `safety_classification` enum('read_only','confirmation_required','blocked') DEFAULT 'read_only',
  `max_retries` int(10) unsigned DEFAULT 3,
  `retry_delay_seconds` int(10) unsigned DEFAULT 60,
  `max_concurrent_runs` int(10) unsigned DEFAULT 1,
  `retry_count` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_autonomy_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_autonomy_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `autonomy_level` enum('assist','suggest','confirm','supervised','restricted') NOT NULL DEFAULT 'suggest',
  `max_steps_per_task` int(11) NOT NULL DEFAULT 20,
  `max_tool_calls_per_run` int(11) NOT NULL DEFAULT 20,
  `max_runtime_seconds` int(11) NOT NULL DEFAULT 120,
  `max_cost_per_day` decimal(12,2) NOT NULL DEFAULT 10.00,
  `max_records_per_action` int(11) NOT NULL DEFAULT 100,
  `allowed_risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `require_confirmation_for_writes` tinyint(1) NOT NULL DEFAULT 1,
  `require_review_for_high_risk` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_autonomy_company` (`company_id`),
  KEY `idx_ai_autonomy_role` (`role_id`),
  KEY `idx_ai_autonomy_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_bulk_action_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_bulk_action_thresholds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(100) NOT NULL,
  `threshold` int(11) NOT NULL DEFAULT 100,
  `required_action` enum('confirm','review','deny') NOT NULL DEFAULT 'review',
  `company_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_bulk_action_type` (`action_type`),
  KEY `idx_ai_bulk_action_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_command_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_command_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `title` varchar(255) DEFAULT NULL,
  `context_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_json`)),
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_message` (`last_message_at`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_command_executions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_command_executions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned DEFAULT NULL,
  `message_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `command_summary` varchar(500) DEFAULT NULL,
  `intent` varchar(50) NOT NULL,
  `capability_chain` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capability_chain`)),
  `status` enum('running','completed','failed','cancelled') NOT NULL DEFAULT 'running',
  `result_summary` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT 0,
  `tools_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tools_used`)),
  `records_affected` int(10) unsigned DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_intent` (`intent`),
  KEY `idx_status` (`status`),
  KEY `idx_started` (`started_at`),
  CONSTRAINT `ai_command_executions_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `ai_command_conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_command_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_command_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `intent` varchar(50) DEFAULT NULL,
  `capabilities_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capabilities_used`)),
  `response_type` varchar(50) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_role` (`role`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `ai_command_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `ai_command_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_command_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_command_suggestions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `suggestion_text` varchar(255) NOT NULL,
  `intent_hint` varchar(50) DEFAULT NULL,
  `required_permission` varchar(100) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_key` varchar(64) NOT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'send,draft,bulk_draft,rule_execute,template_activate,follow_up_create',
  `draft_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `followup_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending' COMMENT 'pending,approved,sending,sent,failed,cancelled,rejected',
  `risk_level` varchar(20) DEFAULT 'low',
  `approval_required` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `recipient_count` int(11) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `governance_decision` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`governance_decision`)),
  `confirmation_token` varchar(64) DEFAULT NULL,
  `confirmation_parameters_hash` varchar(64) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `external_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`external_ids`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`company_id`,`action_type`),
  KEY `idx_status` (`status`),
  KEY `idx_draft` (`draft_id`),
  KEY `idx_confirmation` (`confirmation_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_batch_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_batch_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_key` varchar(64) NOT NULL,
  `job_type` varchar(50) NOT NULL COMMENT 'bulk_draft,bulk_classify,bulk_followup',
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'selection criteria' CHECK (json_valid(`criteria`)),
  `total_records` int(11) DEFAULT 0,
  `processed_records` int(11) DEFAULT 0,
  `success_records` int(11) DEFAULT 0,
  `failed_records` int(11) DEFAULT 0,
  `preview_drafts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'sample drafts for review' CHECK (json_valid(`preview_drafts`)),
  `status` varchar(30) DEFAULT 'pending' COMMENT 'pending,previewing,approved,running,completed,failed,cancelled',
  `approval_required` tinyint(1) DEFAULT 1,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_classifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `communication_id` int(11) DEFAULT NULL COMMENT 'reference to ERP communication record',
  `communication_type` varchar(50) DEFAULT NULL,
  `message_text` longtext DEFAULT NULL,
  `classification` varchar(50) NOT NULL COMMENT 'inquiry,complaint,payment,delivery,quotation,support,sales,procurement,approval,urgent,informational,spam',
  `confidence` decimal(5,4) NOT NULL DEFAULT 0.5000,
  `sub_classification` varchar(100) DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal' COMMENT 'low,normal,high,urgent',
  `priority_reason` text DEFAULT NULL,
  `priority_evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`priority_evidence`)),
  `auto_classified` tinyint(1) DEFAULT 1,
  `corrected_by` int(11) DEFAULT NULL,
  `corrected_at` datetime DEFAULT NULL,
  `correction_reason` text DEFAULT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_classification` (`company_id`,`classification`),
  KEY `idx_priority` (`priority`),
  KEY `idx_communication` (`communication_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_context`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_context` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `draft_id` int(11) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `context_type` varchar(50) NOT NULL COMMENT 'erp_record,communication_history,document,intelligence,workflow',
  `context_key` varchar(255) NOT NULL,
  `context_id` int(11) DEFAULT NULL,
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'assembled context with F24 filtered data' CHECK (json_valid(`context_data`)),
  `data_classification` varchar(30) DEFAULT 'internal' COMMENT 'public,internal,confidential,restricted',
  `permission_required` varchar(100) DEFAULT NULL,
  `included_by_user` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_draft` (`company_id`,`draft_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_context_type` (`context_type`),
  KEY `draft_id` (`draft_id`),
  CONSTRAINT `ai_communication_context_ibfk_1` FOREIGN KEY (`draft_id`) REFERENCES `ai_communication_drafts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `draft_key` varchar(64) NOT NULL,
  `context_type` varchar(50) NOT NULL COMMENT 'customer,supplier,employee,invoice,po,quotation,ticket,document,manual',
  `context_id` int(11) DEFAULT NULL,
  `communication_type` varchar(50) NOT NULL COMMENT 'email,internal_message,customer_reply,supplier_reply,notice,follow_up,reminder,meeting_summary,escalation,approval_request,business_letter',
  `subject` varchar(500) DEFAULT NULL,
  `recipient` varchar(500) DEFAULT NULL,
  `recipient_type` varchar(50) DEFAULT NULL COMMENT 'customer,supplier,employee,internal',
  `recipient_email` varchar(255) DEFAULT NULL,
  `cc` varchar(500) DEFAULT NULL,
  `tone` varchar(50) DEFAULT 'professional',
  `formality` varchar(50) DEFAULT 'standard',
  `language` varchar(10) DEFAULT 'en',
  `length` varchar(20) DEFAULT 'standard',
  `urgency` varchar(20) DEFAULT 'normal',
  `body` longtext NOT NULL,
  `body_html` longtext DEFAULT NULL,
  `plain_text` longtext DEFAULT NULL,
  `verified_facts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ERP facts used in draft' CHECK (json_valid(`verified_facts`)),
  `source_references` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'source records referenced' CHECK (json_valid(`source_references`)),
  `placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'missing info placeholders' CHECK (json_valid(`placeholders`)),
  `provider` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost_usd` decimal(10,6) DEFAULT 0.000000,
  `status` varchar(30) DEFAULT 'draft' COMMENT 'draft,editing,sent,failed,cancelled',
  `risk_level` varchar(20) DEFAULT 'low' COMMENT 'low,medium,high,critical',
  `approval_required` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `send_status` varchar(30) DEFAULT NULL COMMENT 'pending,sending,sent,delivery_failed,bounced',
  `sent_at` datetime DEFAULT NULL,
  `external_message_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_user` (`company_id`,`user_id`),
  KEY `idx_context` (`context_type`,`context_id`),
  KEY `idx_status` (`status`),
  KEY `idx_draft_key` (`draft_key`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'draft_created,draft_edited,draft_sent,classify,translate,summarize,followup_detected,template_activated,rule_executed,approval_requested,approval_granted,approval_denied,send_attempt,send_success,send_failed,feedback',
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'draft,template,followup,classification,summary',
  `entity_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost_usd` decimal(10,6) DEFAULT 0.000000,
  `latency_ms` int(11) DEFAULT 0,
  `context_sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_sources`)),
  `governance_decision` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`governance_decision`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`company_id`,`event_type`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `followup_key` varchar(64) NOT NULL,
  `context_type` varchar(50) NOT NULL COMMENT 'unanswered_message,overdue_commitment,pending_quotation,overdue_invoice,unresolved_ticket,supplier_delay',
  `context_id` int(11) DEFAULT NULL,
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_data`)),
  `reason` text NOT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `suggested_action` varchar(50) DEFAULT 'draft' COMMENT 'draft,schedule,task,ignore,snooze',
  `status` varchar(30) DEFAULT 'detected' COMMENT 'detected,drafting,scheduled,tasked,ignored,snoozed,completed',
  `snoozed_until` datetime DEFAULT NULL,
  `draft_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_context` (`context_type`,`context_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `rule_key` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `rule_type` varchar(50) NOT NULL COMMENT 'follow_up,escalate,notify,draft,task,flag',
  `trigger_condition` varchar(100) NOT NULL COMMENT 'unanswered_days,overdue_days,no_response,pending_quota,inactive',
  `trigger_value` int(11) NOT NULL DEFAULT 7,
  `target_context` varchar(50) NOT NULL COMMENT 'customer,supplier,invoice,po,quotation,ticket,all',
  `action_type` varchar(50) NOT NULL COMMENT 'draft_followup,create_task,notify_owner,escalate,flag_urgent',
  `action_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_config`)),
  `enabled` tinyint(1) DEFAULT 1,
  `ai_recommended` tinyint(1) DEFAULT 0,
  `activated_by` int(11) DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `run_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_enabled` (`company_id`,`enabled`),
  KEY `idx_trigger` (`trigger_condition`,`target_context`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `summary_key` varchar(64) NOT NULL,
  `context_type` varchar(50) NOT NULL,
  `context_id` int(11) DEFAULT NULL,
  `summary_text` longtext NOT NULL,
  `key_topics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'extracted key topics' CHECK (json_valid(`key_topics`)),
  `unresolved_issues` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'unresolved issues found' CHECK (json_valid(`unresolved_issues`)),
  `decisions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'decisions mentioned' CHECK (json_valid(`decisions`)),
  `commitments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'commitments made' CHECK (json_valid(`commitments`)),
  `dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'important dates mentioned' CHECK (json_valid(`dates`)),
  `action_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'action items extracted' CHECK (json_valid(`action_items`)),
  `sentiment` varchar(30) DEFAULT NULL COMMENT 'positive,neutral,negative,mixed - AI interpretation',
  `next_recommended_step` text DEFAULT NULL,
  `message_count` int(11) DEFAULT 0,
  `date_range_start` date DEFAULT NULL,
  `date_range_end` date DEFAULT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_context` (`company_id`,`context_type`,`context_id`),
  KEY `idx_summary_key` (`summary_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_template_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `subject_template` varchar(500) DEFAULT NULL,
  `body_template` longtext NOT NULL,
  `body_html_template` longtext DEFAULT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `changed_by` int(11) NOT NULL,
  `change_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_template_version` (`template_id`,`version`),
  CONSTRAINT `ai_communication_template_versions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `ai_communication_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_communication_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_communication_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `template_key` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `communication_type` varchar(50) NOT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'payment,quotation,order,support,onboarding,renewal,custom',
  `subject_template` varchar(500) DEFAULT NULL,
  `body_template` longtext NOT NULL,
  `body_html_template` longtext DEFAULT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'allowed template variables' CHECK (json_valid(`variables`)),
  `tone` varchar(50) DEFAULT 'professional',
  `language` varchar(10) DEFAULT 'en',
  `version` int(11) DEFAULT 1,
  `status` varchar(30) DEFAULT 'draft' COMMENT 'draft,active,inactive,archived',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `ai_generated` tinyint(1) DEFAULT 0,
  `usage_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_category` (`company_id`,`category`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`communication_type`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_config_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_config_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config_type` varchar(100) NOT NULL,
  `config_id` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `previous_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_config_type` (`config_type`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_confirmation_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_confirmation_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_key` varchar(120) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_data_json` longtext DEFAULT NULL,
  `parameters_hash` varchar(255) DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL,
  `status` enum('pending','confirmed','expired','cancelled') NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_key` (`token_key`),
  KEY `idx_ai_confirm_token` (`token_key`),
  KEY `idx_ai_confirm_user` (`user_id`),
  KEY `idx_ai_confirm_company` (`company_id`),
  KEY `idx_ai_confirm_status` (`status`),
  KEY `idx_ai_confirm_expires` (`expires_at`),
  KEY `idx_ai_confirm_request` (`request_id`),
  KEY `confirmed_by` (`confirmed_by`),
  CONSTRAINT `ai_confirmation_tokens_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `ai_observability_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_confirmation_tokens_ibfk_2` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_connector_capabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_connector_capabilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `capability_key` varchar(150) NOT NULL,
  `connector_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `input_schema_json` longtext DEFAULT NULL,
  `output_schema_json` longtext DEFAULT NULL,
  `required_permission` varchar(150) DEFAULT NULL,
  `safety_classification` enum('normal','sensitive','restricted') NOT NULL DEFAULT 'normal',
  `operation_type` enum('read','write','destructive','restricted') NOT NULL DEFAULT 'read',
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `rate_limit_per_minute` int(11) NOT NULL DEFAULT 30,
  `rate_limit_per_hour` int(11) NOT NULL DEFAULT 500,
  `rate_limit_per_day` int(11) NOT NULL DEFAULT 5000,
  `concurrent_limit` int(11) NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `capability_key` (`capability_key`),
  KEY `idx_ai_connector_caps_connector` (`connector_key`),
  KEY `idx_ai_connector_caps_operation` (`operation_type`)
) ENGINE=InnoDB AUTO_INCREMENT=705 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_connector_rest_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_connector_rest_endpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) DEFAULT NULL,
  `connector_key` varchar(100) NOT NULL,
  `capability_key` varchar(150) NOT NULL,
  `endpoint_key` varchar(150) NOT NULL,
  `name` varchar(150) NOT NULL,
  `approved_base_url` varchar(255) NOT NULL,
  `approved_path` varchar(255) NOT NULL,
  `http_method` enum('GET','POST','PUT','PATCH','DELETE') NOT NULL DEFAULT 'GET',
  `allowed_query_params_json` longtext DEFAULT NULL,
  `request_schema_json` longtext DEFAULT NULL,
  `output_schema_json` longtext DEFAULT NULL,
  `auth_strategy` varchar(50) NOT NULL DEFAULT 'api_key',
  `safety_classification` enum('normal','sensitive','restricted') NOT NULL DEFAULT 'normal',
  `rate_limit_per_minute` int(11) NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_key` (`endpoint_key`),
  KEY `idx_ai_rest_endpoints_connection` (`connection_id`),
  KEY `idx_ai_rest_endpoints_connector` (`connector_key`),
  KEY `idx_ai_rest_endpoints_capability` (`capability_key`)
) ENGINE=InnoDB AUTO_INCREMENT=327 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_connectors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_connectors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connector_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `version` varchar(30) NOT NULL DEFAULT '1.0.0',
  `auth_types_json` longtext DEFAULT NULL,
  `supported_capabilities_json` longtext DEFAULT NULL,
  `configuration_schema_json` longtext DEFAULT NULL,
  `status` enum('available','disabled','deprecated','maintenance') NOT NULL DEFAULT 'available',
  `safety_classification` enum('normal','sensitive','restricted') NOT NULL DEFAULT 'normal',
  `supports_webhooks` tinyint(1) NOT NULL DEFAULT 0,
  `supports_sync` tinyint(1) NOT NULL DEFAULT 0,
  `metadata_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `connector_key` (`connector_key`),
  KEY `idx_ai_connectors_category` (`category`),
  KEY `idx_ai_connectors_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=529 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_conversation_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_conversation_shares` (
  `share_id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `original_type` enum('private','shared_workspace') NOT NULL DEFAULT 'private',
  `workspace_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`share_id`),
  KEY `idx_cshare_conv` (`conversation_id`),
  KEY `idx_cshare_ws` (`workspace_id`),
  CONSTRAINT `ai_conversation_shares_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'New Conversation',
  `provider_id` int(10) unsigned NOT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active|archived',
  `message_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_conv_user` (`user_id`),
  KEY `idx_ai_conv_status` (`status`),
  KEY `idx_ai_conv_last_msg` (`last_message_at`),
  KEY `fk_ai_conv_provider` (`provider_id`),
  KEY `fk_ai_conv_model` (`model_id`),
  CONSTRAINT `fk_ai_conv_model` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_conv_provider` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_conv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_copilot_capabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_copilot_capabilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `capability_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `feature` varchar(50) NOT NULL,
  `intent_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`intent_types`)),
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `requires_review` tinyint(1) NOT NULL DEFAULT 0,
  `max_risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `priority` int(11) NOT NULL DEFAULT 50,
  `handler_class` varchar(200) DEFAULT NULL,
  `handler_method` varchar(200) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_copilot_cap_key` (`capability_key`),
  KEY `idx_copilot_cap_module` (`module`),
  KEY `idx_copilot_cap_enabled` (`is_enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_copilot_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_copilot_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `feedback_type` enum('helpful','not_helpful','incorrect','incomplete','successful','partial','unsafe') NOT NULL,
  `feedback_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_copilot_feedback_type` (`feedback_type`),
  KEY `idx_copilot_feedback_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_copilot_intents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_copilot_intents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `raw_text` text NOT NULL,
  `classified_intent` varchar(100) NOT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `routing_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`routing_result`)),
  `execution_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`execution_result`)),
  `latency_ms` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_copilot_intent_class` (`classified_intent`),
  KEY `idx_copilot_intent_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_copilot_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_copilot_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content_text` longtext NOT NULL,
  `intent_type` varchar(100) DEFAULT NULL,
  `intent_confidence` decimal(5,4) DEFAULT NULL,
  `intent_parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`intent_parameters`)),
  `capabilities_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capabilities_used`)),
  `routing_decision` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`routing_decision`)),
  `governance_decision` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`governance_decision`)),
  `provider_used` varchar(100) DEFAULT NULL,
  `model_used` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT NULL,
  `estimated_cost` decimal(10,6) DEFAULT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `response_components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_components`)),
  `citations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`citations`)),
  `actionable_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`actionable_items`)),
  `f25_task_id` int(11) DEFAULT NULL,
  `feedback` enum('helpful','not_helpful','incorrect','incomplete') DEFAULT NULL,
  `feedback_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_copilot_msg_session` (`session_id`),
  KEY `idx_copilot_msg_company` (`company_id`),
  KEY `idx_copilot_msg_intent` (`intent_type`),
  CONSTRAINT `ai_copilot_messages_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `ai_copilot_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_copilot_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_copilot_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_key` varchar(100) NOT NULL,
  `route_path` varchar(500) NOT NULL,
  `module` varchar(50) NOT NULL,
  `page_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `nav_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`nav_keywords`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `permission_required` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_copilot_route_key` (`route_key`),
  KEY `idx_copilot_route_module` (`module`),
  KEY `idx_copilot_route_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_copilot_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_copilot_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `source` enum('web','voice','notification','intelligence','document','workflow','command_center') NOT NULL DEFAULT 'web',
  `context_module` varchar(50) DEFAULT NULL,
  `context_page` varchar(100) DEFAULT NULL,
  `context_record_type` varchar(100) DEFAULT NULL,
  `context_record_id` int(11) DEFAULT NULL,
  `context_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_metadata`)),
  `status` enum('active','completed','abandoned') NOT NULL DEFAULT 'active',
  `message_count` int(11) NOT NULL DEFAULT 0,
  `last_intent` varchar(100) DEFAULT NULL,
  `total_cost` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_copilot_session_company` (`company_id`),
  KEY `idx_copilot_session_user` (`user_id`),
  KEY `idx_copilot_session_status` (`status`),
  KEY `idx_copilot_session_started` (`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_cost_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_cost_controls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `control_type` enum('per_user_daily','per_company_daily','per_model_daily','monthly_company','per_agent_run','per_automation_run') NOT NULL,
  `target_id` int(10) unsigned DEFAULT NULL,
  `max_cost` decimal(10,2) NOT NULL,
  `current_cost` decimal(10,2) DEFAULT 0.00,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type_target` (`control_type`,`target_id`),
  KEY `idx_period` (`period_start`,`period_end`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_data_classification_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_data_classification_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classification_id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `classification` enum('public','internal','confidential','sensitive','restricted') NOT NULL,
  `mask_at_output` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_dc_fields_module_table_field` (`module_name`,`table_name`,`field_name`),
  KEY `idx_ai_dc_fields_class` (`classification_id`),
  KEY `idx_ai_dc_fields_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_data_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_data_classifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classification` enum('public','internal','confidential','sensitive','restricted') NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `masking_required` tinyint(1) NOT NULL DEFAULT 0,
  `allowed_providers_json` longtext DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_ai_data_class_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_dimension_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_dimension_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dimension_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `source_table` varchar(100) NOT NULL,
  `source_column` varchar(100) NOT NULL,
  `label_column` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dim_key` (`dimension_key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_action_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_action_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_id` varchar(200) NOT NULL,
  `pending_action_id` int(11) DEFAULT NULL,
  `proposed_params_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`proposed_params_json`)),
  `status` enum('pending_confirmation','confirmed','executed','cancelled','failed') NOT NULL DEFAULT 'pending_confirmation',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `idx_doc_action_company` (`company_id`),
  KEY `idx_doc_action_document` (`document_id`),
  KEY `idx_doc_action_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_admin_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_admin_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_doc_admin_config` (`company_id`,`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_audit` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `action_type` enum('registered','processing_started','ocr_completed','classification_suggested','extraction_completed','extraction_reviewed','document_linked','knowledge_indexed','comparison_generated','erp_draft_proposed','action_confirmed','qna_asked','version_suggested','relationship_suggested','action_proposed','deleted') NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `idx_audit_doc` (`document_id`),
  KEY `idx_audit_action` (`action_type`),
  KEY `idx_audit_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_chunks` (
  `chunk_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `chunk_index` int(11) NOT NULL,
  `content` text NOT NULL,
  `page_number` int(11) DEFAULT NULL,
  `start_offset` int(11) DEFAULT NULL,
  `end_offset` int(11) DEFAULT NULL,
  `embedding_id` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chunk_id`),
  KEY `idx_chunk_doc` (`document_id`),
  CONSTRAINT `ai_document_chunks_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_classifications` (
  `classification_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `suggested_type` enum('unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other') NOT NULL,
  `confidence` decimal(3,2) NOT NULL,
  `reason_summary` text DEFAULT NULL,
  `method` enum('rule','ai','hybrid') NOT NULL DEFAULT 'ai',
  `is_accepted` tinyint(1) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`classification_id`),
  KEY `idx_class_doc` (`document_id`),
  CONSTRAINT `ai_document_classifications_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_comparisons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_comparisons` (
  `comparison_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `comparison_type` enum('quotation','invoice','contract','general') NOT NULL DEFAULT 'general',
  `document_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`document_ids`)),
  `comparison_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`comparison_result`)),
  `summary` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`comparison_id`),
  KEY `idx_comp_company` (`company_id`),
  KEY `idx_comp_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_expiry_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_expiry_reminders` (
  `reminder_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `reminder_type` enum('90_days','60_days','30_days','7_days','expired') NOT NULL,
  `expiry_date` date NOT NULL,
  `is_sent` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reminder_id`),
  KEY `idx_expiry_doc` (`document_id`),
  KEY `idx_expiry_date` (`expiry_date`),
  CONSTRAINT `ai_document_expiry_reminders_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_extractions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_extractions` (
  `extraction_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `extraction_type` varchar(100) NOT NULL,
  `extracted_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`extracted_data`)),
  `confidence` decimal(3,2) DEFAULT NULL,
  `validation_status` enum('pending','valid','invalid','needs_review') NOT NULL DEFAULT 'pending',
  `validation_errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_errors`)),
  `evidence_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_json`)),
  `provider` varchar(100) DEFAULT NULL,
  `extraction_method` varchar(100) DEFAULT NULL,
  `review_status` enum('pending','accepted','partially_accepted','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`extraction_id`),
  KEY `idx_extract_doc` (`document_id`),
  CONSTRAINT `ai_document_extractions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_field_confidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_field_confidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `extraction_id` int(11) DEFAULT NULL,
  `field_name` varchar(150) NOT NULL,
  `field_value` text DEFAULT NULL,
  `confidence` decimal(5,4) NOT NULL DEFAULT 0.5000,
  `source_page` int(11) DEFAULT NULL,
  `source_section` varchar(255) DEFAULT NULL,
  `source_text` text DEFAULT NULL,
  `extraction_method` varchar(100) DEFAULT NULL,
  `verification_status` enum('ai_extracted','human_verified','human_edited','rejected') NOT NULL DEFAULT 'ai_extracted',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_doc_fc_document` (`document_id`),
  KEY `idx_ai_doc_fc_extraction` (`extraction_id`),
  KEY `idx_ai_doc_fc_field` (`field_name`),
  KEY `idx_ai_doc_fc_verification` (`verification_status`),
  CONSTRAINT `ai_document_field_confidence_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `field_name` varchar(150) NOT NULL,
  `field_value` varchar(500) DEFAULT NULL,
  `target_module` varchar(100) NOT NULL,
  `target_table` varchar(100) NOT NULL,
  `target_record_id` int(11) NOT NULL,
  `target_record_label` varchar(255) DEFAULT NULL,
  `match_confidence` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `match_method` varchar(100) DEFAULT NULL,
  `match_status` enum('suggested','accepted','rejected','auto_matched') NOT NULL DEFAULT 'suggested',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_doc_match_document` (`document_id`),
  KEY `idx_ai_doc_match_company` (`company_id`),
  KEY `idx_ai_doc_match_target` (`target_module`,`target_table`,`target_record_id`),
  KEY `idx_ai_doc_match_status` (`match_status`),
  CONSTRAINT `ai_document_matches_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_qna_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_qna_sessions` (
  `qna_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` longtext NOT NULL,
  `confidence` decimal(3,2) DEFAULT NULL,
  `evidence_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`qna_id`),
  KEY `idx_qna_company` (`company_id`),
  KEY `idx_qna_document` (`document_id`),
  KEY `idx_qna_user` (`user_id`),
  CONSTRAINT `ai_document_qna_sessions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_queue` (
  `queue_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 5,
  `task_type` enum('extract_text','ocr','classify','structured_extract','validate','index','summarize','compare') NOT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`queue_id`),
  KEY `idx_queue_status` (`status`,`priority`),
  KEY `idx_queue_doc` (`document_id`),
  CONSTRAINT `ai_document_queue_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_relationships` (
  `relationship_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `source_document_id` int(11) NOT NULL,
  `target_document_id` int(11) DEFAULT NULL,
  `target_module` varchar(100) DEFAULT NULL,
  `target_record_id` int(11) DEFAULT NULL,
  `relationship_type` varchar(100) NOT NULL,
  `confidence` decimal(3,2) DEFAULT NULL,
  `method` enum('manual','ai_suggested','verified') NOT NULL DEFAULT 'manual',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`relationship_id`),
  KEY `idx_rel_source` (`source_document_id`),
  KEY `idx_rel_target` (`target_document_id`),
  CONSTRAINT `ai_document_relationships_ibfk_1` FOREIGN KEY (`source_document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_retention_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `retention_days` int(11) NOT NULL DEFAULT 365,
  `archive_after_days` int(11) DEFAULT NULL,
  `delete_after_days` int(11) DEFAULT NULL,
  `legal_hold_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_doc_ret_company` (`company_id`),
  KEY `idx_ai_doc_ret_type` (`document_type`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `event_type` enum('injection_detected','malicious_file','unauthorized_access','classification_violation','retention_violation','external_processing_blocked','secret_detected') NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `details_json` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_doc_sec_company` (`company_id`),
  KEY `idx_ai_doc_sec_type` (`event_type`),
  KEY `idx_ai_doc_sec_severity` (`severity`),
  KEY `idx_ai_doc_sec_document` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_summaries` (
  `summary_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `summary_type` enum('short','executive','key_terms','risks','dates','missing_info') NOT NULL,
  `content` text NOT NULL,
  `facts_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`facts_json`)),
  `interpretations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`interpretations_json`)),
  `risks_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`risks_json`)),
  `created_by_ai` tinyint(1) NOT NULL DEFAULT 1,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`summary_id`),
  KEY `idx_summary_doc` (`document_id`),
  CONSTRAINT `ai_document_summaries_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_three_way_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_three_way_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `invoice_document_id` int(11) DEFAULT NULL,
  `po_document_id` int(11) DEFAULT NULL,
  `delivery_document_id` int(11) DEFAULT NULL,
  `invoice_record_id` int(11) DEFAULT NULL,
  `po_record_id` int(11) DEFAULT NULL,
  `delivery_record_id` int(11) DEFAULT NULL,
  `match_status` enum('pending','matched','discrepancy','manual_review') NOT NULL DEFAULT 'pending',
  `discrepancies_json` longtext DEFAULT NULL,
  `match_details_json` longtext DEFAULT NULL,
  `total_invoice` decimal(15,2) DEFAULT NULL,
  `total_po` decimal(15,2) DEFAULT NULL,
  `total_delivery` decimal(15,2) DEFAULT NULL,
  `quantity_match` tinyint(1) DEFAULT NULL,
  `price_match` tinyint(1) DEFAULT NULL,
  `tax_match` tinyint(1) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_doc_3way_company` (`company_id`),
  KEY `idx_ai_doc_3way_status` (`match_status`),
  KEY `idx_ai_doc_3way_invoice` (`invoice_document_id`),
  KEY `idx_ai_doc_3way_po` (`po_document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_type_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_type_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `type_key` varchar(100) NOT NULL,
  `type_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `extraction_schema_json` longtext DEFAULT NULL,
  `auto_extract` tinyint(1) NOT NULL DEFAULT 1,
  `requires_review` tinyint(1) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_doc_type_config` (`company_id`,`type_key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_document_version_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_document_version_suggestions` (
  `suggestion_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `source_document_id` int(11) NOT NULL,
  `target_document_id` int(11) NOT NULL,
  `confidence` decimal(3,2) NOT NULL,
  `status` enum('pending_review','accepted','rejected') NOT NULL DEFAULT 'pending_review',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`suggestion_id`),
  KEY `idx_doc_version_company` (`company_id`),
  KEY `idx_doc_version_source` (`source_document_id`),
  KEY `idx_doc_version_target` (`target_document_id`),
  CONSTRAINT `ai_document_version_suggestions_ibfk_1` FOREIGN KEY (`source_document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_document_version_suggestions_ibfk_2` FOREIGN KEY (`target_document_id`) REFERENCES `ai_documents` (`document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `file_reference` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `file_hash` varchar(64) DEFAULT NULL,
  `source_module` varchar(100) DEFAULT NULL,
  `related_record_type` varchar(100) DEFAULT NULL,
  `related_record_id` int(11) DEFAULT NULL,
  `document_type` enum('unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other') NOT NULL DEFAULT 'unknown',
  `classification_confidence` decimal(3,2) DEFAULT NULL,
  `classification_reason` text DEFAULT NULL,
  `processing_status` enum('uploaded','queued','processing','needs_review','ready','failed','unsupported') NOT NULL DEFAULT 'uploaded',
  `extraction_status` enum('pending','extracting','extracted','validated','reviewed','failed') NOT NULL DEFAULT 'pending',
  `extracted_text` longtext DEFAULT NULL,
  `ocr_confidence` decimal(3,2) DEFAULT NULL,
  `ocr_language` varchar(10) DEFAULT NULL,
  `duplicate_status` enum('unique','possible_duplicate','duplicate') NOT NULL DEFAULT 'unique',
  `duplicate_of_id` int(11) DEFAULT NULL,
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0,
  `sensitive_category` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `status` enum('active','archived','deleted') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`document_id`),
  KEY `idx_doc_company` (`company_id`),
  KEY `idx_doc_type` (`document_type`),
  KEY `idx_doc_processing` (`processing_status`),
  KEY `idx_doc_extraction` (`extraction_status`),
  KEY `idx_doc_hash` (`file_hash`),
  KEY `idx_doc_status` (`status`),
  KEY `idx_doc_source` (`source_module`,`related_record_type`,`related_record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_emergency_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_emergency_controls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `control_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'global',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `activated_by` int(11) DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `deactivated_by` int(11) DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `control_key` (`control_key`),
  KEY `idx_ai_emergency_key` (`control_key`),
  KEY `idx_ai_emergency_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_error_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_error_registry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `feature` varchar(50) NOT NULL,
  `operation_type` varchar(50) DEFAULT NULL,
  `error_category` varchar(50) NOT NULL,
  `error_message` text DEFAULT NULL,
  `provider_id` int(10) unsigned DEFAULT NULL,
  `model_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `retry_count` int(10) unsigned DEFAULT 0,
  `resolution_status` enum('open','investigating','resolved','ignored') NOT NULL DEFAULT 'open',
  `sanitized_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sanitized_details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_feature` (`feature`),
  KEY `idx_category` (`error_category`),
  KEY `idx_status` (`resolution_status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_event_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_event_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(150) NOT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `record_type` varchar(50) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `event_hash` varchar(64) NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_hash` (`event_hash`),
  KEY `idx_event_name` (`event_name`),
  KEY `idx_processed` (`processed`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_event_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_event_registry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(150) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `payload_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_schema`)),
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_name` (`event_name`),
  KEY `idx_module` (`module`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_event_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_event_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `automation_id` int(10) unsigned NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `filter_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filter_json`)),
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_name`),
  KEY `idx_automation` (`automation_id`),
  CONSTRAINT `ai_event_subscriptions_ibfk_1` FOREIGN KEY (`automation_id`) REFERENCES `ai_automations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_extraction_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_extraction_templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `document_type` enum('unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other') NOT NULL,
  `schema_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`schema_json`)),
  `required_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_fields`)),
  `optional_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`optional_fields`)),
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `version` int(11) NOT NULL DEFAULT 1,
  `status` enum('draft','testing','approved','published','archived') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`template_id`),
  KEY `idx_template_company` (`company_id`),
  KEY `idx_template_type` (`document_type`),
  KEY `idx_template_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_financial_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_financial_thresholds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(100) NOT NULL,
  `min_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(15,2) NOT NULL DEFAULT 999999999.99,
  `required_action` enum('confirm','review') NOT NULL DEFAULT 'confirm',
  `company_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_fin_thresholds_active` (`is_active`),
  KEY `idx_ai_fin_thresholds_action` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_finding_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_finding_evidence` (
  `evidence_id` int(11) NOT NULL AUTO_INCREMENT,
  `finding_id` int(11) NOT NULL,
  `reference_type` enum('erp_record','tool_result','knowledge_document','insight','conversation') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `access_revalidated` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`evidence_id`),
  KEY `idx_fevidence_finding` (`finding_id`),
  CONSTRAINT `ai_finding_evidence_ibfk_1` FOREIGN KEY (`finding_id`) REFERENCES `ai_findings` (`finding_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_findings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_findings` (
  `finding_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `confidence` decimal(3,2) DEFAULT NULL,
  `source_conversation_id` int(11) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_by_ai` tinyint(1) NOT NULL DEFAULT 0,
  `reviewed_by` int(11) DEFAULT NULL,
  `status` enum('draft','reviewed','accepted','dismissed','archived') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`finding_id`),
  KEY `idx_finding_workspace` (`workspace_id`),
  KEY `idx_finding_status` (`status`),
  CONSTRAINT `ai_findings_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_forecasts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_forecasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `metric_key` varchar(100) NOT NULL,
  `forecast_method` enum('moving_average','weighted_moving_average','historical_trend','linear_regression') NOT NULL DEFAULT 'moving_average',
  `forecast_value` decimal(20,4) NOT NULL,
  `forecast_unit` varchar(50) DEFAULT NULL,
  `forecast_currency` varchar(10) DEFAULT NULL,
  `forecast_period_start` date NOT NULL,
  `forecast_period_end` date NOT NULL,
  `historical_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`historical_data`)),
  `historical_range_start` date NOT NULL,
  `historical_range_end` date NOT NULL,
  `confidence_level` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `assumptions` text DEFAULT NULL,
  `limitations` text DEFAULT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `status` enum('active','expired','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_forecast_company` (`company_id`),
  KEY `idx_forecast_metric` (`metric_key`),
  KEY `idx_forecast_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_governance_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_governance_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `actor_type` enum('user','system','ai') NOT NULL DEFAULT 'user',
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `previous_value_json` longtext DEFAULT NULL,
  `new_value_json` longtext DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_gov_audit_action` (`action`),
  KEY `idx_ai_gov_audit_actor` (`actor_id`),
  KEY `idx_ai_gov_audit_target` (`target_type`,`target_id`),
  KEY `idx_ai_gov_audit_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_governance_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_governance_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value_json` longtext DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_ai_gov_config_key` (`config_key`),
  KEY `idx_ai_gov_config_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_health_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_health_checks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component` varchar(100) NOT NULL,
  `component_type` varchar(50) NOT NULL,
  `status` enum('healthy','warning','degraded','unavailable','unknown') NOT NULL DEFAULT 'unknown',
  `message` text DEFAULT NULL,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `last_healthy_at` timestamp NULL DEFAULT NULL,
  `failure_count` int(10) unsigned DEFAULT 0,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_component` (`component`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`component_type`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_incoming_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_incoming_webhook_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `endpoint_token` varchar(120) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `signature_status` enum('verified','failed','missing') NOT NULL DEFAULT 'missing',
  `replay_key` varchar(150) DEFAULT NULL,
  `payload_size_bytes` int(11) NOT NULL DEFAULT 0,
  `status` enum('received','rejected','queued') NOT NULL DEFAULT 'received',
  `payload_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_token` (`endpoint_token`),
  KEY `idx_ai_incoming_webhooks_company` (`company_id`),
  KEY `idx_ai_incoming_webhooks_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_injection_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_injection_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `source` enum('user_input','retrieved_content','tool_output','external_api','document','connector') NOT NULL,
  `injection_type` enum('system_prompt','jailbreak','role_playing','data_exfiltration','command_injection','prompt_injection','unknown') NOT NULL,
  `detection_method` enum('pattern_match','heuristic','ai_detection','rule_based') NOT NULL,
  `matched_pattern` varchar(255) DEFAULT NULL,
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `input_snippet` text DEFAULT NULL,
  `sanitized_input` text DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT 1,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_injection_request` (`request_id`),
  KEY `idx_ai_injection_company` (`company_id`),
  KEY `idx_ai_injection_blocked` (`blocked`),
  KEY `idx_ai_injection_type` (`injection_type`),
  KEY `idx_ai_injection_created` (`created_at`),
  CONSTRAINT `ai_injection_attempts_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `ai_observability_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_insight_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insight_definitions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `definition_key` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `insight_type` enum('rule_based','ai_analyzed','hybrid') NOT NULL DEFAULT 'rule_based',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `required_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_permissions`)),
  `evaluation_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`evaluation_config`)),
  `severity_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`severity_rules`)),
  `target_users_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_users_config`)),
  `navigation_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`navigation_config`)),
  `dedup_strategy` enum('same_day','same_records','same_hash','always_new') NOT NULL DEFAULT 'same_hash',
  `evaluation_frequency` enum('hourly','daily','weekly','on_event') NOT NULL DEFAULT 'hourly',
  `max_records` int(10) unsigned DEFAULT 100,
  `max_runtime_seconds` int(10) unsigned DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `definition_key` (`definition_key`),
  KEY `idx_module` (`module`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_category` (`category`),
  KEY `idx_frequency` (`evaluation_frequency`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_insight_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insight_evaluations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `definition_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('success','partial','failed') NOT NULL,
  `evidence_summary` text DEFAULT NULL,
  `records_evaluated` int(10) unsigned DEFAULT 0,
  `insights_created` int(10) unsigned DEFAULT 0,
  `insights_updated` int(10) unsigned DEFAULT 0,
  `insights_resolved` int(10) unsigned DEFAULT 0,
  `duration_ms` int(10) unsigned DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_definition` (`definition_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_evaluated` (`evaluated_at`),
  CONSTRAINT `ai_insight_evaluations_ibfk_1` FOREIGN KEY (`definition_id`) REFERENCES `ai_insight_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_insight_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insight_feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `insight_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `feedback_type` enum('helpful','not_helpful','dismiss') NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_insight` (`insight_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `ai_insight_feedback_ibfk_1` FOREIGN KEY (`insight_id`) REFERENCES `ai_insight_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_insight_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insight_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `insights_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `info_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notice_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `warning_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `critical_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notification_frequency` enum('immediate','hourly_digest','daily_digest') NOT NULL DEFAULT 'immediate',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_module` (`user_id`,`module`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_insight_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insight_recipients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `insight_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','seen','dismissed','acknowledged') NOT NULL DEFAULT 'pending',
  `seen_at` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_insight_user` (`insight_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ai_insight_recipients_ibfk_1` FOREIGN KEY (`insight_id`) REFERENCES `ai_insight_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_insight_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insight_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `definition_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `summary` text DEFAULT NULL,
  `severity` enum('info','notice','warning','critical') NOT NULL DEFAULT 'info',
  `category` varchar(50) DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`source_ids`)),
  `evidence_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`evidence_json`)),
  `ai_narrative` text DEFAULT NULL,
  `navigation_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`navigation_json`)),
  `dedup_hash` varchar(64) DEFAULT NULL,
  `status` enum('active','seen','dismissed','resolved','expired') NOT NULL DEFAULT 'active',
  `first_detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_detected_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_definition` (`definition_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_severity` (`severity`),
  KEY `idx_dedup` (`dedup_hash`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `ai_insight_records_ibfk_1` FOREIGN KEY (`definition_id`) REFERENCES `ai_insight_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_integration_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_integration_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connector_key` varchar(100) NOT NULL,
  `connection_id` int(11) DEFAULT NULL,
  `capability_key` varchar(150) DEFAULT NULL,
  `actor_type` enum('user','system','workflow','webhook') NOT NULL DEFAULT 'user',
  `actor_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `status` enum('executed','failed','blocked','queued','previewed','confirmed') NOT NULL DEFAULT 'executed',
  `duration_ms` int(11) DEFAULT NULL,
  `safe_input_summary` text DEFAULT NULL,
  `safe_output_summary` text DEFAULT NULL,
  `error_category` varchar(50) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_int_activity_connection` (`connection_id`),
  KEY `idx_ai_int_activity_capability` (`capability_key`),
  KEY `idx_ai_int_activity_status` (`status`),
  KEY `idx_ai_int_activity_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_integration_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_integration_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `connector_key` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `status` enum('draft','connecting','active','expired','failed','disabled','revoked') NOT NULL DEFAULT 'draft',
  `authentication_state` enum('unconfigured','configured','expired','failed','revoked') NOT NULL DEFAULT 'unconfigured',
  `configuration_json` longtext DEFAULT NULL,
  `enabled_capabilities_json` longtext DEFAULT NULL,
  `connection_classification` enum('normal','sensitive','restricted') NOT NULL DEFAULT 'normal',
  `last_health_status` enum('healthy','degraded','failed','expired','disabled') DEFAULT NULL,
  `last_health_check_at` datetime DEFAULT NULL,
  `last_success_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_int_connections_company` (`company_id`),
  KEY `idx_ai_int_connections_connector` (`connector_key`),
  KEY `idx_ai_int_connections_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_integration_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_integration_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) NOT NULL,
  `credential_type` varchar(50) NOT NULL,
  `auth_strategy` varchar(50) NOT NULL,
  `secret_encrypted` longtext DEFAULT NULL,
  `secret_iv` varchar(255) DEFAULT NULL,
  `secondary_secret_encrypted` longtext DEFAULT NULL,
  `secondary_secret_iv` varchar(255) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','rotated','revoked') NOT NULL DEFAULT 'active',
  `rotated_at` datetime DEFAULT NULL,
  `rotated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_int_creds_connection` (`connection_id`),
  KEY `idx_ai_int_creds_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_integration_health`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_integration_health` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) NOT NULL,
  `connector_key` varchar(100) NOT NULL,
  `status` enum('healthy','degraded','failed','expired','disabled') NOT NULL DEFAULT 'healthy',
  `auth_status` enum('valid','expired','failed','revoked','missing') NOT NULL DEFAULT 'missing',
  `api_status` enum('available','degraded','failed','unknown') NOT NULL DEFAULT 'unknown',
  `last_success_at` datetime DEFAULT NULL,
  `last_error_at` datetime DEFAULT NULL,
  `error_rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `rate_limit_events` int(11) NOT NULL DEFAULT 0,
  `consecutive_failures` int(11) NOT NULL DEFAULT 0,
  `circuit_state` enum('healthy','degraded','temporarily_disabled') NOT NULL DEFAULT 'healthy',
  `token_expires_at` datetime DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `connection_id` (`connection_id`),
  KEY `idx_ai_int_health_status` (`status`),
  KEY `idx_ai_int_health_circuit` (`circuit_state`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_integration_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_integration_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `policy_key` varchar(100) NOT NULL,
  `policy_name` varchar(150) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config_json` longtext DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_int_policy` (`company_id`,`policy_key`),
  KEY `idx_ai_int_policy_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=1063 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_key` varchar(120) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `source_module` varchar(100) DEFAULT NULL,
  `source_record_type` varchar(100) DEFAULT NULL,
  `source_record_id` int(11) DEFAULT NULL,
  `detection_type` enum('rule','threshold','trend','anomaly','watchlist','manual') NOT NULL DEFAULT 'rule',
  `detector_key` varchar(100) DEFAULT NULL,
  `severity` enum('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `confidence` decimal(5,4) NOT NULL DEFAULT 0.8000,
  `title` varchar(255) NOT NULL,
  `explanation` text DEFAULT NULL,
  `evidence_json` longtext DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `suggested_action` text DEFAULT NULL,
  `suggested_action_type` varchar(100) DEFAULT NULL,
  `urgency` enum('low','medium','high','immediate') NOT NULL DEFAULT 'medium',
  `status` enum('new','acknowledged','snoozed','action_pending','actioned','dismissed','expired') NOT NULL DEFAULT 'new',
  `assigned_user_id` int(11) DEFAULT NULL,
  `assigned_team` varchar(100) DEFAULT NULL,
  `related_task_id` int(11) DEFAULT NULL,
  `related_task_key` varchar(100) DEFAULT NULL,
  `fingerprint` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `snoozed_until` datetime DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `dismissed_reason` text DEFAULT NULL,
  `actioned_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`),
  KEY `idx_ai_intel_event_status` (`status`),
  KEY `idx_ai_intel_event_severity` (`severity`),
  KEY `idx_ai_intel_event_company` (`company_id`),
  KEY `idx_ai_intel_event_user` (`user_id`),
  KEY `idx_ai_intel_event_detector` (`detector_key`),
  KEY `idx_ai_intel_event_fingerprint` (`fingerprint`),
  KEY `idx_ai_intel_event_created` (`created_at`),
  KEY `idx_ai_intel_event_source` (`source_module`,`source_record_type`,`source_record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_evidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `record_type` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_label` varchar(255) DEFAULT NULL,
  `record_data_json` longtext DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `date_value` date DEFAULT NULL,
  `status_value` varchar(50) DEFAULT NULL,
  `comparison_value` text DEFAULT NULL,
  `classification` enum('public','internal','confidential','sensitive','restricted') NOT NULL DEFAULT 'internal',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_intel_evidence_event` (`event_id`),
  CONSTRAINT `ai_intelligence_evidence_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `ai_intelligence_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_type` enum('useful','not_useful','incorrect','duplicate','already_handled') NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_intel_feedback_event` (`event_id`),
  KEY `idx_ai_intel_feedback_type` (`feedback_type`),
  CONSTRAINT `ai_intelligence_feedback_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `ai_intelligence_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lock_name` varchar(100) NOT NULL,
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_intel_lock_name` (`lock_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_monitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_monitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `monitor_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(100) NOT NULL,
  `data_source` varchar(100) NOT NULL,
  `condition_type` varchar(50) NOT NULL,
  `condition_config_json` longtext NOT NULL,
  `threshold_value` decimal(15,4) DEFAULT NULL,
  `frequency` enum('hourly','daily','weekly','on_change') NOT NULL DEFAULT 'daily',
  `severity` enum('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `notification_target` varchar(100) DEFAULT NULL,
  `recommendation_template` text DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `monitor_key` (`monitor_key`),
  KEY `idx_ai_intel_monitor_key` (`monitor_key`),
  KEY `idx_ai_intel_monitor_enabled` (`enabled`),
  KEY `idx_ai_intel_monitor_freq` (`frequency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `topics_json` longtext DEFAULT NULL,
  `min_severity` enum('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `notification_frequency` enum('realtime','daily','weekly','never') NOT NULL DEFAULT 'daily',
  `briefing_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `briefing_time` time DEFAULT NULL,
  `snooze_hours` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_intel_pref_user_company` (`user_id`,`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(100) NOT NULL,
  `detection_type` enum('rule','threshold','trend','anomaly') NOT NULL DEFAULT 'rule',
  `config_json` longtext NOT NULL,
  `severity_default` enum('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `urgency_default` enum('low','medium','high','immediate') NOT NULL DEFAULT 'medium',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `last_run_status` varchar(50) DEFAULT NULL,
  `last_run_events` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_key` (`rule_key`),
  KEY `idx_ai_intel_rule_key` (`rule_key`),
  KEY `idx_ai_intel_rule_enabled` (`enabled`),
  KEY `idx_ai_intel_rule_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_type` varchar(50) NOT NULL,
  `detector_key` varchar(100) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `status` enum('running','completed','failed','partial') NOT NULL DEFAULT 'running',
  `events_created` int(11) NOT NULL DEFAULT 0,
  `events_updated` int(11) NOT NULL DEFAULT 0,
  `events_deduplicated` int(11) NOT NULL DEFAULT 0,
  `duration_ms` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_intel_run_type` (`run_type`),
  KEY `idx_ai_intel_run_detector` (`detector_key`),
  KEY `idx_ai_intel_run_status` (`status`),
  KEY `idx_ai_intel_run_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intelligence_watchlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intelligence_watchlists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_label` varchar(255) DEFAULT NULL,
  `watch_config_json` longtext DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_checked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_intel_watch_user` (`user_id`),
  KEY `idx_ai_intel_watch_company` (`company_id`),
  KEY `idx_ai_intel_watch_entity` (`entity_type`,`entity_id`),
  KEY `idx_ai_intel_watch_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_kill_switches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_kill_switches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `switch_key` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `disabled_by` int(10) unsigned DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `switch_key` (`switch_key`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=10648 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_chunks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int(10) unsigned NOT NULL,
  `collection_id` int(10) unsigned NOT NULL,
  `chunk_index` int(10) unsigned NOT NULL,
  `content` text NOT NULL,
  `content_hash` varchar(64) NOT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `version_id` int(11) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `section_heading` varchar(500) DEFAULT NULL,
  `page_number` int(11) DEFAULT NULL,
  `embedding_vector` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`embedding_vector`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_content_hash` (`content_hash`),
  CONSTRAINT `ai_knowledge_chunks_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `ai_knowledge_sources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_knowledge_chunks_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `ai_knowledge_collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_citations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_citations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query_id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `chunk_id` int(11) DEFAULT NULL,
  `version_id` int(11) DEFAULT NULL,
  `citation_text` text DEFAULT NULL,
  `section_name` varchar(255) DEFAULT NULL,
  `page_number` int(11) DEFAULT NULL,
  `relevance` decimal(5,4) DEFAULT NULL,
  `used_in_answer` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_query` (`query_id`),
  KEY `idx_source` (`source_id`),
  CONSTRAINT `ai_knowledge_citations_ibfk_1` FOREIGN KEY (`query_id`) REFERENCES `ai_knowledge_queries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_collection_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_collection_access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `collection_id` int(10) unsigned NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_collection_role_user` (`collection_id`,`role`,`user_id`),
  CONSTRAINT `ai_knowledge_collection_access_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `ai_knowledge_collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_collections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `visibility` enum('private','company','restricted') NOT NULL DEFAULT 'company',
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `draft_type` varchar(50) NOT NULL COMMENT 'faq,sop_outline,article,procedure_summary,training_material',
  `title` varchar(500) NOT NULL,
  `content` longtext NOT NULL,
  `source_query` text DEFAULT NULL,
  `source_citations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`source_citations`)),
  `collection_id` int(11) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'draft' COMMENT 'draft,review,approved,published,rejected',
  `is_ai_generated` tinyint(1) DEFAULT 1,
  `is_authoritative` tinyint(1) DEFAULT 0 COMMENT 'Only true after human approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`company_id`,`draft_type`),
  KEY `idx_status` (`status`),
  KEY `idx_collection` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'ingest,search,ask,version_create,version_approve,review,resolution,gap_detected,conflict_detected,freshness_alert,draft_generated,feedback',
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'source,collection,version,query,gap,review',
  `entity_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost_usd` decimal(10,6) DEFAULT 0.000000,
  `latency_ms` int(11) DEFAULT 0,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`company_id`,`event_type`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_freshness_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_freshness_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `rule_name` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL COMMENT 'policy,sop,procedure,manual,contract,general',
  `max_age_days` int(11) DEFAULT NULL COMMENT 'Maximum age before considered stale',
  `review_interval_days` int(11) DEFAULT NULL COMMENT 'How often to review',
  `auto_flag` tinyint(1) DEFAULT 1,
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`company_id`,`document_type`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_gaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_gaps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `topic` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'unanswered queries, low-confidence results' CHECK (json_valid(`evidence`)),
  `affected_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`affected_users`)),
  `affected_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`affected_departments`)),
  `frequency` int(11) DEFAULT 1,
  `recommendation` text DEFAULT NULL,
  `suggested_owner` int(11) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'detected' COMMENT 'detected,acknowledged,in_progress,resolved,dismissed',
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_frequency` (`frequency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(10) unsigned DEFAULT NULL,
  `collection_id` int(10) unsigned DEFAULT NULL,
  `permission_type` varchar(30) NOT NULL,
  `permission_value` varchar(255) NOT NULL,
  `access_level` varchar(20) DEFAULT 'read',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_permission` (`permission_type`,`permission_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_queries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_queries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `query_text` text NOT NULL,
  `query_type` varchar(30) DEFAULT 'search' COMMENT 'search,ask,copilot,record_level,process_check',
  `context_type` varchar(50) DEFAULT NULL COMMENT 'customer,invoice,po,process,manual',
  `context_id` int(11) DEFAULT NULL,
  `results_count` int(11) DEFAULT 0,
  `top_relevance` decimal(5,4) DEFAULT NULL,
  `answer_provided` tinyint(1) DEFAULT 0,
  `answer_confidence` decimal(5,4) DEFAULT NULL,
  `citations_count` int(11) DEFAULT 0,
  `source_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`source_ids`)),
  `collection_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`collection_ids`)),
  `provider` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost_usd` decimal(10,6) DEFAULT 0.000000,
  `latency_ms` int(11) DEFAULT 0,
  `no_answer_reason` varchar(255) DEFAULT NULL,
  `feedback_rating` tinyint(4) DEFAULT NULL COMMENT '1=helpful,0=neutral,-1=not helpful',
  `feedback_text` text DEFAULT NULL,
  `feedback_type` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_user` (`company_id`,`user_id`),
  KEY `idx_query_type` (`query_type`),
  KEY `idx_feedback` (`feedback_rating`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_retrieval_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_retrieval_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `conversation_id` int(10) unsigned DEFAULT NULL,
  `collection_id` int(10) unsigned DEFAULT NULL,
  `source_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`source_ids_json`)),
  `query_summary` varchar(255) DEFAULT NULL,
  `chunks_used` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_collection` (`collection_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `source_id` int(11) DEFAULT NULL,
  `version_id` int(11) DEFAULT NULL,
  `collection_id` int(11) DEFAULT NULL,
  `review_type` varchar(50) NOT NULL COMMENT 'expired,conflicting,low_quality,low_retrieval,unapproved,outdated,incorrect_answer,gap',
  `severity` varchar(20) DEFAULT 'normal' COMMENT 'low,normal,high,urgent',
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `status` varchar(30) DEFAULT 'pending' COMMENT 'pending,in_review,resolved,dismissed',
  `assigned_to` int(11) DEFAULT NULL,
  `resolution` varchar(50) DEFAULT NULL COMMENT 'approved,rejected,replaced,archived,authoritative,owner_assigned',
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_review_type` (`review_type`),
  KEY `idx_source` (`source_id`),
  KEY `idx_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `collection_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `source_type` enum('file','text') NOT NULL DEFAULT 'text',
  `original_filename` varchar(255) DEFAULT NULL,
  `storage_path` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `status` enum('uploaded','extracting','chunking','embedding','indexed','failed') NOT NULL DEFAULT 'uploaded',
  `classification` varchar(30) DEFAULT 'internal',
  `effective_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `review_due_date` date DEFAULT NULL,
  `last_reviewed_at` datetime DEFAULT NULL,
  `source_authority` varchar(100) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `extraction_status` varchar(255) DEFAULT NULL,
  `chunk_count` int(10) unsigned DEFAULT 0,
  `current_version_id` int(11) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ai_knowledge_sources_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `ai_knowledge_collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(10) unsigned NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `content` longtext NOT NULL,
  `content_hash` varchar(64) NOT NULL,
  `effective_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `superseded_by` int(11) DEFAULT NULL,
  `approval_status` varchar(30) DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `change_summary` text DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_source_version` (`source_id`,`version_number`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_expiration_date` (`expiration_date`),
  KEY `idx_approval_status` (`approval_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_loop_detection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_loop_detection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_key` varchar(120) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `tool_name` varchar(120) DEFAULT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `call_count` int(11) NOT NULL DEFAULT 0,
  `consecutive_failures` int(11) NOT NULL DEFAULT 0,
  `last_call_at` datetime NOT NULL,
  `first_call_at` datetime NOT NULL,
  `pattern_type` enum('repeated_call','cyclic_execution','failure_spiral','no_progress','stuck') DEFAULT NULL,
  `detection_method` enum('identical_calls','similar_calls','failure_rate','time_based','entropy') DEFAULT NULL,
  `threshold_exceeded` tinyint(1) NOT NULL DEFAULT 0,
  `paused` tinyint(1) NOT NULL DEFAULT 0,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_loop_session` (`session_key`),
  KEY `idx_ai_loop_company` (`company_id`),
  KEY `idx_ai_loop_pattern` (`pattern_type`),
  KEY `idx_ai_loop_threshold` (`threshold_exceeded`),
  KEY `idx_ai_loop_paused` (`paused`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_loop_detection_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_loop_detection_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) DEFAULT NULL,
  `loop_type` varchar(100) NOT NULL,
  `loop_count` int(11) NOT NULL DEFAULT 0,
  `detected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `context_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_loop_events_conv` (`conversation_id`),
  KEY `idx_ai_loop_events_type` (`loop_type`),
  KEY `idx_ai_loop_events_date` (`detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_masking_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_masking_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `field_pattern` varchar(255) NOT NULL,
  `masking_type` enum('none','partial','full','exclude') NOT NULL DEFAULT 'none',
  `module` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_masking_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_masking_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(100) NOT NULL,
  `field_pattern` varchar(255) NOT NULL,
  `field_type` enum('email','phone','bank','tax_id','personal','credential','api_key','token','password','ssn','ip_address','custom') NOT NULL,
  `masking_type` enum('full','partial','hash','redact','replace') NOT NULL DEFAULT 'full',
  `mask_character` varchar(10) NOT NULL DEFAULT '*',
  `visible_prefix` int(11) NOT NULL DEFAULT 0,
  `visible_suffix` int(11) NOT NULL DEFAULT 0,
  `replacement_text` varchar(50) DEFAULT '[REDACTED]',
  `apply_to_contexts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '["ai_provider","external_connector","logs","observability","analytics"]' CHECK (json_valid(`apply_to_contexts`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_key` (`rule_key`),
  KEY `idx_ai_mask_field_type` (`field_type`),
  KEY `idx_ai_mask_active` (`is_active`),
  KEY `idx_ai_mask_key` (`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `role` varchar(20) NOT NULL COMMENT 'system|user|assistant',
  `content` text NOT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `input_tokens` int(10) unsigned DEFAULT NULL,
  `output_tokens` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_msg_conv` (`conversation_id`),
  KEY `idx_ai_msg_created` (`created_at`),
  CONSTRAINT `fk_ai_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=360 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_metric_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_metric_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('finance','procurement','inventory','sales','hr','operational','custom') NOT NULL DEFAULT 'finance',
  `source_table` varchar(100) NOT NULL,
  `source_column` varchar(100) NOT NULL,
  `calculation_type` enum('sum','count','avg','min','max','ratio','percentage','custom') NOT NULL DEFAULT 'sum',
  `calculation_sql` text DEFAULT NULL,
  `allowed_dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`allowed_dimensions`)),
  `allowed_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_filters`)),
  `data_classification` enum('public','internal','confidential','restricted') NOT NULL DEFAULT 'internal',
  `currency_dependent` tinyint(1) NOT NULL DEFAULT 0,
  `base_currency_column` varchar(100) DEFAULT NULL,
  `aggregation_behavior` enum('sum','average','latest','weighted') NOT NULL DEFAULT 'sum',
  `permissions_required` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_metric_key` (`metric_key`),
  KEY `idx_metric_category` (`category`),
  KEY `idx_metric_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_models` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` int(10) unsigned NOT NULL,
  `model_id` varchar(150) NOT NULL COMMENT 'Provider''s model identifier (e.g. gpt-4o)',
  `display_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `model_type` varchar(50) DEFAULT 'chat' COMMENT 'Primary type: chat, reasoning, embedding, vision, multimodal',
  `embedding_dimensions` int(10) unsigned DEFAULT NULL,
  `context_window` int(10) unsigned DEFAULT NULL COMMENT 'Max context tokens',
  `max_output_tokens` int(10) unsigned DEFAULT NULL COMMENT 'Max output tokens',
  `supports_chat` tinyint(1) NOT NULL DEFAULT 1,
  `supports_tool_calling` tinyint(1) NOT NULL DEFAULT 0,
  `supports_vision` tinyint(1) NOT NULL DEFAULT 0,
  `supports_embeddings` tinyint(1) NOT NULL DEFAULT 0,
  `supports_structured_output` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `configuration_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_models_provider_model` (`provider_id`,`model_id`),
  KEY `idx_ai_models_provider` (`provider_id`),
  KEY `idx_ai_models_active` (`is_active`),
  KEY `idx_ai_models_default` (`is_default`),
  KEY `idx_ai_models_type` (`model_type`),
  CONSTRAINT `fk_ai_models_provider` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_benchmark_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_benchmark_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `benchmark_id` int(11) NOT NULL,
  `evaluation_run_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `result_date` date NOT NULL,
  `score` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `latency_ms` int(11) NOT NULL DEFAULT 0,
  `cost_estimate` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `summary_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_obs_benchmark_results_benchmark` (`benchmark_id`),
  KEY `idx_ai_obs_benchmark_results_date` (`result_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_benchmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_benchmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `benchmark_key` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `category` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'draft',
  `tags_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `benchmark_key` (`benchmark_key`),
  KEY `idx_ai_obs_benchmarks_category` (`category`),
  KEY `idx_ai_obs_benchmarks_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1489 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_daily_aggregates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_daily_aggregates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aggregate_date` date NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `feature` varchar(80) NOT NULL DEFAULT 'ai_chat',
  `tool_name` varchar(120) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'success',
  `request_count` int(11) NOT NULL DEFAULT 0,
  `success_count` int(11) NOT NULL DEFAULT 0,
  `failure_count` int(11) NOT NULL DEFAULT 0,
  `blocked_count` int(11) NOT NULL DEFAULT 0,
  `cancelled_count` int(11) NOT NULL DEFAULT 0,
  `avg_latency_ms` decimal(12,2) NOT NULL DEFAULT 0.00,
  `p50_latency_ms` decimal(12,2) NOT NULL DEFAULT 0.00,
  `p95_latency_ms` decimal(12,2) NOT NULL DEFAULT 0.00,
  `p99_latency_ms` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_input_tokens` bigint(20) NOT NULL DEFAULT 0,
  `total_output_tokens` bigint(20) NOT NULL DEFAULT 0,
  `total_cost` decimal(14,6) NOT NULL DEFAULT 0.000000,
  `quality_score_avg` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `feedback_total` int(11) NOT NULL DEFAULT 0,
  `negative_feedback_total` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ai_obs_daily_agg` (`aggregate_date`,`company_id`,`provider_id`,`model_id`,`feature`,`tool_name`,`status`),
  KEY `idx_ai_obs_daily_agg_date` (`aggregate_date`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_eval_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_eval_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_id` int(11) NOT NULL,
  `case_key` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'response_quality',
  `input_text` mediumtext NOT NULL,
  `expected_output_criteria` text DEFAULT NULL,
  `expected_tool` varchar(120) DEFAULT NULL,
  `expected_intent` varchar(120) DEFAULT NULL,
  `expected_schema_json` longtext DEFAULT NULL,
  `expected_response_contains_json` longtext DEFAULT NULL,
  `safety_requirements_json` longtext DEFAULT NULL,
  `tags_json` longtext DEFAULT NULL,
  `version_label` varchar(40) NOT NULL DEFAULT '1.0',
  `status` enum('draft','active','deprecated') NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_key` (`case_key`),
  KEY `idx_ai_obs_eval_cases_dataset` (`dataset_id`),
  KEY `idx_ai_obs_eval_cases_status` (`status`),
  KEY `idx_ai_obs_eval_cases_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_eval_datasets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_eval_datasets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_key` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `evaluation_type` varchar(80) NOT NULL DEFAULT 'response_quality',
  `version_no` int(11) NOT NULL DEFAULT 1,
  `status` enum('draft','active','deprecated') NOT NULL DEFAULT 'draft',
  `tags_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dataset_key` (`dataset_key`),
  KEY `idx_ai_obs_eval_dataset_status` (`status`),
  KEY `idx_ai_obs_eval_dataset_type` (`evaluation_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_eval_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_eval_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `status` enum('success','partial_success','failed','blocked','cancelled','timeout') NOT NULL DEFAULT 'success',
  `actual_intent` varchar(120) DEFAULT NULL,
  `actual_tool` varchar(120) DEFAULT NULL,
  `actual_response_summary` text DEFAULT NULL,
  `schema_valid` tinyint(1) NOT NULL DEFAULT 0,
  `grounded_status` enum('grounded','partially_grounded','ungrounded') NOT NULL DEFAULT 'partially_grounded',
  `safety_status` enum('passed','warning','failed') NOT NULL DEFAULT 'passed',
  `score` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `scoring_breakdown_json` longtext DEFAULT NULL,
  `latency_ms` int(11) NOT NULL DEFAULT 0,
  `estimated_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `trace_id` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_obs_eval_results_run` (`run_id`),
  KEY `idx_ai_obs_eval_results_case` (`case_id`),
  KEY `idx_ai_obs_eval_results_score` (`score`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_eval_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_eval_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_key` varchar(120) NOT NULL,
  `dataset_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `started_by` int(11) DEFAULT NULL,
  `run_mode` enum('read_only','dry_run','mock_tools','test_company') NOT NULL DEFAULT 'read_only',
  `status` enum('queued','running','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  `total_cases` int(11) NOT NULL DEFAULT 0,
  `completed_cases` int(11) NOT NULL DEFAULT 0,
  `average_score` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `average_latency_ms` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estimated_total_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `summary_json` longtext DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `run_key` (`run_key`),
  KEY `idx_ai_obs_eval_runs_dataset` (`dataset_id`),
  KEY `idx_ai_obs_eval_runs_status` (`status`),
  KEY `idx_ai_obs_eval_runs_provider_model` (`provider_id`,`model_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_experiments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_experiments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `experiment_key` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `experiment_type` varchar(80) NOT NULL DEFAULT 'model_comparison',
  `status` enum('draft','testing','active','archived') NOT NULL DEFAULT 'draft',
  `variant_a_json` longtext DEFAULT NULL,
  `variant_b_json` longtext DEFAULT NULL,
  `metrics_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `experiment_key` (`experiment_key`),
  KEY `idx_ai_obs_experiments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `feedback_type` enum('helpful','not_helpful','report_problem') NOT NULL,
  `reason_code` enum('incorrect','incomplete','unsafe','slow','irrelevant','other') DEFAULT NULL,
  `category` enum('quality','safety','performance','provider','tool','knowledge','other') DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('new','reviewing','resolved','dismissed') NOT NULL DEFAULT 'new',
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_obs_feedback_request` (`request_id`),
  KEY `idx_ai_obs_feedback_company` (`company_id`),
  KEY `idx_ai_obs_feedback_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_feedback_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_feedback_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `reviewer_user_id` int(11) NOT NULL,
  `review_status` enum('queued','categorized','resolved') NOT NULL DEFAULT 'queued',
  `reviewer_notes` text DEFAULT NULL,
  `linked_eval_case_id` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_obs_feedback_reviews_feedback` (`feedback_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_findings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_findings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `finding_key` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `finding_type` varchar(80) NOT NULL DEFAULT 'anomaly',
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'warning',
  `affected_type` varchar(80) DEFAULT NULL,
  `affected_reference` varchar(120) DEFAULT NULL,
  `status` enum('open','acknowledged','investigating','resolved','ignored') NOT NULL DEFAULT 'open',
  `dedupe_key` varchar(180) DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `recommendation_text` text DEFAULT NULL,
  `first_detected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_detected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cooldown_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `finding_key` (`finding_key`),
  KEY `idx_ai_obs_findings_status` (`status`),
  KEY `idx_ai_obs_findings_severity` (`severity`),
  KEY `idx_ai_obs_findings_dedupe` (`dedupe_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_key` varchar(120) NOT NULL,
  `report_type` varchar(80) NOT NULL,
  `title` varchar(180) NOT NULL,
  `scope_company_id` int(11) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `report_date` date NOT NULL,
  `summary_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_key` (`report_key`),
  KEY `idx_ai_obs_reports_type` (`report_type`),
  KEY `idx_ai_obs_reports_date` (`report_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_key` varchar(80) NOT NULL,
  `trace_id` varchar(80) NOT NULL,
  `company_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `command_conversation_id` int(11) DEFAULT NULL,
  `session_reference` varchar(120) DEFAULT NULL,
  `entry_point` varchar(80) NOT NULL DEFAULT 'assistant',
  `provider_id` int(11) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `feature` varchar(80) NOT NULL DEFAULT 'ai_chat',
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `queue_wait_ms` int(11) NOT NULL DEFAULT 0,
  `status` enum('success','partial_success','failed','blocked','cancelled','timeout') NOT NULL DEFAULT 'success',
  `error_category` enum('provider_error','timeout','rate_limit','validation_error','permission_denied','policy_blocked','tool_failure','context_failure','unknown') DEFAULT NULL,
  `input_tokens` int(11) NOT NULL DEFAULT 0,
  `output_tokens` int(11) NOT NULL DEFAULT 0,
  `total_tokens` int(11) NOT NULL DEFAULT 0,
  `estimated_input_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_output_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_tool_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_ocr_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_stt_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_tts_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_embedding_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `estimated_total_cost` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `content_mode` enum('metadata_only','masked_content','full_content') NOT NULL DEFAULT 'metadata_only',
  `sampled_content` tinyint(1) NOT NULL DEFAULT 0,
  `prompt_summary` mediumtext DEFAULT NULL,
  `response_summary` mediumtext DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_key` (`request_key`),
  KEY `idx_ai_obs_req_company` (`company_id`),
  KEY `idx_ai_obs_req_user` (`user_id`),
  KEY `idx_ai_obs_req_feature` (`feature`),
  KEY `idx_ai_obs_req_provider` (`provider_id`),
  KEY `idx_ai_obs_req_model` (`model_id`),
  KEY `idx_ai_obs_req_status` (`status`),
  KEY `idx_ai_obs_req_started` (`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_observability_spans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_observability_spans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `trace_id` varchar(80) NOT NULL,
  `span_key` varchar(80) NOT NULL,
  `parent_span_id` int(11) DEFAULT NULL,
  `span_type` varchar(80) NOT NULL,
  `span_name` varchar(120) NOT NULL,
  `feature` varchar(80) DEFAULT NULL,
  `tool_name` varchar(120) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `status` enum('success','partial_success','failed','blocked','cancelled','timeout') NOT NULL DEFAULT 'success',
  `error_category` enum('provider_error','timeout','rate_limit','validation_error','permission_denied','policy_blocked','tool_failure','context_failure','unknown') DEFAULT NULL,
  `safe_input_summary` mediumtext DEFAULT NULL,
  `safe_output_summary` mediumtext DEFAULT NULL,
  `safe_metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `span_key` (`span_key`),
  KEY `idx_ai_obs_spans_request` (`request_id`),
  KEY `idx_ai_obs_spans_trace` (`trace_id`),
  KEY `idx_ai_obs_spans_type` (`span_type`),
  KEY `idx_ai_obs_spans_tool` (`tool_name`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_parameter_schemas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_parameter_schemas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_name` varchar(100) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `tool_name` varchar(120) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `schema_json` longtext NOT NULL,
  `validation_rules_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_param_schema_action` (`action_type`),
  KEY `idx_ai_param_schema_tool` (`tool_name`),
  KEY `idx_ai_param_schema_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_pending_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_pending_actions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `conversation_id` int(10) unsigned DEFAULT NULL,
  `action_id` varchar(100) NOT NULL,
  `action_input_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`action_input_json`)),
  `action_preview_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_preview_json`)),
  `status` enum('pending','confirmed','executing','completed','cancelled','expired','failed') NOT NULL DEFAULT 'pending',
  `confirmation_token` varchar(64) NOT NULL,
  `confirmation_token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`execution_result_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_token_hash` (`confirmation_token_hash`),
  KEY `idx_expires` (`expires_at`,`status`),
  KEY `idx_conversation` (`conversation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('ai_usage','data_access','data_transmission','tool_usage','action_execution','external_provider','document','voice','integration','automation','knowledge','logging','retention') NOT NULL,
  `scope` enum('global','company','department','role','user','feature','tool','provider','document_type','data_classification','integration') NOT NULL,
  `scope_id` int(11) DEFAULT NULL,
  `conditions_json` longtext DEFAULT NULL,
  `effect` enum('allow','deny','require_confirmation','require_review','mask_data','restrict_provider','log_only') NOT NULL DEFAULT 'allow',
  `priority` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','draft','archived') NOT NULL DEFAULT 'draft',
  `version` int(11) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `policy_key` (`policy_key`),
  KEY `idx_ai_policies_type` (`type`),
  KEY `idx_ai_policies_scope` (`scope`),
  KEY `idx_ai_policies_status` (`status`),
  KEY `idx_ai_policies_priority` (`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_policy_simulations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_policy_simulations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `input_json` longtext DEFAULT NULL,
  `result_json` longtext DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_policy_sim_user` (`user_id`),
  KEY `idx_ai_policy_sim_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_policy_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_policy_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `change_reason` text DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `previous_version` int(11) DEFAULT NULL,
  `conditions_json` longtext DEFAULT NULL,
  `effect` enum('allow','deny','require_confirmation','require_review','mask_data','restrict_provider','log_only') NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','draft','archived') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_policy_versions_policy` (`policy_id`),
  KEY `idx_ai_policy_versions_version` (`version`),
  CONSTRAINT `ai_policy_versions_ibfk_1` FOREIGN KEY (`policy_id`) REFERENCES `ai_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_alerts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `alert_type` varchar(80) NOT NULL,
  `threshold_config_json` longtext DEFAULT NULL,
  `schedule_json` longtext DEFAULT NULL,
  `last_evaluated_at` datetime DEFAULT NULL,
  `last_triggered_at` datetime DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_process_alert_process` (`company_id`,`process_key`,`status`),
  KEY `idx_ai_process_alert_type` (`company_id`,`alert_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `source_modules` longtext DEFAULT NULL,
  `entity_type` varchar(100) NOT NULL,
  `start_event` varchar(120) NOT NULL,
  `completion_event` varchar(120) DEFAULT NULL,
  `expected_stages` longtext DEFAULT NULL,
  `sla_target_hours` decimal(12,2) DEFAULT NULL,
  `business_dimensions` longtext DEFAULT NULL,
  `visibility` varchar(50) NOT NULL DEFAULT 'company',
  `permissions_required` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_definition` (`company_id`,`process_key`),
  KEY `idx_ai_process_def_active` (`company_id`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=473 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `process_instance_id` varchar(190) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `event_name` varchar(120) NOT NULL,
  `event_type` varchar(80) NOT NULL DEFAULT 'status_change',
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `branch` varchar(120) DEFAULT NULL,
  `role_name` varchar(120) DEFAULT NULL,
  `event_time` datetime NOT NULL,
  `status` varchar(80) DEFAULT NULL,
  `source_module` varchar(80) NOT NULL,
  `source_table` varchar(120) NOT NULL,
  `source_row_id` int(11) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `amount_value` decimal(20,4) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_event` (`company_id`,`process_key`,`process_instance_id`,`event_name`,`event_time`,`source_table`,`source_row_id`),
  KEY `idx_ai_process_event_instance` (`company_id`,`process_key`,`process_instance_id`),
  KEY `idx_ai_process_event_time` (`company_id`,`event_time`),
  KEY `idx_ai_process_event_entity` (`entity_type`,`entity_id`),
  KEY `idx_ai_process_event_source` (`source_table`,`source_row_id`)
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_findings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_findings` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `finding_type` varchar(80) NOT NULL,
  `severity` varchar(20) NOT NULL DEFAULT 'medium',
  `title` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `stage_name` varchar(120) DEFAULT NULL,
  `transition_name` varchar(190) DEFAULT NULL,
  `evidence_json` longtext DEFAULT NULL,
  `metric_snapshot_json` longtext DEFAULT NULL,
  `recommendation_text` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `fingerprint` varchar(255) NOT NULL,
  `detected_at` datetime NOT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_finding` (`company_id`,`fingerprint`),
  KEY `idx_ai_process_finding_process` (`company_id`,`process_key`,`status`),
  KEY `idx_ai_process_finding_detected` (`company_id`,`detected_at`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_metrics` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `metric_date` date NOT NULL,
  `dimension_key` varchar(80) NOT NULL DEFAULT 'all',
  `dimension_value` varchar(190) NOT NULL DEFAULT 'all',
  `instance_count` int(11) NOT NULL DEFAULT 0,
  `completed_count` int(11) NOT NULL DEFAULT 0,
  `active_count` int(11) NOT NULL DEFAULT 0,
  `abandoned_count` int(11) NOT NULL DEFAULT 0,
  `breach_count` int(11) NOT NULL DEFAULT 0,
  `avg_cycle_hours` decimal(12,2) DEFAULT NULL,
  `median_cycle_hours` decimal(12,2) DEFAULT NULL,
  `p90_cycle_hours` decimal(12,2) DEFAULT NULL,
  `avg_approval_hours` decimal(12,2) DEFAULT NULL,
  `avg_rework_hours` decimal(12,2) DEFAULT NULL,
  `avg_queue_hours` decimal(12,2) DEFAULT NULL,
  `sample_size` int(11) NOT NULL DEFAULT 0,
  `metric_payload_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_metric` (`company_id`,`process_key`,`metric_date`,`dimension_key`,`dimension_value`),
  KEY `idx_ai_process_metric_date` (`company_id`,`metric_date`),
  KEY `idx_ai_process_metric_process` (`company_id`,`process_key`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_proposals` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `recommendation_id` bigint(20) DEFAULT NULL,
  `workflow_id` int(11) DEFAULT NULL,
  `workflow_version_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `proposal_payload_json` longtext DEFAULT NULL,
  `simulation_result_json` longtext DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'draft',
  `governance_status` varchar(40) NOT NULL DEFAULT 'pending_review',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_process_proposal_process` (`company_id`,`process_key`,`approval_status`),
  KEY `idx_ai_process_proposal_workflow` (`workflow_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_recommendations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `finding_id` bigint(20) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `recommendation_text` text NOT NULL,
  `expected_impact` varchar(255) DEFAULT NULL,
  `rationale` text DEFAULT NULL,
  `evidence_json` longtext DEFAULT NULL,
  `advisory_only` tinyint(1) NOT NULL DEFAULT 1,
  `priority_level` varchar(20) NOT NULL DEFAULT 'medium',
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `fingerprint` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_recommendation` (`company_id`,`fingerprint`),
  KEY `idx_ai_process_recommendation_process` (`company_id`,`process_key`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_runs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `process_instance_id` varchar(190) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `branch` varchar(120) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `process_version` varchar(80) DEFAULT NULL,
  `run_status` varchar(80) NOT NULL DEFAULT 'active',
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cycle_time_hours` decimal(12,2) DEFAULT NULL,
  `waiting_time_hours` decimal(12,2) DEFAULT NULL,
  `active_time_hours` decimal(12,2) DEFAULT NULL,
  `approval_time_hours` decimal(12,2) DEFAULT NULL,
  `queue_time_hours` decimal(12,2) DEFAULT NULL,
  `handoff_time_hours` decimal(12,2) DEFAULT NULL,
  `rework_time_hours` decimal(12,2) DEFAULT NULL,
  `handoff_count` int(11) NOT NULL DEFAULT 0,
  `rework_count` int(11) NOT NULL DEFAULT 0,
  `sla_target_hours` decimal(12,2) DEFAULT NULL,
  `sla_status` varchar(40) NOT NULL DEFAULT 'unknown',
  `breach_hours` decimal(12,2) DEFAULT NULL,
  `event_count` int(11) NOT NULL DEFAULT 0,
  `last_event_name` varchar(120) DEFAULT NULL,
  `last_event_at` datetime DEFAULT NULL,
  `dimensions_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_run` (`company_id`,`process_key`,`process_instance_id`),
  KEY `idx_ai_process_run_status` (`company_id`,`process_key`,`run_status`),
  KEY `idx_ai_process_run_started` (`company_id`,`started_at`),
  KEY `idx_ai_process_run_department` (`company_id`,`department_id`),
  KEY `idx_ai_process_run_branch` (`company_id`,`branch`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_simulations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_simulations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `name` varchar(200) NOT NULL,
  `variables_json` longtext NOT NULL,
  `baseline_snapshot_json` longtext NOT NULL,
  `simulation_result_json` longtext NOT NULL,
  `safety_label` varchar(200) NOT NULL DEFAULT 'Simulation / Estimate — not actual ERP results.',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_process_simulation_process` (`company_id`,`process_key`),
  KEY `idx_ai_process_simulation_created` (`company_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_process_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_process_versions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `process_key` varchar(120) NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `definition_snapshot_json` longtext NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_process_version` (`company_id`,`process_key`,`version_number`),
  KEY `idx_ai_process_version_process` (`company_id`,`process_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_prompt_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_prompt_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prompt_key` varchar(120) NOT NULL,
  `version_no` int(11) NOT NULL,
  `feature` varchar(80) NOT NULL,
  `status` enum('draft','testing','active','archived') NOT NULL DEFAULT 'draft',
  `change_summary` text DEFAULT NULL,
  `prompt_text` mediumtext NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `activated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `activated_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ai_prompt_versions` (`prompt_key`,`version_no`),
  KEY `idx_ai_prompt_versions_feature` (`feature`),
  KEY `idx_ai_prompt_versions_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_provider_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_provider_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_for_classification` enum('public','internal','confidential','sensitive','restricted') DEFAULT NULL,
  `allowed_features_json` longtext DEFAULT NULL,
  `max_cost_per_day` decimal(12,2) DEFAULT NULL,
  `max_tokens_per_request` int(11) DEFAULT NULL,
  `rate_limit_per_minute` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_provider_rules_provider` (`provider_id`),
  KEY `idx_ai_provider_rules_company` (`company_id`),
  KEY `idx_ai_provider_rules_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_providers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `provider_type` varchar(50) NOT NULL DEFAULT 'openai',
  `base_url` varchar(500) DEFAULT NULL,
  `api_key_encrypted` varbinary(1024) DEFAULT NULL,
  `api_key_iv` varbinary(128) DEFAULT NULL,
  `configuration_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `last_tested_at` timestamp NULL DEFAULT NULL,
  `last_test_status` varchar(20) DEFAULT NULL COMMENT 'success|failed|pending',
  `last_connection_status` varchar(20) DEFAULT 'never_tested' COMMENT 'never_tested|success|failed',
  `last_connection_at` timestamp NULL DEFAULT NULL,
  `last_connection_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_providers_slug` (`slug`),
  KEY `idx_ai_providers_type` (`provider_type`),
  KEY `idx_ai_providers_active` (`is_active`),
  KEY `idx_ai_providers_default` (`is_default`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_rate_limit_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_rate_limit_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope_type` enum('user','company','provider','model','feature','tool','integration') NOT NULL,
  `scope_id` int(11) DEFAULT NULL,
  `requests_per_minute` int(11) NOT NULL DEFAULT 30,
  `requests_per_hour` int(11) NOT NULL DEFAULT 500,
  `requests_per_day` int(11) NOT NULL DEFAULT 5000,
  `concurrent_requests` int(11) NOT NULL DEFAULT 5,
  `burst_size` int(11) NOT NULL DEFAULT 10,
  `cooldown_seconds` int(11) NOT NULL DEFAULT 60,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_rlc_scope` (`scope_type`,`scope_id`),
  KEY `idx_ai_rlc_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_rate_limit_counters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_rate_limit_counters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `limit_key` varchar(100) NOT NULL,
  `scope` varchar(50) NOT NULL DEFAULT 'user',
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `company_id` int(10) unsigned NOT NULL DEFAULT 0,
  `feature` varchar(50) NOT NULL DEFAULT '*',
  `window_start` datetime NOT NULL,
  `window_end` datetime NOT NULL,
  `request_count` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_window` (`limit_key`,`scope`,`user_id`,`company_id`,`feature`,`window_start`),
  KEY `idx_window_end` (`window_end`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `limit_key` varchar(100) NOT NULL,
  `limit_type` enum('per_minute','per_hour','per_day') NOT NULL,
  `max_requests` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `feature` varchar(50) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_limit` (`limit_key`),
  KEY `idx_user` (`user_id`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1471 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_research_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_research_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `author_user_id` int(11) NOT NULL,
  `note_type` enum('question','finding','note','summary') NOT NULL DEFAULT 'note',
  `content` text NOT NULL,
  `finding_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `idx_rnote_session` (`session_id`),
  CONSTRAINT `ai_research_notes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `ai_research_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_research_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_research_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `objective` text DEFAULT NULL,
  `status` enum('active','completed','archived') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_rsession_workspace` (`workspace_id`),
  CONSTRAINT `ai_research_sessions_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_retention_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data_type` varchar(100) NOT NULL,
  `retention_days` int(10) unsigned NOT NULL DEFAULT 90,
  `last_cleanup_at` timestamp NULL DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_type` (`data_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1723 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_review_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_review_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_key` varchar(100) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `item_type` enum('action','automation','integration','document','workflow','other') NOT NULL,
  `item_data_json` longtext DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','approved','rejected','escalated') NOT NULL DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_key` (`item_key`),
  KEY `idx_ai_review_queue_status` (`status`),
  KEY `idx_ai_review_queue_risk` (`risk_level`),
  KEY `idx_ai_review_queue_priority` (`priority`),
  KEY `idx_ai_review_queue_company` (`company_id`),
  KEY `idx_ai_review_queue_created` (`created_at`),
  KEY `idx_ai_review_queue_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_risk_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_risk_classifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `level` enum('low','medium','high','critical') NOT NULL,
  `category` enum('action','data','operation','integration','automation') NOT NULL,
  `default_risk` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `auto_detect` tinyint(1) NOT NULL DEFAULT 1,
  `conditions_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_key` (`rule_key`),
  KEY `idx_ai_risk_level` (`level`),
  KEY `idx_ai_risk_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_risk_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_risk_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(150) NOT NULL,
  `rule_key` varchar(100) NOT NULL,
  `classification_id` int(11) DEFAULT NULL,
  `match_type` enum('keyword','pattern','amount','count','field_value') NOT NULL,
  `match_value` varchar(255) DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_key` (`rule_key`),
  KEY `idx_ai_risk_rules_active` (`is_active`),
  KEY `idx_ai_risk_rules_level` (`risk_level`),
  KEY `idx_ai_risk_rules_key` (`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_safety_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_safety_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_key` varchar(100) NOT NULL,
  `policy_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `max_value` int(10) unsigned NOT NULL,
  `current_value` int(10) unsigned DEFAULT 0,
  `scope` enum('system','company','feature') NOT NULL DEFAULT 'system',
  `target_id` int(10) unsigned DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `policy_key` (`policy_key`),
  KEY `idx_scope` (`scope`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=2203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_saved_analyses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_saved_analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `metric_key` varchar(100) NOT NULL,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `comparison_type` varchar(50) DEFAULT NULL,
  `forecast_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`forecast_settings`)),
  `scenario_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scenario_settings`)),
  `visibility` enum('private','team','company') NOT NULL DEFAULT 'private',
  `refresh_schedule` text DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_saved_analysis_company` (`company_id`),
  KEY `idx_saved_analysis_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_scenario_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_scenario_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variable_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `data_type` enum('percentage','number','currency','days') NOT NULL DEFAULT 'percentage',
  `min_value` decimal(20,4) DEFAULT NULL,
  `max_value` decimal(20,4) DEFAULT NULL,
  `default_value` decimal(20,4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scenario_var_key` (`variable_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_scenarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_scenarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `base_metric_key` varchar(100) NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`variables`)),
  `baseline_value` decimal(20,4) DEFAULT NULL,
  `scenario_value` decimal(20,4) DEFAULT NULL,
  `impact_value` decimal(20,4) DEFAULT NULL,
  `impact_percentage` decimal(10,4) DEFAULT NULL,
  `affected_records` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`affected_records`)),
  `simulation_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`simulation_result`)),
  `safety_label` varchar(200) NOT NULL DEFAULT 'Simulation — no ERP data has been changed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scenario_company` (`company_id`),
  KEY `idx_scenario_metric` (`base_metric_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_scheduler_heartbeats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_scheduler_heartbeats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scheduler_key` varchar(100) NOT NULL,
  `status` enum('healthy','warning','degraded','unavailable','unknown') NOT NULL DEFAULT 'unknown',
  `last_heartbeat_at` timestamp NULL DEFAULT NULL,
  `last_successful_cycle_at` timestamp NULL DEFAULT NULL,
  `last_failure_at` timestamp NULL DEFAULT NULL,
  `expected_interval_seconds` int(10) unsigned NOT NULL DEFAULT 60,
  `due_jobs` int(10) unsigned NOT NULL DEFAULT 0,
  `delayed_jobs` int(10) unsigned NOT NULL DEFAULT 0,
  `last_result_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `scheduler_key` (`scheduler_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_scheduler_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_scheduler_locks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `automation_id` int(10) unsigned NOT NULL,
  `lock_key` varchar(100) NOT NULL,
  `locked_by` varchar(255) NOT NULL,
  `locked_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lock_key` (`lock_key`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_automation` (`automation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_key` varchar(100) NOT NULL,
  `event_type` enum('policy_violation','permission_denied','prompt_injection_attempt','unauthorized_tool','blocked_provider','sensitive_data_leak_attempt','repeated_failed_auth','abnormal_usage','autonomy_limit_exceeded','cost_limit_exceeded','rate_limit_exceeded','injection_detected','data_masking_triggered','confirmation_required','review_required','kill_switch_triggered','provider_restricted','action_blocked','sql_safety_violation','parameter_validation_failed') NOT NULL,
  `severity` enum('info','warning','high','critical') NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `trace_id` varchar(80) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_category` varchar(50) DEFAULT NULL,
  `event_data_json` longtext DEFAULT NULL,
  `safe_data_json` longtext DEFAULT NULL,
  `status` enum('open','acknowledged','investigating','resolved','ignored') NOT NULL DEFAULT 'open',
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`),
  KEY `idx_ai_sec_events_type` (`event_type`),
  KEY `idx_ai_sec_events_severity` (`severity`),
  KEY `idx_ai_sec_events_company` (`company_id`),
  KEY `idx_ai_sec_events_status` (`status`),
  KEY `idx_ai_sec_events_category` (`event_category`),
  KEY `idx_ai_sec_events_request` (`request_id`),
  KEY `idx_ai_sec_events_created` (`created_at`),
  KEY `idx_ai_sec_events_user` (`user_id`),
  KEY `idx_ai_sec_events_trace` (`trace_id`),
  CONSTRAINT `ai_security_events_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `ai_observability_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_sensitive_doc_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_sensitive_doc_policies` (
  `policy_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `ai_processing_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `external_provider_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `masking_required` tinyint(1) NOT NULL DEFAULT 0,
  `retention_days` int(11) DEFAULT 365,
  `export_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `sharing_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`policy_id`),
  KEY `idx_policy_company` (`company_id`),
  KEY `idx_policy_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skill_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skill_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skill_id` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `total_starts` int(11) DEFAULT 0,
  `total_completed` int(11) DEFAULT 0,
  `total_cancelled` int(11) DEFAULT 0,
  `total_failed` int(11) DEFAULT 0,
  `avg_duration_seconds` float DEFAULT NULL,
  `total_feedback` int(11) DEFAULT 0,
  `positive_feedback` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_analytics_date` (`skill_id`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skill_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skill_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` varchar(50) NOT NULL,
  `skill_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` enum('helpful','not_helpful','too_long','incorrect') NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feedback_skill` (`skill_id`),
  KEY `idx_feedback_run` (`run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skill_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skill_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` varchar(50) NOT NULL,
  `skill_id` varchar(100) NOT NULL,
  `skill_version` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `current_step` int(11) DEFAULT 0,
  `total_steps` int(11) DEFAULT 0,
  `status` enum('started','in_progress','waiting_for_user','waiting_for_confirmation','completed','failed','cancelled','expired') NOT NULL DEFAULT 'started',
  `context_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_json`)),
  `result_summary` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `run_id` (`run_id`),
  KEY `idx_run_skill` (`skill_id`),
  KEY `idx_run_user` (`user_id`),
  KEY `idx_run_status` (`status`),
  KEY `idx_run_started` (`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skill_step_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skill_step_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` varchar(50) NOT NULL,
  `step_index` int(11) NOT NULL,
  `step_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','failed','skipped') DEFAULT 'pending',
  `input_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_json`)),
  `output_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_json`)),
  `tool_used` varchar(100) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_step_run` (`run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skill_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skill_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `suggested_skill_id` varchar(100) NOT NULL,
  `reason` text DEFAULT NULL,
  `confidence` float DEFAULT 0.5,
  `is_accepted` tinyint(1) DEFAULT 0,
  `is_dismissed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suggestion_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skill_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skill_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `template_type` varchar(50) DEFAULT NULL,
  `steps_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`steps_json`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_id` (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skill_id` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `skill_type` enum('guidance','navigation','workflow','analysis','knowledge','composite') NOT NULL DEFAULT 'guidance',
  `status` enum('draft','testing','approved','published','deprecated','archived') NOT NULL DEFAULT 'draft',
  `version` int(11) NOT NULL DEFAULT 1,
  `required_permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_permissions_json`)),
  `required_capabilities_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_capabilities_json`)),
  `input_schema_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_schema_json`)),
  `steps_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`steps_json`)),
  `output_schema_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_schema_json`)),
  `safety_classification` enum('read_only','confirmation_required','blocked') DEFAULT 'read_only',
  `mode` enum('guide','assist') DEFAULT 'guide',
  `max_steps` int(11) DEFAULT 20,
  `max_runtime_seconds` int(11) DEFAULT 300,
  `max_tool_calls` int(11) DEFAULT 10,
  `parent_skill_id` varchar(100) DEFAULT NULL,
  `nesting_depth` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `deprecated_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `skill_id` (`skill_id`),
  KEY `idx_skill_module` (`module`),
  KEY `idx_skill_type` (`skill_type`),
  KEY `idx_skill_status` (`status`),
  KEY `idx_skill_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_sql_allowed_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_sql_allowed_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_sql_field` (`table_name`,`field_name`,`company_id`),
  KEY `idx_ai_sql_fields_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_sql_allowed_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_sql_allowed_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_sql_table` (`table_name`,`company_id`),
  KEY `idx_ai_sql_tables_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_sync_job_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_sync_job_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_job_id` int(11) NOT NULL,
  `external_record_id` varchar(150) DEFAULT NULL,
  `preview_action` enum('create','update','skip','conflict','error') NOT NULL DEFAULT 'skip',
  `conflict_status` enum('external_newer','erp_newer','conflict','identical') NOT NULL DEFAULT 'identical',
  `resolution_status` enum('pending','approved','skipped','failed') NOT NULL DEFAULT 'pending',
  `source_data_json` longtext DEFAULT NULL,
  `mapped_data_json` longtext DEFAULT NULL,
  `validation_errors_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_sync_rows_job` (`sync_job_id`),
  KEY `idx_ai_sync_rows_action` (`preview_action`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_sync_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_sync_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `connection_id` int(11) NOT NULL,
  `connector_key` varchar(100) NOT NULL,
  `job_key` varchar(150) NOT NULL,
  `job_type` enum('manual','scheduled','event_triggered') NOT NULL DEFAULT 'manual',
  `target_entity` varchar(100) NOT NULL,
  `status` enum('queued','running','completed','failed','partial','cancelled','preview_ready','awaiting_confirmation') NOT NULL DEFAULT 'queued',
  `mapping_id` int(11) DEFAULT NULL,
  `preview_json` longtext DEFAULT NULL,
  `apply_summary_json` longtext DEFAULT NULL,
  `conflict_strategy` enum('manual_review','prefer_erp','prefer_external') NOT NULL DEFAULT 'manual_review',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_key` (`job_key`),
  KEY `idx_ai_sync_jobs_company` (`company_id`),
  KEY `idx_ai_sync_jobs_connection` (`connection_id`),
  KEY `idx_ai_sync_jobs_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_sync_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_sync_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `connection_id` int(11) DEFAULT NULL,
  `mapping_key` varchar(150) NOT NULL,
  `name` varchar(150) NOT NULL,
  `target_entity` varchar(100) NOT NULL,
  `source_schema_json` longtext DEFAULT NULL,
  `target_schema_json` longtext DEFAULT NULL,
  `rules_json` longtext DEFAULT NULL,
  `validation_json` longtext DEFAULT NULL,
  `status` enum('draft','active','disabled','archived') NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mapping_key` (`mapping_key`),
  KEY `idx_ai_sync_mappings_company` (`company_id`),
  KEY `idx_ai_sync_mappings_target` (`target_entity`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_task_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_task_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `step_number` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `step_type` enum('read','action','reasoning') NOT NULL DEFAULT 'read',
  `tool_id` varchar(100) DEFAULT NULL,
  `action_id` varchar(100) DEFAULT NULL,
  `input_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_json`)),
  `output_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_json`)),
  `status` enum('pending','running','awaiting_confirmation','completed','failed','skipped','cancelled') NOT NULL DEFAULT 'pending',
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ai_task_steps_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `ai_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `conversation_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `original_request` text DEFAULT NULL,
  `task_type` enum('manual','automation') NOT NULL DEFAULT 'manual',
  `status` enum('draft','planned','awaiting_confirmation','running','paused','completed','failed','cancelled') NOT NULL DEFAULT 'draft',
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `current_step` int(10) unsigned DEFAULT 0,
  `total_steps` int(10) unsigned DEFAULT 0,
  `plan_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`plan_json`)),
  `result_summary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_tool_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_tool_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tool_name` varchar(120) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `config_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_tool_config` (`tool_name`,`company_id`),
  KEY `idx_ai_tool_configs_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_usage_daily_aggregates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_usage_daily_aggregates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usage_date` date NOT NULL,
  `provider_id` int(10) unsigned DEFAULT NULL,
  `model_id` int(10) unsigned DEFAULT NULL,
  `feature` varchar(50) NOT NULL DEFAULT 'unknown',
  `user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `request_count` int(10) unsigned NOT NULL DEFAULT 0,
  `input_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `output_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `estimated_cost` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `avg_duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`usage_date`),
  KEY `idx_feature` (`feature`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_usage_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` int(10) unsigned DEFAULT NULL,
  `model_id` int(10) unsigned DEFAULT NULL,
  `feature` varchar(50) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `request_type` varchar(50) DEFAULT NULL,
  `input_tokens` int(10) unsigned DEFAULT 0,
  `output_tokens` int(10) unsigned DEFAULT 0,
  `estimated_cost` decimal(10,6) DEFAULT 0.000000,
  `duration_ms` int(10) unsigned DEFAULT 0,
  `status` enum('success','failed','timeout') NOT NULL DEFAULT 'success',
  `error_category` varchar(50) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_model` (`model_id`),
  KEY `idx_feature` (`feature`),
  KEY `idx_user` (`user_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `source` enum('user','ai_suggested','learned') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_pref` (`user_id`,`company_id`,`preference_key`),
  KEY `idx_pref_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `event_type` enum('recording_uploaded','transcription_completed','transcription_failed','voice_command_sent','tts_generated','tts_failed','voice_action_proposed','voice_navigation_requested','dictation_used','shortcuts_configured','settings_changed') NOT NULL,
  `transcription_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_va_user` (`user_id`),
  KEY `idx_va_company` (`company_id`),
  KEY `idx_va_event` (`event_type`),
  KEY `idx_va_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_type` enum('stt','tts') NOT NULL,
  `config_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_data`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vc_company_provider_type` (`company_id`,`provider`,`provider_type`),
  KEY `idx_vc_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_confirmations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_confirmations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `confirmation_key` varchar(128) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_id` varchar(100) DEFAULT NULL,
  `parameters_json` longtext NOT NULL,
  `parameters_hash` varchar(64) NOT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','confirmed','denied','expired','invalidated') NOT NULL DEFAULT 'pending',
  `confirmed_at` datetime DEFAULT NULL,
  `denied_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `invalidated_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_voice_confirm_key` (`confirmation_key`),
  KEY `idx_ai_voice_confirm_company` (`company_id`),
  KEY `idx_ai_voice_confirm_user` (`user_id`),
  KEY `idx_ai_voice_confirm_status` (`status`),
  KEY `idx_ai_voice_confirm_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_injection_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_injection_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `transcription_text` text NOT NULL,
  `injection_type` varchar(100) DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `action_taken` enum('passed','warned','blocked') NOT NULL DEFAULT 'passed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_voice_inj_company` (`company_id`),
  KEY `idx_ai_voice_inj_action` (`action_taken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content_text` text DEFAULT NULL,
  `transcription_text` text DEFAULT NULL,
  `transcription_confidence` decimal(5,4) DEFAULT NULL,
  `transcription_language` varchar(10) DEFAULT NULL,
  `audio_duration_ms` int(11) DEFAULT NULL,
  `tts_generated` tinyint(1) NOT NULL DEFAULT 0,
  `tts_provider` varchar(100) DEFAULT NULL,
  `intent_type` varchar(100) DEFAULT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `action_id` int(11) DEFAULT NULL,
  `action_status` varchar(50) DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `governance_decision` varchar(50) DEFAULT NULL,
  `f25_task_id` int(11) DEFAULT NULL,
  `f25_task_key` varchar(100) DEFAULT NULL,
  `f26_event_id` int(11) DEFAULT NULL,
  `f27_document_id` int(11) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_voice_msg_session` (`session_id`),
  KEY `idx_ai_voice_msg_company` (`company_id`),
  KEY `idx_ai_voice_msg_user` (`user_id`),
  KEY `idx_ai_voice_msg_role` (`role`),
  CONSTRAINT `ai_voice_messages_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `ai_voice_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preferred_language` varchar(10) NOT NULL DEFAULT 'en',
  `preferred_voice` varchar(100) DEFAULT NULL,
  `tts_speed` decimal(3,2) NOT NULL DEFAULT 1.00,
  `auto_tts` tinyint(1) NOT NULL DEFAULT 1,
  `push_to_talk` tinyint(1) NOT NULL DEFAULT 1,
  `noise_cancellation` tinyint(1) NOT NULL DEFAULT 0,
  `max_recording_seconds` int(11) NOT NULL DEFAULT 120,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_voice_pref_user` (`company_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_retention_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `audio_retention_days` int(11) NOT NULL DEFAULT 7,
  `transcript_retention_days` int(11) NOT NULL DEFAULT 90,
  `tts_cache_retention_days` int(11) NOT NULL DEFAULT 1,
  `metadata_retention_days` int(11) NOT NULL DEFAULT 365,
  `auto_delete_audio` tinyint(1) NOT NULL DEFAULT 1,
  `auto_delete_transcripts` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vrp_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'en',
  `stt_provider` varchar(100) DEFAULT NULL,
  `tts_provider` varchar(100) DEFAULT NULL,
  `status` enum('active','paused','completed','expired') NOT NULL DEFAULT 'active',
  `message_count` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_voice_session_company` (`company_id`),
  KEY `idx_ai_voice_session_user` (`user_id`),
  KEY `idx_ai_voice_session_status` (`status`),
  KEY `idx_ai_voice_session_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_shortcuts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_shortcuts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `command` varchar(100) NOT NULL,
  `target_action` varchar(200) NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `description` varchar(300) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vs_company_command` (`company_id`,`command`),
  KEY `idx_vs_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_transcriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_transcriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'browser',
  `model` varchar(100) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `detected_language` varchar(10) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT 0,
  `file_size_bytes` int(11) DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `transcript_text` text DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `raw_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_metadata`)),
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vt_user` (`user_id`),
  KEY `idx_vt_company` (`company_id`),
  KEY `idx_vt_status` (`status`),
  KEY `idx_vt_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_tts_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_tts_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'browser',
  `text_hash` varchar(64) NOT NULL,
  `text_preview` varchar(200) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `voice` varchar(50) DEFAULT NULL,
  `speed` decimal(3,2) DEFAULT 1.00,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size_bytes` int(11) DEFAULT 0,
  `duration_ms` int(11) DEFAULT 0,
  `status` enum('pending','generating','completed','failed') NOT NULL DEFAULT 'pending',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vtts_hash` (`text_hash`),
  KEY `idx_vtts_user` (`user_id`),
  KEY `idx_vtts_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_voice_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_voice_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feature` enum('stt','tts') NOT NULL,
  `provider` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT 0,
  `estimated_cost` decimal(10,6) DEFAULT 0.000000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vu_company` (`company_id`),
  KEY `idx_vu_user` (`user_id`),
  KEY `idx_vu_feature` (`feature`),
  KEY `idx_vu_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_webhook_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_webhook_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `connection_id` int(11) DEFAULT NULL,
  `webhook_key` varchar(150) NOT NULL,
  `name` varchar(150) NOT NULL,
  `destination_url` varchar(255) NOT NULL,
  `allowed_host` varchar(190) NOT NULL,
  `event_source` varchar(100) NOT NULL,
  `payload_schema_json` longtext DEFAULT NULL,
  `secret_encrypted` longtext DEFAULT NULL,
  `secret_iv` varchar(255) DEFAULT NULL,
  `signature_header` varchar(100) NOT NULL DEFAULT 'X-AI-Signature',
  `status` enum('draft','active','disabled','failed','revoked') NOT NULL DEFAULT 'draft',
  `retry_policy_json` longtext DEFAULT NULL,
  `allow_internal_destination` tinyint(1) NOT NULL DEFAULT 0,
  `last_sent_at` datetime DEFAULT NULL,
  `last_error_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_key` (`webhook_key`),
  KEY `idx_ai_webhooks_company` (`company_id`),
  KEY `idx_ai_webhooks_status` (`status`),
  KEY `idx_ai_webhooks_event` (`event_source`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_webhook_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_webhook_deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `request_id` varchar(100) NOT NULL,
  `status` enum('queued','sent','failed','blocked') NOT NULL DEFAULT 'queued',
  `http_status` int(11) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `safe_payload_summary` text DEFAULT NULL,
  `response_summary` text DEFAULT NULL,
  `error_category` varchar(50) DEFAULT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_webhook_deliveries_webhook` (`webhook_id`),
  KEY `idx_ai_webhook_deliveries_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workflow_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workflow_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `workflow_version_id` int(11) NOT NULL,
  `node_id` varchar(100) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `approver_user_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','expired','cancelled') NOT NULL DEFAULT 'pending',
  `title` varchar(200) NOT NULL,
  `summary` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_workflow_approvals_status` (`status`),
  KEY `idx_ai_workflow_approvals_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workflow_run_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workflow_run_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `node_id` varchar(100) NOT NULL,
  `node_type` varchar(100) NOT NULL,
  `status` enum('pending','running','completed','failed','skipped','waiting') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `safe_output_summary` text DEFAULT NULL,
  `error_category` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_workflow_run_nodes_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workflow_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workflow_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `workflow_version_id` int(11) NOT NULL,
  `workflow_version_number` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `trigger_type` enum('manual','scheduled','event') NOT NULL DEFAULT 'manual',
  `trigger_context_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_context_json`)),
  `status` enum('queued','running','waiting','waiting_approval','completed','failed','cancelled','expired') NOT NULL DEFAULT 'queued',
  `current_node_id` varchar(100) DEFAULT NULL,
  `execution_state_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`execution_state_json`)),
  `resume_after` datetime DEFAULT NULL,
  `retry_attempt` int(11) NOT NULL DEFAULT 0,
  `dry_run` tinyint(1) NOT NULL DEFAULT 0,
  `started_by` int(11) DEFAULT NULL,
  `error_summary` varchar(500) DEFAULT NULL,
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_workflow_runs_status` (`status`),
  KEY `idx_ai_workflow_runs_workflow` (`workflow_id`),
  KEY `idx_ai_workflow_runs_resume` (`resume_after`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workflow_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workflow_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `trigger_type` enum('manual','scheduled','event') NOT NULL DEFAULT 'manual',
  `graph_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`graph_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_key` (`template_key`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workflow_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workflow_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `version_status` enum('draft','testing','approved','published','enabled','disabled','archived') NOT NULL DEFAULT 'draft',
  `trigger_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_config_json`)),
  `graph_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`graph_json`)),
  `validation_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_json`)),
  `ai_draft_prompt` text DEFAULT NULL,
  `ai_draft_summary` text DEFAULT NULL,
  `node_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_workflow_version` (`workflow_id`,`version_number`),
  KEY `idx_ai_workflow_versions_status` (`version_status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workflows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_key` varchar(100) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `module` varchar(50) DEFAULT 'ai',
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','testing','approved','published','enabled','disabled','archived') NOT NULL DEFAULT 'draft',
  `safety_classification` enum('low','medium','high','restricted') NOT NULL DEFAULT 'low',
  `trigger_type` enum('manual','scheduled','event') NOT NULL DEFAULT 'manual',
  `current_version_number` int(11) NOT NULL DEFAULT 1,
  `current_version_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `enabled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_key` (`workflow_key`),
  KEY `idx_ai_workflows_status` (`status`),
  KEY `idx_ai_workflows_company` (`company_id`),
  KEY `idx_ai_workflows_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_activity` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `action_type` enum('workspace_created','workspace_updated','member_added','member_removed','role_changed','conversation_shared','context_pinned','finding_created','finding_reviewed','research_started','agent_result_shared','workspace_archived','goal_created','instruction_set','knowledge_added') NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `idx_wact_workspace` (`workspace_id`),
  KEY `idx_wact_actor` (`actor_user_id`),
  KEY `idx_wact_type` (`action_type`),
  CONSTRAINT `ai_workspace_activity_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_conversations` (
  `conversation_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `context_type` enum('shared_workspace') NOT NULL DEFAULT 'shared_workspace',
  `created_by` int(11) NOT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`conversation_id`),
  KEY `idx_wconv_workspace` (`workspace_id`),
  KEY `idx_wconv_creator` (`created_by`),
  CONSTRAINT `ai_workspace_conversations_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_goals` (
  `goal_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `set_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `success_criteria` text DEFAULT NULL,
  `status` enum('active','achieved','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`goal_id`),
  KEY `idx_wgoal_workspace` (`workspace_id`),
  CONSTRAINT `ai_workspace_goals_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_instructions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_instructions` (
  `instruction_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `set_by` int(11) NOT NULL,
  `instruction_text` text NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 50,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`instruction_id`),
  KEY `idx_winst_workspace` (`workspace_id`),
  CONSTRAINT `ai_workspace_instructions_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_knowledge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_knowledge` (
  `wk_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `knowledge_collection_id` int(11) NOT NULL,
  `added_by` int(11) NOT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`wk_id`),
  UNIQUE KEY `uk_ws_know` (`workspace_id`,`knowledge_collection_id`),
  CONSTRAINT `ai_workspace_knowledge_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','manager','member','viewer') NOT NULL DEFAULT 'member',
  `invited_by` int(11) DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `uk_ws_user` (`workspace_id`,`user_id`),
  KEY `idx_wsm_user` (`user_id`),
  CONSTRAINT `ai_workspace_members_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `author_user_id` int(11) NOT NULL,
  `message_type` enum('user','ai','system','activity') NOT NULL DEFAULT 'user',
  `content` text NOT NULL,
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `idx_wmsg_conv` (`conversation_id`),
  KEY `idx_wmsg_author` (`author_user_id`),
  CONSTRAINT `ai_workspace_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `ai_workspace_conversations` (`conversation_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `notification_type` enum('member_added','finding_created','task_assigned','research_updated','agent_result_shared','mention') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_wnotif_recipient` (`recipient_user_id`),
  KEY `idx_wnotif_workspace` (`workspace_id`),
  KEY `idx_wnotif_unread` (`recipient_user_id`,`is_read`),
  CONSTRAINT `ai_workspace_notifications_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_pins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_pins` (
  `pin_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `pinned_by` int(11) NOT NULL,
  `reference_type` enum('conversation','task','insight','knowledge','erp_record','agent_result','finding','research_session') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `pinned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pin_id`),
  UNIQUE KEY `uk_ws_pin_ref` (`workspace_id`,`reference_type`,`reference_id`),
  CONSTRAINT `ai_workspace_pins_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_presence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_presence` (
  `workspace_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('viewing','active_recently','editing') NOT NULL DEFAULT 'active_recently',
  `last_seen` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`workspace_id`,`user_id`),
  KEY `idx_presence_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspace_shared_agent_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspace_shared_agent_runs` (
  `share_id` int(11) NOT NULL AUTO_INCREMENT,
  `workspace_id` int(11) NOT NULL,
  `agent_run_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_summary` text DEFAULT NULL,
  `status` enum('shared','reviewed','archived') NOT NULL DEFAULT 'shared',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`share_id`),
  UNIQUE KEY `uk_ws_agent_run` (`workspace_id`,`agent_run_id`),
  CONSTRAINT `ai_workspace_shared_agent_runs_ibfk_1` FOREIGN KEY (`workspace_id`) REFERENCES `ai_workspaces` (`workspace_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_workspaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_workspaces` (
  `workspace_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `owner_user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `visibility` enum('private','restricted','company') NOT NULL DEFAULT 'restricted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`workspace_id`),
  KEY `idx_ws_company` (`company_id`),
  KEY `idx_ws_owner` (`owner_user_id`),
  KEY `idx_ws_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(40) NOT NULL DEFAULT 'general',
  `value_type` enum('string','integer','decimal','boolean','json','date') NOT NULL DEFAULT 'string',
  `label` varchar(160) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `assigned_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `assigned_by` int(10) unsigned NOT NULL,
  `assignment_notes` text DEFAULT NULL,
  `status` enum('active','returned','overdue') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `asset_assignments_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_assignments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_depreciation_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_depreciation_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `financial_asset_id` int(10) unsigned NOT NULL,
  `period_date` date NOT NULL,
  `opening_book_value` decimal(15,2) NOT NULL,
  `depreciation_amount` decimal(15,2) NOT NULL,
  `accumulated_depreciation` decimal(15,2) NOT NULL,
  `closing_book_value` decimal(15,2) NOT NULL,
  `status` enum('scheduled','posted') DEFAULT 'scheduled',
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `idx_asset_period` (`financial_asset_id`,`period_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `asset_depreciation_schedules_ibfk_1` FOREIGN KEY (`financial_asset_id`) REFERENCES `fixed_asset_financials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_depreciation_schedules_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_disposal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_disposal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `disposal_date` date NOT NULL,
  `disposal_type` enum('sale','scrap','write-off','donation') NOT NULL,
  `disposal_proceeds` decimal(15,2) DEFAULT 0.00,
  `original_cost` decimal(15,2) NOT NULL,
  `accumulated_depreciation` decimal(15,2) NOT NULL,
  `net_book_value` decimal(15,2) NOT NULL,
  `gain_loss` decimal(15,2) GENERATED ALWAYS AS (case when `disposal_proceeds` > `net_book_value` then `disposal_proceeds` - `net_book_value` else `net_book_value` - `disposal_proceeds` end) STORED,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_id` (`asset_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `asset_disposal_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_disposal_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_disposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_disposals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `disposal_date` date NOT NULL,
  `disposal_method` enum('sale','scrapped','donated','other') NOT NULL,
  `disposal_value` decimal(19,4) DEFAULT 0.0000,
  `original_cost` decimal(19,4) NOT NULL,
  `accumulated_depreciation` decimal(19,4) DEFAULT 0.0000,
  `net_book_value` decimal(19,4) NOT NULL,
  `gain_loss` decimal(19,4) DEFAULT 0.0000,
  `reason` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_date` (`disposal_date`),
  CONSTRAINT `asset_disposals_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_impairment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_impairment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `impairment_date` date NOT NULL,
  `carrying_amount` decimal(15,2) NOT NULL,
  `recoverable_amount` decimal(15,2) NOT NULL,
  `impairment_loss` decimal(15,2) GENERATED ALWAYS AS (case when `carrying_amount` > `recoverable_amount` then `carrying_amount` - `recoverable_amount` else 0.00 end) STORED,
  `reason` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `asset_impairment_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_impairment_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_impairments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_impairments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `impairment_date` date NOT NULL,
  `book_value_before_impairment` decimal(19,4) NOT NULL,
  `recoverable_amount` decimal(19,4) NOT NULL,
  `impairment_loss` decimal(19,4) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_date` (`impairment_date`),
  CONSTRAINT `asset_impairments_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_maintenance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_maintenance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('repair','upgrade','cleaning','replacement','inspection') NOT NULL,
  `vendor_name` varchar(200) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_date` (`maintenance_date`),
  KEY `idx_type` (`maintenance_type`),
  CONSTRAINT `asset_maintenance_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_returns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `return_date` date NOT NULL,
  `return_condition` enum('good','damaged','needs_repair') DEFAULT 'good',
  `damage_notes` text DEFAULT NULL,
  `accepted_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assignment` (`assignment_id`),
  KEY `accepted_by` (`accepted_by`),
  CONSTRAINT `asset_returns_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `asset_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_returns_ibfk_2` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_transfer_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_transfer_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `from_department_id` int(10) unsigned DEFAULT NULL,
  `to_department_id` int(10) unsigned DEFAULT NULL,
  `from_cost_center_id` int(10) unsigned DEFAULT NULL,
  `to_cost_center_id` int(10) unsigned DEFAULT NULL,
  `from_location_id` int(10) unsigned DEFAULT NULL,
  `to_location_id` int(10) unsigned DEFAULT NULL,
  `from_project_id` int(10) unsigned DEFAULT NULL,
  `to_project_id` int(10) unsigned DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `transfer_reason` text DEFAULT NULL,
  `approved_by` int(10) unsigned NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `asset_transfer_history_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `from_department_id` int(10) unsigned DEFAULT NULL,
  `to_department_id` int(10) unsigned DEFAULT NULL,
  `from_cost_center_id` int(10) unsigned DEFAULT NULL,
  `to_cost_center_id` int(10) unsigned DEFAULT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `transfer_reason` text DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_date` (`transfer_date`),
  CONSTRAINT `asset_transfers_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(50) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `asset_condition` enum('new','good','fair','damaged') DEFAULT 'new',
  `status` enum('available','assigned','maintenance','damaged','lost','retired') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `idx_asset_code` (`asset_code`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_condition` (`asset_condition`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','absent','half_day','leave','holiday','weekend') DEFAULT 'present',
  `late_minutes` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`employee_id`,`attendance_date`),
  KEY `idx_employee_date` (`employee_id`,`attendance_date`),
  KEY `idx_date` (`attendance_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2432 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_name` varchar(200) NOT NULL,
  `bank_name` varchar(200) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `branch_name` varchar(200) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'AED',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `chart_account_id` int(10) unsigned NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chart_account_id` (`chart_account_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `bank_accounts_ibfk_1` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_reconciliation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(10) unsigned NOT NULL,
  `statement_date` date NOT NULL,
  `statement_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `book_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('in_progress','completed','cancelled') NOT NULL DEFAULT 'in_progress',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_bank_account` (`bank_account_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `bank_reconciliation_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `bank_reconciliation_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_reconciliation_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_id` int(10) unsigned NOT NULL,
  `bank_transaction_id` int(10) unsigned DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_matched` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reconciliation_id` (`reconciliation_id`),
  KEY `bank_transaction_id` (`bank_transaction_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `bank_reconciliation_items_ibfk_1` FOREIGN KEY (`reconciliation_id`) REFERENCES `bank_reconciliation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_reconciliation_items_ibfk_2` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliation_items_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(10) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('deposit','withdrawal','interest','bank_charges','adjustment') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `status` enum('posted','draft','cancelled') NOT NULL DEFAULT 'posted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `idx_bank_account` (`bank_account_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_transaction_type` (`transaction_type`),
  CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `bank_transactions_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bill_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bill_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `change_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_bill` (`bill_id`),
  CONSTRAINT `bill_status_history_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` int(10) unsigned NOT NULL,
  `budget_version_id` bigint(20) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `chart_of_accounts_id` int(10) unsigned NOT NULL,
  `month` tinyint(3) unsigned DEFAULT NULL COMMENT '1-12 (Jul=1..Jun=12); NULL = whole-year/annual',
  `budget_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_line` (`budget_version_id`,`department_id`,`project_id`,`chart_of_accounts_id`,`month`),
  KEY `idx_budget_line_budget` (`budget_id`),
  KEY `idx_budget_line_version` (`budget_version_id`),
  KEY `idx_budget_line_dept` (`department_id`),
  KEY `idx_budget_line_project` (`project_id`),
  KEY `idx_budget_line_account` (`chart_of_accounts_id`),
  CONSTRAINT `budget_lines_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_lines_ibfk_2` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_lines_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_lines_ibfk_4` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_lines_ibfk_5` FOREIGN KEY (`chart_of_accounts_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_monitoring_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_monitoring_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` int(10) unsigned NOT NULL,
  `budget_version_id` bigint(20) unsigned NOT NULL,
  `financial_year_id` int(10) unsigned NOT NULL,
  `snapshot_date` date NOT NULL,
  `month` tinyint(3) unsigned DEFAULT NULL,
  `allocated` decimal(15,2) NOT NULL DEFAULT 0.00,
  `spent` decimal(15,2) NOT NULL DEFAULT 0.00,
  `committed` decimal(15,2) NOT NULL DEFAULT 0.00,
  `available` decimal(15,2) NOT NULL DEFAULT 0.00,
  `utilization_pct` decimal(7,2) NOT NULL DEFAULT 0.00,
  `generated_by` int(10) unsigned DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snapshot_version_month_date` (`budget_version_id`,`month`,`snapshot_date`),
  KEY `budget_id` (`budget_id`),
  KEY `financial_year_id` (`financial_year_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `budget_monitoring_snapshots_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_monitoring_snapshots_ibfk_2` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_monitoring_snapshots_ibfk_3` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `budget_monitoring_snapshots_ibfk_4` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` int(10) unsigned NOT NULL,
  `budget_version_id` bigint(20) unsigned NOT NULL,
  `line_id` bigint(20) unsigned NOT NULL,
  `revision_type` enum('increase','decrease','adjustment') NOT NULL,
  `old_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `new_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'signed delta',
  `reason` text NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `budget_id` (`budget_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_revision_version` (`budget_version_id`),
  KEY `idx_revision_line` (`line_id`),
  CONSTRAINT `budget_revisions_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_revisions_ibfk_2` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_revisions_ibfk_3` FOREIGN KEY (`line_id`) REFERENCES `budget_lines` (`id`),
  CONSTRAINT `budget_revisions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_no` varchar(50) NOT NULL,
  `budget_id` int(10) unsigned NOT NULL,
  `budget_version_id` bigint(20) unsigned NOT NULL,
  `from_line_id` bigint(20) unsigned NOT NULL,
  `to_line_id` bigint(20) unsigned NOT NULL,
  `transfer_type` enum('department','project','account') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_no` (`transfer_no`),
  KEY `budget_id` (`budget_id`),
  KEY `from_line_id` (`from_line_id`),
  KEY `to_line_id` (`to_line_id`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_transfer_status` (`status`),
  KEY `idx_transfer_version` (`budget_version_id`),
  CONSTRAINT `budget_transfers_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_transfers_ibfk_2` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_transfers_ibfk_3` FOREIGN KEY (`from_line_id`) REFERENCES `budget_lines` (`id`),
  CONSTRAINT `budget_transfers_ibfk_4` FOREIGN KEY (`to_line_id`) REFERENCES `budget_lines` (`id`),
  CONSTRAINT `budget_transfers_ibfk_5` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_transfers_ibfk_6` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` int(10) unsigned NOT NULL,
  `version_no` int(11) NOT NULL,
  `version_type` enum('original','revision','forecast','final') NOT NULL DEFAULT 'original',
  `version_label` varchar(50) NOT NULL,
  `status` enum('draft','approved','active','locked') NOT NULL DEFAULT 'draft',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_version` (`budget_id`,`version_no`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_version_status` (`status`),
  CONSTRAINT `budget_versions_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_versions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_versions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `budget_code` varchar(50) NOT NULL,
  `budget_name` varchar(200) NOT NULL,
  `financial_year_id` int(10) unsigned NOT NULL,
  `budget_type` enum('annual','quarterly','monthly','project','department') NOT NULL,
  `status` enum('draft','pending_approval','approved','active','rejected','closed') NOT NULL DEFAULT 'draft',
  `current_version_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK to budget_versions ÔÇö the active version',
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `budget_code` (`budget_code`),
  UNIQUE KEY `uq_budgets_name_year` (`financial_year_id`,`budget_name`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_budget_type` (`budget_type`),
  KEY `idx_budget_status` (`status`),
  KEY `fk_budgets_current_version` (`current_version_id`),
  CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`),
  CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budgets_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_budgets_current_version` FOREIGN KEY (`current_version_id`) REFERENCES `budget_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_name` varchar(200) NOT NULL,
  `location` varchar(200) DEFAULT NULL,
  `custodian_employee_id` int(10) unsigned DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `chart_account_id` int(10) unsigned NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chart_account_id` (`chart_account_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `cash_accounts_ibfk_1` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_flow_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_flow_classifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `classification` enum('operating','investing','financing','non_cash') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `cash_flow_classifications_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_account_id` int(10) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('receipt','payment','cash_deposit','cash_withdrawal','adjustment') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `status` enum('posted','draft','cancelled') NOT NULL DEFAULT 'posted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `idx_cash_account` (`cash_account_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_transaction_type` (`transaction_type`),
  CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `cash_transactions_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chart_of_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `account_code` varchar(50) NOT NULL,
  `account_name` varchar(200) NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `opening_balance_type` enum('debit','credit') DEFAULT 'debit',
  `allow_manual_entry` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code` (`account_code`),
  KEY `idx_coa_group` (`group_id`),
  KEY `idx_coa_type` (`type_id`),
  KEY `idx_coa_status` (`status`),
  KEY `idx_coa_company` (`company_id`),
  CONSTRAINT `chart_of_accounts_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `account_groups` (`id`),
  CONSTRAINT `chart_of_accounts_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `account_types` (`id`),
  CONSTRAINT `fk_coa_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `client_contacts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `client_documents_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `note_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `client_notes_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_notes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_code` varchar(50) NOT NULL,
  `customer_type` enum('company','individual','partnership','sole_proprietorship','government','trust') NOT NULL DEFAULT 'company',
  `account_group` varchar(50) DEFAULT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `search_term` varchar(120) DEFAULT NULL,
  `segment` varchar(60) DEFAULT NULL,
  `parent_client_id` int(10) unsigned DEFAULT NULL,
  `is_one_time` tinyint(1) NOT NULL DEFAULT 0,
  `customer_since` date DEFAULT NULL,
  `lead_source` varchar(80) DEFAULT NULL,
  `company_name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `alternate_phone` varchar(40) DEFAULT NULL,
  `whatsapp` varchar(40) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'USD',
  `payment_terms` varchar(50) DEFAULT NULL,
  `payment_term_days` smallint(5) unsigned NOT NULL DEFAULT 30,
  `payment_method` varchar(40) DEFAULT NULL,
  `default_receivable_account_id` int(10) unsigned DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit_rating` varchar(10) DEFAULT NULL,
  `credit_hold` enum('no','manual','automatic') NOT NULL DEFAULT 'no',
  `collection_priority` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cash_discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `billing_cycle` enum('per_transaction','weekly','monthly','quarterly','annual') NOT NULL DEFAULT 'per_transaction',
  `consolidated_billing` tinyint(1) NOT NULL DEFAULT 0,
  `allow_partial_payment` tinyint(1) NOT NULL DEFAULT 1,
  `price_list` varchar(60) DEFAULT NULL,
  `tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_delivery` enum('email','print','portal','courier') NOT NULL DEFAULT 'email',
  `invoice_email` varchar(190) DEFAULT NULL,
  `invoice_cc_emails` varchar(500) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account_name` varchar(190) DEFAULT NULL,
  `bank_account_number` varchar(60) DEFAULT NULL,
  `bank_branch` varchar(150) DEFAULT NULL,
  `bank_identifier` varchar(60) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `preferred_language` varchar(40) DEFAULT NULL,
  `timezone` varchar(60) DEFAULT NULL,
  `preferred_contact_method` enum('email','phone','whatsapp','mail') NOT NULL DEFAULT 'email',
  `gst_vat_number` varchar(50) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `registration_number` varchar(80) DEFAULT NULL,
  `tax_id` varchar(80) DEFAULT NULL,
  `tax_id_secondary` varchar(80) DEFAULT NULL,
  `tax_category` enum('regular','composition','unregistered','sez','export','deemed_export','overseas') NOT NULL DEFAULT 'regular',
  `tax_jurisdiction` varchar(120) DEFAULT NULL,
  `state_code` varchar(12) DEFAULT NULL,
  `tax_registration_date` date DEFAULT NULL,
  `tax_exemption_ref` varchar(120) DEFAULT NULL,
  `tax_exemption_upto` date DEFAULT NULL,
  `withholding_tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `export_code` varchar(80) DEFAULT NULL,
  `assigned_manager` int(10) unsigned DEFAULT NULL,
  `sales_representative_id` int(10) unsigned DEFAULT NULL,
  `support_representative_id` int(10) unsigned DEFAULT NULL,
  `sales_region` varchar(120) DEFAULT NULL,
  `distribution_channel` varchar(60) DEFAULT NULL,
  `shipping_condition` varchar(60) DEFAULT NULL,
  `incoterms` varchar(10) DEFAULT NULL,
  `incoterms_location` varchar(150) DEFAULT NULL,
  `delivery_lead_time_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `default_discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `company_size` enum('micro','small','medium','large','enterprise') DEFAULT NULL,
  `number_of_employees` int(10) unsigned DEFAULT NULL,
  `annual_revenue` decimal(18,2) DEFAULT NULL,
  `year_established` smallint(5) unsigned DEFAULT NULL,
  `status` enum('active','inactive','prospect','blocked','archived') NOT NULL DEFAULT 'active',
  `kyc_status` enum('not_started','pending','submitted','verified','rejected') NOT NULL DEFAULT 'not_started',
  `kyc_verified_at` datetime DEFAULT NULL,
  `kyc_verified_by` int(10) unsigned DEFAULT NULL,
  `risk_level` enum('low','medium','high') NOT NULL DEFAULT 'low',
  `marketing_consent` tinyint(1) NOT NULL DEFAULT 0,
  `data_processing_consent` tinyint(1) NOT NULL DEFAULT 0,
  `internal_notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_code` (`client_code`),
  KEY `idx_client_code` (`client_code`),
  KEY `idx_status` (`status`),
  KEY `idx_industry` (`industry`),
  KEY `idx_assigned` (`assigned_manager`),
  KEY `fk_clients_parent` (`parent_client_id`),
  KEY `fk_clients_sales_rep` (`sales_representative_id`),
  KEY `fk_clients_receivable_acct` (`default_receivable_account_id`),
  KEY `idx_clients_type` (`customer_type`),
  KEY `idx_clients_segment` (`segment`),
  KEY `idx_clients_status` (`status`),
  KEY `idx_clients_search` (`search_term`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`assigned_manager`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clients_parent` FOREIGN KEY (`parent_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clients_receivable_acct` FOREIGN KEY (`default_receivable_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clients_sales_rep` FOREIGN KEY (`sales_representative_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients_pre_customer_master_20261012`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients_pre_customer_master_20261012` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `client_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_vat_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `industry` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_manager` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `closing_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `closing_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `financial_year_id` int(10) unsigned NOT NULL,
  `period_id` int(10) unsigned DEFAULT NULL,
  `adjustment_type` enum('accrual','prepayment','provision','correction','depreciation','tax','other') NOT NULL DEFAULT 'other',
  `title` varchar(200) NOT NULL,
  `reason` text NOT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','pending_approval','approved','rejected','posted','reversed') NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_adj_fy` (`financial_year_id`),
  KEY `idx_adj_status` (`status`),
  CONSTRAINT `fk_adj_fy` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_code` varchar(20) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `legal_name` varchar(200) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `search_term` varchar(120) DEFAULT NULL,
  `entity_type` enum('limited_liability','sole_proprietorship','partnership','free_zone_establishment','branch','subsidiary','holding','ngo','government','trust') NOT NULL DEFAULT 'limited_liability',
  `company_category` enum('operating','holding','service_provider','special_purpose','dormant') NOT NULL DEFAULT 'operating',
  `industry` varchar(120) DEFAULT NULL,
  `sub_industry` varchar(120) DEFAULT NULL,
  `group_id` int(10) unsigned DEFAULT NULL,
  `parent_company_id` int(10) unsigned DEFAULT NULL,
  `is_legal_entity` tinyint(1) NOT NULL DEFAULT 1,
  `is_operating_unit` tinyint(1) NOT NULL DEFAULT 1,
  `is_business_unit` tinyint(1) NOT NULL DEFAULT 0,
  `bu_receivables` tinyint(1) NOT NULL DEFAULT 1,
  `bu_payables` tinyint(1) NOT NULL DEFAULT 1,
  `bu_inventory` tinyint(1) NOT NULL DEFAULT 1,
  `bu_purchasing` tinyint(1) NOT NULL DEFAULT 1,
  `bu_sales` tinyint(1) NOT NULL DEFAULT 1,
  `established_date` date DEFAULT NULL,
  `duns_number` varchar(20) DEFAULT NULL,
  `lei_number` varchar(30) DEFAULT NULL,
  `registration_number` varchar(60) DEFAULT NULL,
  `tax_number` varchar(60) DEFAULT NULL,
  `vat_number` varchar(60) DEFAULT NULL,
  `trn_number` varchar(60) DEFAULT NULL,
  `tin_number` varchar(60) DEFAULT NULL,
  `ein_number` varchar(60) DEFAULT NULL,
  `gst_state_code` varchar(12) DEFAULT NULL,
  `free_zone_name` varchar(120) DEFAULT NULL,
  `free_zone_license` varchar(80) DEFAULT NULL,
  `free_zone_expiry` date DEFAULT NULL,
  `license_number` varchar(80) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `regulatory_authority` varchar(150) DEFAULT NULL,
  `share_capital` decimal(18,2) DEFAULT NULL,
  `audit_firm` varchar(190) DEFAULT NULL,
  `statutory_auditor` varchar(190) DEFAULT NULL,
  `financial_framework` enum('IFRS','IFRS_for_SMEs','Local_GAAP','US_GAAP','ISFR','None') NOT NULL DEFAULT 'IFRS',
  `consolidation_method` enum('full','proportionate','equity','none') NOT NULL DEFAULT 'full',
  `is_reporting_entity` tinyint(1) NOT NULL DEFAULT 1,
  `base_currency` char(3) NOT NULL DEFAULT 'AED',
  `country` varchar(60) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `region` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `alternate_phone` varchar(40) DEFAULT NULL,
  `fax` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `support_email` varchar(190) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `contact_person` varchar(190) DEFAULT NULL,
  `contact_designation` varchar(120) DEFAULT NULL,
  `language` varchar(40) NOT NULL DEFAULT 'English',
  `default_receivable_account_id` int(10) unsigned DEFAULT NULL,
  `default_payable_account_id` int(10) unsigned DEFAULT NULL,
  `default_bank_account_id` int(10) unsigned DEFAULT NULL,
  `default_cash_account_id` int(10) unsigned DEFAULT NULL,
  `default_inventory_account_id` int(10) unsigned DEFAULT NULL,
  `default_cogs_account_id` int(10) unsigned DEFAULT NULL,
  `default_sales_account_id` int(10) unsigned DEFAULT NULL,
  `default_purchase_account_id` int(10) unsigned DEFAULT NULL,
  `default_tax_account_id` int(10) unsigned DEFAULT NULL,
  `default_intercompany_account_id` int(10) unsigned DEFAULT NULL,
  `default_retained_earnings_account_id` int(10) unsigned DEFAULT NULL,
  `default_fx_difference_account_id` int(10) unsigned DEFAULT NULL,
  `periods_per_year` tinyint(3) unsigned NOT NULL DEFAULT 12,
  `posting_tolerance` decimal(12,2) NOT NULL DEFAULT 0.01,
  `allow_posting_to_closed` tinyint(1) NOT NULL DEFAULT 0,
  `require_cost_center` tinyint(1) NOT NULL DEFAULT 0,
  `require_project` tinyint(1) NOT NULL DEFAULT 0,
  `enable_multicurrency` tinyint(1) NOT NULL DEFAULT 1,
  `cash_basis_accounting` tinyint(1) NOT NULL DEFAULT 0,
  `allow_negative_stock` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_prefix` varchar(12) NOT NULL DEFAULT 'INV',
  `invoice_padding` smallint(5) unsigned NOT NULL DEFAULT 4,
  `quote_prefix` varchar(12) NOT NULL DEFAULT 'QTN',
  `po_prefix` varchar(12) NOT NULL DEFAULT 'PO',
  `pr_prefix` varchar(12) NOT NULL DEFAULT 'PR',
  `gr_prefix` varchar(12) NOT NULL DEFAULT 'GRN',
  `je_prefix` varchar(12) NOT NULL DEFAULT 'JV',
  `receipt_prefix` varchar(12) NOT NULL DEFAULT 'RCP',
  `vendor_bill_prefix` varchar(12) NOT NULL DEFAULT 'VB',
  `payment_reference_format` varchar(60) DEFAULT NULL,
  `invoice_terms` text DEFAULT NULL,
  `quote_validity_days` smallint(5) unsigned NOT NULL DEFAULT 30,
  `default_payment_terms` varchar(50) DEFAULT NULL,
  `default_shipping_condition` varchar(60) DEFAULT NULL,
  `default_incoterms` varchar(10) DEFAULT NULL,
  `default_price_list` varchar(60) DEFAULT NULL,
  `tax_registration_type` enum('standard','vat_registered','non_registered','exempt','reverse_charge') NOT NULL DEFAULT 'standard',
  `tax_authority` varchar(150) DEFAULT NULL,
  `tax_office_code` varchar(40) DEFAULT NULL,
  `vat_return_frequency` enum('monthly','quarterly','semi_annual','annual','none') NOT NULL DEFAULT 'monthly',
  `vat_return_day` tinyint(3) unsigned NOT NULL DEFAULT 15,
  `withholding_agent` tinyint(1) NOT NULL DEFAULT 1,
  `default_withholding_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `e_invoicing_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `e_invoicing_system` enum('none','zatca_fatoora','gst_e_invoice','fbr_iris','peppol','other') NOT NULL DEFAULT 'none',
  `e_invoicing_stage` enum('phase_1_generation','phase_2_integration','cleared_only') DEFAULT NULL,
  `direct_debit_prefix` varchar(30) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `letterhead_template_id` int(10) unsigned DEFAULT NULL,
  `signature_text` text DEFAULT NULL,
  `document_footer_note` text DEFAULT NULL,
  `theme_color` varchar(9) DEFAULT NULL,
  `default_warehouse_id` int(10) unsigned DEFAULT NULL,
  `default_cost_center_id` int(10) unsigned DEFAULT NULL,
  `working_days` varchar(40) NOT NULL DEFAULT '1,2,3,4,5',
  `weekend_days` varchar(20) NOT NULL DEFAULT '5,6',
  `working_hours` varchar(30) NOT NULL DEFAULT '09:00-18:00',
  `employee_count` int(10) unsigned DEFAULT NULL,
  `annual_revenue` decimal(18,2) DEFAULT NULL,
  `enable_inventory` tinyint(1) NOT NULL DEFAULT 1,
  `enable_projects` tinyint(1) NOT NULL DEFAULT 1,
  `enable_hr` tinyint(1) NOT NULL DEFAULT 1,
  `enable_pos` tinyint(1) NOT NULL DEFAULT 0,
  `enable_manufacturing` tinyint(1) NOT NULL DEFAULT 0,
  `branch_code` varchar(30) DEFAULT NULL,
  `is_branch` tinyint(1) NOT NULL DEFAULT 0,
  `timezone` varchar(50) DEFAULT NULL,
  `financial_year_start` date DEFAULT NULL,
  `status` enum('active','inactive','dormant','archived') NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `fk_co_group` (`group_id`),
  KEY `fk_co_warehouse` (`default_warehouse_id`),
  KEY `idx_co_entity_type` (`entity_type`),
  KEY `idx_co_parent` (`parent_company_id`),
  KEY `idx_co_status` (`status`),
  KEY `idx_co_search` (`search_term`),
  CONSTRAINT `fk_co_group` FOREIGN KEY (`group_id`) REFERENCES `company_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_co_parent` FOREIGN KEY (`parent_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_co_warehouse` FOREIGN KEY (`default_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies_pre_company_master_20261012`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies_pre_company_master_20261012` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `company_code` varchar(20) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `legal_name` varchar(200) DEFAULT NULL,
  `registration_number` varchar(60) DEFAULT NULL,
  `tax_number` varchar(60) DEFAULT NULL,
  `base_currency` char(3) NOT NULL DEFAULT 'AED',
  `country` varchar(60) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `financial_year_start` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `account_label` varchar(120) NOT NULL,
  `bank_name` varchar(150) NOT NULL,
  `branch` varchar(150) DEFAULT NULL,
  `account_name` varchar(190) DEFAULT NULL,
  `account_number` varchar(60) DEFAULT NULL,
  `iban` varchar(40) DEFAULT NULL,
  `swift_bic` varchar(20) DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'USD',
  `account_type` enum('current','savings','margin','escrow','fx','fixed_deposit') NOT NULL DEFAULT 'current',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `gl_account_id` int(10) unsigned DEFAULT NULL,
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cba_company` (`company_id`),
  CONSTRAINT `fk_cba_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) NOT NULL,
  `expense_date` date NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','credit_card') DEFAULT NULL,
  `cash_account_id` int(10) unsigned DEFAULT NULL,
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_type_id` int(10) unsigned DEFAULT NULL,
  `tax_rate_id` int(10) unsigned DEFAULT NULL,
  `tax_group_id` int(10) unsigned DEFAULT NULL,
  `tax_status` enum('taxable','zero_rated','exempt','out_of_scope') NOT NULL DEFAULT 'taxable',
  `taxable_amount` decimal(15,2) DEFAULT 0.00,
  `tax_exemption_id` int(10) unsigned DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','rejected','paid','cancelled') DEFAULT 'draft',
  `rejection_reason` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `payment_journal_id` int(10) unsigned DEFAULT NULL COMMENT 'GL journal for the payment posting (DR Expense Payable, CR Bank/Cash)',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_number` (`expense_number`),
  KEY `vendor_id` (`vendor_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `idx_expense_number` (`expense_number`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `idx_category` (`category_id`),
  KEY `idx_department` (`department_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `fk_company_expenses_payment_journal` (`payment_journal_id`),
  KEY `idx_exp_tax` (`tax_rate_id`),
  CONSTRAINT `company_expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `company_expenses_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_expenses_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_expenses_ibfk_4` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_expenses_ibfk_5` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_expenses_ibfk_6` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_expenses_ibfk_7` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_expenses_ibfk_8` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_company_expenses_payment_journal` FOREIGN KEY (`payment_journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_code` varchar(20) NOT NULL,
  `group_name` varchar(150) NOT NULL,
  `parent_company_id` int(10) unsigned DEFAULT NULL,
  `base_currency` char(3) NOT NULL DEFAULT 'AED',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_code` (`group_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_relationships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `parent_company_id` int(10) unsigned NOT NULL,
  `child_company_id` int(10) unsigned NOT NULL,
  `ownership_percentage` decimal(6,3) NOT NULL DEFAULT 100.000,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `relationship_type` enum('parent','subsidiary','associate','branch','other') NOT NULL DEFAULT 'subsidiary',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cr_group` (`group_id`),
  KEY `idx_cr_parent` (`parent_company_id`),
  KEY `idx_cr_child` (`child_company_id`),
  CONSTRAINT `fk_cr_group` FOREIGN KEY (`group_id`) REFERENCES `company_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consolidation_journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consolidation_journal_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `journal_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cjl_journal` (`journal_id`),
  CONSTRAINT `fk_cjl_journal` FOREIGN KEY (`journal_id`) REFERENCES `consolidation_journals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consolidation_journals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consolidation_journals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` int(10) unsigned NOT NULL,
  `adjustment_type` enum('intercompany_elimination','currency_translation','reclassification','manual','other') NOT NULL,
  `description` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source` varchar(50) NOT NULL DEFAULT 'system',
  `partner_pair_key` varchar(120) DEFAULT NULL COMMENT 'idempotency: one elimination per IC pair per run',
  `status` enum('draft','approved','posted') NOT NULL DEFAULT 'posted',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_elim_once` (`run_id`,`adjustment_type`,`partner_pair_key`),
  CONSTRAINT `fk_cj_run` FOREIGN KEY (`run_id`) REFERENCES `consolidation_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consolidation_run_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consolidation_run_companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `financial_year_id` int(10) unsigned DEFAULT NULL,
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `average_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `included` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_run_co` (`run_id`,`company_id`),
  CONSTRAINT `fk_crc_run` FOREIGN KEY (`run_id`) REFERENCES `consolidation_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consolidation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consolidation_runs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `period_label` varchar(60) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `group_currency` char(3) NOT NULL DEFAULT 'AED',
  `translation_method` enum('closing','average','historical') NOT NULL DEFAULT 'closing',
  `status` enum('draft','in_progress','validated','approved','posted','cancelled') NOT NULL DEFAULT 'draft',
  `total_revenue` decimal(18,2) DEFAULT NULL,
  `total_expenses` decimal(18,2) DEFAULT NULL,
  `net_profit` decimal(18,2) DEFAULT NULL,
  `elimination_total` decimal(18,2) DEFAULT NULL,
  `fx_adjustment_total` decimal(18,2) DEFAULT NULL,
  `nci_total` decimal(18,2) DEFAULT NULL,
  `validation_snapshot` longtext DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `posted_by` int(10) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_run_group` (`group_id`),
  CONSTRAINT `fk_run_group` FOREIGN KEY (`group_id`) REFERENCES `company_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractor_contracts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` int(10) unsigned NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `contract_type` varchar(60) DEFAULT NULL,
  `scope_of_work` text DEFAULT NULL,
  `status` enum('draft','active','completed','terminated') NOT NULL DEFAULT 'draft',
  `is_current` tinyint(1) unsigned DEFAULT NULL,
  `supply_source` enum('direct','agency') NOT NULL DEFAULT 'direct',
  `agency_vendor_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `designation_id` int(10) unsigned DEFAULT NULL,
  `contractual_job_title` varchar(150) DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `manager_employee_id` int(10) unsigned DEFAULT NULL,
  `purchase_order_id` int(10) unsigned DEFAULT NULL,
  `po_reference` varchar(60) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `work_arrangement` varchar(40) DEFAULT NULL,
  `fte_allocation` decimal(5,2) DEFAULT NULL,
  `rate_basis` varchar(40) DEFAULT NULL,
  `rate_amount` decimal(15,2) DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `estimated_value` decimal(15,2) DEFAULT NULL,
  `not_to_exceed_amount` decimal(15,2) DEFAULT NULL,
  `billing_frequency` varchar(40) DEFAULT NULL,
  `payment_terms` varchar(60) DEFAULT NULL,
  `timesheet_required` tinyint(1) NOT NULL DEFAULT 0,
  `security_clearance` varchar(60) DEFAULT NULL,
  `badge_number` varchar(60) DEFAULT NULL,
  `extension_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `extension_limit` smallint(5) unsigned DEFAULT NULL,
  `extension_period_days` smallint(5) unsigned DEFAULT NULL,
  `last_extended_on` date DEFAULT NULL,
  `last_extension_reason` varchar(255) DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `ended_on` date DEFAULT NULL,
  `end_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_number` (`contract_number`),
  UNIQUE KEY `uq_one_current_contract` (`contractor_id`,`is_current`),
  KEY `idx_contract_status_dates` (`status`,`start_date`,`end_date`),
  KEY `idx_contract_agency` (`agency_vendor_id`),
  KEY `idx_contract_company` (`company_id`),
  KEY `idx_contract_project` (`project_id`),
  KEY `idx_contract_po` (`purchase_order_id`),
  KEY `fk_contract_department` (`department_id`),
  KEY `fk_contract_designation` (`designation_id`),
  KEY `fk_contract_manager` (`manager_employee_id`),
  KEY `fk_contract_approved_by` (`approved_by`),
  KEY `fk_contract_created_by` (`created_by`),
  KEY `fk_contract_updated_by` (`updated_by`),
  KEY `fk_contract_currency` (`currency_code`),
  CONSTRAINT `fk_contract_agency` FOREIGN KEY (`agency_vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_contract_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contract_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_designation` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_manager` FOREIGN KEY (`manager_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_purchase_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractor_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` int(10) unsigned NOT NULL,
  `contract_id` int(10) unsigned DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contractor_doc_type` (`contractor_id`,`document_type`),
  KEY `idx_contractor_doc_expiry` (`expires_on`),
  KEY `fk_contractor_doc_contract` (`contract_id`),
  KEY `fk_contractor_doc_user` (`uploaded_by`),
  CONSTRAINT `fk_contractor_doc_contract` FOREIGN KEY (`contract_id`) REFERENCES `contractor_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contractor_doc_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contractor_doc_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_rate_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractor_rate_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` int(10) unsigned NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `rate_basis` varchar(40) DEFAULT NULL,
  `rate_amount` decimal(15,2) NOT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_valid_from` (`contract_id`,`valid_from`),
  KEY `idx_rate_contract` (`contract_id`,`valid_from`),
  KEY `fk_ctr_rate_created_by` (`created_by`),
  KEY `fk_ctr_rate_currency` (`currency_code`),
  CONSTRAINT `fk_ctr_rate_contract` FOREIGN KEY (`contract_id`) REFERENCES `contractor_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctr_rate_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ctr_rate_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contractor_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `work_location` varchar(100) DEFAULT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `contractor_category` varchar(60) DEFAULT NULL,
  `specialisation` varchar(200) DEFAULT NULL,
  `emirates_id_number` varchar(20) DEFAULT NULL,
  `emirates_id_expiry` date DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `visa_number` varchar(50) DEFAULT NULL,
  `visa_type` varchar(30) DEFAULT NULL,
  `visa_expiry` date DEFAULT NULL,
  `work_permit_number` varchar(50) DEFAULT NULL,
  `work_permit_expiry` date DEFAULT NULL,
  `labor_card_number` varchar(50) DEFAULT NULL,
  `labor_card_expiry` date DEFAULT NULL,
  `trn_number` varchar(40) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account_number` varchar(60) DEFAULT NULL,
  `account_holder_name` varchar(150) DEFAULT NULL,
  `status` enum('active','suspended','terminated') NOT NULL DEFAULT 'active',
  `user_id` int(10) unsigned DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `termination_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contractor_code` (`contractor_code`),
  UNIQUE KEY `uq_contractor_email` (`email`),
  KEY `idx_contractor_name` (`first_name`,`last_name`),
  KEY `idx_contractor_status` (`status`),
  KEY `idx_contractor_category` (`contractor_category`),
  KEY `idx_contractor_emirates_id` (`emirates_id_number`),
  KEY `idx_contractor_passport` (`passport_number`),
  KEY `idx_contractor_deleted` (`deleted_at`),
  KEY `fk_contractor_created_by` (`created_by`),
  KEY `fk_contractor_updated_by` (`updated_by`),
  KEY `idx_contractor_user` (`user_id`),
  CONSTRAINT `fk_contractor_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contractor_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contractor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cost_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cost_centers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `cost_center_code` varchar(30) NOT NULL,
  `cost_center_name` varchar(150) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cc_code_company` (`cost_center_code`,`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credit_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `credit_note_number` varchar(50) NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `credit_note_date` date NOT NULL,
  `credit_note_type` enum('price_correction','return','discount_adjustment','cancellation') NOT NULL DEFAULT 'price_correction',
  `reason` text DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `tax_type_id` int(10) unsigned DEFAULT NULL,
  `tax_rate_id` int(10) unsigned DEFAULT NULL,
  `tax_group_id` int(10) unsigned DEFAULT NULL,
  `tax_status` enum('taxable','zero_rated','exempt','out_of_scope') NOT NULL DEFAULT 'taxable',
  `taxable_amount` decimal(15,2) DEFAULT 0.00,
  `tax_exemption_id` int(10) unsigned DEFAULT NULL,
  `status` enum('draft','issued','applied','cancelled') DEFAULT 'draft',
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `credit_note_number` (`credit_note_number`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_credit_note_number` (`credit_note_number`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_cn_tax` (`tax_rate_id`),
  CONSTRAINT `credit_notes_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `credit_notes_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `credit_notes_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `credit_notes_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `code` char(3) NOT NULL,
  `name` varchar(120) NOT NULL,
  `symbol` varchar(12) NOT NULL,
  `decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`code`),
  KEY `idx_cur_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currency_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `currency_code` char(3) NOT NULL,
  `rate_to_base` decimal(18,8) NOT NULL,
  `effective_date` date NOT NULL,
  `source` varchar(60) NOT NULL DEFAULT 'manual',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cur_effective` (`currency_code`,`effective_date`),
  CONSTRAINT `fk_rate_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `address_type` enum('billing','shipping','remittance','other') NOT NULL DEFAULT 'shipping',
  `label` varchar(120) NOT NULL,
  `contact_name` varchar(190) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `tax_number` varchar(80) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `valid_from` date DEFAULT NULL,
  `valid_upto` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_addr_client` (`client_id`),
  KEY `idx_addr_type` (`address_type`),
  CONSTRAINT `fk_addr_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('draft','approved','sent','partially_paid','paid','overdue','cancelled') DEFAULT 'draft',
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `project_id` (`project_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `customer_invoices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `customer_invoices_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_invoices_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_invoices_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','credit_card','online') NOT NULL DEFAULT 'bank_transfer',
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `cash_account_id` int(10) unsigned DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unallocated_amount` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_receipt_number` (`receipt_number`),
  KEY `idx_client` (`client_id`),
  KEY `idx_receipt_date` (`receipt_date`),
  CONSTRAINT `customer_receipts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `customer_receipts_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_ibfk_3` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_ibfk_4` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `deal_title` varchar(200) NOT NULL,
  `lead_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `deal_value` decimal(15,2) DEFAULT NULL,
  `stage` enum('inquiry','qualified','proposal','negotiation','closed_won','closed_lost') DEFAULT 'inquiry',
  `expected_closing_date` date DEFAULT NULL,
  `probability` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stage` (`stage`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_expected_date` (`expected_closing_date`),
  KEY `lead_id` (`lead_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `deals_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deals_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deals_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `debit_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debit_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `debit_note_number` varchar(50) NOT NULL,
  `bill_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `debit_note_date` date NOT NULL,
  `debit_note_type` enum('price_correction','return','discount_adjustment','cancellation') NOT NULL DEFAULT 'price_correction',
  `reason` text DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `tax_type_id` int(10) unsigned DEFAULT NULL,
  `tax_rate_id` int(10) unsigned DEFAULT NULL,
  `tax_group_id` int(10) unsigned DEFAULT NULL,
  `tax_status` enum('taxable','zero_rated','exempt','out_of_scope') NOT NULL DEFAULT 'taxable',
  `taxable_amount` decimal(15,2) DEFAULT 0.00,
  `tax_exemption_id` int(10) unsigned DEFAULT NULL,
  `status` enum('draft','issued','applied','cancelled') DEFAULT 'draft',
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `debit_note_number` (`debit_note_number`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_debit_note_number` (`debit_note_number`),
  KEY `idx_bill` (`bill_id`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dn_tax` (`tax_rate_id`),
  CONSTRAINT `debit_notes_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `debit_notes_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `debit_notes_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `debit_notes_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code` (`department_code`),
  KEY `idx_department_name` (`department_name`),
  KEY `idx_department_code` (`department_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `depreciation_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `depreciation_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `method_name` varchar(100) NOT NULL,
  `method_code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `method_code` (`method_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `designations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `designation_name` varchar(100) NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_designation_name` (`designation_name`),
  KEY `idx_department_id` (`department_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `designations_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_sequences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `doc_type` varchar(40) NOT NULL,
  `period_key` varchar(20) NOT NULL DEFAULT '',
  `prefix` varchar(20) NOT NULL,
  `padding` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `date_format` varchar(20) NOT NULL DEFAULT '',
  `format` varchar(80) DEFAULT NULL,
  `next_number` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_docseq_period` (`company_id`,`doc_type`,`period_key`),
  CONSTRAINT `fk_docseq_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=370 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_employee_type` (`employee_id`,`document_type`),
  CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_expense_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_expense_claims` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `claim_number` varchar(50) NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','pending_manager','pending_finance','approved','rejected','reimbursed','cancelled') DEFAULT 'draft',
  `rejection_reason` text DEFAULT NULL,
  `manager_id` int(10) unsigned DEFAULT NULL COMMENT 'Manager who approves level 1',
  `manager_approved_at` timestamp NULL DEFAULT NULL,
  `finance_approved_at` timestamp NULL DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `project_id` (`project_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_claim_number` (`claim_number`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expense_date` (`expense_date`),
  CONSTRAINT `employee_expense_claims_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `employee_expense_claims_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `employee_expense_claims_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_expense_claims_ibfk_4` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_expense_claims_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT 'single',
  `nationality` varchar(100) DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `visa_type` varchar(30) DEFAULT NULL,
  `visa_number` varchar(50) DEFAULT NULL,
  `visa_expiry` date DEFAULT NULL,
  `emirates_id_number` varchar(20) DEFAULT NULL,
  `emirates_id_expiry` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `designation_id` int(10) unsigned DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `contract_number` varchar(50) DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contract','intern') DEFAULT 'full_time',
  `reporting_manager_id` int(10) unsigned DEFAULT NULL,
  `work_location` varchar(255) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','terminated','resigned') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_employee_code` (`employee_code`),
  KEY `idx_department_id` (`department_id`),
  KEY `idx_designation_id` (`designation_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  KEY `reporting_manager_id` (`reporting_manager_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_passport_number` (`passport_number`),
  KEY `idx_emirates_id_number` (`emirates_id_number`),
  KEY `idx_employee_contract_number` (`contract_number`),
  KEY `idx_employee_contract_end` (`contract_end_date`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exchange_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_currency` char(3) NOT NULL,
  `to_currency` char(3) NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `rate_type` enum('closing','average','historical','spot') NOT NULL DEFAULT 'closing',
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rate` (`from_currency`,`to_currency`,`rate_type`,`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_approval_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_approval_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_type` enum('company_expense','employee_claim') NOT NULL,
  `expense_id` int(10) unsigned NOT NULL,
  `approval_level` varchar(50) NOT NULL COMMENT 'e.g. manager_approval, finance_approval, final_approval',
  `action` enum('approved','rejected','submitted','cancelled') NOT NULL,
  `comments` text DEFAULT NULL,
  `action_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expense` (`expense_type`,`expense_id`),
  KEY `idx_expense_id` (`expense_id`),
  KEY `idx_action_by` (`action_by`),
  CONSTRAINT `expense_approval_history_ibfk_1` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_type` enum('company_expense','employee_claim') NOT NULL,
  `expense_id` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(10) unsigned DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_expense_type` (`expense_type`,`expense_id`),
  KEY `idx_expense_id` (`expense_id`),
  CONSTRAINT `expense_attachments_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `default_expense_account_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to chart_of_accounts — default GL account to DR',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_categories_name` (`name`),
  KEY `default_expense_account_id` (`default_expense_account_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_category_active` (`is_active`),
  KEY `idx_category_name` (`name`),
  CONSTRAINT `expense_categories_ibfk_1` FOREIGN KEY (`default_expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expense_categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_categories_old`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories_old` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_type` enum('company_expense','employee_claim') NOT NULL,
  `expense_id` int(10) unsigned NOT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit_price` decimal(15,4) DEFAULT 0.0000,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_expense_type` (`expense_type`,`expense_id`),
  KEY `idx_expense_id` (`expense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_reimbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_reimbursements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reimbursement_number` varchar(50) NOT NULL,
  `claim_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque') NOT NULL DEFAULT 'bank_transfer',
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `cash_account_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('draft','paid','cancelled') DEFAULT 'draft',
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reimbursement_number` (`reimbursement_number`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_reimbursement_number` (`reimbursement_number`),
  KEY `idx_claim` (`claim_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `expense_reimbursements_ibfk_1` FOREIGN KEY (`claim_id`) REFERENCES `employee_expense_claims` (`id`),
  CONSTRAINT `expense_reimbursements_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `expense_reimbursements_ibfk_3` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expense_reimbursements_ibfk_4` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expense_reimbursements_ibfk_5` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expense_reimbursements_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_code` varchar(50) NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','upi','credit_card') DEFAULT 'bank_transfer',
  `vendor_name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `receipt_file` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_code` (`expense_code`),
  KEY `idx_expense_code` (`expense_code`),
  KEY `idx_category` (`category_id`),
  KEY `idx_date` (`expense_date`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories_old` (`id`),
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `finance_goods_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finance_goods_receipt_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grn_id` int(10) unsigned NOT NULL,
  `po_item_id` int(10) unsigned NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `quantity_received` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(15,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_grn` (`grn_id`),
  KEY `idx_po_item` (`po_item_id`),
  CONSTRAINT `finance_goods_receipt_items_ibfk_1` FOREIGN KEY (`grn_id`) REFERENCES `goods_receipt_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_goods_receipt_items_ibfk_2` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `finance_group_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finance_group_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fga_action` (`action`),
  KEY `idx_fga_company` (`company_id`),
  KEY `idx_fga_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=365 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_years` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `year_name` varchar(50) NOT NULL,
  `year_code` varchar(20) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','closing','closed','archived') NOT NULL DEFAULT 'closed',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fy_company_name` (`company_id`,`year_name`),
  UNIQUE KEY `uq_fy_company_range` (`company_id`,`start_date`,`end_date`),
  KEY `idx_fy_status` (`status`),
  KEY `idx_fy_dates` (`start_date`,`end_date`),
  KEY `idx_fy_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fixed_asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_asset_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_category_id` int(10) unsigned NOT NULL,
  `depreciation_method_id` int(10) unsigned NOT NULL,
  `useful_life_months` int(11) NOT NULL,
  `residual_value_percentage` decimal(5,2) DEFAULT 0.00,
  `asset_gl_account_id` int(10) unsigned NOT NULL,
  `accumulated_depreciation_gl_account_id` int(10) unsigned NOT NULL,
  `depreciation_expense_gl_account_id` int(10) unsigned NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_category_id` (`asset_category_id`),
  KEY `depreciation_method_id` (`depreciation_method_id`),
  KEY `asset_gl_account_id` (`asset_gl_account_id`),
  KEY `accumulated_depreciation_gl_account_id` (`accumulated_depreciation_gl_account_id`),
  KEY `depreciation_expense_gl_account_id` (`depreciation_expense_gl_account_id`),
  CONSTRAINT `fixed_asset_categories_ibfk_1` FOREIGN KEY (`asset_category_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `fixed_asset_categories_ibfk_2` FOREIGN KEY (`depreciation_method_id`) REFERENCES `depreciation_methods` (`id`),
  CONSTRAINT `fixed_asset_categories_ibfk_3` FOREIGN KEY (`asset_gl_account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `fixed_asset_categories_ibfk_4` FOREIGN KEY (`accumulated_depreciation_gl_account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `fixed_asset_categories_ibfk_5` FOREIGN KEY (`depreciation_expense_gl_account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fixed_asset_financials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_asset_financials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) unsigned NOT NULL,
  `asset_category_id` int(10) unsigned NOT NULL,
  `capitalization_date` date NOT NULL,
  `in_service_date` date DEFAULT NULL,
  `acquisition_cost` decimal(15,2) NOT NULL,
  `capitalized_cost` decimal(15,2) NOT NULL,
  `residual_value` decimal(15,2) NOT NULL,
  `useful_life_months` int(11) NOT NULL,
  `depreciation_method_id` int(10) unsigned NOT NULL,
  `depreciation_start_date` date NOT NULL,
  `accumulated_depreciation` decimal(15,2) DEFAULT 0.00,
  `impairment_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_book_value` decimal(15,2) GENERATED ALWAYS AS (case when `capitalized_cost` - `accumulated_depreciation` > `residual_value` then `capitalized_cost` - `accumulated_depreciation` else `residual_value` end) STORED,
  `status` enum('active','disposed','impaired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_id` (`asset_id`),
  KEY `asset_category_id` (`asset_category_id`),
  KEY `depreciation_method_id` (`depreciation_method_id`),
  CONSTRAINT `fixed_asset_financials_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fixed_asset_financials_ibfk_2` FOREIGN KEY (`asset_category_id`) REFERENCES `fixed_asset_categories` (`id`),
  CONSTRAINT `fixed_asset_financials_ibfk_3` FOREIGN KEY (`depreciation_method_id`) REFERENCES `depreciation_methods` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fixed_asset_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_asset_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `financial_asset_id` int(10) unsigned NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `financial_asset_id` (`financial_asset_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fixed_asset_history_ibfk_1` FOREIGN KEY (`financial_asset_id`) REFERENCES `fixed_asset_financials` (`id`),
  CONSTRAINT `fixed_asset_history_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `general_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `posting_date` date NOT NULL,
  `journal_entry_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `voucher_type` varchar(50) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `running_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `financial_year_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `financial_year_id` (`financial_year_id`),
  KEY `idx_account_fy` (`account_id`,`financial_year_id`),
  KEY `idx_posting_date` (`posting_date`),
  KEY `idx_journal_entry` (`journal_entry_id`),
  KEY `idx_gl_company` (`company_id`),
  CONSTRAINT `general_ledger_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`),
  CONSTRAINT `general_ledger_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `general_ledger_ibfk_3` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1299 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned NOT NULL,
  `gr_item_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(80) NOT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `expiry_date` date DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grb_gr` (`gr_id`),
  KEY `idx_grb_item` (`gr_item_id`),
  KEY `idx_grb_batch` (`batch_number`),
  CONSTRAINT `fk_grb_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grb_item` FOREIGN KEY (`gr_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_damages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_damages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned NOT NULL,
  `gr_item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `damage_reason` text DEFAULT NULL,
  `damage_type` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photos_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`photos_documents`)),
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grd_gr` (`gr_id`),
  KEY `idx_grd_item` (`gr_item_id`),
  CONSTRAINT `fk_grd_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grd_item` FOREIGN KEY (`gr_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_inspections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned NOT NULL,
  `gr_item_id` int(10) unsigned DEFAULT NULL,
  `inspector_id` int(10) unsigned NOT NULL,
  `inspection_status` enum('pending','passed','partially_passed','failed','not_required') NOT NULL DEFAULT 'pending',
  `accepted_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `rejected_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `inspection_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `supporting_document` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gri_gr` (`gr_id`),
  KEY `idx_gri_item` (`gr_item_id`),
  CONSTRAINT `fk_gri_ins_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gri_ins_item` FOREIGN KEY (`gr_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned NOT NULL,
  `po_id` int(10) unsigned NOT NULL,
  `po_item_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `item_type` enum('stock','non_stock','service','asset','expense') NOT NULL DEFAULT 'non_stock',
  `ordered_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `previously_received_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `remaining_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `received_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `accepted_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `rejected_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `damaged_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `short_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `backorder_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `serial_number` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `damage_reason` text DEFAULT NULL,
  `damage_type` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gri_gr` (`gr_id`),
  KEY `idx_gri_po` (`po_id`),
  KEY `idx_gri_poitem` (`po_item_id`),
  KEY `idx_gri_item` (`item_id`),
  KEY `idx_gri_batch` (`batch_number`),
  KEY `idx_gri_serial` (`serial_number`),
  CONSTRAINT `fk_gri_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gri_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `fk_gri_poitem` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grn_number` varchar(50) NOT NULL,
  `po_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `delivery_note_number` varchar(100) DEFAULT NULL,
  `status` enum('draft','completed','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `grn_number` (`grn_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_grn_number` (`grn_number`),
  KEY `idx_po` (`po_id`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `goods_receipt_notes_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `goods_receipt_notes_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `goods_receipt_notes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_rejections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_rejections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned NOT NULL,
  `gr_item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `reason` text NOT NULL,
  `inspection_result` enum('pending','passed','partially_passed','failed','not_required') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `supporting_document` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grr_gr` (`gr_id`),
  KEY `idx_grr_item` (`gr_item_id`),
  CONSTRAINT `fk_grr_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grr_item` FOREIGN KEY (`gr_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_serials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned NOT NULL,
  `gr_item_id` int(10) unsigned NOT NULL,
  `serial_number` varchar(80) NOT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('received','accepted','rejected','damaged') NOT NULL DEFAULT 'received',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grs_gr` (`gr_id`),
  KEY `idx_grs_item` (`gr_item_id`),
  KEY `idx_grs_serial` (`serial_number`),
  CONSTRAINT `fk_grs_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grs_item` FOREIGN KEY (`gr_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_number` varchar(40) NOT NULL,
  `po_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `receipt_date` date NOT NULL,
  `delivery_note_number` varchar(80) DEFAULT NULL,
  `vendor_delivery_date` date DEFAULT NULL,
  `received_by` int(10) unsigned NOT NULL,
  `inspection_required` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `status` enum('draft','pending_inspection','partially_accepted','accepted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `received_quantity_total` decimal(18,3) NOT NULL DEFAULT 0.000,
  `accepted_quantity_total` decimal(18,3) NOT NULL DEFAULT 0.000,
  `rejected_quantity_total` decimal(18,3) NOT NULL DEFAULT 0.000,
  `damaged_quantity_total` decimal(18,3) NOT NULL DEFAULT 0.000,
  `short_quantity_total` decimal(18,3) NOT NULL DEFAULT 0.000,
  `backorder_quantity_total` decimal(18,3) NOT NULL DEFAULT 0.000,
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `inspection_status` enum('pending','passed','partially_passed','failed','not_required') NOT NULL DEFAULT 'not_required',
  `inspection_date` datetime DEFAULT NULL,
  `inspected_by` int(10) unsigned DEFAULT NULL,
  `inventory_movement_id` int(10) unsigned DEFAULT NULL COMMENT 'link to inventory movement if posted',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gr_number` (`gr_number`),
  KEY `idx_gr_po` (`po_id`),
  KEY `idx_gr_vendor` (`vendor_id`),
  KEY `idx_gr_company` (`company_id`),
  KEY `idx_gr_warehouse` (`warehouse_id`),
  KEY `idx_gr_status` (`status`),
  KEY `idx_gr_date` (`receipt_date`),
  KEY `fk_gr_received_by` (`received_by`),
  CONSTRAINT `fk_gr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_gr_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `fk_gr_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_gr_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `holiday_name` varchar(200) NOT NULL,
  `holiday_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `holiday_type` enum('national','festival','optional') DEFAULT 'national',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`holiday_date`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `intercompany_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intercompany_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `ic_type` enum('receivable','payable','revenue','expense','loan_given','loan_taken','interest_income','interest_expense','other') NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ic_acct` (`company_id`,`ic_type`)
) ENGINE=InnoDB AUTO_INCREMENT=197 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `intercompany_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intercompany_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(40) NOT NULL,
  `source_company_id` int(10) unsigned NOT NULL,
  `target_company_id` int(10) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('sale','purchase','service','expense','loan','interest','transfer','other') NOT NULL DEFAULT 'other',
  `reference` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AED',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `match_status` enum('unmatched','partially_matched','matched','exception','resolved') NOT NULL DEFAULT 'unmatched',
  `counterpart_id` int(10) unsigned DEFAULT NULL COMMENT 'linked opposite-side transaction',
  `root_id` int(10) unsigned DEFAULT NULL COMMENT 'shared pair anchor — identical on both sides',
  `source_journal_id` int(10) unsigned DEFAULT NULL,
  `target_journal_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  UNIQUE KEY `uk_counterpart_once` (`counterpart_id`,`source_company_id`),
  KEY `idx_ict_source` (`source_company_id`),
  KEY `idx_ict_target` (`target_company_id`),
  KEY `idx_ict_status` (`match_status`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_adjustment_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_adjustment_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_id` int(10) unsigned NOT NULL,
  `doc_label` varchar(100) NOT NULL DEFAULT 'Supporting Document',
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iaa_adj` (`adjustment_id`),
  CONSTRAINT `fk_iaa_adj` FOREIGN KEY (`adjustment_id`) REFERENCES `inventory_adjustments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(40) NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `serial_number` varchar(80) DEFAULT NULL,
  `current_qty` decimal(18,3) NOT NULL DEFAULT 0.000,
  `counted_qty` decimal(18,3) NOT NULL DEFAULT 0.000,
  `variance_qty` decimal(18,3) NOT NULL DEFAULT 0.000,
  `adjustment_type` enum('gain','loss') NOT NULL,
  `unit_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reason` varchar(200) NOT NULL,
  `notes` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','rejected','finalised','cancelled') NOT NULL DEFAULT 'draft',
  `movement_id` bigint(20) unsigned DEFAULT NULL,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `finalised_at` datetime DEFAULT NULL,
  `finalised_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `adjustment_number` (`adjustment_number`),
  KEY `idx_ia_company` (`company_id`),
  KEY `idx_ia_warehouse` (`warehouse_id`),
  KEY `idx_ia_item` (`item_id`),
  KEY `idx_ia_status` (`status`),
  KEY `fk_ia_loc` (`location_id`),
  KEY `fk_ia_user` (`created_by`),
  CONSTRAINT `fk_ia_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ia_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_ia_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ia_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_ia_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_name` varchar(200) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `uom_id` int(10) unsigned DEFAULT NULL,
  `costing_method` enum('FIFO','LIFO','AVG','STD','MA') DEFAULT 'AVG',
  `reorder_point` int(10) unsigned DEFAULT 0,
  `is_serial_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_batch_required` tinyint(1) NOT NULL DEFAULT 0,
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `min_quantity` int(10) unsigned DEFAULT 5,
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `idx_item_code` (`item_code`),
  KEY `idx_category` (`category`),
  KEY `vendor_id` (`vendor_id`),
  KEY `idx_inv_wh` (`warehouse_id`),
  KEY `idx_inv_loc` (`location_id`),
  KEY `idx_inv_uom` (`uom_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_type` (`movement_type`),
  KEY `idx_date` (`created_at`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_reconciliation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_reconciliation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_id` bigint(20) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `ledger_qty` decimal(18,3) NOT NULL,
  `physical_qty` decimal(18,3) DEFAULT NULL,
  `reserved_qty` decimal(18,3) NOT NULL DEFAULT 0.000,
  `available_qty` decimal(18,3) NOT NULL DEFAULT 0.000,
  `ledger_value` decimal(18,4) NOT NULL,
  `physical_value` decimal(18,4) DEFAULT NULL,
  `unit_cost` decimal(18,4) NOT NULL,
  `discrepancy_qty` decimal(18,3) DEFAULT 0.000,
  `discrepancy_value` decimal(18,4) DEFAULT 0.0000,
  `discrepancy_reason` enum('none','count_error','damage','theft','data_error','other') DEFAULT 'none',
  `adjustment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'linked adjustment if reconciled',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `location_id` (`location_id`),
  KEY `idx_recon_item` (`reconciliation_id`,`item_id`,`warehouse_id`),
  CONSTRAINT `inventory_reconciliation_items_ibfk_1` FOREIGN KEY (`reconciliation_id`) REFERENCES `inventory_reconciliations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reconciliation_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reconciliation_items_ibfk_3` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reconciliation_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_reconciliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `reconciliation_date` date NOT NULL,
  `status` enum('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
  `total_items` int(10) unsigned DEFAULT 0,
  `matched_items` int(10) unsigned DEFAULT 0,
  `discrepancy_items` int(10) unsigned DEFAULT 0,
  `total_ledger_value` decimal(18,4) DEFAULT 0.0000,
  `total_physical_value` decimal(18,4) DEFAULT 0.0000,
  `difference_value` decimal(18,4) DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_by` int(10) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `created_by` (`created_by`),
  KEY `completed_by` (`completed_by`),
  KEY `idx_recon_company_date` (`company_id`,`reconciliation_date`),
  KEY `idx_recon_status` (`status`),
  CONSTRAINT `inventory_reconciliations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reconciliations_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_reconciliations_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_reconciliations_ibfk_4` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_reservations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(18,3) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g. sales_order, work_order',
  `reference_id` int(10) unsigned DEFAULT NULL,
  `reference_number` varchar(80) DEFAULT NULL,
  `status` enum('reserved','released','consumed','cancelled') NOT NULL DEFAULT 'reserved',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_by` int(10) unsigned DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `location_id` (`location_id`),
  KEY `created_by` (`created_by`),
  KEY `released_by` (`released_by`),
  KEY `idx_res_item_wh` (`item_id`,`warehouse_id`,`location_id`),
  KEY `idx_res_reference` (`reference_type`,`reference_id`),
  KEY `idx_res_status` (`status`),
  KEY `idx_res_company` (`company_id`),
  CONSTRAINT `inventory_reservations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reservations_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reservations_ibfk_3` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_reservations_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_reservations_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_reservations_ibfk_6` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_sequences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `seq_type` varchar(30) NOT NULL,
  `seq_year` smallint(5) unsigned NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inv_seq` (`seq_type`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `allow_negative_stock` tinyint(1) NOT NULL DEFAULT 0,
  `default_costing_method` enum('FIFO','LIFO','AVG','STD','MA') NOT NULL DEFAULT 'AVG',
  `default_warehouse_id` int(10) unsigned DEFAULT NULL,
  `default_location_id` int(10) unsigned DEFAULT NULL,
  `inventory_account_id` int(10) unsigned DEFAULT NULL COMMENT 'GL account for inventory asset',
  `cogs_account_id` int(10) unsigned DEFAULT NULL COMMENT 'GL account for cost of goods sold',
  `adjustment_account_id` int(10) unsigned DEFAULT NULL COMMENT 'GL account for inventory adjustments',
  `stock_movement_prefix` varchar(20) NOT NULL DEFAULT 'SM',
  `require_adj_approval` tinyint(1) NOT NULL DEFAULT 0,
  `require_transfer_approval` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_inv_settings_inventory_account` (`inventory_account_id`),
  KEY `fk_inv_settings_cogs_account` (`cogs_account_id`),
  KEY `fk_inv_settings_adjustment_account` (`adjustment_account_id`),
  CONSTRAINT `fk_inv_settings_adjustment_account` FOREIGN KEY (`adjustment_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_settings_cogs_account` FOREIGN KEY (`cogs_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_settings_inventory_account` FOREIGN KEY (`inventory_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stock_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_stock_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `qty_on_hand` decimal(18,3) NOT NULL DEFAULT 0.000,
  `qty_reserved` decimal(18,3) NOT NULL DEFAULT 0.000,
  `avg_unit_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `stock_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `last_movement_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bal` (`company_id`,`item_id`,`warehouse_id`,`location_id`),
  KEY `idx_bal_wh` (`warehouse_id`),
  KEY `idx_bal_item` (`item_id`),
  KEY `fk_bal_loc` (`location_id`),
  KEY `idx_bal_company_item` (`company_id`,`item_id`),
  KEY `idx_bal_value` (`stock_value`),
  KEY `idx_bal_item_wh` (`item_id`,`warehouse_id`),
  CONSTRAINT `fk_bal_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_bal_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_bal_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bal_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_stock_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `serial_number` varchar(80) DEFAULT NULL,
  `movement_type` enum('purchase_receipt','sales_issue','adjustment_in','adjustment_out','transfer_out','transfer_in','opening_stock','reversal') NOT NULL,
  `qty_in` decimal(18,3) NOT NULL DEFAULT 0.000,
  `qty_out` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `cost_before` decimal(18,4) DEFAULT NULL COMMENT 'avg unit cost before this movement',
  `cost_after` decimal(18,4) DEFAULT NULL COMMENT 'avg unit cost after this movement',
  `value_before` decimal(18,4) DEFAULT NULL COMMENT 'total stock value before this movement',
  `value_after` decimal(18,4) DEFAULT NULL COMMENT 'total stock value after this movement',
  `total_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `reference_number` varchar(80) DEFAULT NULL,
  `movement_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','posted','cancelled','reversed') NOT NULL DEFAULT 'posted',
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_isl_item` (`item_id`),
  KEY `idx_isl_wh` (`warehouse_id`),
  KEY `idx_isl_company` (`company_id`),
  KEY `idx_isl_type` (`movement_type`),
  KEY `idx_isl_ref` (`reference_type`,`reference_id`),
  KEY `idx_isl_datetime` (`movement_datetime`),
  KEY `fk_isl_loc` (`location_id`),
  KEY `fk_isl_user` (`created_by`),
  KEY `idx_isl_company_item_datetime` (`company_id`,`item_id`,`movement_datetime`),
  KEY `idx_isl_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_isl_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_isl_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_isl_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_isl_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_isl_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_stock_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(40) NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `source_warehouse_id` int(10) unsigned NOT NULL,
  `source_location_id` int(10) unsigned DEFAULT NULL,
  `dest_warehouse_id` int(10) unsigned NOT NULL,
  `dest_location_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `transfer_reason` varchar(200) DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','posted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `movement_out_id` bigint(20) unsigned DEFAULT NULL,
  `movement_in_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `posted_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `idx_ist_company` (`company_id`),
  KEY `idx_ist_item` (`item_id`),
  KEY `idx_ist_source` (`source_warehouse_id`),
  KEY `idx_ist_dest` (`dest_warehouse_id`),
  KEY `idx_ist_status` (`status`),
  KEY `fk_ist_sloc` (`source_location_id`),
  KEY `fk_ist_dloc` (`dest_location_id`),
  KEY `fk_ist_user` (`created_by`),
  CONSTRAINT `fk_ist_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ist_dloc` FOREIGN KEY (`dest_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ist_dwh` FOREIGN KEY (`dest_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_ist_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_ist_sloc` FOREIGN KEY (`source_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ist_swh` FOREIGN KEY (`source_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_ist_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `tax_type_id` int(10) unsigned DEFAULT NULL,
  `tax_rate_id` int(10) unsigned DEFAULT NULL,
  `tax_group_id` int(10) unsigned DEFAULT NULL,
  `tax_status` enum('taxable','zero_rated','exempt','out_of_scope') NOT NULL DEFAULT 'taxable',
  `taxable_amount` decimal(15,2) DEFAULT 0.00,
  `tax_exemption_id` int(10) unsigned DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `line_total` decimal(15,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `total` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_item_tax` (`tax_rate_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `change_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_invoice` (`invoice_id`),
  CONSTRAINT `invoice_status_history_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','partially_paid','paid','overdue','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`invoice_date`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(50) NOT NULL,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `voucher_type_id` int(10) unsigned NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','posted','reversed') NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned NOT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_no` (`voucher_no`),
  KEY `voucher_type_id` (`voucher_type_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_voucher_date` (`voucher_date`),
  KEY `idx_status` (`status`),
  KEY `idx_voucher_no` (`voucher_no`),
  KEY `idx_je_company` (`company_id`),
  CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`voucher_type_id`) REFERENCES `voucher_types` (`id`),
  CONSTRAINT `journal_entries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=898 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entry_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entry_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `debit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_journal_entry` (`journal_entry_id`),
  KEY `idx_account` (`account_id`),
  CONSTRAINT `journal_entry_details_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entry_details_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1719 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_base_articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `created_by` int(10) unsigned NOT NULL,
  `views` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `knowledge_base_articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `knowledge_base_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_base_articles_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_base_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_followups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `followup_date` date NOT NULL,
  `followup_type` enum('call','email','meeting','whatsapp','demo') DEFAULT 'call',
  `notes` text DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_date` (`followup_date`),
  CONSTRAINT `lead_followups_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_followups_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lead_code` varchar(50) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `status` enum('new','contacted','qualified','proposal_sent','negotiation','won','lost') DEFAULT 'new',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `expected_value` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_code` (`lead_code`),
  KEY `idx_lead_code` (`lead_code`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_assigned` (`assigned_to`),
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_balances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `leave_type_id` int(10) unsigned NOT NULL,
  `year` year(4) NOT NULL,
  `total_days` decimal(5,1) DEFAULT 0.0,
  `used_days` decimal(5,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_balance` (`employee_id`,`leave_type_id`,`year`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `leave_type_id` int(10) unsigned NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `total_days` decimal(5,1) NOT NULL,
  `is_lop` tinyint(1) DEFAULT 0,
  `half_day` tinyint(1) DEFAULT 0,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_leave_type` (`leave_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`from_date`,`to_date`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(100) NOT NULL,
  `leave_code` varchar(20) NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 0,
  `carry_forward` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_code` (`leave_code`),
  KEY `idx_leave_code` (`leave_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `license_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `license_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `license_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `assigned_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_license` (`license_id`),
  KEY `idx_employee` (`employee_id`),
  CONSTRAINT `license_assignments_ibfk_1` FOREIGN KEY (`license_id`) REFERENCES `software_licenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `license_assignments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(10) unsigned NOT NULL,
  `location_code` varchar(30) NOT NULL,
  `location_name` varchar(150) NOT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `rack` varchar(50) DEFAULT NULL,
  `shelf` varchar(50) DEFAULT NULL,
  `bin` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_loc_code_warehouse` (`location_code`,`warehouse_id`),
  KEY `idx_loc_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_loc_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lookup_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_types` (
  `type_key` varchar(60) NOT NULL,
  `label` varchar(160) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `group_name` varchar(60) NOT NULL DEFAULT 'general',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`type_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lookup_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_key` varchar(60) NOT NULL,
  `value_key` varchar(80) NOT NULL,
  `value_label` varchar(200) NOT NULL,
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lookup` (`type_key`,`value_key`),
  KEY `idx_lookup_active` (`type_key`,`is_active`,`sort_order`),
  CONSTRAINT `fk_lookup_type` FOREIGN KEY (`type_key`) REFERENCES `lookup_types` (`type_key`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=392 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `milestones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `milestone_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `money_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `money_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_account_type` enum('bank','cash') NOT NULL,
  `from_account_id` int(10) unsigned NOT NULL,
  `to_account_type` enum('bank','cash') NOT NULL,
  `to_account_id` int(10) unsigned NOT NULL,
  `transfer_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `status` enum('posted','draft','cancelled') NOT NULL DEFAULT 'posted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `idx_transfer_date` (`transfer_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `money_transfers_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `opening_balance_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opening_balance_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `financial_year_id` int(10) unsigned DEFAULT NULL,
  `old_balance` decimal(15,2) DEFAULT 0.00,
  `new_balance` decimal(15,2) NOT NULL,
  `balance_type` enum('debit','credit') DEFAULT 'debit',
  `changed_by` int(10) unsigned NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_obh_account` (`account_id`),
  KEY `idx_obh_fy` (`financial_year_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `opening_balance_history_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opening_balance_history_ibfk_2` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opening_balance_history_ibfk_3` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` int(10) unsigned NOT NULL,
  `bill_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_bill` (`payment_id`,`bill_id`),
  KEY `idx_payment` (`payment_id`),
  KEY `idx_bill` (`bill_id`),
  CONSTRAINT `payment_allocations_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `vendor_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_allocations_ibfk_2` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','upi','credit_card','paypal') DEFAULT 'bank_transfer',
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_date` (`payment_date`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_finance_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_finance_mappings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_component` varchar(100) NOT NULL,
  `chart_account_id` int(10) unsigned NOT NULL,
  `mapping_type` enum('expense','liability','asset') NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payroll_component` (`payroll_component`),
  KEY `idx_chart_account` (`chart_account_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `payroll_finance_mappings_ibfk_1` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_finance_postings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_finance_postings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int(10) unsigned NOT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `posting_date` date NOT NULL,
  `total_gross` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Posted','Reversed') DEFAULT 'Draft',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_journal_entry` (`journal_entry_id`),
  KEY `idx_posting_date` (`posting_date`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `payroll_finance_postings_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_finance_postings_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_payment_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_payment_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int(10) unsigned NOT NULL,
  `bank_account_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Paid','Failed') DEFAULT 'Pending',
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_bank_account` (`bank_account_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_status` (`status`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `payroll_payment_batches_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `payroll_payment_batches_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_journal_entry` (`journal_entry_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_reconciliation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int(10) unsigned NOT NULL,
  `payroll_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `posted_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Reconciled','Unreconciled','Investigation') DEFAULT 'Unreconciled',
  `checked_by` int(10) unsigned DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payroll_run` (`payroll_run_id`),
  KEY `idx_status` (`status`),
  KEY `checked_by` (`checked_by`),
  CONSTRAINT `payroll_reconciliation_ibfk_1` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_runs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `run_code` varchar(50) NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `payroll_period_start` date NOT NULL,
  `payroll_period_end` date NOT NULL,
  `pay_date` date NOT NULL,
  `basic_salary` decimal(15,2) DEFAULT 0.00,
  `allowances` decimal(15,2) DEFAULT 0.00,
  `overtime` decimal(15,2) DEFAULT 0.00,
  `employer_contributions` decimal(15,2) DEFAULT 0.00,
  `employee_deductions` decimal(15,2) DEFAULT 0.00,
  `gross_salary` decimal(15,2) DEFAULT 0.00,
  `net_salary` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Approved','Posted','Paid') DEFAULT 'Draft',
  `description` text DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `run_code` (`run_code`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_status` (`status`),
  KEY `idx_pay_date` (`pay_date`),
  KEY `idx_period` (`payroll_period_start`,`payroll_period_end`),
  CONSTRAINT `payroll_runs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `payroll_runs_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `people`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `people` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `category` enum('employee','customer','contractor') NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `employee_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `contractor_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_code` (`person_code`),
  KEY `idx_person_code` (`person_code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  KEY `user_id` (`user_id`),
  KEY `employee_id` (`employee_id`),
  KEY `fk_people_customer` (`customer_id`),
  KEY `idx_people_contractor` (`contractor_id`),
  CONSTRAINT `fk_people_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_people_customer` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `people_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `people_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_checklist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_checklist_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `financial_year_id` int(10) unsigned NOT NULL,
  `period_id` int(10) unsigned NOT NULL DEFAULT 0,
  `template_id` int(10) unsigned DEFAULT NULL,
  `item_text` varchar(255) NOT NULL,
  `status` enum('pending','in_progress','completed','exception') NOT NULL DEFAULT 'pending',
  `comments` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `completed_by` int(10) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_checklist_item` (`financial_year_id`,`period_id`,`item_text`(191)),
  KEY `idx_chk_fy` (`financial_year_id`),
  KEY `idx_chk_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_checklist_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_checklist_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_text` varchar(255) NOT NULL,
  `module_area` varchar(50) NOT NULL DEFAULT 'general',
  `applies_to` enum('period','year','both') NOT NULL DEFAULT 'both',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_close_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_close_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `financial_year_id` int(10) unsigned DEFAULT NULL,
  `period_id` int(10) unsigned DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pca_fy` (`financial_year_id`),
  KEY `idx_pca_action` (`action`),
  KEY `idx_pca_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3905 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_close_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_close_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `retained_earnings_account_id` int(10) unsigned DEFAULT NULL,
  `income_summary_account_id` int(10) unsigned DEFAULT NULL,
  `closing_method` enum('direct_to_retained','via_income_summary') NOT NULL DEFAULT 'direct_to_retained',
  `require_checklist_before_period_close` tinyint(1) NOT NULL DEFAULT 0,
  `require_checklist_before_year_close` tinyint(1) NOT NULL DEFAULT 1,
  `allow_year_reopen` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_opening_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_opening_balances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `financial_year_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_closing_id` int(10) unsigned DEFAULT NULL,
  `generated_by` int(10) unsigned DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pob` (`financial_year_id`,`account_id`),
  CONSTRAINT `fk_pob_fy` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission` (`name`,`module`),
  KEY `idx_name` (`name`),
  KEY `idx_module` (`module`),
  KEY `idx_perms_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6567 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petty_cash_funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `petty_cash_funds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_account_id` int(10) unsigned NOT NULL,
  `fund_name` varchar(200) NOT NULL,
  `custodian_employee_id` int(10) unsigned DEFAULT NULL,
  `imprest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `petty_cash_funds_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petty_cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `petty_cash_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `petty_cash_fund_id` int(10) unsigned NOT NULL,
  `transaction_type` enum('issue','expense','topup','close') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expense_category` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `voucher_number` varchar(50) DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `idx_fund` (`petty_cash_fund_id`),
  KEY `idx_type` (`transaction_type`),
  CONSTRAINT `petty_cash_transactions_ibfk_1` FOREIGN KEY (`petty_cash_fund_id`) REFERENCES `petty_cash_funds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `petty_cash_transactions_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `po_receiving_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `po_receiving_summary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `po_item_id` int(10) unsigned NOT NULL,
  `total_received` decimal(18,3) NOT NULL DEFAULT 0.000,
  `total_accepted` decimal(18,3) NOT NULL DEFAULT 0.000,
  `total_rejected` decimal(18,3) NOT NULL DEFAULT 0.000,
  `total_damaged` decimal(18,3) NOT NULL DEFAULT 0.000,
  `remaining_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `outstanding_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `last_receipt_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_id` (`po_id`),
  KEY `idx_prs_po` (`po_id`),
  KEY `idx_prs_poitem` (`po_item_id`),
  CONSTRAINT `fk_prs_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `po_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `po_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `change_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_po` (`po_id`),
  CONSTRAINT `po_status_history_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `po_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_approval_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procurement_approval_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = any company',
  `department_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = any department',
  `priority` enum('low','normal','high','urgent') DEFAULT NULL COMMENT 'NULL = any',
  `max_amount` decimal(18,2) DEFAULT NULL COMMENT 'applies when total <= this; NULL = unlimited',
  `approver_role_id` int(10) unsigned NOT NULL,
  `rule_sequence` int(10) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_par_match` (`company_id`,`department_id`,`rule_sequence`),
  KEY `fk_par_role` (`approver_role_id`),
  CONSTRAINT `fk_par_role` FOREIGN KEY (`approver_role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procurement_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `pr_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pa_action` (`action`),
  KEY `idx_pa_pr` (`pr_id`),
  KEY `idx_pa_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=914 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procurement_sequences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `seq_type` varchar(20) NOT NULL,
  `seq_year` smallint(5) unsigned NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_seq` (`seq_type`,`seq_year`)
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procurement_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `member_role` varchar(50) DEFAULT 'Member',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`project_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_code` varchar(50) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `client_name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `estimated_hours` decimal(10,2) DEFAULT NULL,
  `project_manager_id` int(10) unsigned DEFAULT NULL,
  `status` enum('planning','active','on_hold','completed','cancelled') DEFAULT 'planning',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_code` (`project_code`),
  KEY `idx_project_code` (`project_code`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_manager` (`project_manager_id`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`project_manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_approvals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `approver_id` int(10) unsigned NOT NULL,
  `action` varchar(30) NOT NULL COMMENT 'submitted/approved/rejected/resubmitted',
  `comments` text DEFAULT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_poa_po` (`po_id`),
  CONSTRAINT `fk_poa_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `doc_label` varchar(100) NOT NULL DEFAULT 'Supporting Document',
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_poatt_po` (`po_id`),
  CONSTRAINT `fk_poatt_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `charge_name` varchar(80) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_poc_po` (`po_id`),
  CONSTRAINT `fk_poc_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_communications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_communications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `method` enum('email','pdf','print','manual') NOT NULL DEFAULT 'manual',
  `recipient` varchar(200) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'sent',
  `notes` varchar(255) DEFAULT NULL,
  `sent_by` int(10) unsigned NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pocm_po` (`po_id`),
  CONSTRAINT `fk_pocm_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `po_item_number` int(10) unsigned NOT NULL DEFAULT 1,
  `award_item_id` int(10) unsigned DEFAULT NULL COMMENT 'source quotation_selection rfq_item_id',
  `rfq_item_id` int(10) unsigned DEFAULT NULL,
  `quotation_item_id` int(10) unsigned DEFAULT NULL,
  `pr_item_id` int(10) unsigned DEFAULT NULL,
  `item_type` enum('stock','non_stock','service','asset','expense') NOT NULL DEFAULT 'non_stock',
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_percent` decimal(6,3) NOT NULL DEFAULT 0.000,
  `line_subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_tax` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `required_date` date DEFAULT NULL,
  `delivery_location` varchar(200) DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `awarded_quantity` decimal(18,3) NOT NULL DEFAULT 0.000 COMMENT 'from award',
  `ordered_quantity` decimal(18,3) NOT NULL DEFAULT 0.000 COMMENT 'this PO qty',
  `remaining_quantity` decimal(18,3) NOT NULL DEFAULT 0.000 COMMENT 'awarded - ordered',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_poi_po` (`po_id`),
  KEY `idx_poi_pritem` (`pr_item_id`),
  KEY `idx_poi_rfqitem` (`rfq_item_id`),
  CONSTRAINT `fk_poi_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poi_rfqitem` FOREIGN KEY (`rfq_item_id`) REFERENCES `rfq_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_revision_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_revision_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` int(10) unsigned NOT NULL,
  `po_item_id` int(10) unsigned NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `quantity` decimal(18,3) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_percent` decimal(6,3) NOT NULL DEFAULT 0.000,
  `line_total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pori_rev` (`revision_id`),
  CONSTRAINT `fk_pori_rev` FOREIGN KEY (`revision_id`) REFERENCES `purchase_order_revisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_revisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `revision_number` int(10) unsigned NOT NULL,
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"field":"old→new",...}' CHECK (json_valid(`changed_fields`)),
  `reason` text NOT NULL,
  `changed_by` int(10) unsigned NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_por` (`po_id`,`revision_number`),
  CONSTRAINT `fk_por_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_number` varchar(40) NOT NULL,
  `award_id` int(10) unsigned DEFAULT NULL COMMENT 'source quotation_selection id',
  `quotation_id` int(10) unsigned DEFAULT NULL COMMENT 'source vendor_quotations id',
  `rfq_id` int(10) unsigned DEFAULT NULL COMMENT 'source request_for_quotations id',
  `pr_id` int(10) unsigned DEFAULT NULL COMMENT 'source purchase_requisitions id',
  `vendor_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `requester_id` int(10) unsigned NOT NULL,
  `buyer_id` int(10) unsigned DEFAULT NULL,
  `po_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AED',
  `payment_terms` varchar(120) DEFAULT NULL,
  `delivery_terms` varchar(120) DEFAULT NULL,
  `delivery_location` varchar(200) DEFAULT NULL,
  `incoterms` varchar(60) DEFAULT NULL,
  `warranty_terms` varchar(160) DEFAULT NULL,
  `shipping_method` varchar(60) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `vendor_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `other_charges_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','pending_approval','approved','rejected','sent','acknowledged','partially_received','fully_received','partially_billed','fully_billed','closed','cancelled') NOT NULL DEFAULT 'draft',
  `revision_number` int(10) unsigned NOT NULL DEFAULT 0,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `sent_by` int(10) unsigned DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int(10) unsigned DEFAULT NULL,
  `vendor_reference` varchar(100) DEFAULT NULL,
  `expected_delivery_date_ack` date DEFAULT NULL,
  `vendor_comments` text DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `close_reason` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `idx_po_company` (`company_id`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_vendor` (`vendor_id`),
  KEY `idx_po_pr` (`pr_id`),
  KEY `idx_po_rfq` (`rfq_id`),
  KEY `idx_po_quotation` (`quotation_id`),
  KEY `idx_po_award` (`award_id`),
  CONSTRAINT `fk_po_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_po_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `vendor_quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `request_for_quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_requisition_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requisition_approvals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pr_id` int(10) unsigned NOT NULL,
  `approver_id` int(10) unsigned NOT NULL,
  `action` enum('submitted','approved','rejected','resubmitted','cancelled','reopened') NOT NULL,
  `comments` text DEFAULT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pra_pr` (`pr_id`),
  KEY `fk_pra_user` (`approver_id`),
  CONSTRAINT `fk_pra_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pra_user` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_requisition_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requisition_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pr_id` int(10) unsigned NOT NULL,
  `doc_label` varchar(100) NOT NULL DEFAULT 'Supporting Document',
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pra_att` (`pr_id`),
  CONSTRAINT `fk_pratt_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_requisition_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requisition_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pr_id` int(10) unsigned NOT NULL,
  `item_type` enum('stock','non_stock','service','asset','expense') NOT NULL DEFAULT 'non_stock',
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `est_unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_percent` decimal(6,3) NOT NULL DEFAULT 0.000,
  `line_subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_tax` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `required_date` date DEFAULT NULL,
  `preferred_vendor_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pri_pr` (`pr_id`),
  KEY `fk_pri_vendor` (`preferred_vendor_id`),
  KEY `fk_pri_dept` (`department_id`),
  KEY `fk_pri_project` (`project_id`),
  CONSTRAINT `fk_pri_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pri_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pri_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pri_vendor` FOREIGN KEY (`preferred_vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_requisitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requisitions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(40) NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `branch` varchar(120) DEFAULT NULL,
  `requester_id` int(10) unsigned NOT NULL,
  `request_date` date NOT NULL,
  `required_date` date NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `purpose` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AED',
  `status` enum('draft','submitted','pending_approval','approved','rejected','cancelled','closed') NOT NULL DEFAULT 'draft',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rejection_reason` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_number` (`pr_number`),
  KEY `idx_pr_company` (`company_id`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_requester` (`requester_id`),
  KEY `fk_pr_dept` (`department_id`),
  KEY `fk_pr_project` (`project_id`),
  CONSTRAINT `fk_pr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_pr_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(10) unsigned NOT NULL,
  `doc_label` varchar(100) NOT NULL DEFAULT 'Supporting Document',
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qatt_quote` (`quotation_id`),
  CONSTRAINT `fk_qatt_quote` FOREIGN KEY (`quotation_id`) REFERENCES `vendor_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_evaluations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(10) unsigned NOT NULL,
  `criterion` varchar(80) NOT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 0.00,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '0-10',
  `weighted_score` decimal(6,3) NOT NULL DEFAULT 0.000,
  `comments` varchar(255) DEFAULT NULL,
  `evaluated_by` int(10) unsigned NOT NULL,
  `evaluated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_eval` (`quotation_id`,`criterion`),
  CONSTRAINT `fk_qe_quote` FOREIGN KEY (`quotation_id`) REFERENCES `vendor_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=355 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_other_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_other_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(10) unsigned NOT NULL,
  `charge_name` varchar(80) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_qoc_quote` (`quotation_id`),
  CONSTRAINT `fk_qoc_quote` FOREIGN KEY (`quotation_id`) REFERENCES `vendor_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_selection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_selection` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` int(10) unsigned NOT NULL,
  `quotation_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `rfq_item_id` int(10) unsigned DEFAULT NULL,
  `award_level` enum('rfq','item') NOT NULL DEFAULT 'rfq',
  `reason` text NOT NULL,
  `is_override` tinyint(1) NOT NULL DEFAULT 0,
  `override_reason` text DEFAULT NULL,
  `selected_by` int(10) unsigned NOT NULL,
  `selected_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qs_rfq` (`rfq_id`),
  KEY `idx_qs_item` (`rfq_item_id`),
  KEY `fk_qs_quote` (`quotation_id`),
  CONSTRAINT `fk_qs_item` FOREIGN KEY (`rfq_item_id`) REFERENCES `rfq_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qs_quote` FOREIGN KEY (`quotation_id`) REFERENCES `vendor_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qs_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `request_for_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipt_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_receipt` (`receipt_id`),
  KEY `idx_invoice` (`invoice_id`),
  CONSTRAINT `receipt_allocations_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `customer_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `receipt_allocations_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipt_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gr_id` int(10) unsigned DEFAULT NULL,
  `sr_id` int(10) unsigned DEFAULT NULL,
  `doc_label` varchar(100) NOT NULL DEFAULT 'Supporting Document',
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ra_gr` (`gr_id`),
  KEY `idx_ra_sr` (`sr_id`),
  CONSTRAINT `fk_ra_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_sr` FOREIGN KEY (`sr_id`) REFERENCES `service_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_type` varchar(50) NOT NULL,
  `action` enum('generated','exported') NOT NULL DEFAULT 'generated',
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `generated_by` int(10) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL,
  `export_format` varchar(20) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `report_audit_log_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_exports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_type` varchar(100) NOT NULL,
  `export_format` enum('csv','pdf','print') NOT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `exported_by` int(10) unsigned DEFAULT NULL,
  `exported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_exported_by` (`exported_by`),
  KEY `idx_exported_at` (`exported_at`),
  CONSTRAINT `report_exports_ibfk_1` FOREIGN KEY (`exported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_name` varchar(150) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `template_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_config`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_template` (`template_name`,`report_type`),
  KEY `idx_report_templates_type` (`report_type`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `request_for_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `request_for_quotations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(40) NOT NULL,
  `pr_id` int(10) unsigned DEFAULT NULL COMMENT 'source approved requisition',
  `company_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `requester_id` int(10) unsigned NOT NULL,
  `rfq_date` date NOT NULL,
  `required_date` date DEFAULT NULL,
  `response_due` date NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AED',
  `delivery_location` varchar(200) DEFAULT NULL,
  `payment_terms` varchar(120) DEFAULT NULL,
  `delivery_terms` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','open','sent','partially_responded','fully_responded','evaluation','awarded','cancelled','closed') NOT NULL DEFAULT 'draft',
  `estimated_pr_value` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'informational only',
  `created_by` int(10) unsigned NOT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfq_number` (`rfq_number`),
  KEY `idx_rfq_company` (`company_id`),
  KEY `idx_rfq_status` (`status`),
  KEY `idx_rfq_pr` (`pr_id`),
  CONSTRAINT `fk_rfq_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_rfq_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfq_evaluation_criteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfq_evaluation_criteria` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` int(10) unsigned NOT NULL,
  `criterion` varchar(80) NOT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_crit` (`rfq_id`,`criterion`),
  CONSTRAINT `fk_rec_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `request_for_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=235 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfq_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfq_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` int(10) unsigned NOT NULL,
  `pr_item_id` int(10) unsigned DEFAULT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `specification` text DEFAULT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `required_date` date DEFAULT NULL,
  `delivery_location` varchar(200) DEFAULT NULL,
  `tax_percent` decimal(6,3) NOT NULL DEFAULT 0.000,
  `est_unit_price` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'PR estimate, informational for savings',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rfqitems_rfq` (`rfq_id`),
  KEY `idx_rfqitems_pritem` (`pr_item_id`),
  CONSTRAINT `fk_ri_pritem` FOREIGN KEY (`pr_item_id`) REFERENCES `purchase_requisition_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ri_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `request_for_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfq_vendor_communications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfq_vendor_communications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_vendor_id` int(10) unsigned NOT NULL,
  `method` enum('email','pdf','print','manual') NOT NULL DEFAULT 'manual',
  `recipient` varchar(200) DEFAULT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'sent',
  `notes` varchar(255) DEFAULT NULL,
  `sent_by` int(10) unsigned NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rvc_rfqvendor` (`rfq_vendor_id`),
  CONSTRAINT `fk_rvc_rv` FOREIGN KEY (`rfq_vendor_id`) REFERENCES `rfq_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfq_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfq_vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `status` enum('selected','sent','viewed','responded','declined','expired') NOT NULL DEFAULT 'selected',
  `sent_at` datetime DEFAULT NULL,
  `sent_by` int(10) unsigned DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rfq_vendor` (`rfq_id`,`vendor_id`),
  KEY `fk_rv_vendor` (`vendor_id`),
  CONSTRAINT `fk_rv_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `request_for_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rv_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_roles_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saved_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `saved_reports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scheduled_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduled_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `recipients` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_frequency` (`frequency`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_next_run` (`next_run`),
  CONSTRAINT `scheduled_reports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_receipt_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sr_id` int(10) unsigned NOT NULL,
  `po_item_id` int(10) unsigned NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `ordered_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `completed_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `accepted_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `rejected_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sri_sr` (`sr_id`),
  KEY `idx_sri_poitem` (`po_item_id`),
  CONSTRAINT `fk_sri_poitem` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`),
  CONSTRAINT `fk_sri_sr` FOREIGN KEY (`sr_id`) REFERENCES `service_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sr_number` varchar(40) NOT NULL,
  `po_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `service_description` text DEFAULT NULL,
  `service_period_start` date DEFAULT NULL,
  `service_period_end` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `ordered_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `completed_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `accepted_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `rejected_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `received_by` int(10) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','pending_acceptance','partially_accepted','accepted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sr_number` (`sr_number`),
  KEY `idx_sr_po` (`po_id`),
  KEY `idx_sr_vendor` (`vendor_id`),
  KEY `idx_sr_company` (`company_id`),
  KEY `idx_sr_status` (`status`),
  CONSTRAINT `fk_sr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_sr_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `fk_sr_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sidebar_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sidebar_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL COMMENT 'Section group name. NULL = items within the group that has no section label.',
  `section_order` int(11) DEFAULT 0 COMMENT 'Controls the order of sections.',
  `label` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fas fa-link',
  `route` varchar(255) NOT NULL DEFAULT '#',
  `permission` varchar(100) DEFAULT NULL COMMENT 'Required permission key (e.g. users.view). NULL = visible to all.',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Sort order within a section group.',
  `is_active` tinyint(1) DEFAULT 1,
  `is_indented` tinyint(1) DEFAULT 0 COMMENT 'Visually indent this item (adds pl-10 CSS).',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sidebar_menu_route` (`route`),
  KEY `idx_active` (`is_active`),
  KEY `idx_section_order` (`section_order`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=468 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `slas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sla_name` varchar(100) NOT NULL,
  `response_time_hours` int(11) NOT NULL DEFAULT 24,
  `resolution_time_hours` int(11) NOT NULL DEFAULT 72,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sla_name` (`sla_name`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `software_licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `software_licenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `software_name` varchar(200) NOT NULL,
  `license_key` varchar(500) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `seats` int(10) unsigned DEFAULT 1,
  `assigned_seats` int(10) unsigned DEFAULT 0,
  `vendor` varchar(200) DEFAULT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`software_name`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `task_activities_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_assignees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assignees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignee` (`task_id`,`employee_id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_employee` (`employee_id`),
  CONSTRAINT `task_assignees_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignees_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `task_attachments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_subtasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_subtasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `title` varchar(500) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `position` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  CONSTRAINT `task_subtasks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `tag_color` varchar(7) DEFAULT '#6366f1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  CONSTRAINT `task_tags_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `milestone_id` int(10) unsigned DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `estimated_hours` decimal(10,2) DEFAULT NULL,
  `status` enum('todo','in_progress','review','testing','completed','blocked') DEFAULT 'todo',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `progress_percentage` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_milestone` (`milestone_id`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(50) NOT NULL,
  `tax_type_id` int(10) unsigned NOT NULL,
  `tax_period_id` int(10) unsigned DEFAULT NULL,
  `adjustment_date` date NOT NULL,
  `direction` enum('output','input') NOT NULL DEFAULT 'output',
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reason` text NOT NULL,
  `reference` varchar(200) DEFAULT NULL,
  `status` enum('draft','approved','rejected') NOT NULL DEFAULT 'draft',
  `approval_notes` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `adjustment_no` (`adjustment_no`),
  KEY `fk_adj_type` (`tax_type_id`),
  KEY `fk_adj_period` (`tax_period_id`),
  KEY `fk_adj_journal` (`journal_entry_id`),
  KEY `fk_adj_created` (`created_by`),
  KEY `fk_adj_approved` (`approved_by`),
  KEY `idx_adj_date` (`adjustment_date`),
  KEY `idx_adj_status` (`status`),
  KEY `idx_adj_direction` (`direction`),
  CONSTRAINT `fk_adj_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_adj_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_adj_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_adj_period` FOREIGN KEY (`tax_period_id`) REFERENCES `tax_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_adj_type` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_exemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_exemptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exemption_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `exemption_code` (`exemption_code`),
  KEY `idx_exemption_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_group_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_group_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tax_group_id` int(10) unsigned NOT NULL,
  `tax_rate_id` int(10) unsigned NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_group_rate` (`tax_group_id`,`tax_rate_id`),
  KEY `fk_tgl_rate` (`tax_rate_id`),
  KEY `idx_tgl_group` (`tax_group_id`),
  CONSTRAINT `fk_tgl_group` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tgl_rate` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tax_group_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tax_type_id` int(10) unsigned NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','submitted','closed') NOT NULL DEFAULT 'open',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_period` (`tax_type_id`,`start_date`,`end_date`),
  KEY `fk_tax_period_closed_by` (`closed_by`),
  KEY `idx_period_status` (`status`),
  KEY `idx_period_dates` (`start_date`,`end_date`),
  CONSTRAINT `fk_tax_period_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tax_period_type` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tax_type_id` int(10) unsigned NOT NULL,
  `rate_name` varchar(100) NOT NULL,
  `rate_percentage` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tax_rate_type` (`tax_type_id`),
  KEY `idx_tax_rate_effective` (`effective_from`,`effective_to`),
  CONSTRAINT `fk_tax_rate_type` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_registrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `tax_number` varchar(100) NOT NULL,
  `tax_type_id` int(10) unsigned NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reg_tax_type` (`tax_type_id`),
  KEY `idx_reg_status` (`status`),
  CONSTRAINT `fk_tax_reg_type` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(50) NOT NULL,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_type` enum('gst','vat','tax') DEFAULT 'gst',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tax_type_id` int(10) unsigned NOT NULL,
  `tax_period_id` int(10) unsigned DEFAULT NULL,
  `source_module` varchar(50) NOT NULL,
  `source_record_id` int(10) unsigned NOT NULL,
  `source_document_no` varchar(100) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_direction` enum('output','input','adjustment') NOT NULL DEFAULT 'output',
  `tax_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `tax_rate_id` int(10) unsigned DEFAULT NULL,
  `tax_group_id` int(10) unsigned DEFAULT NULL,
  `tax_status` enum('taxable','zero_rated','exempt','out_of_scope') NOT NULL DEFAULT 'taxable',
  `exemption_id` int(10) unsigned DEFAULT NULL,
  `counterparty_type` enum('customer','vendor','none') NOT NULL DEFAULT 'none',
  `counterparty_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','reversed') NOT NULL DEFAULT 'active',
  `reversal_of` int(10) unsigned DEFAULT NULL,
  `reference` varchar(200) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tx_period` (`tax_period_id`),
  KEY `fk_tx_rate` (`tax_rate_id`),
  KEY `fk_tx_group` (`tax_group_id`),
  KEY `fk_tx_exemption` (`exemption_id`),
  KEY `fk_tx_reversal` (`reversal_of`),
  KEY `fk_tx_created_by` (`created_by`),
  KEY `idx_tx_date` (`transaction_date`),
  KEY `idx_tx_type_period` (`tax_type_id`,`tax_period_id`),
  KEY `idx_tx_direction` (`tax_direction`),
  KEY `idx_tx_source` (`source_module`,`source_record_id`),
  KEY `idx_tx_counterparty` (`counterparty_type`,`counterparty_id`),
  CONSTRAINT `fk_tx_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_exemption` FOREIGN KEY (`exemption_id`) REFERENCES `tax_exemptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_group` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_period` FOREIGN KEY (`tax_period_id`) REFERENCES `tax_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_rate` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_reversal` FOREIGN KEY (`reversal_of`) REFERENCES `tax_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_type` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_tax_type_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_assets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned DEFAULT NULL,
  `asset_type` enum('logo','background','watermark','image','stamp','signature','barcode','qrcode') NOT NULL,
  `asset_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_template` (`template_id`),
  KEY `idx_type` (`asset_type`),
  CONSTRAINT `template_assets_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `template_assets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `performed_by` int(10) unsigned DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `performed_by` (`performed_by`),
  KEY `idx_template` (`template_id`),
  KEY `idx_action` (`action`),
  CONSTRAINT `template_history_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `template_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_module_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_module_mappings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `module_action` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_module` (`template_id`,`module_name`,`module_action`),
  KEY `idx_module` (`module_name`,`module_action`),
  CONSTRAINT `template_module_mappings_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_preview_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_preview_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `version_id` int(10) unsigned NOT NULL,
  `preview_html` longtext DEFAULT NULL,
  `preview_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preview_data`)),
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `version_id` (`version_id`),
  KEY `idx_template_version` (`template_id`,`version_id`),
  CONSTRAINT `template_preview_cache_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `template_preview_cache_ibfk_2` FOREIGN KEY (`version_id`) REFERENCES `template_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Full template definition JSON' CHECK (json_valid(`template_data`)),
  `placeholder_definitions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`placeholder_definitions`)),
  `change_notes` text DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_version` (`template_id`,`version_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_template` (`template_id`),
  CONSTRAINT `template_versions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `template_versions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned DEFAULT NULL,
  `template_name` varchar(200) NOT NULL,
  `template_type` varchar(100) NOT NULL COMMENT 'invoice, leave_report, employee_report, etc.',
  `description` text DEFAULT NULL,
  `page_size` enum('A4','Letter','Legal') DEFAULT 'A4',
  `page_orientation` enum('portrait','landscape') DEFAULT 'portrait',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `version` int(10) unsigned DEFAULT 1,
  `is_system` tinyint(1) DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_category` (`category_id`),
  KEY `idx_type` (`template_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `template_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `templates_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `test_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_order` (
  `id` int(11) NOT NULL,
  `qty` int(11) DEFAULT NULL,
  `val` int(11) DEFAULT NULL,
  `avg` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `test_order2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_order2` (
  `id` int(11) NOT NULL,
  `qty` int(11) DEFAULT NULL,
  `val` int(11) DEFAULT NULL,
  `avg` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `assigned_from` int(10) unsigned DEFAULT NULL,
  `assigned_to` int(10) unsigned NOT NULL,
  `assigned_by` int(10) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `assigned_from` (`assigned_from`),
  CONSTRAINT `ticket_assignments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_assignments_ibfk_2` FOREIGN KEY (`assigned_from`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_assignments_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_assignments_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `reply_id` int(10) unsigned DEFAULT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_reply` (`reply_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `ticket_attachments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `ticket_replies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_attachments_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `priority_default` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`),
  UNIQUE KEY `category_code` (`category_code`),
  KEY `idx_status` (`status`),
  KEY `idx_code` (`category_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_internal_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_internal_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `note` text NOT NULL,
  `mentioned_user_ids` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `ticket_internal_notes_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_internal_notes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_replies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `replied_by` int(10) unsigned NOT NULL,
  `reply_type` enum('public','internal') DEFAULT 'public',
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_replied_by` (`replied_by`),
  KEY `idx_type` (`reply_type`),
  CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(50) NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `employee_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `sla_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('open','in_progress','waiting_client','resolved','closed','reopened') DEFAULT 'open',
  `source` enum('web','email','phone','internal') DEFAULT 'web',
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `sla_breached` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_ticket_number` (`ticket_number`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_client` (`client_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_sla` (`sla_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_created` (`created_at`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`sla_id`) REFERENCES `slas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `timesheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timesheets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned NOT NULL,
  `task_id` int(10) unsigned DEFAULT NULL,
  `work_date` date NOT NULL,
  `hours_logged` decimal(5,2) NOT NULL,
  `work_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_date` (`work_date`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `timesheets_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timesheets_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timesheets_ibfk_3` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units_of_measure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units_of_measure` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_companies` (
  `user_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_dashboard_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_dashboard_layouts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `dashboard_key` varchar(50) NOT NULL,
  `layout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`layout_json`)),
  `is_customized` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_dashboard` (`user_id`,`dashboard_key`),
  KEY `idx_user` (`user_id`),
  KEY `idx_dashboard` (`dashboard_key`),
  CONSTRAINT `user_dashboard_layouts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `theme` varchar(20) NOT NULL DEFAULT 'ibm-light',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_approvals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `approver_id` int(10) unsigned NOT NULL,
  `action` enum('submitted','approved','rejected','overridden','cancelled') NOT NULL,
  `comments` text DEFAULT NULL,
  `override_reason` text DEFAULT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vba_bill` (`bill_id`),
  CONSTRAINT `fk_vba_bill` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `doc_label` varchar(100) NOT NULL DEFAULT 'Supporting Document',
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vbat_bill` (`bill_id`),
  CONSTRAINT `fk_vbat_bill` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_exceptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `match_id` int(10) unsigned DEFAULT NULL,
  `exception_type` enum('quantity_mismatch','price_mismatch','tax_mismatch','missing_receipt','over_billing','under_billing','duplicate_invoice') NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `po_value` decimal(18,2) DEFAULT NULL,
  `received_value` decimal(18,2) DEFAULT NULL,
  `bill_value` decimal(18,2) DEFAULT NULL,
  `difference` decimal(18,2) DEFAULT NULL,
  `difference_percent` decimal(6,3) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `affected_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`affected_items`)),
  `required_action` enum('review','adjust','reject','approve_override') NOT NULL DEFAULT 'review',
  `status` enum('open','resolved','overridden','rejected') NOT NULL DEFAULT 'open',
  `resolved_by` int(10) unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `override_reason` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vbe_bill` (`bill_id`),
  KEY `idx_vbe_match` (`match_id`),
  KEY `idx_vbe_status` (`status`),
  CONSTRAINT `fk_vbe_bill` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vbe_match` FOREIGN KEY (`match_id`) REFERENCES `vendor_bill_matches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `billed_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `remaining_billable_quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `match_quantity_status` enum('pending','matched','short','over','exception') NOT NULL DEFAULT 'pending',
  `match_price_status` enum('pending','matched','variance','exception') NOT NULL DEFAULT 'pending',
  `quantity_difference` decimal(18,3) DEFAULT 0.000,
  `price_difference` decimal(18,4) DEFAULT 0.0000,
  `amount_difference` decimal(18,2) DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `tax_type_id` int(10) unsigned DEFAULT NULL,
  `tax_rate_id` int(10) unsigned DEFAULT NULL,
  `tax_group_id` int(10) unsigned DEFAULT NULL,
  `tax_status` enum('taxable','zero_rated','exempt','out_of_scope') NOT NULL DEFAULT 'taxable',
  `taxable_amount` decimal(15,2) DEFAULT 0.00,
  `tax_exemption_id` int(10) unsigned DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `line_total` decimal(15,2) DEFAULT 0.00,
  `expense_account_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to chart_of_accounts -- which expense account to DR',
  `procurement_po_item_id` int(10) unsigned DEFAULT NULL,
  `procurement_gr_item_id` int(10) unsigned DEFAULT NULL,
  `procurement_sr_item_id` int(10) unsigned DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `expense_account_id` (`expense_account_id`),
  KEY `idx_bill` (`bill_id`),
  KEY `idx_billitem_tax` (`tax_rate_id`),
  KEY `idx_vbi_poitem` (`procurement_po_item_id`),
  KEY `idx_vbi_gritem` (`procurement_gr_item_id`),
  CONSTRAINT `vendor_bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_bill_items_ibfk_2` FOREIGN KEY (`expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_matches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `po_id` int(10) unsigned NOT NULL,
  `gr_id` int(10) unsigned DEFAULT NULL,
  `sr_id` int(10) unsigned DEFAULT NULL,
  `match_status` enum('pending','matched','exception','overridden') NOT NULL DEFAULT 'pending',
  `total_po_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_gr_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_bill_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_percent` decimal(6,3) NOT NULL DEFAULT 0.000,
  `exception_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`exception_details`)),
  `matched_by` int(10) unsigned DEFAULT NULL,
  `matched_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vbm_bill` (`bill_id`),
  KEY `idx_vbm_po` (`po_id`),
  KEY `idx_vbm_gr` (`gr_id`),
  KEY `idx_vbm_status` (`match_status`),
  KEY `fk_vbm_sr` (`sr_id`),
  CONSTRAINT `fk_vbm_bill` FOREIGN KEY (`bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vbm_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vbm_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `fk_vbm_sr` FOREIGN KEY (`sr_id`) REFERENCES `service_receipts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bills` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_number` varchar(50) NOT NULL,
  `vendor_invoice_number` varchar(100) DEFAULT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `cost_center_id` int(10) unsigned DEFAULT NULL,
  `po_id` int(10) unsigned DEFAULT NULL,
  `procurement_po_id` int(10) unsigned DEFAULT NULL,
  `procurement_gr_id` int(10) unsigned DEFAULT NULL,
  `procurement_sr_id` int(10) unsigned DEFAULT NULL,
  `match_status` enum('pending','matched','exception','overridden') NOT NULL DEFAULT 'pending',
  `tolerance_quantity` decimal(6,3) DEFAULT NULL,
  `tolerance_price` decimal(18,4) DEFAULT NULL,
  `tolerance_amount` decimal(18,2) DEFAULT NULL,
  `is_procurement_bill` tinyint(1) NOT NULL DEFAULT 0,
  `grn_id` int(10) unsigned DEFAULT NULL,
  `bill_date` date NOT NULL,
  `due_date` date NOT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('draft','approved','partially_paid','paid','overdue','cancelled') DEFAULT 'draft',
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `posted_by` int(10) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `grn_id` (`grn_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_bill_number` (`bill_number`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_po` (`po_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_vendor_invoice_v` (`vendor_invoice_number`),
  KEY `idx_procurement_po` (`procurement_po_id`),
  KEY `idx_procurement_gr` (`procurement_gr_id`),
  KEY `idx_procurement_sr` (`procurement_sr_id`),
  KEY `idx_match_status` (`match_status`),
  KEY `idx_is_procurement` (`is_procurement_bill`),
  KEY `idx_bill_company` (`company_id`),
  KEY `idx_bill_department` (`department_id`),
  KEY `idx_bill_project` (`project_id`),
  KEY `idx_bill_cost_center` (`cost_center_id`),
  CONSTRAINT `fk_bill_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bill_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bill_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bill_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bills_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `vendor_bills_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bills_ibfk_3` FOREIGN KEY (`grn_id`) REFERENCES `goods_receipt_notes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bills_ibfk_4` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bills_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','credit_card','online') NOT NULL DEFAULT 'bank_transfer',
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `cash_account_id` int(10) unsigned DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unallocated_amount` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_number` (`payment_number`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_payment_number` (`payment_number`),
  KEY `idx_vendor` (`vendor_id`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `vendor_payments_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `vendor_payments_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payments_ibfk_3` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payments_ibfk_4` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payments_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_quotation_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int(10) unsigned NOT NULL,
  `rfq_item_id` int(10) unsigned DEFAULT NULL,
  `is_alternative` tinyint(1) NOT NULL DEFAULT 0,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_percent` decimal(6,3) NOT NULL DEFAULT 0.000,
  `line_subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_tax` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `delivery_days` int(10) unsigned DEFAULT NULL,
  `warranty_months` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vqi_quote` (`quotation_id`),
  KEY `idx_vqi_rfqitem` (`rfq_item_id`),
  CONSTRAINT `fk_vqi_quote` FOREIGN KEY (`quotation_id`) REFERENCES `vendor_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vqi_rfqitem` FOREIGN KEY (`rfq_item_id`) REFERENCES `rfq_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=380 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_quotations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(40) NOT NULL,
  `rfq_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AED',
  `payment_terms` varchar(120) DEFAULT NULL,
  `delivery_terms` varchar(120) DEFAULT NULL,
  `incoterms` varchar(60) DEFAULT NULL,
  `warranty_terms` varchar(160) DEFAULT NULL,
  `vendor_reference` varchar(100) DEFAULT NULL,
  `lead_time_days` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `other_charges_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','received','under_evaluation','shortlisted','rejected','selected','expired','cancelled') NOT NULL DEFAULT 'draft',
  `received_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  UNIQUE KEY `uk_quote_rfq_vendor` (`rfq_id`,`vendor_id`),
  KEY `fk_q_vendor` (`vendor_id`),
  CONSTRAINT `fk_q_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `request_for_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_q_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_code` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `vat_number` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `default_expense_account_id` int(10) unsigned DEFAULT NULL,
  `default_payable_account_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_code` (`vendor_code`),
  KEY `idx_vendor_code` (`vendor_code`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voucher_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voucher_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_name` varchar(100) NOT NULL,
  `voucher_prefix` varchar(10) NOT NULL,
  `auto_numbering` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `warehouse_code` varchar(30) NOT NULL,
  `warehouse_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `manager_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wh_code_company` (`warehouse_code`,`company_id`),
  KEY `idx_wh_company` (`company_id`),
  KEY `idx_wh_status` (`status`),
  CONSTRAINT `fk_wh_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `widget_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widget_definitions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `widget_key` varchar(80) NOT NULL,
  `module` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-chart-bar',
  `category` varchar(50) DEFAULT 'charts',
  `default_width` int(10) unsigned DEFAULT 1,
  `default_height` int(10) unsigned DEFAULT 1,
  `min_width` int(10) unsigned DEFAULT 1,
  `min_height` int(10) unsigned DEFAULT 1,
  `data_url` varchar(500) DEFAULT NULL,
  `render_type` enum('server_html','chartjs','hybrid') DEFAULT 'server_html',
  `permission_required` varchar(100) DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `widget_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`widget_options`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `widget_key` (`widget_key`),
  KEY `idx_module` (`module`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `year_end_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `year_end_closings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `financial_year_id` int(10) unsigned NOT NULL,
  `next_financial_year_id` int(10) unsigned DEFAULT NULL,
  `closing_journal_id` int(10) unsigned DEFAULT NULL,
  `opening_journal_id` int(10) unsigned DEFAULT NULL,
  `net_profit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `retained_earnings_account_id` int(10) unsigned DEFAULT NULL,
  `closing_method` varchar(30) NOT NULL DEFAULT 'direct_to_retained',
  `total_temp_debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_temp_credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `opening_debit_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `opening_credit_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `verification_passed` tinyint(1) NOT NULL DEFAULT 0,
  `validation_snapshot` longtext DEFAULT NULL,
  `status` enum('completed','reopened') NOT NULL DEFAULT 'completed',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopened_by` int(10) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_yec_fy` (`financial_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

