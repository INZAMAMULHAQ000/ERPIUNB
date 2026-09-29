<?php
$pageTitle = 'Inventory Reports';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('inventory.reports')) { die('Access Denied'); }
$db = getDB();

$companyId   = $_GET['company_id'] ?? '';
$warehouseId = $_GET['warehouse_id'] ?? '';
$itemId      = $_GET['item_id'] ?? '';
$fromDate    = $_GET['from_date'] ?? '';
$toDate      = $_GET['to_date'] ?? '';

$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$companies  = $db->query("SELECT id, company_code, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$items      = $db->query("SELECT id, item_code, item_name FROM inventory_items WHERE status='active' ORDER BY item_name")->fetchAll();

function buildWhere($f) {
    $w = ['1=1']; $p = [];
    if ($f['company_id'] !== '')  { $w[] = 'company_id = ?'; $p[] = (int)$f['company_id']; }
    if ($f['warehouse_id'] !== ''){ $w[] = 'warehouse_id = ?'; $p[] = (int)$f['warehouse_id']; }
    if ($f['item_id'] !== '')     { $w[] = 'item_id = ?'; $p[] = (int)$f['item_id']; }
    if ($f['from_date'])          { $w[] = 'DATE(movement_datetime) >= ?'; $p[] = $f['from_date']; }
    if ($f['to_date'])            { $w[] = 'DATE(movement_datetime) <= ?'; $p[] = $f['to_date']; }
    return [$w, $p];
}

function renderTable($db, $title, $sql, $params, $cols) {
    echo '<div class="mb-6"><h3 class="text-lg font-semibold text-gray-800 mb-2">' . $title . '</h3>';
    echo '<div class="bg-white rounded-lg shadow overflow-x-auto"><table class="w-full"><thead class="bg-gray-50"><tr>';
    foreach ($cols as $c) echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">' . $c . '</th>';
    echo '</tr></thead><tbody class="divide-y divide-gray-200">';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        echo '<tr><td colspan="' . count($cols) . '" class="px-4 py-4 text-center text-gray-500">No data</td></tr>';
    } else {
        foreach ($rows as $r) {
            echo '<tr class="hover:bg-gray-50">';
            foreach ($r as $k => $v) {
                if ($k === 'id' || $k === 'item_id') continue;
                echo '<td class="px-4 py-2 text-sm text-gray-600">' . sanitize((string)$v) . '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</tbody></table></div></div>';
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Inventory Reports</h1>
    <p class="text-gray-600">Current stock, movement ledger, adjustment history, transfer history</p>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Company</label>
            <select name="company_id" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $companyId==$c['id']?'selected':'' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Warehouse</label>
            <select name="warehouse_id" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= $w['id'] ?>" <?= $warehouseId==$w['id']?'selected':'' ?>><?= sanitize($w['warehouse_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Item</label>
            <select name="item_id" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <?php foreach ($items as $i): ?>
                <option value="<?= $i['id'] ?>" <?= $itemId==$i['id']?'selected':'' ?>><?= sanitize($i['item_code'] . ' - ' . $i['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <input type="date" name="from_date" value="<?= sanitize($fromDate) ?>" class="border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="date" name="to_date" value="<?= sanitize($toDate) ?>" class="border rounded px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">Filter</button>
    </form>
</div>

<?php
$f = ['company_id'=>$companyId,'warehouse_id'=>$warehouseId,'item_id'=>$itemId,'from_date'=>$fromDate,'to_date'=>$toDate];
list($bw, $bp) = buildWhere($f);

// 1. Current Stock summary
renderTable($db, '1. Current Stock Summary',
    "SELECT b.item_id, i.item_code, i.item_name, w.warehouse_name, l.location_name,
            b.qty_on_hand, b.qty_reserved, b.avg_unit_cost, b.stock_value
     FROM inventory_stock_balances b
     JOIN inventory_items i ON i.id=b.item_id JOIN warehouses w ON w.id=b.warehouse_id
     LEFT JOIN locations l ON l.id=b.location_id WHERE " . implode(' AND ', $bw) . " ORDER BY i.item_name",
    $bp,
    ['Code','Name','Warehouse','Location','On-Hand','Reserved','Avg Cost','Value']
);

// 2. Stock Movement Ledger (summary by type)
renderTable($db, '2. Stock Movement Ledger',
    "SELECT movement_type, COUNT(*) as cnt,
            SUM(qty_in) as total_in, SUM(qty_out) as total_out,
            SUM(total_value) as total_value
     FROM inventory_stock_ledger WHERE " . implode(' AND ', $bw) . "
     GROUP BY movement_type ORDER BY movement_type",
    $bp,
    ['Type','Count','Total In','Total Out','Total Value']
);

// 3. Adjustment History
renderTable($db, '3. Adjustment History',
    "SELECT ia.adjustment_number, ia.adjustment_type, ia.reason, ia.variance_qty,
            ia.variance_value, ia.status, u.name AS created_by, ia.created_at
     FROM inventory_adjustments ia
     LEFT JOIN users u ON u.id=ia.created_by
     WHERE " . implode(' AND ', $bw) . " ORDER BY ia.created_at DESC",
    $bp,
    ['Number','Type','Reason','Variance','Value','Status','Created By','Date']
);

// 4. Transfer History
renderTable($db, '4. Transfer History',
    "SELECT t.transfer_number, i.item_code, i.item_name,
            sw.warehouse_name AS src, dw.warehouse_name AS dest,
            t.quantity, t.status, u.name AS created_by, t.created_at
     FROM inventory_stock_transfers t
     JOIN inventory_items i ON i.id=t.item_id
     JOIN warehouses sw ON sw.id=t.source_warehouse_id
     JOIN warehouses dw ON dw.id=t.dest_warehouse_id
     LEFT JOIN users u ON u.id=t.created_by
     WHERE " . implode(' AND ', $bw) . " ORDER BY t.created_at DESC",
    $bp,
    ['Number','Item','Source','Dest','Qty','Status','Created By','Date']
);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');

    function updateToDateMin() {

        if (!dateFrom.value) {
            dateTo.removeAttribute('min');
            return;
        }

        const fromDate = new Date(dateFrom.value + 'T00:00:00');

        // From Date + 1 day
        fromDate.setDate(fromDate.getDate() + 1);

        const year = fromDate.getFullYear();
        const month = String(fromDate.getMonth() + 1).padStart(2, '0');
        const day = String(fromDate.getDate()).padStart(2, '0');

        const minToDate = `${year}-${month}-${day}`;

        // Set minimum To Date
        dateTo.setAttribute('min', minToDate);

        // If existing To Date is before minimum date,
        // automatically clear it.
        if (dateTo.value && dateTo.value < minToDate) {
            dateTo.value = '';
        }
    }

    // Initial page load
    updateToDateMin();

    // When From Date changes
    dateFrom.addEventListener('change', function () {
        updateToDateMin();
    });

});
</script>

<?php require_once '../../includes/footer.php'; ?>
