<?php
$pageTitle = 'Roles';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('roles.view')) {
    die('Access Denied');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| CSRF Helper
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
| Delete Role
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/roles/index.php');
        exit;
    }

    if ($action === 'delete') {

        if (!hasPermission('roles.delete')) {
            flash('error', 'You do not have permission to delete roles.');
            to('modules/roles/index.php');
            exit;
        }

        if ($roleId <= 0) {
            flash('error', 'Invalid role.');
            to('modules/roles/index.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Role 1 / Super Admin Protection
        |--------------------------------------------------------------------------
        */
        if ($roleId <= 1) {
            flash('error', 'The default administrator role cannot be deleted.');
            to('modules/roles/index.php');
            exit;
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Role
            |--------------------------------------------------------------------------
            */

            $roleStmt = $db->prepare("
                SELECT id, name
                FROM roles
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $roleStmt->execute([$roleId]);

            $deleteRole = $roleStmt->fetch();

            if (!$deleteRole) {
                flash('error', 'Role not found or already deleted.');
                to('modules/roles/index.php');
                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Check Assigned Users
            |--------------------------------------------------------------------------
            */

            $userCheck = $db->prepare("
                SELECT COUNT(*) AS cnt
                FROM user_roles
                WHERE role_id = ?
            ");

            $userCheck->execute([$roleId]);

            $assignedUsers = (int)$userCheck->fetch()['cnt'];

            if ($assignedUsers > 0) {
                flash(
                    'error',
                    'This role cannot be deleted because it is assigned to '
                        . $assignedUsers . ' user(s).'
                );

                to('modules/roles/index.php');
                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Soft Delete
            |--------------------------------------------------------------------------
            */

            $deleteStmt = $db->prepare("
                UPDATE roles
                SET deleted_at = NOW()
                WHERE id = ?
                  AND deleted_at IS NULL
            ");

            $deleteStmt->execute([$roleId]);

            if ($deleteStmt->rowCount() > 0) {

                if (function_exists('logAudit')) {
                    logAudit(
                        $_SESSION['user_id'] ?? null,
                        'Deleted role: ' . $deleteRole['name'],
                        'roles'
                    );
                }

                flash(
                    'success',
                    'Role deleted successfully.',
                    'success'
                );
            } else {

                flash(
                    'error',
                    'Role could not be deleted.'
                );
            }
        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                flash(
                    'error',
                    'This role cannot be deleted because related records exist.'
                );
            } else {

                flash(
                    'error',
                    'An error occurred while deleting the role.'
                );
            }
        }

        to('modules/roles/index.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$where = "WHERE deleted_at IS NULL";
$params = [];

if ($search !== '') {

    $where .= " AND name LIKE ?";
    $params[] = "%{$search}%";
}

/*
|--------------------------------------------------------------------------
| Get Roles
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM roles
    $where
    ORDER BY created_at DESC
");

$stmt->execute($params);

$roles = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

$csrf = csrfToken();
?>

<style>
    /*
|--------------------------------------------------------------------------
| Roles Page
|--------------------------------------------------------------------------
*/

    .roles-page .filter-card {
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .roles-page .table-header {
        background: #f8fafc;
    }

    .roles-page input.error {
        border-color: #ef4444 !important;
    }

    .roles-page input.valid {
        border-color: #d1d5db !important;
    }

    .roles-page label.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
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

    #deleteRoleModal {
        display: none;
    }

    #deleteRoleModal.show {
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

    #deleteRoleModal .modal-backdrop {
        background: rgba(15, 23, 42, 0.55);
    }
</style>


<div class="roles-page">

    <!-- ============================================================
         PAGE HEADER
    ============================================================ -->

    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

        <div>

            <h1 class="text-xl font-semibold text-gray-800">
                Roles
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage system roles
            </p>

        </div>


        <?php if (hasPermission('roles.create')): ?>

            <a
                href="create.php"
                class="inline-flex items-center justify-center
                       bg-blue-600 text-white
                       px-4 py-2
                       text-sm font-medium
                       hover:bg-blue-700
                       transition">
                <i class="fas fa-plus mr-2"></i>
                Add Role
            </a>

        <?php endif; ?>

    </div>


    <!-- ============================================================
         SEARCH
    ============================================================ -->

    <div class="filter-card mb-5">

        <form
            method="GET"
            id="roleFilterForm"
            class="p-4">

            <div class="flex flex-col sm:flex-row gap-4 items-end">

                <div class="flex-1 w-full">

                    <label
                        for="search"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Search
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= htmlspecialchars(
                                    $search,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        placeholder="Search by role name..."
                        class="w-full h-10
                               px-3
                               border border-gray-300
                               text-sm text-gray-700
                               focus:outline-none
                               focus:border-blue-500
                               focus:ring-1
                               focus:ring-blue-500">

                </div>


                <div class="w-full sm:w-auto">

                    <button
                        type="submit"
                        class="h-10
                               px-5
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
         ROLES TABLE
    ============================================================ -->

    <div class="bg-white border border-gray-200 overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="table-header border-b border-gray-200">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Role Name
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Permissions
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Users
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

                    <?php foreach ($roles as $role): ?>

                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | Permission Count
                        |--------------------------------------------------------------------------
                        */

                        $permCount = $db->prepare("
                            SELECT COUNT(*) AS cnt
                            FROM role_permissions
                            WHERE role_id = ?
                        ");

                        $permCount->execute([
                            $role['id']
                        ]);

                        $permCnt = (int)$permCount->fetch()['cnt'];


                        /*
                        |--------------------------------------------------------------------------
                        | User Count
                        |--------------------------------------------------------------------------
                        */

                        $userCount = $db->prepare("
                            SELECT COUNT(*) AS cnt
                            FROM user_roles
                            WHERE role_id = ?
                        ");

                        $userCount->execute([
                            $role['id']
                        ]);

                        $userCnt = (int)$userCount->fetch()['cnt'];


                        $roleName = $role['name'] ?? '';

                        ?>

                        <tr class="hover:bg-gray-50">

                            <!-- Role Name -->
                            <td class="px-5 py-3">

                                <div class="font-medium text-gray-900 text-sm">
                                    <?= sanitize($roleName) ?>
                                </div>

                            </td>


                            <!-- Permissions -->
                            <td class="px-5 py-3">

                                <span
                                    class="inline-block
                                           bg-purple-100
                                           text-purple-800
                                           text-xs
                                           px-2 py-1">
                                    <?= $permCnt ?>
                                    <?= $permCnt === 1
                                        ? 'permission'
                                        : 'permissions'
                                    ?>
                                </span>

                            </td>


                            <!-- Users -->
                            <td class="px-5 py-3">

                                <span
                                    class="inline-block
                                           bg-blue-100
                                           text-blue-800
                                           text-xs
                                           px-2 py-1">
                                    <?= $userCnt ?>
                                    <?= $userCnt === 1
                                        ? 'user'
                                        : 'users'
                                    ?>
                                </span>

                            </td>


                            <!-- Created -->
                            <td class="px-5 py-3 text-sm text-gray-500">

                                <?= !empty($role['created_at'])
                                    ? date(
                                        'M d, Y',
                                        strtotime($role['created_at'])
                                    )
                                    : '-'
                                ?>

                            </td>


                            <!-- Actions -->
                            <td class="px-5 py-3 text-right whitespace-nowrap">

                                <!-- Permissions -->
                                <?php if (hasPermission('roles.edit')): ?>

                                    <a
                                        href="permissions.php?id=<?= (int)$role['id'] ?>"
                                        class="text-purple-600 hover:text-purple-900 mr-3"
                                        title="Permissions">
                                        <i class="fas fa-key"></i>
                                    </a>

                                <?php endif; ?>


                                <!-- Edit -->
                                <?php if (hasPermission('roles.edit')): ?>

                                    <a
                                        href="edit.php?id=<?= (int)$role['id'] ?>"
                                        class="text-yellow-600 hover:text-yellow-900 mr-3"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                <?php endif; ?>


                                <!-- Delete -->
                                <?php if (
                                    hasPermission('roles.delete')
                                    && (int)$role['id'] > 1
                                ): ?>

                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-900"
                                        title="Delete"
                                        onclick="openDeleteRoleModal(
                                            <?= (int)$role['id'] ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $roleName,
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


                    <?php if (empty($roles)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="px-5 py-10 text-center text-gray-500">

                                <div class="text-sm">
                                    No roles found
                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- ================================================================
     DELETE ROLE MODAL
================================================================ -->

<div
    id="deleteRoleModal"
    class="fixed inset-0 z-50 items-center justify-center">

    <!-- Backdrop -->
    <div
        class="modal-backdrop absolute inset-0"
        onclick="closeDeleteRoleModal()"></div>


    <!-- Modal -->
    <div
        class="delete-modal-box relative z-10"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteRoleTitle">

        <!-- Header -->
        <div
            class="px-5 py-4
                   border-b border-gray-200
                   flex items-center justify-between">

            <div class="flex items-center gap-3">

                <div class="delete-modal-icon">

                    <i class="fas fa-trash"></i>

                </div>


                <div>

                    <h2
                        id="deleteRoleTitle"
                        class="text-base font-semibold text-gray-800">
                        Delete Role
                    </h2>

                    <p class="text-xs text-gray-500 mt-0.5">
                        Confirmation required
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeleteRoleModal()"
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
                    id="deleteRoleName"
                    class="font-semibold text-gray-900"></span>

                ?

            </p>


            <p class="text-xs text-gray-500 mt-2">
                The role will be removed from the active roles list.
            </p>

        </div>


        <!-- Footer -->
        <div
            class="px-5 py-3
                   border-t border-gray-200
                   bg-gray-50
                   flex justify-end gap-2">

            <button
                type="button"
                onclick="closeDeleteRoleModal()"
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
                id="deleteRoleForm">

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
                    name="role_id"
                    id="deleteRoleId"
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
| Open Delete Role Modal
|--------------------------------------------------------------------------
*/

    function openDeleteRoleModal(roleId, roleName) {
        const modal = document.getElementById('deleteRoleModal');
        const roleIdInput = document.getElementById('deleteRoleId');
        const roleNameElement = document.getElementById('deleteRoleName');

        if (!modal || !roleIdInput || !roleNameElement) {
            return;
        }

        roleIdInput.value = roleId;

        roleNameElement.textContent = roleName;

        modal.classList.add('show');

        document.body.classList.add('overflow-hidden');
    }


    /*
    |--------------------------------------------------------------------------
    | Close Delete Role Modal
    |--------------------------------------------------------------------------
    */

    function closeDeleteRoleModal() {
        const modal = document.getElementById('deleteRoleModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('show');

        document.body.classList.remove('overflow-hidden');

        const roleIdInput = document.getElementById('deleteRoleId');
        const roleNameElement = document.getElementById('deleteRoleName');

        if (roleIdInput) {
            roleIdInput.value = '';
        }

        if (roleNameElement) {
            roleNameElement.textContent = '';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Escape Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            const modal = document.getElementById('deleteRoleModal');

            if (
                modal &&
                modal.classList.contains('show')
            ) {
                closeDeleteRoleModal();
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


        $('#roleFilterForm').validate({

            rules: {

                search: {
                    maxlength: 100
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 100 characters.'
                }

            },

            errorPlacement: function(error, element) {

                error.css({
                    display: 'block',
                    width: '100%',
                    clear: 'both',
                    marginTop: '4px'
                });

                error.insertAfter(element);
            },

            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>