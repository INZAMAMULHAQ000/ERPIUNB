<?php
$pageTitle = 'Debit Notes';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('debit_notes.view')) {
    die('Access Denied');
}

$db = getDB();

$search = $_GET['search'] ?? '';
$vendor_id = $_GET['vendor_id'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (dn.debit_note_number LIKE ? OR v.vendor_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($vendor_id) {
    $where .= " AND dn.vendor_id = ?";
    $params[] = (int)$vendor_id;
}
if ($status !== '') {
    $where .= " AND dn.status = ?";
    $params[] = $status;
}
if ($date_from) {
    $where .= " AND dn.debit_note_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND dn.debit_note_date <= ?";
    $params[] = $date_to;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM debit_notes dn JOIN vendors v ON dn.vendor_id = v.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT dn.*, v.vendor_name, v.vendor_code,
        vb.bill_number, je.voucher_no AS je_voucher_no
    FROM debit_notes dn
    JOIN vendors v ON dn.vendor_id = v.id
    LEFT JOIN vendor_bills vb ON dn.bill_id = vb.id
    LEFT JOIN journal_entries je ON dn.journal_entry_id = je.id
    $where ORDER BY dn.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$notes = $stmt->fetchAll();

$vendors = $db->query("SELECT id, vendor_code, vendor_name FROM vendors WHERE status = 'active' ORDER BY vendor_name")->fetchAll();

$dnTypes = ['price_correction' => 'Price Correction', 'return' => 'Return', 'discount_adjustment' => 'Discount Adjustment', 'cancellation' => 'Cancellation'];
$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'issued' => 'bg-blue-100 text-blue-800',
    'applied' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Debit Notes</h1>
        <p class="text-gray-600">Manage vendor credit notes and adjustments</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('debit_notes.create')): ?>
            <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Debit Note</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="DN # or Vendor..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
            <select name="vendor_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Vendors</option>
                <?php foreach ($vendors as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $vendor_id == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="issued" <?= $status === 'issued' ? 'selected' : '' ?>>Issued</option>
                <option value="applied" <?= $status === 'applied' ? 'selected' : '' ?>>Applied</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($date_from) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($date_to) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $vendor_id || $status !== '' || $date_from || $date_to): ?>
            <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Debit Note #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill Ref</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($notes as $n): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900"><?= sanitize($n['debit_note_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($n['vendor_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($n['debit_note_date'])) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= $dnTypes[$n['debit_note_type']] ?? $n['debit_note_type'] ?></td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($n['bill_number'] ?? '--') ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$n['status']] ?? 'bg-gray-100' ?>">
                                <?= ucfirst($n['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$n['total_amount']) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="view.php?id=<?= $n['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($notes)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            <i class="fas fa-file-invoice text-4xl mb-3 text-gray-300"></i>
                            <p>No debit notes found</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        function updateToDate() {

            const fromDate = $('#date_from').val();
            const $toDate = $('#date_to');

            if (fromDate) {

                $toDate.attr('min', fromDate);

                if ($toDate.val() && $toDate.val() < fromDate) {
                    $toDate.val('');
                }

            } else {

                $toDate.removeAttr('min');
                $toDate.val('');
            }
        }

        // Page load
        updateToDate();

        // From Date change
        $('#date_from').on('change', function() {

            updateToDate();

            // To Date validation refresh
            $('#date_to').valid();
        });

        // To Date change
        $('#date_to').on('change', function() {

            const fromDate = $('#date_from').val();
            const toDate = $(this).val();

            if (!fromDate) {
                $(this).val('');
                return;
            }

            if (toDate < fromDate) {
                $(this).val('');
            }
        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>