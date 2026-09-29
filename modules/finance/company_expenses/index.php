<?php

$pageTitle = 'Company Expenses';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('company_expenses.view')) {
    die('Access Denied');
}

$db = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$page = getPageNumber();
$perPage = 15;


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$where = "WHERE 1=1";
$params = [];

if ($search !== '') {

    $where .= " AND (
        ce.expense_number LIKE ?
        OR ce.description LIKE ?
    )";

    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {

    $where .= " AND ce.status = ?";
    $params[] = $status;
}

if ($category_id !== '') {

    $where .= " AND ce.category_id = ?";
    $params[] = (int)$category_id;
}

if ($department_id !== '') {

    $where .= " AND ce.department_id = ?";
    $params[] = (int)$department_id;
}

if ($date_from !== '') {

    $where .= " AND ce.expense_date >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {

    $where .= " AND ce.expense_date <= ?";
    $params[] = $date_to;
}


/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM company_expenses ce
    $where
");

$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

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
    [
        $perPage,
        $pagination['offset']
    ]
);

$stmt = $db->prepare("
    SELECT
        ce.*,
        ec.name AS category_name,
        d.department_name,
        p.project_name,
        u.name AS created_by_name,
        je.voucher_no

    FROM company_expenses ce

    LEFT JOIN expense_categories ec
        ON ce.category_id = ec.id

    LEFT JOIN departments d
        ON ce.department_id = d.id

    LEFT JOIN projects p
        ON ce.project_id = p.id

    LEFT JOIN users u
        ON ce.created_by = u.id

    LEFT JOIN journal_entries je
        ON ce.journal_entry_id = je.id

    $where

    ORDER BY ce.created_at DESC

    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$expenses = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Filter Dropdowns
|--------------------------------------------------------------------------
*/

$categories = $db->query("
    SELECT
        id,
        name
    FROM expense_categories
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();


$departments = $db->query("
    SELECT
        id,
        department_name
    FROM departments
    WHERE status = 'active'
    ORDER BY department_name
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$todayTotal = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM company_expenses
    WHERE DATE(expense_date) = CURDATE()
      AND status NOT IN ('cancelled')
")->fetchColumn();


$monthTotal = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM company_expenses
    WHERE MONTH(expense_date) = MONTH(CURDATE())
      AND YEAR(expense_date) = YEAR(CURDATE())
      AND status NOT IN ('cancelled')
")->fetchColumn();


$pendingCount = $db->query("
    SELECT COUNT(*)
    FROM company_expenses
    WHERE status = 'pending_approval'
")->fetchColumn();


$approvedTotal = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM company_expenses
    WHERE status = 'approved'
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| Status Badges
|--------------------------------------------------------------------------
*/

$statusBadges = [

    'draft'
    => 'bg-gray-100 text-gray-800',

    'pending_approval'
    => 'bg-yellow-100 text-yellow-800',

    'approved'
    => 'bg-blue-100 text-blue-800',

    'rejected'
    => 'bg-red-100 text-red-800',

    'paid'
    => 'bg-green-100 text-green-800',

    'cancelled'
    => 'bg-gray-200 text-gray-600',

];

?>

<!--
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
-->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Company Expenses
        </h1>

        <p class="text-gray-600">
            Record and manage company-paid expenses
        </p>

    </div>


    <div class="flex gap-2">

        <a
            href="<?= url('modules/finance/index.php') ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

            <i class="fas fa-arrow-left mr-2"></i>

            Back to Finance

        </a>


        <?php if (hasPermission('company_expenses.create')): ?>

            <a
                href="create.php"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">

                <i class="fas fa-plus mr-2"></i>

                New Expense

            </a>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
-->

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">


    <!-- Today -->

    <div class="bg-white rounded-lg shadow p-4">

        <div class="flex items-center justify-between">

            <div>

                <p class="text-sm text-gray-500">
                    Today
                </p>

                <p class="text-xl font-bold text-gray-800">
                    <?= formatMoney((float)$todayTotal) ?>
                </p>

            </div>

            <div class="bg-blue-100 p-3 rounded-full">

                <i class="fas fa-calendar-day text-blue-600"></i>

            </div>

        </div>

    </div>


    <!-- This Month -->

    <div class="bg-white rounded-lg shadow p-4">

        <div class="flex items-center justify-between">

            <div>

                <p class="text-sm text-gray-500">
                    This Month
                </p>

                <p class="text-xl font-bold text-gray-800">
                    <?= formatMoney((float)$monthTotal) ?>
                </p>

            </div>

            <div class="bg-indigo-100 p-3 rounded-full">

                <i class="fas fa-calendar-alt text-indigo-600"></i>

            </div>

        </div>

    </div>


    <!-- Pending -->

    <div class="bg-white rounded-lg shadow p-4">

        <div class="flex items-center justify-between">

            <div>

                <p class="text-sm text-gray-500">
                    Pending Approval
                </p>

                <p class="text-xl font-bold text-yellow-600">
                    <?= (int)$pendingCount ?>
                </p>

            </div>

            <div class="bg-yellow-100 p-3 rounded-full">

                <i class="fas fa-clock text-yellow-600"></i>

            </div>

        </div>

    </div>


    <!-- Approved -->

    <div class="bg-white rounded-lg shadow p-4">

        <div class="flex items-center justify-between">

            <div>

                <p class="text-sm text-gray-500">
                    Approved Total
                </p>

                <p class="text-xl font-bold text-green-600">
                    <?= formatMoney((float)$approvedTotal) ?>
                </p>

            </div>

            <div class="bg-green-100 p-3 rounded-full">

                <i class="fas fa-check-circle text-green-600"></i>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
-->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        class="p-4 flex flex-wrap gap-4 items-end">


        <!-- Search -->

        <div class="flex-1 min-w-[180px]">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Search

            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Expense # or description..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Status -->

        <div class="w-44">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Status

            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All
                </option>

                <option
                    value="draft"
                    <?= $status === 'draft' ? 'selected' : '' ?>>

                    Draft

                </option>

                <option
                    value="pending_approval"
                    <?= $status === 'pending_approval' ? 'selected' : '' ?>>

                    Pending Approval

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

                <option
                    value="paid"
                    <?= $status === 'paid' ? 'selected' : '' ?>>

                    Paid

                </option>

                <option
                    value="cancelled"
                    <?= $status === 'cancelled' ? 'selected' : '' ?>>

                    Cancelled

                </option>

            </select>

        </div>


        <!-- Category -->

        <div class="w-44">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Category

            </label>

            <select
                name="category_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All
                </option>

                <?php foreach ($categories as $c): ?>

                    <option
                        value="<?= (int)$c['id'] ?>"
                        <?= $category_id == $c['id'] ? 'selected' : '' ?>>

                        <?= sanitize($c['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Department -->

        <div class="w-44">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Department

            </label>

            <select
                name="department_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All
                </option>

                <?php foreach ($departments as $d): ?>

                    <option
                        value="<?= (int)$d['id'] ?>"
                        <?= $department_id == $d['id'] ? 'selected' : '' ?>>

                        <?= sanitize($d['department_name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Date From -->

        <div class="w-40">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Date From

            </label>

            <input
                type="date"
                id="date_from"
                name="date_from"
                value="<?= sanitize($date_from) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Date To -->

        <div class="w-40">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Date To

            </label>

            <input
                type="date"
                id="date_to"
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


        <!-- Clear -->

        <?php
        if (
            $search ||
            $status !== '' ||
            $category_id ||
            $department_id ||
            $date_from ||
            $date_to
        ):
        ?>

            <a
                href="index.php"
                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">

                <i class="fas fa-times"></i>

            </a>

        <?php endif; ?>

    </form>

</div>


<!--
|--------------------------------------------------------------------------
| Expenses Table
|--------------------------------------------------------------------------
-->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Expense #
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Date
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Category
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Department
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Description
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


                <?php foreach ($expenses as $e): ?>

                    <tr class="hover:bg-gray-50">


                        <!-- Expense Number -->

                        <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900">

                            <?= sanitize($e['expense_number']) ?>

                        </td>


                        <!-- Date -->

                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">

                            <?= date(
                                'M d, Y',
                                strtotime($e['expense_date'])
                            ) ?>

                        </td>


                        <!-- Category -->

                        <td class="px-4 py-3 text-sm text-gray-600">

                            <?= sanitize(
                                $e['category_name'] ?? '--'
                            ) ?>

                        </td>


                        <!-- Department -->

                        <td class="px-4 py-3 text-sm text-gray-600">

                            <?= sanitize(
                                $e['department_name'] ?? '--'
                            ) ?>

                        </td>


                        <!-- Description -->

                        <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">

                            <?= sanitize(
                                $e['description'] ?? '--'
                            ) ?>

                        </td>


                        <!-- Status -->

                        <td class="px-4 py-3 text-center whitespace-nowrap">

                            <span
                                class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$e['status']]
                                                                                    ?? 'bg-gray-100 text-gray-800' ?>">

                                <?= ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $e['status']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- Total -->

                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">

                            <?= formatMoney(
                                (float)$e['total_amount']
                            ) ?>

                        </td>


                        <!-- Actions -->

                        <td class="px-4 py-3 text-right whitespace-nowrap">


                            <!-- View -->

                            <a
                                href="view.php?id=<?= (int)$e['id'] ?>"
                                class="text-blue-600 hover:text-blue-900 mr-3"
                                title="View">

                                <i class="fas fa-eye"></i>

                            </a>


                            <!-- Edit -->

                            <?php
                            if (
                                $e['status'] === 'draft' &&
                                hasPermission('company_expenses.edit')
                            ):
                            ?>

                                <a
                                    href="edit.php?id=<?= (int)$e['id'] ?>"
                                    class="text-yellow-600 hover:text-yellow-900 mr-3"
                                    title="Edit">

                                    <i class="fas fa-pencil"></i>

                                </a>

                            <?php endif; ?>


                            <!--
                            |--------------------------------------------------------------------------
                            | Submit For Approval
                            |--------------------------------------------------------------------------
                            -->

                            <?php
                            if (
                                $e['status'] === 'draft' &&
                                hasPermission('company_expenses.approve')
                            ):
                            ?>

                                <form
                                    method="POST"
                                    action="submit.php"
                                    style="display:inline"
                                    class="expense-action-form"
                                    data-action="submit"
                                    data-expense="<?= sanitize($e['expense_number']) ?>">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= csrfToken() ?>">

                                    <input
                                        type="hidden"
                                        name="expense_id"
                                        value="<?= (int)$e['id'] ?>">

                                    <button
                                        type="button"
                                        class="submit-expense-btn text-blue-600 hover:text-blue-900 mr-3"
                                        title="Submit for Approval">

                                        <i class="fas fa-paper-plane"></i>

                                    </button>

                                </form>

                            <?php endif; ?>


                            <!--
                            |--------------------------------------------------------------------------
                            | Mark As Paid
                            |--------------------------------------------------------------------------
                            -->

                            <?php
                            if (
                                $e['status'] === 'approved' &&
                                hasPermission('company_expenses.pay')
                            ):
                            ?>

                                <form
                                    method="POST"
                                    action="mark_paid.php"
                                    style="display:inline"
                                    class="expense-action-form"
                                    data-action="paid"
                                    data-expense="<?= sanitize($e['expense_number']) ?>">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= csrfToken() ?>">

                                    <input
                                        type="hidden"
                                        name="expense_id"
                                        value="<?= (int)$e['id'] ?>">

                                    <button
                                        type="button"
                                        class="paid-expense-btn text-green-600 hover:text-green-900 mr-3"
                                        title="Mark as Paid">

                                        <i class="fas fa-money-bill-wave"></i>

                                    </button>

                                </form>

                            <?php endif; ?>


                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (empty($expenses)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="px-4 py-8 text-center text-gray-500">

                            <i class="fas fa-receipt text-4xl mb-3 text-gray-300"></i>

                            <p>
                                No company expenses found
                            </p>


                            <?php if (hasPermission('company_expenses.create')): ?>

                                <a
                                    href="create.php"
                                    class="text-blue-600 hover:underline mt-2 inline-block">

                                    Record your first expense

                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
-->

<?= paginationLinks($pagination, '?') ?>


<!--
|--------------------------------------------------------------------------
| Confirmation Modal
|--------------------------------------------------------------------------
-->

<div
    id="expenseConfirmModal"
    class="fixed inset-0 z-50 hidden"
    aria-hidden="true">


    <!-- Overlay -->

    <div
        id="expenseModalOverlay"
        class="absolute inset-0 bg-black/50">
    </div>


    <!-- Modal Wrapper -->

    <div
        class="relative z-10 flex min-h-full items-center justify-center p-4">


        <!-- Modal -->

        <div
            id="expenseModalBox"
            class="w-full max-w-md bg-white rounded-lg shadow-xl">


            <!-- Header -->

            <div
                class="flex items-center justify-between px-5 py-4 border-b">


                <div class="flex items-center gap-3">


                    <div
                        id="expenseModalIcon"
                        class="w-10 h-10 rounded-full flex items-center justify-center bg-blue-100 text-blue-600">

                        <i class="fas fa-question"></i>

                    </div>


                    <h3
                        id="expenseModalTitle"
                        class="text-lg font-semibold text-gray-800">

                        Confirm Action

                    </h3>

                </div>


                <button
                    type="button"
                    id="expenseModalClose"
                    class="text-gray-400 hover:text-gray-600 text-xl">

                    <i class="fas fa-times"></i>

                </button>

            </div>


            <!-- Body -->

            <div class="px-5 py-5">

                <p
                    id="expenseModalMessage"
                    class="text-sm text-gray-600 leading-6">
                </p>

            </div>


            <!-- Footer -->

            <div
                class="flex justify-end gap-3 px-5 py-4 bg-gray-50 border-t">


                <button
                    type="button"
                    id="expenseModalCancel"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">

                    Cancel

                </button>


                <button
                    type="button"
                    id="expenseModalConfirm"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">

                    Confirm

                </button>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| jQuery
|--------------------------------------------------------------------------
-->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


<script>
    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Date Filter
        |--------------------------------------------------------------------------
        */

        function updateToDate() {

            const fromDate = $('#date_from').val();

            const $toDate = $('#date_to');


            if (fromDate) {

                $toDate.attr(
                    'min',
                    fromDate
                );


                if (
                    $toDate.val() &&
                    $toDate.val() < fromDate
                ) {

                    $toDate.val('');

                }

            } else {

                $toDate.removeAttr('min');

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Initial Date Setup
        |--------------------------------------------------------------------------
        */

        updateToDate();


        /*
        |--------------------------------------------------------------------------
        | From Date Change
        |--------------------------------------------------------------------------
        */

        $('#date_from').on(
            'change',
            function() {

                updateToDate();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | To Date Change
        |--------------------------------------------------------------------------
        */

        $('#date_to').on(
            'change',
            function() {

                const fromDate =
                    $('#date_from').val();

                const toDate =
                    $(this).val();


                if (!fromDate) {

                    $(this).val('');

                    return;

                }


                if (toDate < fromDate) {

                    $(this).val('');

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Modal Variables
        |--------------------------------------------------------------------------
        */

        let currentForm = null;


        const $modal =
            $('#expenseConfirmModal');

        const $message =
            $('#expenseModalMessage');

        const $title =
            $('#expenseModalTitle');

        const $confirmBtn =
            $('#expenseModalConfirm');

        const $icon =
            $('#expenseModalIcon');


        /*
        |--------------------------------------------------------------------------
        | Escape HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(value) {

            return $('<div>')
                .text(value)
                .html();

        }


        /*
        |--------------------------------------------------------------------------
        | Open Modal
        |--------------------------------------------------------------------------
        */

        function openExpenseModal(form) {

            currentForm = form;


            const action =
                $(form).data('action');


            const expenseNumber =
                $(form).data('expense');


            /*
            |--------------------------------------------------------------------------
            | Submit
            |--------------------------------------------------------------------------
            */

            if (action === 'submit') {


                $title.text(
                    'Submit Expense'
                );


                $message.html(
                    'Are you sure you want to submit expense ' +
                    '<strong>' +
                    escapeHtml(expenseNumber) +
                    '</strong>' +
                    ' for approval?'
                );


                $icon
                    .removeClass(
                        'bg-green-100 text-green-600'
                    )
                    .addClass(
                        'bg-blue-100 text-blue-600'
                    );


                $icon.html(
                    '<i class="fas fa-paper-plane"></i>'
                );


                $confirmBtn
                    .text('Submit')
                    .removeClass(
                        'bg-green-600 hover:bg-green-700'
                    )
                    .addClass(
                        'bg-blue-600 hover:bg-blue-700'
                    );

            }


            /*
            |--------------------------------------------------------------------------
            | Mark Paid
            |--------------------------------------------------------------------------
            */

            if (action === 'paid') {


                $title.text(
                    'Mark Expense as Paid'
                );


                $message.html(
                    'Are you sure you want to mark expense ' +
                    '<strong>' +
                    escapeHtml(expenseNumber) +
                    '</strong>' +
                    ' as paid?' +
                    '<br><br>' +
                    '<span class="text-yellow-700">' +
                    '<i class="fas fa-info-circle mr-1"></i>' +
                    'This will post the payment to the general ledger.' +
                    '</span>'
                );


                $icon
                    .removeClass(
                        'bg-blue-100 text-blue-600'
                    )
                    .addClass(
                        'bg-green-100 text-green-600'
                    );


                $icon.html(
                    '<i class="fas fa-money-bill-wave"></i>'
                );


                $confirmBtn
                    .text('Mark as Paid')
                    .removeClass(
                        'bg-blue-600 hover:bg-blue-700'
                    )
                    .addClass(
                        'bg-green-600 hover:bg-green-700'
                    );

            }


            /*
            |--------------------------------------------------------------------------
            | Show Modal
            |--------------------------------------------------------------------------
            */

            $modal
                .removeClass('hidden')
                .attr(
                    'aria-hidden',
                    'false'
                );


            $('body').addClass(
                'overflow-hidden'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Close Modal
        |--------------------------------------------------------------------------
        */

        function closeExpenseModal() {

            $modal
                .addClass('hidden')
                .attr(
                    'aria-hidden',
                    'true'
                );


            $('body').removeClass(
                'overflow-hidden'
            );


            currentForm = null;

        }


        /*
        |--------------------------------------------------------------------------
        | Submit Button
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'click',
            '.submit-expense-btn',
            function() {

                const form =
                    $(this).closest('form')[0];

                openExpenseModal(form);

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Paid Button
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'click',
            '.paid-expense-btn',
            function() {

                const form =
                    $(this).closest('form')[0];

                openExpenseModal(form);

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Confirm
        |--------------------------------------------------------------------------
        */

        $('#expenseModalConfirm').on(
            'click',
            function() {

                if (!currentForm) {

                    return;

                }


                const form =
                    currentForm;


                closeExpenseModal();


                form.submit();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Cancel
        |--------------------------------------------------------------------------
        */

        $('#expenseModalCancel').on(
            'click',
            function() {

                closeExpenseModal();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Close X
        |--------------------------------------------------------------------------
        */

        $('#expenseModalClose').on(
            'click',
            function() {

                closeExpenseModal();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Overlay Click
        |--------------------------------------------------------------------------
        */

        $('#expenseModalOverlay').on(
            'click',
            function() {

                closeExpenseModal();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | ESC Key
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'keydown',
            function(event) {

                if (
                    event.key === 'Escape' &&
                    !$modal.hasClass('hidden')
                ) {

                    closeExpenseModal();

                }

            }
        );


    });
</script>


<style>
    /*
|--------------------------------------------------------------------------
| Modal Animation
|--------------------------------------------------------------------------
*/

    #expenseConfirmModal {

        transition:
            opacity 0.15s ease-in-out;

    }


    #expenseConfirmModal:not(.hidden) {

        animation:
            expenseModalFadeIn 0.15s ease-out;

    }


    @keyframes expenseModalFadeIn {

        from {

            opacity: 0;

        }

        to {

            opacity: 1;

        }

    }


    /*
|--------------------------------------------------------------------------
| Modal Box Animation
|--------------------------------------------------------------------------
*/

    #expenseModalBox {

        animation:
            expenseModalScaleIn 0.15s ease-out;

    }


    @keyframes expenseModalScaleIn {

        from {

            opacity: 0;

            transform:
                scale(0.96) translateY(-8px);

        }

        to {

            opacity: 1;

            transform:
                scale(1) translateY(0);

        }

    }


    /*
|--------------------------------------------------------------------------
| Prevent Button Focus Outline Issue
|--------------------------------------------------------------------------
*/

    #expenseConfirmModal button:focus {

        outline: none;

    }
</style>


<?php

require_once '../../../includes/footer.php';

?>