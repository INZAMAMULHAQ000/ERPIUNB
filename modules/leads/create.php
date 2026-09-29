<?php
$pageTitle = 'Create Lead';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('leads.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$sources = ['Website', 'Referral', 'Social Media', 'Trade Show', 'Cold Call', 'Advertisement', 'Other'];

function generateLeadCode($db)
{
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM leads WHERE lead_code LIKE 'LD$year%'");
    return "LD" . $year . str_pad($stmt->fetch()['cnt'] + 1, 4, '0', STR_PAD_LEFT);
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

    $errors = validate($data, ['company_name' => 'required|min:2']);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/leads/create.php');
    }

    $stmt = $db->prepare("INSERT INTO leads (lead_code, company_name, contact_person, email, phone, country, source, assigned_to, status, priority, expected_value, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        generateLeadCode($db),
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
        $data['notes']
    ]);

    logAudit($_SESSION['user_id'], 'create_lead', 'leads');
    flash('success', 'Lead created successfully');
    to('http://localhost/ERP/modules/leads/index.php');
}
?>

<div class="mb-6">
    <a href="<?= url('modules/leads/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Leads
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Lead</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="createLeadForm" class="space-y-4" novalidate>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Company Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Company Name <span class="text-red-600">*</span>
                </label>

                <input type="text"
                    name="company_name"
                    value="<?= old('company_name') ?>"
                    required
                    maxlength="150"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Contact Person -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contact Person
                </label>

                <input type="text"
                    name="contact_person"
                    value="<?= old('contact_person') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>

                <input type="email"
                    name="email"
                    value="<?= old('email') ?>"
                    maxlength="150"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Phone
                </label>

                <input type="text"
                    name="phone"
                    value="<?= old('phone') ?>"
                    maxlength="15"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Country -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Country
                </label>

                <input type="text"
                    name="country"
                    value="<?= old('country') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Source -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Source
                </label>

                <select name="source"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select</option>

                    <?php foreach ($sources as $s): ?>
                        <option value="<?= sanitize($s) ?>"
                            <?= old('source') == $s ? 'selected' : '' ?>>
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

                <select name="assigned_to"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Unassigned</option>

                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"
                            <?= old('assigned_to') == $emp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Status
                </label>

                <select name="status"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="new" <?= old('status', 'new') === 'new' ? 'selected' : '' ?>>
                        New
                    </option>

                    <option value="contacted" <?= old('status') === 'contacted' ? 'selected' : '' ?>>
                        Contacted
                    </option>

                    <option value="qualified" <?= old('status') === 'qualified' ? 'selected' : '' ?>>
                        Qualified
                    </option>
                </select>
            </div>

            <!-- Priority -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Priority
                </label>

                <select name="priority"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="low" <?= old('priority', 'medium') === 'low' ? 'selected' : '' ?>>
                        Low
                    </option>

                    <option value="medium" <?= old('priority', 'medium') === 'medium' ? 'selected' : '' ?>>
                        Medium
                    </option>

                    <option value="high" <?= old('priority') === 'high' ? 'selected' : '' ?>>
                        High
                    </option>

                    <option value="critical" <?= old('priority') === 'critical' ? 'selected' : '' ?>>
                        Critical
                    </option>
                </select>
            </div>

            <!-- Expected Value -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expected Value
                </label>

                <input type="number"
                    name="expected_value"
                    value="<?= old('expected_value') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Notes -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea name="notes"
                rows="3"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Lead
            </button>

            <a href="<?= url('modules/leads/index.php') ?>"
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

        /* ==========================================
           CREATE LEAD - INPUT FILTERING
           ========================================== */

        // Company Name
        $('input[name="company_name"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .slice(0, 150);
        });

        // Contact Person - letters and spaces only
        $('input[name="contact_person"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z .'-]/g, '')
                .slice(0, 100);
        });

        // Email - valid email characters only
        $('input[name="email"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9._%+\-@]/g, '')
                .slice(0, 150);
        });

        // Phone - numbers only
        $('input[name="phone"]').on('input', function() {
            this.value = this.value
                .replace(/[^0-9]/g, '')
                .slice(0, 15);
        });

        // Country - letters and spaces only
        $('input[name="country"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z .'-]/g, '')
                .slice(0, 100);
        });

        // Expected Value - numbers and one decimal point
        $('input[name="expected_value"]').on('input', function() {

            let value = this.value.replace(/[^0-9.]/g, '');

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


        /* ==========================================
           JQUERY VALIDATION
           ========================================== */

        $('#createLeadForm').validate({

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
                    required: false
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
                    required: "Please enter company name",
                    minlength: "Company name must be at least 2 characters",
                    maxlength: "Company name cannot exceed 150 characters"
                },

                contact_person: {
                    maxlength: "Contact person cannot exceed 100 characters"
                },

                email: {
                    email: "Please enter a valid email address",
                    maxlength: "Email cannot exceed 150 characters"
                },

                phone: {
                    digits: "Phone number must contain only numbers",
                    minlength: "Phone number must be at least 7 digits",
                    maxlength: "Phone number cannot exceed 15 digits"
                },

                country: {
                    maxlength: "Country cannot exceed 100 characters"
                },

                source: {
                    required: "Please select a source"
                },

                status: {
                    required: "Please select a status"
                },

                priority: {
                    required: "Please select a priority"
                },

                expected_value: {
                    number: "Please enter a valid expected value",
                    min: "Expected value cannot be negative"
                },

                notes: {
                    maxlength: "Notes cannot exceed 1000 characters"
                }
            },

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

            errorPlacement: function(error, element) {

                if (element.is("select")) {
                    element.closest("div").append(error);
                } else {
                    error.insertAfter(element);
                }
            },

            highlight: function(element) {
                $(element).addClass("border-red-500");
            },

            unhighlight: function(element) {
                $(element).removeClass("border-red-500");
            }
        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>