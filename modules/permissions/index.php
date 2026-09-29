<?php
$pageTitle = 'Permissions';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('permissions.view')) {
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
| Delete Permission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $permissionId = (int)($_POST['permission_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/permissions/index.php');
        exit;
    }

    if ($action === 'delete') {

        if (!hasPermission('permissions.delete')) {
            flash('error', 'You do not have permission to delete permissions.');
            to('modules/permissions/index.php');
            exit;
        }

        if ($permissionId <= 0) {
            flash('error', 'Invalid permission.');
            to('modules/permissions/index.php');
            exit;
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Permission
            |--------------------------------------------------------------------------
            */

            $permissionStmt = $db->prepare("
                SELECT id, name, module
                FROM permissions
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $permissionStmt->execute([
                $permissionId
            ]);

            $deletePermission = $permissionStmt->fetch();

            if (!$deletePermission) {
                flash(
                    'error',
                    'Permission not found or already deleted.'
                );

                to('modules/permissions/index.php');
                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Check Role Assignment
            |--------------------------------------------------------------------------
            */

            $roleCheck = $db->prepare("
                SELECT COUNT(*) AS cnt
                FROM role_permissions
                WHERE permission_id = ?
            ");

            $roleCheck->execute([
                $permissionId
            ]);

            $assignedRoles = (int)$roleCheck->fetch()['cnt'];

            if ($assignedRoles > 0) {

                flash(
                    'error',
                    'This permission cannot be deleted because it is assigned to '
                        . $assignedRoles . ' role(s).'
                );

                to('modules/permissions/index.php');
                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Soft Delete
            |--------------------------------------------------------------------------
            */

            $deleteStmt = $db->prepare("
                UPDATE permissions
                SET deleted_at = NOW()
                WHERE id = ?
                  AND deleted_at IS NULL
            ");

            $deleteStmt->execute([
                $permissionId
            ]);

            if ($deleteStmt->rowCount() > 0) {

                if (function_exists('logAudit')) {

                    logAudit(
                        $_SESSION['user_id'] ?? null,
                        'Deleted permission: '
                            . $deletePermission['name']
                            . ' (' . $deletePermission['module'] . ')',
                        'permissions'
                    );
                }

                flash(
                    'success',
                    'Permission deleted successfully.',
                    'success'
                );
            } else {

                flash(
                    'error',
                    'Permission could not be deleted.'
                );
            }
        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                flash(
                    'error',
                    'This permission cannot be deleted because related records exist.'
                );
            } else {

                flash(
                    'error',
                    'An error occurred while deleting the permission.'
                );
            }
        }

        to('modules/permissions/index.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$module = trim($_GET['module'] ?? '');

/*
|--------------------------------------------------------------------------
| Where
|--------------------------------------------------------------------------
*/

$where = "WHERE deleted_at IS NULL";
$params = [];

if ($search !== '') {

    $where .= " AND name LIKE ?";
    $params[] = "%{$search}%";
}

if ($module !== '') {

    $where .= " AND module = ?";
    $params[] = $module;
}

/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM permissions
    $where
    ORDER BY module, name
");

$stmt->execute($params);

$permissions = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Modules
|--------------------------------------------------------------------------
*/

$modules = $db->query("
    SELECT DISTINCT module
    FROM permissions
    WHERE deleted_at IS NULL
    ORDER BY module
")->fetchAll();

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
| Permissions Page
|--------------------------------------------------------------------------
*/

    .permissions-page .filter-card {
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .permissions-page .table-header {
        background: #f8fafc;
    }

    .permissions-page select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none !important;
    }

    .permissions-page input.error,
    .permissions-page select.error {
        border-color: #ef4444 !important;
    }

    .permissions-page input.valid,
    .permissions-page select.valid {
        border-color: #d1d5db !important;
    }

    .permissions-page label.error {
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

    #deletePermissionModal {
        display: none;
    }

    #deletePermissionModal.show {
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

    #deletePermissionModal .modal-backdrop {
        background: rgba(15, 23, 42, 0.55);
    }
</style>


<div class="permissions-page">

    <!-- ============================================================
         PAGE HEADER
    ============================================================ -->

    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

        <div>

            <h1 class="text-xl font-semibold text-gray-800">
                Permissions
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage system permissions
            </p>

        </div>


        <?php if (hasPermission('permissions.create')): ?>

            <a
                href="create.php"
                class="inline-flex items-center justify-center
                       bg-blue-600 text-white
                       px-4 py-2
                       text-sm font-medium
                       hover:bg-blue-700
                       transition">
                <i class="fas fa-plus mr-2"></i>
                Add Permission
            </a>

        <?php endif; ?>

    </div>


    <!-- ============================================================
         FILTER
    ============================================================ -->

    <div class="filter-card mb-5">

        <form
            method="GET"
            id="permissionFilterForm"
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
                        value="<?= htmlspecialchars(
                                    $search,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        placeholder="Search by permission name..."
                        class="w-full h-10
                               px-3
                               border border-gray-300
                               text-sm text-gray-700
                               focus:outline-none
                               focus:border-blue-500
                               focus:ring-1
                               focus:ring-blue-500">

                </div>


                <!-- Module -->
                <div class="md:col-span-3">

                    <label
                        for="module"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Module
                    </label>

                    <div class="relative">

                        <select
                            id="module"
                            name="module"
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

                            <?php foreach ($modules as $m): ?>

                                <?php
                                $moduleName = $m['module'] ?? '';
                                ?>

                                <option
                                    value="<?= htmlspecialchars(
                                                $moduleName,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                    <?= $module === $moduleName
                                        ? 'selected'
                                        : ''
                                    ?>>
                                    <?= htmlspecialchars(
                                        ucfirst($moduleName),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

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
         PERMISSIONS TABLE
    ============================================================ -->

    <div class="bg-white border border-gray-200 overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="table-header border-b border-gray-200">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Permission Name
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Module
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Assigned To Roles
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

                    <?php foreach ($permissions as $perm): ?>

                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | Role Count
                        |--------------------------------------------------------------------------
                        */

                        $roleCount = $db->prepare("
                            SELECT COUNT(*) AS cnt
                            FROM role_permissions
                            WHERE permission_id = ?
                        ");

                        $roleCount->execute([
                            $perm['id']
                        ]);

                        $roleCnt = (int)$roleCount->fetch()['cnt'];


                        $permissionName = $perm['name'] ?? '';
                        $permissionModule = $perm['module'] ?? '';

                        ?>

                        <tr class="hover:bg-gray-50">

                            <!-- Permission Name -->
                            <td class="px-5 py-3">

                                <div class="font-medium text-gray-900 text-sm">
                                    <?= sanitize($permissionName) ?>
                                </div>

                            </td>


                            <!-- Module -->
                            <td class="px-5 py-3">

                                <span
                                    class="inline-block
                                           bg-gray-100
                                           text-gray-800
                                           text-xs
                                           px-2 py-1">
                                    <?= sanitize($permissionModule) ?>
                                </span>

                            </td>


                            <!-- Roles -->
                            <td class="px-5 py-3">

                                <span
                                    class="inline-block
                                           bg-blue-100
                                           text-blue-800
                                           text-xs
                                           px-2 py-1">
                                    <?= $roleCnt ?>
                                    <?= $roleCnt === 1
                                        ? 'role'
                                        : 'roles'
                                    ?>
                                </span>

                            </td>


                            <!-- Created -->
                            <td class="px-5 py-3 text-sm text-gray-500">

                                <?= !empty($perm['created_at'])
                                    ? date(
                                        'M d, Y',
                                        strtotime($perm['created_at'])
                                    )
                                    : '-'
                                ?>

                            </td>


                            <!-- Actions -->
                            <td class="px-5 py-3 text-right whitespace-nowrap">

                                <!-- Edit -->
                                <?php if (hasPermission('permissions.edit')): ?>

                                    <a
                                        href="edit.php?id=<?= (int)$perm['id'] ?>"
                                        class="text-yellow-600 hover:text-yellow-900 mr-3"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                <?php endif; ?>


                                <!-- Delete -->
                                <?php if (
                                    hasPermission('permissions.delete')
                                    && $roleCnt === 0
                                ): ?>

                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-900"
                                        title="Delete"
                                        onclick="openDeletePermissionModal(
                                            <?= (int)$perm['id'] ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $permissionName,
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


                    <?php if (empty($permissions)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="px-5 py-10 text-center text-gray-500">

                                <div class="text-sm">
                                    No permissions found
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
     DELETE PERMISSION MODAL
================================================================ -->

<div
    id="deletePermissionModal"
    class="fixed inset-0 z-50 items-center justify-center">

    <!-- Backdrop -->
    <div
        class="modal-backdrop absolute inset-0"
        onclick="closeDeletePermissionModal()"></div>


    <!-- Modal -->
    <div
        class="delete-modal-box relative z-10"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deletePermissionTitle">

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
                        id="deletePermissionTitle"
                        class="text-base font-semibold text-gray-800">
                        Delete Permission
                    </h2>

                    <p class="text-xs text-gray-500 mt-0.5">
                        Confirmation required
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeletePermissionModal()"
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
                    id="deletePermissionName"
                    class="font-semibold text-gray-900"></span>

                ?

            </p>


            <p class="text-xs text-gray-500 mt-2">
                The permission will be removed from the active permissions list.
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
                onclick="closeDeletePermissionModal()"
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
                id="deletePermissionForm">

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
                    name="permission_id"
                    id="deletePermissionId"
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
| Open Delete Permission Modal
|--------------------------------------------------------------------------
*/

    function openDeletePermissionModal(permissionId, permissionName) {
        const modal = document.getElementById(
            'deletePermissionModal'
        );

        const permissionIdInput = document.getElementById(
            'deletePermissionId'
        );

        const permissionNameElement = document.getElementById(
            'deletePermissionName'
        );

        if (
            !modal ||
            !permissionIdInput ||
            !permissionNameElement
        ) {
            return;
        }

        permissionIdInput.value = permissionId;

        permissionNameElement.textContent = permissionName;

        modal.classList.add('show');

        document.body.classList.add('overflow-hidden');
    }


    /*
    |--------------------------------------------------------------------------
    | Close Delete Permission Modal
    |--------------------------------------------------------------------------
    */

    function closeDeletePermissionModal() {
        const modal = document.getElementById(
            'deletePermissionModal'
        );

        if (!modal) {
            return;
        }

        modal.classList.remove('show');

        document.body.classList.remove('overflow-hidden');

        const permissionIdInput = document.getElementById(
            'deletePermissionId'
        );

        const permissionNameElement = document.getElementById(
            'deletePermissionName'
        );

        if (permissionIdInput) {
            permissionIdInput.value = '';
        }

        if (permissionNameElement) {
            permissionNameElement.textContent = '';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Escape Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            const modal = document.getElementById(
                'deletePermissionModal'
            );

            if (
                modal &&
                modal.classList.contains('show')
            ) {
                closeDeletePermissionModal();
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


        $('#permissionFilterForm').validate({

            rules: {

                search: {
                    maxlength: 100
                },

                module: {
                    maxlength: 100
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 100 characters.'
                },

                module: {
                    maxlength: 'Module cannot exceed 100 characters.'
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

    });
</script>

<?php require_once '../../includes/footer.php'; ?>