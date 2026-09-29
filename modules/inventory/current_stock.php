<?php
$pageTitle = 'Current Stock';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';
if (!hasPermission('inventory.view')) {
    die('Access Denied');
}
$db = getDB();
$companyId = $_SESSION['user_id'] ? null : 1;
$fCompany   = $_GET['company_id'] ?? ($companyId ?? '');
$fWarehouse = $_GET['warehouse_id'] ?? '';
$fItem      = $_GET['item_id'] ?? '';
$page    = getPageNumber();
$perPage = 20;
$where  = ['1=1'];
$params = [];
if ($fCompany !== '' && $fCompany !== null) {
    $where[]  = 'b.company_id = ?';
    $params[] = (int)$fCompany;
}
if ($fWarehouse !== '') {
    $where[]  = 'b.warehouse_id = ?';
    $params[] = (int)$fWarehouse;
}
if ($fItem !== '') {
    $where[]  = '(i.item_name LIKE ? OR i.item_code LIKE ?)';
    $params[] = '%' . $fItem . '%';
    $params[] = '%' . $fItem . '%';
}
$whereSql = implode(' AND ', $where);
/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM inventory_stock_balances b
    JOIN inventory_items i ON i.id = b.item_id
    WHERE $whereSql
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);
/*
|--------------------------------------------------------------------------
| Current Stock
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
        b.*,
        i.item_name,
        i.item_code,
        i.unit_price AS item_unit_price,
        w.warehouse_name,
        l.location_name
    FROM inventory_stock_balances b
    JOIN inventory_items i
        ON i.id = b.item_id
    JOIN warehouses w
        ON w.id = b.warehouse_id
    LEFT JOIN locations l
        ON l.id = b.location_id
    WHERE $whereSql
    ORDER BY i.item_name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$rows = $stmt->fetchAll();
/*
|--------------------------------------------------------------------------
| Companies
|--------------------------------------------------------------------------
*/
$warehouses = $db->query("
    SELECT
        id,
        warehouse_code,
        warehouse_name
    FROM warehouses
    WHERE status = 'active'
    ORDER BY warehouse_name
")->fetchAll();
$companies = $db->query("
    SELECT
        id,
        company_code,
        company_name
    FROM companies
    WHERE status = 'active'
    ORDER BY company_name
")->fetchAll();
?>
<!-- =========================================================
     PAGE HEADER
========================================================= -->
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Current Stock
        </h1>
        <p class="text-gray-600">
            On-hand quantities by item, warehouse and location
        </p>
    </div>
    <?php if (hasPermission('inventory.create')): ?>
        <a
            href="<?= url('modules/inventory/opening_stock.php') ?>"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <i class="fas fa-plus mr-1"></i>
            Opening Stock
        </a>
    <?php endif; ?>
</div>
<!-- =========================================================
     FILTERS
========================================================= -->
<div class="bg-white rounded-lg shadow p-4 mb-4">
    <form
        method="GET"
        class="flex flex-wrap gap-3 items-end">
        <!-- =================================================
             COMPANY DROPDOWN
        ================================================== -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">
                Company
            </label>
            <div
                class="relative"
                id="companyDropdown">
                <!-- Hidden value -->
                <input
                    type="hidden"
                    name="company_id"
                    id="companyFilter"
                    value="<?= htmlspecialchars($fCompany ?? '') ?>">
                <!-- Dropdown Button -->
                <button
                    type="button"
                    id="companyDropdownButton"
                    class="custom-dropdown-button">
                    <span id="companySelectedText">
                        <?php
                        $selectedCompanyName = 'All';
                        foreach ($companies as $c) {
                            if ((string)$fCompany === (string)$c['id']) {
                                $selectedCompanyName = $c['company_name'];
                                break;
                            }
                        }
                        echo sanitize($selectedCompanyName);
                        ?>
                    </span>
                    <!-- Existing simple arrow -->
                    <span
                        id="companyArrow"
                        class="custom-dropdown-arrow">▼</span>
                </button>
                <!-- Dropdown Menu -->
                <div
                    id="companyDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <!-- All -->
                        <li>
                            <button
                                type="button"
                                class="company-option custom-dropdown-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>
                        <?php foreach ($companies as $c): ?>
                            <li>
                                <button
                                    type="button"
                                    class="company-option custom-dropdown-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= (int)$c['id'] ?>">
                                    <?= sanitize($c['company_name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <!-- =================================================
             WAREHOUSE DROPDOWN
        ================================================== -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">
                Warehouse
            </label>
            <div
                class="relative"
                id="warehouseDropdown">
                <!-- Hidden value -->
                <input
                    type="hidden"
                    name="warehouse_id"
                    id="warehouseFilter"
                    value="<?= htmlspecialchars($fWarehouse ?? '') ?>">
                <!-- Dropdown Button -->
                <button
                    type="button"
                    id="warehouseDropdownButton"
                    class="custom-dropdown-button">
                    <span id="warehouseSelectedText">
                        <?php
                        $selectedWarehouseName = 'All';
                        foreach ($warehouses as $w) {
                            if ((string)$fWarehouse === (string)$w['id']) {
                                $selectedWarehouseName = $w['warehouse_name'];
                                break;
                            }
                        }
                        echo sanitize($selectedWarehouseName);
                        ?>
                    </span>
                    <!-- Existing simple arrow -->
                    <span
                        id="warehouseArrow"
                        class="custom-dropdown-arrow">▼</span>
                </button>
                <!-- Dropdown Menu -->
                <div
                    id="warehouseDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <!-- All -->
                        <li>
                            <button
                                type="button"
                                class="warehouse-option custom-dropdown-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>
                        <?php foreach ($warehouses as $w): ?>
                            <li>
                                <button
                                    type="button"
                                    class="warehouse-option custom-dropdown-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= (int)$w['id'] ?>">
                                    <?= sanitize($w['warehouse_name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <!-- =================================================
             ITEM
        ================================================== -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">
                Item
            </label>
            <input
                type="text"
                name="item_id"
                value="<?= sanitize($fItem) ?>"
                placeholder="Name or code"
                class="border rounded px-3 py-2 text-sm">
        </div>
        <!-- =================================================
             SEARCH
        ================================================== -->
        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
            <i class="fas fa-search"></i>
        </button>
        <!-- =================================================
             CLEAR
        ================================================== -->
        <a
            href="<?= url('modules/inventory/current_stock.php') ?>"
            class="text-sm text-gray-600 px-4 py-2 border border-gray-200 hover:text-gray-900">
            Clear
        </a>
    </form>
</div>
<!-- =========================================================
     CURRENT STOCK TABLE
========================================================= -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Item
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Warehouse
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Location
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    On-Hand
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Reserved
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Available
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Avg Cost
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Stock Value
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($rows as $r):
                $available =
                    (float)$r['qty_on_hand'] -
                    (float)$r['qty_reserved'];
                $low = $available <= 0;
            ?>
                <tr
                    class="hover:bg-gray-50 <?= $low ? 'bg-red-50' : '' ?>">
                    <!-- Item -->
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">
                            <?= sanitize(
                                $r['item_code'] ?? '-'
                            ) ?>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?= sanitize(
                                $r['item_name']
                            ) ?>
                        </div>
                    </td>
                    <!-- Warehouse -->
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize(
                            $r['warehouse_name']
                        ) ?>
                    </td>
                    <!-- Location -->
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize(
                            $r['location_name'] ?? '-'
                        ) ?>
                    </td>
                    <!-- On Hand -->
                    <td class="px-4 py-3 text-right text-sm font-medium">
                        <?= number_format(
                            $r['qty_on_hand'],
                            3
                        ) ?>
                    </td>
                    <!-- Reserved -->
                    <td class="px-4 py-3 text-right text-sm text-gray-500">
                        <?= number_format(
                            $r['qty_reserved'],
                            3
                        ) ?>
                    </td>
                    <!-- Available -->
                    <td
                        class="px-4 py-3 text-right text-sm font-medium <?= $low ? 'text-red-600' : '' ?>">
                        <?= number_format(
                            $available,
                            3
                        ) ?>
                    </td>
                    <!-- Average Cost -->
                    <td class="px-4 py-3 text-right text-sm">
                        <?= number_format(
                            $r['avg_unit_cost'],
                            4
                        ) ?>
                    </td>
                    <!-- Stock Value -->
                    <td class="px-4 py-3 text-right text-sm">
                        <?= number_format(
                            $r['stock_value'],
                            4
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr>
                    <td
                        colspan="8"
                        class="px-4 py-8 text-center text-gray-500">
                        No stock records found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- =========================================================
     PAGINATION
========================================================= -->
<?= paginationLinks($pagination, '?') ?>
<!-- =========================================================
     CUSTOM DROPDOWN CSS
========================================================= -->
<style>
    /*
    |--------------------------------------------------------------------------
    | Dropdown Button
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-button {
        width: 220px;
        height: 38px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 0 12px;
        font-size: 14px;
        color: #374151;
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        outline: none !important;
        box-shadow: none !important;
    }
    /*
    |--------------------------------------------------------------------------
    | Hover
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-button:hover {
        border-color: #9ca3af;
    }
    /*
    |--------------------------------------------------------------------------
    | Focus
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-button:focus,
    .custom-dropdown-button:active {
        outline: none !important;
        box-shadow: none !important;
        border-color: #d1d5db !important;
    }
    /*
    |--------------------------------------------------------------------------
    | Arrow
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-arrow {
        color: #6b7280 !important;
        font-size: 11px;
    }
    /*
    |--------------------------------------------------------------------------
    | Dropdown Option
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-option {
        color: #374151;
        background: #ffffff;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        cursor: pointer;
    }
    /*
    |--------------------------------------------------------------------------
    | Option Hover
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-option:hover {
        background: #f3f4f6;
    }
    /*
    |--------------------------------------------------------------------------
    | Option Focus
    |--------------------------------------------------------------------------
    */
    .custom-dropdown-option:focus,
    .custom-dropdown-option:active {
        color: #374151 !important;
        background: #f3f4f6 !important;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>
<!-- =========================================================
     CUSTOM DROPDOWN JS
========================================================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        /*
        |--------------------------------------------------------------------------
        | COMPANY
        |--------------------------------------------------------------------------
        */
        const companyDropdown =
            document.getElementById('companyDropdown');
        const companyButton =
            document.getElementById('companyDropdownButton');
        const companyMenu =
            document.getElementById('companyDropdownMenu');
        const companyInput =
            document.getElementById('companyFilter');
        const companyText =
            document.getElementById('companySelectedText');
        const companyArrow =
            document.getElementById('companyArrow');
        /*
        |--------------------------------------------------------------------------
        | WAREHOUSE
        |--------------------------------------------------------------------------
        */
        const warehouseDropdown =
            document.getElementById('warehouseDropdown');
        const warehouseButton =
            document.getElementById('warehouseDropdownButton');
        const warehouseMenu =
            document.getElementById('warehouseDropdownMenu');
        const warehouseInput =
            document.getElementById('warehouseFilter');
        const warehouseText =
            document.getElementById('warehouseSelectedText');
        const warehouseArrow =
            document.getElementById('warehouseArrow');
        /*
        |--------------------------------------------------------------------------
        | Close All Dropdowns
        |--------------------------------------------------------------------------
        */
        function closeAllDropdowns() {
            if (companyMenu) {
                companyMenu.classList.add('hidden');
                companyArrow.textContent = '▼';
            }
            if (warehouseMenu) {
                warehouseMenu.classList.add('hidden');
                warehouseArrow.textContent = '▼';
            }
        }
        /*
        |--------------------------------------------------------------------------
        | COMPANY - Open / Close
        |--------------------------------------------------------------------------
        */
        if (
            companyDropdown &&
            companyButton &&
            companyMenu
        ) {
            companyButton.addEventListener(
                'click',
                function(event) {
                    event.stopPropagation();
                    const isClosed =
                        companyMenu.classList.contains('hidden');
                    closeAllDropdowns();
                    if (isClosed) {
                        companyMenu.classList.remove('hidden');
                        companyArrow.textContent = '▲';
                    }
                }
            );
        }
        /*
        |--------------------------------------------------------------------------
        | COMPANY - Select
        |--------------------------------------------------------------------------
        */
        document
            .querySelectorAll('.company-option')
            .forEach(function(option) {
                option.addEventListener(
                    'click',
                    function(event) {
                        event.stopPropagation();
                        const value =
                            this.dataset.value;
                        const text =
                            this.textContent.trim();
                        companyInput.value =
                            value;
                        companyText.textContent =
                            text;
                        closeAllDropdowns();
                        const form =
                            companyButton.closest('form');
                        if (form) {
                            form.submit();
                        }
                    }
                );
            });
        /*
        |--------------------------------------------------------------------------
        | WAREHOUSE - Open / Close
        |--------------------------------------------------------------------------
        */
        if (
            warehouseDropdown &&
            warehouseButton &&
            warehouseMenu
        ) {
            warehouseButton.addEventListener(
                'click',
                function(event) {
                    event.stopPropagation();
                    const isClosed =
                        warehouseMenu.classList.contains('hidden');
                    closeAllDropdowns();
                    if (isClosed) {
                        warehouseMenu.classList.remove('hidden');
                        warehouseArrow.textContent = '▲';
                    }
                }
            );
        }
        /*
        |--------------------------------------------------------------------------
        | WAREHOUSE - Select
        |--------------------------------------------------------------------------
        */
        document
            .querySelectorAll('.warehouse-option')
            .forEach(function(option) {
                option.addEventListener(
                    'click',
                    function(event) {
                        event.stopPropagation();
                        const value =
                            this.dataset.value;
                        const text =
                            this.textContent.trim();
                        warehouseInput.value =
                            value;
                        warehouseText.textContent =
                            text;
                        closeAllDropdowns();
                        const form =
                            warehouseButton.closest('form');
                        if (form) {
                            form.submit();
                        }
                    }
                );
            });
        /*
        |--------------------------------------------------------------------------
        | Click Outside
        |--------------------------------------------------------------------------
        */
        document.addEventListener(
            'click',
            function(event) {
                const clickedInsideCompany =
                    companyDropdown &&
                    companyDropdown.contains(event.target);
                const clickedInsideWarehouse =
                    warehouseDropdown &&
                    warehouseDropdown.contains(event.target);
                if (
                    !clickedInsideCompany &&
                    !clickedInsideWarehouse
                ) {
                    closeAllDropdowns();
                }
            }
        );
        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */
        document.addEventListener(
            'keydown',
            function(event) {
                if (event.key === 'Escape') {
                    closeAllDropdowns();
                }
            }
        );
    });
</script>
<?php
require_once '../../includes/footer.php';
?>