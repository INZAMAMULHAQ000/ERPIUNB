<?php
$pageTitle = 'Stock Movement Ledger';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('inventory.ledger')) {
    die('Access Denied');
}

$db = getDB();

$filters = [
    'company_id'     => $_GET['company_id'] ?? '',
    'item_id'        => $_GET['item_id'] ?? '',
    'warehouse_id'   => $_GET['warehouse_id'] ?? '',
    'movement_type'  => $_GET['movement_type'] ?? '',
    'from_date'      => $_GET['from_date'] ?? '',
    'to_date'        => $_GET['to_date'] ?? '',
    'reference_type' => $_GET['reference_type'] ?? '',
];

$page = getPageNumber();
$perPage = 25;

$result = InventoryEngine::listLedger(
    $db,
    $filters,
    $perPage,
    ($page - 1) * $perPage
);

$rows = $result['rows'];
$total = $result['total'];
$pagination = paginate($total, $perPage, $page);

$warehouses = $db->query("
    SELECT id, warehouse_code, warehouse_name
    FROM warehouses
    WHERE status = 'active'
    ORDER BY warehouse_name
")->fetchAll();

$companies = $db->query("
    SELECT id, company_code, company_name
    FROM companies
    WHERE status = 'active'
    ORDER BY company_name
")->fetchAll();

$items = $db->query("
    SELECT id, item_code, item_name
    FROM inventory_items
    WHERE status = 'active'
    ORDER BY item_name
")->fetchAll();

$movementTypes = [
    'purchase_receipt',
    'sales_issue',
    'adjustment_in',
    'adjustment_out',
    'transfer_out',
    'transfer_in',
    'opening_stock',
    'reversal'
];
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Stock Movement Ledger</h1>
    <p class="text-gray-600">Immutable record of all inventory movements</p>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">

        <!-- Company -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">Company</label>

            <div class="relative" id="companyDropdown">
                <input
                    type="hidden"
                    name="company_id"
                    id="companyFilter"
                    value="<?= htmlspecialchars($filters['company_id']) ?>">

                <button
                    type="button"
                    id="companyDropdownButton"
                    class="ledger-dropdown-button">
                    <span id="companySelectedText">
                        <?php
                        $companyText = 'All';

                        foreach ($companies as $c) {
                            if ((string)$filters['company_id'] === (string)$c['id']) {
                                $companyText = $c['company_name'];
                                break;
                            }
                        }

                        echo sanitize($companyText);
                        ?>
                    </span>

                    <span id="companyArrow" class="ledger-dropdown-arrow">▼</span>
                </button>

                <div
                    id="companyDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <li>
                            <button
                                type="button"
                                class="company-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>

                        <?php foreach ($companies as $c): ?>
                            <li>
                                <button
                                    type="button"
                                    class="company-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= (int)$c['id'] ?>">
                                    <?= sanitize($c['company_name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Warehouse -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">Warehouse</label>

            <div class="relative" id="warehouseDropdown">
                <input
                    type="hidden"
                    name="warehouse_id"
                    id="warehouseFilter"
                    value="<?= htmlspecialchars($filters['warehouse_id']) ?>">

                <button
                    type="button"
                    id="warehouseDropdownButton"
                    class="ledger-dropdown-button">
                    <span id="warehouseSelectedText">
                        <?php
                        $warehouseText = 'All';

                        foreach ($warehouses as $w) {
                            if ((string)$filters['warehouse_id'] === (string)$w['id']) {
                                $warehouseText = $w['warehouse_name'];
                                break;
                            }
                        }

                        echo sanitize($warehouseText);
                        ?>
                    </span>

                    <span id="warehouseArrow" class="ledger-dropdown-arrow">▼</span>
                </button>

                <div
                    id="warehouseDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <li>
                            <button
                                type="button"
                                class="warehouse-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>

                        <?php foreach ($warehouses as $w): ?>
                            <li>
                                <button
                                    type="button"
                                    class="warehouse-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= (int)$w['id'] ?>">
                                    <?= sanitize($w['warehouse_name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Item -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">Item</label>

            <div class="relative" id="itemDropdown">
                <input
                    type="hidden"
                    name="item_id"
                    id="itemFilter"
                    value="<?= htmlspecialchars($filters['item_id']) ?>">

                <button
                    type="button"
                    id="itemDropdownButton"
                    class="ledger-dropdown-button item-dropdown-button">
                    <span id="itemSelectedText">
                        <?php
                        $itemText = 'All';

                        foreach ($items as $i) {
                            if ((string)$filters['item_id'] === (string)$i['id']) {
                                $itemText = $i['item_code'] . ' - ' . $i['item_name'];
                                break;
                            }
                        }

                        echo sanitize($itemText);
                        ?>
                    </span>

                    <span id="itemArrow" class="ledger-dropdown-arrow">▼</span>
                </button>

                <div
                    id="itemDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[280px] max-h-[300px] overflow-y-auto bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <li>
                            <button
                                type="button"
                                class="item-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>

                        <?php foreach ($items as $i): ?>
                            <li>
                                <button
                                    type="button"
                                    class="item-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= (int)$i['id'] ?>">
                                    <?= sanitize($i['item_code'] . ' - ' . $i['item_name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Type -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">Type</label>

            <div class="relative" id="typeDropdown">
                <input
                    type="hidden"
                    name="movement_type"
                    id="typeFilter"
                    value="<?= htmlspecialchars($filters['movement_type']) ?>">

                <button
                    type="button"
                    id="typeDropdownButton"
                    class="ledger-dropdown-button">
                    <span id="typeSelectedText">
                        <?= $filters['movement_type'] ? htmlspecialchars($filters['movement_type']) : 'All' ?>
                    </span>

                    <span id="typeArrow" class="ledger-dropdown-arrow">▼</span>
                </button>

                <div
                    id="typeDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <li>
                            <button
                                type="button"
                                class="type-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>

                        <?php foreach ($movementTypes as $t): ?>
                            <li>
                                <button
                                    type="button"
                                    class="type-option ledger-dropdown-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= htmlspecialchars($t) ?>">
                                    <?= htmlspecialchars($t) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- From -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>

            <input
                type="date"
                name="from_date"
                id="from_date"
                value="<?= sanitize($filters['from_date']) ?>"
                class="border rounded px-3 py-2 text-sm">
        </div>

        <!-- To -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>

            <input
                type="date"
                name="to_date"
                id="to_date"
                value="<?= sanitize($filters['to_date']) ?>"
                class="border rounded px-3 py-2 text-sm">
        </div>

        <!-- Search -->
        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
            <i class="fas fa-search"></i>
        </button>

        <!-- Clear -->
        <a
            href="<?= url('modules/inventory/ledger.php') ?>"
            class="text-sm text-gray-600 px-4 py-2 border border-grey-200 hover:text-gray-900">
            Clear
        </a>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DateTime</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">In</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Out</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Value</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">
                        #<?= $r['id'] ?>
                    </td>

                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                        <?= date('Y-m-d H:i', strtotime($r['movement_datetime'])) ?>
                    </td>

                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-900">
                            <?= sanitize($r['item_code'] ?? '-') ?>
                        </div>

                        <div class="text-xs text-gray-400">
                            <?= sanitize($r['item_name']) ?>
                        </div>
                    </td>

                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize($r['warehouse_name']) ?>
                    </td>

                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= sanitize($r['movement_type']) ?>
                    </td>

                    <td class="px-4 py-3 text-right text-sm text-green-600 font-medium">
                        <?= $r['qty_in'] > 0 ? number_format($r['qty_in'], 3) : '—' ?>
                    </td>

                    <td class="px-4 py-3 text-right text-sm text-red-600 font-medium">
                        <?= $r['qty_out'] > 0 ? number_format($r['qty_out'], 3) : '—' ?>
                    </td>

                    <td class="px-4 py-3 text-right text-sm">
                        <?= number_format($r['unit_cost'], 4) ?>
                    </td>

                    <td class="px-4 py-3 text-right text-sm">
                        <?= number_format($r['total_value'], 4) ?>
                    </td>

                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= sanitize($r['reference_type'] ?? '') ?>
                        #<?= $r['reference_id'] ?? '-' ?>
                    </td>

                    <td class="px-4 py-3 text-sm">
                        <?= InventoryEngine::getStatusBadge($r['status']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
                <tr>
                    <td
                        colspan="11"
                        class="px-4 py-8 text-center text-gray-500">
                        No ledger entries found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>

<style>
    .ledger-dropdown-button {
        width: 170px;
        height: 38px;
        background: #fff;
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

    .item-dropdown-button {
        width: 170px;
    }

    .ledger-dropdown-button:hover {
        border-color: #9ca3af;
    }

    .ledger-dropdown-button:focus,
    .ledger-dropdown-button:active {
        border-color: #d1d5db !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .ledger-dropdown-arrow {
        color: #6b7280 !important;
        font-size: 11px;
    }

    .ledger-dropdown-option {
        color: #374151;
        background: #fff;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        cursor: pointer;
    }

    .ledger-dropdown-option:hover {
        background: #f3f4f6;
    }

    .ledger-dropdown-option:focus,
    .ledger-dropdown-option:active {
        color: #374151 !important;
        background: #f3f4f6 !important;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = [{
                dropdown: 'companyDropdown',
                button: 'companyDropdownButton',
                menu: 'companyDropdownMenu',
                input: 'companyFilter',
                text: 'companySelectedText',
                arrow: 'companyArrow',
                option: '.company-option'
            },
            {
                dropdown: 'warehouseDropdown',
                button: 'warehouseDropdownButton',
                menu: 'warehouseDropdownMenu',
                input: 'warehouseFilter',
                text: 'warehouseSelectedText',
                arrow: 'warehouseArrow',
                option: '.warehouse-option'
            },
            {
                dropdown: 'itemDropdown',
                button: 'itemDropdownButton',
                menu: 'itemDropdownMenu',
                input: 'itemFilter',
                text: 'itemSelectedText',
                arrow: 'itemArrow',
                option: '.item-option'
            },
            {
                dropdown: 'typeDropdown',
                button: 'typeDropdownButton',
                menu: 'typeDropdownMenu',
                input: 'typeFilter',
                text: 'typeSelectedText',
                arrow: 'typeArrow',
                option: '.type-option'
            }
        ];

        function closeAll() {
            dropdowns.forEach(function(item) {
                const menu = document.getElementById(item.menu);
                const arrow = document.getElementById(item.arrow);

                if (menu) {
                    menu.classList.add('hidden');
                }

                if (arrow) {
                    arrow.textContent = '▼';
                }
            });
        }

        dropdowns.forEach(function(item) {
            const dropdown = document.getElementById(item.dropdown);
            const button = document.getElementById(item.button);
            const menu = document.getElementById(item.menu);
            const input = document.getElementById(item.input);
            const text = document.getElementById(item.text);
            const arrow = document.getElementById(item.arrow);

            if (!dropdown || !button || !menu || !input || !text || !arrow) {
                return;
            }

            button.addEventListener('click', function(event) {
                event.stopPropagation();

                const isClosed = menu.classList.contains('hidden');

                closeAll();

                if (isClosed) {
                    menu.classList.remove('hidden');
                    arrow.textContent = '▲';
                }
            });

            document.querySelectorAll(item.option).forEach(function(option) {
                option.addEventListener('click', function(event) {
                    event.stopPropagation();

                    input.value = this.dataset.value;
                    text.textContent = this.textContent.trim();

                    closeAll();

                    const form = button.closest('form');

                    if (form) {
                        form.submit();
                    }
                });
            });
        });

        document.addEventListener('click', function(event) {
            let insideDropdown = false;

            dropdowns.forEach(function(item) {
                const dropdown = document.getElementById(item.dropdown);

                if (dropdown && dropdown.contains(event.target)) {
                    insideDropdown = true;
                }
            });

            if (!insideDropdown) {
                closeAll();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAll();
            }
        });
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fromDate = document.getElementById('from_date');
    const toDate = document.getElementById('to_date');

    if (!fromDate || !toDate) {
        return;
    }

    function updateToDateMin() {
        if (!fromDate.value) {
            toDate.removeAttribute('min');
            return;
        }

        const date = new Date(fromDate.value + 'T00:00:00');
        date.setDate(date.getDate() + 1);

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        const nextDate = `${year}-${month}-${day}`;

        toDate.min = nextDate;

        if (toDate.value && toDate.value < nextDate) {
            toDate.value = '';
        }
    }

    fromDate.addEventListener('change', updateToDateMin);

    updateToDateMin();
});
</script>

<?php require_once '../../includes/footer.php'; ?>