<?php
$pageTitle = 'Edit Lead';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('leads.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$lead = $db->prepare("SELECT * FROM leads WHERE id = ?");
$lead->execute([$id]);
$lead = $lead->fetch();

if (!$lead) {
    flash('error', 'Lead not found');
    to('modules/leads/index.php');
}

$employees = $db->query("
    SELECT id, first_name, last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$sources = [
    'Website',
    'Referral',
    'Social Media',
    'Trade Show',
    'Cold Call',
    'Advertisement',
    'Other'
];

$statuses = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'proposal_sent' => 'Proposal Sent',
    'negotiation' => 'Negotiation',
    'won' => 'Won',
    'lost' => 'Lost'
];

$priorities = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical'
];

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'company_name' => $_POST['company_name'] ?? '',
        'contact_person' => $_POST['contact_person'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'country' => $_POST['country'] ?? '',
        'source' => $_POST['source'] ?? '',
        'assigned_to' => $_POST['assigned_to'] ?? '',
        'status' => $_POST['status'] ?? 'new',
        'priority' => $_POST['priority'] ?? 'medium',
        'expected_value' => $_POST['expected_value'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    $errors = validate($data, [
        'company_name' => 'required|min:2'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/leads/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("
        UPDATE leads
        SET
            company_name = ?,
            contact_person = ?,
            email = ?,
            phone = ?,
            country = ?,
            source = ?,
            assigned_to = ?,
            status = ?,
            priority = ?,
            expected_value = ?,
            notes = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['company_name'],
        $data['contact_person'],
        $data['email'],
        $data['phone'],
        $data['country'],
        $data['source'],
        $data['assigned_to'] ?: null,
        $data['status'],
        $data['priority'],
        $data['expected_value'] ?: null,
        $data['notes'],
        $id
    ]);

    logAudit($_SESSION['user_id'], 'update_lead', 'leads');

    flash('success', 'Lead updated successfully');

    to('modules/leads/view.php?id=' . $id);
}
?>

<div class="mb-6">
    <a href="<?= url('modules/leads/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Lead
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Edit Lead - <?= sanitize($lead['lead_code']) ?>
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
        id="editLeadForm"
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
                    value="<?= sanitize(old('company_name', $lead['company_name'])) ?>"
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
                    value="<?= sanitize(old('contact_person', $lead['contact_person'] ?? '')) ?>"
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
                    value="<?= sanitize(old('email', $lead['email'] ?? '')) ?>"
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
                    value="<?= sanitize(old('phone', $lead['phone'] ?? '')) ?>"
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
                    value="<?= sanitize(old('country', $lead['country'] ?? '')) ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Source -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Source
                </label>

                <select
                    name="source"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select</option>

                    <?php foreach ($sources as $s): ?>
                        <option
                            value="<?= sanitize($s) ?>"
                            <?= old('source', $lead['source'] ?? '') == $s ? 'selected' : '' ?>>
                            <?= sanitize($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Assigned To -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Assigned To
                </label>

                <select
                    name="assigned_to"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Unassigned</option>

                    <?php foreach ($employees as $emp): ?>
                        <option
                            value="<?= $emp['id'] ?>"
                            <?= old('assigned_to', $lead['assigned_to'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
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
                    <option value="">Select Status</option>

                    <?php foreach ($statuses as $key => $label): ?>
                        <option
                            value="<?= sanitize($key) ?>"
                            <?= old('status', $lead['status']) == $key ? 'selected' : '' ?>>
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Priority <span class="text-red-600">*</span>
                </label>

                <select
                    name="priority"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Priority</option>

                    <?php foreach ($priorities as $key => $label): ?>
                        <option
                            value="<?= sanitize($key) ?>"
                            <?= old('priority', $lead['priority']) == $key ? 'selected' : '' ?>>
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Expected Value -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expected Value
                </label>

                <input
                    type="text"
                    name="expected_value"
                    value="<?= sanitize(old('expected_value', $lead['expected_value'] ?? '')) ?>"
                    maxlength="15"
                    inputmode="decimal"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

        </div>

        <!-- Notes -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea
                name="notes"
                rows="3"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('notes', $lead['notes'] ?? '')) ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Lead
            </button>

            <a
                href="<?= url('modules/leads/view.php?id=' . $id) ?>"
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

        // Expected Value
        $('input[name="expected_value"]').on('input', function() {

            let value = this.value;

            // Remove non-numeric characters except decimal point
            value = value.replace(/[^0-9.]/g, '');

            // Allow only one decimal point
            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            this.value = value;
        });

        // Notes
        $('textarea[name="notes"]').on('input', function() {
            this.value = this.value.slice(0, 1000);
        });


        /*
         * jQuery Validate
         */

        $('#editLeadForm').validate({

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

                source: {
                    required: true
                },

                assigned_to: {
                    digits: true
                },

                status: {
                    required: true
                },

                priority: {
                    required: true
                },

                expected_value: {
                    number: true,
                    min: 0
                },

                notes: {
                    maxlength: 1000
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

                source: {
                    required: 'Please select a source'
                },

                assigned_to: {
                    digits: 'Invalid employee selection'
                },

                status: {
                    required: 'Please select a status'
                },

                priority: {
                    required: 'Please select a priority'
                },

                expected_value: {
                    number: 'Please enter a valid number',
                    min: 'Expected value cannot be negative'
                },

                notes: {
                    maxlength: 'Notes must not exceed 1000 characters'
                }
            }

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>