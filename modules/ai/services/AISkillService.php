<?php
/**
 * AI F17 — Skill Registry & Execution Service.
 *
 * Manages skill CRUD, matching, execution lifecycle, and analytics.
 */
class AISkillService
{
    public static function getSkills(PDO $db, array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];
        if (!empty($filters['module'])) { $where[] = "module = ?"; $params[] = $filters['module']; }
        if (!empty($filters['skill_type'])) { $where[] = "skill_type = ?"; $params[] = $filters['skill_type']; }
        if (!empty($filters['status'])) { $where[] = "status = ?"; $params[] = $filters['status']; }
        if (isset($filters['is_active'])) { $where[] = "is_active = ?"; $params[] = (int)$filters['is_active']; }
        if (!empty($filters['search'])) { $where[] = "(name LIKE ? OR description LIKE ?)"; $params[] = "%{$filters['search']}%"; $params[] = "%{$filters['search']}%"; }
        $whereClause = implode(' AND ', $where);
        $limit = min(100, (int)($filters['limit'] ?? 50));
        $stmt = $db->prepare("SELECT * FROM ai_skills WHERE {$whereClause} ORDER BY name ASC LIMIT {$limit}");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSkill(PDO $db, string $skillId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_skills WHERE skill_id = ?");
        $stmt->execute([$skillId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function createSkill(PDO $db, array $data): int
    {
        $stmt = $db->prepare("INSERT INTO ai_skills (skill_id, name, description, module, category, skill_type, status, version, required_permissions_json, required_capabilities_json, input_schema_json, steps_json, output_schema_json, safety_classification, mode, max_steps, max_runtime_seconds, max_tool_calls, parent_skill_id, nesting_depth, created_by, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        $stmt->execute([
            $data['skill_id'], $data['name'], $data['description'] ?? null, $data['module'] ?? null,
            $data['category'] ?? null, $data['skill_type'] ?? 'guidance', $data['status'] ?? 'draft',
            $data['version'] ?? 1, json_encode($data['required_permissions'] ?? []),
            json_encode($data['required_capabilities'] ?? []), json_encode($data['input_schema'] ?? []),
            json_encode($data['steps'] ?? []), json_encode($data['output_schema'] ?? []),
            $data['safety_classification'] ?? 'read_only', $data['mode'] ?? 'guide',
            $data['max_steps'] ?? 20, $data['max_runtime_seconds'] ?? 300, $data['max_tool_calls'] ?? 10,
            $data['parent_skill_id'] ?? null, $data['nesting_depth'] ?? 0, $data['created_by'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function updateSkill(PDO $db, string $skillId, array $data): bool
    {
        $sets = [];
        $params = [];
        $updatable = ['name','description','module','category','skill_type','status','version','required_permissions_json','required_capabilities_json','input_schema_json','steps_json','output_schema_json','safety_classification','mode','max_steps','max_runtime_seconds','max_tool_calls','parent_skill_id','nesting_depth','approved_by','published_at','deprecated_at','is_active'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
            }
        }
        if (empty($sets)) return false;
        $params[] = $skillId;
        $stmt = $db->prepare("UPDATE ai_skills SET " . implode(', ', $sets) . " WHERE skill_id = ?");
        return $stmt->execute($params);
    }

    public static function deleteSkill(PDO $db, string $skillId): bool
    {
        return self::updateSkill($db, $skillId, ['status' => 'archived', 'is_active' => 0]);
    }

    public static function publishSkill(PDO $db, string $skillId, int $approvedBy): bool
    {
        return self::updateSkill($db, $skillId, ['status' => 'published', 'approved_by' => $approvedBy, 'published_at' => date('Y-m-d H:i:s')]);
    }

    public static function matchSkills(PDO $db, string $request, array $context = []): array
    {
        $published = self::getSkills($db, ['status' => 'published', 'is_active' => 1]);
        $matches = [];
        $requestLower = strtolower($request);
        foreach ($published as $skill) {
            $score = 0;
            $nameLower = strtolower($skill['name']);
            $descLower = strtolower($skill['description'] ?? '');
            if (strpos($nameLower, $requestLower) !== false) { $score += 50; }
            $words = explode(' ', $requestLower);
            foreach ($words as $word) {
                if (strlen($word) > 2 && strpos($nameLower, $word) !== false) $score += 10;
                if (strlen($word) > 2 && strpos($descLower, $word) !== false) $score += 5;
            }
            if (!empty($context['module']) && $skill['module'] === $context['module']) $score += 20;
            if (!empty($context['preferred_type']) && $skill['skill_type'] === $context['preferred_type']) $score += 15;
            if ($score > 0) { $skill['match_score'] = $score; $matches[] = $skill; }
        }
        usort($matches, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        return array_slice($matches, 0, 10);
    }

    public static function getSuggestions(PDO $db, int $userId, array $context = []): array
    {
        $published = self::getSkills($db, ['status' => 'published', 'is_active' => 1]);
        $suggestions = [];
        foreach ($published as $skill) {
            $reason = null;
            if (!empty($context['module']) && $skill['module'] === $context['module']) $reason = "Related to current module: {$skill['module']}";
            if ($skill['skill_type'] === 'workflow' && !empty($context['page'])) $reason = "Workflow available for this page";
            if ($reason) { $skill['suggestion_reason'] = $reason; $suggestions[] = $skill; }
        }
        return array_slice($suggestions, 0, 5);
    }

    public static function startRun(PDO $db, string $skillId, int $userId, ?int $companyId = null, ?int $conversationId = null): ?string
    {
        $skill = self::getSkill($db, $skillId);
        if (!$skill || $skill['status'] !== 'published' || !$skill['is_active']) return null;
        $runId = 'run_' . bin2hex(random_bytes(16));
        $steps = json_decode($skill['steps_json'], true) ?: [];
        $db->prepare("INSERT INTO ai_skill_runs (run_id, skill_id, skill_version, user_id, company_id, conversation_id, current_step, total_steps, status, context_json, started_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'started', '{}', NOW(), NOW())")->execute([$runId, $skillId, $skill['version'], $userId, $companyId, $conversationId, count($steps)]);
        self::audit($db, $userId, 'skill_started', $skillId, ['run_id' => $runId]);
        return $runId;
    }

    public static function advanceRun(PDO $db, string $runId, array $stepOutput = []): ?array
    {
        $run = self::getRun($db, $runId);
        if (!$run || !in_array($run['status'], ['started', 'in_progress', 'waiting_for_user'])) return null;
        $nextStep = $run['current_step'] + 1;
        $newStatus = $nextStep >= $run['total_steps'] ? 'completed' : 'in_progress';
        $db->prepare("UPDATE ai_skill_runs SET current_step = ?, status = ?, result_summary = IF(? = 'completed', ?, result_summary), completed_at = IF(? = 'completed', NOW(), completed_at) WHERE run_id = ?")->execute([$nextStep, $newStatus, $newStatus, json_encode($stepOutput), $newStatus, $runId]);
        if (!empty($stepOutput)) {
            $db->prepare("INSERT INTO ai_skill_step_results (run_id, step_index, step_type, status, output_json, created_at) VALUES (?, ?, ?, 'completed', ?, NOW())")->execute([$runId, $run['current_step'], $stepOutput['type'] ?? 'unknown', json_encode($stepOutput)]);
        }
        if ($newStatus === 'completed') {
            self::audit($db, $run['user_id'], 'skill_completed', $run['skill_id'], ['run_id' => $runId]);
            self::updateAnalytics($db, $run['skill_id'], 'completed');
        }
        return self::getRun($db, $runId);
    }

    public static function cancelRun(PDO $db, string $runId, int $userId): bool
    {
        $run = self::getRun($db, $runId);
        if (!$run || $run['user_id'] !== $userId) return false;
        if (in_array($run['status'], ['completed', 'cancelled', 'failed'])) return false;
        $stmt = $db->prepare("UPDATE ai_skill_runs SET status = 'cancelled' WHERE run_id = ?");
        $result = $stmt->execute([$runId]);
        self::audit($db, $userId, 'skill_cancelled', $run['skill_id'], ['run_id' => $runId]);
        self::updateAnalytics($db, $run['skill_id'], 'cancelled');
        return $result;
    }

    public static function getRun(PDO $db, string $runId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_skill_runs WHERE run_id = ?");
        $stmt->execute([$runId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function getRunsForSkill(PDO $db, string $skillId, int $limit = 20): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_skill_runs WHERE skill_id = ? ORDER BY started_at DESC LIMIT ?");
        $stmt->execute([$skillId, (int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function submitFeedback(PDO $db, string $runId, int $userId, string $rating, ?string $comment = null): bool
    {
        $run = self::getRun($db, $runId);
        if (!$run || $run['user_id'] !== $userId) return false;
        return $db->prepare("INSERT INTO ai_skill_feedback (run_id, skill_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$runId, $run['skill_id'], $userId, $rating, $comment]);
    }

    public static function getAnalytics(PDO $db, string $skillId, int $days = 30): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_skill_analytics WHERE skill_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY date DESC");
        $stmt->execute([$skillId, (int)$days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totals = ['starts' => 0, 'completed' => 0, 'cancelled' => 0, 'failed' => 0, 'feedback' => 0, 'positive' => 0];
        foreach ($rows as $row) {
            $totals['starts'] += $row['total_starts'];
            $totals['completed'] += $row['total_completed'];
            $totals['cancelled'] += $row['total_cancelled'];
            $totals['failed'] += $row['total_failed'];
            $totals['feedback'] += $row['total_feedback'];
            $totals['positive'] += $row['positive_feedback'];
        }
        $totals['completion_rate'] = $totals['starts'] > 0 ? round($totals['completed'] / $totals['starts'] * 100, 1) : 0;
        $totals['feedback_rate'] = $totals['starts'] > 0 ? round($totals['feedback'] / $totals['starts'] * 100, 1) : 0;
        return ['daily' => $rows, 'totals' => $totals];
    }

    private static function updateAnalytics(PDO $db, string $skillId, string $event): void
    {
        $today = date('Y-m-d');
        $col = match($event) { 'completed' => 'total_completed', 'cancelled' => 'total_cancelled', 'failed' => 'total_failed', default => 'total_starts' };
        $db->prepare("INSERT INTO ai_skill_analytics (skill_id, date, total_starts, total_completed, total_cancelled, total_failed) VALUES (?, ?, IF(?='started',1,0), IF(?='completed',1,0), IF(?='cancelled',1,0), IF(?='failed',1,0)) ON DUPLICATE KEY UPDATE $col = $col + 1")->execute([$skillId, $today, $event, $event, $event, $event]);
    }

    public static function validateSkill(array $data): array
    {
        $errors = [];
        if (empty($data['skill_id'])) $errors[] = 'skill_id is required';
        if (empty($data['name'])) $errors[] = 'name is required';
        if (empty($data['steps']) || !is_array($data['steps'])) $errors[] = 'steps array is required';
        if (!empty($data['skill_type']) && !in_array($data['skill_type'], ['guidance','navigation','workflow','analysis','knowledge','composite'])) $errors[] = 'Invalid skill_type';
        if (!empty($data['steps']) && is_array($data['steps'])) {
            if (count($data['steps']) > ($data['max_steps'] ?? 20)) $errors[] = 'Too many steps';
            foreach ($data['steps'] as $i => $step) {
                if (empty($step['type'])) $errors[] = "Step $i missing type";
                if (in_array($step['type'] ?? '', ['php', 'sql', 'shell', 'exec', 'eval'])) $errors[] = "Step $i has forbidden type: {$step['type']}";
            }
        }
        if (!empty($data['parent_skill_id']) && $data['parent_skill_id'] === ($data['skill_id'] ?? '')) $errors[] = 'Circular skill reference detected';
        return $errors;
    }

    public static function getTemplates(PDO $db): array
    {
        return $db->query("SELECT * FROM ai_skill_templates WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createFromTemplate(PDO $db, string $templateId, array $overrides = []): ?int
    {
        $stmt = $db->prepare("SELECT * FROM ai_skill_templates WHERE template_id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) return null;
        $steps = json_decode($template['steps_json'], true);
        $data = array_merge(['name' => $template['name'], 'description' => $template['description'], 'skill_type' => $template['template_type'], 'steps' => $steps], $overrides);
        if (empty($data['skill_id'])) $data['skill_id'] = 'custom.' . preg_replace('/[^a-z0-9]/', '_', strtolower($data['name']));
        return self::createSkill($db, $data);
    }

    public static function getPreferences(PDO $db, int $userId, ?int $companyId = null): array
    {
        $where = "user_id = ? AND is_active = 1";
        $params = [$userId];
        if ($companyId) { $where .= " AND (company_id = ? OR company_id IS NULL)"; $params[] = $companyId; }
        $stmt = $db->prepare("SELECT * FROM ai_user_preferences WHERE $where ORDER BY category, preference_key");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function setPreference(PDO $db, int $userId, string $key, string $value, ?int $companyId = null, string $category = 'general', string $source = 'user'): bool
    {
        return $db->prepare("INSERT INTO ai_user_preferences (user_id, company_id, preference_key, preference_value, category, source, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), source = VALUES(source), updated_at = NOW()")->execute([$userId, $companyId, $key, $value, $category, $source]);
    }

    public static function deletePreference(PDO $db, int $userId, string $key, ?int $companyId = null): bool
    {
        $where = "user_id = ? AND preference_key = ?";
        $params = [$userId, $key];
        if ($companyId) { $where .= " AND (company_id = ? OR company_id IS NULL)"; $params[] = $companyId; }
        return $db->prepare("UPDATE ai_user_preferences SET is_active = 0 WHERE $where")->execute($params);
    }

    public static function clearPreferences(PDO $db, int $userId): bool
    {
        return $db->prepare("UPDATE ai_user_preferences SET is_active = 0 WHERE user_id = ?")->execute([$userId]);
    }

    private static function audit(PDO $db, int $userId, string $action, string $skillId, array $details = []): void
    {
        try { $db->prepare("INSERT INTO ai_config_audit (config_type, config_id, action, new_value, user_id, created_at) VALUES ('skill', ?, ?, ?, ?, NOW())")->execute([$skillId, $action, json_encode($details), $userId]); } catch (\Throwable $e) {}
    }

    public static function registerDefaultTemplates(PDO $db): int
    {
        $templates = [
            ['template_id' => 'step_by_step_guide', 'name' => 'Step-by-Step Guide', 'description' => 'Guides user through a process step by step', 'template_type' => 'guidance', 'steps_json' => json_encode([['type' => 'navigation', 'description' => 'Navigate to the correct page'], ['type' => 'guidance', 'description' => 'Explain the current step'], ['type' => 'response', 'description' => 'Wait for user action'], ['type' => 'verification', 'description' => 'Verify step completion']])],
            ['template_id' => 'search_analyze', 'name' => 'Search & Analyze', 'description' => 'Searches data and provides analysis', 'template_type' => 'analysis', 'steps_json' => json_encode([['type' => 'tool', 'reference' => 'data.search', 'description' => 'Search for relevant data'], ['type' => 'analysis', 'description' => 'Analyze the results'], ['type' => 'response', 'description' => 'Present findings']])],
            ['template_id' => 'monthly_review', 'name' => 'Monthly Review', 'description' => 'Comprehensive monthly review workflow', 'template_type' => 'composite', 'steps_json' => json_encode([['type' => 'tool', 'reference' => 'data.search', 'description' => 'Retrieve current period data'], ['type' => 'analysis', 'description' => 'Compare with previous period'], ['type' => 'response', 'description' => 'Generate summary report']])],
            ['template_id' => 'create_record', 'name' => 'Create Record', 'description' => 'Guides through record creation', 'template_type' => 'workflow', 'steps_json' => json_encode([['type' => 'navigation', 'description' => 'Navigate to create form'], ['type' => 'guidance', 'description' => 'Explain required fields'], ['type' => 'response', 'description' => 'Wait for user to fill form'], ['type' => 'action', 'reference' => 'record.create', 'description' => 'Submit the record'], ['type' => 'verification', 'description' => 'Verify record created']])],
            ['template_id' => 'investigate_issue', 'name' => 'Investigate Issue', 'description' => 'Investigates and diagnoses an issue', 'template_type' => 'analysis', 'steps_json' => json_encode([['type' => 'guidance', 'description' => 'Understand the issue'], ['type' => 'tool', 'reference' => 'data.search', 'description' => 'Search for relevant records'], ['type' => 'analysis', 'description' => 'Analyze findings'], ['type' => 'response', 'description' => 'Present diagnosis']])],
        ];
        $count = 0;
        foreach ($templates as $t) {
            try { $db->prepare("INSERT IGNORE INTO ai_skill_templates (template_id, name, description, template_type, steps_json, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())")->execute([$t['template_id'], $t['name'], $t['description'], $t['template_type'], $t['steps_json']]); $count++; } catch (\Throwable $e) {}
        }
        return $count;
    }

    public static function registerDefaultSkills(PDO $db, int $createdBy = 1): int
    {
        $skills = [
            ['skill_id' => 'erp.navigation_assistant', 'name' => 'ERP Navigation Assistant', 'description' => 'Helps users navigate to the correct ERP page', 'module' => 'system', 'category' => 'navigation', 'skill_type' => 'navigation', 'steps' => [['type' => 'navigation', 'reference' => 'ai.navigation', 'description' => 'Find the correct page']]],
            ['skill_id' => 'procurement.review_pending_requisitions', 'name' => 'Review Pending Requisitions', 'description' => 'Reviews and analyzes pending purchase requisitions', 'module' => 'procurement', 'category' => 'procurement', 'skill_type' => 'analysis', 'required_permissions' => ['procurement.requisition.view'], 'steps' => [['type' => 'tool', 'reference' => 'procurement.search_requisitions', 'description' => 'Search pending requisitions'], ['type' => 'analysis', 'description' => 'Analyze data'], ['type' => 'response', 'description' => 'Present findings']]],
            ['skill_id' => 'procurement.create_purchase_requisition', 'name' => 'Create Purchase Requisition', 'description' => 'Guides through creating a new purchase requisition', 'module' => 'procurement', 'category' => 'procurement', 'skill_type' => 'workflow', 'safety_classification' => 'confirmation_required', 'required_permissions' => ['procurement.requisition.create'], 'steps' => [['type' => 'navigation', 'reference' => 'procurement.requisitions', 'description' => 'Navigate to requisitions'], ['type' => 'guidance', 'description' => 'Explain required fields'], ['type' => 'response', 'description' => 'Wait for user input'], ['type' => 'action', 'reference' => 'procurement.create_requisition', 'description' => 'Submit requisition']]],
            ['skill_id' => 'procurement.monthly_review', 'name' => 'Monthly Procurement Review', 'description' => 'Comprehensive monthly procurement review', 'module' => 'procurement', 'category' => 'procurement', 'skill_type' => 'composite', 'required_permissions' => ['procurement.requisition.view'], 'steps' => [['type' => 'tool', 'reference' => 'procurement.search_requisitions', 'description' => 'Retrieve pending PRs'], ['type' => 'analysis', 'description' => 'Analyze data'], ['type' => 'response', 'description' => 'Generate summary']]],
            ['skill_id' => 'ai.understand_this_module', 'name' => 'Understand This Module', 'description' => 'Teaches the user about the current ERP module', 'module' => null, 'category' => 'learning', 'skill_type' => 'guidance', 'steps' => [['type' => 'analysis', 'description' => 'Analyze module context'], ['type' => 'response', 'description' => 'Explain features']]],
            ['skill_id' => 'ai.integration.morning_meeting_assistant', 'name' => 'Morning Meeting Assistant', 'description' => 'Uses approved integration capabilities to review tomorrow\'s calendar and summarize related ERP work.', 'module' => 'ai', 'category' => 'integrations', 'skill_type' => 'workflow', 'required_permissions' => ['ai.integrations.use'], 'steps' => [['type' => 'connector_capability', 'reference' => 'external.calendar.read_events', 'description' => 'Read approved calendar events'], ['type' => 'tool', 'reference' => 'procurement.search_purchase_requisitions', 'description' => 'Read open ERP work'], ['type' => 'analysis', 'description' => 'Generate summary']]],
        ];
        $count = 0;
        foreach ($skills as $s) {
            $steps = $s['steps'];
            unset($s['steps']);
            $s['steps_json'] = json_encode($steps);
            $s['status'] = 'published';
            $s['published_at'] = date('Y-m-d H:i:s');
            $s['created_by'] = $createdBy;
            try {
                $db->prepare("INSERT IGNORE INTO ai_skills (skill_id, name, description, module, category, skill_type, status, version, required_permissions_json, steps_json, safety_classification, mode, max_steps, max_runtime_seconds, max_tool_calls, created_by, published_at, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 'guide', 20, 300, 10, ?, ?, 1, NOW(), NOW())")->execute([$s['skill_id'], $s['name'], $s['description'], $s['module'], $s['category'], $s['skill_type'], $s['status'], json_encode($s['required_permissions'] ?? []), $s['steps_json'], $s['safety_classification'] ?? 'read_only', $s['created_by'], $s['published_at']]);
                $count++;
            } catch (\Throwable $e) {}
        }
        return $count;
    }
}
