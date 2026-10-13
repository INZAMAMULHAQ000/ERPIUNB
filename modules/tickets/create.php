<?php
$pageTitle = 'Create Ticket';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('tickets.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$categories = $db->query("
    SELECT id, category_name, priority_default
    FROM ticket_categories
    WHERE status = 'active'
    ORDER BY category_name
")->fetchAll();

$clients = $db->query("
    SELECT id, company_name
    FROM clients
    WHERE status = 'active' AND deleted_at IS NULL AND deleted_at IS NULL
    ORDER BY company_name
")->fetchAll();

$employees = $db->query("
    SELECT id, first_name, last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$slas = $db->query("
    SELECT id, sla_name, priority, resolution_time_hours
    FROM slas
    WHERE status = 'active'
    ORDER BY priority
")->fetchAll();


function generateTicketNumber($db)
{
    $year = date('Y');
    $month = date('m');

    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt
        FROM tickets
        WHERE ticket_number LIKE ?
    ");

    $stmt->execute(["TKT-$year$month%"]);

    $count = $stmt->fetch()['cnt'] + 1;

    return "TKT-" . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $data = [
        'client_id'       => $_POST['client_id'] ?? null,
        'employee_id'     => $_POST['employee_id'] ?? null,
        'category_id'     => $_POST['category_id'] ?? null,
        'sla_id'          => $_POST['sla_id'] ?? null,
        'subject'         => trim($_POST['subject'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'priority'        => $_POST['priority'] ?? 'medium',
        'source'          => $_POST['source'] ?? 'web',
        'assigned_to'     => $_POST['assigned_to'] ?? null,
        'due_date'        => $_POST['due_date'] ?? null
    ];

    $errors = validate($data, [
        'subject'     => 'required|min:5|max:255',
        'description' => 'required|min:10'
    ]);


    if (empty($errors) && $data['category_id']) {

        $catStmt = $db->prepare("
            SELECT id
            FROM ticket_categories
            WHERE id = ?
            AND status = 'active'
        ");

        $catStmt->execute([$data['category_id']]);

        if (!$catStmt->fetch()) {
            $errors['category_id'] = 'Invalid or inactive category';
        }
    }


    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/tickets/create.php');
    }


    $ticketNumber = generateTicketNumber($db);

    $dueDate = $data['due_date']
        ? $data['due_date']
        : null;


    if ($data['sla_id'] && !$dueDate) {

        $slaStmt = $db->prepare("
            SELECT resolution_time_hours
            FROM slas
            WHERE id = ?
        ");

        $slaStmt->execute([$data['sla_id']]);

        $sla = $slaStmt->fetch();

        if ($sla) {

            $dueDate = date(
                'Y-m-d H:i:s',
                strtotime(
                    "+" . $sla['resolution_time_hours'] . " hours"
                )
            );
        }
    }


    $stmt = $db->prepare("
        INSERT INTO tickets
        (
            ticket_number,
            client_id,
            employee_id,
            category_id,
            sla_id,
            subject,
            description,
            priority,
            status,
            source,
            assigned_to,
            due_date
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?)
    ");


    $stmt->execute([
        $ticketNumber,
        $data['client_id'] ?: null,
        $data['employee_id'] ?: null,
        $data['category_id'] ?: null,
        $data['sla_id'] ?: null,
        $data['subject'],
        $data['description'],
        $data['priority'],
        $data['source'],
        $data['assigned_to'] ?: null,
        $dueDate
    ]);


    $ticketId = $db->lastInsertId();


    if ($data['assigned_to']) {

        $assignStmt = $db->prepare("
            INSERT INTO ticket_assignments
            (
                ticket_id,
                assigned_to,
                assigned_by
            )
            VALUES (?, ?, ?)
        ");

        $assignStmt->execute([
            $ticketId,
            $data['assigned_to'],
            $_SESSION['user_id']
        ]);
    }


    logAudit(
        $_SESSION['user_id'],
        'create_ticket',
        'tickets'
    );

    flash(
        'success',
        "Ticket {$ticketNumber} created successfully"
    );

    to(
        'http://localhost/ERP/modules/tickets/view.php?id=' . $ticketId
    );
}
?>


<div class="mb-6">

    <a
        href="<?= url('modules/tickets/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Tickets
    </a>

</div>


<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Create New Support Ticket
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
        id="ticketForm"
        class="space-y-4"
        novalidate>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <!-- Subject -->
            <div class="md:col-span-2">

                <label
                    for="subject"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Subject
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="subject"
                    id="subject"
                    value="<?= old('subject') ?>"
                    maxlength="255"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Brief description of the issue">

            </div>


            <!-- Description -->
            <div class="md:col-span-2">

                <label
                    for="description"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Description
                    <span class="text-red-600">*</span>
                </label>

                <textarea
                    name="description"
                    id="description"
                    rows="5"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Detailed description of the issue..."><?= old('description') ?></textarea>

            </div>


            <!-- Client -->
            <div>

                <label
                    for="client_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Client
                </label>

                <select
                    name="client_id"
                    id="client_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Client (optional)
                    </option>

                    <?php foreach ($clients as $client): ?>

                        <option
                            value="<?= htmlspecialchars($client['id'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('client_id') == $client['id'] ? 'selected' : '' ?>>
                            <?= sanitize($client['company_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Category -->
            <div>

                <label
                    for="categorySelect"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Category
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="category_id"
                    id="categorySelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Category
                    </option>

                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?= htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8') ?>"
                            data-priority="<?= htmlspecialchars($cat['priority_default'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['category_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Priority -->
            <div>

                <label
                    for="prioritySelect"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Priority
                </label>

                <select
                    name="priority"
                    id="prioritySelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="low"
                        <?= old('priority') === 'low' ? 'selected' : '' ?>>
                        Low
                    </option>

                    <option
                        value="medium"
                        <?= old('priority', 'medium') === 'medium' ? 'selected' : '' ?>>
                        Medium
                    </option>

                    <option
                        value="high"
                        <?= old('priority') === 'high' ? 'selected' : '' ?>>
                        High
                    </option>

                    <option
                        value="critical"
                        <?= old('priority') === 'critical' ? 'selected' : '' ?>>
                        Critical
                    </option>

                </select>

            </div>


            <!-- Source -->
            <div>

                <label
                    for="source"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Source
                </label>

                <select
                    name="source"
                    id="source"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="web"
                        <?= old('source', 'web') === 'web' ? 'selected' : '' ?>>
                        Web
                    </option>

                    <option
                        value="email"
                        <?= old('source') === 'email' ? 'selected' : '' ?>>
                        Email
                    </option>

                    <option
                        value="phone"
                        <?= old('source') === 'phone' ? 'selected' : '' ?>>
                        Phone
                    </option>

                    <option
                        value="internal"
                        <?= old('source') === 'internal' ? 'selected' : '' ?>>
                        Internal
                    </option>

                </select>

            </div>


            <!-- SLA -->
            <div>

                <label
                    for="slaSelect"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    SLA Policy
                </label>

                <select
                    name="sla_id"
                    id="slaSelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        No SLA
                    </option>

                    <?php foreach ($slas as $sla): ?>

                        <option
                            value="<?= htmlspecialchars($sla['id'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('sla_id') == $sla['id'] ? 'selected' : '' ?>>
                            <?= sanitize($sla['sla_name']) ?>
                            (<?= $sla['resolution_time_hours'] ?>h resolution)
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Assign To -->
            <div>

                <label
                    for="assigned_to"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Assign To
                </label>

                <select
                    name="assigned_to"
                    id="assigned_to"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Unassigned
                    </option>

                    <?php foreach ($employees as $emp): ?>

                        <option
                            value="<?= htmlspecialchars($emp['id'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('assigned_to') == $emp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Due Date -->
            <div>

                <label
                    for="due_date"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Due Date
                </label>

                <input
                    type="datetime-local"
                    name="due_date"
                    id="due_date"
                    value="<?= old('due_date') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <p class="text-xs text-gray-400 mt-1">
                    Auto-set if SLA selected
                </p>

            </div>

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Ticket
            </button>

            <a
                href="<?= url('modules/tickets/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>

</div>


<!-- jQuery Validation CSS -->
<style>
    #ticketForm label.error,
    #ticketForm span.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        font-weight: 400 !important;
        position: static !important;
    }

    #ticketForm input.error,
    #ticketForm textarea.error,
    #ticketForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #ticketForm input.valid,
    #ticketForm textarea.valid,
    #ticketForm select.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {


        /*
         * Custom validation:
         * Subject should contain at least one letter/number
         */
        $.validator.addMethod(
            "validSubject",
            function(value, element) {

                return this.optional(element) ||
                    /[A-Za-z0-9]/.test(value);

            },
            "Please enter a valid subject."
        );


        /*
         * Custom validation:
         * Description should contain at least one letter/number
         */
        $.validator.addMethod(
            "validDescription",
            function(value, element) {

                return this.optional(element) ||
                    /[A-Za-z0-9]/.test(value);

            },
            "Please enter a valid description."
        );


        /*
         * Ticket Form Validation
         */
        $('#ticketForm').validate({

            rules: {

                subject: {
                    required: true,
                    minlength: 5,
                    maxlength: 255,
                    validSubject: true
                },

                description: {
                    required: true,
                    minlength: 10,
                    validDescription: true
                },

                client_id: {
                    digits: true
                },

                category_id: {
                    required: true,
                    digits: true
                },

                priority: {
                    required: true
                },

                source: {
                    required: true
                },

                sla_id: {
                    digits: true
                },

                assigned_to: {
                    digits: true
                },

                due_date: {
                    date: false
                }

            },


            messages: {

                subject: {
                    required: "Please enter subject.",
                    minlength: "Subject must be at least 5 characters.",
                    maxlength: "Subject cannot exceed 255 characters.",
                    validSubject: "Please enter a valid subject."
                },

                description: {
                    required: "Please enter description.",
                    minlength: "Description must be at least 10 characters.",
                    validDescription: "Please enter a valid description."
                },

                category_id: {
                    required: "Please select a category.",
                    digits: "Invalid category."
                },

                client_id: {
                    digits: "Invalid client."
                },

                priority: {
                    required: "Please select priority."
                },

                source: {
                    required: "Please select source."
                },

                sla_id: {
                    digits: "Invalid SLA policy."
                },

                assigned_to: {
                    digits: "Invalid employee."
                }

            },


            /*
             * Select validation error
             * appears BELOW dropdown
             */
            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },


            /*
             * Highlight invalid fields
             */
            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },


            /*
             * Remove error when valid
             */
            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },


            /*
             * Submit only after validation
             */
            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
         * Category -> Priority auto selection
         *
         * Existing functionality preserved
         */
        $('#categorySelect').on('change', function() {

            var option = this.options[this.selectedIndex];

            var priority = option.getAttribute('data-priority');

            if (priority) {

                $('#prioritySelect').val(priority);

            }

        });


        /*
         * Subject:
         * Remove leading spaces
         */
        $('#subject').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });


        /*
         * Description:
         * Remove leading spaces
         */
        $('#description').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });


        /*
         * Subject live character limit
         */
        $('#subject').on('input', function() {

            if (this.value.length > 255) {

                this.value = this.value.substring(0, 255);

            }

        });


        /*
         * Description minimum validation
         * trims only for validation
         */
        $('#description').on('blur', function() {

            $(this).val($(this).val().trim());

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>