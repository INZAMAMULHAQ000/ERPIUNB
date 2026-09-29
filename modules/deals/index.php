<?php
$pageTitle = 'Deals Pipeline';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('deals.view')) { die('Access Denied'); }

$db = getDB();

$stmt = $db->query("
    SELECT d.*, c.company_name, e.first_name, e.last_name
    FROM deals d
    LEFT JOIN clients c ON d.client_id = c.id
    LEFT JOIN employees e ON d.assigned_to = e.id
    ORDER BY d.stage, d.expected_closing_date
");
$allDeals = $stmt->fetchAll();

$stages = ['inquiry', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
$stageLabels = ['inquiry' => 'Inquiry', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'negotiation' => 'Negotiation', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost'];
$stageColors = ['inquiry' => 'bg-gray-100', 'qualified' => 'bg-blue-100', 'proposal' => 'bg-yellow-100', 'negotiation' => 'bg-orange-100', 'closed_won' => 'bg-green-100', 'closed_lost' => 'bg-red-100'];

$dealsByStage = [];
foreach ($stages as $s) { $dealsByStage[$s] = []; }
foreach ($allDeals as $deal) { $dealsByStage[$deal['stage']][] = $deal; }

$totalValue = array_sum(array_map(function($d) { return $d['deal_value'] ?: 0; }, $allDeals));
$wonValue = array_sum(array_map(function($d) { return $d['stage'] === 'closed_won' ? ($d['deal_value'] ?: 0) : 0; }, $allDeals));
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Deals Pipeline</h1>
        <p class="text-gray-600">Manage sales deals</p>
    </div>
    <?php if (hasPermission('deals.create')): ?>
    <a href="<?= url('modules/deals/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Deal</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Deals</div>
        <div class="text-2xl font-bold text-gray-800"><?= count($allDeals) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Value</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatMoney($totalValue, 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Won Value</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($wonValue, 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Win Rate</div>
        <div class="text-2xl font-bold text-gray-800"><?= count($allDeals) > 0 ? round(($wonValue / max($totalValue, 1)) * 100) : 0 ?>%</div>
    </div>
</div>

<div class="flex gap-4 overflow-x-auto pb-4">
    <?php foreach ($stages as $stage): ?>
    <div class="min-w-[280px] bg-gray-100 rounded-lg p-3">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-700"><?= $stageLabels[$stage] ?></h3>
            <span class="bg-white text-gray-600 text-xs px-2 py-1 rounded"><?= count($dealsByStage[$stage]) ?></span>
        </div>
        <div class="space-y-3 max-h-[500px] overflow-y-auto">
            <?php foreach ($dealsByStage[$stage] as $deal): ?>
            <div class="bg-white rounded-lg shadow p-3 cursor-pointer hover:shadow-md" onclick="window.location.href='<?= url('modules/deals/view.php?id=' . $deal['id']) ?>'">
                <div class="font-medium text-gray-800 text-sm mb-1"><?= sanitize($deal['deal_title']) ?></div>
                <div class="text-xs text-gray-500 mb-2"><?= sanitize($deal['company_name'] ?? 'No client') ?></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-800"><?= formatMoney($deal['deal_value'] ?: 0, 0) ?></span>
                    <?php if ($deal['expected_closing_date']): ?>
                    <span class="text-xs text-gray-500"><?= date('M d', strtotime($deal['expected_closing_date'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-gray-500 mt-1"><?= sanitize($deal['first_name'] ?? 'Unassigned') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
