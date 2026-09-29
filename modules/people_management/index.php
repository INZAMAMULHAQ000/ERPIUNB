<?php
$pageTitle = 'People Management';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('people_management.view')) {
    die('Access Denied');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| CSRF helper fallback
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

/*
|--------------------------------------------------------------------------
| POST - Delete Person
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $personId = (int)($_POST['person_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/people_management/index.php');
        exit;
    }

    if ($action === 'delete') {

        if (!hasPermission('people_management.delete')) {
            flash('error', 'You do not have permission to delete this person.');
            to('modules/people_management/index.php');
            exit;
        }

        if ($personId <= 0) {
            flash('error', 'Invalid person selected.');
            to('modules/people_management/index.php');
            exit;
        }

        try {

            $stmt = $db->prepare("
                SELECT
                    id,
                    first_name,
                    last_name,
                    person_code,
                    user_id
                FROM people
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$personId]);

            $person = $stmt->fetch();

            if (!$person) {
                flash('error', 'Person not found.');
                to('modules/people_management/index.php');
                exit;
            }

            /*
             * Delete person.
             *
             * If your database has foreign-key restrictions,
             * this catch block will show a friendly message.
             */
            $deleteStmt = $db->prepare("
                DELETE FROM people
                WHERE id = ?
            ");

            $deleteStmt->execute([$personId]);

            if ($deleteStmt->rowCount() > 0) {

                if (function_exists('logAudit')) {
                    logAudit(
                        $_SESSION['user_id'] ?? 0,
                        'Deleted person: ' .
                            $person['first_name'] . ' ' .
                            $person['last_name'] .
                            ' (' . $person['person_code'] . ')',
                        'people_management'
                    );
                }

                flash('success', 'Person deleted successfully.');
            } else {
                flash('error', 'Person could not be deleted.');
            }
        } catch (PDOException $e) {

            /*
             * Foreign-key constraint / database error
             */
            if ($e->getCode() === '23000') {
                flash(
                    'error',
                    'This person cannot be deleted because related records exist.'
                );
            } else {
                flash(
                    'error',
                    'Unable to delete the person. Please try again.'
                );
            }
        }

        to('modules/people_management/index.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');

$page = getPageNumber();
$perPage = 15;

/*
|--------------------------------------------------------------------------
| Allowed filters
|--------------------------------------------------------------------------
*/
$allowedCategories = [
    'employee',
    'customer',
    'contractor'
];

$allowedStatuses = [
    'active',
    'inactive'
];

if ($category !== '' && !in_array($category, $allowedCategories, true)) {
    $category = '';
}

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| Search Query
|--------------------------------------------------------------------------
*/
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {

    $where .= "
        AND (
            p.first_name LIKE ?
            OR p.last_name LIKE ?
            OR p.email LIKE ?
            OR p.person_code LIKE ?
        )
    ";

    $searchValue = "%{$search}%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($category !== '') {
    $where .= " AND p.category = ?";
    $params[] = $category;
}

if ($status !== '') {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) AS cnt
    FROM people p
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
| People
|--------------------------------------------------------------------------
*/
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT
        p.*,
        u.name AS user_name,
        u.email AS user_email,
        u.status AS user_status
    FROM people p
    LEFT JOIN users u ON p.user_id = u.id
    $where
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$people = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/
$totalActive = (int)$db->query("
    SELECT COUNT(*) AS c
    FROM people
    WHERE status = 'active'
")->fetch()['c'];

$totalInactive = (int)$db->query("
    SELECT COUNT(*) AS c
    FROM people
    WHERE status = 'inactive'
")->fetch()['c'];

$categoryCounts = $db->query("
    SELECT
        category,
        COUNT(*) AS cnt
    FROM people
    GROUP BY category
")->fetchAll();

$catMap = [];

foreach ($categoryCounts as $row) {
    $catMap[$row['category']] = (int)$row['cnt'];
}

$csrf = csrfToken();

$catLabels = [
    'employee'   => 'Employee',
    'customer'   => 'Customer',
    'contractor' => 'Contractor'
];

$catColors = [
    'employee'   => 'bg-blue-100 text-blue-800',
    'customer'   => 'bg-purple-100 text-purple-800',
    'contractor' => 'bg-orange-100 text-orange-800'
];
?>

<style>
    /* ============================================================
   FILTER VALIDATION
   ============================================================ */

    #peopleFilterForm label.error {
        display: block !important;
        width: 100%;
        margin-top: 5px !important;
        margin-bottom: 0 !important;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
        font-weight: 400;
    }

    #peopleFilterForm input.error,
    #peopleFilterForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #peopleFilterForm input.valid,
    #peopleFilterForm select.valid {
        border-color: #d1d5db !important;
        box-shadow: none !important;
    }

    #peopleFilterForm select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: none !important;
    }


    /* ============================================================
   DELETE MODAL
   ============================================================ */

    #deletePersonModal {
        display: none;
    }

    #deletePersonModal.show {
        display: flex;
    }

    #deletePersonModal .delete-modal-box {
        animation: deleteModalIn 0.15s ease-out;
    }

    @keyframes deleteModalIn {

        from {
            opacity: 0;
            transform: translateY(-8px) scale(0.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

    }


    /* ============================================================
   TABLE
   ============================================================ */

    .people-table th {
        white-space: nowrap;
    }

    .people-table td {
        vertical-align: middle;
    }
</style>


<!-- ============================================================
     PAGE HEADER
     ============================================================ -->

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            People Management
        </h1>

        <p class="text-gray-600">
            Central directory — Employees, Customers &amp; Contractors
        </p>
    </div>

    <?php if (hasPermission('people_management.create')): ?>

        <a
            href="create.php"
            class="inline-flex items-center
                   bg-blue-600 text-white
                   px-4 py-2
                   rounded
                   hover:bg-blue-700
                   transition">
            <i class="fas fa-plus mr-2"></i>
            Add Person
        </a>

    <?php endif; ?>

</div>


<!-- ============================================================
     STATISTICS
     ============================================================ -->

<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">

    <!-- Total -->
    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Total People
        </div>

        <div class="text-2xl font-bold text-gray-800">
            <?= $total ?>
        </div>

    </div>


    <!-- Active -->
    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Active
        </div>

        <div class="text-2xl font-bold text-green-600">
            <?= $totalActive ?>
        </div>

    </div>


    <!-- Employees -->
    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Employees
        </div>

        <div class="text-2xl font-bold text-blue-600">
            <?= (int)($catMap['employee'] ?? 0) ?>
        </div>

    </div>


    <!-- Customers -->
    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Customers
        </div>

        <div class="text-2xl font-bold text-purple-600">
            <?= (int)($catMap['customer'] ?? 0) ?>
        </div>

    </div>


    <!-- Contractors -->
    <div class="bg-white rounded-lg shadow p-4">

        <div class="text-sm text-gray-500">
            Contractors
        </div>

        <div class="text-2xl font-bold text-orange-600">
            <?= (int)($catMap['contractor'] ?? 0) ?>
        </div>

    </div>

</div>


<!-- ============================================================
     FILTER
     ============================================================ -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="peopleFilterForm"
        class="p-4">

        <div class="flex flex-wrap gap-4 items-start">

            <!-- Search -->
            <div class="flex-1 min-w-[220px]">

                <label
                    for="search"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Search
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Name, email, code..."
                    maxlength="100"
                    class="w-full px-3 py-2
                           border border-gray-300
                           rounded
                           focus:outline-none
                           focus:ring-1
                           focus:ring-blue-500
                           focus:border-blue-500">

            </div>


            <!-- Category -->
            <div class="w-44">

                <label
                    for="category"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Category
                </label>

                <div class="relative">

                    <select
                        id="category"
                        name="category"
                        class="w-full
                               appearance-none
                               px-3 py-2 pr-9
                               border border-gray-300
                               rounded
                               bg-white
                               focus:outline-none
                               focus:ring-1
                               focus:ring-blue-500
                               focus:border-blue-500">

                        <option value="">
                            All
                        </option>

                        <option
                            value="employee"
                            <?= $category === 'employee' ? 'selected' : '' ?>>
                            Employee
                        </option>

                        <option
                            value="customer"
                            <?= $category === 'customer' ? 'selected' : '' ?>>
                            Customer
                        </option>

                        <option
                            value="contractor"
                            <?= $category === 'contractor' ? 'selected' : '' ?>>
                            Contractor
                        </option>

                    </select>

                    <span
                        class="pointer-events-none
                               absolute
                               right-3
                               top-1/2
                               -translate-y-1/2
                               text-gray-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </span>

                </div>

            </div>


            <!-- Status -->
            <div class="w-44">

                <label
                    for="status"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Status
                </label>

                <div class="relative">

                    <select
                        id="status"
                        name="status"
                        class="w-full
                               appearance-none
                               px-3 py-2 pr-9
                               border border-gray-300
                               rounded
                               bg-white
                               focus:outline-none
                               focus:ring-1
                               focus:ring-blue-500
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
                               absolute
                               right-3
                               top-1/2
                               -translate-y-1/2
                               text-gray-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </span>

                </div>

            </div>


            <!-- Search Button -->
            <div class="pt-6">

                <button
                    type="submit"
                    class="bg-gray-600
                           text-white
                           px-4 py-2
                           rounded
                           hover:bg-gray-700
                           transition"
                    title="Search">
                    <i class="fas fa-search"></i>
                </button>

            </div>


            <!-- Clear -->
            <?php if ($search !== '' || $category !== '' || $status !== ''): ?>

                <div class="pt-6">

                    <a
                        href="index.php"
                        class="inline-flex items-center
                               bg-gray-100
                               text-gray-700
                               px-4 py-2
                               rounded
                               hover:bg-gray-200
                               transition">
                        <i class="fas fa-times mr-2"></i>
                        Clear
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </form>

</div>


<!-- ============================================================
     PEOPLE TABLE
     ============================================================ -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full people-table">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Person
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Code
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Category
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Contact
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Login
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

                <?php foreach ($people as $person): ?>

                    <?php
                    $firstName = trim($person['first_name'] ?? '');
                    $lastName  = trim($person['last_name'] ?? '');

                    $initials = '';

                    if ($firstName !== '') {
                        $initials .= strtoupper(substr($firstName, 0, 1));
                    }

                    if ($lastName !== '') {
                        $initials .= strtoupper(substr($lastName, 0, 1));
                    }

                    $personName = trim($firstName . ' ' . $lastName);

                    $categoryKey = $person['category'] ?? '';

                    $categoryLabel =
                        $catLabels[$categoryKey]
                        ?? ucfirst($categoryKey);

                    $categoryColor =
                        $catColors[$categoryKey]
                        ?? 'bg-gray-100 text-gray-800';

                    $personStatus = $person['status'] ?? 'inactive';

                    $userStatus = $person['user_status'] ?? 'inactive';

                    ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Person -->
                        <td class="px-6 py-4">

                            <div class="flex items-center">

                                <div
                                    class="h-10 w-10
                                           rounded-full
                                           bg-gray-700
                                           flex
                                           items-center
                                           justify-center
                                           text-white
                                           font-bold
                                           text-sm">
                                    <?= htmlspecialchars($initials ?: '?', ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="ml-3">

                                    <div class="font-medium text-gray-900">
                                        <?= sanitize($personName) ?>
                                    </div>

                                </div>

                            </div>

                        </td>


                        <!-- Code -->
                        <td class="px-6 py-4 text-gray-600 text-sm">

                            <?= sanitize($person['person_code'] ?? '-') ?>

                        </td>


                        <!-- Category -->
                        <td class="px-6 py-4">

                            <span
                                class="px-2 py-1
                                       text-xs
                                       rounded
                                       <?= $categoryColor ?>">
                                <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </td>


                        <!-- Contact -->
                        <td class="px-6 py-4">

                            <div class="text-sm text-gray-900">
                                <?= sanitize($person['email'] ?? '-') ?>
                            </div>

                            <div class="text-xs text-gray-500">
                                <?= sanitize($person['phone'] ?? '') ?>
                            </div>

                        </td>


                        <!-- Login -->
                        <td class="px-6 py-4">

                            <?php if (!empty($person['user_id'])): ?>

                                <span
                                    class="px-2 py-1
                                           text-xs
                                           rounded
                                           <?= $userStatus === 'active'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-yellow-100 text-yellow-800' ?>">

                                    <i class="fas fa-check-circle mr-1"></i>

                                    <?= ucfirst($userStatus) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-xs text-gray-400">
                                    No login
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Status -->
                        <td class="px-6 py-4">

                            <span
                                class="px-2 py-1
                                       text-xs
                                       rounded
                                       <?= $personStatus === 'active'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800' ?>">
                                <?= ucfirst($personStatus) ?>
                            </span>

                        </td>


                        <!-- Actions -->
                        <td class="px-6 py-4 text-right whitespace-nowrap">

                            <!-- View -->
                            <a
                                href="view.php?id=<?= (int)$person['id'] ?>"
                                class="text-blue-600 hover:text-blue-900 mr-3"
                                title="View">
                                <i class="fas fa-eye"></i>
                            </a>


                            <!-- Edit -->
                            <?php if (hasPermission('people_management.edit')): ?>

                                <a
                                    href="edit.php?id=<?= (int)$person['id'] ?>"
                                    class="text-yellow-600 hover:text-yellow-900 mr-3"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                            <?php endif; ?>


                            <!-- Delete -->
                            <?php if (hasPermission('people_management.delete')): ?>

                                <button
                                    type="button"
                                    class="text-red-600 hover:text-red-900"
                                    title="Delete"
                                    onclick="openDeleteModal(
                                        <?= (int)$person['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $personName,
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


                <?php if (empty($people)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="px-6 py-10 text-center text-gray-500">

                            <div class="text-gray-400 mb-2">
                                <i class="fas fa-users text-3xl"></i>
                            </div>

                            No people found

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- ============================================================
     PAGINATION
     ============================================================ -->

<?= paginationLinks($pagination, '?') ?>


<!-- ============================================================
     DELETE CONFIRMATION MODAL
     ============================================================ -->

<div
    id="deletePersonModal"
    class="fixed inset-0
           z-50
           items-center
           justify-center
           bg-black/50
           px-4">

    <div
        class="delete-modal-box
               bg-white
               w-full
               max-w-md
               shadow-xl">

        <!-- Modal Header -->
        <div
            class="flex
                   items-center
                   justify-between
                   px-5
                   py-4
                   bg-gray-100
                   border-b
                   border-gray-200">

            <div class="flex items-center">

                <div
                    class="w-9 h-9
                           flex
                           items-center
                           justify-center
                           bg-red-100
                           text-red-600
                           mr-3">
                    <i class="fas fa-trash"></i>
                </div>

                <h3 class="text-base font-semibold text-gray-800">
                    Delete Person
                </h3>

            </div>

            <button
                type="button"
                onclick="closeDeleteModal()"
                class="text-gray-400
                       hover:text-gray-700
                       text-lg"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Modal Body -->
        <div class="px-5 py-5">

            <p class="text-sm text-gray-700">
                Are you sure you want to delete this person?
            </p>

            <div
                id="deletePersonName"
                class="mt-3
                       px-3
                       py-2
                       bg-gray-50
                       border
                       border-gray-200
                       text-sm
                       font-semibold
                       text-gray-800"></div>

            <p class="mt-3 text-xs text-red-600">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                This action cannot be undone.
            </p>

        </div>


        <!-- Modal Footer -->
        <div
            class="flex
                   justify-end
                   gap-2
                   px-5
                   py-3
                   bg-gray-50
                   border-t
                   border-gray-200">

            <button
                type="button"
                onclick="closeDeleteModal()"
                class="px-4
                       py-2
                       text-sm
                       font-medium
                       text-gray-700
                       bg-white
                       border
                       border-gray-300
                       rounded
                       hover:bg-gray-100">
                Cancel
            </button>


            <form
                method="POST"
                id="deletePersonForm"
                class="inline">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <input
                    type="hidden"
                    name="action"
                    value="delete">

                <input
                    type="hidden"
                    name="person_id"
                    id="deletePersonId"
                    value="">

                <button
                    type="submit"
                    class="px-4
                           py-2
                           text-sm
                           font-semibold
                           text-white
                           bg-red-600
                           rounded
                           hover:bg-red-700">
                    <i class="fas fa-trash mr-1"></i>
                    Delete
                </button>

            </form>

        </div>

    </div>

</div>


<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | People Filter Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validSearch',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return /^[A-Za-z0-9@._\-\/\s]+$/.test(value);
            },
            'Please enter a valid search value.'
        );


        $('#peopleFilterForm').validate({

            ignore: [],

            rules: {

                search: {
                    maxlength: 100,
                    validSearch: true
                },

                category: {
                    required: false
                },

                status: {
                    required: false
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 100 characters.',
                    validSearch: 'Please enter a valid search value.'
                }

            },

            errorElement: 'label',

            errorClass: 'error',

            errorPlacement: function(error, element) {

                error.css({
                    display: 'block',
                    width: '100%',
                    clear: 'both',
                    marginTop: '5px'
                });

                /*
                 * Dropdown validation message:
                 * message will appear BELOW the select box.
                 */
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


    /*
    |--------------------------------------------------------------------------
    | Delete Modal
    |--------------------------------------------------------------------------
    */

    function openDeleteModal(personId, personName) {

        const modal = document.getElementById('deletePersonModal');
        const idInput = document.getElementById('deletePersonId');
        const nameBox = document.getElementById('deletePersonName');

        idInput.value = personId;

        nameBox.textContent = personName;

        modal.classList.add('show');

        document.body.classList.add('overflow-hidden');

    }


    function closeDeleteModal() {

        const modal = document.getElementById('deletePersonModal');

        modal.classList.remove('show');

        document.body.classList.remove('overflow-hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | Close when clicking outside modal
    |--------------------------------------------------------------------------
    */

    document.getElementById('deletePersonModal').addEventListener(
        'click',
        function(event) {

            if (event.target === this) {
                closeDeleteModal();
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ESC key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {
            closeDeleteModal();
        }

    });
</script>


<?php require_once '../../includes/footer.php'; ?>