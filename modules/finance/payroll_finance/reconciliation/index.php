<?php

/**
 * Payroll Reconciliation
 * Phase F10 - Payroll & Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10.Reconciliation
 */

$pageTitle = 'Payroll Reconciliation';

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.reconcile')) {
    redirect_to('no-access.php');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search       = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND CAST(prr.payroll_run_id AS CHAR) LIKE ?";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '') {
    $where .= " AND prr.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom !== '') {
    $where .= " AND DATE(prr.checked_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where .= " AND DATE(prr.checked_at) <= ?";
    $params[] = $dateTo;
}

/*
|--------------------------------------------------------------------------
| Fetch Reconciliation Records
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT
        prr.id,
        prr.payroll_run_id,
        prr.payroll_total,
        prr.posted_total,
        prr.paid_total,
        prr.difference,
        prr.status,
        prr.checked_at
    FROM payroll_reconciliation prr
    $where
    ORDER BY prr.checked_at DESC, prr.id DESC
");

$stmt->execute($params);
$runs = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Summary Calculations
|--------------------------------------------------------------------------
*/
$totalCalculated = 0.0;
$totalPosted     = 0.0;
$totalPaid       = 0.0;

foreach ($runs as $r) {
    $totalCalculated += (float) $r['payroll_total'];
    $totalPosted     += (float) $r['posted_total'];
    $totalPaid       += (float) $r['paid_total'];
}

$totalDifference = $totalCalculated - $totalPaid;
$totalRuns = count($runs);

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/
$statusBadge = [
    'Reconciled'   => 'bg-green-100 text-green-800 border-green-200',
    'Unreconciled' => 'bg-red-100 text-red-800 border-red-200',
    'Investigation' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
];
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- =========================================================
         Page Header
    ========================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Payroll Reconciliation
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Compare payroll totals with finance posted and bank paid amounts
            </p>
        </div>

    </div>


    <!-- =========================================================
         Search & Filters
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

                <!-- Payroll Run Search -->
                <div class="lg:col-span-1">

                    <label
                        for="search"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Payroll Run ID
                    </label>

                    <div class="relative">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Run ID"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300
                                   focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                   outline-none">

                    </div>

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
                        class="w-full px-3 py-2 text-sm border border-gray-300
                               bg-white focus:border-blue-500
                               focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">All Statuses</option>

                        <option
                            value="Reconciled"
                            <?= $statusFilter === 'Reconciled' ? 'selected' : '' ?>>
                            Reconciled
                        </option>

                        <option
                            value="Unreconciled"
                            <?= $statusFilter === 'Unreconciled' ? 'selected' : '' ?>>
                            Unreconciled
                        </option>

                        <option
                            value="Investigation"
                            <?= $statusFilter === 'Investigation' ? 'selected' : '' ?>>
                            Investigation
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
                        max="<?= htmlspecialchars($dateTo ?: date('Y-m-d')) ?>"
                        class="w-full px-3 py-2 text-sm border border-gray-300
                               focus:border-blue-500 focus:ring-1
                               focus:ring-blue-500 outline-none">

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
                        min="<?= htmlspecialchars($dateFrom) ?>"
                        max="<?= date('Y-m-d') ?>"
                        class="w-full px-3 py-2 text-sm border border-gray-300
                               focus:border-blue-500 focus:ring-1
                               focus:ring-blue-500 outline-none">

                </div>


                <!-- Buttons -->
                <div class="flex gap-2">

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2
                               px-4 py-2
                               bg-blue-600 text-white
                               text-sm font-medium
                               border border-blue-600
                               hover:bg-blue-700
                               transition">
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <?php if (
                        $search !== '' ||
                        $statusFilter !== '' ||
                        $dateFrom !== '' ||
                        $dateTo !== ''
                    ): ?>

                        <a
                            href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                            class="inline-flex items-center justify-center gap-2
                                   px-4 py-2
                                   bg-white text-gray-700
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
         Reconciliation Summary
    ========================================================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <!-- Payroll Calculated -->
        <div class="bg-white border border-gray-200 shadow-sm">

            <div class="p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-gray-500">
                            Payroll Calculated
                        </p>

                        <p class="text-2xl font-bold text-blue-600 mt-1">
                            AED <?= formatNumber($totalCalculated) ?>
                        </p>

                    </div>

                    <div class="w-10 h-10 flex items-center justify-center
                                bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-calculator"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- Finance Posted -->
        <div class="bg-white border border-gray-200 shadow-sm">

            <div class="p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-gray-500">
                            Finance Posted
                        </p>

                        <p class="text-2xl font-bold text-green-600 mt-1">
                            AED <?= formatNumber($totalPosted) ?>
                        </p>

                    </div>

                    <div class="w-10 h-10 flex items-center justify-center
                                bg-green-50 text-green-600">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- Bank Paid -->
        <div class="bg-white border border-gray-200 shadow-sm">

            <div class="p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-gray-500">
                            Bank Paid
                        </p>

                        <p class="text-2xl font-bold text-red-600 mt-1">
                            AED <?= formatNumber($totalPaid) ?>
                        </p>

                    </div>

                    <div class="w-10 h-10 flex items-center justify-center
                                bg-red-50 text-red-600">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- Difference -->
        <div class="bg-white border border-gray-200 shadow-sm">

            <div class="p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-gray-500">
                            Difference
                        </p>

                        <p class="text-2xl font-bold
                            <?= abs($totalDifference) > 0.0001
                                ? 'text-red-600'
                                : 'text-green-600' ?>
                            mt-1">
                            AED <?= formatNumber($totalDifference) ?>
                        </p>

                    </div>

                    <div
                        class="w-10 h-10 flex items-center justify-center
                        <?= abs($totalDifference) > 0.0001
                            ? 'bg-red-50 text-red-600'
                            : 'bg-green-50 text-green-600' ?>">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         Table Header
    ========================================================== -->
    <div class="flex items-center justify-between mb-3">

        <div>

            <h2 class="text-base font-semibold text-gray-800">
                Reconciliation Records
            </h2>

            <p class="text-xs text-gray-500 mt-1">
                <?= $totalRuns ?>
                record<?= $totalRuns !== 1 ? 's' : '' ?> found
            </p>

        </div>

    </div>


    <!-- =========================================================
         Reconciliation Table
    ========================================================== -->
    <div class="bg-white border border-gray-200 shadow-sm overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b border-gray-200">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Payroll Run
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Payroll Calculated
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Finance Posted
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Bank Paid
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Difference
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Status
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Checked At
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($runs)): ?>

                        <tr>

                            <td colspan="7" class="px-5 py-12 text-center">

                                <div class="flex flex-col items-center justify-center">

                                    <div class="w-12 h-12 flex items-center justify-center
                                                bg-gray-100 text-gray-400 mb-3">
                                        <i class="fa-solid fa-scale-balanced text-xl"></i>
                                    </div>

                                    <p class="text-sm font-medium text-gray-700">
                                        No reconciliation records found
                                    </p>

                                    <p class="text-xs text-gray-500 mt-1">
                                        Try changing the search or filter criteria.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($runs as $r): ?>

                            <?php
                            $payrollCalculated = (float) $r['payroll_total'];
                            $financePosted     = (float) $r['posted_total'];
                            $bankPaid          = (float) $r['paid_total'];
                            $difference        = (float) $r['difference'];

                            $status = trim((string) $r['status']);

                            $badgeClass = $statusBadge[$status]
                                ?? 'bg-gray-100 text-gray-700 border-gray-200';

                            $differenceClass = abs($difference) < 0.0001
                                ? 'text-green-600'
                                : 'text-red-600';
                            ?>

                            <tr class="hover:bg-gray-50 transition">

                                <!-- Payroll Run -->
                                <td class="px-5 py-3 whitespace-nowrap">

                                    <div class="flex items-center gap-3">

                                        <div class="w-8 h-8 flex items-center justify-center
                                                    bg-blue-50 text-blue-600">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </div>

                                        <div>

                                            <p class="font-medium text-gray-800">
                                                Run #<?= (int) $r['payroll_run_id'] ?>
                                            </p>

                                            <p class="text-xs text-gray-500">
                                                Reconciliation #<?= (int) $r['id'] ?>
                                            </p>

                                        </div>

                                    </div>

                                </td>


                                <!-- Payroll Calculated -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">

                                    <span class="font-medium text-gray-700">
                                        AED <?= formatNumber($payrollCalculated) ?>
                                    </span>

                                </td>


                                <!-- Finance Posted -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">

                                    <span class="font-medium text-gray-700">
                                        AED <?= formatNumber($financePosted) ?>
                                    </span>

                                </td>


                                <!-- Bank Paid -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">

                                    <span class="font-medium text-gray-700">
                                        AED <?= formatNumber($bankPaid) ?>
                                    </span>

                                </td>


                                <!-- Difference -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">

                                    <span class="font-semibold <?= $differenceClass ?>">
                                        AED <?= formatNumber($difference) ?>
                                    </span>

                                </td>


                                <!-- Status -->
                                <td class="px-5 py-3 whitespace-nowrap">

                                    <span
                                        class="inline-flex items-center gap-1.5
                                               px-2.5 py-1
                                               text-xs font-medium
                                               border <?= $badgeClass ?>">

                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>

                                        <?= htmlspecialchars($status) ?>

                                    </span>

                                </td>


                                <!-- Checked At -->
                                <td class="px-5 py-3 whitespace-nowrap">

                                    <div class="flex items-center gap-2 text-gray-600">

                                        <i class="fa-regular fa-clock text-gray-400"></i>

                                        <span>
                                            <?= htmlspecialchars($r['checked_at']) ?>
                                        </span>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>