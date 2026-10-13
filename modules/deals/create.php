<?php
$pageTitle = 'Create Deal';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('deals.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$leads = $db->query("
    SELECT id, company_name
    FROM leads
    ORDER BY company_name
")->fetchAll();

$clients = $db->query("
    SELECT id, company_name
    FROM clients
    WHERE deleted_at IS NULL
    ORDER BY company_name
")->fetchAll();

$employees = $db->query("
    SELECT id, first_name, last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$stages = [
    'inquiry' => 'Inquiry',
    'qualified' => 'Qualified',
    'proposal' => 'Proposal',
    'negotiation' => 'Negotiation',
    'closed_won' => 'Closed Won',
    'closed_lost' => 'Closed Lost'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'deal_title' => $_POST['deal_title'] ?? '',
        'lead_id' => $_POST['lead_id'] ?? '',
        'client_id' => $_POST['client_id'] ?? '',
        'assigned_to' => $_POST['assigned_to'] ?? '',
        'deal_value' => $_POST['deal_value'] ?? '',
        'stage' => $_POST['stage'] ?? 'inquiry',
        'expected_closing_date' => $_POST['expected_closing_date'] ?? '',
        'probability' => $_POST['probability'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    $errors = validate($data, [
        'deal_title' => 'required|min:2'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/deals/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO deals
        (
            deal_title,
            lead_id,
            client_id,
            assigned_to,
            deal_value,
            stage,
            expected_closing_date,
            probability,
            notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['deal_title'],
        $data['lead_id'] ?: null,
        $data['client_id'] ?: null,
        $data['assigned_to'] ?: null,
        $data['deal_value'] ?: null,
        $data['stage'],
        $data['expected_closing_date'] ?: null,
        $data['probability'] !== '' ? (int) $data['probability'] : null,
        $data['notes']
    ]);

    logAudit(
        $_SESSION['user_id'],
        'create_deal',
        'deals'
    );

    flash('success', 'Deal created successfully');

    to('modules/deals/index.php');
}
?>

<div class="mb-6">
    <a
        href="<?= url('modules/deals/index.php') ?>"
        class="text-blue-600 hover:text-blue-900"
    >
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Deals Pipeline
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Create New Deal
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
        id="createDealForm"
        class="space-y-4"
        novalidate
    >

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Deal Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Deal Title <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="deal_title"
                    value="<?= sanitize(old('deal_title')) ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <!-- Stage -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Stage
                </label>

                <select
                    name="stage"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
                    <?php foreach ($stages as $key => $label): ?>
                        <option
                            value="<?= sanitize($key) ?>"
                            <?= old('stage', 'inquiry') == $key ? 'selected' : '' ?>
                        >
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Linked Lead -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Linked Lead
                </label>

                <select
                    name="lead_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">None</option>

                    <?php foreach ($leads as $lead): ?>
                        <option
                            value="<?= $lead['id'] ?>"
                            <?= old('lead_id') == $lead['id'] ? 'selected' : '' ?>
                        >
                            <?= sanitize($lead['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Client -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Client
                </label>

                <select
                    name="client_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">None</option>

                    <?php foreach ($clients as $client): ?>
                        <option
                            value="<?= $client['id'] ?>"
                            <?= old('client_id') == $client['id'] ? 'selected' : '' ?>
                        >
                            <?= sanitize($client['company_name']) ?>
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
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Unassigned</option>

                    <?php foreach ($employees as $emp): ?>
                        <option
                            value="<?= $emp['id'] ?>"
                            <?= old('assigned_to') == $emp['id'] ? 'selected' : '' ?>
                        >
                            <?= sanitize(
                                $emp['first_name'] . ' ' . $emp['last_name']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Deal Value -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Deal Value
                </label>

                <input
                    type="text"
                    name="deal_value"
                    value="<?= sanitize(old('deal_value')) ?>"
                    maxlength="15"
                    inputmode="decimal"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <!-- Probability -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Probability (%)
                </label>

                <input
                    type="text"
                    name="probability"
                    value="<?= sanitize(old('probability')) ?>"
                    maxlength="3"
                    inputmode="numeric"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <!-- Expected Closing Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expected Closing Date
                </label>

                <input
                    type="date"
                    name="expected_closing_date"
                    value="<?= sanitize(old('expected_closing_date')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >
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
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
            ><?= sanitize(old('notes')) ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"
            >
                Create Deal
            </button>

            <a
                href="<?= url('modules/deals/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900"
            >
                Cancel
            </a>

        </div>

    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
$(document).ready(function () {

    /*
     * Remove invalid characters while typing / pasting
     */

    // Deal Title
    $('input[name="deal_title"]').on('input', function () {
        this.value = this.value
            .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
            .slice(0, 200);
    });

    // Deal Value
    $('input[name="deal_value"]').on('input', function () {

        let value = this.value;

        // Allow numbers and decimal point only
        value = value.replace(/[^0-9.]/g, '');

        // Allow only one decimal point
        const parts = value.split('.');

        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }

        this.value = value.slice(0, 15);
    });

    // Probability
    $('input[name="probability"]').on('input', function () {

        this.value = this.value
            .replace(/[^0-9]/g, '')
            .slice(0, 3);

        // Maximum 100
        if (this.value !== '' && parseInt(this.value, 10) > 100) {
            this.value = '100';
        }
    });

    // Notes
    $('textarea[name="notes"]').on('input', function () {
        this.value = this.value.slice(0, 1000);
    });


    /*
     * jQuery Validate
     */

    $('#createDealForm').validate({

        errorElement: 'span',

        errorClass: 'text-red-500 text-sm mt-1 block',

        errorPlacement: function (error, element) {

            if (element.is('select')) {
                error.insertAfter(element);
            } else {
                error.insertAfter(element);
            }
        },

        highlight: function (element) {
            $(element).addClass('border-red-500');
        },

        unhighlight: function (element) {
            $(element).removeClass('border-red-500');
        },

        rules: {

            deal_title: {
                required: true,
                minlength: 2,
                maxlength: 200
            },

            stage: {
                required: true
            },

            lead_id: {
                digits: true
            },

            client_id: {
                digits: true
            },

            assigned_to: {
                digits: true
            },

            deal_value: {
                number: true,
                min: 0
            },

            probability: {
                digits: true,
                min: 0,
                max: 100
            },

            expected_closing_date: {
                date: true
            },

            notes: {
                maxlength: 1000
            }
        },

        messages: {

            deal_title: {
                required: 'Deal title is required',
                minlength: 'Deal title must be at least 2 characters',
                maxlength: 'Deal title must not exceed 200 characters'
            },

            stage: {
                required: 'Please select a stage'
            },

            lead_id: {
                digits: 'Invalid lead selection'
            },

            client_id: {
                digits: 'Invalid client selection'
            },

            assigned_to: {
                digits: 'Invalid employee selection'
            },

            deal_value: {
                number: 'Please enter a valid deal value',
                min: 'Deal value cannot be negative'
            },

            probability: {
                digits: 'Probability must contain numbers only',
                min: 'Probability cannot be less than 0%',
                max: 'Probability cannot exceed 100%'
            },

            expected_closing_date: {
                date: 'Please enter a valid date'
            },

            notes: {
                maxlength: 'Notes must not exceed 1000 characters'
            }
        }

    });

});
</script>

<?php require_once '../../includes/footer.php'; ?>