<?php
$pageTitle = 'Vendors';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('vendors.view')) {
    die('Access Denied');
}

$db = getDB();
$search = $_GET['search'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (vendor_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM vendors $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $db->prepare("
    SELECT * 
    FROM vendors 
    $where 
    ORDER BY vendor_name 
    LIMIT ? OFFSET ?
");

$stmt->execute($queryParams);
$vendors = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('vendors.create')) {

    $vendor_name = $_POST['vendor_name'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $website = $_POST['website'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if ($vendor_name) {

        $stmt = $db->prepare("
            INSERT INTO vendors 
            (
                vendor_name,
                contact_person,
                email,
                phone,
                address,
                website,
                notes
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $vendor_name,
            $contact_person,
            $email,
            $phone,
            $address,
            $website,
            $notes
        ]);

        flash('success', 'Vendor added successfully');
        to('http://localhost/ERP/modules/vendors/index.php');
    }
}

if (isset($_GET['toggle']) && hasPermission('vendors.edit')) {

    $id = $_GET['toggle'];

    $v = $db->prepare("SELECT status FROM vendors WHERE id = ?");
    $v->execute([$id]);
    $v = $v->fetch();

    if ($v) {

        $newStatus = $v['status'] == 'active'
            ? 'inactive'
            : 'active';

        $db->prepare("
            UPDATE vendors 
            SET status = ? 
            WHERE id = ?
        ")->execute([$newStatus, $id]);

        to('http://localhost/ERP/modules/vendors/index.php');
    }
}
?>

<style>
    /* Validation messages */
    #vendorForm label.error,
    #vendorForm p.error {
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
    #vendorForm input.error,
    #vendorForm select.error,
    #vendorForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid fields */
    #vendorForm input.valid,
    #vendorForm select.valid,
    #vendorForm textarea.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Vendors</h1>
        <p class="text-gray-600">Manage vendors and suppliers</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    <?php
    $totalVendors = $db->query("
        SELECT COUNT(*) as c 
        FROM vendors 
        WHERE status = 'active'
    ")->fetch()['c'];
    ?>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">
            Total Vendors
        </div>

        <div class="text-2xl font-bold text-gray-800">
            <?= $totalVendors ?>
        </div>
    </div>

</div>

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
                placeholder="Vendor name, contact, email..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>

        <button
            type="submit"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-search"></i>
        </button>

    </form>

</div>

<?php if (hasPermission('vendors.create')): ?>

    <div class="bg-white rounded-lg shadow mb-6 p-6">

        <h2 class="text-lg font-semibold mb-4">
            Add New Vendor
        </h2>

        <form method="POST" id="vendorForm" novalidate class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Vendor Name -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor Name
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="vendor_name"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Contact Person -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contact Person
                </label>

                <input
                    type="text"
                    name="contact_person"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Email -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Phone -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone
                </label>

                <input
                    type="text"
                    name="phone"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Address -->
            <div class="md:col-span-2">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Address
                </label>

                <input
                    type="text"
                    name="address"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Website -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Website
                </label>

                <input
                    type="text"
                    name="website"
                    placeholder="https://example.com"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Notes -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Notes
                </label>

                <input
                    type="text"
                    name="notes"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Submit -->
            <div class="md:col-span-2">

                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Add Vendor
                </button>

            </div>

        </form>

    </div>

<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">

            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Vendor Name
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Contact Person
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Email
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Phone
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

            <?php foreach ($vendors as $v): ?>

                <tr class="hover:bg-gray-50">

                    <td class="px-6 py-4 font-medium text-gray-900">
                        <?= sanitize($v['vendor_name']) ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($v['contact_person'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($v['email'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($v['phone'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4">

                        <span
                            class="px-2 py-1 text-xs rounded
                        <?= $v['status'] == 'active'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($v['status']) ?>
                        </span>

                    </td>

                    <td class="px-6 py-4 text-right">

                        <?php if (hasPermission('vendors.edit')): ?>

                            <a
                                href="<?= url('modules/vendors/index.php?toggle=' . $v['id'] . '') ?>"
                                class="text-gray-600 hover:text-gray-900 mr-2"
                                title="Toggle Status">
                                <i class="fas fa-toggle-<?= $v['status'] == 'active' ? 'on' : 'off' ?>"></i>
                            </a>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (empty($vendors)): ?>

                <tr>

                    <td
                        colspan="6"
                        class="px-6 py-8 text-center text-gray-500">
                        No vendors found
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<?= paginationLinks($pagination, 'index.php') ?>


<style>
    #vendorForm label.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        font-weight: 400 !important;
        position: static !important;
    }

    #vendorForm input.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #vendorForm input.valid {
        border-color: #22c55e !important;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        if (typeof jQuery === "undefined") {
            console.error("jQuery is not loaded.");
            return;
        }

        if (typeof jQuery.fn.validate === "undefined") {
            console.error("jQuery Validation is not loaded.");
            return;
        }

        const $ = jQuery;

        $("#vendorForm").validate({

            errorElement: "label",
            errorClass: "error",

            rules: {

                vendor_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 255
                },

                contact_person: {
                    maxlength: 255
                },

                email: {
                    maxlength: 255,
                    email: true
                },

                phone: {
                    maxlength: 30
                },

                address: {
                    maxlength: 500
                },

                website: {
                    maxlength: 255,
                    url: true
                },

                notes: {
                    maxlength: 1000
                }

            },

            messages: {

                vendor_name: {
                    required: "Please enter vendor name.",
                    minlength: "Vendor name must be at least 2 characters.",
                    maxlength: "Vendor name cannot exceed 255 characters."
                },

                contact_person: {
                    maxlength: "Contact person cannot exceed 255 characters."
                },

                email: {
                    email: "Please enter a valid email address.",
                    maxlength: "Email cannot exceed 255 characters."
                },

                phone: {
                    maxlength: "Phone number cannot exceed 30 characters."
                },

                address: {
                    maxlength: "Address cannot exceed 500 characters."
                },

                website: {
                    maxlength: "Website cannot exceed 255 characters.",
                    url: "Please enter a valid website URL."
                },

                notes: {
                    maxlength: "Notes cannot exceed 1000 characters."
                }

            },

            errorPlacement: function(error, element) {
                error.insertAfter(element);
            },

            highlight: function(element) {
                $(element)
                    .addClass("error")
                    .removeClass("valid");
            },

            unhighlight: function(element) {
                $(element)
                    .removeClass("error")
                    .addClass("valid");
            },

            submitHandler: function(form) {
                form.submit();
            }

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>