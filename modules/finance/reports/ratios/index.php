<?php
/**
 * F11 — Financial Ratios
 * Liquidity, Profitability, Efficiency Ratios with Benchmarks
 */

$pageTitle = 'Financial Ratios';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.ratios.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$fyId = (int)($_GET['financial_year_id'] ?? 0);
$dateAsOf = $_GET['date_as_of'] ?? date('Y-m-d');

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

// Generate ratios
$report = FinancialReportService::calculateRatios([
    'financial_year_id' => $fyId,
    'date_as_of' => $dateAsOf,
]);

// Get additional context data
$pl = FinancialReportService::generateProfitLoss(['financial_year_id' => $fyId]);
$bs = FinancialReportService::generateBalanceSheet(['financial_year_id' => $fyId, 'date_as_of' => $dateAsOf]);
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Financial Ratios</h1>
        <p class="text-gray-600">As of <?= date('M d, Y', strtotime($dateAsOf)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
            <input type="date" name="date_as_of" value="<?= sanitize($dateAsOf) ?>" class="px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Calculate</button>
    </form>
</div>

<?php if ($report && $report['success']): ?>

<!-- Ratios Overview Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php 
    $keyRatios = ['current_ratio', 'quick_ratio', 'gross_margin', 'net_margin'];
    $colors = ['blue', 'green', 'purple', 'indigo'];
    $i = 0;
    foreach ($keyRatios as $key): 
        $ratio = $report['ratios'][$key] ?? null;
        if (!$ratio) continue;
        
        // Determine status based on benchmark
        $status = 'neutral';
        if (in_array($key, ['current_ratio', 'quick_ratio'])) {
            $status = $ratio['value'] >= 1.5 ? 'good' : ($ratio['value'] >= 1 ? 'warning' : 'poor');
        } elseif (in_array($key, ['gross_margin', 'net_margin'])) {
            $status = $ratio['value'] >= 10 ? 'good' : ($ratio['value'] >= 5 ? 'warning' : 'poor');
        }
    ?>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-<?= $colors[$i] ?>-500">
        <p class="text-xs text-gray-500 uppercase"><?= sanitize($ratio['label']) ?></p>
        <p class="text-2xl font-bold text-gray-800"><?= $ratio['value'] ?><?= $ratio['unit'] ?? '' ?></p>
        <p class="text-xs text-gray-400">Benchmark: <?= sanitize($ratio['benchmark'] ?? 'N/A') ?></p>
        <span class="inline-block mt-2 px-2 py-1 rounded text-xs font-semibold <?= $status === 'good' ? 'bg-green-100 text-green-800' : ($status === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
            <?= $status === 'good' ? '✓ Healthy' : ($status === 'warning' ? '⚠ Review' : '✗ Concern') ?>
        </span>
    </div>
    <?php $i++; endforeach; ?>
</div>

<!-- Liquidity Ratios -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b bg-blue-50">
        <h3 class="font-semibold text-blue-800">Liquidity Ratios</h3>
        <p class="text-sm text-blue-600">Ability to meet short-term obligations</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Ratio</th>
                    <th class="px-4 py-3 text-left">Formula</th>
                    <th class="px-4 py-3 text-right">Value</th>
                    <th class="px-4 py-3 text-center">Benchmark</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $liquidityRatios = ['current_ratio', 'quick_ratio'];
                foreach ($liquidityRatios as $key):
                    $ratio = $report['ratios'][$key] ?? null;
                    if (!$ratio) continue;
                    $status = $ratio['value'] >= 1.5 ? 'good' : ($ratio['value'] >= 1 ? 'warning' : 'poor');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($ratio['label']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($ratio['formula']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= $ratio['value'] ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= sanitize($ratio['benchmark']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $status === 'good' ? 'bg-green-100 text-green-800' : ($status === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= $status === 'good' ? '✓ Good' : ($status === 'warning' ? '⚠ Fair' : '✗ Poor') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Profitability Ratios -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b bg-green-50">
        <h3 class="font-semibold text-green-800">Profitability Ratios</h3>
        <p class="text-sm text-green-600">Ability to generate profits from operations</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Ratio</th>
                    <th class="px-4 py-3 text-left">Formula</th>
                    <th class="px-4 py-3 text-right">Value</th>
                    <th class="px-4 py-3 text-center">Benchmark</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $profitabilityRatios = ['gross_margin', 'net_margin', 'operating_margin'];
                foreach ($profitabilityRatios as $key):
                    $ratio = $report['ratios'][$key] ?? null;
                    if (!$ratio) continue;
                    $status = $ratio['value'] >= 10 ? 'good' : ($ratio['value'] >= 5 ? 'warning' : 'poor');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($ratio['label']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($ratio['formula']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= $ratio['value'] ?><?= $ratio['unit'] ?? '%' ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= sanitize($ratio['benchmark']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $status === 'good' ? 'bg-green-100 text-green-800' : ($status === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= $status === 'good' ? '✓ Good' : ($status === 'warning' ? '⚠ Fair' : '✗ Poor') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Efficiency Ratios -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b bg-purple-50">
        <h3 class="font-semibold text-purple-800">Efficiency Ratios</h3>
        <p class="text-sm text-purple-600">How effectively assets and liabilities are managed</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Ratio</th>
                    <th class="px-4 py-3 text-left">Formula</th>
                    <th class="px-4 py-3 text-right">Value</th>
                    <th class="px-4 py-3 text-center">Interpretation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $efficiencyRatios = ['receivables_days', 'payables_days'];
                foreach ($efficiencyRatios as $key):
                    $ratio = $report['ratios'][$key] ?? null;
                    if (!$ratio) continue;
                    
                    // Interpret based on ratio type
                    $interpretation = '';
                    if ($key === 'receivables_days') {
                        $interpretation = $ratio['value'] <= 45 ? 'Excellent collection' : ($ratio['value'] <= 60 ? 'Good' : 'Needs improvement');
                    } elseif ($key === 'payables_days') {
                        $interpretation = $ratio['value'] <= 30 ? 'Early payment' : ($ratio['value'] <= 60 ? 'Normal' : 'Extended terms');
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($ratio['label']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($ratio['formula']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= $ratio['value'] ?> <?= $ratio['unit'] ?? '' ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= $interpretation ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Leverage Ratios -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b bg-amber-50">
        <h3 class="font-semibold text-amber-800">Leverage (Solvency) Ratios</h3>
        <p class="text-sm text-amber-600">Long-term financial stability and debt management</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Ratio</th>
                    <th class="px-4 py-3 text-left">Formula</th>
                    <th class="px-4 py-3 text-right">Value</th>
                    <th class="px-4 py-3 text-center">Benchmark</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $leverageRatios = ['debt_to_equity'];
                foreach ($leverageRatios as $key):
                    $ratio = $report['ratios'][$key] ?? null;
                    if (!$ratio) continue;
                    $status = $ratio['value'] <= 1 ? 'good' : ($ratio['value'] <= 2 ? 'warning' : 'poor');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($ratio['label']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($ratio['formula']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= $ratio['value'] ?></td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= sanitize($ratio['benchmark']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $status === 'good' ? 'bg-green-100 text-green-800' : ($status === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= $status === 'good' ? '✓ Low Risk' : ($status === 'warning' ? '⚠ Moderate' : '✗ High Risk') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Return Ratios -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b bg-indigo-50">
        <h3 class="font-semibold text-indigo-800">Return Ratios</h3>
        <p class="text-sm text-indigo-600">Returns generated on investments</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Ratio</th>
                    <th class="px-4 py-3 text-left">Formula</th>
                    <th class="px-4 py-3 text-right">Value</th>
                    <th class="px-4 py-3 text-center">Interpretation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $returnRatios = ['roa', 'roe'];
                foreach ($returnRatios as $key):
                    $ratio = $report['ratios'][$key] ?? null;
                    if (!$ratio) continue;
                    $interpretation = $ratio['value'] >= 5 ? 'Good return' : ($ratio['value'] >= 0 ? 'Low return' : 'Negative return');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($ratio['label']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($ratio['formula']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= $ratio['value'] ?>%</td>
                    <td class="px-4 py-3 text-center text-gray-600"><?= $interpretation ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Ratio Interpretation Guide -->
<div class="bg-gray-50 rounded-lg p-6">
    <h4 class="font-semibold text-gray-800 mb-4">Ratio Interpretation Guide</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
            <h5 class="font-medium text-gray-700 mb-2">Liquidity</h5>
            <ul class="space-y-1 text-gray-600">
                <li>• <strong>Current Ratio:</strong> Measures ability to pay short-term debts. Higher is better (≥1.5 ideal)</li>
                <li>• <strong>Quick Ratio:</strong> More conservative liquidity test. Should be ≥1.0</li>
            </ul>
        </div>
        <div>
            <h5 class="font-medium text-gray-700 mb-2">Profitability</h5>
            <ul class="space-y-1 text-gray-600">
                <li>• <strong>Gross Margin:</strong> Profit after deducting cost of sales. Higher is better</li>
                <li>• <strong>Net Margin:</strong> Final profit as % of revenue. ≥10% is healthy</li>
            </ul>
        </div>
        <div>
            <h5 class="font-medium text-gray-700 mb-2">Efficiency</h5>
            <ul class="space-y-1 text-gray-600">
                <li>• <strong>DSO:</strong> Days to collect receivables. Lower is better (&lt;45 days ideal)</li>
                <li>• <strong>DPO:</strong> Days to pay suppliers. Higher can improve cash flow</li>
            </ul>
        </div>
        <div>
            <h5 class="font-medium text-gray-700 mb-2">Leverage</h5>
            <ul class="space-y-1 text-gray-600">
                <li>• <strong>Debt-to-Equity:</strong> Proportion of debt to equity. Lower is safer (&lt;1.0 ideal)</li>
            </ul>
        </div>
    </div>
</div>

<?php else: ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
    Unable to calculate ratios. Please ensure financial data exists for the selected period.
</div>
<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>