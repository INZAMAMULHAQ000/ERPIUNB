<?php
$pageTitle = 'Account Types';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();

$search = $_GET['search'] ?? '';
$groupId = $_GET['group_id'] ?? '';

$page = getPageNumber();
$perPage = 10;

$where = "WHERE 1=1";
$params = [];


/*
 * Search
 */
if ($search) {

    $where .= " AND at.type_name LIKE ?";

    $params[] = "%$search%";
}


/*
 * Group Filter
 */
if ($groupId !== '') {

    $where .= " AND at.group_id = ?";

    $params[] = (int) $groupId;
}


/*
 * Total Count
 */
$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM account_types at
    $where
");

$countStmt->execute($params);

$total = $countStmt->fetch()['cnt'];


/*
 * Pagination
 */
$pagination = paginate(
    $total,
    $perPage,
    $page
);


/*
 * Account Types
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
        at.*,
        ag.group_name,
        ag.nature
    FROM account_types at
    JOIN account_groups ag
        ON at.group_id = ag.id
    $where
    ORDER BY at.type_name ASC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$accountTypes = $stmt->fetchAll();


/*
 * Groups
 */
$groups = $db->query("
    SELECT
        id,
        group_name,
        nature
    FROM account_groups
    WHERE status = 'active'
    ORDER BY nature, display_order
")->fetchAll();

?>

<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Account Types
        </h1>

        <p class="text-gray-600">
            Manage account type classifications
        </p>

    </div>


    <?php if (hasPermission('finance.manage')): ?>

        <a
            href="create.php"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-plus mr-2"></i>

            Add Account Type

        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     SEARCH / FILTER
========================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        class="p-4 flex flex-wrap gap-4 items-end">


        <!-- Search -->
        <div class="flex-1 min-w-[200px]">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Search

            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Search by type name..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Group -->
        <div class="w-56">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Group

            </label>

            <select
                name="group_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All Groups
                </option>

                <?php foreach ($groups as $g): ?>

                    <option
                        value="<?= $g['id'] ?>"
                        <?= $groupId == $g['id'] ? 'selected' : '' ?>>

                        [<?= ucfirst(sanitize($g['nature'])) ?>]
                        <?= sanitize($g['group_name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Search Button -->
        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

            <i class="fas fa-search"></i>

        </button>

    </form>

</div>


<!-- =========================================================
     TABLE
========================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">

            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Type Name
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Group
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Description
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Created Date
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody class="divide-y divide-gray-200">

            <?php foreach ($accountTypes as $at): ?>

                <tr class="hover:bg-gray-50">


                    <!-- Type Name -->
                    <td class="px-6 py-4 font-medium text-gray-900">

                        <?= sanitize($at['type_name']) ?>

                    </td>


                    <!-- Group -->
                    <td class="px-6 py-4 text-gray-600">

                        <span class="text-xs uppercase text-gray-400">

                            [<?= sanitize($at['nature']) ?>]

                        </span>

                        <?= sanitize($at['group_name']) ?>

                    </td>


                    <!-- Description -->
                    <td class="px-6 py-4 text-gray-500 text-sm">

                        <?= sanitize($at['description'] ?? '-') ?>

                    </td>


                    <!-- Created Date -->
                    <td class="px-6 py-4 text-gray-500 text-sm">

                        <?= date(
                            'M d, Y',
                            strtotime($at['created_at'])
                        ) ?>

                    </td>


                    <!-- Actions -->
                    <td class="px-6 py-4 text-right">

                        <?php if (hasPermission('finance.manage')): ?>


                            <!-- Edit -->
                            <a
                                href="edit.php?id=<?= $at['id'] ?>"
                                class="text-yellow-600 hover:text-yellow-900 mr-2"
                                title="Edit">

                                <i class="fas fa-edit"></i>

                            </a>


                            <!-- Delete -->
                            <a
                                href="javascript:void(0);"
                                class="text-red-600 hover:text-red-900"
                                title="Delete"
                                onclick="showAccountTypeDeleteModal(
                                    '<?= $at['id'] ?>',
                                    '<?= htmlspecialchars(
                                            $at['type_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>',
                                    'delete.php?id=<?= $at['id'] ?>'
                                )">

                                <i class="fas fa-trash"></i>

                            </a>


                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (empty($accountTypes)): ?>

                <tr>

                    <td
                        colspan="5"
                        class="px-6 py-8 text-center text-gray-500">

                        No account types found

                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =========================================================
     PAGINATION
========================================================= -->

<?= paginationLinks($pagination, '?') ?>


<!-- =========================================================
     DELETE CONFIRMATION MODAL
========================================================= -->

<div
    id="accountTypeDeleteModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">


    <div
        class="bg-white rounded-lg shadow-xl w-full max-w-md">


        <!-- Header -->
        <div class="px-6 py-4 border-b">

            <div class="flex items-center gap-3">

                <div
                    class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">

                    <i class="fas fa-trash text-red-600"></i>

                </div>


                <div>

                    <h3 class="text-lg font-semibold text-gray-800">

                        Confirm Delete

                    </h3>

                    <p class="text-sm text-gray-500">

                        Please confirm your action

                    </p>

                </div>

            </div>

        </div>


        <!-- Body -->
        <div class="px-6 py-5">

            <p class="text-gray-600">

                Are you sure you want to delete this account type?

            </p>


            <div
                id="accountTypeDeleteName"
                class="mt-3 px-3 py-2 bg-gray-50 border rounded text-sm font-medium text-gray-800">

            </div>


            <p class="mt-3 text-sm text-red-600">

                This action cannot be undone.

            </p>

        </div>


        <!-- Footer -->
        <div
            class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">


            <button
                type="button"
                onclick="closeAccountTypeDeleteModal()"
                class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-100 transition">

                Cancel

            </button>


            <button
                type="button"
                onclick="confirmAccountTypeDelete()"
                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">

                Delete

            </button>

        </div>

    </div>

</div>


<script>
    /*
     * =========================================================
     * ACCOUNT TYPE DELETE MODAL
     * =========================================================
     */

    let accountTypeDeleteUrl = '';


    /*
     * Open Delete Modal
     */
    function showAccountTypeDeleteModal(
        id,
        typeName,
        url
    ) {

        accountTypeDeleteUrl = url;


        /*
         * Show Account Type Name
         */
        document.getElementById(
                'accountTypeDeleteName'
            ).textContent =
            'Account Type: ' + typeName;


        /*
         * Open Modal
         */
        const modal = document.getElementById(
            'accountTypeDeleteModal'
        );

        modal.classList.remove('hidden');

        modal.classList.add('flex');

    }


    /*
     * Close Delete Modal
     */
    function closeAccountTypeDeleteModal() {

        const modal = document.getElementById(
            'accountTypeDeleteModal'
        );

        modal.classList.add('hidden');

        modal.classList.remove('flex');

        accountTypeDeleteUrl = '';

    }


    /*
     * Confirm Delete
     */
    function confirmAccountTypeDelete() {

        if (!accountTypeDeleteUrl) {
            return;
        }

        window.location.href = accountTypeDeleteUrl;

    }


    /*
     * ESC Key
     */
    document.addEventListener(
        'keydown',
        function(event) {

            if (event.key === 'Escape') {

                const modal = document.getElementById(
                    'accountTypeDeleteModal'
                );

                if (!modal.classList.contains('hidden')) {

                    closeAccountTypeDeleteModal();

                }

            }

        }
    );


    /*
     * Click Outside Modal
     */
    document.getElementById(
        'accountTypeDeleteModal'
    ).addEventListener(
        'click',
        function(event) {

            if (event.target === this) {

                closeAccountTypeDeleteModal();

            }

        }
    );
</script>


<?php require_once '../../../includes/footer.php'; ?>