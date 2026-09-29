<?php
$pageTitle = 'Mark Attendance';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('attendance.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$statusOptions = [
    'present' => 'Present',
    'absent' => 'Absent',
    'half_day' => 'Half Day',
    'leave' => 'Leave',
    'holiday' => 'Holiday',
    'weekend' => 'Weekend'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'employee_id' => $_POST['employee_id'] ?? '',
        'attendance_date' => $_POST['attendance_date'] ?? '',
        'check_in' => $_POST['check_in'] ?? '',
        'check_out' => $_POST['check_out'] ?? '',
        'status' => $_POST['status'] ?? 'present',
        'late_minutes' => $_POST['late_minutes'] ?? 0,
        'remarks' => $_POST['remarks'] ?? ''
    ];

    $errors = validate($data, [
        'employee_id' => 'required',
        'attendance_date' => 'required',
        'status' => 'required'
    ]);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $checkStmt->execute([$data['employee_id'], $data['attendance_date']]);

        if ($checkStmt->fetch()) {
            $errors['attendance_date'] = 'Attendance already marked for this date';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/attendance/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO attendance 
        (employee_id, attendance_date, check_in, check_out, status, late_minutes, remarks) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['employee_id'],
        $data['attendance_date'],
        $data['check_in'] ?: null,
        $data['check_out'] ?: null,
        $data['status'],
        $data['late_minutes'],
        $data['remarks']
    ]);

    logAudit($_SESSION['user_id'], 'mark_attendance', 'attendance');
    flash('success', 'Attendance marked successfully');
    to('http://localhost/ERP/modules/attendance/index.php');
}
?>

<div class="mb-6">
    <a href="<?= url('modules/attendance/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Attendance
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">Mark Attendance</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="attendanceForm" class="space-y-4" novalidate>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Employee -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Employee <span class="text-red-600">*</span>
                </label>
                <select
                    name="employee_id"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option
                            value="<?= $emp['id'] ?>"
                            <?= old('employee_id') == $emp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            (<?= $emp['employee_code'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Attendance Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="attendance_date"
                    value="<?= old('attendance_date', date('Y-m-d')) ?>"
                    min="<?= date('Y-m-d') ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Check In -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Check In
                </label>

                <input
                    type="time"
                    name="check_in"
                    id="check_in"
                    value="<?= old('check_in') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Check Out -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Check Out
                </label>

                <input
                    type="time"
                    name="check_out"
                    id="check_out"
                    value="<?= old('check_out') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Status <span class="text-red-600">*</span>
                </label>

                <select
                    name="status"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($statusOptions as $val => $label): ?>

                        <option
                            value="<?= $val ?>"
                            <?= old('status', 'present') == $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Late Minutes -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Late Minutes
                </label>

                <input
                    type="number"
                    name="late_minutes"
                    value="<?= old('late_minutes', 0) ?>"
                    min="0"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

        </div>

        <!-- Remarks -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Remarks
            </label>

            <textarea
                name="remarks"
                rows="2"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('remarks') ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Mark Attendance
            </button>

            <a
                href="<?= url('modules/attendance/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        // Late Minutes - numbers only
        $('input[name="late_minutes"]').on('input', function() {
            this.value = this.value
                .replace(/[^0-9]/g, '')
                .slice(0, 4);
        });

        // Remarks - maximum 500 characters
        $('textarea[name="remarks"]').on('input', function() {
            this.value = this.value.slice(0, 500);
        });

        // Attendance Form Validation
        $("#attendanceForm").validate({

            rules: {

                employee_id: {
                    required: true
                },

                attendance_date: {
                    required: true
                },

                status: {
                    required: true
                },

                late_minutes: {
                    digits: true,
                    min: 0,
                    maxlength: 4
                },

                remarks: {
                    maxlength: 500
                }
            },

            messages: {

                employee_id: {
                    required: "Please select an employee"
                },

                attendance_date: {
                    required: "Attendance date is required"
                },

                status: {
                    required: "Please select attendance status"
                },

                late_minutes: {
                    digits: "Late minutes can contain numbers only",
                    min: "Late minutes cannot be negative",
                    maxlength: "Late minutes must not exceed 4 digits"
                },

                remarks: {
                    maxlength: "Remarks must not exceed 500 characters"
                }
            },

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

            // Validation message below the field
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

    $('input[name="check_in"]').on('change', function () {
    const checkIn = $(this).val();

    if (checkIn) {
        $('input[name="check_out"]').attr('min', checkIn);
    } else {
        $('input[name="check_out"]').removeAttr('min');
    }

    // Already selected checkout is before check-in
    const checkOut = $('input[name="check_out"]').val();

    if (checkOut && checkOut < checkIn) {
        $('input[name="check_out"]').val('');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>