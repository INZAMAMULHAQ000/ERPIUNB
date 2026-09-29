<?php
$pageTitle = 'Financial Years';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();


// =========================================================
// Activate Financial Year
// =========================================================
if (isset($_GET['activate'])) {

    if (!hasPermission('finance.year.edit')) {
        die('Access Denied');
    }

    $activateId = (int) $_GET['activate'];

    try {

        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT id
            FROM financial_years
            WHERE id = ?
        ");

        $stmt->execute([$activateId]);

        if ($stmt->fetch()) {

            // Close currently active year
            $db->exec("
                UPDATE financial_years
                SET status = 'closed'
                WHERE status = 'active'
            ");

            // Activate selected year
            $stmt2 = $db->prepare("
                UPDATE financial_years
                SET status = 'active'
                WHERE id = ?
            ");

            $stmt2->execute([$activateId]);

            logAudit(
                $_SESSION['user_id'],
                'Activated financial year ID: ' . $activateId,
                'finance'
            );

            flash(
                'success',
                'Financial year activated successfully.',
                'success'
            );
        }

        $db->commit();
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        flash(
            'error',
            'Failed to activate financial year.',
            'error'
        );
    }

    to('modules/finance/financial_year/index.php');
}


// =========================================================
// Close Financial Year
// =========================================================
if (isset($_GET['close'])) {

    if (!hasPermission('finance.year.edit')) {
        die('Access Denied');
    }

    $closeId = (int) $_GET['close'];

    $stmt = $db->prepare("
        SELECT status
        FROM financial_years
        WHERE id = ?
    ");

    $stmt->execute([$closeId]);

    $fy = $stmt->fetch();

    if ($fy) {

        if ($fy['status'] === 'active') {

            flash(
                'error',
                'Cannot close the active financial year. Activate another year first.',
                'error'
            );
        } else {

            $stmt2 = $db->prepare("
                UPDATE financial_years
                SET status = 'closed'
                WHERE id = ?
            ");

            $stmt2->execute([$closeId]);

            logAudit(
                $_SESSION['user_id'],
                'Closed financial year ID: ' . $closeId,
                'finance'
            );

            flash(
                'success',
                'Financial year closed successfully.',
                'success'
            );
        }
    }

    to('modules/finance/financial_year/index.php');
}


// =========================================================
// Delete Financial Year
// =========================================================
if (isset($_GET['delete'])) {

    if (!hasPermission('finance.year.edit')) {
        die('Access Denied');
    }

    $deleteId = (int) $_GET['delete'];

    $stmt = $db->prepare("
        SELECT status
        FROM financial_years
        WHERE id = ?
    ");

    $stmt->execute([$deleteId]);

    $fy = $stmt->fetch();

    if ($fy) {

        if ($fy['status'] === 'active') {

            flash(
                'error',
                'Cannot delete the active financial year.',
                'error'
            );
        } else {

            $stmt2 = $db->prepare("
                DELETE FROM financial_years
                WHERE id = ?
                AND status = 'closed'
            ");

            $stmt2->execute([$deleteId]);

            if ($stmt2->rowCount()) {

                logAudit(
                    $_SESSION['user_id'],
                    'Deleted financial year ID: ' . $deleteId,
                    'finance'
                );

                flash(
                    'success',
                    'Financial year deleted successfully.',
                    'success'
                );
            }
        }
    }

    to('modules/finance/financial_year/index.php');
}


// =========================================================
// Search & Pagination
// =========================================================
$search = $_GET['search'] ?? '';

$page = getPageNumber();

$perPage = 10;

$where = "WHERE 1=1";

$params = [];

if ($search !== '') {

    $where .= " AND year_name LIKE ?";

    $params[] = "%$search%";
}


// Count
$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM financial_years
    $where
");

$countStmt->execute($params);

$total = $countStmt->fetch()['cnt'];


// Pagination
$pagination = paginate(
    $total,
    $perPage,
    $page
);


// Get Financial Years
$queryParams = array_merge(
    $params,
    [
        $perPage,
        $pagination['offset']
    ]
);

$stmt = $db->prepare("
    SELECT *
    FROM financial_years
    $where
    ORDER BY created_at DESC
    LIMIT ?
    OFFSET ?
");

$stmt->execute($queryParams);

$years = $stmt->fetchAll();

?>

<!-- =======================================================
     Page Header
======================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Financial Years
        </h1>

        <p class="text-gray-600">
            Manage financial year periods
        </p>

    </div>


    <div class="flex gap-2">

        <a href="<?= url('modules/finance/index.php') ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

            <i class="fas fa-arrow-left mr-2"></i>

            Back to Finance

        </a>


        <?php if (hasPermission('finance.year.create')): ?>

            <a href="create.php"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">

                <i class="fas fa-plus mr-2"></i>

                Add Financial Year

            </a>

        <?php endif; ?>

    </div>

</div>


<!-- =======================================================
     Search
======================================================= -->

<div class="bg-white rounded-lg shadow mb-6">

    <form method="GET"
        class="p-4 flex gap-4 items-end">

        <div class="flex-1 min-w-[200px]">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Search by year name..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

            <i class="fas fa-search"></i>

        </button>


        <?php if ($search): ?>

            <a
                href="index.php"
                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">

                <i class="fas fa-times"></i>

            </a>

        <?php endif; ?>

    </form>

</div>


<!-- =======================================================
     Financial Years Table
======================================================= -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">

            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Year Name
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Start Date
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    End Date
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Default
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Actions
                </th>

            </tr>

        </thead>


        <tbody class="divide-y divide-gray-200">

            <?php foreach ($years as $fy): ?>

                <?php
                $yearName = htmlspecialchars(
                    $fy['year_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

                <tr class="hover:bg-gray-50">

                    <!-- Year Name -->
                    <td class="px-6 py-4 font-medium text-gray-900">

                        <?= sanitize($fy['year_name']) ?>

                    </td>


                    <!-- Start Date -->
                    <td class="px-6 py-4 text-gray-600">

                        <?= date(
                            'M d, Y',
                            strtotime($fy['start_date'])
                        ) ?>

                    </td>


                    <!-- End Date -->
                    <td class="px-6 py-4 text-gray-600">

                        <?= date(
                            'M d, Y',
                            strtotime($fy['end_date'])
                        ) ?>

                    </td>


                    <!-- Status -->
                    <td class="px-6 py-4">

                        <?php if ($fy['status'] === 'active'): ?>

                            <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 font-medium">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800 font-medium">
                                Closed
                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- Default -->
                    <td class="px-6 py-4">

                        <?php if ($fy['is_default']): ?>

                            <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800 font-medium">
                                Default
                            </span>

                        <?php else: ?>

                            <span class="text-gray-400">
                                -
                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- Actions -->
                    <td class="px-6 py-4 text-right">

                        <?php if (hasPermission('finance.year.edit')): ?>

                            <!-- Edit -->
                            <a
                                href="edit.php?id=<?= $fy['id'] ?>"
                                class="text-yellow-600 hover:text-yellow-900 mr-2"
                                title="Edit">

                                <i class="fas fa-edit"></i>

                            </a>

                        <?php endif; ?>


                        <?php if (hasPermission('finance.year.edit')): ?>

                            <?php if ($fy['status'] === 'active'): ?>

                                <!-- Close -->
                                <a
                                    href="?close=<?= $fy['id'] ?>"
                                    class="text-orange-600 hover:text-orange-900 mr-2"
                                    title="Close"
                                    onclick="return showConfirmMessage(
                                        'Are you sure you want to close <?= $yearName ?>?',
                                        this.href,
                                        'Close Financial Year',
                                        'orange'
                                    )">

                                    <i class="fas fa-lock"></i>

                                </a>

                            <?php else: ?>

                                <!-- Activate -->
                                <a
                                    href="?activate=<?= $fy['id'] ?>"
                                    class="text-green-600 hover:text-green-900 mr-2"
                                    title="Activate"
                                    onclick="return showConfirmMessage(
                                        'Activate <?= $yearName ?>? This will deactivate all other financial years.',
                                        this.href,
                                        'Activate Financial Year',
                                        'green'
                                    )">

                                    <i class="fas fa-check-circle"></i>

                                </a>

                            <?php endif; ?>

                        <?php endif; ?>


                        <?php if (
                            hasPermission('finance.year.edit')
                            && $fy['status'] !== 'active'
                        ): ?>

                            <!-- Delete -->
                            <a
                                href="?delete=<?= $fy['id'] ?>"
                                class="text-red-600 hover:text-red-900"
                                title="Delete"
                                onclick="return showConfirmMessage(
                                    'Are you sure you want to delete <?= $yearName ?>? This action cannot be undone.',
                                    this.href,
                                    'Delete Financial Year',
                                    'red'
                                )">

                                <i class="fas fa-trash"></i>

                            </a>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (empty($years)): ?>

                <tr>

                    <td
                        colspan="6"
                        class="px-6 py-8 text-center text-gray-500">

                        No financial years found

                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =======================================================
     Pagination
======================================================= -->

<?= paginationLinks($pagination, '?') ?>


<!-- =======================================================
     Confirmation Modal
======================================================= -->

<div
    id="financeConfirmModal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-4">

    <div
        id="financeConfirmBox"
        class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all">

        <!-- Header -->
        <div class="px-6 py-4 border-b">

            <div class="flex items-center gap-3">

                <div
                    id="financeConfirmIcon"
                    class="w-10 h-10 rounded-full flex items-center justify-center bg-yellow-100">

                    <i
                        id="financeConfirmIconClass"
                        class="fas fa-exclamation-triangle text-yellow-600">
                    </i>

                </div>


                <div>

                    <h3
                        id="financeConfirmTitle"
                        class="text-lg font-semibold text-gray-800">

                        Confirmation

                    </h3>

                </div>

            </div>

        </div>


        <!-- Message -->
        <div class="px-6 py-5">

            <p
                id="financeConfirmMessage"
                class="text-gray-600 leading-relaxed">

            </p>

        </div>


        <!-- Buttons -->
        <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">

            <button
                type="button"
                id="financeCancelBtn"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">

                Cancel

            </button>


            <button
                type="button"
                id="financeConfirmBtn"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">

                Confirm

            </button>

        </div>

    </div>

</div>


<!-- =======================================================
     Confirmation Modal JavaScript
======================================================= -->

<script>
    let financeConfirmUrl = null;


    /**
     * Show confirmation modal
     */
    function showConfirmMessage(
        message,
        url,
        title = 'Confirmation',
        type = 'blue'
    ) {

        financeConfirmUrl = url;


        const modal = document.getElementById(
            'financeConfirmModal'
        );

        const titleElement = document.getElementById(
            'financeConfirmTitle'
        );

        const messageElement = document.getElementById(
            'financeConfirmMessage'
        );

        const iconContainer = document.getElementById(
            'financeConfirmIcon'
        );

        const icon = document.getElementById(
            'financeConfirmIconClass'
        );

        const confirmButton = document.getElementById(
            'financeConfirmBtn'
        );


        // Set content
        titleElement.textContent = title;

        messageElement.textContent = message;


        // Remove previous colors
        iconContainer.className =
            'w-10 h-10 rounded-full flex items-center justify-center';

        icon.className = 'fas';

        confirmButton.className =
            'px-4 py-2 text-white rounded transition';


        // Type based styling
        if (type === 'red') {

            iconContainer.classList.add(
                'bg-red-100'
            );

            icon.classList.add(
                'fa-trash',
                'text-red-600'
            );

            confirmButton.classList.add(
                'bg-red-600',
                'hover:bg-red-700'
            );

        } else if (type === 'green') {

            iconContainer.classList.add(
                'bg-green-100'
            );

            icon.classList.add(
                'fa-check-circle',
                'text-green-600'
            );

            confirmButton.classList.add(
                'bg-green-600',
                'hover:bg-green-700'
            );

        } else if (type === 'orange') {

            iconContainer.classList.add(
                'bg-orange-100'
            );

            icon.classList.add(
                'fa-lock',
                'text-orange-600'
            );

            confirmButton.classList.add(
                'bg-orange-600',
                'hover:bg-orange-700'
            );

        } else {

            iconContainer.classList.add(
                'bg-blue-100'
            );

            icon.classList.add(
                'fa-question-circle',
                'text-blue-600'
            );

            confirmButton.classList.add(
                'bg-blue-600',
                'hover:bg-blue-700'
            );
        }


        // Show modal
        modal.classList.remove('hidden');

        modal.classList.add('flex');


        // Prevent background scrolling
        document.body.classList.add(
            'overflow-hidden'
        );


        // Prevent original link navigation
        return false;
    }


    /**
     * Cancel button
     */
    document.getElementById(
        'financeCancelBtn'
    ).addEventListener('click', function() {

        closeFinanceConfirmModal();

    });


    /**
     * Confirm button
     */
    document.getElementById(
        'financeConfirmBtn'
    ).addEventListener('click', function() {

        if (financeConfirmUrl) {

            window.location.href =
                financeConfirmUrl;

        }

    });


    /**
     * Close modal
     */
    function closeFinanceConfirmModal() {

        const modal = document.getElementById(
            'financeConfirmModal'
        );

        modal.classList.add('hidden');

        modal.classList.remove('flex');

        financeConfirmUrl = null;

        document.body.classList.remove(
            'overflow-hidden'
        );
    }


    /**
     * Close when clicking outside modal
     */
    document.getElementById(
        'financeConfirmModal'
    ).addEventListener('click', function(event) {

        if (event.target === this) {

            closeFinanceConfirmModal();

        }

    });


    /**
     * ESC key close
     */
    document.addEventListener(
        'keydown',
        function(event) {

            if (
                event.key === 'Escape' &&
                financeConfirmUrl
            ) {

                closeFinanceConfirmModal();

            }

        }
    );
</script>


<?php
require_once '../../../includes/footer.php';
?>