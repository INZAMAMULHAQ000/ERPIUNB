<?php
$pageTitle = 'Departments';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('departments.view')) {
    die('Access Denied');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| CSRF fallback
|--------------------------------------------------------------------------
*/
if (!function_exists('csrfToken')) {
    function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrf')) {
    function verifyCsrf($token)
    {
        return !empty($_SESSION['csrf_token'])
            && !empty($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$csrf = csrfToken();


/*
|--------------------------------------------------------------------------
| Delete Department
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $departmentId = (int)($_POST['department_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/departments/index.php');
        exit;
    }

    if ($action === 'delete') {

        if (!hasPermission('departments.delete')) {
            flash(
                'error',
                'You do not have permission to delete departments.'
            );

            to('modules/departments/index.php');
            exit;
        }

        if ($departmentId <= 0) {
            flash('error', 'Invalid department selected.');
            to('modules/departments/index.php');
            exit;
        }

        try {

            /*
             * Check department exists
             */
            $departmentStmt = $db->prepare("
                SELECT id, department_name
                FROM departments
                WHERE id = ?
                LIMIT 1
            ");

            $departmentStmt->execute([$departmentId]);

            $department = $departmentStmt->fetch();

            if (!$department) {

                flash(
                    'error',
                    'Department not found.'
                );

                to('modules/departments/index.php');
                exit;
            }


            /*
             * Check active designations
             */
            $designationStmt = $db->prepare("
                SELECT COUNT(*) AS cnt
                FROM designations
                WHERE department_id = ?
                  AND status = 'active'
            ");

            $designationStmt->execute([$departmentId]);

            $designationCount = (int)$designationStmt->fetch()['cnt'];

            if ($designationCount > 0) {

                flash(
                    'error',
                    'This department cannot be deleted because it has '
                        . $designationCount
                        . ' active designation(s).'
                );

                to('modules/departments/index.php');
                exit;
            }


            /*
             * Delete department
             */
            $deleteStmt = $db->prepare("
                DELETE FROM departments
                WHERE id = ?
            ");

            $deleteStmt->execute([$departmentId]);


            /*
             * Audit log if available
             */
            if (function_exists('logAudit')) {

                logAudit(
                    $_SESSION['user_id'] ?? null,
                    'Deleted department: ' . $department['department_name'],
                    'departments'
                );
            }


            flash(
                'success',
                'Department "' .
                    $department['department_name'] .
                    '" deleted successfully.'
            );
        } catch (PDOException $e) {

            /*
             * Foreign key constraint
             */
            if ((string)$e->getCode() === '23000') {

                flash(
                    'error',
                    'This department cannot be deleted because related records exist.'
                );
            } else {

                flash(
                    'error',
                    'Unable to delete the department. Please try again.'
                );
            }
        }

        to('modules/departments/index.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
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
if ($search !== '') {

    $where .= "
        AND (
            department_name LIKE ?
            OR department_code LIKE ?
        )
    ";

    $searchValue = "%{$search}%";

    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/
if ($status !== '') {

    $where .= " AND status = ?";
    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) AS cnt
    FROM departments
    $where
");

$countStmt->execute($params);

$total = (int)$countStmt->fetch()['cnt'];


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$pagination = paginate(
    $total,
    $perPage,
    $page
);


/*
|--------------------------------------------------------------------------
| Departments
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
    SELECT *
    FROM departments
    $where
    ORDER BY department_name ASC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$departments = $stmt->fetchAll();

?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Delete Modal
    |--------------------------------------------------------------------------
    */

    #deleteDepartmentModal {
        display: none;
    }

    #deleteDepartmentModal.show {
        display: flex;
    }

    #deleteDepartmentModal .modal-box {
        transform: scale(0.96);
        opacity: 0;
        transition: all 0.15s ease;
    }

    #deleteDepartmentModal.show .modal-box {
        transform: scale(1);
        opacity: 1;
    }


    /*
    |--------------------------------------------------------------------------
    | Select
    |--------------------------------------------------------------------------
    */

    .department-filter-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Responsive Table
    |--------------------------------------------------------------------------
    */

    .department-table-wrapper {
        overflow-x: auto;
    }

    .department-table {
        min-width: 900px;
    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Departments
        </h1>

        <p class="text-gray-600">
            Manage organizational departments
        </p>

    </div>


    <?php if (hasPermission('departments.create')): ?>

        <a
            href="create.php"
            class="bg-blue-600 text-white px-4 py-2 rounded
                   hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Department
        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     FILTERS
========================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="departmentFilterForm"
        class="p-4 flex flex-wrap gap-4 items-end">

        <!-- Search -->

        <div class="flex-1 min-w-[200px]">

            <label
                for="departmentSearch"
                class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                id="departmentSearch"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Search by name or code..."
                class="w-full px-3 py-2
                       border border-gray-300 rounded
                       focus:outline-none
                       focus:ring-1 focus:ring-blue-500
                       focus:border-blue-500">

        </div>


        <!-- Status -->

        <div class="w-40">

            <label
                for="departmentStatus"
                class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <div class="relative">

                <select
                    id="departmentStatus"
                    name="status"
                    class="department-filter-select
                           w-full px-3 py-2 pr-8
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-1 focus:ring-blue-500
                           focus:border-blue-500">

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

                <span
                    class="pointer-events-none
                           absolute right-3 top-1/2
                           -translate-y-1/2
                           text-gray-500">
                    <i class="fas fa-chevron-down text-xs"></i>
                </span>

            </div>

        </div>


        <!-- Search Button -->

        <button
            type="submit"
            class="bg-gray-600 text-white
                   px-4 py-2 rounded
                   hover:bg-gray-700 transition"
            title="Search">
            <i class="fas fa-search"></i>
        </button>

    </form>

</div>


<!-- =========================================================
     DEPARTMENT TABLE
========================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="department-table-wrapper">

        <table class="w-full department-table">

            <thead class="bg-gray-50">

                <tr>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Department Name
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Code
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Description
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Designations
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

                <?php foreach ($departments as $dept): ?>

                    <?php

                    /*
                     * Active designation count
                     */
                    $desigCountStmt = $db->prepare("
                        SELECT COUNT(*) AS cnt
                        FROM designations
                        WHERE department_id = ?
                          AND status = 'active'
                    ");

                    $desigCountStmt->execute([
                        $dept['id']
                    ]);

                    $desigCnt = (int)$desigCountStmt
                        ->fetch()['cnt'];

                    $departmentName =
                        $dept['department_name'] ?? '';

                    ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Department -->

                        <td
                            class="px-6 py-4
                                   font-medium text-gray-900">
                            <?= sanitize($departmentName) ?>
                        </td>


                        <!-- Code -->

                        <td class="px-6 py-4 text-gray-600">

                            <?= sanitize(
                                $dept['department_code'] ?? '-'
                            ) ?>

                        </td>


                        <!-- Description -->

                        <td
                            class="px-6 py-4
                                   text-gray-500 text-sm">

                            <?= sanitize(
                                $dept['description'] ?? '-'
                            ) ?>

                        </td>


                        <!-- Designations -->

                        <td class="px-6 py-4">

                            <span
                                class="bg-blue-100 text-blue-800
                                       text-xs px-2 py-1 rounded">
                                <?= $desigCnt ?>
                                <?= $desigCnt == 1
                                    ? 'designation'
                                    : 'designations' ?>
                            </span>

                        </td>


                        <!-- Status -->

                        <td class="px-6 py-4">

                            <?php if ($dept['status'] === 'active'): ?>

                                <span
                                    class="px-2 py-1 text-xs rounded
                                           bg-green-100
                                           text-green-800">
                                    Active
                                </span>

                            <?php else: ?>

                                <span
                                    class="px-2 py-1 text-xs rounded
                                           bg-gray-100
                                           text-gray-800">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Actions -->

                        <td class="px-6 py-4 text-right">

                            <!-- View -->

                            <?php if (hasPermission('departments.view')): ?>

                                <a
                                    href="view.php?id=<?= (int)$dept['id'] ?>"
                                    class="text-blue-600
                                           hover:text-blue-900 mr-2"
                                    title="View">
                                    <i class="fas fa-eye"></i>
                                </a>

                            <?php endif; ?>


                            <!-- Edit -->

                            <?php if (hasPermission('departments.edit')): ?>

                                <a
                                    href="edit.php?id=<?= (int)$dept['id'] ?>"
                                    class="text-yellow-600
                                           hover:text-yellow-900 mr-2"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                            <?php endif; ?>


                            <!-- Delete -->

                            <?php
                            if (
                                hasPermission('departments.delete')
                                && $desigCnt == 0
                            ):
                            ?>

                                <button
                                    type="button"
                                    class="text-red-600
                                           hover:text-red-900"
                                    title="Delete"
                                    onclick="openDeleteDepartmentModal(
                                        <?= (int)$dept['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $departmentName,
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


                <!-- Empty -->

                <?php if (empty($departments)): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="px-6 py-8
                                   text-center
                                   text-gray-500">
                            No departments found
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
    id="deleteDepartmentModal"
    class="fixed inset-0 z-50
           items-center justify-center
           bg-black/40 px-4"
    aria-hidden="true">

    <div
        class="modal-box w-full max-w-md
               bg-white shadow-xl
               border border-gray-200"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteDepartmentTitle">

        <!-- Modal Header -->

        <div
            class="flex items-center justify-between
                   border-b border-gray-200
                   px-5 py-4">

            <div class="flex items-center gap-3">

                <div
                    class="h-9 w-9
                           flex items-center justify-center
                           bg-red-100 text-red-600">
                    <i class="fas fa-trash"></i>
                </div>

                <div>

                    <h3
                        id="deleteDepartmentTitle"
                        class="text-base font-semibold
                               text-gray-800">
                        Delete Department
                    </h3>

                    <p class="text-xs text-gray-500">
                        Please confirm this action
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeleteDepartmentModal()"
                class="text-gray-400
                       hover:text-gray-700"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Modal Body -->

        <div class="px-5 py-5">

            <p class="text-sm text-gray-600 leading-6">

                Are you sure you want to delete

                <span
                    id="deleteDepartmentName"
                    class="font-semibold text-gray-900"></span>

                ?

            </p>

            <p class="mt-2 text-xs text-gray-500">
                This action cannot be undone.
            </p>

        </div>


        <!-- Modal Footer -->

        <div
            class="flex justify-end gap-3
                   border-t border-gray-200
                   px-5 py-4">

            <button
                type="button"
                onclick="closeDeleteDepartmentModal()"
                class="px-4 py-2 text-sm
                       border border-gray-300
                       bg-white text-gray-700
                       hover:bg-gray-50">
                Cancel
            </button>


            <form
                method="POST"
                id="deleteDepartmentForm"
                class="inline">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                                $csrf,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="delete">

                <input
                    type="hidden"
                    name="department_id"
                    id="deleteDepartmentId"
                    value="">

                <button
                    type="submit"
                    class="px-4 py-2 text-sm
                           bg-red-600 text-white
                           hover:bg-red-700">
                    <i class="fas fa-trash mr-1"></i>
                    Delete
                </button>

            </form>

        </div>

    </div>

</div>


<script>
    /*
|--------------------------------------------------------------------------
| Open Delete Modal
|--------------------------------------------------------------------------
*/

    function openDeleteDepartmentModal(
        departmentId,
        departmentName
    ) {

        const modal =
            document.getElementById(
                'deleteDepartmentModal'
            );

        const idInput =
            document.getElementById(
                'deleteDepartmentId'
            );

        const nameElement =
            document.getElementById(
                'deleteDepartmentName'
            );

        if (
            !modal ||
            !idInput ||
            !nameElement
        ) {
            return;
        }

        idInput.value = departmentId;

        nameElement.textContent =
            departmentName;

        modal.classList.add('show');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'overflow-hidden'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Close Delete Modal
    |--------------------------------------------------------------------------
    */

    function closeDeleteDepartmentModal() {

        const modal =
            document.getElementById(
                'deleteDepartmentModal'
            );

        if (!modal) {
            return;
        }

        modal.classList.remove('show');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'overflow-hidden'
        );

        document.getElementById(
            'deleteDepartmentId'
        ).value = '';

        document.getElementById(
            'deleteDepartmentName'
        ).textContent = '';
    }


    /*
    |--------------------------------------------------------------------------
    | ESC Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function(event) {

            if (event.key === 'Escape') {
                closeDeleteDepartmentModal();
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Click Outside Modal
    |--------------------------------------------------------------------------
    */

    const deleteDepartmentModal =
        document.getElementById(
            'deleteDepartmentModal'
        );

    if (deleteDepartmentModal) {

        deleteDepartmentModal.addEventListener(
            'click',
            function(event) {

                if (event.target === this) {
                    closeDeleteDepartmentModal();
                }

            }
        );
    }
</script>


<?php require_once '../../includes/footer.php'; ?>