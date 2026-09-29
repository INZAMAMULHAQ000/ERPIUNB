<?php
$pageTitle = 'Create Client';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('clients.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$employees = $db->query("
    SELECT id, first_name, last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$industries = [
    'IT',
    'Finance',
    'Healthcare',
    'Retail',
    'Manufacturing',
    'Education',
    'Real Estate',
    'Telecommunications',
    'Consulting',
    'Other'
];

function generateClientCode($db)
{
    $year = date('Y');

    $stmt = $db->query("
        SELECT COUNT(*) as cnt
        FROM clients
        WHERE client_code LIKE 'CL$year%'
    ");

    return "CL" . $year . str_pad(
        $stmt->fetch()['cnt'] + 1,
        4,
        '0',
        STR_PAD_LEFT
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'company_name' => $_POST['company_name'] ?? '',
        'contact_person' => $_POST['contact_person'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'country' => $_POST['country'] ?? '',
        'website' => $_POST['website'] ?? '',
        'gst_vat_number' => $_POST['gst_vat_number'] ?? '',
        'industry' => $_POST['industry'] ?? '',
        'assigned_manager' => $_POST['assigned_manager'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'company_name' => 'required|min:2'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/clients/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO clients
        (
            client_code,
            company_name,
            contact_person,
            email,
            phone,
            address,
            country,
            website,
            gst_vat_number,
            industry,
            assigned_manager,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        generateClientCode($db),
        $data['company_name'],
        $data['contact_person'],
        $data['email'],
        $data['phone'],
        $data['address'],
        $data['country'],
        $data['website'],
        $data['gst_vat_number'],
        $data['industry'],
        $data['assigned_manager'] ?: null,
        $data['status']
    ]);

    logAudit(
        $_SESSION['user_id'],
        'create_client',
        'clients'
    );

    flash('success', 'Client created successfully');

    to('http://localhost/ERP/modules/clients/index.php');
}
?>

<div class="mb-6">
    <a
        href="<?= url('modules/clients/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Clients
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Create New Client
    </h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        id="createClientForm"
        class="space-y-4"
        novalidate>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Company Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Company Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="company_name"
                    value="<?= sanitize(old('company_name')) ?>"
                    maxlength="150"
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
                    value="<?= sanitize(old('contact_person')) ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>

                <input
                    type="text"
                    name="email"
                    value="<?= sanitize(old('email')) ?>"
                    maxlength="150"
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
                    value="<?= sanitize(old('phone')) ?>"
                    maxlength="15"
                    inputmode="numeric"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Country -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Country
                </label>

                <input
                    type="text"
                    name="country"
                    value="<?= sanitize(old('country')) ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Industry -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Industry
                </label>

                <select
                    name="industry"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select</option>

                    <?php foreach ($industries as $ind): ?>
                        <option
                            value="<?= sanitize($ind) ?>"
                            <?= old('industry') == $ind ? 'selected' : '' ?>>
                            <?= sanitize($ind) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Website -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Website
                </label>

                <input
                    type="text"
                    name="website"
                    value="<?= sanitize(old('website')) ?>"
                    maxlength="255"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- GST/VAT Number -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    GST/VAT Number
                </label>

                <input
                    type="text"
                    name="gst_vat_number"
                    value="<?= sanitize(old('gst_vat_number')) ?>"
                    maxlength="50"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Assigned Manager -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Assigned Manager
                </label>

                <select
                    name="assigned_manager"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select</option>

                    <?php foreach ($employees as $emp): ?>
                        <option
                            value="<?= $emp['id'] ?>"
                            <?= old('assigned_manager') == $emp['id'] ? 'selected' : '' ?>>
                            <?= sanitize(
                                $emp['first_name'] . ' ' . $emp['last_name']
                            ) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Status <span class="text-red-600">*</span>
                </label>

                <select
                    name="status"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option
                        value="active"
                        <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= old('status') === 'inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>
            </div>

        </div>

        <!-- Address -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Address
            </label>

            <textarea
                name="address"
                rows="2"
                maxlength="500"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('address')) ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Client
            </button>

            <a
                href="<?= url('modules/clients/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        /*
         * Remove invalid characters while typing / pasting
         */

        // Company Name
        $('input[name="company_name"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .slice(0, 150);
        });

        // Contact Person
        $('input[name="contact_person"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z .'-]/g, '')
                .slice(0, 100);
        });

        // Email
        $('input[name="email"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9._%+\-@]/g, '')
                .slice(0, 150);
        });

        // Phone
        $('input[name="phone"]').on('input', function() {
            this.value = this.value
                .replace(/[^0-9]/g, '')
                .slice(0, 15);
        });

        // Country
        $('input[name="country"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z .'-]/g, '')
                .slice(0, 100);
        });

        // Website
        $('input[name="website"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9:/?&.=_%+#@~\-]/g, '')
                .slice(0, 255);
        });

        // GST / VAT Number
        $('input[name="gst_vat_number"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9\-\/ ]/g, '')
                .slice(0, 50);
        });

        // Address
        $('textarea[name="address"]').on('input', function() {
            this.value = this.value.slice(0, 500);
        });


        /*
         * jQuery Validate
         */

        $('#createClientForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {
                    error.insertAfter(element);
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

                company_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 150
                },

                contact_person: {
                    maxlength: 100
                },

                email: {
                    email: true,
                    maxlength: 150
                },

                phone: {
                    digits: true,
                    minlength: 7,
                    maxlength: 15
                },

                country: {
                    maxlength: 100
                },

                website: {
                    maxlength: 255
                },

                gst_vat_number: {
                    maxlength: 50
                },

                assigned_manager: {
                    digits: true
                },

                status: {
                    required: true
                },

                address: {
                    maxlength: 500
                }
            },

            messages: {

                company_name: {
                    required: 'Company name is required',
                    minlength: 'Company name must be at least 2 characters',
                    maxlength: 'Company name must not exceed 150 characters'
                },

                contact_person: {
                    maxlength: 'Contact person must not exceed 100 characters'
                },

                email: {
                    email: 'Please enter a valid email address',
                    maxlength: 'Email must not exceed 150 characters'
                },

                phone: {
                    digits: 'Phone number must contain numbers only',
                    minlength: 'Phone number must be at least 7 digits',
                    maxlength: 'Phone number must not exceed 15 digits'
                },

                country: {
                    maxlength: 'Country must not exceed 100 characters'
                },

                industry: {
                    required: 'Please select an industry'
                },

                website: {
                    maxlength: 'Website must not exceed 255 characters'
                },

                gst_vat_number: {
                    maxlength: 'GST/VAT number must not exceed 50 characters'
                },

                assigned_manager: {
                    digits: 'Invalid manager selection'
                },

                status: {
                    required: 'Please select a status'
                },

                address: {
                    maxlength: 'Address must not exceed 500 characters'
                }
            }

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>