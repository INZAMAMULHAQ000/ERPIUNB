<?php
$pageTitle = 'Cost Audit';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';

if (!hasPermission('inventory.reports')) { die('Access Denied'); }
$db = getDB();

$companyId   = $_GET['company_id'] ?? '';
$itemId      = $_GET['item_id'] ?? '';
$warehouseId = $_GET['warehouse_id'] ?? '';
$movementType = $_GET['movement_type'] ?? '';
$dateFrom    = $_GET['date_from'] ?? '';
$dateTo      = $_GET['date_to'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 50;

$filters = [];
if ($companyId !== '')   $filters['company_id']   = (int)$companyId;
if ($itemId !== '')      $filters['item_id']      = (int)$itemId;
if ($warehouseId !== '') $filters['warehouse_id'] = (int)$warehouseId;
if ($movementType !== '') $filters['movement_type'] = $movementType;
if ($dateFrom !== '')    $filters['date_from']    = $dateFrom . ' 00:00:00';
if ($dateTo !== '')      $filters['date_to']      = $dateTo . ' 23:59:59';

$audit = InventoryEngine::getCostAudit($db, $filters, $perPage, ($page - 1) * $perPage);
$rows = $audit['rows'] ?? [];
$total = $audit['total'] ?? 0;
$totalPages = max(1, (int)ceil($total / $perPage));

$companies = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$items = $db->query("SELECT id, item_code, item_name FROM inventory_items WHERE status='active' ORDER BY item_name")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Cost Audit</h1>
    <p class="text-gray-600">Inventory valuation history — movement-level cost tracing</p>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
            <select name="company_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($filters['company_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
            <select name="warehouse_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= $w['id'] ?>" <?= ($filters['warehouse_id'] ?? '') === $w['id'] ? 'selected' : '' ?>><?= sanitize($w['warehouse_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
            <select name="item_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($items as $i): ?>
                <option value="<?= $i['id'] ?>" <?= ($filters['item_id'] ?? '') === $i['id'] ? 'selected' : '' ?>><?= sanitize($i['item_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full border rounded px-3 py-2">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
            <a href="cost_audit.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h2 class="text-lg font-semibold text-gray-800">Audit Entries (<?= number_format($total) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Movement</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty In</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty Out</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost Before</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost After</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Value Before</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Value After</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Value</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($rows as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-700"><?= date('Y-m-d H:i', strtotime($row['movement_datetime'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-900"><?= ucfirst(str_replace('_', ' ', $row['movement_type'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($row['item_code']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($row['warehouse_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= $row['qty_in'] > 0 ? number_format($row['qty_in'], 3) : '-' ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= $row['qty_out'] > 0 ? number_format($row['qty_out'], 3) : '-' ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($row['cost_before'] ?? 0, 4) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($row['cost_after'] ?? 0, 4) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($row['value_before'] ?? 0, 2) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($row['value_after'] ?? 0, 2) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-blue-600"><?= number_format($row['total_value'], 2) ?></td>
                    <td class="px-4 py-3 text-sm"><?= InventoryEngine::getStatusBadge($row['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)) { echo '<tr><td colspan="12" class="px-4 py-8 text-center text-gray-500">No audit entries found</td></tr>'; } ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex justify-between items-center">
        <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1 border rounded text-sm">Prev</a><?php endif; ?>
            <?php if ($page < $totalPages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1 border rounded text-sm">Next</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>

$(document).ready(function () {

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    | From date can be any date.
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    | Initially no restriction.
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | FROM DATE CHANGE
    |--------------------------------------------------------------------------
    */

    $('#date_from').on('change', function () {

        const fromDate = $(this).val();

        const toDate = $('#date_to').val();


        if (fromDate) {

            /*
             * To Date cannot be before From Date
             */
            $('#date_to').attr('min', fromDate);


            /*
             * If already selected To Date
             * is before From Date, clear it.
             */
            if (toDate && toDate < fromDate) {

                $('#date_to').val('');

            }

        } else {

            /*
             * Remove restriction
             */
            $('#date_to').removeAttr('min');

        }


        /*
         * Revalidate To Date
         */
        $('#date_to').valid();

    });


    /*
    |--------------------------------------------------------------------------
    | CUSTOM VALIDATION
    |--------------------------------------------------------------------------
    */

    $.validator.addMethod(
        'validDateRange',
        function (value, element) {

            /*
             * To Date is optional
             */
            if (!value) {
                return true;
            }


            const fromDate = $('#date_from').val();


            /*
             * If From Date is empty,
             * no comparison required.
             */
            if (!fromDate) {
                return true;
            }


            /*
             * To Date must be same or after From Date
             */
            return value >= fromDate;

        },
        'To Date must be on or after From Date.'
    );


    /*
    |--------------------------------------------------------------------------
    | FORM VALIDATION
    |--------------------------------------------------------------------------
    */

    $('#costAuditFilterForm').validate({

        rules: {

            date_from: {
                date: true
            },

            date_to: {
                date: true,
                validDateRange: true
            }

        },

        messages: {

            date_from: {
                date: 'Please enter a valid From Date.'
            },

            date_to: {
                date: 'Please enter a valid To Date.',
                validDateRange:
                    'To Date must be on or after From Date.'
            }

        },

        errorElement: 'span',

        errorClass: 'error text-red-600 text-xs mt-1 block',

        errorPlacement: function (error, element) {

            error.insertAfter(element);

        },

        highlight: function (element) {

            $(element)
                .addClass('border-red-500')
                .removeClass('border-gray-300');

        },

        unhighlight: function (element) {

            $(element)
                .removeClass('border-red-500')
                .addClass('border-gray-300');

        }

    });


    /*
    |--------------------------------------------------------------------------
    | SET TO DATE MIN ON PAGE LOAD
    |--------------------------------------------------------------------------
    | Important when page loads with existing GET filters.
    |--------------------------------------------------------------------------
    */

    const existingFromDate = $('#date_from').val();

    if (existingFromDate) {

        $('#date_to').attr(
            'min',
            existingFromDate
        );

    }

});

</script>

<?php require_once '../../includes/footer.php'; ?>
