<?php
$pageTitle = 'Edit Ticket';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('tickets.edit')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT *
    FROM tickets
    WHERE id = ?
");

$stmt->execute([$id]);

$ticket = $stmt->fetch();

if (!$ticket) {
    flash('error', 'Ticket not found');
    to('http://localhost/ERP/modules/tickets/index.php');
}


$categories = $db->query("
    SELECT id, category_name
    FROM ticket_categories
    WHERE status = 'active'
    ORDER BY category_name
")->fetchAll();

$clients = $db->query("
    SELECT id, company_name
    FROM clients
    WHERE status = 'active'
    ORDER BY company_name
")->fetchAll();

$employees = $db->query("
    SELECT id, first_name, last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$slas = $db->query("
    SELECT id, sla_name
    FROM slas
    WHERE status = 'active'
    ORDER BY sla_name
")->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }


    $data = [
        'client_id'    => $_POST['client_id'] ?? null,
        'category_id'  => $_POST['category_id'] ?? null,
        'sla_id'       => $_POST['sla_id'] ?? null,
        'subject'      => trim($_POST['subject'] ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'priority'     => $_POST['priority'] ?? 'medium',
        'source'       => $_POST['source'] ?? 'web',
        'due_date'     => $_POST['due_date'] ?: null
    ];


    $errors = validate($data, [
        'subject'     => 'required|min:5|max:255',
        'description' => 'required|min:10'
    ]);


    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/tickets/edit.php?id=' . $id);
    }


    $stmt = $db->prepare("
        UPDATE tickets
        SET
            client_id = ?,
            category_id = ?,
            sla_id = ?,
            subject = ?,
            description = ?,
            priority = ?,
            source = ?,
            due_date = ?
        WHERE id = ?
    ");


    $stmt->execute([
        $data['client_id'] ?: null,
        $data['category_id'] ?: null,
        $data['sla_id'] ?: null,
        $data['subject'],
        $data['description'],
        $data['priority'],
        $data['source'],
        $data['due_date'] ?: null,
        $id
    ]);


    logAudit(
        $_SESSION['user_id'],
        'update_ticket',
        'tickets'
    );

    flash(
        'success',
        'Ticket updated successfully'
    );

    to(
        'http://localhost/ERP/modules/tickets/view.php?id=' . $id
    );
} else {

    $_POST = $ticket;
}

?>

<div class="mb-6">

    <a
        href="<?= url('modules/tickets/view.php?id=' . $ticket['id']) ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Ticket
    </a>

</div>


<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Edit Ticket <?= sanitize($ticket['ticket_number']) ?>
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
        id="editTicketForm"
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
                        None
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
                    for="category_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Category
                </label>

                <select
                    name="category_id"
                    id="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        None
                    </option>

                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?= htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['category_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Priority -->
            <div>

                <label
                    for="priority"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Priority
                </label>

                <select
                    name="priority"
                    id="priority"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <?php foreach (['low', 'medium', 'high', 'critical'] as $p): ?>

                        <option
                            value="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('priority') === $p ? 'selected' : '' ?>>
                            <?= ucfirst($p) ?>
                        </option>

                    <?php endforeach; ?>

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

                    <?php foreach (['web', 'email', 'phone', 'internal'] as $s): ?>

                        <option
                            value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('source') === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SLA -->
            <div>

                <label
                    for="sla_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    SLA Policy
                </label>

                <select
                    name="sla_id"
                    id="sla_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        None
                    </option>

                    <?php foreach ($slas as $sla): ?>

                        <option
                            value="<?= htmlspecialchars($sla['id'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= old('sla_id') == $sla['id'] ? 'selected' : '' ?>>
                            <?= sanitize($sla['sla_name']) ?>
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
                    value="<?= old('due_date') ? date('Y-m-d\TH:i', strtotime(old('due_date'))) : '' ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Ticket
            </button>

            <a
                href="<?= url('modules/tickets/view.php?id=' . $ticket['id']) ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>

</div>


<!-- Validation CSS -->
<style>
    #editTicketForm label.error,
    #editTicketForm span.error {
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

    #editTicketForm input.error,
    #editTicketForm textarea.error,
    #editTicketForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #editTicketForm input.valid,
    #editTicketForm textarea.valid,
    #editTicketForm select.valid {
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
         * Custom Subject validation
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
         * Custom Description validation
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
         * Edit Ticket Validation
         */
        $('#editTicketForm').validate({

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

                client_id: {
                    digits: "Invalid client."
                },

                category_id: {
                    digits: "Invalid category."
                },

                priority: {
                    required: "Please select priority."
                },

                source: {
                    required: "Please select source."
                },

                sla_id: {
                    digits: "Invalid SLA policy."
                }

            },


            /*
             * Dropdown validation message
             * appears below dropdown
             */
            errorPlacement: function(error, element) {

                if (element.is('select')) {

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
                    .addClass('error')
                    .removeClass('valid');

            },


            /*
             * Valid field
             */
            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },


            /*
             * Submit after validation
             */
            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
         * Remove leading spaces from Subject
         */
        $('#subject').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });


        /*
         * Remove leading spaces from Description
         */
        $('#description').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });


        /*
         * Trim Description on blur
         */
        $('#description').on('blur', function() {

            $(this).val($(this).val().trim());

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>