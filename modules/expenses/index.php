<?php
$pageTitle = 'Expenses';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('expenses.view')) {
    die('Access Denied');
}

$db = getDB();

$search     = trim($_GET['search'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$status     = trim($_GET['status'] ?? '');

$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

/*
|--------------------------------------------------------------------------
| Allowed statuses
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'pending',
    'approved',
    'rejected'
];

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/
$categories = $db->query("
    SELECT *
    FROM expense_categories
    WHERE status = 'active'
    ORDER BY category_name
")->fetchAll();

$categoryIds = array_map(
    'strval',
    array_column($categories, 'id')
);

/*
|--------------------------------------------------------------------------
| Search validation
|--------------------------------------------------------------------------
*/
if ($search !== '') {

    if (mb_strlen($search) > 100) {
        $search = mb_substr($search, 0, 100);
    }

    $where .= "
        AND (
            e.expense_code LIKE ?
            OR e.vendor_name LIKE ?
        )
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

/*
|--------------------------------------------------------------------------
| Category filter
|--------------------------------------------------------------------------
*/
if ($category_id !== '') {

    if (ctype_digit($category_id) && in_array($category_id, $categoryIds, true)) {

        $where .= " AND e.category_id = ?";
        $params[] = (int)$category_id;
    } else {

        $category_id = '';
    }
}

/*
|--------------------------------------------------------------------------
| Status filter
|--------------------------------------------------------------------------
*/
if ($status !== '') {

    if (in_array($status, $allowedStatuses, true)) {

        $where .= " AND e.status = ?";
        $params[] = $status;
    } else {

        $status = '';
    }
}

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM expenses e
    $where
");

$countStmt->execute($params);

$total = $countStmt->fetch()['cnt'];

$pagination = paginate(
    $total,
    $perPage,
    $page
);

/*
|--------------------------------------------------------------------------
| Expenses
|--------------------------------------------------------------------------
*/
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT
        e.*,
        c.category_name,
        u.name AS created_by_name
    FROM expenses e
    LEFT JOIN expense_categories c
        ON e.category_id = c.id
    LEFT JOIN users u
        ON e.created_by = u.id
    $where
    ORDER BY
        e.expense_date DESC,
        e.id DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$expenses = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status / Method colors
|--------------------------------------------------------------------------
*/
$statusColors = [
    'pending'  => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800'
];

$methodColors = [
    'cash'         => 'bg-gray-100 text-gray-800',
    'bank_transfer' => 'bg-blue-100 text-blue-800',
    'upi'          => 'bg-purple-100 text-purple-800',
    'credit_card'  => 'bg-yellow-100 text-yellow-800'
];

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/
$totalExpenses = $db->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE status = 'approved'
")->fetch()['total'];

$thisMonthExp = $db->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE status = 'approved'
      AND MONTH(expense_date) = MONTH(CURRENT_DATE)
      AND YEAR(expense_date) = YEAR(CURRENT_DATE)
")->fetch()['total'];

$pendingCount = $db->query("
    SELECT COUNT(*) AS c
    FROM expenses
    WHERE status = 'pending'
")->fetch()['c'];

$pendingAmount = $db->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE status = 'pending'
")->fetch()['total'];
?>

<style>
    /* =========================================================
   Expense Filter Validation
   ========================================================= */

    #expenseFilterForm label.error {
        display: block !important;
        width: 100%;
        clear: both;
        margin-top: 5px !important;
        margin-bottom: 0 !important;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
        font-weight: 400;
    }

    #expenseFilterForm input.error,
    #expenseFilterForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #expenseFilterForm input.valid,
    #expenseFilterForm select.valid {
        border-color: #d1d5db !important;
        box-shadow: none !important;
    }

    /* Select arrow */
    #expenseFilterForm select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: none !important;
    }

    /* Keep validation message below each field */
    .filter-field {
        min-height: 72px;
    }
</style>


<!-- =========================================================
     Header
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Expenses
        </h1>

        <p class="text-gray-600">
            Track business expenses
        </p>

    </div>

    <?php if (hasPermission('expenses.create')): ?>

        <a
            href="<?= url('modules/expenses/create.php') ?>"
            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">

            <i class="fas fa-plus mr-2"></i>
            Add Expense

        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     Summary Cards
========================================================= -->

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Total Expenses
        </div>

        <div class="text-2xl font-bold text-red-600">
            <?= formatMoney($totalExpenses) ?>
        </div>

    </div>


    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            This Month
        </div>

        <div class="text-2xl font-bold text-gray-800">
            <?= formatMoney($thisMonthExp) ?>
        </div>

    </div>


    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Pending Approval
        </div>

        <div class="text-2xl font-bold text-yellow-600">
            <?= (int)$pendingCount ?>
        </div>

    </div>


    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Pending Amount
        </div>

        <div class="text-2xl font-bold text-yellow-600">
            <?= formatMoney($pendingAmount) ?>
        </div>

    </div>

</div>


<!-- =========================================================
     Filters
========================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="expenseFilterForm"
        class="p-4 flex flex-wrap gap-4 items-start"
        novalidate>

        <!-- Search -->
        <div class="flex-1 min-w-[200px] filter-field">

            <label
                for="expenseSearch"
                class="block text-sm font-medium text-gray-700 mb-1">

                Search

            </label>

            <input
                type="text"
                name="search"
                id="expenseSearch"
                value="<?= sanitize($search) ?>"
                maxlength="100"
                placeholder="Expense code or vendor..."
                class="w-full h-10 px-3 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">

        </div>


        <!-- Category -->
        <div class="w-48 filter-field">

            <label
                for="category_id"
                class="block text-sm font-medium text-gray-700 mb-1">

                Category

            </label>

            <div class="relative">

                <select
                    name="category_id"
                    id="category_id"
                    class="w-full h-10 px-3 pr-9 border border-gray-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">

                    <option value="">
                        All
                    </option>

                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?= (int)$cat['id'] ?>"
                            <?= (string)$category_id === (string)$cat['id'] ? 'selected' : '' ?>>

                            <?= sanitize($cat['category_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">

                    <i class="fas fa-chevron-down text-xs"></i>

                </span>

            </div>

        </div>


        <!-- Status -->
        <div class="w-40 filter-field">

            <label
                for="status"
                class="block text-sm font-medium text-gray-700 mb-1">

                Status

            </label>

            <div class="relative">

                <select
                    name="status"
                    id="status"
                    class="w-full h-10 px-3 pr-9 border border-gray-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">

                    <option value="">
                        All
                    </option>

                    <option
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>>
                        Pending
                    </option>

                    <option
                        value="approved"
                        <?= $status === 'approved' ? 'selected' : '' ?>>
                        Approved
                    </option>

                    <option
                        value="rejected"
                        <?= $status === 'rejected' ? 'selected' : '' ?>>
                        Rejected
                    </option>

                </select>

                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">

                    <i class="fas fa-chevron-down text-xs"></i>

                </span>

            </div>

        </div>


        <!-- Search button -->
        <div class="pt-6">

            <button
                type="submit"
                class="h-10 bg-gray-600 text-white px-4 rounded hover:bg-gray-700">

                <i class="fas fa-search"></i>

            </button>

        </div>


        <!-- Clear -->
        <?php if ($search !== '' || $category_id !== '' || $status !== ''): ?>

            <div class="pt-6">

                <a
                    href="<?= url('modules/expenses/index.php') ?>"
                    class="inline-flex items-center h-10 px-4 border border-gray-300 text-gray-600 rounded hover:bg-gray-50">

                    <i class="fas fa-times mr-1"></i>
                    Clear

                </a>

            </div>

        <?php endif; ?>

    </form>

</div>


<!-- =========================================================
     Expense Table
========================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">

            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Code
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Category
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Date
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Vendor
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Amount
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Method
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody class="divide-y divide-gray-200">

            <?php foreach ($expenses as $exp): ?>

                <tr class="hover:bg-gray-50">

                    <td class="px-6 py-4 font-medium text-gray-900">
                        <?= sanitize($exp['expense_code']) ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($exp['category_name'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= date('M d, Y', strtotime($exp['expense_date'])) ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($exp['vendor_name'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4 text-right font-medium">
                        <?= formatMoney($exp['amount']) ?>
                    </td>

                    <td class="px-6 py-4">

                        <span class="px-2 py-1 text-xs rounded <?= $methodColors[$exp['payment_method']] ?? 'bg-gray-100 text-gray-800' ?>">

                            <?= ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $exp['payment_method']
                                )
                            ) ?>

                        </span>

                    </td>

                    <td class="px-6 py-4">

                        <span class="px-2 py-1 text-xs rounded <?= $statusColors[$exp['status']] ?? 'bg-gray-100 text-gray-800' ?>">

                            <?= ucfirst($exp['status']) ?>

                        </span>

                    </td>

                    <td class="px-6 py-4 text-right">

                        <?php if (hasPermission('expenses.edit')): ?>

                            <a
                                href="<?= url('modules/expenses/edit.php?id=' . (int)$exp['id']) ?>"
                                class="text-yellow-600 hover:text-yellow-900 mr-2"
                                title="Edit">

                                <i class="fas fa-edit"></i>

                            </a>

                        <?php endif; ?>


                        <?php if (
                            hasPermission('expenses.approve') &&
                            $exp['status'] === 'pending'
                        ): ?>

                            <a
                                href="<?= url('modules/expenses/approve.php?id=' . (int)$exp['id'] . '&action=approve') ?>"
                                class="text-green-600 hover:text-green-900 mr-2"
                                title="Approve">

                                <i class="fas fa-check"></i>

                            </a>

                            <a
                                href="<?= url('modules/expenses/approve.php?id=' . (int)$exp['id'] . '&action=reject') ?>"
                                class="text-red-600 hover:text-red-900"
                                title="Reject">

                                <i class="fas fa-times"></i>

                            </a>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (empty($expenses)): ?>

                <tr>

                    <td
                        colspan="8"
                        class="px-6 py-8 text-center text-gray-500">

                        No expenses found

                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<?= paginationLinks($pagination, '?') ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Custom validation methods
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validSearch',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return /^[A-Za-z0-9\s#\-_\/.&()]+$/.test(value);

            },
            'Please enter a valid search value.'
        );


        $.validator.addMethod(
            'validCategory',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return /^\d+$/.test(value);

            },
            'Please select a valid category.'
        );


        $.validator.addMethod(
            'validStatus',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return [
                    'pending',
                    'approved',
                    'rejected'
                ].includes(value);

            },
            'Please select a valid status.'
        );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $('#expenseFilterForm').validate({

            ignore: [],

            rules: {

                search: {
                    maxlength: 100,
                    validSearch: true
                },

                category_id: {
                    validCategory: true
                },

                status: {
                    validStatus: true
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 100 characters.',
                    validSearch: 'Please enter a valid search value.'
                },

                category_id: {
                    validCategory: 'Please select a valid category.'
                },

                status: {
                    validStatus: 'Please select a valid status.'
                }

            },

            errorElement: 'label',

            errorClass: 'error',

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            | Select validation message BELOW select box
            |--------------------------------------------------------------------------
            */

            errorPlacement: function(error, element) {

                error.css({
                    display: 'block',
                    width: '100%',
                    clear: 'both',
                    marginTop: '5px'
                });

                if (element.is('select')) {

                    const wrapper = element.closest('.relative');

                    if (wrapper.length) {

                        error.insertAfter(wrapper);

                    } else {

                        error.insertAfter(element);

                    }

                } else {

                    error.insertAfter(element);

                }
            },

            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },

            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },

            success: function(label) {

                label.remove();

            },

            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>