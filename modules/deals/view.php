<?php
$pageTitle = 'Deal Details';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('deals.view')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT d.*, c.company_name, e.first_name, e.last_name, l.company_name AS lead_company
    FROM deals d
    LEFT JOIN clients c ON d.client_id = c.id
    LEFT JOIN employees e ON d.assigned_to = e.id
    LEFT JOIN leads l ON d.lead_id = l.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$deal = $stmt->fetch();

if (!$deal) {
    flash('error', 'Deal not found');
    to('modules/deals/index.php');
}

$stageLabels = ['inquiry' => 'Inquiry', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'negotiation' => 'Negotiation', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost'];
$stageColors = ['inquiry' => 'bg-gray-100 text-gray-800', 'qualified' => 'bg-blue-100 text-blue-800', 'proposal' => 'bg-yellow-100 text-yellow-800', 'negotiation' => 'bg-orange-100 text-orange-800', 'closed_won' => 'bg-green-100 text-green-800', 'closed_lost' => 'bg-red-100 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="<?= url('modules/deals/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Deals Pipeline
    </a>
    <?php if (hasPermission('deals.create')): ?>
    <a href="<?= url('modules/deals/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Deal
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Deal Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-500">Deal Title</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($deal['deal_title']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Stage</label>
                    <span class="px-2 py-1 text-xs rounded <?= $stageColors[$deal['stage']] ?? 'bg-gray-100 text-gray-800' ?>"><?= $stageLabels[$deal['stage']] ?? ucfirst($deal['stage']) ?></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Deal Value</label>
                    <p class="text-gray-900 font-medium"><?= $deal['deal_value'] ? formatMoney($deal['deal_value'], 0) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Probability</label>
                    <p class="text-gray-900"><?= $deal['probability'] ? $deal['probability'] . '%' : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Expected Closing</label>
                    <p class="text-gray-900"><?= $deal['expected_closing_date'] ? date('M d, Y', strtotime($deal['expected_closing_date'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Created</label>
                    <p class="text-gray-900"><?= $deal['created_at'] ? date('M d, Y', strtotime($deal['created_at'])) : '-' ?></p>
                </div>
            </div>
            <?php if ($deal['notes']): ?>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-500">Notes</label>
                <p class="text-gray-900 whitespace-pre-line"><?= sanitize($deal['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="h-16 w-16 rounded-full bg-emerald-500 flex items-center justify-center text-white text-2xl font-bold">
                <?= strtoupper(substr($deal['deal_title'], 0, 1)) ?>
            </div>
            <h3 class="mt-4 text-lg font-semibold"><?= sanitize($deal['deal_title']) ?></h3>
            <p class="text-sm text-gray-600"><?= $stageLabels[$deal['stage']] ?? ucfirst($deal['stage']) ?></p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Relations</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Client</label>
                    <p class="text-gray-900"><?= sanitize($deal['company_name'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Linked Lead</label>
                    <p class="text-gray-900"><?= sanitize($deal['lead_company'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Assigned To</label>
                    <p class="text-gray-900"><?= sanitize(trim(($deal['first_name'] ?? '') . ' ' . ($deal['last_name'] ?? ''))) ?: 'Unassigned' ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
