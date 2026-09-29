<?php
$pageTitle = 'Software Licenses';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('licenses.view')) {
    die('Access Denied');
}

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (software_name LIKE ? OR vendor LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where .= " AND status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM software_licenses $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $db->prepare("
    SELECT * 
    FROM software_licenses 
    $where 
    ORDER BY expiry_date ASC, software_name ASC 
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);
$licenses = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('licenses.create')) {

    $software_name = $_POST['software_name'] ?? '';
    $license_key = $_POST['license_key'] ?? '';
    $purchase_date = $_POST['purchase_date'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $seats = $_POST['seats'] ?? 1;
    $vendor = $_POST['vendor'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if ($software_name) {
        $stmt = $db->prepare("
            INSERT INTO software_licenses 
            (
                software_name, 
                license_key, 
                purchase_date, 
                expiry_date, 
                seats, 
                vendor, 
                status, 
                notes
            ) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
        ");

        $stmt->execute([
            $software_name,
            $license_key,
            $purchase_date ?: null,
            $expiry_date ?: null,
            $seats,
            $vendor,
            $notes
        ]);

        flash('success', 'License added successfully');
        to('http://localhost/ERP/modules/licenses/index.php');
    }
}
?>

<style>
    /* Validation messages */
    #licenseForm label.error,
    #licenseForm p.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        position: static !important;
        font-weight: 400 !important;
    }

    /* Invalid fields */
    #licenseForm input.error,
    #licenseForm select.error,
    #licenseForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid fields */
    #licenseForm input.valid,
    #licenseForm select.valid,
    #licenseForm textarea.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Software Licenses</h1>
        <p class="text-gray-600">Manage software licenses and seats</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $activeCount = $db->query("
        SELECT COUNT(*) as c 
        FROM software_licenses 
        WHERE status = 'active'
    ")->fetch()['c'];

    $expiredCount = $db->query("
        SELECT COUNT(*) as c 
        FROM software_licenses 
        WHERE status = 'expired'
    ")->fetch()['c'];

    $expiringCount = $db->query("
        SELECT COUNT(*) as c 
        FROM software_licenses 
        WHERE expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        AND expiry_date >= CURRENT_DATE
        AND status = 'active'
    ")->fetch()['c'];

    $totalSeats = $db->query("
        SELECT COALESCE(SUM(seats), 0) as total 
        FROM software_licenses 
        WHERE status = 'active'
    ")->fetch()['total'];

    $usedSeats = $db->query("
        SELECT COALESCE(SUM(assigned_seats), 0) as total 
        FROM software_licenses 
        WHERE status = 'active'
    ")->fetch()['total'];
    ?>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Active Licenses</div>
        <div class="text-2xl font-bold text-green-600">
            <?= $activeCount ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Expired</div>
        <div class="text-2xl font-bold text-red-600">
            <?= $expiredCount ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Expiring Soon</div>
        <div class="text-2xl font-bold text-orange-600">
            <?= $expiringCount ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Seats (Used/Total)</div>
        <div class="text-2xl font-bold text-blue-600">
            <?= $usedSeats ?>/<?= $totalSeats ?>
        </div>
    </div>
</div>

<?php if (hasPermission('licenses.create')): ?>

    <div class="bg-white rounded-lg shadow mb-6 p-6">

        <h2 class="text-lg font-semibold mb-4">Add New License</h2>

        <form
            method="POST"
            id="licenseForm"
            novalidate
            class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Software Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Software Name
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="software_name"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- License Key -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    License Key
                </label>

                <input
                    type="text"
                    name="license_key"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Seats -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Seats
                </label>

                <input
                    type="number"
                    name="seats"
                    value="1"
                    min="1"
                    step="1"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Purchase Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Purchase Date
                </label>

                <input
                    type="date"
                    name="purchase_date"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Expiry Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expiry Date
                </label>

                <input
                    type="date"
                    name="expiry_date"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Vendor -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor
                </label>

                <input
                    type="text"
                    name="vendor"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Button -->
            <div class="md:col-span-3">
                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Add License
                </button>
            </div>

        </form>
    </div>

<?php endif; ?>

<div class="bg-white rounded-lg shadow mb-6">

    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">

        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Search
            </label>

            <input
                type="text"
                name="search"
                value="<?= sanitize($search) ?>"
                placeholder="Software name..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="w-32">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>

                <option
                    value="active"
                    <?= $status === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option
                    value="expired"
                    <?= $status === 'expired' ? 'selected' : '' ?>>
                    Expired
                </option>

                <option
                    value="cancelled"
                    <?= $status === 'cancelled' ? 'selected' : '' ?>>
                    Cancelled
                </option>
            </select>

        </div>

        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-search"></i>
        </button>

    </form>

</div>

<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">
            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Software
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Vendor
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Expiry Date
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Seats
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Actions
                </th>

            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">

            <?php foreach ($licenses as $lic): ?>

                <tr class="hover:bg-gray-50">

                    <td class="px-6 py-4 font-medium text-gray-900">
                        <?= sanitize($lic['software_name']) ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($lic['vendor'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">

                        <?php if ($lic['expiry_date']): ?>

                            <?php if (strtotime($lic['expiry_date']) < time()): ?>

                                <span class="text-red-600">
                                    Expired
                                </span>

                            <?php elseif (strtotime($lic['expiry_date']) < strtotime('+30 days')): ?>

                                <span class="text-orange-600">
                                    <?= date('M d, Y', strtotime($lic['expiry_date'])) ?>
                                </span>

                            <?php else: ?>

                                <?= date('M d, Y', strtotime($lic['expiry_date'])) ?>

                            <?php endif; ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= $lic['assigned_seats'] ?>/<?= $lic['seats'] ?>
                    </td>

                    <td class="px-6 py-4">

                        <span
                            class="px-2 py-1 text-xs rounded
                        <?= $lic['status'] == 'active'
                            ? 'bg-green-100 text-green-800'
                            : ($lic['status'] == 'expired'
                                ? 'bg-red-100 text-red-800'
                                : 'bg-gray-100 text-gray-800') ?>">
                            <?= ucfirst($lic['status']) ?>
                        </span>

                    </td>

                    <td class="px-6 py-4 text-right">

                        <?php if (
                            hasPermission('licenses.edit') &&
                            $lic['assigned_seats'] < $lic['seats']
                        ): ?>

                            <a
                                href="<?= url('modules/licenses/assign.php?id=' . $lic['id'] . '') ?>"
                                class="text-green-600 hover:text-green-900 mr-2"
                                title="Assign">
                                <i class="fas fa-user-plus"></i>
                            </a>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (empty($licenses)): ?>

                <tr>
                    <td
                        colspan="6"
                        class="px-6 py-8 text-center text-gray-500">
                        No licenses found
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?= paginationLinks($pagination, '?') ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
         * Custom validation:
         * Expiry Date should not be before Purchase Date
         */
        $.validator.addMethod(
            "validExpiryDate",
            function(value, element) {

                const purchaseDate = $('input[name="purchase_date"]').val();

                if (!value || !purchaseDate) {
                    return true;
                }

                return new Date(value) >= new Date(purchaseDate);
            },
            "Expiry date cannot be before purchase date."
        );


        $("#licenseForm").validate({

            /*
             * Validation rules
             */
            rules: {

                software_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 255
                },

                license_key: {
                    maxlength: 255
                },

                seats: {
                    required: true,
                    digits: true,
                    min: 1
                },

                purchase_date: {
                    date: true
                },

                expiry_date: {
                    date: true,
                    validExpiryDate: true
                },

                vendor: {
                    maxlength: 255
                }

            },


            /*
             * Validation messages
             */
            messages: {

                software_name: {
                    required: "Please enter software name.",
                    minlength: "Software name must be at least 2 characters.",
                    maxlength: "Software name cannot exceed 255 characters."
                },

                license_key: {
                    maxlength: "License key cannot exceed 255 characters."
                },

                seats: {
                    required: "Please enter number of seats.",
                    digits: "Seats must be a whole number.",
                    min: "Seats must be at least 1."
                },

                purchase_date: {
                    date: "Please enter a valid purchase date."
                },

                expiry_date: {
                    date: "Please enter a valid expiry date.",
                    validExpiryDate: "Expiry date cannot be before purchase date."
                },

                vendor: {
                    maxlength: "Vendor name cannot exceed 255 characters."
                }

            },


            /*
             * Error placement
             * Select errors will appear below the dropdown.
             */
            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },


            /*
             * Invalid field
             */
            highlight: function(element) {

                $(element)
                    .addClass("error")
                    .removeClass("valid");

            },


            /*
             * Valid field
             */
            unhighlight: function(element) {

                $(element)
                    .removeClass("error")
                    .addClass("valid");

            },


            /*
             * Submit only when validation passes
             */
            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
         * Revalidate expiry date whenever
         * purchase date changes.
         */
        $('input[name="purchase_date"]').on('change', function() {

            $('input[name="expiry_date"]').valid();

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>