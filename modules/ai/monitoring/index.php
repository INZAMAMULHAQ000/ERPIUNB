<?php
/**
 * AI Monitoring & Observability Dashboard.
 *
 * Real-time performance metrics, token analytics, cost accrual,
 * latency distribution, and error monitoring.
 */
$pageTitle = 'AI Monitoring';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.management.view') && !hasPermission('ai.settings.manage') && ($_SESSION['role'] ?? '') !== 'superadmin') {
    die('Access Denied');
}

$db = getDB();
$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [1, 7, 30, 90, 365], true)) {
    $days = 30;
}

$dateFrom = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
$dateTo = date('Y-m-d 23:59:59');

$stats = AIUsageService::getStats($db, ['date_from' => $dateFrom, 'date_to' => $dateTo]);
$byFeature = AIUsageService::getGroupedStats($db, ['date_from' => $dateFrom, 'date_to' => $dateTo], 'feature');
$byProvider = AIUsageService::getGroupedStats($db, ['date_from' => $dateFrom, 'date_to' => $dateTo], 'provider');
$byModel = AIUsageService::getGroupedStats($db, ['date_from' => $dateFrom, 'date_to' => $dateTo], 'model');
$dailyTrend = AIUsageService::getDailyUsage($db, $days);
$recentLogs = AIUsageService::getRecent($db, 30);

$totalReqs = (int) ($stats['total'] ?? 0);
$successReqs = (int) ($stats['successful'] ?? 0);
$failedReqs = (int) ($stats['failed'] ?? 0);
$errorRate = $totalReqs > 0 ? round(($failedReqs / $totalReqs) * 100, 1) : 0;
$totalTokens = (int) (($stats['total_input_tokens'] ?? 0) + ($stats['total_output_tokens'] ?? 0));
$totalCost = (float) ($stats['total_cost'] ?? 0.0);
$avgLatency = round((float) ($stats['avg_duration'] ?? 0));
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <div class="ai-page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-chart-line"></i></span>
                AI Observability &amp; Monitoring
            </h1>
            <p class="ai-page-subtitle">System performance metrics, token utilization, cost accrual, and live request logs</p>
        </div>
        <!-- Timeframe Filter -->
        <div class="flex items-center gap-1.5 rounded-lg p-1" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border)">
            <a href="?days=1" class="px-3 py-1.5 rounded-md text-xs font-semibold <?= $days === 1 ? '' : 'opacity-80 hover:opacity-100' ?>" style="background:var(--ai-accent);color:#0f172a">Today</a>
            <a href="?days=7" class="px-3 py-1.5 rounded-md text-xs font-semibold <?= $days === 7 ? '' : 'opacity-80 hover:opacity-100' ?>" style="background:var(--ai-accent);color:#0f172a">7 Days</a>
            <a href="?days=30" class="px-3 py-1.5 rounded-md text-xs font-semibold <?= $days === 30 ? '' : 'opacity-80 hover:opacity-100' ?>" style="background:var(--ai-accent);color:#0f172a">30 Days</a>
            <a href="?days=90" class="px-3 py-1.5 rounded-md text-xs font-semibold <?= $days === 90 ? '' : 'opacity-80 hover:opacity-100' ?>" style="background:var(--ai-accent);color:#0f172a">90 Days</a>
        </div>
    </div>

<?php ai_render_module_nav('ai.monitoring'); ?>

<!-- KPI Cards -->
<div class="ai-stat-grid mb-6">
    <div class="ai-stat-card">
        <div class="text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Total Requests</div>
        <div class="text-2xl font-bold mt-1" style="color:var(--ai-text-primary)"><?= number_format($totalReqs) ?></div>
        <div class="text-xs mt-1" style="color:var(--ai-text-muted)"><?= number_format($successReqs) ?> ok · <?= number_format($failedReqs) ?> fail</div>
    </div>
    <div class="ai-stat-card">
        <div class="text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Error Rate</div>
        <div class="text-2xl font-bold mt-1 <?= $errorRate > 5 ? '' : ($errorRate > 0 ? '' : '') ?>">
            <span style="color:<?= $errorRate > 5 ? 'var(--ai-danger)' : ($errorRate > 0 ? 'var(--ai-warning)' : 'var(--ai-success)') ?>"><?= $errorRate ?>%</span>
        </div>
        <div class="text-xs mt-1" style="color:var(--ai-text-muted)"><?= $failedReqs ?> total errors</div>
    </div>
    <div class="ai-stat-card">
        <div class="text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Average Latency</div>
        <div class="text-2xl font-bold mt-1" style="color:var(--ai-accent)"><?= number_format($avgLatency) ?> <span class="text-sm font-normal" style="color:var(--ai-text-muted)">ms</span></div>
        <div class="text-xs mt-1" style="color:var(--ai-text-muted)">Per request turnaround</div>
    </div>
    <div class="ai-stat-card">
        <div class="text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Tokens Consumed</div>
        <div class="text-2xl font-bold mt-1" style="color:var(--ai-accent-2)"><?= number_format($totalTokens) ?></div>
        <div class="text-xs mt-1" style="color:var(--ai-text-muted)"><?= number_format((int)($stats['total_input_tokens'] ?? 0)) ?> in · <?= number_format((int)($stats['total_output_tokens'] ?? 0)) ?> out</div>
    </div>
    <div class="ai-stat-card">
        <div class="text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Estimated Cost</div>
        <div class="text-2xl font-bold mt-1" style="color:var(--ai-success)">$<?= number_format($totalCost, 4) ?></div>
        <div class="text-xs mt-1" style="color:var(--ai-text-muted)">Past <?= $days ?> days</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Feature Breakdown -->
    <div class="ai-content-card">
        <h3 class="ai-section-title">
            <i class="fas fa-layer-group"></i> Usage by AI Feature
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr style="border-bottom:1px solid var(--ai-border)">
                        <th class="py-2.5 px-3 text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Feature</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Requests</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Tokens</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Cost</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Avg Latency</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color:var(--ai-border)">
                    <?php if (empty($byFeature)): ?>
                    <tr><td colspan="5" class="py-4 text-center" style="color:var(--ai-text-muted)">No usage recorded for this timeframe.</td></tr>
                    <?php else: ?>
                    <?php foreach ($byFeature as $f): ?>
                    <tr class="transition-colors" style="border-color:var(--ai-border)" onmouseover="this.style.background='rgba(56,189,248,0.06)'" onmouseout="this.style.background='transparent'">
                        <td class="py-2.5 px-3 font-medium capitalize" style="color:var(--ai-text-primary)"><?= htmlspecialchars(str_replace('_', ' ', $f['label'])) ?></td>
                        <td class="py-2.5 px-3 text-right font-bold" style="color:var(--ai-text-primary)"><?= number_format((int)$f['total_requests']) ?></td>
                        <td class="py-2.5 px-3 text-right" style="color:var(--ai-text-secondary)"><?= number_format((int)(($f['input_tokens'] ?? 0) + ($f['output_tokens'] ?? 0))) ?></td>
                        <td class="py-2.5 px-3 text-right font-medium" style="color:var(--ai-success)">$<?= number_format((float)$f['estimated_cost'], 4) ?></td>
                        <td class="py-2.5 px-3 text-right" style="color:var(--ai-text-secondary)"><?= number_format((float)$f['avg_duration_ms'], 0) ?> ms</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Provider Breakdown -->
    <div class="ai-content-card">
        <h3 class="ai-section-title">
            <i class="fas fa-server"></i> Usage by Provider
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr style="border-bottom:1px solid var(--ai-border)">
                        <th class="py-2.5 px-3 text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Provider</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Requests</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Success %</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Cost</th>
                        <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Avg Latency</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color:var(--ai-border)">
                    <?php if (empty($byProvider)): ?>
                    <tr><td colspan="5" class="py-4 text-center" style="color:var(--ai-text-muted)">No provider usage recorded.</td></tr>
                    <?php else: ?>
                    <?php foreach ($byProvider as $p):
                        $rate = (int)$p['total_requests'] > 0 ? round(((int)$p['successful_requests'] / (int)$p['total_requests']) * 100, 1) : 0;
                    ?>
                    <tr class="transition-colors" style="border-color:var(--ai-border)" onmouseover="this.style.background='rgba(56,189,248,0.06)'" onmouseout="this.style.background='transparent'">
                        <td class="py-2.5 px-3 font-medium" style="color:var(--ai-text-primary)"><?= htmlspecialchars($p['label']) ?></td>
                        <td class="py-2.5 px-3 text-right font-bold" style="color:var(--ai-text-primary)"><?= number_format((int)$p['total_requests']) ?></td>
                        <td class="py-2.5 px-3 text-right font-semibold" style="color:<?= $rate >= 95 ? 'var(--ai-success)' : 'var(--ai-warning)' ?>"><?= $rate ?>%</td>
                        <td class="py-2.5 px-3 text-right font-medium" style="color:var(--ai-success)">$<?= number_format((float)$p['estimated_cost'], 4) ?></td>
                        <td class="py-2.5 px-3 text-right" style="color:var(--ai-text-secondary)"><?= number_format((float)$p['avg_duration_ms'], 0) ?> ms</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Live Recent Invocations Stream -->
<div class="ai-content-card">
    <div class="flex items-center justify-between mb-4">
        <h3 class="ai-section-title mb-0 pb-0 border-b-0">
            <i class="fas fa-stream"></i> Recent Invocations Stream (Last 30)
        </h3>
        <button onclick="location.reload()" class="text-xs font-medium transition-opacity opacity-80 hover:opacity-100" style="color:var(--ai-accent)">
            <i class="fas fa-sync-alt mr-1"></i> Refresh Stream
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr style="border-bottom:1px solid var(--ai-border)">
                    <th class="py-2.5 px-3 text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Time</th>
                    <th class="py-2.5 px-3 text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Feature</th>
                    <th class="py-2.5 px-3 text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Provider / Model</th>
                    <th class="py-2.5 px-3 text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">User</th>
                    <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Tokens</th>
                    <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Latency</th>
                    <th class="py-2.5 px-3 text-right text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Cost</th>
                    <th class="py-2.5 px-3 text-center text-xs font-semibold uppercase" style="color:var(--ai-text-muted)">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y" style="border-color:var(--ai-border)">
                <?php if (empty($recentLogs)): ?>
                <tr><td colspan="8" class="py-6 text-center" style="color:var(--ai-text-muted)">No invocation logs recorded yet.</td></tr>
                <?php else: ?>
                <?php foreach ($recentLogs as $log): ?>
                <tr class="transition-colors" style="border-color:var(--ai-border)" onmouseover="this.style.background='rgba(56,189,248,0.06)'" onmouseout="this.style.background='transparent'">
                    <td class="py-2.5 px-3 text-xs whitespace-nowrap" style="color:var(--ai-text-muted)"><?= htmlspecialchars(date('M j, H:i:s', strtotime($log['created_at']))) ?></td>
                    <td class="py-2.5 px-3 font-semibold capitalize" style="color:var(--ai-text-primary)"><?= htmlspecialchars(str_replace('_', ' ', $log['feature'])) ?></td>
                    <td class="py-2.5 px-3 text-xs" style="color:var(--ai-text-secondary)">
                        <span class="font-medium"><?= htmlspecialchars($log['provider_name'] ?? 'Provider #' . $log['provider_id']) ?></span>
                        <div class="font-mono" style="color:var(--ai-text-muted)"><?= htmlspecialchars($log['model_name'] ?? $log['model_id'] ?? '-') ?></div>
                    </td>
                    <td class="py-2.5 px-3 text-xs" style="color:var(--ai-text-secondary)"><?= htmlspecialchars($log['user_name'] ?? 'User #' . $log['user_id']) ?></td>
                    <td class="py-2.5 px-3 text-right font-mono text-xs" style="color:var(--ai-text-primary)"><?= number_format((int)($log['input_tokens'] + $log['output_tokens'])) ?></td>
                    <td class="py-2.5 px-3 text-right text-xs" style="color:var(--ai-text-secondary)"><?= number_format((int)$log['duration_ms']) ?> ms</td>
                    <td class="py-2.5 px-3 text-right text-xs font-medium" style="color:var(--ai-success)">$<?= number_format((float)$log['estimated_cost'], 4) ?></td>
                    <td class="py-2.5 px-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $log['status'] === 'success' ? '' : '' ?>" style="background:<?= $log['status'] === 'success' ? 'var(--ai-badge-success-bg)' : 'var(--ai-badge-danger-bg)' ?>;color:<?= $log['status'] === 'success' ? 'var(--ai-success)' : 'var(--ai-danger)' ?>">
                            <?= ucfirst(htmlspecialchars($log['status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


