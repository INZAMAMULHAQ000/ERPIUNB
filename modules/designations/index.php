<?php
$pageTitle = 'Designations';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('designations.view')) {
    die('Access Denied');
}

$db = getDB();

$search = $_GET['search'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$status = $_GET['status'] ?? '';

$page = getPageNumber();
$perPage = 10;

$where = "WHERE 1=1";
$params = [];

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
if ($search) {
    $where .= " AND d.designation_name LIKE ?";
    $params[] = "%$search%";
}

/*
|--------------------------------------------------------------------------
| Department
|--------------------------------------------------------------------------
*/
if ($department_id) {
    $where .= " AND d.department_id = ?";
    $params[] = $department_id;
}

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/
if ($status) {
    $where .= " AND d.status = ?";
    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM designations d
    $where
");

$countStmt->execute($params);

$total = $countStmt->fetch()['cnt'];

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$pagination = paginate($total, $perPage, $page);

/*
|--------------------------------------------------------------------------
| Designations
|--------------------------------------------------------------------------
*/
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT
        d.*,
        dep.department_name
    FROM designations d
    LEFT JOIN departments dep
        ON d.department_id = dep.id
    $where
    ORDER BY d.designation_name ASC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$designations = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Departments
|--------------------------------------------------------------------------
*/
$departments = $db->query("
    SELECT *
    FROM departments
    WHERE status = 'active'
    ORDER BY department_name
")->fetchAll();
?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Custom Select
    |--------------------------------------------------------------------------
    */
    #designationFilter select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none !important;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Modal
    |--------------------------------------------------------------------------
    */
    #deleteDesignationModal {
        display: none;
    }

    #deleteDesignationModal.show {
        display: flex;
    }

    body.modal-open {
        overflow: hidden;
    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->
<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Designations
        </h1>

        <p class="text-gray-600">
            Manage job titles and positions
        </p>
    </div>

    <?php if (hasPermission('designations.create')): ?>

        <a
            href="create.php"
            class="bg-blue-600 text-white px-4 py-2 rounded
                   hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Designation
        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     FILTERS
========================================================= -->
<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="designationFilter"
        class="p-4 flex flex-wrap gap-4 items-end">

        <!-- Search -->
        <div class="flex-1 min-w-[200px]">

            <label
                class="block text-sm font-medium text-gray-700 mb-1"
                for="search">
                Search
            </label>

            <input
                type="text"
                id="search"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Search by name..."
                class="w-full px-3 py-2 border border-gray-300 rounded
                       focus:outline-none focus:ring-2
                       focus:ring-blue-500">

        </div>


        <!-- Department -->
        <div class="w-48">

            <label
                class="block text-sm font-medium text-gray-700 mb-1"
                for="department_id">
                Department
            </label>

            <div class="relative">

                <select
                    id="department_id"
                    name="department_id"
                    class="w-full px-3 py-2 pr-9
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-2
                           focus:ring-blue-500">

                    <option value="">
                        All Departments
                    </option>

                    <?php foreach ($departments as $dept): ?>

                        <option
                            value="<?= (int)$dept['id'] ?>"
                            <?= $department_id == $dept['id']
                                ? 'selected'
                                : '' ?>>
                            <?= sanitize($dept['department_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


            </div>

        </div>


        <!-- Status -->
        <div class="w-32">

            <label
                class="block text-sm font-medium text-gray-700 mb-1"
                for="status">
                Status
            </label>

            <div class="relative">

                <select
                    id="status"
                    name="status"
                    class="w-full px-3 py-2 pr-9
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-2
                           focus:ring-blue-500">

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

        </div>


        <!-- Search -->
        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded
                   hover:bg-gray-700 transition"
            title="Search">
            <i class="fas fa-search"></i>
        </button>

    </form>

</div>


<!-- =========================================================
     TABLE
========================================================= -->
<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Designation
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Department
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Employees
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Status
                    </th>

                    <th
                        class="px-6 py-3 text-right
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-gray-200">

                <?php foreach ($designations as $desig): ?>

                    <?php
                    /*
                     * Existing employee count logic preserved.
                     */
                    $empCount = $db->prepare("
                        SELECT COUNT(*) as cnt
                        FROM employees
                        WHERE designation_id = ?
                        AND status = 'active'
                    ");

                    $empCount->execute([
                        $desig['id']
                    ]);

                    $empCnt = $empCount->fetch()['cnt'];
                    ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Designation -->
                        <td
                            class="px-6 py-4
                                   font-medium
                                   text-gray-900">
                            <?= sanitize(
                                $desig['designation_name']
                            ) ?>
                        </td>


                        <!-- Department -->
                        <td
                            class="px-6 py-4
                                   text-gray-600">
                            <?= sanitize(
                                $desig['department_name'] ?? '-'
                            ) ?>
                        </td>


                        <!-- Employees -->
                        <td class="px-6 py-4">

                            <span
                                class="bg-blue-100
                                       text-blue-800
                                       text-xs
                                       px-2 py-1
                                       rounded">
                                <?= $empCnt ?>
                                <?= $empCnt == 1
                                    ? 'employee'
                                    : 'employees' ?>
                            </span>

                        </td>


                        <!-- Status -->
                        <td class="px-6 py-4">

                            <?php if (
                                $desig['status'] === 'active'
                            ): ?>

                                <span
                                    class="px-2 py-1
                                           text-xs rounded
                                           bg-green-100
                                           text-green-800">
                                    Active
                                </span>

                            <?php else: ?>

                                <span
                                    class="px-2 py-1
                                           text-xs rounded
                                           bg-gray-100
                                           text-gray-800">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Actions -->
                        <td
                            class="px-6 py-4
                                   text-right">

                            <?php if (
                                hasPermission('designations.edit')
                            ): ?>

                                <a
                                    href="edit.php?id=<?= (int)$desig['id'] ?>"
                                    class="text-yellow-600
                                           hover:text-yellow-900
                                           mr-2"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                            <?php endif; ?>


                            <?php if (
                                hasPermission('designations.delete')
                                && $empCnt == 0
                            ): ?>

                                <button
                                    type="button"
                                    class="text-red-600
                                           hover:text-red-900"
                                    title="Delete"
                                    onclick="openDeleteDesignationModal(
                                        <?= (int)$desig['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $desig['designation_name'],
                                                JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_AMP
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    )">
                                    <i class="fas fa-trash"></i>
                                </button>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (empty($designations)): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="px-6 py-8
                                   text-center
                                   text-gray-500">
                            No designations found
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================================================
     PAGINATION
========================================================= -->
<?= paginationLinks($pagination, '?') ?>


<!-- =========================================================
     DELETE CONFIRMATION MODAL
========================================================= -->
<div
    id="deleteDesignationModal"
    class="fixed inset-0 z-50
           items-center justify-center
           bg-black/50 px-4">

    <div
        class="bg-white
               w-full max-w-md
               shadow-xl"
        onclick="event.stopPropagation()">

        <!-- Modal Header -->
        <div
            class="flex items-center
                   justify-between
                   px-5 py-4
                   border-b border-gray-200">

            <div class="flex items-center gap-3">

                <div
                    class="w-10 h-10
                           flex items-center
                           justify-center
                           bg-red-100
                           text-red-600">
                    <i class="fas fa-trash-alt"></i>
                </div>

                <div>

                    <h3
                        class="text-base
                               font-semibold
                               text-gray-800">
                        Delete Designation
                    </h3>

                    <p
                        class="text-xs
                               text-gray-500">
                        Confirmation required
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeleteDesignationModal()"
                class="text-gray-400
                       hover:text-gray-600"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Modal Body -->
        <div class="px-5 py-5">

            <p
                class="text-sm
                       text-gray-700
                       leading-6">

                Are you sure you want to delete

                <span
                    id="deleteDesignationName"
                    class="font-semibold
                           text-gray-900"></span>

                ?

            </p>


            <div
                class="mt-4
                       px-3 py-3
                       bg-yellow-50
                       border border-yellow-200
                       text-yellow-800
                       text-xs">

                <i
                    class="fas fa-exclamation-triangle
                           mr-1"></i>

                This action cannot be undone.

            </div>

        </div>


        <!-- Modal Footer -->
        <div
            class="flex justify-end
                   gap-3
                   px-5 py-4
                   border-t border-gray-200
                   bg-gray-50">

            <button
                type="button"
                onclick="closeDeleteDesignationModal()"
                class="px-4 py-2
                       text-sm
                       border border-gray-300
                       bg-white
                       text-gray-700
                       rounded
                       hover:bg-gray-100">
                Cancel
            </button>


            <a
                href="#"
                id="confirmDeleteDesignation"
                class="px-4 py-2
                       text-sm
                       bg-red-600
                       text-white
                       rounded
                       hover:bg-red-700">
                <i class="fas fa-trash mr-1"></i>
                Delete
            </a>

        </div>

    </div>

</div>


<script>
    (function() {

        const modal = document.getElementById(
            'deleteDesignationModal'
        );

        const nameElement = document.getElementById(
            'deleteDesignationName'
        );

        const confirmButton = document.getElementById(
            'confirmDeleteDesignation'
        );


        /*
        |--------------------------------------------------------------------------
        | Open Delete Modal
        |--------------------------------------------------------------------------
        */
        window.openDeleteDesignationModal = function(
            designationId,
            designationName
        ) {

            nameElement.textContent = designationName;

            /*
             * Keep your existing delete.php logic.
             */
            confirmButton.href =
                'delete.php?id=' +
                encodeURIComponent(designationId);

            modal.classList.add('show');

            document.body.classList.add('modal-open');
        };


        /*
        |--------------------------------------------------------------------------
        | Close Delete Modal
        |--------------------------------------------------------------------------
        */
        window.closeDeleteDesignationModal = function() {

            modal.classList.remove('show');

            document.body.classList.remove('modal-open');

            nameElement.textContent = '';

            confirmButton.href = '#';
        };


        /*
        |--------------------------------------------------------------------------
        | Click outside modal
        |--------------------------------------------------------------------------
        */
        modal.addEventListener('click', function(event) {

            if (event.target === modal) {
                closeDeleteDesignationModal();
            }

        });


        /*
        |--------------------------------------------------------------------------
        | ESC key
        |--------------------------------------------------------------------------
        */
        document.addEventListener('keydown', function(event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains('show')
            ) {
                closeDeleteDesignationModal();
            }

        });

    })();
</script>


<?php require_once '../../includes/footer.php'; ?>