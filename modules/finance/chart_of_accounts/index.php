<?php
$pageTitle = 'Chart of Accounts';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();

$search  = $_GET['search'] ?? '';
$group_id = $_GET['group_id'] ?? '';
$status  = $_GET['status'] ?? '';

$page = getPageNumber();
$perPage = 15;


// =========================================================
// Filters
// =========================================================

$where = "WHERE 1=1";
$params = [];

if ($search !== '') {

    $where .= "
        AND (
            coa.account_code LIKE ?
            OR coa.account_name LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($group_id !== '') {

    $where .= " AND coa.group_id = ?";

    $params[] = $group_id;
}

if ($status !== '') {

    $where .= " AND coa.status = ?";

    $params[] = $status;
}


// =========================================================
// Count
// =========================================================

$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM chart_of_accounts coa
    $where
");

$countStmt->execute($params);

$total = $countStmt->fetch()['cnt'];


// =========================================================
// Pagination
// =========================================================

$pagination = paginate(
    $total,
    $perPage,
    $page
);


// =========================================================
// Accounts
// =========================================================

$queryParams = array_merge(
    $params,
    [
        $perPage,
        $pagination['offset']
    ]
);

$stmt = $db->prepare("
    SELECT
        coa.*,
        ag.group_name,
        at.type_name

    FROM chart_of_accounts coa

    LEFT JOIN account_groups ag
        ON coa.group_id = ag.id

    LEFT JOIN account_types at
        ON coa.type_id = at.id

    $where

    ORDER BY coa.account_code ASC

    LIMIT ?
    OFFSET ?
");

$stmt->execute($queryParams);

$accounts = $stmt->fetchAll();


// =========================================================
// Account Groups
// =========================================================

$allGroups = $db->query("
    SELECT
        id,
        group_name,
        parent_group_id,
        nature

    FROM account_groups

    WHERE status = 'active'

    ORDER BY
        nature,
        display_order,
        group_name
")->fetchAll();


// =========================================================
// Build Group Filter Options
// =========================================================

function buildGroupFilterOptions(
    $groups,
    $selectedId = null,
    $parentId = 0,
    $depth = 0
) {

    $html = '';

    foreach ($groups as $g) {

        if (($g['parent_group_id'] ?? 0) == $parentId) {

            $prefix = str_repeat(
                '&nbsp;&nbsp;',
                $depth
            );

            $sel = (
                $selectedId == $g['id']
            )
                ? ' selected'
                : '';

            $html .= '
                <option
                    value="' . $g['id'] . '"' . $sel . '>
                    ' . $prefix . sanitize($g['group_name']) . '
                </option>
            ';

            $html .= buildGroupFilterOptions(
                $groups,
                $selectedId,
                $g['id'],
                $depth + 1
            );
        }
    }

    return $html;
}

?>

<!-- =======================================================
     Page Header
======================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Chart of Accounts
        </h1>

        <p class="text-gray-600">
            Manage your chart of accounts
        </p>

    </div>


    <?php if (hasPermission('finance.account.create')): ?>

        <a
            href="<?= url('modules/finance/chart_of_accounts/create.php') ?>"
            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">

            <i class="fas fa-plus mr-2"></i>

            Add Account

        </a>

    <?php endif; ?>

</div>


<!-- =======================================================
     Search / Filters
======================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        class="p-4 flex flex-wrap gap-4 items-end">


        <!-- Search -->

        <div class="flex-1 min-w-[200px]">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Account code or name..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Group -->

        <div class="w-48">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Group
            </label>

            <select
                name="group_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All Groups
                </option>

                <?= buildGroupFilterOptions(
                    $allGroups,
                    $group_id
                ) ?>

            </select>

        </div>


        <!-- Status -->

        <div class="w-32">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All
                </option>

                <option
                    value="active"
                    <?= $status === 'active' ? 'selected' : '' ?>>

                    Active

                </option>

                <option
                    value="inactive"
                    <?= $status === 'inactive' ? 'selected' : '' ?>>

                    Inactive

                </option>

            </select>

        </div>


        <!-- Search Button -->

        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">

            <i class="fas fa-search"></i>

        </button>

    </form>

</div>


<!-- =======================================================
     Accounts Table
======================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">

            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Account Code
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Account Name
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Group
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Type
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Opening Balance
                </th>

                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                    Manual Entry
                </th>

                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                    Status
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody class="divide-y divide-gray-200">


            <?php foreach ($accounts as $acct): ?>

                <?php

                $accountCode = htmlspecialchars(
                    $acct['account_code'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $accountName = htmlspecialchars(
                    $acct['account_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

                <tr class="hover:bg-gray-50">


                    <!-- Account Code -->

                    <td class="px-6 py-4 font-mono text-gray-900">

                        <?= sanitize(
                            $acct['account_code']
                        ) ?>

                    </td>


                    <!-- Account Name -->

                    <td class="px-6 py-4 font-medium text-gray-900">

                        <?= sanitize(
                            $acct['account_name']
                        ) ?>

                    </td>


                    <!-- Group -->

                    <td class="px-6 py-4 text-gray-600">

                        <?= sanitize(
                            $acct['group_name'] ?? '-'
                        ) ?>

                    </td>


                    <!-- Type -->

                    <td class="px-6 py-4 text-gray-600">

                        <?= sanitize(
                            $acct['type_name'] ?? '-'
                        ) ?>

                    </td>


                    <!-- Opening Balance -->

                    <td class="px-6 py-4 text-right">

                        <?php if (
                            (float) $acct['opening_balance'] != 0
                        ): ?>

                            <span
                                class="font-medium
                                <?= $acct['opening_balance_type'] === 'debit'
                                    ? 'text-blue-600'
                                    : 'text-red-600'
                                ?>">

                                <?= formatMoney(
                                    $acct['opening_balance']
                                ) ?>

                                <span class="text-xs">

                                    <?= ucfirst(
                                        $acct['opening_balance_type']
                                    ) ?>

                                </span>

                            </span>

                        <?php else: ?>

                            <span class="text-gray-400">
                                -
                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- Manual Entry -->

                    <td class="px-6 py-4 text-center">

                        <?php if ($acct['allow_manual_entry']): ?>

                            <span class="text-green-600">

                                <i
                                    class="fas fa-check-circle"
                                    title="Allowed">
                                </i>

                            </span>

                        <?php else: ?>

                            <span class="text-red-400">

                                <i
                                    class="fas fa-times-circle"
                                    title="Not allowed">
                                </i>

                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- Status -->

                    <td class="px-6 py-4 text-center">

                        <span
                            class="px-2 py-1 text-xs rounded
                            <?= $acct['status'] === 'active'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                            ?>">

                            <?= ucfirst(
                                $acct['status']
                            ) ?>

                        </span>

                    </td>


                    <!-- Actions -->

                    <td class="px-6 py-4 text-right">


                        <?php if (hasPermission('finance.manage')): ?>


                            <!-- Edit -->

                            <a
                                href="<?= url(
                                            'modules/finance/chart_of_accounts/edit.php?id='
                                                . $acct['id']
                                        ) ?>"
                                class="text-yellow-600 hover:text-yellow-900 mr-2"
                                title="Edit">

                                <i class="fas fa-edit"></i>

                            </a>


                            <!-- Delete -->

                            <a
                                href="<?= url(
                                            'modules/finance/chart_of_accounts/delete.php?id='
                                                . $acct['id']
                                        ) ?>"
                                class="text-red-600 hover:text-red-900"
                                title="Delete"

                                onclick="return showAccountDeleteConfirmation(
                                    '<?= $accountCode ?>',
                                    '<?= $accountName ?>',
                                    this.href
                                );">

                                <i class="fas fa-trash"></i>

                            </a>


                        <?php endif; ?>


                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (empty($accounts)): ?>

                <tr>

                    <td
                        colspan="8"
                        class="px-6 py-8 text-center text-gray-500">

                        No accounts found

                    </td>

                </tr>

            <?php endif; ?>


        </tbody>

    </table>

</div>


<!-- =======================================================
     Pagination
======================================================= -->

<?= paginationLinks(
    $pagination,
    '?'
) ?>


<!-- =======================================================
     Delete Confirmation Modal
======================================================= -->

<div
    id="accountDeleteModal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-4">


    <div
        class="bg-white rounded-lg shadow-xl w-full max-w-md">


        <!-- Modal Header -->

        <div class="px-6 py-4 border-b">

            <div class="flex items-center gap-3">


                <div
                    class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">

                    <i
                        class="fas fa-trash text-red-600">
                    </i>

                </div>


                <div>

                    <h3
                        class="text-lg font-semibold text-gray-800">

                        Delete Account

                    </h3>

                </div>


            </div>

        </div>


        <!-- Modal Message -->

        <div class="px-6 py-5">

            <p
                id="accountDeleteMessage"
                class="text-gray-600 leading-relaxed">

            </p>

        </div>


        <!-- Modal Buttons -->

        <div
            class="px-6 py-4 bg-gray-50 flex justify-end gap-3">


            <button
                type="button"
                id="accountDeleteCancelBtn"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">

                Cancel

            </button>


            <button
                type="button"
                id="accountDeleteConfirmBtn"
                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">

                Delete

            </button>


        </div>

    </div>

</div>


<!-- =======================================================
     Delete Confirmation JavaScript
======================================================= -->

<script>
    let accountDeleteUrl = null;


    /**
     * Show Delete Confirmation
     */
    function showAccountDeleteConfirmation(
        accountCode,
        accountName,
        url
    ) {

        accountDeleteUrl = url;


        const modal =
            document.getElementById(
                'accountDeleteModal'
            );


        const message =
            document.getElementById(
                'accountDeleteMessage'
            );


        message.textContent =
            'Are you sure you want to delete account ' +
            accountCode +
            ' - ' +
            accountName +
            '? This action cannot be undone.';


        modal.classList.remove('hidden');

        modal.classList.add('flex');


        // Prevent background scrolling

        document.body.classList.add(
            'overflow-hidden'
        );


        // Stop normal link navigation

        return false;
    }


    /**
     * Cancel
     */
    document.getElementById(
        'accountDeleteCancelBtn'
    ).addEventListener(
        'click',
        function() {

            closeAccountDeleteModal();

        }
    );


    /**
     * Confirm Delete
     */
    document.getElementById(
        'accountDeleteConfirmBtn'
    ).addEventListener(
        'click',
        function() {

            if (accountDeleteUrl) {

                window.location.href =
                    accountDeleteUrl;

            }

        }
    );


    /**
     * Close Modal
     */
    function closeAccountDeleteModal() {

        const modal =
            document.getElementById(
                'accountDeleteModal'
            );


        modal.classList.add('hidden');

        modal.classList.remove('flex');


        accountDeleteUrl = null;


        document.body.classList.remove(
            'overflow-hidden'
        );

    }


    /**
     * Click outside modal
     */
    document.getElementById(
        'accountDeleteModal'
    ).addEventListener(
        'click',
        function(event) {

            if (event.target === this) {

                closeAccountDeleteModal();

            }

        }
    );


    /**
     * ESC key
     */
    document.addEventListener(
        'keydown',
        function(event) {

            if (
                event.key === 'Escape' &&
                accountDeleteUrl
            ) {

                closeAccountDeleteModal();

            }

        }
    );
</script>


<?php
require_once '../../../includes/footer.php';
?>