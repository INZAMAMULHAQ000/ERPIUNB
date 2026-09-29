<?php
$pageTitle = 'Stock Adjustments';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';
if (!hasPermission('inventory.adjust')) {
    die('Access Denied');
}
$db = getDB();
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$status = $_GET['status'] ?? '';
$item   = $_GET['item_id'] ?? '';
$page    = getPageNumber();
$perPage = 20;
/*
|--------------------------------------------------------------------------
| Build WHERE
|--------------------------------------------------------------------------
*/
$where  = ['1=1'];
$params = [];
if ($status) {
    $where[]  = 'ia.status = ?';
    $params[] = $status;
}
if ($item) {
    $where[]  = '(i.item_name LIKE ? OR i.item_code LIKE ?)';
    $params[] = '%' . $item . '%';
    $params[] = '%' . $item . '%';
}
$whereSql = implode(' AND ', $where);
/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM inventory_adjustments ia
    JOIN inventory_items i ON i.id = ia.item_id
    WHERE $whereSql
");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);
/*
|--------------------------------------------------------------------------
| Fetch records
|--------------------------------------------------------------------------
*/
$q = array_merge(
    $params,
    [
        $perPage,
        $pagination['offset']
    ]
);
$stmt = $db->prepare("
    SELECT
        ia.*,
        i.item_name,
        i.item_code,
        w.warehouse_name,
        l.location_name,
        u.name AS created_by_name,
        a.name AS approved_by_name
    FROM inventory_adjustments ia
    JOIN inventory_items i
        ON i.id = ia.item_id
    JOIN warehouses w
        ON w.id = ia.warehouse_id
    LEFT JOIN locations l
        ON l.id = ia.location_id
    LEFT JOIN users u
        ON u.id = ia.created_by
    LEFT JOIN users a
        ON a.id = ia.approved_by
    WHERE $whereSql
    ORDER BY ia.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($q);
$rows = $stmt->fetchAll();
?>
<!-- =========================================================
     FILTERS
========================================================= -->
<div class="bg-white rounded-lg shadow p-4 mb-4">
    <form method="GET" class="flex items-end gap-3">
        <!-- =================================================
             STATUS DROPDOWN
        ================================================== -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">
                Status
            </label>
            <div
                class="relative"
                id="statusDropdown">
                <!-- Hidden input -->
                <input
                    type="hidden"
                    name="status"
                    id="statusFilter"
                    value="<?= htmlspecialchars($status) ?>">
                <!-- Dropdown button -->
                <button
                    type="button"
                    id="statusDropdownButton"
                    class="status-dropdown-button">
                    <span id="statusSelectedText">
                        <?= $status ? htmlspecialchars($status) : 'All' ?>
                    </span>
                    <!-- Existing dropdown arrow -->
                    <span
                        id="statusArrow"
                        class="status-dropdown-arrow">▼</span>
                </button>
                <!-- Dropdown menu -->
                <div
                    id="statusDropdownMenu"
                    class="hidden absolute left-0 top-full mt-1 w-[190px] bg-white border border-gray-200 rounded shadow-lg z-50">
                    <ul class="py-1">
                        <!-- All -->
                        <li>
                            <button
                                type="button"
                                class="status-option w-full text-left px-3 py-2 text-sm"
                                data-value="">
                                All
                            </button>
                        </li>
                        <?php
                        $statuses = [
                            'draft',
                            'pending_approval',
                            'approved',
                            'rejected',
                            'finalised',
                            'cancelled'
                        ];
                        ?>
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
                value="<?= sanitize($item) ?>"
                placeholder="Name or code"
                class="border border-gray-300 rounded px-3 py-2 text-sm w-[220px]">
        </div>
        <!-- =================================================
             SEARCH
        ================================================== -->
        <div>
            <label class="block text-xs text-gray-500 mb-1">
                &nbsp;
            </label>
            <button
                type="submit"
                class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>
<!-- =========================================================
     TABLE
========================================================= -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Number
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Item
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Warehouse
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Variance
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Reason
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Status
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-gray-500">
                            No stock adjustments found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-gray-50">
                            <!-- Number -->
                            <td class="px-4 py-3">
                                <?= htmlspecialchars(
                                    $r['adjustment_number']
                                        ?? $r['number']
                                        ?? $r['id']
                                ) ?>
                            </td>
                            <!-- Item -->
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">
                                    <?= htmlspecialchars($r['item_name']) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= htmlspecialchars($r['item_code']) ?>
                                </div>
                            </td>
                            <!-- Warehouse -->
                            <td class="px-4 py-3">
                                <div>
                                    <?= htmlspecialchars($r['warehouse_name']) ?>
                                </div>
                                <?php if (!empty($r['location_name'])): ?>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($r['location_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <!-- Variance -->
                            <td class="px-4 py-3">
                                <?= htmlspecialchars(
                                    $r['variance']
                                        ?? $r['quantity']
                                        ?? 0
                                ) ?>
                            </td>
                            <!-- Reason -->
                            <td class="px-4 py-3">
                                <?= htmlspecialchars(
                                    $r['reason'] ?? ''
                                ) ?>
                            </td>
                            <!-- Status -->
                            <td class="px-4 py-3">
                                <?= InventoryEngine::getStatusBadge(
                                    $r['status']
                                ) ?>
                            </td>
                            <!-- Actions -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <?php if (!empty($r['id'])): ?>
                                        <a
                                            href="view.php?id=<?= (int)$r['id'] ?>"
                                            class="text-gray-600 hover:text-gray-900"
                                            title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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
     DROPDOWN CSS
     Only dropdown styling
========================================================= -->
<style>
    /*
    |--------------------------------------------------------------------------
    | Dropdown Button
    |--------------------------------------------------------------------------
    */
    .status-dropdown-button {
        width: 190px;
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
    .status-dropdown-button:hover {
        border-color: #9ca3af;
    }
    /*
    |--------------------------------------------------------------------------
    | Focus / Active
    |--------------------------------------------------------------------------
    */
    .status-dropdown-button:focus,
    .status-dropdown-button:active {
        outline: none !important;
        box-shadow: none !important;
        border-color: #d1d5db !important;
    }
    /*
    |--------------------------------------------------------------------------
    | Dropdown Arrow
    |--------------------------------------------------------------------------
    */
    .status-dropdown-arrow {
        color: #6b7280 !important;
        font-size: 11px;
    }
    /*
    |--------------------------------------------------------------------------
    | Dropdown Options
    |--------------------------------------------------------------------------
    */
    .status-option {
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
    .status-option:hover {
        background: #f3f4f6;
    }
    /*
    |--------------------------------------------------------------------------
    | Option Focus / Active
    |--------------------------------------------------------------------------
    */
    .status-option:focus,
    .status-option:active {
        color: #374151 !important;
        background: #f3f4f6 !important;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>
<!-- =========================================================
     DROPDOWN JS
========================================================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdown = document.getElementById('statusDropdown');
        const button = document.getElementById('statusDropdownButton');
        const menu = document.getElementById('statusDropdownMenu');
        const hiddenInput = document.getElementById('statusFilter');
        const selectedText = document.getElementById('statusSelectedText');
        const arrow = document.getElementById('statusArrow');
        /*
        |--------------------------------------------------------------------------
        | Safety check
        |--------------------------------------------------------------------------
        */
        if (
            !dropdown ||
            !button ||
            !menu ||
            !hiddenInput ||
            !selectedText ||
            !arrow
        ) {
            return;
        }
        /*
        |--------------------------------------------------------------------------
        | Open
        |--------------------------------------------------------------------------
        */
        function openDropdown() {
            menu.classList.remove('hidden');
            arrow.textContent = '▲';
        }
        /*
        |--------------------------------------------------------------------------
        | Close
        |--------------------------------------------------------------------------
        */
        function closeDropdown() {
            menu.classList.add('hidden');
            arrow.textContent = '▼';
        }
        /*
        |--------------------------------------------------------------------------
        | Toggle dropdown
        |--------------------------------------------------------------------------
        */
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            if (menu.classList.contains('hidden')) {
                openDropdown();
            } else {
                closeDropdown();
            }
        });
        /*
        |--------------------------------------------------------------------------
        | Select option
        |--------------------------------------------------------------------------
        */
        const options = document.querySelectorAll('.status-option');
        options.forEach(function(option) {
            option.addEventListener('click', function(event) {
                event.stopPropagation();
                const value = this.dataset.value;
                const text = this.textContent.trim();
                /*
                | Set hidden input
                */
                hiddenInput.value = value;
                /*
                | Change displayed text
                */
                selectedText.textContent = text;
                /*
                | Close dropdown
                */
                closeDropdown();
                /*
                | Submit form
                */
                const form = button.closest('form');
                if (form) {
                    form.submit();
                }
            });
        });
        /*
        |--------------------------------------------------------------------------
        | Click outside
        |--------------------------------------------------------------------------
        */
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target)) {
                closeDropdown();
            }
        });
        /*
        |--------------------------------------------------------------------------
        | ESC key
        |--------------------------------------------------------------------------
        */
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDropdown();
            }
        });
    });
</script>