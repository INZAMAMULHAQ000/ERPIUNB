<?php

/**
 * Payroll GL Mapping List
 * Phase F10 - Payroll Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10.Mappings
 */

$pageTitle = 'Payroll GL Mappings';

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
$search     = trim($_GET['search'] ?? '');
$mappingType = trim($_GET['mapping_type'] ?? '');
$status      = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| Fetch Mappings
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND pfm.payroll_component LIKE ?";
    $params[] = '%' . $search . '%';
}

if ($mappingType !== '') {
    $where .= " AND pfm.mapping_type = ?";
    $params[] = $mappingType;
}

if ($status !== '') {
    $where .= " AND pfm.status = ?";
    $params[] = $status;
}

$stmt = $db->prepare("
    SELECT
        pfm.id,
        pfm.payroll_component,
        pfm.chart_account_id,
        pfm.mapping_type,
        pfm.status,
        coa.account_code,
        coa.account_name
    FROM payroll_finance_mappings pfm
    LEFT JOIN chart_of_accounts coa
        ON pfm.chart_account_id = coa.id
    $where
    ORDER BY pfm.payroll_component ASC
");

$stmt->execute($params);
$mappings = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Mapping Type Options
|--------------------------------------------------------------------------
*/
$typeStmt = $db->prepare("
    SELECT DISTINCT mapping_type
    FROM payroll_finance_mappings
    WHERE mapping_type IS NOT NULL
      AND mapping_type != ''
    ORDER BY mapping_type
");

$typeStmt->execute();
$mappingTypes = $typeStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status Options
|--------------------------------------------------------------------------
*/
$statusStmt = $db->prepare("
    SELECT DISTINCT status
    FROM payroll_finance_mappings
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
                Payroll GL Mappings
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage payroll component to General Ledger account mappings.
            </p>
        </div>

        <?php if (hasPermission('payroll_finance.create')): ?>

            <a
                href="add.php"
                class="inline-flex items-center justify-center gap-2
                       px-4 py-2
                       bg-blue-600 text-white
                       text-sm font-medium
                       border border-blue-600
                       hover:bg-blue-700
                       transition">
                <i class="fa-solid fa-plus"></i>
                Add New Mapping
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">

                <!-- Search -->
                <div>

                    <label
                        for="search"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Payroll Component
                    </label>

                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search component..."
                        class="w-full px-3 py-2
                               border border-gray-300
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                </div>


                <!-- Mapping Type -->
                <div>

                    <label
                        for="mapping_type"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Mapping Type
                    </label>

                    <select
                        name="mapping_type"
                        id="mapping_type"
                        class="w-full px-3 py-2
                               border border-gray-300
                               bg-white
                               text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:border-blue-500">

                        <option value="">
                            All Types
                        </option>

                        <?php foreach ($mappingTypes as $type): ?>

                            <option
                                value="<?= htmlspecialchars($type['mapping_type']) ?>"
                                <?= $mappingType === $type['mapping_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($type['mapping_type'])) ?>
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
         SUMMARY
    ========================================================== -->
    <div class="flex items-center justify-between mb-3">

        <div>

            <h2 class="text-lg font-semibold text-gray-800">
                GL Mappings
            </h2>

            <p class="text-sm text-gray-500">
                <?= count($mappings) ?> mapping(s) found
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
                            Payroll Component
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            GL Account
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Mapping Type
                        </th>

                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            Status
                        </th>

                        <th class="px-4 py-3 text-center font-semibold text-gray-700">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($mappings)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="px-4 py-10 text-center text-gray-500">

                                <div class="flex flex-col items-center">

                                    <i class="fa-solid fa-link-slash text-3xl text-gray-300 mb-3"></i>

                                    <p class="font-medium text-gray-600">
                                        No mappings found
                                    </p>

                                    <p class="text-sm text-gray-400 mt-1">
                                        Try changing the search or filter criteria.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($mappings as $mapping): ?>

                            <tr class="hover:bg-gray-50 transition">

                                <!-- Payroll Component -->
                                <td class="px-4 py-3">

                                    <div class="font-medium text-gray-800">
                                        <?= htmlspecialchars($mapping['payroll_component']) ?>
                                    </div>

                                </td>


                                <!-- GL Account -->
                                <td class="px-4 py-3">

                                    <?php if (!empty($mapping['account_code'])): ?>

                                        <div class="font-medium text-gray-800">
                                            <?= htmlspecialchars($mapping['account_code']) ?>
                                        </div>

                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <?= htmlspecialchars($mapping['account_name']) ?>
                                        </div>

                                    <?php else: ?>

                                        <span class="text-gray-500">
                                            Account #<?= (int)$mapping['chart_account_id'] ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Mapping Type -->
                                <td class="px-4 py-3">

                                    <?php if ($mapping['mapping_type'] === 'expense'): ?>

                                        <span
                                            class="inline-flex items-center
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-blue-100
                                                   text-blue-800
                                                   border border-blue-200">
                                            Expense
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="inline-flex items-center
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-yellow-100
                                                   text-yellow-800
                                                   border border-yellow-200">
                                            <?= htmlspecialchars(ucfirst($mapping['mapping_type'])) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Status -->
                                <td class="px-4 py-3">

                                    <?php if (strtolower($mapping['status']) === 'active'): ?>

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   text-xs font-medium
                                                   bg-green-100
                                                   text-green-800
                                                   border border-green-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                            Active
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
                                            <?= htmlspecialchars($mapping['status']) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Actions -->
                                <td class="px-4 py-3">

                                    <div class="flex justify-center items-center gap-2">

                                        <?php if (hasPermission('payroll_finance.view')): ?>

                                            <a
                                                href="view.php?id=<?= (int)$mapping['id'] ?>"
                                                title="View"
                                                class="inline-flex items-center justify-center
                                                       w-8 h-8
                                                       border border-blue-200
                                                       text-blue-600
                                                       hover:bg-blue-50
                                                       transition">
                                                <i class="fa-solid fa-eye text-xs"></i>
                                            </a>

                                        <?php endif; ?>


                                        <?php if (hasPermission('payroll_finance.edit')): ?>

                                            <a
                                                href="edit.php?id=<?= (int)$mapping['id'] ?>"
                                                title="Edit"
                                                class="inline-flex items-center justify-center
                                                       w-8 h-8
                                                       border border-gray-300
                                                       text-gray-600
                                                       hover:bg-gray-50
                                                       transition">
                                                <i class="fa-solid fa-pen text-xs"></i>
                                            </a>


                                            <button
                                                type="button"
                                                title="Delete"
                                                onclick="if (confirm('Delete this mapping?')) { window.location.href='process.php?action=delete&id=<?= (int)$mapping['id'] ?>'; }"
                                                class="inline-flex items-center justify-center
                                                       w-8 h-8
                                                       border border-red-200
                                                       text-red-600
                                                       hover:bg-red-50
                                                       transition">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>

                                        <?php endif; ?>

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