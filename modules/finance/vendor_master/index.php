<?php
$pageTitle = 'Vendor Master';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('vendor_master.view')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

// --- Filters ---
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (v.vendor_name LIKE ? OR v.vendor_code LIKE ? OR v.email LIKE ? OR v.contact_person LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $where .= " AND v.status = ?";
    $params[] = $status;
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM vendors v $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];

$pagination = paginate($total, $perPage, $page);

// Fetch vendors
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $db->prepare("
    SELECT v.*,
        (SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = v.id) as po_count,
        (SELECT COUNT(*) FROM vendor_bills WHERE vendor_id = v.id AND status IN ('approved','partially_paid','overdue')) as open_bills,
        (SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE vendor_id = v.id AND status IN ('approved','partially_paid','overdue')) as outstanding
    FROM vendors v $where
    ORDER BY v.vendor_name ASC
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);
$vendors = $stmt->fetchAll();

// Stats
$totalVendors = $db->query("SELECT COUNT(*) FROM vendors")->fetchColumn();

$activeVendors = $db->query("
    SELECT COUNT(*)
    FROM vendors
    WHERE status = 'active'
")->fetchColumn();

$totalOutstanding = $db->query("
    SELECT COALESCE(SUM(balance_amount),0)
    FROM vendor_bills
    WHERE status IN ('approved','partially_paid','overdue')
")->fetchColumn();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Vendor Master</h1>
        <p class="text-gray-600">Manage vendor profiles and information</p>
    </div>

    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Finance
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Vendors</p>
                <p class="text-xl font-bold text-gray-800">
                    <?= (int)$totalVendors ?>
                </p>
            </div>

            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-truck text-blue-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Active Vendors</p>
                <p class="text-xl font-bold text-green-600">
                    <?= (int)$activeVendors ?>
                </p>
            </div>

            <div class="bg-green-100 p-3 rounded-full">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Outstanding AP</p>
                <p class="text-xl font-bold text-red-600">
                    <?= formatMoney((float)$totalOutstanding) ?>
                </p>
            </div>

            <div class="bg-red-100 p-3 rounded-full">
                <i class="fas fa-credit-card text-red-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Inactive</p>
                <p class="text-xl font-bold text-gray-800">
                    <?= (int)($totalVendors - $activeVendors) ?>
                </p>
            </div>

            <div class="bg-gray-100 p-3 rounded-full">
                <i class="fas fa-times-circle text-gray-600"></i>
            </div>
        </div>
    </div>

</div>

<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6">

    <form method="GET"
        id="vendorFilterForm"
        class="p-4 flex flex-wrap gap-4 items-end"
        novalidate>

        <div class="flex-1 min-w-[200px]">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input type="text"
                name="search"
                id="vendorSearch"
                value="<?= sanitize($search) ?>"
                placeholder="Name, Code, Email, Contact..."
                maxlength="200"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>

        <div class="w-36">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select name="status"
                id="vendorStatus"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">All</option>

                <option value="active"
                    <?= $status === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive"
                    <?= $status === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>

            </select>

        </div>

        <button type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>

        <?php if ($search || $status !== ''): ?>

            <a href="index.php"
                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
                <i class="fas fa-times"></i>
            </a>

        <?php endif; ?>

        <?php if (hasPermission('vendor_master.create')): ?>

            <a href="edit.php"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>New Vendor
            </a>

        <?php endif; ?>

    </form>

</div>

<!-- Vendors Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Vendor Code
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Vendor Name
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Contact
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Email
                    </th>

                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                        Status
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Outstanding
                    </th>

                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                        POs
                    </th>

                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-gray-200">

                <?php foreach ($vendors as $v): ?>

                    <tr class="hover:bg-gray-50">

                        <td class="px-4 py-3 text-sm font-mono text-gray-900 whitespace-nowrap">
                            <?= sanitize($v['vendor_code'] ?? '--') ?>
                        </td>

                        <td class="px-4 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">
                            <?= sanitize($v['vendor_name']) ?>
                        </td>

                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= sanitize($v['contact_person'] ?? '--') ?>
                        </td>

                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= sanitize($v['email'] ?? '--') ?>
                        </td>

                        <td class="px-4 py-3 text-center whitespace-nowrap">

                            <span class="px-2 py-1 text-xs rounded font-medium
                            <?= $v['status'] === 'active'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-100 text-gray-600' ?>">

                                <?= ucfirst($v['status']) ?>

                            </span>

                        </td>

                        <td class="px-4 py-3 text-sm text-right font-mono
                        <?= (float)$v['outstanding'] > 0
                            ? 'text-red-600'
                            : 'text-gray-900' ?>
                        whitespace-nowrap">

                            <?= formatMoney((float)$v['outstanding']) ?>

                        </td>

                        <td class="px-4 py-3 text-sm text-center text-gray-600 whitespace-nowrap">
                            <?= (int)$v['po_count'] ?> /
                            <?= (int)$v['open_bills'] ?>
                        </td>

                        <td class="px-4 py-3 text-right whitespace-nowrap">

                            <a href="view.php?id=<?= $v['id'] ?>"
                                class="text-blue-600 hover:text-blue-900 mr-2"
                                title="View Profile">
                                <i class="fas fa-eye"></i>
                            </a>

                            <?php if (hasPermission('vendor_master.edit')): ?>

                                <a href="edit.php?id=<?= $v['id'] ?>"
                                    class="text-yellow-600 hover:text-yellow-900 mr-2"
                                    title="Edit">
                                    <i class="fas fa-pencil"></i>
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($vendors)): ?>

                    <tr>

                        <td colspan="8"
                            class="px-4 py-8 text-center text-gray-500">

                            <i class="fas fa-truck text-4xl mb-3 text-gray-300"></i>

                            <p>No vendors found</p>

                            <?php if (hasPermission('vendor_master.create')): ?>

                                <a href="edit.php"
                                    class="text-blue-600 hover:underline mt-2 inline-block">
                                    Create your first vendor
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?= paginationLinks($pagination, '?') ?>

<!-- jQuery Validation -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
         * Search input sanitization
         */
        $('#vendorSearch').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                ''
            );

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 200));
        });

        /*
         * Vendor Filter Form Validation
         */
        $('#vendorFilterForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {
                    element.parent().append(error);
                } else {
                    error.insertAfter(element);
                }
            },

            highlight: function(element) {
                $(element).addClass('border-red-500');
            },

            unhighlight: function(element) {
                $(element).removeClass('border-red-500');
            },

            rules: {

                search: {
                    maxlength: 200
                },

                status: {
                    required: false,
                    normalizer: function(value) {
                        return $.trim(value);
                    }
                }

            },

            messages: {

                search: {
                    maxlength: 'Search cannot exceed 200 characters.'
                },

                status: {
                    normalizer: 'Invalid status.'
                }

            }

        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>