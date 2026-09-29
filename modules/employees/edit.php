<?php
$pageTitle = 'Edit Employee';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('employees.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash('error', 'Employee not found');
    to('modules/employees/index.php');
}

$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$designations = $db->query("SELECT * FROM designations WHERE status = 'active' ORDER BY designation_name")->fetchAll();
$managers = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' AND id != $id ORDER BY first_name")->fetchAll();
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'marital_status' => $_POST['marital_status'] ?? 'single',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'department_id' => $_POST['department_id'] ?? '',
        'designation_id' => $_POST['designation_id'] ?? '',
        'joining_date' => $_POST['joining_date'] ?? '',
        'employment_type' => $_POST['employment_type'] ?? 'full_time',
        'reporting_manager_id' => $_POST['reporting_manager_id'] ?? '',
        'work_location' => $_POST['work_location'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];
    
    $errors = validate($data, [
        'first_name' => 'required|min:2|max:100',
        'gender' => 'required',
        'email' => 'required|email',
        'department_id' => 'required',
        'designation_id' => 'required',
        'joining_date' => 'required'
    ]);
    
    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $checkStmt->execute([$data['email'], $id]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/employees/edit.php?id=' . $id);
    }

        $stmt = $db->prepare("UPDATE employees SET 
            first_name = ?, last_name = ?, gender = ?, date_of_birth = ?, marital_status = ?,
            phone = ?, email = ?, address = ?, department_id = ?, designation_id = ?, 
            joining_date = ?, employment_type = ?, reporting_manager_id = ?, work_location = ?, status = ?
            WHERE id = ?");
        
        $stmt->execute([
            $data['first_name'], $data['last_name'], $data['gender'],
            $data['date_of_birth'] ?: null, $data['marital_status'], $data['phone'],
            $data['email'], $data['address'], $data['department_id'], $data['designation_id'],
            $data['joining_date'], $data['employment_type'], $data['reporting_manager_id'] ?: null,
            $data['work_location'], $data['status'], $id
        ]);
        
        logAudit($_SESSION['user_id'], 'update', 'employees');
        flash('success', 'Employee updated successfully');
        to('modules/employees/index.php');
    

    
    $emp = array_merge($emp, $data);
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Employees
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit Employee</h2>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?= sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="employeeForm" class="space-y-6">
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></labe>
                    <input type="text" id="first_name" name="first_name" value="<?= old('first_name', $emp['first_name']) ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= old('last_name', $emp['last_name'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-600">*</span></labe>
                    <select id="gender" name="gender" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="male" <?= ($emp['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($emp['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($emp['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= old('date_of_birth', $emp['date_of_birth'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marital Status</label>
                    <select name="marital_status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="single" <?= ($emp['marital_status'] ?? 'single') === 'single' ? 'selected' : '' ?>>Single</option>
                        <option value="married" <?= ($emp['marital_status'] ?? 'single') === 'married' ? 'selected' : '' ?>>Married</option>
                        <option value="divorced" <?= ($emp['marital_status'] ?? 'single') === 'divorced' ? 'selected' : '' ?>>Divorced</option>
                        <option value="widowed" <?= ($emp['marital_status'] ?? 'single') === 'widowed' ? 'selected' : '' ?>>Widowed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= old('phone', $emp['phone'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></labe>
                    <input type="email" id="email" name="email" value="<?= old('email', $emp['email']) ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" value="<?= old('address', $emp['address'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Employment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-600">*</span></labe>
                    <select id="department_id" name="department_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= ($emp['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>><?= sanitize($dept['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Designation <span class="text-red-600">*</span></labe>
                    <select id="designation_id" name="designation_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($designations as $desig): ?>
                        <option value="<?= $desig['id'] ?>" <?= ($emp['designation_id'] ?? '') == $desig['id'] ? 'selected' : '' ?>><?= sanitize($desig['designation_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date <span class="text-red-600">*</span></labe>
                    <input type="date" id="joining_date" name="joining_date" value="<?= old('joining_date', $emp['joining_date'] ?? '') ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
                    <select name="employment_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="full_time" <?= ($emp['employment_type'] ?? 'full_time') === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                        <option value="part_time" <?= ($emp['employment_type'] ?? 'full_time') === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                        <option value="contract" <?= ($emp['employment_type'] ?? 'full_time') === 'contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="intern" <?= ($emp['employment_type'] ?? 'full_time') === 'intern' ? 'selected' : '' ?>>Intern</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Manager</label>
                    <select name="reporting_manager_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $mgr): ?>
                        <option value="<?= $mgr['id'] ?>" <?= ($emp['reporting_manager_id'] ?? '') == $mgr['id'] ? 'selected' : '' ?>><?= sanitize($mgr['first_name'] . ' ' . $mgr['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Work Location</label>
                    <input type="text" id="work_location" name="work_location" value="<?= old('work_location', $emp['work_location'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="active" <?= ($emp['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($emp['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="terminated" <?= ($emp['status'] ?? 'active') === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                        <option value="resigned" <?= ($emp['status'] ?? 'active') === 'resigned' ? 'selected' : '' ?>>Resigned</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="pt-4 border-t">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Employee
            </button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    // Custom validation method
    $.validator.addMethod("lettersOnly", function (value, element) {
        return this.optional(element) || /^[A-Za-z]+(?: [A-Za-z]+)*$/.test(value);
    }, "Only letters and spaces are allowed");


    // First Name - letters and spaces only
    $("#first_name").on("input", function () {
        this.value = this.value.replace(/[^A-Za-z ]/g, "");
    });


    // Last Name - letters and spaces only
    $("#last_name").on("input", function () {
        this.value = this.value.replace(/[^A-Za-z ]/g, "");
    });


    // Phone - numbers only
    $("#phone").on("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "");
    });


    // Email - allowed characters only
    $("#email").on("input", function () {
        this.value = this.value.replace(/[^A-Za-z0-9._%+\-@]/g, "");
    });


    // Work Location - letters, numbers and spaces
    $("#work_location").on("input", function () {
        this.value = this.value.replace(/[^A-Za-z0-9 ]/g, "");
    });


    // Edit Employee Form Validation
    $("#employeeForm").validate({

        rules: {

            first_name: {
                required: true,
                minlength: 2,
                maxlength: 100,
                lettersOnly: true
            },

            last_name: {
                maxlength: 100,
                lettersOnly: true
            },

            gender: {
                required: true
            },

            date_of_birth: {
                required: false
            },

            marital_status: {
                required: true
            },

            phone: {
                digits: true,
                minlength: 10,
                maxlength: 15
            },

            email: {
                required: true,
                email: true,
                maxlength: 150
            },

            address: {
                maxlength: 500
            },

            department_id: {
                required: true
            },

            designation_id: {
                required: true
            },

            joining_date: {
                required: true
            },

            employment_type: {
                required: true
            },

            reporting_manager_id: {
                digits: true
            },

            work_location: {
                maxlength: 100
            },

            status: {
                required: true
            }
        },


        messages: {

            first_name: {
                required: "First Name is required",
                minlength: "First Name must be at least 2 characters",
                maxlength: "First Name must not exceed 100 characters",
                lettersOnly: "First Name can contain letters and spaces only"
            },

            last_name: {
                maxlength: "Last Name must not exceed 100 characters",
                lettersOnly: "Last Name can contain letters and spaces only"
            },

            gender: {
                required: "Please select gender"
            },

            marital_status: {
                required: "Please select marital status"
            },

            phone: {
                digits: "Phone number can contain numbers only",
                minlength: "Phone number must be at least 10 digits",
                maxlength: "Phone number must not exceed 15 digits"
            },

            email: {
                required: "Email is required",
                email: "Please enter a valid email address",
                maxlength: "Email must not exceed 150 characters"
            },

            address: {
                maxlength: "Address must not exceed 500 characters"
            },

            department_id: {
                required: "Please select a department"
            },

            designation_id: {
                required: "Please select a designation"
            },

            joining_date: {
                required: "Joining Date is required"
            },

            employment_type: {
                required: "Please select employment type"
            },

            reporting_manager_id: {
                digits: "Invalid manager selection"
            },

            work_location: {
                maxlength: "Work Location must not exceed 100 characters"
            },

            status: {
                required: "Please select status"
            }
        },


        errorElement: "span",

        errorClass: "text-red-500 text-sm mt-1 block",


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