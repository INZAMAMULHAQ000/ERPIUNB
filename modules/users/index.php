<?php
$pageTitle = 'Users';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/
if (!hasPermission('users.view')) {
    die('Access Denied');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| CSRF Helper
|--------------------------------------------------------------------------
| Use existing application functions if already available.
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
| Delete User
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/users/index.php');
        exit;
    }

    if ($action === 'delete') {

        if (!hasPermission('users.delete')) {
            flash('error', 'You do not have permission to delete users.');
            to('modules/users/index.php');
            exit;
        }

        if ($userId <= 0) {
            flash('error', 'Invalid user.');
            to('modules/users/index.php');
            exit;
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Get User
            |--------------------------------------------------------------------------
            */
            $userStmt = $db->prepare("
                SELECT id, name, email
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $userStmt->execute([$userId]);
            $deleteUser = $userStmt->fetch();

            if (!$deleteUser) {
                flash('error', 'User not found or already deleted.');
                to('modules/users/index.php');
                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent deleting current logged-in user
            |--------------------------------------------------------------------------
            */
            if (
                isset($_SESSION['user_id'])
                && (int)$_SESSION['user_id'] === $userId
            ) {
                flash('error', 'You cannot delete your own account.');
                to('modules/users/index.php');
                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Soft Delete
            |--------------------------------------------------------------------------
            | Users table already uses deleted_at in the listing query,
            | so use soft delete instead of permanently deleting the record.
            |--------------------------------------------------------------------------
            */
            $deleteStmt = $db->prepare("
                UPDATE users
                SET deleted_at = NOW()
                WHERE id = ?
                  AND deleted_at IS NULL
            ");

            $deleteStmt->execute([$userId]);

            if ($deleteStmt->rowCount() > 0) {

                if (function_exists('logAudit')) {
                    logAudit(
                        $_SESSION['user_id'] ?? null,
                        'Deleted user: ' . $deleteUser['name'] .
                            ' (' . $deleteUser['email'] . ')',
                        'users'
                    );
                }

                flash(
                    'success',
                    'User deleted successfully.',
                    'success'
                );
            } else {

                flash(
                    'error',
                    'User could not be deleted.'
                );
            }
        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Foreign Key / Database Error
            |--------------------------------------------------------------------------
            */
            if ($e->getCode() == 23000) {

                flash(
                    'error',
                    'This user cannot be deleted because related records exist.'
                );
            } else {

                flash(
                    'error',
                    'An error occurred while deleting the user.'
                );
            }
        }

        to('modules/users/index.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'active',
    'inactive',
    'suspended'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$page = getPageNumber();
$perPage = 10;

/*
|--------------------------------------------------------------------------
| Query Conditions
|--------------------------------------------------------------------------
*/
$where = "WHERE deleted_at IS NULL";
$params = [];

if ($search !== '') {

    $where .= " AND (
        name LIKE ?
        OR email LIKE ?
    )";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status !== '') {

    $where .= " AND status = ?";
    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| Total Records
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) AS cnt
    FROM users
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
| Users
|--------------------------------------------------------------------------
*/
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT *
    FROM users
    $where
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$users = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
$csrf = csrfToken();
?>

<style>
    /*
|--------------------------------------------------------------------------
| Users Page
|--------------------------------------------------------------------------
*/

    .users-page .filter-card {
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .users-page .table-header {
        background: #f8fafc;
    }

    .users-page select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none !important;
    }

    .users-page input.error,
    .users-page select.error {
        border-color: #ef4444 !important;
    }

    .users-page input.valid,
    .users-page select.valid {
        border-color: #d1d5db !important;
    }

    .users-page label.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
        font-weight: 400;
    }

    /*
|--------------------------------------------------------------------------
| Delete Modal
|--------------------------------------------------------------------------
*/

    #deleteUserModal {
        display: none;
    }

    #deleteUserModal.show {
        display: flex;
    }

    .delete-modal-box {
        width: 100%;
        max-width: 440px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.20);
    }

    .delete-modal-icon {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fee2e2;
        color: #dc2626;
    }

    #deleteUserModal .modal-backdrop {
        background: rgba(15, 23, 42, 0.55);
    }
</style>

<div class="users-page">

    <!-- ============================================================
         PAGE HEADER
    ============================================================ -->
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

        <div>
            <h1 class="text-xl font-semibold text-gray-800">
                Users
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage system users
            </p>
        </div>

        <?php if (hasPermission('users.create')): ?>

            <a
                href="create.php"
                class="inline-flex items-center justify-center
                       bg-blue-600 text-white
                       px-4 py-2
                       text-sm font-medium
                       hover:bg-blue-700
                       transition">
                <i class="fas fa-plus mr-2"></i>
                Add User
            </a>

        <?php endif; ?>

    </div>


    <!-- ============================================================
         FILTER
    ============================================================ -->
    <div class="filter-card mb-5">

        <form
            method="GET"
            id="userFilterForm"
            class="p-4">

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">

                <!-- Search -->
                <div class="md:col-span-7">

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
                        placeholder="Search by name or email..."
                        class="w-full h-10
                               px-3
                               border border-gray-300
                               text-sm text-gray-700
                               focus:outline-none
                               focus:border-blue-500
                               focus:ring-1
                               focus:ring-blue-500">

                </div>


                <!-- Status -->
                <div class="md:col-span-3">

                    <label
                        for="status"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Status
                    </label>

                    <div class="relative">

                        <select
                            id="status"
                            name="status"
                            class="w-full h-10
                                   appearance-none
                                   bg-white
                                   px-3 pr-9
                                   border border-gray-300
                                   text-sm text-gray-700
                                   cursor-pointer
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
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

                            <option
                                value="suspended"
                                <?= $status === 'suspended' ? 'selected' : '' ?>>
                                Suspended
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
                <div class="md:col-span-2">

                    <button
                        type="submit"
                        class="w-full h-10
                               bg-gray-700
                               text-white
                               text-sm
                               font-medium
                               hover:bg-gray-800
                               transition">
                        <i class="fas fa-search mr-1"></i>
                        Search
                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- ============================================================
         TABLE
    ============================================================ -->
    <div class="bg-white border border-gray-200 overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="table-header border-b border-gray-200">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Name
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Email
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Roles
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Status
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Created
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-200">

                    <?php foreach ($users as $user): ?>

                        <?php
                        /*
                        |--------------------------------------------------------------------------
                        | Roles
                        |--------------------------------------------------------------------------
                        */
                        $roleStmt = $db->prepare("
                            SELECT r.name
                            FROM roles r
                            JOIN user_roles ur
                                ON r.id = ur.role_id
                            WHERE ur.user_id = ?
                            ORDER BY r.name
                        ");

                        $roleStmt->execute([
                            $user['id']
                        ]);

                        $roles = $roleStmt->fetchAll();

                        /*
                        |--------------------------------------------------------------------------
                        | Status
                        |--------------------------------------------------------------------------
                        */
                        $statusColors = [
                            'active'    => 'bg-green-100 text-green-800',
                            'inactive'  => 'bg-gray-100 text-gray-800',
                            'suspended' => 'bg-red-100 text-red-800'
                        ];

                        $userStatusClass =
                            $statusColors[$user['status']]
                            ?? 'bg-gray-100 text-gray-800';

                        /*
                        |--------------------------------------------------------------------------
                        | User Name
                        |--------------------------------------------------------------------------
                        */
                        $userName = $user['name'] ?? '';
                        ?>

                        <tr class="hover:bg-gray-50">

                            <!-- Name -->
                            <td class="px-5 py-3">

                                <div class="font-medium text-gray-900 text-sm">
                                    <?= sanitize($userName) ?>
                                </div>

                            </td>


                            <!-- Email -->
                            <td class="px-5 py-3 text-sm text-gray-600">

                                <?= sanitize($user['email']) ?>

                            </td>


                            <!-- Roles -->
                            <td class="px-5 py-3">

                                <?php if (!empty($roles)): ?>

                                    <?php foreach ($roles as $role): ?>

                                        <span
                                            class="inline-block
                                                   bg-blue-100
                                                   text-blue-800
                                                   text-xs
                                                   px-2 py-1
                                                   mr-1 mb-1">
                                            <?= sanitize($role['name']) ?>
                                        </span>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span class="text-xs text-gray-400">
                                        No role
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Status -->
                            <td class="px-5 py-3">

                                <span
                                    class="inline-block
                                           px-2 py-1
                                           text-xs
                                           <?= $userStatusClass ?>">
                                    <?= ucfirst(sanitize($user['status'])) ?>
                                </span>

                            </td>


                            <!-- Created -->
                            <td class="px-5 py-3 text-sm text-gray-500">

                                <?= !empty($user['created_at'])
                                    ? date('M d, Y', strtotime($user['created_at']))
                                    : '-'
                                ?>

                            </td>


                            <!-- Actions -->
                            <td class="px-5 py-3 text-right whitespace-nowrap">

                                <?php if (hasPermission('users.view')): ?>

                                    <a
                                        href="view.php?id=<?= (int)$user['id'] ?>"
                                        class="text-blue-600 hover:text-blue-900 mr-3"
                                        title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                <?php endif; ?>


                                <?php if (hasPermission('users.edit')): ?>

                                    <a
                                        href="edit.php?id=<?= (int)$user['id'] ?>"
                                        class="text-yellow-600 hover:text-yellow-900 mr-3"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                <?php endif; ?>


                                <?php if (hasPermission('users.delete')): ?>

                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-900"
                                        title="Delete"
                                        onclick="openDeleteUserModal(
                                            <?= (int)$user['id'] ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $userName,
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


                    <?php if (empty($users)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="px-5 py-10 text-center text-gray-500">
                                <div class="text-sm">
                                    No users found
                                </div>
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
    <div class="mt-4">

        <?= paginationLinks($pagination, '?') ?>

    </div>

</div>


<!-- ================================================================
     DELETE USER MODAL
================================================================ -->
<div
    id="deleteUserModal"
    class="fixed inset-0 z-50 items-center justify-center">

    <div
        class="modal-backdrop absolute inset-0"
        onclick="closeDeleteUserModal()"></div>


    <div
        class="delete-modal-box relative z-10"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteUserTitle">

        <!-- Header -->
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">

            <div class="flex items-center gap-3">

                <div class="delete-modal-icon">
                    <i class="fas fa-trash"></i>
                </div>

                <div>

                    <h2
                        id="deleteUserTitle"
                        class="text-base font-semibold text-gray-800">
                        Delete User
                    </h2>

                    <p class="text-xs text-gray-500 mt-0.5">
                        Confirmation required
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeleteUserModal()"
                class="text-gray-400 hover:text-gray-700"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Body -->
        <div class="px-5 py-5">

            <p class="text-sm text-gray-700">

                Are you sure you want to delete

                <span
                    id="deleteUserName"
                    class="font-semibold text-gray-900"></span>

                ?

            </p>

            <p class="text-xs text-gray-500 mt-2">
                The user will be removed from the active user list.
            </p>

        </div>


        <!-- Footer -->
        <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">

            <button
                type="button"
                onclick="closeDeleteUserModal()"
                class="px-4 py-2
                       border border-gray-300
                       bg-white
                       text-gray-700
                       text-sm
                       hover:bg-gray-100">
                Cancel
            </button>


            <form
                method="POST"
                id="deleteUserForm">

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
                    name="user_id"
                    id="deleteUserId"
                    value="">

                <button
                    type="submit"
                    class="px-4 py-2
                           bg-red-600
                           text-white
                           text-sm
                           font-medium
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
| Delete User Modal
|--------------------------------------------------------------------------
*/

    function openDeleteUserModal(userId, userName) {

        const modal = document.getElementById('deleteUserModal');
        const userIdInput = document.getElementById('deleteUserId');
        const userNameElement = document.getElementById('deleteUserName');

        if (!modal || !userIdInput || !userNameElement) {
            return;
        }

        userIdInput.value = userId;
        userNameElement.textContent = userName;

        modal.classList.add('show');

        document.body.classList.add('overflow-hidden');
    }


    function closeDeleteUserModal() {

        const modal = document.getElementById('deleteUserModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('show');

        document.body.classList.remove('overflow-hidden');

        document.getElementById('deleteUserId').value = '';
        document.getElementById('deleteUserName').textContent = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Escape Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            const modal = document.getElementById('deleteUserModal');

            if (modal && modal.classList.contains('show')) {
                closeDeleteUserModal();
            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | jQuery Validation
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {

        if (
            typeof $ === 'undefined' ||
            typeof $.validator === 'undefined'
        ) {
            return;
        }

        $('#userFilterForm').validate({

            rules: {

                search: {
                    maxlength: 100
                },

                status: {
                    validStatus: true
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 100 characters.'
                },

                status: {
                    validStatus: 'Invalid status selected.'
                }

            },

            errorPlacement: function(error, element) {

                error.css({
                    display: 'block',
                    width: '100%',
                    clear: 'both',
                    marginTop: '4px'
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

            submitHandler: function(form) {
                form.submit();
            }

        });


        /*
        |--------------------------------------------------------------------------
        | Custom Status Validation
        |--------------------------------------------------------------------------
        */

        if (!$.validator.methods.validStatus) {

            $.validator.addMethod(
                'validStatus',
                function(value, element) {

                    if (value === '') {
                        return true;
                    }

                    return [
                        'active',
                        'inactive',
                        'suspended'
                    ].includes(value);

                },
                'Invalid status selected.'
            );

        }

    });
</script>

<?php require_once '../../includes/footer.php'; ?>