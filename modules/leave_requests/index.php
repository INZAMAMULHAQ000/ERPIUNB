<?php
$pageTitle = 'Leave Requests';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('leaves.view')) {
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
| Leave Approve / Reject
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $leaveId = (int)($_POST['leave_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {

        flash(
            'error',
            'Invalid security token. Please try again.'
        );

        to('modules/leave_requests/index.php');
        exit;
    }

    if (
        $action === 'approve'
        || $action === 'reject'
    ) {

        if (!hasPermission('leaves.approve')) {

            flash(
                'error',
                'You do not have permission to approve or reject leave requests.'
            );

            to('modules/leave_requests/index.php');
            exit;
        }

        if ($leaveId <= 0) {

            flash(
                'error',
                'Invalid leave request selected.'
            );

            to('modules/leave_requests/index.php');
            exit;
        }

        try {

            /*
             * Check leave request
             */
            $checkStmt = $db->prepare("
                SELECT
                    id,
                    status,
                    employee_id
                FROM leave_requests
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$leaveId]);

            $leaveRequest = $checkStmt->fetch();

            if (!$leaveRequest) {

                flash(
                    'error',
                    'Leave request not found.'
                );

                to('modules/leave_requests/index.php');
                exit;
            }


            /*
             * Only pending requests can be approved/rejected
             */
            if ($leaveRequest['status'] !== 'pending') {

                flash(
                    'error',
                    'Only pending leave requests can be approved or rejected.'
                );

                to('modules/leave_requests/index.php');
                exit;
            }


            /*
             * Determine new status
             */
            $newStatus =
                $action === 'approve'
                ? 'approved'
                : 'rejected';


            /*
             * Update leave request
             */
            $updateStmt = $db->prepare("
                UPDATE leave_requests
                SET
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND status = 'pending'
            ");

            $updateStmt->execute([
                $newStatus,
                $leaveId
            ]);


            /*
             * Check whether update happened
             */
            if ($updateStmt->rowCount() === 0) {

                flash(
                    'error',
                    'The leave request could not be updated.'
                );

                to('modules/leave_requests/index.php');
                exit;
            }


            /*
             * Audit log
             */
            if (function_exists('logAudit')) {

                logAudit(
                    $_SESSION['user_id'] ?? null,
                    ucfirst($newStatus) .
                        ' leave request ID: ' .
                        $leaveId,
                    'leave_requests'
                );
            }


            /*
             * Success message
             */
            if ($newStatus === 'approved') {

                flash(
                    'success',
                    'Leave request approved successfully.'
                );
            } else {

                flash(
                    'success',
                    'Leave request rejected successfully.'
                );
            }
        } catch (PDOException $e) {

            flash(
                'error',
                'Unable to update the leave request. Please try again.'
            );
        }

        to('modules/leave_requests/index.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$employee_id = $_GET['employee_id'] ?? '';
$status = $_GET['status'] ?? '';

$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];


/*
|--------------------------------------------------------------------------
| Employee Filter
|--------------------------------------------------------------------------
*/
if ($employee_id !== '') {

    $where .= " AND lr.employee_id = ?";
    $params[] = (int)$employee_id;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
if ($status !== '') {

    $where .= " AND lr.status = ?";
    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*) AS cnt
    FROM leave_requests lr
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
| Leave Requests
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
        lr.*,
        e.first_name,
        e.last_name,
        e.employee_code,
        lt.leave_name,
        lt.leave_code

    FROM leave_requests lr

    JOIN employees e
        ON lr.employee_id = e.id

    JOIN leave_types lt
        ON lr.leave_type_id = lt.id

    $where

    ORDER BY lr.created_at DESC

    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$requests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/
$employees = $db->query("
    SELECT
        id,
        first_name,
        last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Status Colors
|--------------------------------------------------------------------------
*/
$statusColors = [
    'pending'   => 'bg-yellow-100 text-yellow-800',
    'approved'  => 'bg-green-100 text-green-800',
    'rejected'  => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-100 text-gray-800'
];

?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Select
    |--------------------------------------------------------------------------
    */

    .leave-filter-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Action Modal
    |--------------------------------------------------------------------------
    */

    #leaveActionModal {
        display: none;
    }

    #leaveActionModal.show {
        display: flex;
    }


    /*
    |--------------------------------------------------------------------------
    | Modal Animation
    |--------------------------------------------------------------------------
    */

    #leaveActionModal .modal-box {
        transform: scale(0.96);
        opacity: 0;
        transition: all 0.15s ease;
    }

    #leaveActionModal.show .modal-box {
        transform: scale(1);
        opacity: 1;
    }


    /*
    |--------------------------------------------------------------------------
    | Responsive Table
    |--------------------------------------------------------------------------
    */

    .leave-table-wrapper {
        overflow-x: auto;
    }

    .leave-table {
        min-width: 900px;
    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Leave Requests
        </h1>

        <p class="text-gray-600">
            Manage employee leave applications
        </p>

    </div>


    <?php if (hasPermission('leaves.create')): ?>

        <a
            href="<?= url('modules/leave_requests/create.php') ?>"
            class="bg-blue-600 text-white px-4 py-2 rounded
                   hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>
            New Leave Request
        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     FILTERS
========================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="leaveFilterForm"
        class="p-4 flex flex-wrap gap-4 items-end">

        <!-- Employee -->

        <div class="w-48">

            <label
                for="employee_id"
                class="block text-sm font-medium
                       text-gray-700 mb-1">
                Employee
            </label>

            <div class="relative">

                <select
                    id="employee_id"
                    name="employee_id"
                    class="leave-filter-select
                           w-full px-3 py-2 pr-8
                           border border-gray-300 rounded
                           bg-white
                           focus:outline-none
                           focus:ring-1 focus:ring-blue-500
                           focus:border-blue-500">

                    <option value="">
                        All
                    </option>

                    <?php foreach ($employees as $emp): ?>

                        <option
                            value="<?= (int)$emp['id'] ?>"
                            <?= (string)$employee_id === (string)$emp['id']
                                ? 'selected'
                                : '' ?>>
                            <?= sanitize(
                                $emp['first_name'] . ' ' . $emp['last_name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

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


        <!-- Status -->

        <div class="w-40">

            <label
                for="leaveStatus"
                class="block text-sm font-medium
                       text-gray-700 mb-1">
                Status
            </label>

            <div class="relative">

                <select
                    id="leaveStatus"
                    name="status"
                    class="leave-filter-select
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
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>>
                        Pending
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


        <!-- Search -->

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
     LEAVE REQUEST TABLE
========================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="leave-table-wrapper">

        <table class="w-full leave-table">

            <thead class="bg-gray-50">

                <tr>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Employee
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Leave Type
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        From
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        To
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Days
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

                <?php foreach ($requests as $req): ?>

                    <?php

                    $employeeName = trim(
                        ($req['first_name'] ?? '') .
                            ' ' .
                            ($req['last_name'] ?? '')
                    );

                    $leaveName =
                        $req['leave_name'] ?? '-';

                    $leaveCode =
                        $req['leave_code'] ?? '-';

                    $requestStatus =
                        $req['status'] ?? '';

                    ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Employee -->

                        <td class="px-6 py-4">

                            <div
                                class="font-medium text-gray-900">
                                <?= sanitize($employeeName) ?>
                            </div>

                            <div class="text-sm text-gray-500">

                                <?= sanitize(
                                    $req['employee_code'] ?? '-'
                                ) ?>

                            </div>

                        </td>


                        <!-- Leave Type -->

                        <td class="px-6 py-4">

                            <span class="font-medium">

                                <?= sanitize($leaveName) ?>

                            </span>

                            <span
                                class="text-gray-500 text-sm">
                                (
                                <?= sanitize($leaveCode) ?>
                                )
                            </span>

                        </td>


                        <!-- From -->

                        <td class="px-6 py-4 text-gray-600">

                            <?= !empty($req['from_date'])
                                ? date(
                                    'M d, Y',
                                    strtotime($req['from_date'])
                                )
                                : '-' ?>

                        </td>


                        <!-- To -->

                        <td class="px-6 py-4 text-gray-600">

                            <?= !empty($req['to_date'])
                                ? date(
                                    'M d, Y',
                                    strtotime($req['to_date'])
                                )
                                : '-' ?>

                        </td>


                        <!-- Days -->

                        <td class="px-6 py-4 text-gray-600">

                            <?= sanitize(
                                $req['total_days'] ?? '0'
                            ) ?>

                        </td>


                        <!-- Status -->

                        <td class="px-6 py-4">

                            <span
                                class="px-2 py-1 text-xs rounded
                                       <?= $statusColors[$requestStatus]
                                            ?? 'bg-gray-100 text-gray-800' ?>">
                                <?= sanitize(
                                    ucfirst($requestStatus)
                                ) ?>
                            </span>

                        </td>


                        <!-- Actions -->

                        <td class="px-6 py-4 text-right">

                            <!-- View -->

                            <a
                                href="<?= url(
                                            'modules/leave_requests/view.php?id='
                                                . (int)$req['id']
                                        ) ?>"
                                class="text-blue-600
                                       hover:text-blue-900 mr-2"
                                title="View">
                                <i class="fas fa-eye"></i>
                            </a>


                            <!-- Approve / Reject -->

                            <?php
                            if (
                                hasPermission('leaves.approve')
                                && $req['status'] === 'pending'
                            ):
                            ?>

                                <!-- Approve -->

                                <button
                                    type="button"
                                    class="text-green-600
                                           hover:text-green-900 mr-2"
                                    title="Approve"
                                    onclick="openLeaveActionModal(
                                        <?= (int)$req['id'] ?>,
                                        'approve',
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
                                    <i class="fas fa-check"></i>
                                </button>


                                <!-- Reject -->

                                <button
                                    type="button"
                                    class="text-red-600
                                           hover:text-red-900"
                                    title="Reject"
                                    onclick="openLeaveActionModal(
                                        <?= (int)$req['id'] ?>,
                                        'reject',
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
                                    <i class="fas fa-times"></i>
                                </button>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <!-- Empty -->

                <?php if (empty($requests)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="px-6 py-8
                                   text-center
                                   text-gray-500">
                            No leave requests found
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
     APPROVE / REJECT MODAL
========================================================= -->

<div
    id="leaveActionModal"
    class="fixed inset-0 z-50
           items-center justify-center
           bg-black/40 px-4"
    aria-hidden="true">

    <div
        class="modal-box w-full max-w-md
               bg-white shadow-xl
               border border-gray-200"
        role="dialog"
        aria-modal="true">

        <!-- Header -->

        <div
            class="flex items-center justify-between
                   border-b border-gray-200
                   px-5 py-4">

            <div class="flex items-center gap-3">

                <div
                    id="leaveActionIcon"
                    class="h-9 w-9
                           flex items-center justify-center
                           bg-blue-100 text-blue-600">
                    <i class="fas fa-check"></i>
                </div>

                <div>

                    <h3
                        id="leaveActionTitle"
                        class="text-base font-semibold
                               text-gray-800">
                        Leave Request
                    </h3>

                    <p class="text-xs text-gray-500">
                        Please confirm this action
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeLeaveActionModal()"
                class="text-gray-400
                       hover:text-gray-700"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Body -->

        <div class="px-5 py-5">

            <p
                id="leaveActionMessage"
                class="text-sm text-gray-600
                       leading-6">
            </p>

            <p
                id="leaveActionWarning"
                class="mt-2 text-xs text-gray-500">
            </p>

        </div>


        <!-- Footer -->

        <div
            class="flex justify-end gap-3
                   border-t border-gray-200
                   px-5 py-4">

            <button
                type="button"
                onclick="closeLeaveActionModal()"
                class="px-4 py-2 text-sm
                       border border-gray-300
                       bg-white text-gray-700
                       hover:bg-gray-50">
                Cancel
            </button>


            <form
                method="POST"
                id="leaveActionForm"
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
                    id="leaveAction"
                    value="">

                <input
                    type="hidden"
                    name="leave_id"
                    id="leaveId"
                    value="">

                <button
                    type="submit"
                    id="leaveActionButton"
                    class="px-4 py-2 text-sm
                           bg-green-600 text-white
                           hover:bg-green-700">
                    <i
                        id="leaveActionButtonIcon"
                        class="fas fa-check mr-1"></i>

                    <span id="leaveActionButtonText">
                        Approve
                    </span>
                </button>

            </form>

        </div>

    </div>

</div>


<script>
    /*
|--------------------------------------------------------------------------
| Open Approve / Reject Modal
|--------------------------------------------------------------------------
*/

    function openLeaveActionModal(
        leaveId,
        action,
        employeeName
    ) {

        const modal =
            document.getElementById(
                'leaveActionModal'
            );

        const leaveIdInput =
            document.getElementById(
                'leaveId'
            );

        const actionInput =
            document.getElementById(
                'leaveAction'
            );

        const title =
            document.getElementById(
                'leaveActionTitle'
            );

        const message =
            document.getElementById(
                'leaveActionMessage'
            );

        const warning =
            document.getElementById(
                'leaveActionWarning'
            );

        const button =
            document.getElementById(
                'leaveActionButton'
            );

        const buttonText =
            document.getElementById(
                'leaveActionButtonText'
            );

        const buttonIcon =
            document.getElementById(
                'leaveActionButtonIcon'
            );

        const modalIcon =
            document.getElementById(
                'leaveActionIcon'
            );


        if (!modal) {
            return;
        }


        /*
         * Set hidden values
         */
        leaveIdInput.value = leaveId;
        actionInput.value = action;


        /*
         * APPROVE
         */
        if (action === 'approve') {

            title.textContent =
                'Approve Leave Request';

            message.innerHTML =
                'Are you sure you want to approve leave request for ' +
                '<strong class="text-gray-900">' +
                escapeHtml(employeeName) +
                '</strong>?';

            warning.textContent =
                'The leave request will be marked as approved.';

            buttonText.textContent =
                'Approve';

            button.className =
                'px-4 py-2 text-sm bg-green-600 text-white hover:bg-green-700';

            buttonIcon.className =
                'fas fa-check mr-1';

            modalIcon.className =
                'h-9 w-9 flex items-center justify-center bg-green-100 text-green-600';

            modalIcon.innerHTML =
                '<i class="fas fa-check"></i>';

        }


        /*
         * REJECT
         */
        else if (action === 'reject') {

            title.textContent =
                'Reject Leave Request';

            message.innerHTML =
                'Are you sure you want to reject leave request for ' +
                '<strong class="text-gray-900">' +
                escapeHtml(employeeName) +
                '</strong>?';

            warning.textContent =
                'The leave request will be marked as rejected.';

            buttonText.textContent =
                'Reject';

            button.className =
                'px-4 py-2 text-sm bg-red-600 text-white hover:bg-red-700';

            buttonIcon.className =
                'fas fa-times mr-1';

            modalIcon.className =
                'h-9 w-9 flex items-center justify-center bg-red-100 text-red-600';

            modalIcon.innerHTML =
                '<i class="fas fa-times"></i>';
        }


        /*
         * Show modal
         */
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
    | Close Modal
    |--------------------------------------------------------------------------
    */

    function closeLeaveActionModal() {

        const modal =
            document.getElementById(
                'leaveActionModal'
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
            'leaveId'
        ).value = '';

        document.getElementById(
            'leaveAction'
        ).value = '';
    }


    /*
    |--------------------------------------------------------------------------
    | HTML Escape
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value;

        return div.innerHTML;
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
                closeLeaveActionModal();
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Click Outside Modal
    |--------------------------------------------------------------------------
    */

    const leaveActionModal =
        document.getElementById(
            'leaveActionModal'
        );

    if (leaveActionModal) {

        leaveActionModal.addEventListener(
            'click',
            function(event) {

                if (event.target === this) {
                    closeLeaveActionModal();
                }

            }
        );
    }
</script>


<?php require_once '../../includes/footer.php'; ?>