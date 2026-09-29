<?php
$pageTitle = 'Client Details';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('clients.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT c.*, e.first_name, e.last_name FROM clients c LEFT JOIN employees e ON c.assigned_manager = e.id WHERE c.id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    flash('error', 'Client not found');
    to('modules/clients/index.php');
}

$statusColors = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="<?= url('modules/clients/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Clients
    </a>
    <?php if (hasPermission('clients.create')): ?>
    <a href="<?= url('modules/clients/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Client
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Company Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Client Code</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($client['client_code']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Company Name</label>
                    <p class="text-gray-900"><?= sanitize($client['company_name']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Industry</label>
                    <p class="text-gray-900"><?= sanitize($client['industry'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Website</label>
                    <p class="text-gray-900"><?= sanitize($client['website'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">GST/VAT Number</label>
                    <p class="text-gray-900"><?= sanitize($client['gst_vat_number'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Country</label>
                    <p class="text-gray-900"><?= sanitize($client['country'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Status</label>
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$client['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                        <?= ucfirst($client['status']) ?>
                    </span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Created</label>
                    <p class="text-gray-900"><?= $client['created_at'] ? date('M d, Y', strtotime($client['created_at'])) : '-' ?></p>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-500">Address</label>
                <p class="text-gray-900"><?= nl2br(sanitize($client['address'] ?? '-')) ?></p>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="h-16 w-16 rounded-full bg-blue-500 flex items-center justify-center text-white text-2xl font-bold">
                <?= strtoupper(substr($client['company_name'], 0, 1)) ?>
            </div>
            <h3 class="mt-4 text-lg font-semibold"><?= sanitize($client['company_name']) ?></h3>
            <p class="text-sm text-gray-600"><?= sanitize($client['client_code']) ?></p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Contact</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Contact Person</label>
                    <p class="text-gray-900"><?= sanitize($client['contact_person'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Email</label>
                    <p class="text-gray-900"><?= sanitize($client['email'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Phone</label>
                    <p class="text-gray-900"><?= sanitize($client['phone'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Assigned Manager</label>
                    <p class="text-gray-900"><?= sanitize(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))) ?: '-' ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
