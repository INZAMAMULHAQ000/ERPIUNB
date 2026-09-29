<?php
$pageTitle = 'Lead Details';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('leads.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT l.*, e.first_name, e.last_name FROM leads l LEFT JOIN employees e ON l.assigned_to = e.id WHERE l.id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    flash('error', 'Lead not found');
    to('modules/leads/index.php');
}

$statusColors = ['new' => 'bg-blue-100 text-blue-800', 'contacted' => 'bg-yellow-100 text-yellow-800', 'qualified' => 'bg-purple-100 text-purple-800', 'proposal_sent' => 'bg-indigo-100 text-indigo-800', 'negotiation' => 'bg-orange-100 text-orange-800', 'won' => 'bg-green-100 text-green-800', 'lost' => 'bg-red-100 text-red-800'];
$priorityColors = ['low' => 'bg-gray-100 text-gray-800', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="<?= url('modules/leads/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Leads
    </a>
    <div class="flex gap-2">
        <?php if (hasPermission('leads.edit')): ?>
        <a href="<?= url('modules/leads/edit.php?id=' . $lead['id']) ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-edit mr-2"></i>Edit Lead
        </a>
        <?php endif; ?>
        <?php if (hasPermission('leads.create')): ?>
        <a href="<?= url('modules/leads/create.php') ?>" class="bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200 transition">
            <i class="fas fa-plus mr-2"></i>Add Lead
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Lead Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Lead Code</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($lead['lead_code']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Company Name</label>
                    <p class="text-gray-900"><?= sanitize($lead['company_name']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Source</label>
                    <p class="text-gray-900"><?= sanitize($lead['source'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Country</label>
                    <p class="text-gray-900"><?= sanitize($lead['country'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Expected Value</label>
                    <p class="text-gray-900"><?= $lead['expected_value'] ? formatMoney($lead['expected_value'], 0) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Created</label>
                    <p class="text-gray-900"><?= $lead['created_at'] ? date('M d, Y', strtotime($lead['created_at'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Status</label>
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$lead['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($lead['status']) ?></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Priority</label>
                    <span class="px-2 py-1 text-xs rounded <?= $priorityColors[$lead['priority']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($lead['priority']) ?></span>
                </div>
            </div>
            <?php if ($lead['notes']): ?>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-500">Notes</label>
                <p class="text-gray-900 whitespace-pre-line"><?= sanitize($lead['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="h-16 w-16 rounded-full bg-indigo-500 flex items-center justify-center text-white text-2xl font-bold">
                <?= strtoupper(substr($lead['company_name'], 0, 1)) ?>
            </div>
            <h3 class="mt-4 text-lg font-semibold"><?= sanitize($lead['company_name']) ?></h3>
            <p class="text-sm text-gray-600"><?= sanitize($lead['lead_code']) ?></p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Contact</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Contact Person</label>
                    <p class="text-gray-900"><?= sanitize($lead['contact_person'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Email</label>
                    <p class="text-gray-900"><?= sanitize($lead['email'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Phone</label>
                    <p class="text-gray-900"><?= sanitize($lead['phone'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Assigned To</label>
                    <p class="text-gray-900"><?= sanitize(trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''))) ?: 'Unassigned' ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
