<?php
$isEdit = isset($_GET['id']) && $_GET['id'] > 0;
$pageTitle = $isEdit ? 'Edit Vendor' : 'Create Vendor';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission($isEdit ? 'vendor_master.edit' : 'vendor_master.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$vendor = [
    'id' => 0,
    'vendor_code' => '',
    'vendor_name' => '',
    'contact_person' => '',
    'email' => '',
    'phone' => '',
    'vat_number' => '',
    'address' => '',
    'payment_terms' => '',
    'currency' => 'AED',
    'website' => '',
    'notes' => '',
    'default_expense_account_id' => null,
    'default_payable_account_id' => null,
    'status' => 'active',
];

$vendor_id = 0;

if ($isEdit) {
    $vendor_id = (int)$_GET['id'];

    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);

    $v = $stmt->fetch();

    if (!$v) {
        flash('error', 'Vendor not found.');
        redirect('index.php');
    }

    $vendor = array_merge($vendor, $v);
}

// Load chart of accounts for dropdowns
$expenseAccounts = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE type_id = (
        SELECT id
        FROM account_types
        WHERE type_name = 'Expense'
        LIMIT 1
    )
    AND status = 'active'
    ORDER BY account_code
")->fetchAll();

$payableAccounts = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE group_id = (
        SELECT id
        FROM account_groups
        WHERE group_name = 'Accounts Payable'
        LIMIT 1
    )
    AND status = 'active'
    ORDER BY account_code
")->fetchAll();

// If no expense accounts found by type, fallback
if (empty($expenseAccounts)) {
    $expenseAccounts = $db->query("
        SELECT id, account_code, account_name
        FROM chart_of_accounts
        WHERE status = 'active'
        AND allow_manual_entry = 1
        ORDER BY account_code
        LIMIT 50
    ")->fetchAll();
}

if (empty($payableAccounts)) {
    $payableAccounts = $db->query("
        SELECT id, account_code, account_name
        FROM chart_of_accounts
        WHERE status = 'active'
        AND allow_manual_entry = 1
        ORDER BY account_code
        LIMIT 50
    ")->fetchAll();
}

// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $vat_number = trim($_POST['vat_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $currency = trim($_POST['currency'] ?? 'AED');
    $website = trim($_POST['website'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $default_expense_account_id = !empty($_POST['default_expense_account_id'])
        ? (int)$_POST['default_expense_account_id']
        : null;

    $default_payable_account_id = !empty($_POST['default_payable_account_id'])
        ? (int)$_POST['default_payable_account_id']
        : null;

    $status = $_POST['status'] ?? 'active';

    // Validate
    if (empty($vendor_name)) {
        $errors[] = 'Vendor name is required.';
    }

    if (strlen($vendor_name) > 200) {
        $errors[] = 'Vendor name must not exceed 200 characters.';
    }

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if ($phone && strlen($phone) > 20) {
        $errors[] = 'Phone must not exceed 20 characters.';
    }

    if ($default_expense_account_id) {
        $chk = $db->prepare("
            SELECT id
            FROM chart_of_accounts
            WHERE id = ?
            AND status = 'active'
        ");

        $chk->execute([$default_expense_account_id]);

        if (!$chk->fetch()) {
            $errors[] = 'Selected expense account is invalid.';
        }
    }

    if ($default_payable_account_id) {
        $chk = $db->prepare("
            SELECT id
            FROM chart_of_accounts
            WHERE id = ?
            AND status = 'active'
        ");

        $chk->execute([$default_payable_account_id]);

        if (!$chk->fetch()) {
            $errors[] = 'Selected payable account is invalid.';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);

        to(
            'modules/finance/vendor_master/edit.php' .
                ($isEdit ? '?id=' . $vendor_id : '')
        );
    }

    try {

        $db->beginTransaction();

        if ($isEdit) {

            $stmt = $db->prepare("
                UPDATE vendors SET
                    vendor_name = ?,
                    contact_person = ?,
                    email = ?,
                    phone = ?,
                    vat_number = ?,
                    address = ?,
                    payment_terms = ?,
                    currency = ?,
                    website = ?,
                    notes = ?,
                    default_expense_account_id = ?,
                    default_payable_account_id = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $vendor_name,
                $contact_person,
                $email,
                $phone,
                $vat_number,
                $address,
                $payment_terms,
                $currency,
                $website,
                $notes,
                $default_expense_account_id,
                $default_payable_account_id,
                $status,
                $vendor_id
            ]);

            logAudit(
                $_SESSION['user_id'],
                'Updated vendor: ' . $vendor_name,
                'vendor_master'
            );

            flash(
                'success',
                'Vendor updated successfully.',
                'success'
            );
        } else {

            // Auto-generate vendor code
            $prefix = 'VND' . date('Ymd');

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM vendors
                WHERE vendor_code LIKE ?
            ");

            $stmt->execute([$prefix . '%']);

            $count = (int)$stmt->fetchColumn();

            $vendor_code = $prefix . '-' .
                str_pad($count + 1, 3, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO vendors (
                    vendor_code,
                    vendor_name,
                    contact_person,
                    email,
                    phone,
                    vat_number,
                    address,
                    payment_terms,
                    currency,
                    website,
                    notes,
                    default_expense_account_id,
                    default_payable_account_id,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $vendor_code,
                $vendor_name,
                $contact_person,
                $email,
                $phone,
                $vat_number,
                $address,
                $payment_terms,
                $currency,
                $website,
                $notes,
                $default_expense_account_id,
                $default_payable_account_id,
                $status
            ]);

            logAudit(
                $_SESSION['user_id'],
                'Created vendor: ' . $vendor_name . ' (' . $vendor_code . ')',
                'vendor_master'
            );

            flash(
                'success',
                'Vendor created successfully. Code: ' . $vendor_code,
                'success'
            );
        }

        $db->commit();

        redirect('index.php');
    } catch (Exception $e) {

        $db->rollBack();

        flash(
            'error',
            'Error: ' . $e->getMessage()
        );

        to(
            'modules/finance/vendor_master/edit.php' .
                ($isEdit ? '?id=' . $vendor_id : '')
        );
    }
}
?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= $isEdit ? 'Edit' : 'Create' ?> Vendor
        </h1>

        <p class="text-gray-600">
            <?= $isEdit
                ? 'Update vendor details'
                : 'Add a new vendor to the system' ?>
        </p>
    </div>

    <a href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>Back to Vendors
    </a>

</div>

<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p><?= sanitize($e) ?></p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6">

    <form method="POST"
        id="vendorForm"
        novalidate>

        <input type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Vendor Name -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor Name <span class="text-red-500">*</span>
                </label>

                <input type="text"
                    name="vendor_name"
                    value="<?= sanitize(old('vendor_name', $vendor['vendor_name'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="200">

            </div>

            <!-- Contact Person -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contact Person
                </label>

                <input type="text"
                    name="contact_person"
                    value="<?= sanitize(old('contact_person', $vendor['contact_person'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="100">

            </div>

            <!-- Email -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>

                <input type="email"
                    name="email"
                    value="<?= sanitize(old('email', $vendor['email'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="100">

            </div>

            <!-- Phone -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone
                </label>

                <input type="text"
                    name="phone"
                    value="<?= sanitize(old('phone', $vendor['phone'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="20">

            </div>

            <!-- VAT Number -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    VAT/GST Number
                </label>

                <input type="text"
                    name="vat_number"
                    value="<?= sanitize(old('vat_number', $vendor['vat_number'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="50">

            </div>

            <!-- Payment Terms -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Terms
                </label>

                <select name="payment_terms"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">-- Select --</option>

                    <option value="Due on Receipt"
                        <?= old('payment_terms', $vendor['payment_terms']) === 'Due on Receipt' ? 'selected' : '' ?>>
                        Due on Receipt
                    </option>

                    <option value="Net 15"
                        <?= old('payment_terms', $vendor['payment_terms']) === 'Net 15' ? 'selected' : '' ?>>
                        Net 15
                    </option>

                    <option value="Net 30"
                        <?= old('payment_terms', $vendor['payment_terms']) === 'Net 30' ? 'selected' : '' ?>>
                        Net 30
                    </option>

                    <option value="Net 45"
                        <?= old('payment_terms', $vendor['payment_terms']) === 'Net 45' ? 'selected' : '' ?>>
                        Net 45
                    </option>

                    <option value="Net 60"
                        <?= old('payment_terms', $vendor['payment_terms']) === 'Net 60' ? 'selected' : '' ?>>
                        Net 60
                    </option>

                </select>

            </div>

            <!-- Currency -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Currency
                </label>

                <select name="currency"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="AED"
                        <?= old('currency', $vendor['currency']) === 'AED' ? 'selected' : '' ?>>
                        AED - Dirham
                    </option>

                    <option value="USD"
                        <?= old('currency', $vendor['currency']) === 'USD' ? 'selected' : '' ?>>
                        USD - Dollar
                    </option>

                    <option value="EUR"
                        <?= old('currency', $vendor['currency']) === 'EUR' ? 'selected' : '' ?>>
                        EUR - Euro
                    </option>

                    <option value="GBP"
                        <?= old('currency', $vendor['currency']) === 'GBP' ? 'selected' : '' ?>>
                        GBP - Sterling
                    </option>

                    <option value="SAR"
                        <?= old('currency', $vendor['currency']) === 'SAR' ? 'selected' : '' ?>>
                        SAR - Riyal
                    </option>

                </select>

            </div>

            <!-- Website -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Website
                </label>

                <input type="url"
                    name="website"
                    value="<?= sanitize(old('website', $vendor['website'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="200">

            </div>

            <!-- Status -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Status
                </label>

                <select name="status"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="active"
                        <?= old('status', $vendor['status']) === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option value="inactive"
                        <?= old('status', $vendor['status']) === 'inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>

                </select>

            </div>

            <!-- Default Expense Account -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Default Expense Account
                </label>

                <select name="default_expense_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- None (use module default) --
                    </option>

                    <?php foreach ($expenseAccounts as $a): ?>

                        <option value="<?= $a['id'] ?>"
                            <?= old(
                                'default_expense_account_id',
                                $vendor['default_expense_account_id']
                            ) == $a['id'] ? 'selected' : '' ?>>

                            <?= sanitize($a['account_code'] . ' - ' . $a['account_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Default Payable Account -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Default Payable Account
                </label>

                <select name="default_payable_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- None (use module default) --
                    </option>

                    <?php foreach ($payableAccounts as $a): ?>

                        <option value="<?= $a['id'] ?>"
                            <?= old(
                                'default_payable_account_id',
                                $vendor['default_payable_account_id']
                            ) == $a['id'] ? 'selected' : '' ?>>

                            <?= sanitize($a['account_code'] . ' - ' . $a['account_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <!-- Address -->
        <div class="mt-6">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Address
            </label>

            <textarea name="address"
                rows="2"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('address', $vendor['address'])) ?></textarea>

        </div>

        <!-- Notes -->
        <div class="mt-6">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea name="notes"
                rows="2"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('notes', $vendor['notes'])) ?></textarea>

        </div>

        <div class="mt-8 flex justify-end gap-4">

            <a href="index.php"
                class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
                Cancel
            </a>

            <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-save mr-2"></i>
                <?= $isEdit ? 'Update' : 'Create' ?> Vendor
            </button>

        </div>

    </form>

</div>

<!-- jQuery Validation -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
         * Vendor Name
         */
        $('input[name="vendor_name"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                ''
            );

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 200));
        });

        /*
         * Contact Person
         */
        $('input[name="contact_person"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z .,'-]/g,
                ''
            );

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 100));
        });

        /*
         * Email
         */
        $('input[name="email"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/\s/g, '');

            $(this).val(value.substring(0, 100));
        });

        /*
         * Phone
         */
        $('input[name="phone"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/[^0-9+() .-]/g, '');

            $(this).val(value.substring(0, 20));
        });

        /*
         * VAT / GST Number
         */
        $('input[name="vat_number"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z0-9 ./_-]/g,
                ''
            );

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 50));
        });

        /*
         * Website
         */
        $('input[name="website"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/\s/g, '');

            $(this).val(value.substring(0, 200));
        });

        /*
         * Address
         */
        $('textarea[name="address"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 1000));
        });

        /*
         * Notes
         */
        $('textarea[name="notes"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 1000));
        });

        /*
         * Vendor Form Validation
         */
        $('#vendorForm').validate({

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

                vendor_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 200
                },

                contact_person: {
                    minlength: 2,
                    maxlength: 100
                },

                email: {
                    email: true,
                    maxlength: 100
                },

                phone: {
                    maxlength: 20
                },

                vat_number: {
                    maxlength: 50
                },

                payment_terms: {
                    maxlength: 50
                },

                currency: {
                    required: true,
                    maxlength: 3
                },

                website: {
                    url: true,
                    maxlength: 200
                },

                status: {
                    required: true
                },

                address: {
                    maxlength: 1000
                },

                notes: {
                    maxlength: 1000
                },

                default_expense_account_id: {
                    digits: true,
                    min: 1
                },

                default_payable_account_id: {
                    digits: true,
                    min: 1
                }

            },

            messages: {

                vendor_name: {
                    required: 'Please enter the vendor name.',
                    minlength: 'Vendor name must be at least 2 characters.',
                    maxlength: 'Vendor name cannot exceed 200 characters.'
                },

                contact_person: {
                    minlength: 'Contact person must be at least 2 characters.',
                    maxlength: 'Contact person cannot exceed 100 characters.'
                },

                email: {
                    email: 'Please enter a valid email address.',
                    maxlength: 'Email cannot exceed 100 characters.'
                },

                phone: {
                    maxlength: 'Phone cannot exceed 20 characters.'
                },

                vat_number: {
                    maxlength: 'VAT/GST number cannot exceed 50 characters.'
                },

                payment_terms: {
                    maxlength: 'Payment terms cannot exceed 50 characters.'
                },

                currency: {
                    required: 'Please select a currency.',
                    maxlength: 'Invalid currency.'
                },

                website: {
                    url: 'Please enter a valid website URL.',
                    maxlength: 'Website cannot exceed 200 characters.'
                },

                status: {
                    required: 'Please select the vendor status.'
                },

                address: {
                    maxlength: 'Address cannot exceed 1000 characters.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                },

                default_expense_account_id: {
                    digits: 'Invalid expense account.',
                    min: 'Please select a valid expense account.'
                },

                default_payable_account_id: {
                    digits: 'Invalid payable account.',
                    min: 'Please select a valid payable account.'
                }

            }

        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>