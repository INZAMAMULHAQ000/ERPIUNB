<?php

/**
 * Payroll Deductions
 * Phase F10 - Payroll & Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10.Deductions
 */

$pageTitle = 'Payroll Deductions';

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

/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND CAST(pfp.payroll_run_id AS CHAR) LIKE ?";
    $params[] = "%{$search}%";
}

/*
|--------------------------------------------------------------------------
| Fetch Deduction Totals
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT
        pfp.payroll_run_id,
        SUM(pfp.total_deductions) AS total_deductions,
        COUNT(*) AS posting_count,
        pfp.status
    FROM payroll_finance_postings pfp
    $where
    GROUP BY pfp.payroll_run_id, pfp.status
    ORDER BY total_deductions DESC
");

$stmt->execute($params);
$deductions = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/
$totalAll = 0.0;

foreach ($deductions as $d) {
    $totalAll += (float) $d['total_deductions'];
}

$runCount = count($deductions);

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/
$statusBadge = [
    'Posted' => 'bg-green-100 text-green-800 border-green-200',
    'Draft'  => 'bg-gray-100 text-gray-700 border-gray-200',
    'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'Failed' => 'bg-red-100 text-red-800 border-red-200',
];
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- =========================================================
         Page Header
    ========================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Payroll Deductions
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Deductions tracked across payroll finance postings
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

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">

                <!-- Search -->
                <div class="md:col-span-2">

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
                            placeholder="Search Payroll Run ID"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300
                                   focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                   outline-none">

                    </div>

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

                </div>

            </div>

        </form>

    </div>


    <!-- =========================================================
         Summary Cards
    ========================================================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

        <!-- Total Deductions -->
        <div class="bg-white border border-gray-200 shadow-sm">

            <div class="p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-gray-500">
                            Total Deductions
                        </p>

                        <p class="text-2xl font-bold text-blue-600 mt-1">
                            AED <?= formatNumber($totalAll) ?>
                        </p>

                    </div>

                    <div class="w-10 h-10 flex items-center justify-center
                                bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- Payroll Runs -->
        <div class="bg-white border border-gray-200 shadow-sm">

            <div class="p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-gray-500">
                            Payroll Runs with Deductions
                        </p>

                        <p class="text-2xl font-bold text-green-600 mt-1">
                            <?= $runCount ?>
                        </p>

                    </div>

                    <div class="w-10 h-10 flex items-center justify-center
                                bg-green-50 text-green-600">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
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
                Deduction Records
            </h2>

            <p class="text-xs text-gray-500 mt-1">
                <?= $runCount ?> record<?= $runCount !== 1 ? 's' : '' ?> found
            </p>

        </div>

    </div>


    <!-- =========================================================
         Table
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

                        <th class="px-5 py-3 text-left text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Posting Status
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Posting Count
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold
                                   text-gray-600 uppercase tracking-wide">
                            Total Deductions (AED)
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($deductions)): ?>

                        <tr>

                            <td colspan="4" class="px-5 py-12 text-center">

                                <div class="flex flex-col items-center justify-center">

                                    <div class="w-12 h-12 flex items-center justify-center
                                                bg-gray-100 text-gray-400 mb-3">
                                        <i class="fa-solid fa-receipt text-xl"></i>
                                    </div>

                                    <p class="text-sm font-medium text-gray-700">
                                        No deduction records found
                                    </p>

                                    <p class="text-xs text-gray-500 mt-1">
                                        Try changing the Payroll Run ID filter.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($deductions as $d): ?>

                            <?php
                            $status = trim((string) $d['status']);

                            $badgeClass = $statusBadge[$status]
                                ?? 'bg-gray-100 text-gray-700 border-gray-200';
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
                                                Run #<?= (int) $d['payroll_run_id'] ?>
                                            </p>

                                            <p class="text-xs text-gray-500">
                                                Payroll Finance Posting
                                            </p>

                                        </div>

                                    </div>

                                </td>


                                <!-- Status -->
                                <td class="px-5 py-3 whitespace-nowrap">

                                    <span
                                        class="inline-flex items-center gap-1.5
                                               px-2.5 py-1
                                               text-xs font-medium
                                               border <?= $badgeClass ?>">

                                        <span
                                            class="w-1.5 h-1.5 rounded-full bg-current"></span>

                                        <?= htmlspecialchars($status) ?>

                                    </span>

                                </td>


                                <!-- Posting Count -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">

                                    <span class="text-gray-700 font-medium">
                                        <?= (int) $d['posting_count'] ?>
                                    </span>

                                </td>


                                <!-- Total Deductions -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">

                                    <span class="font-semibold text-gray-800">
                                        AED <?= number_format(
                                                (float) $d['total_deductions'],
                                                2
                                            ) ?>
                                    </span>

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