<?php
$pageTitle = 'Purchase Orders';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('purchase_orders.view')) {
    die('Access Denied');
}

$db = getDB();

// --- Filters ---
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
    $where .= " AND (po.po_number LIKE ? OR v.vendor_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($vendor_id) {
    $where .= " AND po.vendor_id = ?";
    $params[] = (int)$vendor_id;
}

if ($status !== '') {
    $where .= " AND po.status = ?";
    $params[] = $status;
}

if ($date_from) {
    $where .= " AND po.order_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where .= " AND po.order_date <= ?";
    $params[] = $date_to;
}

// Count total
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    $where
");

$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$pagination = paginate($total, $perPage, $page);

// Fetch POs
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $db->prepare("
    SELECT 
        po.*,
        v.vendor_name,
        v.vendor_code
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    $where
    ORDER BY po.created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);
$pos = $stmt->fetchAll();

// Vendors for filter
$vendors = $db->query("
    SELECT id, vendor_code, vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();

// Stats
$stats = [];

$stats['total'] = $db->query("
    SELECT COUNT(*)
    FROM purchase_orders
")->fetchColumn();

$stats['pending'] = $db->query("
    SELECT COUNT(*)
    FROM purchase_orders
    WHERE status IN ('draft','approved','sent','acknowledged')
")->fetchColumn();

$stats['received'] = $db->query("
    SELECT COUNT(*)
    FROM purchase_orders
    WHERE status IN ('received','closed','fully_billed')
")->fetchColumn();

$stats['cancelled'] = $db->query("
    SELECT COUNT(*)
    FROM purchase_orders
    WHERE status = 'cancelled'
")->fetchColumn();

// Status badges
$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'sent' => 'bg-indigo-100 text-indigo-800',
    'acknowledged' => 'bg-purple-100 text-purple-800',
    'partially_received' => 'bg-yellow-100 text-yellow-800',
    'received' => 'bg-green-100 text-green-800',
    'closed' => 'bg-gray-300 text-gray-700',
    'fully_billed' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-red-100 text-red-800',
];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Purchase Orders</h1>
        <p class="text-gray-600">Manage purchase orders and procurement</p>
    </div>

    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Finance
        </a>

        <?php if (hasPermission('purchase_orders.create')): ?>
            <a href="create.php"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>New PO
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

    <!-- Total -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total POs</p>
                <p class="text-xl font-bold text-gray-800">
                    <?= (int)$stats['total'] ?>
                </p>
            </div>

            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-file-invoice text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Pending -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Pending</p>
                <p class="text-xl font-bold text-yellow-600">
                    <?= (int)$stats['pending'] ?>
                </p>
            </div>

            <div class="bg-yellow-100 p-3 rounded-full">
                <i class="fas fa-clock text-yellow-600"></i>
            </div>
        </div>
    </div>

    <!-- Received -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Received/Closed</p>
                <p class="text-xl font-bold text-green-600">
                    <?= (int)$stats['received'] ?>
                </p>
            </div>

            <div class="bg-green-100 p-3 rounded-full">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
        </div>
    </div>

    <!-- Cancelled -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Cancelled</p>
                <p class="text-xl font-bold text-red-600">
                    <?= (int)$stats['cancelled'] ?>
                </p>
            </div>

            <div class="bg-red-100 p-3 rounded-full">
                <i class="fas fa-ban text-red-600"></i>
            </div>
        </div>
    </div>

</div>

<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6">

    <form method="GET"
        id="purchaseOrderFilterForm"
        class="p-4 flex flex-wrap gap-4 items-end"
        novalidate>

        <!-- Search -->
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="PO # or Vendor..."
                maxlength="100"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Vendor -->
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Vendor
            </label>

            <select
                name="vendor_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">All Vendors</option>

                <?php foreach ($vendors as $v): ?>
                    <option
                        value="<?= (int)$v['id'] ?>"
                        <?= $vendor_id == $v['id'] ? 'selected' : '' ?>>
                        <?= sanitize($v['vendor_name']) ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </div>

        <!-- Status -->
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">All</option>

                <option value="draft"
                    <?= $status === 'draft' ? 'selected' : '' ?>>
                    Draft
                </option>

                <option value="approved"
                    <?= $status === 'approved' ? 'selected' : '' ?>>
                    Approved
                </option>

                <option value="sent"
                    <?= $status === 'sent' ? 'selected' : '' ?>>
                    Sent
                </option>

                <option value="acknowledged"
                    <?= $status === 'acknowledged' ? 'selected' : '' ?>>
                    Acknowledged
                </option>

                <option value="partially_received"
                    <?= $status === 'partially_received' ? 'selected' : '' ?>>
                    Partially Received
                </option>

                <option value="received"
                    <?= $status === 'received' ? 'selected' : '' ?>>
                    Received
                </option>

                <option value="closed"
                    <?= $status === 'closed' ? 'selected' : '' ?>>
                    Closed
                </option>

                <option value="fully_billed"
                    <?= $status === 'fully_billed' ? 'selected' : '' ?>>
                    Fully Billed
                </option>

                <option value="cancelled"
                    <?= $status === 'cancelled' ? 'selected' : '' ?>>
                    Cancelled
                </option>

            </select>
        </div>

        <!-- Date From -->
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Date From
            </label>

            <input
                type="date"
                name="date_from"
                value="<?= sanitize($date_from) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Date To -->
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Date To
            </label>

            <input
                type="date"
                name="date_to"
                value="<?= sanitize($date_to) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Search Button -->
        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

            <i class="fas fa-search"></i>

        </button>

        <?php if ($search || $vendor_id || $status !== '' || $date_from || $date_to): ?>

            <a href="index.php"
                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">

                <i class="fas fa-times"></i>

            </a>

        <?php endif; ?>

    </form>

</div>

<!-- PO Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">
                <tr>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        PO #
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Vendor
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Order Date
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Delivery
                    </th>

                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                        Status
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Total
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">

                <?php foreach ($pos as $po): ?>

                    <?php
                    /*
                     * Safe values
                     *
                     * These prevent Undefined array key warnings.
                     */
                    $poNumber = $po['po_number'] ?? '';

                    $vendorName = $po['vendor_name'] ?? '';

                    $orderDate = $po['order_date']
                        ?? $po['po_date']
                        ?? $po['purchase_date']
                        ?? null;

                    $deliveryDate = $po['expected_delivery_date']
                        ?? $po['delivery_date']
                        ?? null;

                    $totalAmount = $po['total_amount']
                        ?? $po['grand_total']
                        ?? $po['total']
                        ?? $po['net_total']
                        ?? 0;

                    $poStatus = $po['status'] ?? 'draft';

                    $formattedOrderDate = '--';

                    if (!empty($orderDate)) {
                        $timestamp = strtotime($orderDate);

                        if ($timestamp !== false) {
                            $formattedOrderDate = date('M d, Y', $timestamp);
                        }
                    }

                    $formattedDeliveryDate = '--';

                    if (!empty($deliveryDate)) {
                        $timestamp = strtotime($deliveryDate);

                        if ($timestamp !== false) {
                            $formattedDeliveryDate = date('M d, Y', $timestamp);
                        }
                    }
                    ?>

                    <tr class="hover:bg-gray-50">

                        <!-- PO Number -->
                        <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900 whitespace-nowrap">
                            <?= sanitize($poNumber) ?>
                        </td>

                        <!-- Vendor -->
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= sanitize($vendorName) ?>
                        </td>

                        <!-- Order Date -->
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            <?= $formattedOrderDate ?>
                        </td>

                        <!-- Delivery -->
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            <?= $formattedDeliveryDate ?>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3 text-center whitespace-nowrap">

                            <span class="px-2 py-1 text-xs rounded font-medium
                            <?= $statusBadges[$poStatus] ?? 'bg-gray-100 text-gray-800' ?>">

                                <?= ucwords(str_replace('_', ' ', $poStatus)) ?>

                            </span>

                        </td>

                        <!-- Total -->
                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap">
                            <?= formatMoney((float)$totalAmount) ?>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3 text-right whitespace-nowrap">

                            <!-- View -->
                            <a
                                href="view.php?id=<?= (int)$po['id'] ?>"
                                class="text-blue-600 hover:text-blue-900 mr-2"
                                title="View">

                                <i class="fas fa-eye"></i>

                            </a>

                            <!-- Edit -->
                            <?php if ($poStatus === 'draft' && hasPermission('purchase_orders.edit')): ?>

                                <a
                                    href="edit.php?id=<?= (int)$po['id'] ?>"
                                    class="text-yellow-600 hover:text-yellow-900 mr-2"
                                    title="Edit">

                                    <i class="fas fa-pencil"></i>

                                </a>

                            <?php endif; ?>

                            <!-- Approve -->
                            <?php if ($poStatus === 'draft' && hasPermission('purchase_orders.approve')): ?>

                                <form
                                    method="POST"
                                    action="approve.php"
                                    style="display:inline"
                                    onsubmit="return confirm('Approve PO <?= sanitize(addslashes($poNumber)) ?>?')">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= csrfToken() ?>">

                                    <input
                                        type="hidden"
                                        name="po_id"
                                        value="<?= (int)$po['id'] ?>">

                                    <button
                                        type="submit"
                                        class="text-green-600 hover:text-green-900 mr-2"
                                        title="Approve">

                                        <i class="fas fa-check"></i>

                                    </button>

                                </form>

                            <?php endif; ?>

                            <!-- Cancel -->
                            <?php if (
                                !in_array(
                                    $poStatus,
                                    ['received', 'closed', 'cancelled', 'fully_billed'],
                                    true
                                )
                                && hasPermission('purchase_orders.cancel')
                            ): ?>

                                <form
                                    method="POST"
                                    action="cancel.php"
                                    style="display:inline"
                                    onsubmit="return confirm('Cancel PO <?= sanitize(addslashes($poNumber)) ?>?')">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= csrfToken() ?>">

                                    <input
                                        type="hidden"
                                        name="po_id"
                                        value="<?= (int)$po['id'] ?>">

                                    <button
                                        type="submit"
                                        class="text-red-600 hover:text-red-900"
                                        title="Cancel">

                                        <i class="fas fa-ban"></i>

                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($pos)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-gray-500">

                            <i class="fas fa-file-invoice text-4xl mb-3 text-gray-300"></i>

                            <p>No purchase orders found</p>

                            <?php if (hasPermission('purchase_orders.create')): ?>

                                <a
                                    href="create.php"
                                    class="text-blue-600 hover:underline mt-2 inline-block">

                                    Create your first PO

                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?= paginationLinks($pagination, '?') ?>

<!-- jQuery Validation -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        // Search input
        $('input[name="search"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/[^A-Za-z0-9 ._\/#\-]/g, '');
            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 100));
        });

        // Valid Vendor ID
        $.validator.addMethod('validVendorId', function(value, element) {

            if (value === '') {
                return true;
            }

            return /^[0-9]+$/.test(value) && parseInt(value, 10) > 0;

        }, 'Please select a valid vendor.');

        // Valid PO Status
        $.validator.addMethod('validPOStatus', function(value, element) {

            const allowedStatuses = [
                '',
                'draft',
                'approved',
                'sent',
                'acknowledged',
                'partially_received',
                'received',
                'closed',
                'fully_billed',
                'cancelled'
            ];

            return allowedStatuses.includes(value);

        }, 'Please select a valid status.');

        // Date Range
        $.validator.addMethod('validDateRange', function(value, element) {

            const dateFrom = $('input[name="date_from"]').val();
            const dateTo = $('input[name="date_to"]').val();

            if (!dateFrom || !dateTo) {
                return true;
            }

            return dateFrom <= dateTo;

        }, 'Date To must be greater than or equal to Date From.');

        // Form validation
        $('#purchaseOrderFilterForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {
                    element.parent().append(error);
                } else {
                    error.insertAfter(element);
                }

            },

            highlight: function(element) {
                $(element).addClass('border-red-500');
            },

            unhighlight: function(element) {
                $(element).removeClass('border-red-500');
            },

            rules: {

                search: {
                    maxlength: 100
                },

                vendor_id: {
                    validVendorId: true
                },

                status: {
                    validPOStatus: true
                },

                date_from: {
                    date: true
                },

                date_to: {
                    date: true,
                    validDateRange: true
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 100 characters.'
                },

                vendor_id: {
                    validVendorId: 'Please select a valid vendor.'
                },

                status: {
                    validPOStatus: 'Please select a valid purchase order status.'
                },

                date_from: {
                    date: 'Please enter a valid From date.'
                },

                date_to: {
                    date: 'Please enter a valid To date.',
                    validDateRange: 'Date To must be greater than or equal to Date From.'
                }

            }

        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>