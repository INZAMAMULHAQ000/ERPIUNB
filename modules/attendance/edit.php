<?php
$pageTitle = 'Edit Attendance';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('attendance.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    flash('error', 'Attendance record not found');
    to('http://localhost/ERP/modules/attendance/index.php');
}

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$statusOptions = ['present' => 'Present', 'absent' => 'Absent', 'half_day' => 'Half Day', 'leave' => 'Leave', 'holiday' => 'Holiday', 'weekend' => 'Weekend'];
$old = $_POST;

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
        $checkStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ? AND id != ?");
        $checkStmt->execute([$data['employee_id'], $data['attendance_date'], $id]);
        if ($checkStmt->fetch()) {
            $errors['attendance_date'] = 'Attendance already marked for this date';
        }
    }
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/attendance/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("UPDATE attendance SET employee_id = ?, attendance_date = ?, check_in = ?, check_out = ?, status = ?, late_minutes = ?, remarks = ? WHERE id = ?");
    $stmt->execute([
        $data['employee_id'], $data['attendance_date'],
        $data['check_in'] ?: null, $data['check_out'] ?: null,
        $data['status'], $data['late_minutes'], $data['remarks'], $id
    ]);
    
    logAudit($_SESSION['user_id'], 'update_attendance', 'attendance');
    flash('success', 'Attendance updated successfully');
    to('http://localhost/ERP/modules/attendance/index.php');

    $record = array_merge($record, $data);
}
?>

<div class="mb-6">
    <a href="<?= url('modules/attendance/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Attendance
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit Attendance</h2>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?= sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="editAttendanceForm" class="space-y-4" novalidate>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee <span class="text-red-600">*</span></labe>
                <select name="employee_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= ($record['employee_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                        <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_code'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-600">*</span></labe>
                <input type="date" name="attendance_date" value="<?= old('attendance_date', $record['attendance_date'] ?? '') ?>" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Check In</label>
                <input type="time" name="check_in" value="<?= old('check_in', $record['check_in'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Check Out</label>
                <input type="time" name="check_out" value="<?= old('check_out', $record['check_out'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-600">*</span></labe>
                <select name="status" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($statusOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($record['status'] ?? 'present') == $val ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Late Minutes</label>
                <input type="number" name="late_minutes" value="<?= old('late_minutes', $record['late_minutes'] ?? 0) ?>" min="0"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
            <textarea name="remarks" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('remarks', $record['remarks'] ?? '') ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Attendance
            </button>
            <a href="<?= url('modules/attendance/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    // Employee validation
    $("#editAttendanceForm select[name='employee_id']").on("change", function () {
        $(this).valid();
    });

    // Date validation
    $("#editAttendanceForm input[name='attendance_date']").on("change", function () {
        $(this).valid();
    });

    // Late minutes - numbers only while typing/pasting
    $("#editAttendanceForm input[name='late_minutes']").on("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").slice(0, 4);
    });

    // Remarks - maximum 500 characters
    $("#editAttendanceForm textarea[name='remarks']").on("input", function () {
        this.value = this.value.slice(0, 500);
    });

    $("#editAttendanceForm").validate({

        rules: {
            employee_id: {
                required: true
            },

            attendance_date: {
                required: true
            },

            check_in: {
                required: false
            },

            check_out: {
                required: false
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

        // Error message below dropdown/input
        errorPlacement: function (error, element) {

            if (element.is("select")) {
                element.closest("div").append(error);
            } else {
                error.insertAfter(element);
            }
        },

        highlight: function (element) {
            $(element).addClass("border-red-500");
        },

        unhighlight: function (element) {
            $(element).removeClass("border-red-500");
        }
    });

});
</script>

<?php require_once '../../includes/footer.php'; ?>