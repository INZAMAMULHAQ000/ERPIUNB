<?php
$pageTitle = 'Log Timesheet';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('timesheets.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status IN ('active', 'planning') ORDER BY project_name")->fetchAll();
$tasks = $db->query("SELECT id, task_title, project_id FROM tasks WHERE status NOT IN ('completed') ORDER BY task_title")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'employee_id' => $_POST['employee_id'] ?? '',
        'project_id' => $_POST['project_id'] ?? '',
        'task_id' => $_POST['task_id'] ?? '',
        'work_date' => $_POST['work_date'] ?? '',
        'hours_logged' => $_POST['hours_logged'] ?? '',
        'work_description' => $_POST['work_description'] ?? ''
    ];

    $errors = validate($data, [
        'employee_id' => 'required',
        'project_id' => 'required',
        'work_date' => 'required',
        'hours_logged' => 'required'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/timesheets/create.php');
    }

    $stmt = $db->prepare("INSERT INTO timesheets (employee_id, project_id, task_id, work_date, hours_logged, work_description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['employee_id'],
        $data['project_id'],
        $data['task_id'] ?: null,
        $data['work_date'],
        $data['hours_logged'],
        $data['work_description']
    ]);

    logAudit($_SESSION['user_id'], 'log_timesheet', 'timesheets');
    flash('success', 'Timesheet logged successfully');
    to('http://localhost/ERP/modules/timesheets/index.php');
}
?>

<div class="mb-6">
    <a href="<?= url('modules/timesheets/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Timesheets
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Log Working Hours</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="timesheetForm" class="space-y-4" novalidate>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Employee -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Employee <span class="text-red-600">*</span>
                </label>

                <select name="employee_id"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Employee</option>

                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"
                            <?= old('employee_id') == $emp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Project -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Project <span class="text-red-600">*</span>
                </label>

                <select name="project_id"
                    id="project_id"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Project</option>

                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"
                            <?= old('project_id') == $proj['id'] ? 'selected' : '' ?>>
                            <?= sanitize($proj['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Task -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Task (Optional)
                </label>

                <select name="task_id"
                    id="task_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Task</option>
                </select>
            </div>

            <!-- Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Date <span class="text-red-600">*</span>
                </label>

                <input type="date"
                    name="work_date"
                    value="<?= old('work_date', date('Y-m-d')) ?>"
                    min="<?= date('Y-m-d') ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Hours -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Hours Logged <span class="text-red-600">*</span>
                </label>

                <input type="number"
                    name="hours_logged"
                    value="<?= old('hours_logged') ?>"
                    required
                    min="0.5"
                    max="24"
                    step="0.5"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Work Description -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Work Description
            </label>

            <textarea name="work_description"
                rows="3"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('work_description') ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Log Hours
            </button>

            <a href="<?= url('modules/timesheets/index.php') ?>"
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
           TIMESHEET FORM - INPUT FILTERING
           ========================================== */

        // Hours Logged - allow numbers and one decimal point
        $('input[name="hours_logged"]').on('input', function() {

            let value = this.value.replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            this.value = value;
        });

        // Work Description - maximum 1000 characters
        $('textarea[name="work_description"]').on('input', function() {
            this.value = this.value.slice(0, 1000);
        });


        /* ==========================================
           JQUERY VALIDATE
           ========================================== */

        $('#timesheetForm').validate({

            rules: {
                employee_id: {
                    required: true
                },

                project_id: {
                    required: true
                },

                task_id: {
                    required: false
                },

                work_date: {
                    required: true,
                    date: true
                },

                hours_logged: {
                    required: true,
                    number: true,
                    min: 0.5,
                    max: 24
                },

                work_description: {
                    maxlength: 1000
                }
            },

            messages: {
                employee_id: {
                    required: "Please select an employee"
                },

                project_id: {
                    required: "Please select a project"
                },

                work_date: {
                    required: "Please select a work date",
                    date: "Please enter a valid date"
                },

                hours_logged: {
                    required: "Please enter hours logged",
                    number: "Please enter a valid number",
                    min: "Hours logged must be at least 0.5",
                    max: "Hours logged cannot exceed 24"
                },

                work_description: {
                    maxlength: "Work description cannot exceed 1000 characters"
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