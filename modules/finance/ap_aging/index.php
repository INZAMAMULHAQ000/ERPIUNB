<?php
$pageTitle = 'AP Aging Report';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('aging_reports_ap.view')) {
    die('Access Denied');
}

$db = getDB();

$vendor_id = (int)($_GET['vendor_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Vendors
|--------------------------------------------------------------------------
*/
$vendors = $db->query("
    SELECT 
        id,
        vendor_code,
        vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| AP Aging Query
|--------------------------------------------------------------------------
*/
$where = "
    WHERE vb.balance_amount > 0
      AND vb.status IN ('approved', 'partially_paid', 'overdue')
";

$params = [];

if ($vendor_id) {
    $where .= " AND vb.vendor_id = ?";
    $params[] = $vendor_id;
}

$stmt = $db->prepare("
    SELECT
        vb.id,
        vb.bill_number,
        vb.bill_date,
        vb.due_date,
        vb.total_amount,
        vb.paid_amount,
        vb.balance_amount,
        vb.status,
        v.vendor_name,
        v.vendor_code,
        DATEDIFF(CURDATE(), vb.due_date) AS days_overdue
    FROM vendor_bills vb
    JOIN vendors v
        ON vb.vendor_id = v.id
    $where
    ORDER BY vb.due_date ASC
");

$stmt->execute($params);

$bills = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Bucket Bills
|--------------------------------------------------------------------------
*/
$buckets = [
    'current' => [
        'label' => 'Current (0–30 days)',
        'total' => 0,
        'bills' => []
    ],

    '31_60' => [
        'label' => '31–60 Days Overdue',
        'total' => 0,
        'bills' => []
    ],

    '61_90' => [
        'label' => '61–90 Days Overdue',
        'total' => 0,
        'bills' => []
    ],

    '90_plus' => [
        'label' => '90+ Days Overdue',
        'total' => 0,
        'bills' => []
    ],
];

$grandTotal = 0;

foreach ($bills as $b) {

    $dueDate = strtotime($b['due_date']);
    $today = time();

    $daysDiff = floor(
        ($today - $dueDate) / (60 * 60 * 24)
    );

    if ($daysDiff <= 30) {

        $buckets['current']['bills'][] = $b;
        $buckets['current']['total'] +=
            (float)$b['balance_amount'];
    } elseif ($daysDiff <= 60) {

        $buckets['31_60']['bills'][] = $b;
        $buckets['31_60']['total'] +=
            (float)$b['balance_amount'];
    } elseif ($daysDiff <= 90) {

        $buckets['61_90']['bills'][] = $b;
        $buckets['61_90']['total'] +=
            (float)$b['balance_amount'];
    } else {

        $buckets['90_plus']['bills'][] = $b;
        $buckets['90_plus']['total'] +=
            (float)$b['balance_amount'];
    }

    $grandTotal += (float)$b['balance_amount'];
}

/*
|--------------------------------------------------------------------------
| Check No Data
|--------------------------------------------------------------------------
*/
$noDataFound =
    empty($buckets['current']['bills']) &&
    empty($buckets['31_60']['bills']) &&
    empty($buckets['61_90']['bills']) &&
    empty($buckets['90_plus']['bills']);

/*
|--------------------------------------------------------------------------
| Bucket Colors
|--------------------------------------------------------------------------
*/
$bucketColors = [
    'current' => 'border-green-400',
    '31_60' => 'border-yellow-400',
    '61_90' => 'border-orange-400',
    '90_plus' => 'border-red-500',
];
?>

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            AP Aging Report
        </h1>

        <p class="text-gray-600">
            Aged accounts payable as of <?= date('M d, Y') ?>
        </p>

    </div>

    <a
        href="<?= url('modules/finance/index.php') ?>"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

        <i class="fas fa-arrow-left mr-2"></i>

        Back to Finance

    </a>

</div>


<!-- Filter -->
<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        class="p-4 flex flex-wrap gap-4 items-end">

        <div class="w-64">

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Vendor

            </label>

            <select
                name="vendor_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    All Vendors
                </option>

                <?php foreach ($vendors as $v): ?>

                    <option
                        value="<?= (int)$v['id'] ?>"
                        <?= $vendor_id == $v['id'] ? 'selected' : '' ?>>

                        <?= sanitize($v['vendor_name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-search mr-2"></i>

            Filter

        </button>


        <?php if ($vendor_id): ?>

            <a
                href="index.php"
                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">

                Clear

            </a>

        <?php endif; ?>

    </form>

</div>


<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $bucketColors['current'] ?>">

        <p class="text-xs text-gray-500">
            Current / 0–30 Days
        </p>

        <p class="text-xl font-bold">
            <?= formatMoney($buckets['current']['total']) ?>
        </p>

        <p class="text-xs text-gray-400">
            <?= count($buckets['current']['bills']) ?> bills
        </p>

    </div>


    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $bucketColors['31_60'] ?>">

        <p class="text-xs text-gray-500">
            31-60 Days Overdue
        </p>

        <p class="text-xl font-bold">
            <?= formatMoney($buckets['31_60']['total']) ?>
        </p>

        <p class="text-xs text-gray-400">
            <?= count($buckets['31_60']['bills']) ?> bills
        </p>

    </div>


    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $bucketColors['61_90'] ?>">

        <p class="text-xs text-gray-500">
            61-90 Days Overdue
        </p>

        <p class="text-xl font-bold">
            <?= formatMoney($buckets['61_90']['total']) ?>
        </p>

        <p class="text-xs text-gray-400">
            <?= count($buckets['61_90']['bills']) ?> bills
        </p>

    </div>


    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $bucketColors['90_plus'] ?>">

        <p class="text-xs text-gray-500">
            90+ Days Overdue
        </p>

        <p class="text-xl font-bold">
            <?= formatMoney($buckets['90_plus']['total']) ?>
        </p>

        <p class="text-xs text-gray-400">
            <?= count($buckets['90_plus']['bills']) ?> bills
        </p>

    </div>

</div>


<!-- Detail Tables -->
<?php foreach ($buckets as $key => $bucket): ?>

    <?php if (!empty($bucket['bills'])): ?>

        <div class="bg-white rounded-lg shadow mb-6">

            <div class="px-4 py-3 border-b flex justify-between items-center">

                <h3 class="font-semibold text-gray-800">
                    <?= $bucket['label'] ?>
                </h3>

                <span class="text-sm font-bold">
                    <?= formatMoney($bucket['total']) ?>
                </span>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Vendor
                            </th>

                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Bill #
                            </th>

                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Date
                            </th>

                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Due
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Total
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Paid
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Balance
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Days Overdue
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-200">

                        <?php foreach ($bucket['bills'] as $b): ?>

                            <tr class="hover:bg-gray-50">

                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <?= sanitize($b['vendor_name']) ?>
                                </td>

                                <td class="px-4 py-2 text-sm font-mono">
                                    <?= sanitize($b['bill_number']) ?>
                                </td>

                                <td class="px-4 py-2 text-sm whitespace-nowrap">
                                    <?= date('M d, Y', strtotime($b['bill_date'])) ?>
                                </td>

                                <td class="px-4 py-2 text-sm whitespace-nowrap">
                                    <?= date('M d, Y', strtotime($b['due_date'])) ?>
                                </td>

                                <td class="px-4 py-2 text-sm text-right font-mono">
                                    <?= formatMoney((float)$b['total_amount']) ?>
                                </td>

                                <td class="px-4 py-2 text-sm text-right font-mono">
                                    <?= formatMoney((float)$b['paid_amount']) ?>
                                </td>

                                <td class="px-4 py-2 text-sm text-right font-mono text-red-600">
                                    <?= formatMoney((float)$b['balance_amount']) ?>
                                </td>

                                <td class="px-4 py-2 text-sm text-right font-mono">

                                    <?= $b['days_overdue'] > 0
                                        ? $b['days_overdue']
                                        : '--'
                                    ?>

                                </td>

                                <td class="px-4 py-2 text-right">

                                    <a
                                        href="../vendor_bills/view.php?id=<?= (int)$b['id'] ?>"
                                        class="text-blue-600 hover:underline text-sm">

                                        View

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

<?php endforeach; ?>


<!-- Normal No Data Area -->
<?php if ($noDataFound): ?>

    <div class="bg-white rounded-lg shadow p-6 text-center">

        <i class="fas fa-check-circle text-4xl mb-3 text-green-400"></i>

        <p class="text-gray-500">
            No outstanding bills found.
        </p>

    </div>

<?php endif; ?>


<!-- ========================================================= -->
<!-- No Data Information Modal -->
<!-- ========================================================= -->

<div
    id="noDataModal"
    class="fixed inset-0 z-50 hidden">

    <!-- Overlay -->
    <div
        id="noDataModalOverlay"
        class="absolute inset-0 bg-black bg-opacity-50">
    </div>


    <!-- Modal Wrapper -->
    <div
        class="relative min-h-screen flex items-center justify-center p-4">

        <div
            id="noDataModalBox"
            class="relative bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all">

            <!-- Header -->
            <div class="px-6 py-4 border-b flex items-center justify-between">

                <div class="flex items-center gap-3">

                    <div
                        class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">

                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>

                    </div>

                    <h3 class="text-lg font-semibold text-gray-800">
                        No Data Found
                    </h3>

                </div>


                <button
                    type="button"
                    id="closeNoDataModal"
                    class="text-gray-400 hover:text-gray-600 text-xl">

                    <i class="fas fa-times"></i>

                </button>

            </div>


            <!-- Body -->
            <div class="px-6 py-5">

                <p class="text-gray-600 text-sm leading-6">

                    No outstanding bills were found for the selected vendor.

                </p>

                <?php if ($vendor_id): ?>

                    <p class="text-gray-500 text-sm mt-2">

                        Please select another vendor or click
                        <strong>Clear</strong>
                        to view all outstanding bills.

                    </p>

                <?php else: ?>

                    <p class="text-gray-500 text-sm mt-2">

                        There are currently no outstanding accounts payable
                        bills available for this report.

                    </p>

                <?php endif; ?>

            </div>


            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-end">

                <button
                    type="button"
                    id="okNoDataModal"
                    class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">

                    OK

                </button>

            </div>

        </div>

    </div>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const noDataFound = <?= $noDataFound ? 'true' : 'false' ?>;

        const vendorSelected = <?= $vendor_id > 0 ? 'true' : 'false' ?>;

        const modal = document.getElementById('noDataModal');

        const closeButton = document.getElementById('closeNoDataModal');

        const okButton = document.getElementById('okNoDataModal');

        const overlay = document.getElementById('noDataModalOverlay');


        /*
        |--------------------------------------------------------------------------
        | Open Modal
        |--------------------------------------------------------------------------
        */
        function openNoDataModal() {

            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');

            document.body.classList.add('overflow-hidden');

        }


        /*
        |--------------------------------------------------------------------------
        | Close Modal
        |--------------------------------------------------------------------------
        */
        function closeNoDataModal() {

            if (!modal) {
                return;
            }

            modal.classList.add('hidden');

            document.body.classList.remove('overflow-hidden');

        }


        /*
        |--------------------------------------------------------------------------
        | Button Events
        |--------------------------------------------------------------------------
        */
        if (closeButton) {

            closeButton.addEventListener(
                'click',
                closeNoDataModal
            );

        }


        if (okButton) {

            okButton.addEventListener(
                'click',
                closeNoDataModal
            );

        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                closeNoDataModal
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ESC Key
        |--------------------------------------------------------------------------
        */
        document.addEventListener('keydown', function(event) {

            if (event.key === 'Escape') {

                closeNoDataModal();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Show Modal After Filter
        |--------------------------------------------------------------------------
        */
        if (noDataFound && vendorSelected) {

            setTimeout(function() {

                openNoDataModal();

            }, 150);

        }

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>