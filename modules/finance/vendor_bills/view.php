<?php
$pageTitle = 'View Vendor Bill';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('vendor_bills.view')) {
    die('Access Denied');
}

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid bill ID.');
    redirect('index.php');
}

/*
|--------------------------------------------------------------------------
| Vendor Bill
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT
        vb.*,
        v.vendor_name,
        v.vendor_code,
        v.contact_person,
        v.email,
        v.phone,
        v.address,
        po.po_number,
        grn.grn_number,
        u.name AS created_by_name,
        je.voucher_no AS je_voucher_no
    FROM vendor_bills vb
    JOIN vendors v
        ON vb.vendor_id = v.id
    LEFT JOIN purchase_orders po
        ON vb.po_id = po.id
    LEFT JOIN goods_receipt_notes grn
        ON vb.grn_id = grn.id
    LEFT JOIN users u
        ON vb.created_by = u.id
    LEFT JOIN journal_entries je
        ON vb.journal_entry_id = je.id
    WHERE vb.id = ?
");

$stmt->execute([$id]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Vendor bill not found.');
    redirect('index.php');
}

/*
|--------------------------------------------------------------------------
| Bill Items
|--------------------------------------------------------------------------
*/
$itemsStmt = $db->prepare("
    SELECT *
    FROM vendor_bill_items
    WHERE bill_id = ?
    ORDER BY sort_order, id
");

$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status History
|--------------------------------------------------------------------------
*/
$historyStmt = $db->prepare("
    SELECT
        bsh.*,
        u.name AS full_name
    FROM bill_status_history bsh
    LEFT JOIN users u
        ON bsh.changed_by = u.id
    WHERE bsh.bill_id = ?
    ORDER BY bsh.created_at ASC
");

$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Payment Allocations
|--------------------------------------------------------------------------
*/
$allocationsStmt = $db->prepare("
    SELECT
        pa.*,
        vp.payment_number,
        vp.payment_date
    FROM payment_allocations pa
    JOIN vendor_payments vp
        ON pa.payment_id = vp.id
    WHERE pa.bill_id = ?
    ORDER BY pa.created_at DESC
");

$allocationsStmt->execute([$id]);
$allocations = $allocationsStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/
$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'partially_paid' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'overdue' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];

$statusClass = $statusBadges[$bill['status']] ?? 'bg-gray-100 text-gray-800';

$statusLabel = ucwords(
    str_replace('_', ' ', $bill['status'])
);

$billDate = !empty($bill['bill_date'])
    ? date('M d, Y', strtotime($bill['bill_date']))
    : '--';

$dueDate = !empty($bill['due_date'])
    ? date('M d, Y', strtotime($bill['due_date']))
    : '--';

$isOverdue =
    !empty($bill['due_date']) &&
    strtotime($bill['due_date']) < time() &&
    (float)$bill['balance_amount'] > 0;
?>

<!-- =========================================================
     PAGE HEADER
========================================================= -->
<div class="mb-6 flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Bill: <?= sanitize($bill['bill_number']) ?>
        </h1>

        <p class="text-gray-600 mt-1">
            <?= sanitize($bill['vendor_name']) ?>
            ·
            <?= $billDate ?>
        </p>
    </div>

    <div class="flex flex-wrap gap-2">

        <!-- Back -->
        <a
            href="index.php"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>
            Back
        </a>

        <!-- Edit -->
        <?php if (
            $bill['status'] === 'draft' &&
            hasPermission('vendor_bills.edit')
        ): ?>

            <a
                href="edit.php?id=<?= $id ?>"
                class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition">
                <i class="fas fa-pencil mr-2"></i>
                Edit
            </a>

        <?php endif; ?>

        <!-- Approve -->
        <?php if (
            $bill['status'] === 'draft' &&
            hasPermission('vendor_bills.approve')
        ): ?>

            <form
                method="POST"
                action="approve.php"
                class="inline">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= csrfToken() ?>">

                <input
                    type="hidden"
                    name="bill_id"
                    value="<?= $id ?>">

                <button
                    type="submit"
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>
                    Approve & Post
                </button>
            </form>

        <?php endif; ?>

        <!-- Cancel -->
        <?php if (
            !in_array($bill['status'], ['paid', 'cancelled'], true) &&
            hasPermission('vendor_bills.cancel')
        ): ?>

            <form
                method="POST"
                action="cancel.php"
                class="inline">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= csrfToken() ?>">

                <input
                    type="hidden"
                    name="bill_id"
                    value="<?= $id ?>">

                <button
                    type="submit"
                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
                    <i class="fas fa-ban mr-2"></i>
                    Cancel
                </button>
            </form>

        <?php endif; ?>

    </div>
</div>


<!-- =========================================================
     MAIN GRID
========================================================= -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- =====================================================
         LEFT COLUMN
    ====================================================== -->
    <div class="lg:col-span-1">

        <!-- =================================================
             BILL DETAILS
        ================================================== -->
        <div class="bg-white rounded-lg shadow p-6">

            <div class="flex justify-between items-start mb-4">

                <h3 class="text-lg font-semibold text-gray-800">
                    Bill Details
                </h3>

                <span
                    class="px-3 py-1 text-sm rounded font-medium <?= $statusClass ?>">
                    <?= sanitize($statusLabel) ?>
                </span>

            </div>

            <dl class="space-y-3">

                <!-- Vendor -->
                <div>
                    <dt class="text-sm text-gray-500">
                        Vendor
                    </dt>

                    <dd class="text-sm font-medium">

                        <a
                            href="../vendor_master/view.php?id=<?= (int)$bill['vendor_id'] ?>"
                            class="text-blue-600 hover:underline">
                            <?= sanitize($bill['vendor_name']) ?>
                        </a>

                    </dd>
                </div>


                <!-- Vendor Code -->
                <?php if (!empty($bill['vendor_code'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            Vendor Code
                        </dt>

                        <dd class="text-sm font-medium">
                            <?= sanitize($bill['vendor_code']) ?>
                        </dd>
                    </div>

                <?php endif; ?>


                <!-- Contact Person -->
                <?php if (!empty($bill['contact_person'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            Contact Person
                        </dt>

                        <dd class="text-sm font-medium">
                            <?= sanitize($bill['contact_person']) ?>
                        </dd>
                    </div>

                <?php endif; ?>


                <!-- Email -->
                <?php if (!empty($bill['email'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            Email
                        </dt>

                        <dd class="text-sm font-medium">
                            <?= sanitize($bill['email']) ?>
                        </dd>
                    </div>

                <?php endif; ?>


                <!-- Phone -->
                <?php if (!empty($bill['phone'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            Phone
                        </dt>

                        <dd class="text-sm font-medium">
                            <?= sanitize($bill['phone']) ?>
                        </dd>
                    </div>

                <?php endif; ?>


                <!-- Bill Date -->
                <div>
                    <dt class="text-sm text-gray-500">
                        Bill Date
                    </dt>

                    <dd class="text-sm font-medium">
                        <?= $billDate ?>
                    </dd>
                </div>


                <!-- Due Date -->
                <div>
                    <dt class="text-sm text-gray-500">
                        Due Date
                    </dt>

                    <dd
                        class="text-sm font-medium <?= $isOverdue ? 'text-red-600 font-bold' : '' ?>">
                        <?= $dueDate ?>

                        <?php if ($isOverdue): ?>
                            <span class="ml-1 text-xs">
                                (Overdue)
                            </span>
                        <?php endif; ?>

                    </dd>
                </div>


                <!-- Currency -->
                <div>
                    <dt class="text-sm text-gray-500">
                        Currency
                    </dt>

                    <dd class="text-sm font-medium">
                        <?= sanitize($bill['currency'] ?? '--') ?>
                    </dd>
                </div>


                <!-- Vendor Invoice -->
                <?php if (!empty($bill['vendor_invoice_number'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            Vendor Invoice #
                        </dt>

                        <dd class="text-sm font-medium">
                            <?= sanitize($bill['vendor_invoice_number']) ?>
                        </dd>
                    </div>

                <?php endif; ?>


                <!-- PO Reference -->
                <?php if (!empty($bill['po_number'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            PO Reference
                        </dt>

                        <dd class="text-sm font-medium">

                            <a
                                href="../purchase_orders/view.php?id=<?= (int)$bill['po_id'] ?>"
                                class="text-blue-600 hover:underline">
                                <?= sanitize($bill['po_number']) ?>
                            </a>

                        </dd>
                    </div>

                <?php endif; ?>


                <!-- GRN Reference -->
                <?php if (!empty($bill['grn_number'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            GRN Reference
                        </dt>

                        <dd class="text-sm font-medium">

                            <a
                                href="../goods_receipt/view.php?id=<?= (int)$bill['grn_id'] ?>"
                                class="text-blue-600 hover:underline">
                                <?= sanitize($bill['grn_number']) ?>
                            </a>

                        </dd>
                    </div>

                <?php endif; ?>


                <!-- Journal Voucher -->
                <?php if (!empty($bill['je_voucher_no'])): ?>

                    <div>
                        <dt class="text-sm text-gray-500">
                            Journal Voucher
                        </dt>

                        <dd class="text-sm font-medium">
                            <?= sanitize($bill['je_voucher_no']) ?>
                        </dd>
                    </div>

                <?php endif; ?>


                <!-- Created By -->
                <div>
                    <dt class="text-sm text-gray-500">
                        Created By
                    </dt>

                    <dd class="text-sm font-medium">
                        <?= sanitize($bill['created_by_name'] ?? '--') ?>
                    </dd>
                </div>

            </dl>


            <!-- Notes -->
            <?php if (!empty($bill['notes'])): ?>

                <div class="mt-4">

                    <h4 class="text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </h4>

                    <p class="text-sm text-gray-600 whitespace-pre-line">
                        <?= sanitize($bill['notes']) ?>
                    </p>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             AMOUNT SUMMARY
        ================================================== -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">

            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                Amount Summary
            </h3>

            <dl class="space-y-3">

                <!-- Subtotal -->
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">
                        Subtotal
                    </span>

                    <span class="text-sm font-mono">
                        <?= formatMoney((float)$bill['subtotal']) ?>
                    </span>
                </div>


                <!-- Discount -->
                <?php if ((float)$bill['discount_amount'] > 0): ?>

                    <div class="flex justify-between">

                        <span class="text-sm text-gray-500">
                            Discount
                        </span>

                        <span class="text-sm font-mono text-red-600">
                            -<?= formatMoney((float)$bill['discount_amount']) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- Tax -->
                <?php if ((float)$bill['tax_amount'] > 0): ?>

                    <div class="flex justify-between">

                        <span class="text-sm text-gray-500">
                            Tax
                        </span>

                        <span class="text-sm font-mono">
                            <?= formatMoney((float)$bill['tax_amount']) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- Total -->
                <div class="flex justify-between border-t pt-2">

                    <span class="text-sm font-bold text-gray-700">
                        Total
                    </span>

                    <span class="text-sm font-mono font-bold">
                        <?= formatMoney((float)$bill['total_amount']) ?>
                    </span>

                </div>


                <!-- Paid -->
                <div class="flex justify-between">

                    <span class="text-sm text-gray-500">
                        Paid
                    </span>

                    <span class="text-sm font-mono text-green-600">
                        <?= formatMoney((float)$bill['paid_amount']) ?>
                    </span>

                </div>


                <!-- Balance -->
                <?php
                $balance = (float)$bill['balance_amount'];
                $balanceClass = $balance > 0
                    ? 'text-red-600'
                    : 'text-green-600';
                ?>

                <div class="flex justify-between border-t pt-2">

                    <span class="text-sm font-bold <?= $balanceClass ?>">
                        Balance
                    </span>

                    <span class="text-sm font-mono font-bold <?= $balanceClass ?>">
                        <?= formatMoney($balance) ?>
                    </span>

                </div>

            </dl>

        </div>

    </div>


    <!-- =====================================================
         RIGHT COLUMN
    ====================================================== -->
    <div class="lg:col-span-2 space-y-6">

        <!-- =================================================
             LINE ITEMS
        ================================================== -->
        <div class="bg-white rounded-lg shadow">

            <div class="px-4 py-3 border-b">

                <h3 class="font-semibold text-gray-800">
                    Line Items
                </h3>

            </div>

            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                #
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Description
                            </th>

                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Qty
                            </th>

                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Price
                            </th>

                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Disc
                            </th>

                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Tax
                            </th>

                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Total
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-200">

                        <?php if (empty($items)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="px-3 py-6 text-center text-sm text-gray-500">
                                    No line items found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($items as $i => $item): ?>

                                <tr class="hover:bg-gray-50">

                                    <td class="px-3 py-2 text-sm text-gray-500">
                                        <?= $i + 1 ?>
                                    </td>

                                    <td class="px-3 py-2 text-sm text-gray-900">
                                        <?= sanitize($item['description'] ?? '--') ?>
                                    </td>

                                    <td class="px-3 py-2 text-sm text-right font-mono">
                                        <?= number_format((float)$item['quantity'], 3) ?>
                                    </td>

                                    <td class="px-3 py-2 text-sm text-right font-mono">
                                        <?= formatMoney((float)$item['unit_price']) ?>
                                    </td>

                                    <td class="px-3 py-2 text-sm text-right font-mono">

                                        <?php if ((float)$item['discount_percent'] > 0): ?>

                                            <?= number_format((float)$item['discount_percent'], 2) ?>%

                                        <?php else: ?>

                                            --

                                        <?php endif; ?>

                                    </td>

                                    <td class="px-3 py-2 text-sm text-right font-mono">

                                        <?php if ((float)$item['tax_percent'] > 0): ?>

                                            <?= number_format((float)$item['tax_percent'], 2) ?>%

                                        <?php else: ?>

                                            --

                                        <?php endif; ?>

                                    </td>

                                    <td class="px-3 py-2 text-sm text-right font-mono font-medium">
                                        <?= formatMoney((float)$item['line_total']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =================================================
             PAYMENT ALLOCATIONS
        ================================================== -->
        <?php if (!empty($allocations)): ?>

            <div class="bg-white rounded-lg shadow">

                <div class="px-4 py-3 border-b">

                    <h3 class="font-semibold text-gray-800">
                        Payment Allocations
                    </h3>

                </div>

                <div class="overflow-x-auto">

                    <table class="w-full">

                        <thead class="bg-gray-50">

                            <tr>

                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    Payment #
                                </th>

                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                    Date
                                </th>

                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                    Allocated
                                </th>

                            </tr>

                        </thead>

                        <tbody class="divide-y divide-gray-200">

                            <?php foreach ($allocations as $a): ?>

                                <tr>

                                    <td class="px-4 py-2 text-sm font-mono">

                                        <a
                                            href="../vendor_payments/view.php?id=<?= (int)$a['payment_id'] ?>"
                                            class="text-blue-600 hover:underline">
                                            <?= sanitize($a['payment_number']) ?>
                                        </a>

                                    </td>

                                    <td class="px-4 py-2 text-sm text-gray-600">

                                        <?= !empty($a['payment_date'])
                                            ? date('M d, Y', strtotime($a['payment_date']))
                                            : '--'
                                        ?>

                                    </td>

                                    <td class="px-4 py-2 text-sm text-right font-mono">
                                        <?= formatMoney((float)$a['amount']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATUS HISTORY
        ================================================== -->
        <div class="bg-white rounded-lg shadow">

            <div class="px-4 py-3 border-b">

                <h3 class="font-semibold text-gray-800">
                    Status History
                </h3>

            </div>

            <div class="p-4">

                <?php if (empty($history)): ?>

                    <p class="text-sm text-gray-500">
                        No history recorded.
                    </p>

                <?php else: ?>

                    <div class="space-y-3">

                        <?php foreach ($history as $h): ?>

                            <div class="flex items-start gap-3">

                                <div class="flex-shrink-0 w-2 h-2 mt-2 rounded-full bg-blue-500"></div>

                                <div>

                                    <p class="text-sm">

                                        <span class="font-medium">
                                            <?= !empty($h['from_status'])
                                                ? sanitize(ucwords(str_replace('_', ' ', $h['from_status'])))
                                                : '--'
                                            ?>
                                        </span>

                                        <i class="fas fa-arrow-right text-gray-400 mx-1"></i>

                                        <span class="font-medium">
                                            <?= sanitize(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $h['to_status']
                                                    )
                                                )
                                            ) ?>
                                        </span>

                                    </p>


                                    <p class="text-xs text-gray-500">

                                        <?= sanitize($h['full_name'] ?? '--') ?>

                                        ·

                                        <?= !empty($h['created_at'])
                                            ? date('M d, Y H:i', strtotime($h['created_at']))
                                            : '--'
                                        ?>

                                    </p>


                                    <?php if (!empty($h['change_note'])): ?>

                                        <p class="text-xs text-gray-600 mt-1">
                                            <?= sanitize($h['change_note']) ?>
                                        </p>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<?php require_once '../../../includes/footer.php'; ?>