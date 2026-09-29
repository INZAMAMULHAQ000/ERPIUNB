<?php
/**
 * AI F31 — Process Intelligence & Workflow Optimization.
 *
 * Grounded process analytics over allowlisted ERP lifecycle tables.
 * No unrestricted SQL is exposed to users or to AI routing.
 */
class AIProcessIntelligenceService
{
    private PDO $db;
    private int $companyId;
    private int $userId;

    public function __construct(PDO $db, int $companyId, int $userId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    public function initialize(): void
    {
        $this->ensureProcessDefinitions();
        if ($this->countRows('ai_process_events') === 0) {
            $this->refreshProcessData();
        }
    }

    public function processRequest(string $text, array $context = []): array
    {
        if (!$this->isAnalysisAllowed()) {
            return ['success' => false, 'error' => 'Process intelligence is currently disabled.'];
        }

        $lower = strtolower(trim($text));
        if ($lower === '') {
            return ['success' => false, 'error' => 'Query cannot be empty.'];
        }

        if (preg_match('/\b(select|insert|update|delete|drop|alter|truncate|union|information_schema|sleep\s*\(|benchmark\s*\()/i', $text)) {
            return [
                'success' => false,
                'type' => 'rejected',
                'error' => 'Arbitrary SQL and database instructions are not allowed. Ask a business process question instead.',
            ];
        }

        if (class_exists('AIInjectionDetectionService')) {
            $injectionCheck = AIInjectionDetectionService::analyze(
                $this->db,
                $text,
                AIInjectionDetectionService::TRUST_USER_INPUT,
                ['module' => 'process_intelligence']
            );
            if (($injectionCheck['action'] ?? 'pass') !== 'pass') {
                return [
                    'success' => false,
                    'type' => 'rejected',
                    'error' => 'Potential prompt injection detected. Ask a business process question instead.',
                ];
            }
        }

        $this->initialize();

        if (preg_match('/\b(refresh|rebuild|rediscover|sync process)\b/i', $text)) {
            return $this->refreshProcessData();
        }

        $processKey = $context['process_key'] ?? $this->detectProcessKey($lower);

        if (preg_match('/\b(simulate|simulation|what if)\b/i', $lower)) {
            return $this->runSimulation($processKey, $context['variables'] ?? [], $context['name'] ?? 'Ad hoc simulation');
        }

        if (preg_match('/\b(metric|metrics|cycle time|cycle times|stage duration|stage durations|waiting time|waiting times|median|p90|p95|duration|durations)\b/i', $lower)) {
            return [
                'success' => true,
                'type' => 'metrics',
                'process_key' => $processKey,
                'metrics' => $this->getMetrics($processKey),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        if (preg_match('/\b(sla|breach(?:es)?|breached|at risk|compliant|compliance)\b/i', $lower)) {
            return [
                'success' => true,
                'type' => 'sla',
                'process_key' => $processKey,
                'sla' => $this->getSlaSummary($processKey),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        if (preg_match('/\b(rework|rejected|resubmitted|resubmit|loop)\b/i', $lower)) {
            return [
                'success' => true,
                'type' => 'rework',
                'process_key' => $processKey,
                'rework' => $this->getReworkSummary($processKey),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        if (preg_match('/\b(recommend|improve|optimization|optimize|reduce)\b/i', $lower)) {
            return [
                'success' => true,
                'type' => 'recommendations',
                'process_key' => $processKey,
                'recommendations' => $this->getRecommendations($processKey),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        if (preg_match('/\b(compare|comparison|between|versus|vs)\b/i', $lower)) {
            $dimension = $this->detectComparisonDimension($lower);
            return [
                'success' => true,
                'type' => 'comparison',
                'process_key' => $processKey,
                'dimension' => $dimension,
                'comparison' => $this->compareProcess($processKey, $dimension),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        if (preg_match('/\b(map|path|stage|transition)\b/i', $lower)) {
            return [
                'success' => true,
                'type' => 'process_map',
                'process_key' => $processKey,
                'map' => $this->getProcessMap($processKey),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        if (preg_match('/\b(bottleneck(?:s)?|stuck|stalled|delay(?:ed|s)?|slow(?:er|down)?|queue(?:s|d)?|backlog(?:s)?|throughput|late(?:r)?|lag(?:ging)?|approval(?:s)?|taking\s+(?:so\s+)?long|too\s+long)\b/i', $lower)) {
            return [
                'success' => true,
                'type' => 'bottlenecks',
                'process_key' => $processKey,
                'findings' => $this->getBottlenecks($processKey),
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];
        }

        return [
            'success' => true,
            'type' => 'overview',
            'overview' => $this->getOverview(),
            'route' => $this->route('modules/ai/process-intelligence/index.php'),
        ];
    }

    public function getOverview(): array
    {
        $this->initialize();
        $definitions = $this->getDefinitions();
        $runs = $this->queryAll("
            SELECT process_key, COUNT(*) AS total_runs,
                   SUM(CASE WHEN run_status = 'completed' THEN 1 ELSE 0 END) AS completed_runs,
                   SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) AS breached_runs,
                   AVG(cycle_time_hours) AS avg_cycle_hours,
                   AVG(approval_time_hours) AS avg_approval_hours
            FROM ai_process_runs
            WHERE company_id = ?
            GROUP BY process_key
            ORDER BY total_runs DESC, process_key ASC
        ", [$this->companyId]);

        $totals = [
            'definitions' => count($definitions),
            'events' => $this->countRows('ai_process_events'),
            'instances' => $this->countRows('ai_process_runs'),
            'open_findings' => $this->scalar("SELECT COUNT(*) FROM ai_process_findings WHERE company_id = ? AND status = 'open'", [$this->companyId]),
            'open_recommendations' => $this->scalar("SELECT COUNT(*) FROM ai_process_recommendations WHERE company_id = ? AND status = 'open'", [$this->companyId]),
        ];

        return [
            'totals' => $totals,
            'definitions' => $definitions,
            'processes' => $runs,
            'recent_findings' => $this->getBottlenecks(null, 6),
            'recent_simulations' => $this->queryAll("
                SELECT id, process_key, name, safety_label, created_at
                FROM ai_process_simulations
                WHERE company_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ", [$this->companyId]),
        ];
    }

    public function getMetrics(?string $processKey = null): array
    {
        $this->initialize();
        $sql = "
            SELECT process_key, process_instance_id, cycle_time_hours, waiting_time_hours, approval_time_hours,
                   queue_time_hours, rework_time_hours, run_status, sla_status, breach_hours
            FROM ai_process_runs
            WHERE company_id = ?
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $sql .= " ORDER BY process_key ASC, cycle_time_hours ASC, process_instance_id ASC";
        $runs = $this->queryAll($sql, $params);

        $grouped = [];
        foreach ($runs as $run) {
            $grouped[$run['process_key']][] = $run;
        }

        $rows = [];
        foreach ($grouped as $key => $items) {
            $cycles = array_map('floatval', array_column($items, 'cycle_time_hours'));
            $waiting = array_map('floatval', array_column($items, 'waiting_time_hours'));
            $approval = array_map('floatval', array_column($items, 'approval_time_hours'));
            $queue = array_map('floatval', array_column($items, 'queue_time_hours'));
            $rework = array_map('floatval', array_column($items, 'rework_time_hours'));
            $rows[] = [
                'process_key' => $key,
                'sample_size' => count($items),
                'total_cycle_hours' => round(array_sum($cycles), 2),
                'avg_cycle_hours' => $this->avg($cycles),
                'median_cycle_hours' => $this->percentile($cycles, 50),
                'p90_cycle_hours' => $this->percentile($cycles, 90),
                'p95_cycle_hours' => $this->percentile($cycles, 95),
                'min_cycle_hours' => empty($cycles) ? null : round(min($cycles), 2),
                'max_cycle_hours' => empty($cycles) ? null : round(max($cycles), 2),
                'avg_waiting_hours' => $this->avg($waiting),
                'avg_approval_hours' => $this->avg($approval),
                'avg_queue_hours' => $this->avg($queue),
                'avg_rework_hours' => $this->avg($rework),
                'breach_count' => count(array_filter($items, fn(array $r) => ($r['sla_status'] ?? '') === 'breached')),
                'completed_count' => count(array_filter($items, fn(array $r) => ($r['run_status'] ?? '') === 'completed')),
            ];
        }

        usort($rows, fn(array $a, array $b) => ($b['sample_size'] <=> $a['sample_size']) ?: strcmp($a['process_key'], $b['process_key']));

        return [
            'process_key' => $processKey,
            'rows' => $rows,
            'summary' => [
                'sample_size' => count($runs),
                'process_count' => count($rows),
            ],
        ];
    }

    public function getSlaSummary(?string $processKey = null): array
    {
        $this->initialize();
        $sql = "
            SELECT process_key, process_instance_id, sla_status, breach_hours, cycle_time_hours, started_at, completed_at
            FROM ai_process_runs
            WHERE company_id = ?
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $runs = $this->queryAll($sql . " ORDER BY process_key ASC, started_at ASC, process_instance_id ASC", $params);

        $total = count($runs);
        $met = count(array_filter($runs, fn(array $r) => ($r['sla_status'] ?? '') === 'met'));
        $atRisk = count(array_filter($runs, fn(array $r) => ($r['sla_status'] ?? '') === 'at_risk'));
        $breached = count(array_filter($runs, fn(array $r) => ($r['sla_status'] ?? '') === 'breached'));
        $breachHours = array_map('floatval', array_column(array_filter($runs, fn(array $r) => ($r['sla_status'] ?? '') === 'breached'), 'breach_hours'));

        $byProcess = [];
        foreach ($runs as $run) {
            $key = $run['process_key'];
            if (!isset($byProcess[$key])) {
                $byProcess[$key] = ['process_key' => $key, 'total' => 0, 'breached' => 0, 'breach_hours' => []];
            }
            $byProcess[$key]['total']++;
            if (($run['sla_status'] ?? '') === 'breached') {
                $byProcess[$key]['breached']++;
                $byProcess[$key]['breach_hours'][] = (float) ($run['breach_hours'] ?? 0);
            }
        }

        $worst = [];
        foreach ($byProcess as $row) {
            $worst[] = [
                'process_key' => $row['process_key'],
                'breach_count' => $row['breached'],
                'breach_percentage' => $row['total'] > 0 ? round(($row['breached'] / $row['total']) * 100, 2) : 0.0,
                'avg_breach_hours' => $this->avg($row['breach_hours']),
            ];
        }
        usort($worst, fn(array $a, array $b) => ($b['breach_count'] <=> $a['breach_count']) ?: strcmp($a['process_key'], $b['process_key']));

        return [
            'process_key' => $processKey,
            'sample_size' => $total,
            'met_count' => $met,
            'at_risk_count' => $atRisk,
            'breached_count' => $breached,
            'breach_percentage' => $total > 0 ? round(($breached / $total) * 100, 2) : 0.0,
            'avg_breach_hours' => $this->avg($breachHours),
            'worst_processes' => array_slice($worst, 0, 5),
        ];
    }

    public function getReworkSummary(?string $processKey = null): array
    {
        $this->initialize();
        $sql = "
            SELECT process_key, process_instance_id, rework_count, rework_time_hours, cycle_time_hours, started_at, completed_at
            FROM ai_process_runs
            WHERE company_id = ?
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $runs = $this->queryAll($sql . " ORDER BY process_key ASC, started_at ASC, process_instance_id ASC", $params);

        $reworkRuns = array_values(array_filter($runs, fn(array $r) => (int) ($r['rework_count'] ?? 0) > 0));
        $reworkHours = array_map('floatval', array_column($reworkRuns, 'rework_time_hours'));
        $extraCycleHours = array_map('floatval', array_column($reworkRuns, 'cycle_time_hours'));
        $topInstances = array_map(fn(array $r) => [
            'process_key' => $r['process_key'],
            'process_instance_id' => $r['process_instance_id'],
            'rework_count' => (int) ($r['rework_count'] ?? 0),
            'rework_time_hours' => (float) ($r['rework_time_hours'] ?? 0),
        ], array_slice($reworkRuns, 0, 10));

        return [
            'process_key' => $processKey,
            'sample_size' => count($runs),
            'rework_instance_count' => count($reworkRuns),
            'rework_rate' => count($runs) > 0 ? round((count($reworkRuns) / count($runs)) * 100, 2) : 0.0,
            'avg_rework_hours' => $this->avg($reworkHours),
            'avg_additional_cycle_hours' => $this->avg($extraCycleHours),
            'top_instances' => $topInstances,
        ];
    }

    public function getDefinitions(): array
    {
        $this->ensureProcessDefinitions();
        return $this->queryAll("
            SELECT *
            FROM ai_process_definitions
            WHERE company_id = ? AND is_active = 1
            ORDER BY name ASC
        ", [$this->companyId]);
    }

    public function refreshProcessData(): array
    {
        if (!$this->isAnalysisAllowed()) {
            return ['success' => false, 'error' => 'Process intelligence is currently disabled.'];
        }

        $this->ensureProcessDefinitions();
        $events = array_merge(
            $this->buildRequisitionToPoEvents(),
            $this->buildInvoiceToPaymentEvents()
        );

        $inserted = 0;
        $stmt = $this->db->prepare("
            INSERT INTO ai_process_events
                (company_id, process_key, process_instance_id, entity_type, entity_id, event_name, event_type,
                 actor_user_id, actor_name, department_id, branch, role_name, event_time, status, source_module,
                 source_table, source_row_id, currency_code, amount_value, metadata_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                actor_user_id = VALUES(actor_user_id),
                department_id = VALUES(department_id),
                branch = VALUES(branch),
                status = VALUES(status),
                metadata_json = VALUES(metadata_json)
        ");

        foreach ($events as $event) {
            if (empty($event['event_time'])) {
                continue;
            }
            $stmt->execute([
                $this->companyId,
                $event['process_key'],
                $event['process_instance_id'],
                $event['entity_type'],
                $event['entity_id'],
                $event['event_name'],
                $event['event_type'],
                $event['actor_user_id'],
                $event['actor_name'],
                $event['department_id'],
                $event['branch'],
                $event['role_name'],
                $event['event_time'],
                $event['status'],
                $event['source_module'],
                $event['source_table'],
                $event['source_row_id'],
                $event['currency_code'],
                $event['amount_value'],
                json_encode($event['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $inserted++;
        }

        $runCount = $this->rebuildRunSnapshots();
        $this->refreshDailyMetrics();
        $findings = $this->detectFindings();
        $this->audit('process_data_refreshed', ['events_written' => $inserted, 'runs_rebuilt' => $runCount, 'findings' => count($findings)]);

        return [
            'success' => true,
            'events_processed' => $inserted,
            'runs_rebuilt' => $runCount,
            'findings_detected' => count($findings),
        ];
    }

    public function getProcessExplorer(?string $processKey = null, int $limit = 25): array
    {
        $this->initialize();
        $sql = "
            SELECT *
            FROM ai_process_runs
            WHERE company_id = ?
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $sql .= " ORDER BY last_event_at DESC, started_at DESC LIMIT " . max(1, min(250, $limit));
        return $this->queryAll($sql, $params);
    }

    public function getProcessMap(?string $processKey = null): array
    {
        $this->initialize();
        $events = $this->fetchEvents($processKey);
        $transitions = [];
        $stages = [];

        foreach ($events as $instanceEvents) {
            foreach ($instanceEvents as $index => $event) {
                $stages[$event['event_name']] = ($stages[$event['event_name']] ?? 0) + 1;
                if (!isset($instanceEvents[$index + 1])) {
                    continue;
                }
                $next = $instanceEvents[$index + 1];
                $key = $event['event_name'] . ' -> ' . $next['event_name'];
                if (!isset($transitions[$key])) {
                    $transitions[$key] = [
                        'from' => $event['event_name'],
                        'to' => $next['event_name'],
                        'count' => 0,
                        'durations' => [],
                    ];
                }
                $transitions[$key]['count']++;
                $transitions[$key]['durations'][] = $this->hoursBetween($event['event_time'], $next['event_time']);
            }
        }

        $rows = [];
        foreach ($transitions as $transition) {
            $durations = $transition['durations'];
            sort($durations);
            $rows[] = [
                'from' => $transition['from'],
                'to' => $transition['to'],
                'frequency' => $transition['count'],
                'average_duration_hours' => $this->avg($durations),
                'median_duration_hours' => $this->percentile($durations, 50),
                'p90_duration_hours' => $this->percentile($durations, 90),
                'slow_transition' => $this->avg($durations) >= 24,
            ];
        }

        usort($rows, fn(array $a, array $b) => ($b['frequency'] <=> $a['frequency']) ?: strcmp($a['from'], $b['from']));

        $processRuns = $this->getProcessExplorer($processKey, 250);
        $abandoned = count(array_filter($processRuns, fn(array $run) => ($run['run_status'] ?? '') === 'active' && strtotime((string) ($run['last_event_at'] ?? 'now')) < strtotime('-7 days')));
        $reworkLoops = array_values(array_filter($processRuns, fn(array $run) => (int) ($run['rework_count'] ?? 0) > 0));

        return [
            'process_key' => $processKey,
            'stages' => array_map(fn(string $name) => ['stage' => $name, 'frequency' => $stages[$name]], array_keys($stages)),
            'transitions' => $rows,
            'abandoned_instances' => $abandoned,
            'rework_instances' => count($reworkLoops),
        ];
    }

    public function getBottlenecks(?string $processKey = null, int $limit = 12): array
    {
        $this->initialize();
        $this->detectFindings($processKey);
        $sql = "
            SELECT *
            FROM ai_process_findings
            WHERE company_id = ? AND status = 'open'
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $sql .= " ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), detected_at DESC LIMIT " . max(1, min(50, $limit));
        return $this->queryAll($sql, $params);
    }

    public function compareProcess(?string $processKey, string $dimension = 'department'): array
    {
        $this->initialize();
        $field = match ($dimension) {
            'branch' => 'branch',
            'currency' => 'currency_code',
            'status' => 'run_status',
            default => 'department_id',
        };
        $sql = "
            SELECT {$field} AS dimension_value,
                   COUNT(*) AS instance_count,
                   AVG(cycle_time_hours) AS avg_cycle_hours,
                   AVG(approval_time_hours) AS avg_approval_hours,
                   SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) AS breach_count
            FROM ai_process_runs
            WHERE company_id = ?
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $sql .= " GROUP BY {$field} ORDER BY instance_count DESC, avg_cycle_hours DESC";

        return [
            'process_key' => $processKey,
            'dimension' => $dimension,
            'rows' => $this->queryAll($sql, $params),
        ];
    }

    public function getRecommendations(?string $processKey = null): array
    {
        $this->initialize();
        $findings = $this->getBottlenecks($processKey, 20);
        foreach ($findings as $finding) {
            $fingerprint = sha1('rec|' . ($finding['fingerprint'] ?? '') . '|' . ($finding['process_key'] ?? ''));
            $title = 'Recommendation: ' . ($finding['title'] ?? 'Process improvement');
            $text = $this->recommendationTextForFinding($finding);
            $this->db->prepare("
                INSERT INTO ai_process_recommendations
                    (company_id, process_key, finding_id, title, recommendation_text, expected_impact,
                     rationale, evidence_json, advisory_only, priority_level, status, fingerprint, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'open', ?, ?)
                ON DUPLICATE KEY UPDATE
                    expected_impact = VALUES(expected_impact),
                    rationale = VALUES(rationale),
                    evidence_json = VALUES(evidence_json),
                    updated_at = NOW()
            ")->execute([
                $this->companyId,
                $finding['process_key'],
                $finding['id'],
                $title,
                $text['recommendation'],
                $text['impact'],
                $text['rationale'],
                $finding['evidence_json'],
                $finding['severity'] === 'critical' ? 'high' : 'medium',
                $fingerprint,
                $this->userId,
            ]);
        }

        $sql = "
            SELECT *
            FROM ai_process_recommendations
            WHERE company_id = ? AND status = 'open'
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $sql .= " ORDER BY FIELD(priority_level, 'high', 'medium', 'low'), created_at DESC";
        return $this->queryAll($sql, $params);
    }

    public function runSimulation(?string $processKey, array $variables, string $name): array
    {
        $this->initialize();
        $baselineRows = $this->queryAll("
            SELECT process_key, COUNT(*) AS instance_count, AVG(cycle_time_hours) AS avg_cycle_hours,
                   AVG(approval_time_hours) AS avg_approval_hours, AVG(queue_time_hours) AS avg_queue_hours,
                   AVG(rework_time_hours) AS avg_rework_hours
            FROM ai_process_runs
            WHERE company_id = ?" . ($processKey ? " AND process_key = ?" : "") . "
            GROUP BY process_key
            " . ($processKey ? "" : "HAVING AVG(cycle_time_hours) > 0") . "
            ORDER BY instance_count DESC, avg_cycle_hours DESC
        ", $processKey ? [$this->companyId, $processKey] : [$this->companyId]);

        if (empty($baselineRows)) {
            return ['success' => false, 'error' => 'No historical process data available for simulation.'];
        }

        $baseline = $baselineRows[0];
        $selectedProcessKey = (string) ($baseline['process_key'] ?? $processKey ?? '');
        $historyRows = $this->queryAll("
            SELECT
                COUNT(*) AS sample_size,
                MIN(started_at) AS historical_start,
                MAX(COALESCE(completed_at, started_at)) AS historical_end,
                MIN(cycle_time_hours) AS min_cycle_hours,
                MAX(cycle_time_hours) AS max_cycle_hours,
                STDDEV_SAMP(cycle_time_hours) AS stddev_cycle_hours
            FROM ai_process_runs
            WHERE company_id = ? AND process_key = ?
        ", [$this->companyId, $selectedProcessKey]);
        $historySummary = $historyRows[0] ?? [];
        $sampleSize = (int) ($historySummary['sample_size'] ?? 0);
        if ($sampleSize < 3) {
            return [
                'success' => false,
                'error' => 'Insufficient historical data for simulation. Need at least 3 data points, got ' . $sampleSize . '.',
            ];
        }

        $approvalReduction = max(-100, min(100, (float) ($variables['approval_reduction_pct'] ?? 0)));
        $queueReduction = max(-100, min(100, (float) ($variables['queue_reduction_pct'] ?? 0)));
        $reworkReduction = max(-100, min(100, (float) ($variables['rework_reduction_pct'] ?? 0)));
        $volumeChange = max(-100, min(300, (float) ($variables['volume_change_pct'] ?? 0)));

        $approvalHours = max(0.0, (float) ($baseline['avg_approval_hours'] ?? 0) * (1 - ($approvalReduction / 100)));
        $queueHours = max(0.0, (float) ($baseline['avg_queue_hours'] ?? 0) * (1 - ($queueReduction / 100)));
        $reworkHours = max(0.0, (float) ($baseline['avg_rework_hours'] ?? 0) * (1 - ($reworkReduction / 100)));
        $otherHours = max(0.0, (float) ($baseline['avg_cycle_hours'] ?? 0) - (float) ($baseline['avg_approval_hours'] ?? 0) - (float) ($baseline['avg_queue_hours'] ?? 0) - (float) ($baseline['avg_rework_hours'] ?? 0));
        $simulatedCycle = round(($approvalHours + $queueHours + $reworkHours + $otherHours) * (1 + ($volumeChange / 250)), 2);
        $impactPct = ($baseline['avg_cycle_hours'] ?? 0) > 0
            ? round((($simulatedCycle - (float) $baseline['avg_cycle_hours']) / (float) $baseline['avg_cycle_hours']) * 100, 2)
            : 0.0;

        $stddev = (float) ($historySummary['stddev_cycle_hours'] ?? 0);
        $minCycle = (float) ($historySummary['min_cycle_hours'] ?? 0);
        $maxCycle = (float) ($historySummary['max_cycle_hours'] ?? 0);
        $coefficientOfVariation = ((float) ($baseline['avg_cycle_hours'] ?? 0) > 0)
            ? $stddev / (float) $baseline['avg_cycle_hours']
            : 0.0;

        $confidenceLevel = 'medium';
        $confidenceScore = 0.72;
        if ($sampleSize >= 8 && $coefficientOfVariation < 0.25) {
            $confidenceLevel = 'high';
            $confidenceScore = 0.9;
        } elseif ($sampleSize >= 5 && $coefficientOfVariation < 0.5) {
            $confidenceLevel = 'medium';
            $confidenceScore = 0.78;
        } else {
            $confidenceLevel = 'low';
            $confidenceScore = 0.62;
        }

        $result = [
            'success' => true,
            'process_key' => $baseline['process_key'],
            'simulation_name' => $name,
            'method' => 'Deterministic historical-average adjustment',
            'variables' => $variables,
            'baseline' => $baseline,
            'simulated_cycle_hours' => $simulatedCycle,
            'impact_percentage' => $impactPct,
            'historical_range' => [
                'start' => $historySummary['historical_start'] ?? null,
                'end' => $historySummary['historical_end'] ?? null,
                'count' => $sampleSize,
            ],
            'uncertainty' => [
                'confidence_level' => $confidenceLevel,
                'confidence_score' => $confidenceScore,
                'sample_size' => $sampleSize,
                'cycle_time_stddev_hours' => round($stddev, 2),
                'cycle_time_range_hours' => [
                    'min' => round($minCycle, 2),
                    'max' => round($maxCycle, 2),
                ],
                'notes' => [
                    'Derived from authorized historical process runs only.',
                    'Variance is based on observed cycle times, not AI-generated estimates.',
                    'Simulation output is advisory and does not modify production ERP records.',
                ],
            ],
            'assumptions' => [
                'Simulation uses current grounded historical averages from authorized ERP events.',
                'Changes are applied only as estimates to approval, queue, rework, and volume components.',
                'No production workflow or ERP record is modified.',
            ],
            'safety_label' => 'Simulation / Estimate — not actual ERP results.',
        ];

        $this->db->prepare("
            INSERT INTO ai_process_simulations
                (company_id, process_key, name, variables_json, baseline_snapshot_json, simulation_result_json, safety_label, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $this->companyId,
            $baseline['process_key'],
            $name,
            json_encode($variables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($baseline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $result['safety_label'],
            $this->userId,
        ]);

        $this->recordObservability('process_simulation', $baseline['process_key'], $result);
        return $result;
    }

    public function createTaskFromRecommendation(int $recommendationId): array
    {
        if (AIEmergencyControlsService::isControlActive($this->db, 'disable_ai_actions')) {
            return ['success' => false, 'error' => 'AI actions are currently disabled.'];
        }

        $recommendation = $this->getRecommendationById($recommendationId);
        if (!$recommendation) {
            return ['success' => false, 'error' => 'Recommendation not found.'];
        }

        $task = AITaskService::createTask(
            $this->db,
            $this->userId,
            $recommendation['title'],
            $recommendation['recommendation_text'],
            'Created from AI Process Intelligence recommendation #' . $recommendationId
        );

        if (!empty($task['success'])) {
            $this->audit('process_recommendation_task_created', [
                'recommendation_id' => $recommendationId,
                'task_id' => $task['id'],
                'process_key' => $recommendation['process_key'],
            ]);
        }

        return $task;
    }

    public function createProposalFromRecommendation(int $recommendationId): array
    {
        if (AIEmergencyControlsService::isControlActive($this->db, 'disable_ai_actions')) {
            return ['success' => false, 'error' => 'AI actions are currently disabled.'];
        }

        $recommendation = $this->getRecommendationById($recommendationId);
        if (!$recommendation) {
            return ['success' => false, 'error' => 'Recommendation not found.'];
        }

        $workflowId = null;
        $workflowVersionId = null;
        $summary = 'Prepared for governance review. No production workflow has been changed.';
        $payload = [
            'process_key' => $recommendation['process_key'],
            'recommendation_id' => $recommendationId,
            'recommendation_text' => $recommendation['recommendation_text'],
            'advisory_only' => true,
        ];

        if (class_exists('AIWorkflowService') && AIKillSwitchService::isEnabled($this->db, 'workflow_generation')) {
            $draft = AIWorkflowService::generateDraft(
                $this->db,
                $this->userId,
                $recommendation['recommendation_text'],
                ['module' => 'ai', 'source' => 'process_intelligence'],
                true
            );
            if (!empty($draft['success'])) {
                $workflowId = (int) ($draft['workflow_id'] ?? 0) ?: null;
                if ($workflowId) {
                    $workflow = AIWorkflowService::getWorkflow($this->db, $workflowId);
                    $workflowVersionId = (int) ($workflow['current_version_id'] ?? 0) ?: null;
                    $payload['workflow_id'] = $workflowId;
                    $payload['workflow_version_id'] = $workflowVersionId;
                    $summary = 'Draft workflow prepared in Workflow Studio. Review, test, approve, and publish separately.';
                }
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO ai_process_proposals
                (company_id, process_key, recommendation_id, workflow_id, workflow_version_id, title, summary,
                 proposal_payload_json, simulation_result_json, approval_status, governance_status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'pending_review', ?)
        ");
        $stmt->execute([
            $this->companyId,
            $recommendation['process_key'],
            $recommendationId,
            $workflowId,
            $workflowVersionId,
            $recommendation['title'],
            $summary,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            null,
            $this->userId,
        ]);
        $proposalId = (int) $this->db->lastInsertId();

        $this->audit('process_proposal_created', [
            'proposal_id' => $proposalId,
            'recommendation_id' => $recommendationId,
            'workflow_id' => $workflowId,
        ]);

        return [
            'success' => true,
            'proposal_id' => $proposalId,
            'workflow_id' => $workflowId,
            'message' => $summary,
        ];
    }

    public function scheduleAnalysis(string $processKey, array $schedule): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_process_alerts
                (company_id, process_key, alert_type, schedule_json, status, created_by)
            VALUES (?, ?, 'scheduled_analysis', ?, 'active', ?)
        ");
        $stmt->execute([
            $this->companyId,
            $processKey,
            json_encode($schedule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $this->userId,
        ]);
        return ['success' => true, 'alert_id' => (int) $this->db->lastInsertId()];
    }

    public function runDueScheduledAnalyses(): array
    {
        $alerts = $this->queryAll("
            SELECT *
            FROM ai_process_alerts
            WHERE company_id = ? AND alert_type = 'scheduled_analysis' AND status = 'active'
            ORDER BY updated_at DESC
        ", [$this->companyId]);

        $executed = [];
        foreach ($alerts as $alert) {
            $schedule = json_decode((string) ($alert['schedule_json'] ?? 'null'), true);
            if (!$this->isScheduleDue($schedule, $alert['last_evaluated_at'] ?? null)) {
                continue;
            }
            $findings = $this->getBottlenecks($alert['process_key'], 5);
            $this->db->prepare("
                UPDATE ai_process_alerts
                SET last_evaluated_at = NOW(), last_triggered_at = IF(? > 0, NOW(), last_triggered_at), updated_at = NOW()
                WHERE id = ?
            ")->execute([count($findings), $alert['id']]);

            $executed[] = [
                'alert_id' => (int) $alert['id'],
                'process_key' => $alert['process_key'],
                'finding_count' => count($findings),
            ];
        }

        return ['success' => true, 'executed_count' => count($executed), 'executed' => $executed];
    }

    public function getHistory(int $limit = 25): array
    {
        return [
            'findings' => $this->queryAll("
                SELECT id, process_key, title, severity, status, detected_at
                FROM ai_process_findings
                WHERE company_id = ?
                ORDER BY detected_at DESC
                LIMIT " . max(1, min(100, $limit)), [$this->companyId]),
            'recommendations' => $this->queryAll("
                SELECT id, process_key, title, priority_level, status, created_at
                FROM ai_process_recommendations
                WHERE company_id = ?
                ORDER BY created_at DESC
                LIMIT " . max(1, min(100, $limit)), [$this->companyId]),
            'proposals' => $this->queryAll("
                SELECT id, process_key, title, approval_status, governance_status, workflow_id, created_at
                FROM ai_process_proposals
                WHERE company_id = ?
                ORDER BY created_at DESC
                LIMIT " . max(1, min(100, $limit)), [$this->companyId]),
        ];
    }

    private function ensureProcessDefinitions(): void
    {
        $definitions = [];
        if ($this->tableExists('purchase_requisitions') && $this->tableExists('purchase_orders')) {
            $definitions[] = [
                'process_key' => 'requisition_to_po',
                'name' => 'Purchase Requisition to Purchase Order',
                'description' => 'Tracks requisition approval and PO creation lifecycle.',
                'source_modules' => ['procurement', 'finance'],
                'entity_type' => 'purchase_requisition',
                'start_event' => 'requisition_created',
                'completion_event' => 'po_closed',
                'expected_stages' => ['requisition_created', 'requisition_submitted', 'requisition_approved', 'po_created', 'po_approved', 'po_closed'],
                'sla_target_hours' => 72,
                'business_dimensions' => ['department', 'branch', 'currency', 'status'],
                'permissions_required' => 'ai.process_intelligence.view',
            ];
        }
        if (($this->tableExists('customer_invoices') || $this->tableExists('invoices')) && $this->tableExists('payments')) {
            $definitions[] = [
                'process_key' => 'invoice_to_payment',
                'name' => 'Invoice to Payment',
                'description' => 'Tracks invoice lifecycle through payment collection.',
                'source_modules' => ['finance', 'sales'],
                'entity_type' => 'invoice',
                'start_event' => 'invoice_created',
                'completion_event' => 'payment_received',
                'expected_stages' => ['invoice_created', 'invoice_approved', 'invoice_sent', 'invoice_due', 'payment_received'],
                'sla_target_hours' => 168,
                'business_dimensions' => ['customer', 'branch', 'currency', 'status'],
                'permissions_required' => 'ai.process_intelligence.view',
            ];
        }

        $stmt = $this->db->prepare("
            INSERT INTO ai_process_definitions
                (company_id, process_key, name, description, source_modules, entity_type, start_event,
                 completion_event, expected_stages, sla_target_hours, business_dimensions, visibility,
                 permissions_required, is_active, metadata, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'company', ?, 1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                source_modules = VALUES(source_modules),
                completion_event = VALUES(completion_event),
                expected_stages = VALUES(expected_stages),
                sla_target_hours = VALUES(sla_target_hours),
                business_dimensions = VALUES(business_dimensions),
                permissions_required = VALUES(permissions_required),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");

        foreach ($definitions as $definition) {
            $stmt->execute([
                $this->companyId,
                $definition['process_key'],
                $definition['name'],
                $definition['description'],
                json_encode($definition['source_modules']),
                $definition['entity_type'],
                $definition['start_event'],
                $definition['completion_event'],
                json_encode($definition['expected_stages']),
                $definition['sla_target_hours'],
                json_encode($definition['business_dimensions']),
                $definition['permissions_required'],
                json_encode(['seeded_by' => 'f31', 'company_id' => $this->companyId]),
                $this->userId,
                $this->userId,
            ]);

            $existing = (int) $this->scalar("
                SELECT COUNT(*)
                FROM ai_process_versions
                WHERE company_id = ? AND process_key = ?
            ", [$this->companyId, $definition['process_key']]);
            if ($existing === 0) {
                $this->db->prepare("
                    INSERT INTO ai_process_versions (company_id, process_key, version_number, definition_snapshot_json, created_by)
                    VALUES (?, ?, 1, ?, ?)
                ")->execute([
                    $this->companyId,
                    $definition['process_key'],
                    json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $this->userId,
                ]);
            }
        }
    }

    private function buildRequisitionToPoEvents(): array
    {
        if (!$this->tableExists('purchase_requisitions') || !$this->tableExists('purchase_orders')) {
            return [];
        }

        $rows = $this->queryAll("
            SELECT pr.*, po.id AS po_id, po.po_number, po.po_date, po.expected_delivery_date, po.status AS po_status,
                   po.created_at AS po_created_at, po.approved_at AS po_approved_at, po.sent_at, po.acknowledged_at,
                   po.closed_at, po.updated_at AS po_updated_at, po.currency AS po_currency, po.grand_total AS po_grand_total,
                   po.approved_by AS po_approved_by, po.closed_by, po.sent_by, po.acknowledged_by
            FROM purchase_requisitions pr
            LEFT JOIN purchase_orders po ON po.pr_id = pr.id AND po.company_id = pr.company_id
            WHERE pr.company_id = ?
            ORDER BY pr.id ASC, po.id ASC
        ", [$this->companyId]);

        $events = [];
        foreach ($rows as $row) {
            $instanceId = 'pr-' . (int) $row['id'];
            $common = [
                'process_key' => 'requisition_to_po',
                'process_instance_id' => $instanceId,
                'entity_type' => 'purchase_requisition',
                'entity_id' => (int) $row['id'],
                'event_type' => 'status_change',
                'department_id' => (int) ($row['department_id'] ?? 0) ?: null,
                'branch' => $row['branch'] ?? null,
                'role_name' => null,
                'source_module' => 'procurement',
                'currency_code' => $row['currency'] ?? $row['po_currency'] ?? null,
                'amount_value' => isset($row['grand_total']) ? (float) $row['grand_total'] : null,
                'metadata' => [
                    'pr_number' => $row['pr_number'] ?? null,
                    'po_number' => $row['po_number'] ?? null,
                    'pr_status' => $row['status'] ?? null,
                    'po_status' => $row['po_status'] ?? null,
                ],
            ];

            $events[] = $common + [
                'event_name' => 'requisition_created',
                'actor_user_id' => (int) ($row['created_by'] ?? 0) ?: null,
                'actor_name' => null,
                'event_time' => $this->normalizeDateTime($row['created_at'] ?? $row['request_date'] ?? null),
                'status' => $row['status'] ?? 'draft',
                'source_table' => 'purchase_requisitions',
                'source_row_id' => (int) $row['id'],
            ];

            foreach ([
                'submitted_at' => ['requisition_submitted', 'submitted'],
                'approved_at' => ['requisition_approved', 'approved'],
                'cancelled_at' => ['requisition_cancelled', 'cancelled'],
            ] as $field => $meta) {
                if (empty($row[$field])) {
                    continue;
                }
                $events[] = $common + [
                    'event_name' => $meta[0],
                    'actor_user_id' => (int) (($field === 'approved_at' ? ($row['approved_by'] ?? 0) : ($row['cancelled_by'] ?? 0))) ?: null,
                    'actor_name' => null,
                    'event_time' => $this->normalizeDateTime($row[$field]),
                    'status' => $meta[1],
                    'source_table' => 'purchase_requisitions',
                    'source_row_id' => (int) $row['id'],
                ];
            }

            if (!empty($row['po_id'])) {
                $poCommon = $common + [
                    'entity_type' => 'purchase_order',
                    'entity_id' => (int) $row['po_id'],
                    'source_table' => 'purchase_orders',
                    'source_row_id' => (int) $row['po_id'],
                    'currency_code' => $row['po_currency'] ?? $row['currency'] ?? null,
                    'amount_value' => isset($row['po_grand_total']) ? (float) $row['po_grand_total'] : null,
                ];
                $events[] = $poCommon + [
                    'event_name' => 'po_created',
                    'actor_user_id' => null,
                    'actor_name' => null,
                    'event_time' => $this->normalizeDateTime($row['po_created_at'] ?? $row['po_date'] ?? null),
                    'status' => $row['po_status'] ?? 'draft',
                ];
                foreach ([
                    'po_approved_at' => ['po_approved', $row['po_approved_by'] ?? null],
                    'sent_at' => ['po_sent', $row['sent_by'] ?? null],
                    'acknowledged_at' => ['po_acknowledged', $row['acknowledged_by'] ?? null],
                    'closed_at' => ['po_closed', $row['closed_by'] ?? null],
                ] as $field => $meta) {
                    if (empty($row[$field])) {
                        continue;
                    }
                    $events[] = $poCommon + [
                        'event_name' => $meta[0],
                        'actor_user_id' => (int) ($meta[1] ?? 0) ?: null,
                        'actor_name' => null,
                        'event_time' => $this->normalizeDateTime($row[$field]),
                        'status' => $row['po_status'] ?? null,
                    ];
                }
                if (in_array($row['po_status'] ?? '', ['partially_received', 'fully_received'], true)) {
                    $events[] = $poCommon + [
                        'event_name' => 'goods_received',
                        'actor_user_id' => null,
                        'actor_name' => null,
                        'event_time' => $this->normalizeDateTime($row['po_updated_at'] ?? $row['acknowledged_at'] ?? $row['po_date'] ?? null),
                        'status' => $row['po_status'],
                    ];
                }
            }
        }

        return $events;
    }

    private function buildInvoiceToPaymentEvents(): array
    {
        $invoiceTable = $this->tableExists('customer_invoices') ? 'customer_invoices' : ($this->tableExists('invoices') ? 'invoices' : null);
        if ($invoiceTable === null || !$this->tableExists('payments')) {
            return [];
        }

        $invoiceAmount = $invoiceTable === 'customer_invoices' ? 'total_amount' : 'total_amount';
        $invoiceCurrency = $invoiceTable === 'customer_invoices' ? 'currency' : "'USD'";
        $rows = $this->queryAll("
            SELECT i.*, p.id AS payment_id, p.payment_date, p.amount AS payment_amount, p.reference_number, p.received_by, p.created_at AS payment_created_at
            FROM {$invoiceTable} i
            LEFT JOIN payments p ON p.invoice_id = i.id
            ORDER BY i.id ASC, p.id ASC
        ");

        $events = [];
        foreach ($rows as $row) {
            $instanceId = 'invoice-' . (int) $row['id'];
            $common = [
                'process_key' => 'invoice_to_payment',
                'process_instance_id' => $instanceId,
                'entity_type' => 'invoice',
                'entity_id' => (int) $row['id'],
                'event_type' => 'status_change',
                'department_id' => null,
                'branch' => null,
                'role_name' => null,
                'source_module' => 'finance',
                'currency_code' => $row['currency'] ?? null,
                'amount_value' => isset($row[$invoiceAmount]) ? (float) $row[$invoiceAmount] : null,
                'metadata' => [
                    'invoice_number' => $row['invoice_number'] ?? null,
                    'invoice_status' => $row['status'] ?? null,
                    'balance_amount' => $row['balance_amount'] ?? null,
                ],
            ];

            $events[] = $common + [
                'event_name' => 'invoice_created',
                'actor_user_id' => (int) ($row['created_by'] ?? 0) ?: null,
                'actor_name' => null,
                'event_time' => $this->normalizeDateTime($row['created_at'] ?? $row['invoice_date'] ?? null),
                'status' => $row['status'] ?? 'draft',
                'source_table' => $invoiceTable,
                'source_row_id' => (int) $row['id'],
            ];

            if (($row['status'] ?? '') === 'approved') {
                $events[] = $common + [
                    'event_name' => 'invoice_approved',
                    'actor_user_id' => null,
                    'actor_name' => null,
                    'event_time' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'status' => 'approved',
                    'source_table' => $invoiceTable,
                    'source_row_id' => (int) $row['id'],
                ];
            }
            if (in_array($row['status'] ?? '', ['sent', 'partially_paid', 'paid', 'overdue'], true)) {
                $events[] = $common + [
                    'event_name' => 'invoice_sent',
                    'actor_user_id' => null,
                    'actor_name' => null,
                    'event_time' => $this->normalizeDateTime($row['updated_at'] ?? $row['invoice_date'] ?? null),
                    'status' => $row['status'],
                    'source_table' => $invoiceTable,
                    'source_row_id' => (int) $row['id'],
                ];
            }
            if (($row['status'] ?? '') === 'overdue') {
                $events[] = $common + [
                    'event_name' => 'invoice_due',
                    'actor_user_id' => null,
                    'actor_name' => null,
                    'event_time' => $this->normalizeDateTime($row['due_date'] ?? $row['updated_at'] ?? null),
                    'status' => 'overdue',
                    'source_table' => $invoiceTable,
                    'source_row_id' => (int) $row['id'],
                ];
            }
            if (!empty($row['payment_id'])) {
                $events[] = $common + [
                    'event_name' => 'payment_received',
                    'actor_user_id' => (int) ($row['received_by'] ?? 0) ?: null,
                    'actor_name' => null,
                    'event_time' => $this->normalizeDateTime($row['payment_date'] ?? $row['payment_created_at'] ?? null),
                    'status' => 'paid',
                    'source_table' => 'payments',
                    'source_row_id' => (int) $row['payment_id'],
                    'amount_value' => isset($row['payment_amount']) ? (float) $row['payment_amount'] : null,
                ];
            }
        }

        return $events;
    }

    private function rebuildRunSnapshots(): int
    {
        $definitions = [];
        foreach ($this->getDefinitions() as $definition) {
            $definitions[$definition['process_key']] = $definition;
        }

        $events = $this->fetchEvents();
        $stmt = $this->db->prepare("
            INSERT INTO ai_process_runs
                (company_id, process_key, process_instance_id, entity_type, entity_id, department_id, branch, currency_code,
                 process_version, run_status, started_at, completed_at, cycle_time_hours, waiting_time_hours, active_time_hours,
                 approval_time_hours, queue_time_hours, handoff_time_hours, rework_time_hours, handoff_count, rework_count,
                 sla_target_hours, sla_status, breach_hours, event_count, last_event_name, last_event_at, dimensions_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                entity_type = VALUES(entity_type),
                entity_id = VALUES(entity_id),
                department_id = VALUES(department_id),
                branch = VALUES(branch),
                currency_code = VALUES(currency_code),
                run_status = VALUES(run_status),
                started_at = VALUES(started_at),
                completed_at = VALUES(completed_at),
                cycle_time_hours = VALUES(cycle_time_hours),
                waiting_time_hours = VALUES(waiting_time_hours),
                active_time_hours = VALUES(active_time_hours),
                approval_time_hours = VALUES(approval_time_hours),
                queue_time_hours = VALUES(queue_time_hours),
                handoff_time_hours = VALUES(handoff_time_hours),
                rework_time_hours = VALUES(rework_time_hours),
                handoff_count = VALUES(handoff_count),
                rework_count = VALUES(rework_count),
                sla_target_hours = VALUES(sla_target_hours),
                sla_status = VALUES(sla_status),
                breach_hours = VALUES(breach_hours),
                event_count = VALUES(event_count),
                last_event_name = VALUES(last_event_name),
                last_event_at = VALUES(last_event_at),
                dimensions_json = VALUES(dimensions_json),
                updated_at = NOW()
        ");

        $count = 0;
        foreach ($events as $key => $instanceEvents) {
            $first = $instanceEvents[0];
            $last = $instanceEvents[count($instanceEvents) - 1];
            $definition = $definitions[$first['process_key']] ?? [];
            $durations = [];
            $approvalHours = 0.0;
            $handoffCount = 0;
            $reworkCount = 0;
            $seenStages = [];
            $previousActor = null;

            for ($i = 0; $i < count($instanceEvents) - 1; $i++) {
                $current = $instanceEvents[$i];
                $next = $instanceEvents[$i + 1];
                $hours = $this->hoursBetween($current['event_time'], $next['event_time']);
                $durations[] = $hours;
                if (str_contains($current['event_name'], 'approval') || str_contains($current['event_name'], 'submitted') || str_contains($next['event_name'], 'approved')) {
                    $approvalHours += $hours;
                }
                if ($previousActor !== null && $current['actor_user_id'] !== null && $previousActor !== $current['actor_user_id']) {
                    $handoffCount++;
                }
                $previousActor = $current['actor_user_id'];
                if (isset($seenStages[$current['event_name']])) {
                    $reworkCount++;
                }
                $seenStages[$current['event_name']] = true;
            }

            $cycleHours = $last['event_time'] ? $this->hoursBetween($first['event_time'], $last['event_time']) : null;
            $completed = $this->isCompletionEvent($last['event_name']);
            $slaTarget = isset($definition['sla_target_hours']) ? (float) $definition['sla_target_hours'] : null;
            $slaStatus = 'unknown';
            $breachHours = null;
            if ($slaTarget !== null && $cycleHours !== null) {
                if ($cycleHours > $slaTarget) {
                    $slaStatus = 'breached';
                    $breachHours = round($cycleHours - $slaTarget, 2);
                } elseif ($cycleHours >= ($slaTarget * 0.8)) {
                    $slaStatus = 'at_risk';
                    $breachHours = 0.0;
                } else {
                    $slaStatus = 'met';
                    $breachHours = 0.0;
                }
            }

            $dimensions = [
                'department_id' => $first['department_id'],
                'branch' => $first['branch'],
                'currency_code' => $first['currency_code'],
                'last_status' => $last['status'],
                'source_module' => $first['source_module'],
            ];

            $stmt->execute([
                $this->companyId,
                $first['process_key'],
                $first['process_instance_id'],
                $first['entity_type'],
                $first['entity_id'],
                $first['department_id'] ?: null,
                $first['branch'],
                $first['currency_code'],
                'v1',
                $completed ? 'completed' : 'active',
                $first['event_time'],
                $completed ? $last['event_time'] : null,
                $cycleHours,
                $cycleHours,
                0.0,
                round($approvalHours, 2),
                $cycleHours,
                0.0,
                $reworkCount > 0 ? round(array_sum($durations) * min(0.15, $reworkCount / 10), 2) : 0.0,
                $handoffCount,
                $reworkCount,
                $slaTarget,
                $slaStatus,
                $breachHours,
                count($instanceEvents),
                $last['event_name'],
                $last['event_time'],
                json_encode($dimensions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $count++;
        }

        return $count;
    }

    private function refreshDailyMetrics(): void
    {
        $rows = $this->queryAll("
            SELECT process_key, DATE(started_at) AS metric_date,
                   COUNT(*) AS instance_count,
                   SUM(CASE WHEN run_status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                   SUM(CASE WHEN run_status = 'active' THEN 1 ELSE 0 END) AS active_count,
                   SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) AS breach_count,
                   AVG(cycle_time_hours) AS avg_cycle_hours,
                   AVG(approval_time_hours) AS avg_approval_hours,
                   AVG(rework_time_hours) AS avg_rework_hours,
                   AVG(queue_time_hours) AS avg_queue_hours
            FROM ai_process_runs
            WHERE company_id = ?
            GROUP BY process_key, DATE(started_at)
        ", [$this->companyId]);

        $insert = $this->db->prepare("
            INSERT INTO ai_process_metrics
                (company_id, process_key, metric_date, dimension_key, dimension_value, instance_count, completed_count,
                 active_count, abandoned_count, breach_count, avg_cycle_hours, median_cycle_hours, p90_cycle_hours,
                 avg_approval_hours, avg_rework_hours, avg_queue_hours, sample_size, metric_payload_json)
            VALUES (?, ?, ?, 'all', 'all', ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                instance_count = VALUES(instance_count),
                completed_count = VALUES(completed_count),
                active_count = VALUES(active_count),
                breach_count = VALUES(breach_count),
                avg_cycle_hours = VALUES(avg_cycle_hours),
                median_cycle_hours = VALUES(median_cycle_hours),
                p90_cycle_hours = VALUES(p90_cycle_hours),
                avg_approval_hours = VALUES(avg_approval_hours),
                avg_rework_hours = VALUES(avg_rework_hours),
                avg_queue_hours = VALUES(avg_queue_hours),
                sample_size = VALUES(sample_size),
                metric_payload_json = VALUES(metric_payload_json),
                updated_at = NOW()
        ");

        foreach ($rows as $row) {
            $cycles = $this->queryColumn("
                SELECT cycle_time_hours
                FROM ai_process_runs
                WHERE company_id = ? AND process_key = ? AND DATE(started_at) = ? AND cycle_time_hours IS NOT NULL
                ORDER BY cycle_time_hours ASC
            ", [$this->companyId, $row['process_key'], $row['metric_date']]);
            $insert->execute([
                $this->companyId,
                $row['process_key'],
                $row['metric_date'],
                (int) $row['instance_count'],
                (int) $row['completed_count'],
                (int) $row['active_count'],
                (int) $row['breach_count'],
                $row['avg_cycle_hours'] !== null ? round((float) $row['avg_cycle_hours'], 2) : null,
                $this->percentile($cycles, 50),
                $this->percentile($cycles, 90),
                $row['avg_approval_hours'] !== null ? round((float) $row['avg_approval_hours'], 2) : null,
                $row['avg_rework_hours'] !== null ? round((float) $row['avg_rework_hours'], 2) : null,
                $row['avg_queue_hours'] !== null ? round((float) $row['avg_queue_hours'], 2) : null,
                count($cycles),
                json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function detectFindings(?string $processKey = null): array
    {
        $maps = $this->getProcessMap($processKey);
        $inserted = [];
        foreach ($maps['transitions'] as $transition) {
            $shouldFlag = ((float) ($transition['average_duration_hours'] ?? 0) >= 24)
                || ((float) ($transition['p90_duration_hours'] ?? 0) >= 48);
            if (!$shouldFlag) {
                continue;
            }

            $severity = ((float) ($transition['p90_duration_hours'] ?? 0) >= 96) ? 'high' : 'medium';
            $title = $transition['from'] . ' to ' . $transition['to'] . ' is a bottleneck';
            $summary = 'Average transition time is ' . number_format((float) $transition['average_duration_hours'], 2) .
                ' hours with p90 at ' . number_format((float) $transition['p90_duration_hours'], 2) . ' hours.';
            $fingerprint = sha1(($processKey ?: 'all') . '|' . $transition['from'] . '|' . $transition['to']);
            $evidence = [
                'transition' => $transition,
                'route' => $this->route('modules/ai/process-intelligence/index.php'),
            ];

            $this->db->prepare("
                INSERT INTO ai_process_findings
                    (company_id, process_key, finding_type, severity, title, summary, stage_name, transition_name,
                     evidence_json, metric_snapshot_json, recommendation_text, status, fingerprint, detected_at)
                VALUES (?, ?, 'bottleneck', ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW())
                ON DUPLICATE KEY UPDATE
                    severity = VALUES(severity),
                    summary = VALUES(summary),
                    evidence_json = VALUES(evidence_json),
                    metric_snapshot_json = VALUES(metric_snapshot_json),
                    recommendation_text = VALUES(recommendation_text),
                    detected_at = NOW(),
                    updated_at = NOW(),
                    status = 'open'
            ")->execute([
                $this->companyId,
                $processKey ?: 'all',
                $severity,
                $title,
                $summary,
                $transition['to'],
                $transition['from'] . ' -> ' . $transition['to'],
                json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($transition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'Investigate queue ownership, approval routing, and pre-validation before this transition.',
                $fingerprint,
            ]);

            $inserted[] = ['process_key' => $processKey ?: 'all', 'title' => $title, 'severity' => $severity];
            if ($severity === 'high') {
                $this->createIntelligenceEvent($processKey ?: 'all', $title, $summary, $evidence);
            }
        }

        $slaRows = $this->queryAll("
            SELECT process_key, COUNT(*) AS breach_count, AVG(breach_hours) AS avg_breach_hours
            FROM ai_process_runs
            WHERE company_id = ? AND sla_status = 'breached'" . ($processKey ? " AND process_key = ?" : "") . "
            GROUP BY process_key
        ", $processKey ? [$this->companyId, $processKey] : [$this->companyId]);
        foreach ($slaRows as $row) {
            if ((int) $row['breach_count'] <= 0) {
                continue;
            }
            $fingerprint = sha1('sla|' . $row['process_key']);
            $title = 'SLA breaches detected for ' . $row['process_key'];
            $summary = (int) $row['breach_count'] . ' run(s) breached SLA with average breach duration of ' .
                number_format((float) ($row['avg_breach_hours'] ?? 0), 2) . ' hours.';
            $this->db->prepare("
                INSERT INTO ai_process_findings
                    (company_id, process_key, finding_type, severity, title, summary, evidence_json,
                     metric_snapshot_json, recommendation_text, status, fingerprint, detected_at)
                VALUES (?, ?, 'sla_breach', 'high', ?, ?, ?, ?, ?, 'open', ?, NOW())
                ON DUPLICATE KEY UPDATE
                    summary = VALUES(summary),
                    evidence_json = VALUES(evidence_json),
                    metric_snapshot_json = VALUES(metric_snapshot_json),
                    recommendation_text = VALUES(recommendation_text),
                    detected_at = NOW(),
                    updated_at = NOW(),
                    status = 'open'
            ")->execute([
                $this->companyId,
                $row['process_key'],
                $title,
                $summary,
                json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'Review SLA target, approval queue capacity, and early-stage validation for this process.',
                $fingerprint,
            ]);
            $this->createIntelligenceEvent($row['process_key'], $title, $summary, [
                'breach_count' => (int) $row['breach_count'],
                'avg_breach_hours' => (float) ($row['avg_breach_hours'] ?? 0),
            ]);
        }

        return $inserted;
    }

    private function createIntelligenceEvent(string $processKey, string $title, string $summary, array $evidence): void
    {
        if (!class_exists('AIIntelligenceService')) {
            return;
        }
        try {
            AIIntelligenceService::createEvent($this->db, [
                'event_key' => 'process_intelligence_' . $processKey,
                'company_id' => $this->companyId,
                'user_id' => $this->userId,
                'source_module' => 'process_intelligence',
                'source_record_type' => 'ai_process_findings',
                'source_record_id' => null,
                'detection_type' => 'trend',
                'detector_key' => 'f31.process_intelligence',
                'severity' => 'high',
                'confidence' => 0.92,
                'title' => $title,
                'explanation' => $summary,
                'evidence' => [['type' => 'process_finding', 'label' => $title, 'data' => $evidence]],
                'recommendation' => 'Open AI Process Intelligence and review the evidence before making workflow changes.',
                'urgency' => 'high',
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function getRecommendationById(int $recommendationId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ai_process_recommendations
            WHERE id = ? AND company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$recommendationId, $this->companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function recommendationTextForFinding(array $finding): array
    {
        $title = strtolower((string) ($finding['title'] ?? ''));
        if (str_contains($title, 'sla')) {
            return [
                'recommendation' => 'Introduce earlier validation and queue monitoring before the breach stage, then route exceptions into a human approval lane.',
                'impact' => 'Expected to reduce breach frequency and shorten median cycle time.',
                'rationale' => 'SLA breaches are grounded in historical runs and should be addressed before production workflow changes are proposed.',
            ];
        }
        if (str_contains($title, 'approval')) {
            return [
                'recommendation' => 'Reduce approval queue time by tightening approver routing, adding escalation alerts, and removing unnecessary handoffs.',
                'impact' => 'Expected to reduce approval delay and total cycle time.',
                'rationale' => 'The bottleneck is concentrated in approval-related transitions with reproducible stage-duration evidence.',
            ];
        }
        return [
            'recommendation' => 'Validate earlier in the process, reduce queue ownership ambiguity, and pilot a governed workflow draft before any production change.',
            'impact' => 'Expected to lower waiting time and rework without modifying current ERP records.',
            'rationale' => 'This recommendation is advisory and grounded in deterministic process transition evidence.',
        ];
    }

    private function fetchEvents(?string $processKey = null): array
    {
        $sql = "
            SELECT *
            FROM ai_process_events
            WHERE company_id = ?
        ";
        $params = [$this->companyId];
        if ($processKey) {
            $sql .= " AND process_key = ?";
            $params[] = $processKey;
        }
        $sql .= " ORDER BY process_key ASC, process_instance_id ASC, event_time ASC, id ASC";
        $rows = $this->queryAll($sql, $params);

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['process_key'] . '|' . $row['process_instance_id'];
            $grouped[$key][] = $row;
        }
        return $grouped;
    }

    private function detectProcessKey(string $text): ?string
    {
        if (preg_match('/\b(invoice|payment|receivable|collection)\b/i', $text)) {
            return 'invoice_to_payment';
        }
        if (preg_match('/\b(requisition|purchase order|procurement|approval|po)\b/i', $text)) {
            return 'requisition_to_po';
        }
        return null;
    }

    private function detectComparisonDimension(string $text): string
    {
        if (preg_match('/\b(branch|location|city)\b/i', $text)) {
            return 'branch';
        }
        if (preg_match('/\b(currency)\b/i', $text)) {
            return 'currency';
        }
        if (preg_match('/\b(status)\b/i', $text)) {
            return 'status';
        }
        return 'department';
    }

    private function isCompletionEvent(string $eventName): bool
    {
        return preg_match('/\b(closed|completed|paid|received|cancelled|rejected)\b/i', $eventName) === 1;
    }

    private function isAnalysisAllowed(): bool
    {
        if (!AIKillSwitchService::isEnabled($this->db, 'process_intelligence')) {
            return false;
        }
        if (AIEmergencyControlsService::isControlActive($this->db, 'disable_all_ai')) {
            return false;
        }
        return true;
    }

    private function isScheduleDue(?array $schedule, ?string $lastEvaluatedAt): bool
    {
        if (!is_array($schedule) || empty($schedule['schedule_type'])) {
            return false;
        }
        if ($lastEvaluatedAt === null) {
            return true;
        }
        if (class_exists('AIAutomationSchedulerService')) {
            $next = AIAutomationSchedulerService::calculateNextRun($schedule, $lastEvaluatedAt);
            return $next ? strtotime($next) <= time() : true;
        }
        return true;
    }

    private function recordObservability(string $operationType, ?string $processKey, array $result): void
    {
        try {
            $this->db->prepare("
                INSERT INTO ai_analytics_runs
                    (company_id, user_id, request_text, metric_key, operation_type, query_definition,
                     execution_time_ms, result_summary, result_json, governance_decision, risk_level, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'allow', 'low', NOW())
            ")->execute([
                $this->companyId,
                $this->userId,
                'AI Process Intelligence operation',
                $processKey,
                $operationType,
                json_encode(['process_key' => $processKey, 'operation' => $operationType]),
                0,
                substr((string) ($result['message'] ?? $result['safety_label'] ?? $operationType), 0, 500),
                json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function audit(string $action, array $details = []): void
    {
        try {
            $this->db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'process_intelligence', ?, ?, NOW())
            ")->execute([
                $this->userId,
                $action,
                $this->companyId,
                json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->execute([$table]);
        return $cache[$table] = (bool) $stmt->fetchColumn();
    }

    private function countRows(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int) $this->scalar("SELECT COUNT(*) FROM {$table} WHERE company_id = ?", [$this->companyId]);
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function queryAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function queryColumn(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function hoursBetween(?string $from, ?string $to): ?float
    {
        if (!$from || !$to) {
            return null;
        }
        $diff = strtotime($to) - strtotime($from);
        return round(max(0, $diff) / 3600, 2);
    }

    private function avg(array $values): ?float
    {
        $filtered = array_values(array_filter($values, fn($value) => $value !== null));
        if (empty($filtered)) {
            return null;
        }
        return round(array_sum($filtered) / count($filtered), 2);
    }

    private function percentile(array $values, int $percent): ?float
    {
        $filtered = array_values(array_filter($values, fn($value) => $value !== null));
        if (empty($filtered)) {
            return null;
        }
        sort($filtered);
        $index = (count($filtered) - 1) * ($percent / 100);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if ($lower === $upper) {
            return round((float) $filtered[$lower], 2);
        }
        $weight = $index - $lower;
        return round(((1 - $weight) * $filtered[$lower]) + ($weight * $filtered[$upper]), 2);
    }

    private function route(string $path): string
    {
        if (function_exists('url')) {
            return url($path);
        }

        $path = ltrim($path, '/');
        if (defined('BASE_URL') && BASE_URL !== '') {
            return rtrim((string) BASE_URL, '/') . '/' . $path;
        }

        return '/' . $path;
    }
}
