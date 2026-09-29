<?php

/**
 * Salary Payment Batches
 * Phase F10 - Payroll & Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10.Payments
 */

$pageTitle = 'Payroll Payment Batches';

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.view')) {
    redirect_to('no-access.php');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;

/*
|--------------------------------------------------------------------------
| Query Conditions
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= "
        AND (
            pb.reference_number LIKE ?
            OR CAST(pb.payroll_run_id AS CHAR) LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($statusFilter !== '') {
    $where .= " AND pb.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom !== '') {
    $where .= " AND pb.payment_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where .= " AND pb.payment_date <= ?";
    $params[] = $dateTo;
}

/*
|--------------------------------------------------------------------------
| Pagination Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM payroll_payment_batches pb
    $where
");

$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

$pagination = paginate($total, $perPage, $page);

/*
|--------------------------------------------------------------------------
| Fetch Payment Batches
|--------------------------------------------------------------------------
*/
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT
        pb.id,
        pb.payment_date,
        pb.total_amount,
        pb.reference_number,
        pb.status,
        pb.payroll_run_id AS run_id,
        ba.account_name AS bank_account
    FROM payroll_payment_batches pb
    LEFT JOIN bank_accounts ba
        ON pb.bank_account_id = ba.id
    $where
    ORDER BY pb.payment_date DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$batches = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/
$statusBadge = [
    'Pending'  => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'Approved' => 'bg-blue-100 text-blue-800 border-blue-200',
    'Paid'     => 'bg-green-100 text-green-800 border-green-200',
    'Failed'   => 'bg-red-100 text-red-800 border-red-200',
];

?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Payroll Payment Batches
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                View and manage employee salary payment batches.
            </p>

        </div>


        <?php if (hasPermission('payroll_finance.create')): ?>

            <a
                href="create.php"
                class="inline-flex items-center justify-center gap-2
                       px-4 py-2
                       bg-blue-600
                       text-white
                       text-sm font-medium
                       border border-blue-600
                       hover:bg-blue-700
                       transition">
                <i class="fa-solid fa-plus"></i>
                Create Payment Batch
            </a>

        <?php endif; ?>

    </div>


    <!-- =========================================================
         FILTER CARD
    ========================================================== -->
    <div class="bg-white border border-gray-200 shadow-sm mb-6">

        <div class="px-5 py-4 border-b border-gray-200">

            <div class="flex items-center gap-2">

                <i class="fa-solid fa-filter text-blue-600"></i>

                <h2 class="text-sm font-semibold text-gray-800">
                    Search & Filters
                </h2>

            </div>

        </div>


        <form method="GET" class="p-5">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">

                <!-- Search -->
                <div class="lg:col-span-1">

                    <label
                        for="search"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Reference / Run ID"
                        class="w-full px-3 py-2
                               border border-gray-300
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                </div>


                <!-- Status -->
                <div>

                    <label
                        for="status"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Status
                    </label>

                    <select
                        name="status"
                        id="status"
                        class="w-full px-3 py-2
                               border border-gray-300
                               bg-white
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="Pending"
                            <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>
                            Pending
                        </option>

                        <option
                            value="Approved"
                            <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>
                            Approved
                        </option>

                        <option
                            value="Paid"
                            <?= $statusFilter === 'Paid' ? 'selected' : '' ?>>
                            Paid
                        </option>

                        <option
                            value="Failed"
                            <?= $statusFilter === 'Failed' ? 'selected' : '' ?>>
                            Failed
                        </option>

                    </select>

                </div>


                <!-- Date From -->
                <div>

                    <label
                        for="date_from"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="<?= htmlspecialchars($dateFrom) ?>"
                        class="w-full px-3 py-2
                               border border-gray-300
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                </div>


                <!-- Date To -->
                <div>

                    <label
                        for="date_to"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="<?= htmlspecialchars($dateTo) ?>"
                        class="w-full px-3 py-2
                               border border-gray-300
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                </div>


                <!-- Buttons -->
                <div class="flex gap-2">

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2
                               px-4 py-2
                               bg-blue-600
                               text-white
                               text-sm font-medium
                               border border-blue-600
                               hover:bg-blue-700
                               transition">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Filter
                    </button>


                    <?php if (
                        $search !== '' ||
                        $statusFilter !== '' ||
                        $dateFrom !== '' ||
                        $dateTo !== ''
                    ): ?>

                        <a
                            href="index.php"
                            class="inline-flex items-center justify-center gap-2
                                   px-4 py-2
                                   bg-white
                                   text-gray-700
                                   text-sm font-medium
                                   border border-gray-300
                                   hover:bg-gray-50
                                   transition">
                            <i class="fa-solid fa-rotate-left"></i>
                            Reset
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </form>

    </div>


    <!-- =========================================================
         TABLE HEADER
    ========================================================== -->
    <div class="flex items-center justify-between mb-3">

        <div>

            <h2 class="text-lg font-semibold text-gray-800">
                Payment Batches
            </h2>

            <p class="text-sm text-gray-500">
                <?= $total ?> payment batch(s) found
            </p>

        </div>

    </div>


    <!-- =========================================================
         TABLE
    ========================================================== -->
    <div class="bg-white border border-gray-200 shadow-sm overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b border-gray-200">

                    <tr>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Payment Batch
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Payroll Run
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Bank Account
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Payment Date
                        </th>

                        <th class="px-4 py-3 text-right font-semibold text-gray-700">
                            Amount
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Reference Number
                        </th>

                        <th class="px-4 py-3 text-center font-semibold text-gray-700">
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($batches)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-gray-500">

                                <div class="flex flex-col items-center">

                                    <i
                                        class="fa-solid fa-money-check-dollar
                                               text-3xl text-gray-300 mb-3"></i>

                                    <p class="font-medium text-gray-600">
                                        No payment batches found
                                    </p>

                                    <p class="text-sm text-gray-400 mt-1">
                                        Try changing the search or filter criteria.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($batches as $b): ?>

                            <?php
                            $currentStatus = trim($b['status'] ?? '');

                            $badgeClass = $statusBadge[$currentStatus]
                                ?? 'bg-gray-100 text-gray-800 border-gray-200';
                            ?>

                            <tr class="hover:bg-gray-50 transition">

                                <!-- Payment Batch -->
                                <td class="px-4 py-3">

                                    <div class="font-medium text-gray-800">
                                        #<?= (int)$b['id'] ?>
                                    </div>

                                    <div class="text-xs text-gray-500 mt-0.5">
                                        Payment Batch
                                    </div>

                                </td>


                                <!-- Payroll Run -->
                                <td class="px-4 py-3">

                                    <span
                                        class="inline-flex items-center
                                               px-2.5 py-1
                                               text-xs font-medium
                                               bg-blue-100
                                               text-blue-800
                                               border border-blue-200">
                                        Run #<?= (int)$b['run_id'] ?>
                                    </span>

                                </td>


                                <!-- Bank Account -->
                                <td class="px-4 py-3">

                                    <?php if (!empty($b['bank_account'])): ?>

                                        <div class="flex items-center gap-2">

                                            <i
                                                class="fa-solid fa-building-columns
                                                       text-gray-400"></i>

                                            <span class="text-gray-700">
                                                <?= htmlspecialchars($b['bank_account']) ?>
                                            </span>

                                        </div>

                                    <?php else: ?>

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Payment Date -->
                                <td class="px-4 py-3">

                                    <div class="text-gray-700">

                                        <?= htmlspecialchars(
                                            formatDate($b['payment_date'])
                                        ) ?>

                                    </div>

                                </td>


                                <!-- Amount -->
                                <td class="px-4 py-3 text-right">

                                    <span class="font-semibold text-gray-900">
                                        <?= htmlspecialchars(
                                            formatMoney($b['total_amount'])
                                        ) ?>
                                    </span>

                                </td>


                                <!-- Reference -->
                                <td class="px-4 py-3">

                                    <?php if (!empty($b['reference_number'])): ?>

                                        <span class="text-gray-700">
                                            <?= htmlspecialchars($b['reference_number']) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Status -->
                                <td class="px-4 py-3 text-center">

                                    <span
                                        class="inline-flex items-center gap-1.5
                                               px-2.5 py-1
                                               text-xs font-medium
                                               border
                                               <?= $badgeClass ?>">

                                        <?php if (strtolower($currentStatus) === 'paid'): ?>

                                            <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>

                                        <?php elseif (strtolower($currentStatus) === 'failed'): ?>

                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>

                                        <?php elseif (strtolower($currentStatus) === 'approved'): ?>

                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>

                                        <?php elseif (strtolower($currentStatus) === 'pending'): ?>

                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span>

                                        <?php else: ?>

                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>

                                        <?php endif; ?>

                                        <?= htmlspecialchars($currentStatus) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =========================================================
         PAGINATION
    ========================================================== -->
    <div class="mt-5">

        <?= paginationLinks($pagination, '?') ?>

    </div>

</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>