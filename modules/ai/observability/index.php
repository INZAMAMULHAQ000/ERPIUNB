<?php
$pageTitle = 'AI Observability';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIObservabilityService.php';

if (!hasPermission('ai.observability.view') && !hasPermission('ai.observability.manage') && !hasPermission('ai.settings.manage')) {
    die('Access Denied');
}

$db = getDB();
$companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
AIObservabilityService::ensureDefaults($db);

$overview = AIObservabilityService::getOverview($db, ['company_id' => $companyId]);
$requests = AIObservabilityService::listRequests($db, ['company_id' => $companyId], 20);
$requestDetail = !empty($requests) ? AIObservabilityService::getRequestDetail($db, (int) $requests[0]['id'], $companyId) : null;
$runs = AIObservabilityService::listEvaluationRuns($db, 10);
$feedback = AIObservabilityService::listFeedback($db, ['company_id' => $companyId], 15);
$experiments = AIObservabilityService::listExperiments($db);
$benchmarks = AIObservabilityService::listBenchmarks($db);
$findings = AIObservabilityService::listFindings($db, 15);
$failures = AIObservabilityService::getFailures($db, ['company_id' => $companyId], 15);
$reports = AIObservabilityService::listReports($db, 10);
$datasets = AIObservabilityService::listDatasets($db);
$prompts = AIObservabilityService::listPromptVersions($db);
?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.observability'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <div class="ai-page-header mb-6 flex flex-wrap justify-between items-start gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">AI Observability</h1>
            <p class="text-gray-400 mt-1">Operational visibility for requests, traces, evaluations, feedback, experiments, failures, and reports.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="runAggregates" class="bg-slate-700 hover:bg-slate-600 text-gray-300 px-3 py-2 rounded-lg text-sm font-medium transition shadow-sm">Refresh Aggregates</button>
            <button type="button" id="runAnomalies" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition shadow-sm">Detect Anomalies</button>
            <button type="button" id="runReport" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition shadow-sm">Generate Daily Report</button>
        </div>
    </div>

    <nav class="ai-tabs mb-6">
        <a href="#overview" class="ai-tab active"><i class="fas fa-chart-pie"></i> Overview</a>
        <a href="#requests" class="ai-tab"><i class="fas fa-list"></i> Requests</a>
        <a href="#traces" class="ai-tab"><i class="fas fa-stream"></i> Traces</a>
        <a href="#evaluations" class="ai-tab"><i class="fas fa-vial"></i> Evaluations</a>
        <a href="#feedback" class="ai-tab"><i class="fas fa-comments"></i> Feedback</a>
        <a href="#experiments" class="ai-tab"><i class="fas fa-flask"></i> Experiments</a>
        <a href="#benchmarks" class="ai-tab"><i class="fas fa-tachometer-alt"></i> Benchmarks</a>
        <a href="#quality" class="ai-tab"><i class="fas fa-star"></i> Quality</a>
        <a href="#failures" class="ai-tab"><i class="fas fa-exclamation-triangle"></i> Failures</a>
        <a href="#reports" class="ai-tab"><i class="fas fa-file-alt"></i> Reports</a>
    </nav>

    <div id="feedbackBanner" class="hidden mb-4 rounded-lg px-4 py-3 text-sm"></div>

    <div id="overview" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="ai-content-card">
            <div class="text-xs uppercase text-gray-400 font-semibold">Requests</div>
            <div class="text-2xl font-bold text-white mt-1"><?= (int) ($overview['total_requests'] ?? 0) ?></div>
            <div class="text-xs text-gray-500 mt-2">Last 30 days</div>
        </div>
        <div class="ai-content-card">
            <div class="text-xs uppercase text-gray-400 font-semibold">Success Rate</div>
            <div class="text-2xl font-bold text-emerald-400 mt-1"><?= number_format((float) ($overview['success_rate'] ?? 0), 2) ?>%</div>
            <div class="text-xs text-gray-500 mt-2">Failures: <?= (int) ($overview['failed_count'] ?? 0) ?></div>
        </div>
        <div class="ai-content-card">
            <div class="text-xs uppercase text-gray-400 font-semibold">P95 Latency</div>
            <div class="text-2xl font-bold text-sky-400 mt-1"><?= number_format((float) ($overview['p95_latency_ms'] ?? 0), 0) ?>ms</div>
            <div class="text-xs text-gray-500 mt-2">Average: <?= number_format((float) ($overview['avg_latency_ms'] ?? 0), 0) ?>ms</div>
        </div>
        <div class="ai-content-card">
            <div class="text-xs uppercase text-gray-400 font-semibold">Estimated Cost</div>
            <div class="text-2xl font-bold text-rose-400 mt-1">$<?= number_format((float) ($overview['total_cost'] ?? 0), 4) ?></div>
            <div class="text-xs text-gray-500 mt-2">Open findings: <?= (int) ($overview['open_findings'] ?? 0) ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <section id="requests" class="xl:col-span-2 ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Recent Requests</h2>
                <p class="text-sm text-gray-400 mt-1">Provider/model/request metadata with request IDs, status, latency, and safe summaries.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-5 py-3">Request</th>
                            <th class="text-left px-5 py-3">Feature</th>
                            <th class="text-left px-5 py-3">Provider</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="text-left px-5 py-3">Latency</th>
                            <th class="text-left px-5 py-3">Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-200"><?= htmlspecialchars($request['request_key']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($request['entry_point']) ?> · <?= htmlspecialchars($request['started_at']) ?></div>
                                </td>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($request['feature']) ?></td>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($request['provider_name'] ?? 'Unknown') ?><div class="text-xs text-gray-500"><?= htmlspecialchars($request['model_name'] ?? 'Unknown') ?></div></td>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($request['status']) ?></td>
                                <td class="px-5 py-4 text-gray-300"><?= (int) ($request['duration_ms'] ?? 0) ?>ms</td>
                                <td class="px-5 py-4 text-gray-300">$<?= number_format((float) ($request['estimated_total_cost'] ?? 0), 4) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="quality" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Quality Snapshot</h2>
                <p class="text-sm text-gray-400 mt-1">Feedback-driven quality and tool utility indicators.</p>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-slate-700/50 p-3">
                        <div class="text-gray-400 text-xs uppercase">Helpful</div>
                        <div class="text-xl font-bold text-emerald-400"><?= (int) (($overview['quality_breakdown']['helpful'] ?? 0)) ?></div>
                    </div>
                    <div class="rounded-lg border border-slate-700/50 p-3">
                        <div class="text-gray-400 text-xs uppercase">Problems</div>
                        <div class="text-xl font-bold text-rose-400"><?= (int) (($overview['quality_breakdown']['report_problem'] ?? 0) + ($overview['quality_breakdown']['not_helpful'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-700/50 p-3">
                    <div class="text-xs uppercase text-gray-400">Quality Score</div>
                    <div class="text-2xl font-bold text-gray-200"><?= number_format((float) ($overview['quality_score'] ?? 0), 4) ?></div>
                </div>
                <div>
                    <div class="text-xs uppercase text-gray-400 font-semibold mb-2">Top Tools</div>
                    <div class="space-y-2">
                        <?php foreach (array_slice($overview['tool_analytics'] ?? [], 0, 5) as $tool): ?>
                            <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                                <div class="font-medium text-gray-200"><?= htmlspecialchars($tool['tool_name']) ?></div>
                                <div class="text-xs text-gray-500 mt-1">Success <?= number_format((float) ($tool['success_rate'] ?? 0), 2) ?>% · Avg <?= number_format((float) ($tool['avg_duration_ms'] ?? 0), 0) ?>ms</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <section id="traces" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Trace Timeline</h2>
                <p class="text-sm text-gray-400 mt-1">Logical span timeline for the latest request.</p>
            </div>
            <div class="p-5">
                <?php if ($requestDetail): ?>
                    <div class="text-sm text-gray-400 mb-4">Trace: <span class="font-mono"><?= htmlspecialchars($requestDetail['trace_id']) ?></span></div>
                    <div class="space-y-3">
                        <?php foreach ($requestDetail['spans'] as $span): ?>
                            <details class="rounded-lg border border-slate-700/50 p-3">
                                <summary class="cursor-pointer text-sm font-medium text-gray-200">
                                    <?= htmlspecialchars($span['span_name']) ?> · <?= htmlspecialchars($span['span_type']) ?> · <?= (int) ($span['duration_ms'] ?? 0) ?>ms · <?= htmlspecialchars($span['status']) ?>
                                </summary>
                                <div class="mt-3 text-xs text-gray-400 space-y-2">
                                    <div><strong>Input:</strong> <?= htmlspecialchars($span['safe_input_summary'] ?? '—') ?></div>
                                    <div><strong>Output:</strong> <?= htmlspecialchars($span['safe_output_summary'] ?? '—') ?></div>
                                    <div><strong>Error:</strong> <?= htmlspecialchars($span['error_category'] ?? '—') ?></div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-sm text-gray-500">No request traces captured yet.</div>
                <?php endif; ?>
            </div>
        </section>

        <section id="evaluations" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Evaluations</h2>
                <p class="text-sm text-gray-400 mt-1">Deterministic evaluation datasets, runs, and scores.</p>
            </div>
            <div class="p-5 space-y-4">
                <form id="runEvalForm" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1">Dataset</label>
                        <select name="dataset_id" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                            <?php foreach ($datasets as $dataset): ?>
                                <option value="<?= (int) $dataset['id'] ?>"><?= htmlspecialchars($dataset['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Run Evaluation</button>
                </form>
                <div class="space-y-3">
                    <?php foreach ($runs as $run): ?>
                        <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                            <div class="font-medium text-gray-200"><?= htmlspecialchars($run['dataset_name'] ?? $run['run_key']) ?></div>
                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($run['status']) ?> · Score <?= number_format((float) ($run['average_score'] ?? 0), 4) ?> · Avg <?= number_format((float) ($run['average_latency_ms'] ?? 0), 0) ?>ms</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <section id="feedback" class="xl:col-span-2 ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Feedback Review Queue</h2>
                <p class="text-sm text-gray-400 mt-1">Helpful/not helpful/report problem reviews linked to request IDs and traces.</p>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($feedback as $item): ?>
                    <div class="rounded-lg border border-slate-700/50 p-4">
                        <div class="flex flex-wrap justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-200"><?= htmlspecialchars($item['feedback_type']) ?><?= !empty($item['reason_code']) ? ' · ' . htmlspecialchars($item['reason_code']) : '' ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($item['request_key'] ?? 'No request link') ?> · <?= htmlspecialchars($item['feature'] ?? 'unknown') ?> · <?= htmlspecialchars($item['status']) ?></div>
                                <?php if (!empty($item['comment'])): ?><div class="text-sm text-gray-400 mt-2"><?= nl2br(htmlspecialchars($item['comment'])) ?></div><?php endif; ?>
                            </div>
                            <button type="button" class="reviewFeedbackBtn text-xs text-sky-400 hover:text-sky-300 transition" data-id="<?= (int) $item['id'] ?>">Mark Reviewed</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="experiments" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Experiments</h2>
                <p class="text-sm text-gray-400 mt-1">Controlled comparison records only.</p>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($experiments as $experiment): ?>
                    <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                        <div class="font-medium text-gray-200"><?= htmlspecialchars($experiment['name']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($experiment['experiment_type']) ?> · <?= htmlspecialchars($experiment['status']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($experiments)): ?><div class="text-sm text-gray-500">No experiments yet.</div><?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <section id="benchmarks" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Benchmarks</h2>
                <p class="text-sm text-gray-400 mt-1">Company-neutral benchmark catalog.</p>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($benchmarks as $benchmark): ?>
                    <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                        <div class="font-medium text-gray-200"><?= htmlspecialchars($benchmark['name']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($benchmark['category']) ?> · <?= htmlspecialchars($benchmark['status']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Prompt Versions</h2>
                <p class="text-sm text-gray-400 mt-1">Tracked internal prompt/configuration versions with rollback-friendly history.</p>
            </div>
            <div class="p-5 space-y-3 max-h-80 overflow-auto">
                <?php foreach (array_slice($prompts, 0, 10) as $prompt): ?>
                    <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                        <div class="font-medium text-gray-200"><?= htmlspecialchars($prompt['prompt_key']) ?> v<?= (int) $prompt['version_no'] ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($prompt['feature']) ?> · <?= htmlspecialchars($prompt['status']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Findings</h2>
                <p class="text-sm text-gray-400 mt-1">Deterministic anomalies and recommendations.</p>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($findings as $finding): ?>
                    <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                        <div class="font-medium text-gray-200"><?= htmlspecialchars($finding['title']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($finding['severity']) ?> · <?= htmlspecialchars($finding['status']) ?></div>
                        <?php if (!empty($finding['recommendation_text'])): ?><div class="text-xs text-gray-400 mt-2"><?= htmlspecialchars($finding['recommendation_text']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <section id="failures" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Failures</h2>
                <p class="text-sm text-gray-400 mt-1">Grouped request failures without exposing secrets or raw stack traces.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-5 py-3">Request</th>
                            <th class="text-left px-5 py-3">Category</th>
                            <th class="text-left px-5 py-3">Provider</th>
                            <th class="text-left px-5 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach ($failures as $failure): ?>
                            <tr>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($failure['request_key']) ?><div class="text-xs text-gray-500"><?= htmlspecialchars($failure['feature']) ?></div></td>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($failure['error_category'] ?? 'unknown') ?><div class="text-xs text-gray-500"><?= htmlspecialchars($failure['status']) ?></div></td>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($failure['provider_name'] ?? 'Unknown') ?><div class="text-xs text-gray-500"><?= htmlspecialchars($failure['model_name'] ?? 'Unknown') ?></div></td>
                                <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($failure['started_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="reports" class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Reports</h2>
                <p class="text-sm text-gray-400 mt-1">Aggregated operational and quality reports.</p>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($reports as $report): ?>
                    <div class="rounded-lg border border-slate-700/50 p-3 text-sm">
                        <div class="font-medium text-gray-200"><?= htmlspecialchars($report['title']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($report['report_type']) ?> · <?= htmlspecialchars($report['report_date']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($reports)): ?><div class="text-sm text-gray-500">No reports generated yet.</div><?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
const OBS_API = '<?= url('modules/ai/api/observability.php') ?>';
const CSRF = '<?= csrfToken() ?>';

function showBanner(message, success = true) {
    const el = document.getElementById('feedbackBanner');
    el.className = (success
        ? 'mb-4 rounded-lg px-4 py-3 text-sm bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
        : 'mb-4 rounded-lg px-4 py-3 text-sm bg-rose-500/15 text-rose-400 border border-rose-500/30');
    el.textContent = message;
    el.classList.remove('hidden');
}

async function postObs(action, payload = {}) {
    const resp = await fetch(`${OBS_API}?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(payload)
    });
    return await resp.json();
}

document.getElementById('runAggregates')?.addEventListener('click', async () => {
    const result = await postObs('refresh_aggregates');
    showBanner(result.success ? 'Observability aggregates refreshed.' : (result.error || 'Unable to refresh aggregates.'), !!result.success);
});

document.getElementById('runAnomalies')?.addEventListener('click', async () => {
    const result = await postObs('detect_anomalies');
    showBanner(result.success ? 'Anomaly detection completed.' : (result.error || 'Unable to detect anomalies.'), !!result.success);
});

document.getElementById('runReport')?.addEventListener('click', async () => {
    const result = await postObs('generate_report', { report_type: 'daily_operations' });
    showBanner(result.success ? 'Daily AI operations report generated.' : (result.error || 'Unable to generate report.'), !!result.success);
});

document.getElementById('runEvalForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const result = await postObs('run_evaluation', { dataset_id: parseInt(fd.get('dataset_id'), 10) });
    showBanner(result.success ? 'Evaluation run completed.' : (result.error || 'Unable to run evaluation.'), !!result.success);
});

document.querySelectorAll('.reviewFeedbackBtn').forEach((button) => {
    button.addEventListener('click', async () => {
        const result = await postObs('review_feedback', { feedback_id: parseInt(button.dataset.id, 10), status: 'resolved', reviewer_notes: 'Reviewed from Observability Center.' });
        showBanner(result.success ? 'Feedback marked as reviewed.' : (result.error || 'Unable to review feedback.'), !!result.success);
    });
});
</script>
<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>