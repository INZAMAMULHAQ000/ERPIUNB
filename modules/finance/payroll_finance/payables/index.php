<?php

/**
 * Payroll Payables
 * Phase F10 - Payroll & Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10.Payables
 */

$pageTitle = 'Payroll Payables';

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
$status = trim($_GET['status'] ?? '');
$payrollRunId = (int)($_GET['payroll_run_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Fetch Payables
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= "
        AND (
            CAST(ppb.payroll_run_id AS CHAR) LIKE ?
            OR ppb.reference_number LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($status !== '') {
    $where .= " AND ppb.status = ?";
    $params[] = $status;
}

if ($payrollRunId > 0) {
    $where .= " AND ppb.payroll_run_id = ?";
    $params[] = $payrollRunId;
}

$stmt = $db->prepare("
    SELECT
        ppb.id,
        ppb.payroll_run_id,
        ppb.payment_date,
        ppb.total_amount,
        ppb.reference_number,
        ppb.status,
        ba.account_name AS bank_account
    FROM payroll_payment_batches ppb
    LEFT JOIN bank_accounts ba
        ON ppb.bank_account_id = ba.id
    $where
    ORDER BY ppb.payment_date ASC
");

$stmt->execute($params);
$payables = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status Options
|--------------------------------------------------------------------------
*/
$statusStmt = $db->prepare("
    SELECT DISTINCT status
    FROM payroll_payment_batches
    WHERE status IS NOT NULL
      AND status != ''
    ORDER BY status
");

$statusStmt->execute();
$statuses = $statusStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Payroll Run Options
|--------------------------------------------------------------------------
*/
$runStmt = $db->prepare("
    SELECT DISTINCT payroll_run_id
    FROM payroll_payment_batches
    WHERE payroll_run_id IS NOT NULL
    ORDER BY payroll_run_id DESC
");

$runStmt->execute();
$payrollRuns = $runStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/
$outstanding = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM payroll_payment_batches
    WHERE status NOT IN ('Paid', 'Failed')
")->fetchColumn();

$thisMonth = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM payroll_payment_batches
    WHERE MONTH(payment_date) = MONTH(CURDATE())
      AND YEAR(payment_date) = YEAR(CURDATE())
")->fetchColumn();

?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Payroll Payables
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage outstanding employee salary payments and payroll payment batches.
            </p>

        </div>

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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">

                <!-- Search -->
                <div>

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
                        placeholder="Run ID or reference..."
                        class="w-full px-3 py-2
                               border border-gray-300
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                </div>


                <!-- Payroll Run -->
                <div>

                    <label
                        for="payroll_run_id"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Payroll Run
                    </label>

                    <select
                        name="payroll_run_id"
                        id="payroll_run_id"
                        class="w-full px-3 py-2
                               border border-gray-300
                               bg-white
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                        <option value="">
                            All Payroll Runs
                        </option>

                        <?php foreach ($payrollRuns as $run): ?>

                            <option
                                value="<?= (int)$run['payroll_run_id'] ?>"
                                <?= $payrollRunId == $run['payroll_run_id'] ? 'selected' : '' ?>>
                                Run #<?= (int)$run['payroll_run_id'] ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

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

                        <?php foreach ($statuses as $s): ?>

                            <option
                                value="<?= htmlspecialchars($s['status']) ?>"
                                <?= $status === $s['status'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['status']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

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

                </div>

            </div>

        </form>

    </div>


    <!-- =========================================================
         SUMMARY CARDS
    ========================================================== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        <!-- Outstanding -->
        <div class="bg-white border border-gray-200 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>

                    <p class="text-sm text-gray-500">
                        Total Outstanding
                    </p>

                    <p class="text-2xl font-bold text-red-600 mt-1">
                        AED <?= formatNumber($outstanding) ?>
                    </p>

                </div>

                <div
                    class="w-10 h-10
                           flex items-center justify-center
                           bg-red-50
                           text-red-600">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                </div>

            </div>

        </div>


        <!-- This Month -->
        <div class="bg-white border border-gray-200 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>

                    <p class="text-sm text-gray-500">
                        This Month
                    </p>

                    <p class="text-2xl font-bold text-green-600 mt-1">
                        AED <?= formatNumber($thisMonth) ?>
                    </p>

                </div>

                <div
                    class="w-10 h-10
                           flex items-center justify-center
                           bg-green-50
                           text-green-600">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

            </div>

        </div>


        <!-- Payment Batches -->
        <div class="bg-white border border-gray-200 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>

                    <p class="text-sm text-gray-500">
                        Payment Batches
                    </p>

                    <p class="text-2xl font-bold text-gray-800 mt-1">
                        <?= count($payables) ?>
                    </p>

                </div>

                <div
                    class="w-10 h-10
                           flex items-center justify-center
                           bg-blue-50
                           text-blue-600">
                    <i class="fa-solid fa-layer-group"></i>
                </div>

            </div>

        </div>

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
                <?= count($payables) ?> payment batch(s) found
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
                            Batch ID
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
                            Amount (AED)
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Reference
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($payables)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-gray-500">

                                <div class="flex flex-col items-center">

                                    <i class="fa-solid fa-money-bill-wave text-3xl text-gray-300 mb-3"></i>

                                    <p class="font-medium text-gray-600">
                                        No payables found
                                    </p>

                                    <p class="text-sm text-gray-400 mt-1">
                                        Try changing the search or filter criteria.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($payables as $p): ?>

                            <?php
                            $currentStatus = strtolower(trim($p['status'] ?? ''));
                            ?>

                            <tr class="hover:bg-gray-50 transition">

                                <!-- Batch ID -->
                                <td class="px-4 py-3">

                                    <div class="font-medium text-gray-800">
                                        #<?= (int)$p['id'] ?>
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
                                        Run #<?= (int)$p['payroll_run_id'] ?>
                                    </span>

                                </td>


                                <!-- Bank Account -->
                                <td class="px-4 py-3">

                                    <?php if (!empty($p['bank_account'])): ?>

                                        <div class="flex items-center gap-2">

                                            <i class="fa-solid fa-building-columns text-gray-400"></i>

                                            <span class="text-gray-700">
                                                <?= htmlspecialchars($p['bank_account']) ?>
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
                                        <?= htmlspecialchars($p['payment_date']) ?>
                                    </div>

                                </td>


                                <!-- Amount -->
                                <td class="px-4 py-3 text-right">

                                    <span class="font-semibold text-gray-900">
                                        AED <?= number_format((float)$p['total_amount'], 2) ?>
                                    </span>

                                </td>


                                <!-- Reference -->
                                <td class="px-4 py-3">

                                    <?php if (!empty($p['reference_number'])): ?>

                                        <span class="text-gray-700">
                                            <?= htmlspecialchars($p['reference_number']) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Status -->
                                <td class="px-4 py-3">

                                    <?php if ($currentStatus === 'paid'): ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-green-100
                                                   text-green-800
                                                   border border-green-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                            Paid
                                        </span>

                                    <?php elseif ($currentStatus === 'failed'): ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-red-100
                                                   text-red-800
                                                   border border-red-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                                            Failed
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-yellow-100
                                                   text-yellow-800
                                                   border border-yellow-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span>

                                            <?= htmlspecialchars($p['status']) ?>

                                        </span>

                                    <?php endif; ?>

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