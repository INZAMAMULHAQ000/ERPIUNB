<?php
$pageTitle = 'Create Scheduled Report';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('scheduled_reports.create')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $name        = trim($_POST['name'] ?? '');
    $report_type = trim($_POST['report_type'] ?? '');
    $frequency   = trim($_POST['frequency'] ?? '');
    $recipients  = trim($_POST['recipients'] ?? '');

    if (!$name) {
        $errors[] = 'Name is required.';
    }

    if (!$report_type) {
        $errors[] = 'Report type is required.';
    }

    if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
        $errors[] = 'Invalid frequency.';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/scheduled_reports/create.php');
    }

    $nextRun = match ($frequency) {
        'daily'   => date('Y-m-d', strtotime('+1 day')),
        'weekly'  => date('Y-m-d', strtotime('+1 week')),
        'monthly' => date('Y-m-d', strtotime('+1 month')),
    };

    $stmt = $db->prepare("
        INSERT INTO scheduled_reports (name, report_type, frequency, recipients, next_run, created_by)
        VALUES (?,?,?,?,?,?)
    ");

    $stmt->execute([
        $name,
        $report_type,
        $frequency,
        $recipients,
        $nextRun,
        $_SESSION['user_id']
    ]);

    logAudit(
        $_SESSION['user_id'],
        'created_scheduled_report',
        'scheduled_reports'
    );

    flash(
        'success',
        'Scheduled report created successfully.',
        'success'
    );

    to('modules/scheduled_reports/index.php');
}

$reportTypes = [
    'employee_summary',
    'attendance_report',
    'leave_summary',
    'project_progress',
    'task_completion',
    'timesheet_summary',
    'leads_conversion',
    'sales_pipeline',
    'client_growth',
    'revenue_summary',
    'expense_summary',
    'profit_overview',
    'invoice_aging',
    'asset_allocation',
    'maintenance_summary',
    'license_expiry',
    'ticket_resolution',
    'sla_performance',
    'support_workload',
];
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">
        <i class="fas fa-arrow-left mr-1"></i>Scheduled Reports
    </a>

    <h1 class="text-2xl font-bold text-gray-800 mt-1">
        Create Scheduled Report
    </h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php foreach ($errors as $e): ?>
            <div><?= sanitize($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 w-full">

    <form method="POST" id="scheduledReportForm">

        <input type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">

        <!-- Schedule Name -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Schedule Name
                <span class="text-red-500">*</span>
            </label>

            <input type="text"
                name="name"
                value="<?= old('name') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Report Type -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Report Type
                <span class="text-red-500">*</span>
            </label>

            <select name="report_type"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                required>

                <option value="">Select report type...</option>

                <?php foreach ($reportTypes as $rt): ?>

                    <option value="<?= $rt ?>"
                        <?= old('report_type') === $rt ? 'selected' : '' ?>>

                        <?= ucwords(str_replace('_', ' ', $rt)) ?>

                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <!-- Frequency -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Frequency
                <span class="text-red-500">*</span>
            </label>

            <select name="frequency"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">Select frequency...</option>

                <option value="daily"
                    <?= old('frequency') === 'daily' ? 'selected' : '' ?>>
                    Daily
                </option>

                <option value="weekly"
                    <?= old('frequency', 'weekly') === 'weekly' ? 'selected' : '' ?>>
                    Weekly
                </option>

                <option value="monthly"
                    <?= old('frequency') === 'monthly' ? 'selected' : '' ?>>
                    Monthly
                </option>

            </select>
        </div>

        <!-- Recipients -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Recipients (comma-separated emails)
            </label>

            <input type="text"
                name="recipients"
                value="<?= old('recipients') ?>"
                placeholder="admin@company.com, manager@company.com"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            <p class="text-xs text-gray-500 mt-1">
                Email addresses that will receive this report
            </p>
        </div>

        <!-- Buttons -->
        <div class="flex gap-3">

            <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

                <i class="fas fa-save mr-1"></i>
                Create Schedule

            </button>

            <a href="index.php"
                class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition">

                Cancel

            </a>

        </div>

    </form>

</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        $.validator.addMethod(
            "emailList",
            function(value, element) {

                if ($.trim(value) === "") {
                    return true;
                }

                var emails = value.split(",");

                for (var i = 0; i < emails.length; i++) {

                    var email = $.trim(emails[i]);

                    if (!/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)) {
                        return false;
                    }
                }

                return true;

            },
            "Please enter valid email addresses separated by commas."
        );


        $("#scheduledReportForm").validate({

            rules: {

                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                report_type: {
                    required: true
                },

                frequency: {
                    required: true
                },

                recipients: {
                    emailList: true
                }
            },


            messages: {

                name: {
                    required: "Schedule name is required.",
                    minlength: "Schedule name must be at least 2 characters.",
                    maxlength: "Schedule name must not exceed 100 characters."
                },

                report_type: {
                    required: "Please select a report type."
                },

                frequency: {
                    required: "Please select a frequency."
                },

                recipients: {
                    emailList: "Please enter valid email addresses separated by commas."
                }
            },


            errorElement: "p",

            errorClass: "text-red-600 text-sm mt-1",


            errorPlacement: function(error, element) {

                if (element.is("select")) {
                    error.insertAfter(element);
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