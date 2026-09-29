<?php
$pageTitle = 'Journal Entries';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('finance.journal.view')) {
    die('Access Denied');
}

$db = getDB();

// --- Post Handler ---
if (
    isset($_GET['post']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    hasPermission('finance.journal.post')
) {
    $journalId = (int) $_GET['post'];

    require_once __DIR__ . '/../posting_engine/PostingEngine.php';

    $result = PostingEngine::post(
        $journalId,
        $_SESSION['user_id']
    );

    if ($result['success']) {
        flash('success', $result['message'], 'success');
    } else {
        flash('error', $result['message'], 'error');
    }

    to('modules/finance/journal_entries/index.php');
}


// --- Filters ---
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($statusFilter !== '') {
    $where .= " AND je.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $where .= " AND je.voucher_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where .= " AND je.voucher_date <= ?";
    $params[] = $dateTo;
}

if ($search) {
    $where .= "
        AND (
            je.voucher_no LIKE ?
            OR je.reference_no LIKE ?
            OR je.description LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}


// --- Count Total ---
$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM journal_entries je
    $where
");

$countStmt->execute($params);

$total = $countStmt->fetch()['cnt'];

$pagination = paginate(
    $total,
    $perPage,
    $page
);


// --- Fetch Journals ---
$queryParams = array_merge(
    $params,
    [$perPage, $pagination['offset']]
);

$stmt = $db->prepare("
    SELECT
        je.*,
        vt.voucher_name,
        u.name AS creator_name,

        (
            SELECT COALESCE(SUM(debit_amount), 0)
            FROM journal_entry_details
            WHERE journal_entry_id = je.id
        ) AS debit_total,

        (
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM journal_entry_details
            WHERE journal_entry_id = je.id
        ) AS credit_total

    FROM journal_entries je

    JOIN voucher_types vt
        ON je.voucher_type_id = vt.id

    JOIN users u
        ON je.created_by = u.id

    $where

    ORDER BY je.created_at DESC

    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);

$journals = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Journal Entries
        </h1>

        <p class="text-gray-600">
            View and manage journal entries
        </p>

    </div>


    <div class="flex gap-2">

        <a
            href="<?= url('modules/finance/index.php') ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Finance
        </a>


        <?php if (hasPermission('finance.journal.create')): ?>

            <a
                href="create.php"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>
                New Journal Entry
            </a>

        <?php endif; ?>

    </div>

</div>


<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        class="p-4 flex flex-wrap gap-4 items-end">

        <!-- Search -->
        <div class="flex-1 min-w-[180px]">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Voucher No, Reference, Description..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Status -->
        <div class="w-36">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All
                </option>

                <option
                    value="draft"
                    <?= $statusFilter === 'draft' ? 'selected' : '' ?>>
                    Draft
                </option>

                <option
                    value="posted"
                    <?= $statusFilter === 'posted' ? 'selected' : '' ?>>
                    Posted
                </option>

                <option
                    value="reversed"
                    <?= $statusFilter === 'reversed' ? 'selected' : '' ?>>
                    Reversed
                </option>

            </select>

        </div>


        <!-- Date From -->
        <div class="w-44">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Date From
            </label>

            <input
                type="date"
                name="date_from"
                id="date_from"
                value="<?= sanitize($dateFrom) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Date To -->
        <div class="w-44">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Date To
            </label>

            <input
                type="date"
                name="date_to"
                id="date_to"
                value="<?= sanitize($dateTo) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Search Button -->
        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>


        <!-- Clear Filter -->
        <?php if ($search || $statusFilter !== '' || $dateFrom || $dateTo): ?>

            <a
                href="index.php"
                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
                <i class="fas fa-times"></i>
            </a>

        <?php endif; ?>

    </form>

</div>


<!-- Journals Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Date
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Voucher No
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Voucher Type
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Description
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Debit Total
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Credit Total
                    </th>

                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                        Status
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Created By
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-gray-200">

                <?php foreach ($journals as $j): ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Date -->
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">

                            <?= date(
                                'M d, Y',
                                strtotime($j['voucher_date'])
                            ) ?>

                        </td>


                        <!-- Voucher No -->
                        <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900 whitespace-nowrap">

                            <?= sanitize($j['voucher_no']) ?>

                        </td>


                        <!-- Voucher Type -->
                        <td class="px-4 py-3 text-sm text-gray-600">

                            <?= sanitize($j['voucher_name']) ?>

                        </td>


                        <!-- Description -->
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">

                            <?= sanitize($j['description'] ?? '') ?>

                        </td>


                        <!-- Debit Total -->
                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap">

                            <?= formatNumber($j['debit_total']) ?>

                        </td>


                        <!-- Credit Total -->
                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap">

                            <?= formatNumber($j['credit_total']) ?>

                        </td>


                        <!-- Status -->
                        <td class="px-4 py-3 text-center whitespace-nowrap">

                            <?php if ($j['status'] === 'draft'): ?>

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Draft
                                </span>

                            <?php elseif ($j['status'] === 'posted'): ?>

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Posted
                                </span>

                            <?php elseif ($j['status'] === 'reversed'): ?>

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Reversed
                                </span>

                            <?php else: ?>

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <?= sanitize($j['status']) ?>
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Created By -->
                        <td class="px-4 py-3 text-sm text-gray-600">

                            <?= sanitize($j['creator_name'] ?? '') ?>

                        </td>


                        <!-- Actions -->
                        <td class="px-4 py-3 text-right whitespace-nowrap">

                            <!-- View / Edit -->
                            <a
                                href="edit.php?id=<?= $j['id'] ?>"
                                class="text-blue-600 hover:text-blue-900 mr-2"
                                title="<?= $j['status'] === 'draft' ? 'Edit' : 'View Details' ?>">

                                <i
                                    class="fas <?= $j['status'] === 'draft'
                                                    ? 'fa-edit'
                                                    : 'fa-eye' ?>"></i>

                            </a>


                            <?php if ($j['status'] === 'draft'): ?>

                                <!-- Edit -->
                                <?php if (hasPermission('finance.journal.edit')): ?>

                                    <a
                                        href="edit.php?id=<?= $j['id'] ?>"
                                        class="text-yellow-600 hover:text-yellow-900 mr-2"
                                        title="Edit">

                                        <i class="fas fa-pen"></i>

                                    </a>

                                <?php endif; ?>


                                <!-- Post -->
                                <?php if (hasPermission('finance.journal.post')): ?>

                                    <form
                                        method="POST"
                                        action="?post=<?= $j['id'] ?>"
                                        style="display:inline"
                                        onsubmit="return confirm('Post journal <?= sanitize(addslashes($j['voucher_no'])) ?>? This action cannot be undone.')">

                                        <button
                                            type="submit"
                                            class="text-green-600 hover:text-green-900"
                                            title="Post Journal">

                                            <i class="fas fa-check-circle"></i>

                                        </button>

                                    </form>

                                <?php endif; ?>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <!-- No Records -->
                <?php if (empty($journals)): ?>

                    <tr>

                        <td
                            colspan="9"
                            class="px-4 py-8 text-center text-gray-500">

                            <i class="fas fa-journal-whills text-4xl mb-3 text-gray-300"></i>

                            <p>
                                No journal entries found
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    function updateToDate() {

        const fromDate = $('#date_from').val();
        const $toDate = $('#date_to');

        if (fromDate) {

            $toDate.attr('min', fromDate);

            if ($toDate.val() && $toDate.val() < fromDate) {
                $toDate.val('');
            }

        } else {

            $toDate.removeAttr('min');
            $toDate.val('');
        }
    }

    // Page load
    updateToDate();

    // From Date change
    $('#date_from').on('change', function () {

        updateToDate();

        // To Date validation refresh
        $('#date_to').valid();
    });

    // To Date change
    $('#date_to').on('change', function () {

        const fromDate = $('#date_from').val();
        const toDate = $(this).val();

        if (!fromDate) {
            $(this).val('');
            return;
        }

        if (toDate < fromDate) {
            $(this).val('');
        }
    });

});
</script>
<!-- Pagination -->
<?= paginationLinks($pagination, '?') ?>


<?php require_once '../../../includes/footer.php'; ?>