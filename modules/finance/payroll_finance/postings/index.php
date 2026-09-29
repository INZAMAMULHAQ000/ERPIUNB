<?php

/**
 * Payroll Finance Postings
 * Phase F10 - Payroll & Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10.Postings
 */

$pageTitle = 'Payroll Finance Postings';

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.post')) {
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

/*
|--------------------------------------------------------------------------
| Fetch Postings
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND CAST(pfp.payroll_run_id AS CHAR) LIKE ?";
    $params[] = '%' . $search . '%';
}

if ($status !== '') {
    $where .= " AND pfp.status = ?";
    $params[] = $status;
}

$stmt = $db->prepare("
    SELECT
        pfp.id,
        pfp.payroll_run_id,
        pfp.posting_date,
        pfp.total_gross,
        pfp.total_deductions,
        pfp.total_net,
        pfp.status,
        pfp.journal_entry_id,
        prr.payroll_total AS payroll_calculated
    FROM payroll_finance_postings pfp
    LEFT JOIN payroll_reconciliation prr
        ON pfp.payroll_run_id = prr.payroll_run_id
    $where
    ORDER BY pfp.posting_date DESC
");

$stmt->execute($params);
$postings = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status Options
|--------------------------------------------------------------------------
*/
$statusStmt = $db->prepare("
    SELECT DISTINCT status
    FROM payroll_finance_postings
    WHERE status IS NOT NULL
      AND status != ''
    ORDER BY status
");

$statusStmt->execute();
$statuses = $statusStmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Payroll Finance Postings
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Review payroll postings and their corresponding finance journal entries.
            </p>

        </div>

        <?php if (hasPermission('payroll_finance.post')): ?>

            <a
                href="create.php"
                class="inline-flex items-center justify-center gap-2
                       px-4 py-2
                       bg-blue-600 text-white
                       text-sm font-medium
                       border border-blue-600
                       hover:bg-blue-700
                       transition">
                <i class="fa-solid fa-plus"></i>
                Post New Payroll
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">

                <!-- Search -->
                <div>

                    <label
                        for="search"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Payroll Run ID
                    </label>

                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search payroll run ID..."
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
         SUMMARY
    ========================================================== -->
    <div class="flex items-center justify-between mb-3">

        <div>

            <h2 class="text-lg font-semibold text-gray-800">
                Payroll Postings
            </h2>

            <p class="text-sm text-gray-500">
                <?= count($postings) ?> posting(s) found
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
                            Payroll Run
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Posting Date
                        </th>

                        <th class="px-4 py-3 text-right font-semibold text-gray-700">
                            Total Gross
                        </th>

                        <th class="px-4 py-3 text-right font-semibold text-gray-700">
                            Total Deductions
                        </th>

                        <th class="px-4 py-3 text-right font-semibold text-gray-700">
                            Total Net
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Journal Entry
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($postings)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-gray-500">

                                <div class="flex flex-col items-center">

                                    <i class="fa-solid fa-file-invoice-dollar text-3xl text-gray-300 mb-3"></i>

                                    <p class="font-medium text-gray-600">
                                        No payroll postings found
                                    </p>

                                    <p class="text-sm text-gray-400 mt-1">
                                        Try changing the search or filter criteria.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($postings as $p): ?>

                            <tr class="hover:bg-gray-50 transition">

                                <!-- Payroll Run -->
                                <td class="px-4 py-3">

                                    <div class="font-medium text-gray-800">
                                        #<?= (int)$p['payroll_run_id'] ?>
                                    </div>

                                    <div class="text-xs text-gray-500 mt-0.5">
                                        Payroll Run
                                    </div>

                                </td>


                                <!-- Posting Date -->
                                <td class="px-4 py-3">

                                    <div class="text-gray-700">
                                        <?= htmlspecialchars($p['posting_date']) ?>
                                    </div>

                                </td>


                                <!-- Total Gross -->
                                <td class="px-4 py-3 text-right">

                                    <span class="font-medium text-gray-800">
                                        AED <?= number_format((float)$p['total_gross'], 2) ?>
                                    </span>

                                </td>


                                <!-- Total Deductions -->
                                <td class="px-4 py-3 text-right">

                                    <span class="font-medium text-gray-800">
                                        AED <?= number_format((float)$p['total_deductions'], 2) ?>
                                    </span>

                                </td>


                                <!-- Total Net -->
                                <td class="px-4 py-3 text-right">

                                    <span class="font-semibold text-gray-900">
                                        AED <?= number_format((float)$p['total_net'], 2) ?>
                                    </span>

                                </td>


                                <!-- Journal Entry -->
                                <td class="px-4 py-3">

                                    <?php if (!empty($p['journal_entry_id'])): ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-blue-100
                                                   text-blue-800
                                                   border border-blue-200">
                                            <i class="fa-solid fa-book-open text-xs"></i>

                                            JE #<?= (int)$p['journal_entry_id'] ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Status -->
                                <td class="px-4 py-3">

                                    <?php
                                    $currentStatus = strtolower(trim($p['status'] ?? ''));
                                    ?>

                                    <?php if ($currentStatus === 'posted'): ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-green-100
                                                   text-green-800
                                                   border border-green-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                            Posted
                                        </span>

                                    <?php elseif ($currentStatus === 'draft'): ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-yellow-100
                                                   text-yellow-800
                                                   border border-yellow-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span>
                                            Draft
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-red-100
                                                   text-red-800
                                                   border border-red-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>

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