<?php
$pageTitle = 'Stock Transfers';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('inventory.transfer')) {
    die('Access Denied');
}

$db = getDB();

$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 20;

$where = ['1=1'];
$params = [];

if ($status) {
    $where[] = 't.status = ?';
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM inventory_stock_transfers t
    WHERE $whereSql
");
$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$q = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $db->prepare("
    SELECT t.*, i.item_name, i.item_code,
           sw.warehouse_name AS src_name,
           dw.warehouse_name AS dest_name,
           u.name AS created_by_name
    FROM inventory_stock_transfers t
    JOIN inventory_items i ON i.id = t.item_id
    JOIN warehouses sw ON sw.id = t.source_warehouse_id
    JOIN warehouses dw ON dw.id = t.dest_warehouse_id
    LEFT JOIN users u ON u.id = t.created_by
    WHERE $whereSql
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($q);
$rows = $stmt->fetchAll();

$statuses = [
    'draft',
    'pending_approval',
    'approved',
    'posted',
    'rejected',
    'cancelled'
];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Stock Transfers</h1>
        <p class="text-gray-600">Warehouse to warehouse transfers</p>
    </div>

    <a
        href="<?= url('modules/inventory/transfer_create.php') ?>"
        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
        <i class="fas fa-plus mr-1"></i>New Transfer
    </a>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>

            <div class="relative" id="statusDropdown">
                <input
                    type="hidden"
                    name="status"
                    id="statusFilter"
                    value="<?= htmlspecialchars($status) ?>">

                <button
                    type="button"
                    id="statusDropdownButton"
                    class="status-dropdown-button">
                    <span id="statusSelectedText">
                        <?= $status ? htmlspecialchars($status) : 'All' ?>
                    </span>

                    <span
                        id="statusArrow"
                        class="status-dropdown-arrow">▼</span>
                </button>

                <div
                    id="statusDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[190px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <li>
                            <button
                                type="button"
                                class="status-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>

                        <?php foreach ($statuses as $s): ?>
                            <li>
                                <button
                                    type="button"
                                    class="status-option w-full text-left px-3 py-2 text-sm"
                                    data-value="<?= htmlspecialchars($s) ?>">
                                    <?= htmlspecialchars($s) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                        <?= sanitize($r['transfer_number']) ?>
                    </td>

                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize($r['item_code'] ?? '-') ?><br>
                        <span class="text-xs text-gray-400">
                            <?= sanitize($r['item_name']) ?>
                        </span>
                    </td>

                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize($r['src_name']) ?>
                    </td>

                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize($r['dest_name']) ?>
                    </td>

                    <td class="px-4 py-3 text-right text-sm font-medium">
                        <?= number_format($r['quantity'], 3) ?>
                    </td>

                    <td class="px-4 py-3 text-sm">
                        <?= InventoryEngine::getStatusBadge($r['status']) ?>
                    </td>

                    <td class="px-4 py-3 text-right text-sm">
                        <a
                            href="<?= url('modules/inventory/transfer_create.php?id=' . $r['id']) ?>"
                            class="text-blue-600 hover:text-blue-900">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
                <tr>
                    <td
                        colspan="7"
                        class="px-4 py-8 text-center text-gray-500">
                        No transfers found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>

<style>
    .status-dropdown-button {
        width: 190px;
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

    .status-dropdown-button:hover {
        border-color: #9ca3af;
    }

    .status-dropdown-button:focus,
    .status-dropdown-button:active {
        border-color: #d1d5db !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .status-dropdown-arrow {
        color: #6b7280 !important;
        font-size: 11px;
    }

    .status-option {
        color: #374151;
        background: #fff;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        cursor: pointer;
    }

    .status-option:hover {
        background: #f3f4f6;
    }

    .status-option:focus,
    .status-option:active {
        color: #374151 !important;
        background: #f3f4f6 !important;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdown = document.getElementById('statusDropdown');
        const button = document.getElementById('statusDropdownButton');
        const menu = document.getElementById('statusDropdownMenu');
        const hiddenInput = document.getElementById('statusFilter');
        const selectedText = document.getElementById('statusSelectedText');
        const arrow = document.getElementById('statusArrow');

        if (!dropdown || !button || !menu || !hiddenInput) {
            return;
        }

        function openDropdown() {
            menu.classList.remove('hidden');
            arrow.textContent = '▲';
        }

        function closeDropdown() {
            menu.classList.add('hidden');
            arrow.textContent = '▼';
        }

        button.addEventListener('click', function(event) {
            event.stopPropagation();

            if (menu.classList.contains('hidden')) {
                openDropdown();
            } else {
                closeDropdown();
            }
        });

        document.querySelectorAll('.status-option').forEach(function(option) {
            option.addEventListener('click', function(event) {
                event.stopPropagation();

                hiddenInput.value = this.dataset.value;
                selectedText.textContent = this.textContent.trim();

                closeDropdown();

                const form = button.closest('form');

                if (form) {
                    form.submit();
                }
            });
        });

        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target)) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDropdown();
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>