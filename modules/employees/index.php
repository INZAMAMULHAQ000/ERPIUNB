<?php
$pageTitle = 'Employees';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';
require_once __DIR__ . '/document_service.php';

if (!hasPermission('employees.view')) {
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
| Delete Employee
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $employeeId = (int)($_POST['employee_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/employees/index.php');
        exit;
    }

    if ($action === 'delete') {

        if (!hasPermission('employees.delete')) {
            flash('error', 'You do not have permission to delete employees.');
            to('modules/employees/index.php');
            exit;
        }

        if ($employeeId <= 0) {
            flash('error', 'Invalid employee selected.');
            to('modules/employees/index.php');
            exit;
        }

        try {

            /*
             * Check employee exists
             */
            $checkStmt = $db->prepare("
                SELECT id, first_name, last_name
                FROM employees
                WHERE id = ?
                LIMIT 1
            ");
            $checkStmt->execute([$employeeId]);

            $employee = $checkStmt->fetch();

            if (!$employee) {
                flash('error', 'Employee not found.');
                to('modules/employees/index.php');
                exit;
            }

            /*
             * Check whether deleted_at column exists.
             * If available, perform soft delete.
             */
            $columnStmt = $db->query("
                SHOW COLUMNS FROM employees LIKE 'deleted_at'
            ");

            $hasDeletedAt = (bool)$columnStmt->fetch();

            if ($hasDeletedAt) {

                $deleteStmt = $db->prepare("
                    UPDATE employees
                    SET deleted_at = NOW()
                    WHERE id = ?
                ");

                $deleteStmt->execute([$employeeId]);
            } else {

                /*
                 * Fallback to hard delete
                 */
                $deleteStmt = $db->prepare("
                    DELETE FROM employees
                    WHERE id = ?
                ");

                $deleteStmt->execute([$employeeId]);
            }

            flash(
                'success',
                'Employee "' .
                    trim($employee['first_name'] . ' ' . $employee['last_name']) .
                    '" deleted successfully.'
            );
        } catch (PDOException $e) {

            /*
             * Foreign key constraint
             */
            if ((string)$e->getCode() === '23000') {

                flash(
                    'error',
                    'This employee cannot be deleted because related records exist.'
                );
            } else {

                flash(
                    'error',
                    'Unable to delete the employee. Please try again.'
                );
            }
        }

        to('modules/employees/index.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$department_id = $_GET['department_id'] ?? '';
$designation_id = $_GET['designation_id'] ?? '';
$status = $_GET['status'] ?? '';
$employment_type = $_GET['employment_type'] ?? '';

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
            e.first_name LIKE ?
            OR e.last_name LIKE ?
            OR e.email LIKE ?
            OR e.employee_code LIKE ?
            OR e.passport_number LIKE ?
            OR e.visa_number LIKE ?
            OR e.emirates_id_number LIKE ?
            OR e.nationality LIKE ?
            OR e.contract_number LIKE ?
        )
    ";

    $searchValue = "%{$search}%";

    // Document numbers are stored without separators, so searching
    // "784-1234567890-12" has to match the digits-only value.
    $alphaValue = preg_replace('/[^A-Za-z0-9]/', '', $search);
    $digitValue = preg_replace('/\D/', '', $search);
    $passportValue = $alphaValue !== '' ? '%' . strtoupper($alphaValue) . '%' : $searchValue;
    $emiratesValue = $digitValue !== '' ? '%' . $digitValue . '%' : $searchValue;

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $passportValue;
    $params[] = $passportValue;
    $params[] = $emiratesValue;
    $params[] = $searchValue;
    // Contract numbers keep their dashes and slashes, so match them verbatim.
    $params[] = $searchValue;
}

/*
|--------------------------------------------------------------------------
| Department
|--------------------------------------------------------------------------
*/
if ($department_id !== '') {

    $where .= " AND e.department_id = ?";
    $params[] = (int)$department_id;
}

/*
|--------------------------------------------------------------------------
| Designation
|--------------------------------------------------------------------------
*/
if ($designation_id !== '') {

    $where .= " AND e.designation_id = ?";
    $params[] = (int)$designation_id;
}

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/
if ($status !== '') {

    $where .= " AND e.status = ?";
    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| Employment Type
|--------------------------------------------------------------------------
*/
if ($employment_type !== '') {

    $where .= " AND e.employment_type = ?";
    $params[] = $employment_type;
}

/*
|--------------------------------------------------------------------------
| Check deleted_at column
|--------------------------------------------------------------------------
*/
$deletedColumnStmt = $db->query("
    SHOW COLUMNS FROM employees LIKE 'deleted_at'
");

$hasDeletedAt = (bool)$deletedColumnStmt->fetch();

if ($hasDeletedAt) {
    $where .= " AND e.deleted_at IS NULL";
}

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) AS cnt
    FROM employees e
    $where
");

$countStmt->execute($params);

$total = (int)$countStmt->fetch()['cnt'];

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$pagination = paginate($total, $perPage, $page);

/*
|--------------------------------------------------------------------------
| Employee List
|--------------------------------------------------------------------------
*/
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT
        e.*,
        d.department_name,
        desig.designation_name
    FROM employees e
    LEFT JOIN departments d
        ON e.department_id = d.id
    LEFT JOIN designations desig
        ON e.designation_id = desig.id

    $where

    ORDER BY e.first_name ASC

    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$employees = $stmt->fetchAll();

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

/*
|--------------------------------------------------------------------------
| Designations
|--------------------------------------------------------------------------
*/
$designations = $db->query("
    SELECT *
    FROM designations
    WHERE status = 'active'
    ORDER BY designation_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Labels
|--------------------------------------------------------------------------
*/
$typeLabels = [
    'full_time' => 'Full Time',
    'part_time' => 'Part Time',
    'contract'  => 'Contract',
    'intern'    => 'Intern'
];

$statusColors = [
    'active'     => 'bg-green-100 text-green-800',
    'inactive'   => 'bg-gray-100 text-gray-800',
    'terminated' => 'bg-red-100 text-red-800',
    'resigned'   => 'bg-yellow-100 text-yellow-800'
];
?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Delete Modal
    |--------------------------------------------------------------------------
    */

    #deleteEmployeeModal {
        display: none;
    }

    #deleteEmployeeModal.show {
        display: flex;
    }

    /*
    |--------------------------------------------------------------------------
    | Modal Animation
    |--------------------------------------------------------------------------
    */

    #deleteEmployeeModal .modal-box {
        transform: scale(0.96);
        opacity: 0;
        transition: all 0.15s ease;
    }

    #deleteEmployeeModal.show .modal-box {
        transform: scale(1);
        opacity: 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Select
    |--------------------------------------------------------------------------
    */

    .employee-filter-select {
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

    @media (max-width: 900px) {
        .employee-table-wrapper {
            overflow-x: auto;
        }

        .employee-table {
            min-width: 900px;
        }
    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Employees
        </h1>

        <p class="text-gray-600">
            Manage employee records
        </p>
    </div>

    <?php if (hasPermission('employees.create')): ?>

        <a
            href="create.php"
            class="bg-blue-600 text-white px-4 py-2 rounded
                   hover:bg-blue-700 transition">

            <i class="fas fa-plus mr-2"></i>
            Add Employee

        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     FILTERS
========================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="employeeFilterForm"
        class="p-4 flex flex-wrap gap-4 items-end">

        <!-- Search -->

        <div class="flex-1 min-w-[200px]">

            <label
                for="employee_search"
                class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                id="employee_search"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Name, email, code..."
                class="w-full px-3 py-2 border border-gray-300 rounded
                       focus:outline-none
                       focus:ring-1 focus:ring-blue-500
                       focus:border-blue-500">

        </div>


        <!-- Department -->

        <div class="w-40">

            <label
                for="department_id"
                class="block text-sm font-medium text-gray-700 mb-1">
                Department
            </label>

            <div class="relative">

                <select
                    id="department_id"
                    name="department_id"
                    class="employee-filter-select w-full px-3 py-2 pr-8
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-1 focus:ring-blue-500
                           focus:border-blue-500">

                    <option value="">All</option>

                    <?php foreach ($departments as $dept): ?>

                        <option
                            value="<?= (int)$dept['id'] ?>"
                            <?= (string)$department_id === (string)$dept['id'] ? 'selected' : '' ?>>
                            <?= sanitize($dept['department_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                

            </div>

        </div>


        <!-- Designation -->

        <div class="w-40">

            <label
                for="designation_id"
                class="block text-sm font-medium text-gray-700 mb-1">
                Designation
            </label>

            <div class="relative">

                <select
                    id="designation_id"
                    name="designation_id"
                    class="employee-filter-select w-full px-3 py-2 pr-8
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-1 focus:ring-blue-500
                           focus:border-blue-500">

                    <option value="">All</option>

                    <?php foreach ($designations as $designation): ?>

                        <option
                            value="<?= (int)$designation['id'] ?>"
                            <?= (string)$designation_id === (string)$designation['id'] ? 'selected' : '' ?>>
                            <?= sanitize($designation['designation_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

               

            </div>

        </div>


        <!-- Status -->

        <div class="w-40">

            <label
                for="status"
                class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <div class="relative">

                <select
                    id="status"
                    name="status"
                    class="employee-filter-select w-full px-3 py-2 pr-8
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-1 focus:ring-blue-500
                           focus:border-blue-500">

                    <option value="">All</option>

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

                    <option
                        value="terminated"
                        <?= $status === 'terminated' ? 'selected' : '' ?>>
                        Terminated
                    </option>

                    <option
                        value="resigned"
                        <?= $status === 'resigned' ? 'selected' : '' ?>>
                        Resigned
                    </option>

                </select>


            </div>

        </div>


        <!-- Search Button -->

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
     EMPLOYEE TABLE
========================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="employee-table-wrapper">

        <table class="w-full employee-table">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Employee
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Code
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Identification
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Department
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Designation
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Type
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium
                               text-gray-500 uppercase">
                        Status
                    </th>

                    <th class="px-6 py-3 text-right text-xs font-medium
                               text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-gray-200">

                <?php foreach ($employees as $emp): ?>

                    <?php
                    $employeeName = trim(
                        ($emp['first_name'] ?? '') . ' ' .
                            ($emp['last_name'] ?? '')
                    );

                    $initial = strtoupper(
                        substr(
                            $emp['first_name'] ?? 'E',
                            0,
                            1
                        )
                    );

                    $employeeType =
                        $typeLabels[$emp['employment_type'] ?? '']
                        ?? ($emp['employment_type'] ?? '-');

                    $employeeStatus =
                        $emp['status'] ?? '';

                    $statusClass =
                        $statusColors[$employeeStatus]
                        ?? 'bg-gray-100 text-gray-800';
                    ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Employee -->

                        <td class="px-6 py-4">

                            <div class="flex items-center">

                                <?php if (!empty($emp['photo_path'])): ?>
                                <img src="<?= attachmentFileUrl($emp['photo_path']) ?>"
                                     alt="<?= sanitize($employeeName) ?>"
                                     class="h-10 w-10 rounded-full object-cover border">
                                <?php else: ?>
                                <div
                                    class="h-10 w-10 rounded-full
                                           bg-blue-500
                                           flex items-center justify-center
                                           text-white font-bold">
                                    <?= sanitize($initial) ?>
                                </div>
                                <?php endif; ?>

                                <div class="ml-3">

                                    <div
                                        class="font-medium text-gray-900">
                                        <?= sanitize($employeeName) ?>
                                    </div>

                                    <div class="text-sm text-gray-500">
                                        <?= sanitize($emp['email'] ?? '') ?>
                                    </div>

                                </div>

                            </div>

                        </td>


                        <!-- Code -->

                        <td class="px-6 py-4 text-gray-600">
                            <?= sanitize($emp['employee_code'] ?? '-') ?>
                        </td>


                        <!-- Identification -->

                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">
                                <?= sanitize($emp['nationality'] ?? '') ?: '-' ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                Passport: <?= sanitize($emp['passport_number'] ?? '') ?: '-' ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                Emirates ID: <?= sanitize(employeeFormatEmiratesId($emp['emirates_id_number'] ?? '')) ?: '-' ?>
                            </div>
                        </td>


                        <!-- Department -->

                        <td class="px-6 py-4 text-gray-600">
                            <?= sanitize($emp['department_name'] ?? '-') ?>
                        </td>


                        <!-- Designation -->

                        <td class="px-6 py-4 text-gray-600">
                            <?= sanitize($emp['designation_name'] ?? '-') ?>
                        </td>


                        <!-- Type -->

                        <td class="px-6 py-4">

                            <span class="text-sm text-gray-700">
                                <?= sanitize($employeeType) ?>
                            </span>

                        </td>


                        <!-- Status -->

                        <td class="px-6 py-4">

                            <span
                                class="px-2 py-1 text-xs rounded
                                       <?= $statusClass ?>">
                                <?= sanitize(ucfirst($employeeStatus)) ?>
                            </span>

                        </td>


                        <!-- Actions -->

                        <td class="px-6 py-4 text-right">

                            <!-- View -->

                            <a
                                href="view.php?id=<?= (int)$emp['id'] ?>"
                                class="text-blue-600 hover:text-blue-900 mr-2"
                                title="View">
                                <i class="fas fa-eye"></i>
                            </a>


                            <!-- Edit -->

                            <?php if (hasPermission('employees.edit')): ?>

                                <a
                                    href="edit.php?id=<?= (int)$emp['id'] ?>"
                                    class="text-yellow-600
                                           hover:text-yellow-900 mr-2"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                            <?php endif; ?>


                            <!-- Delete -->

                            <?php if (hasPermission('employees.delete')): ?>

                                <button
                                    type="button"
                                    class="text-red-600 hover:text-red-900"
                                    title="Delete"
                                    onclick="openDeleteModal(
                                        <?= (int)$emp['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $employeeName,
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

                <?php if (empty($employees)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="px-6 py-8 text-center text-gray-500">
                            No employees found
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

<?= paginationLinks($pagination, 'index.php') ?>


<!-- =========================================================
     DELETE CONFIRMATION MODAL
========================================================= -->

<div
    id="deleteEmployeeModal"
    class="fixed inset-0 z-50 items-center justify-center
           bg-black/40 px-4"
    aria-hidden="true">

    <div
        class="modal-box w-full max-w-md bg-white shadow-xl
               border border-gray-200"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteEmployeeTitle">

        <!-- Header -->

        <div
            class="flex items-center justify-between
                   border-b border-gray-200 px-5 py-4">

            <div class="flex items-center gap-3">

                <div
                    class="h-9 w-9 flex items-center justify-center
                           bg-red-100 text-red-600">
                    <i class="fas fa-trash"></i>
                </div>

                <div>

                    <h3
                        id="deleteEmployeeTitle"
                        class="text-base font-semibold text-gray-800">
                        Delete Employee
                    </h3>

                    <p class="text-xs text-gray-500">
                        Please confirm this action
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeleteModal()"
                class="text-gray-400 hover:text-gray-700"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Body -->

        <div class="px-5 py-5">

            <p class="text-sm text-gray-600 leading-6">

                Are you sure you want to delete

                <span
                    id="deleteEmployeeName"
                    class="font-semibold text-gray-900"></span>

                ?

            </p>

            <p class="mt-2 text-xs text-gray-500">
                This action cannot be undone.
            </p>

        </div>


        <!-- Footer -->

        <div
            class="flex justify-end gap-3
                   border-t border-gray-200 px-5 py-4">

            <button
                type="button"
                onclick="closeDeleteModal()"
                class="px-4 py-2 text-sm border border-gray-300
                       bg-white text-gray-700
                       hover:bg-gray-50">
                Cancel
            </button>


            <form
                method="POST"
                id="deleteEmployeeForm"
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
                    name="employee_id"
                    id="deleteEmployeeId"
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
| Delete Modal
|--------------------------------------------------------------------------
*/

    function openDeleteModal(employeeId, employeeName) {

        const modal = document.getElementById('deleteEmployeeModal');
        const idInput = document.getElementById('deleteEmployeeId');
        const nameElement = document.getElementById('deleteEmployeeName');

        if (!modal || !idInput || !nameElement) {
            return;
        }

        idInput.value = employeeId;
        nameElement.textContent = employeeName;

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('overflow-hidden');
    }


    function closeDeleteModal() {

        const modal = document.getElementById('deleteEmployeeModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('overflow-hidden');

        document.getElementById('deleteEmployeeId').value = '';
        document.getElementById('deleteEmployeeName').textContent = '';
    }


    /*
    |--------------------------------------------------------------------------
    | ESC Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {
            closeDeleteModal();
        }

    });


    /*
    |--------------------------------------------------------------------------
    | Click Outside Modal
    |--------------------------------------------------------------------------
    */

    document.getElementById('deleteEmployeeModal')
        .addEventListener('click', function(event) {

            if (event.target === this) {
                closeDeleteModal();
            }

        });
</script>


<?php require_once '../../includes/footer.php'; ?>