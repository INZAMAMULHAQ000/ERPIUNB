<?php
$pageTitle = 'Create Employee';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';
require_once __DIR__ . '/document_service.php';

if (!hasPermission('employees.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$designations = $db->query("SELECT * FROM designations WHERE status = 'active' ORDER BY designation_name")->fetchAll();
$managers = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

// Create mode renders the shared identification block with no record behind it yet.
$emp = [];
$isEdit = false;
$employeeId = 0;
$slotDocs = [];
$photoPath = '';

function generateEmployeeCode($db)
{
    $year = date('Y');
    $prefix = 'EMP' . $year;
    $stmt = $db->query("SELECT employee_code FROM employees WHERE employee_code LIKE '$prefix%' ORDER BY employee_code DESC LIMIT 1");
    $last = $stmt->fetch();
    if ($last) {
        $num = (int) substr($last['employee_code'], -4) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session expired. Please submit the form again.');
        to('modules/employees/create.php');
    }

    $data = array_merge([
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
    ], employeeIdInput($_POST));

    $errors = validate($data, [
        'first_name' => 'required|min:2|max:100',
        'gender' => 'required',
        'email' => 'required|email',
        'department_id' => 'required',
        'designation_id' => 'required',
        'joining_date' => 'required'
    ]);
    $errors += employeeIdErrors($data, $_POST);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM employees WHERE email = ?");
        $checkStmt->execute([$data['email']]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/employees/create.php');
    }

    $maxRetries = 5;
    $employeeId = 0;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $employeeCode = generateEmployeeCode($db);

            $stmt = $db->prepare("INSERT INTO employees (
                employee_code, first_name, last_name, gender, date_of_birth, marital_status,
                nationality, passport_number, passport_expiry, visa_type, visa_number, visa_expiry,
                emirates_id_number, emirates_id_expiry,
                joining_date, contract_number, contract_start_date, contract_end_date,
                phone, email, address, department_id, designation_id,
                employment_type, reporting_manager_id, work_location, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $employeeCode,
                $data['first_name'],
                $data['last_name'],
                $data['gender'],
                $data['date_of_birth'] ?: null,
                $data['marital_status'],
                $data['nationality'] ?: null,
                $data['passport_number'] ?: null,
                $data['passport_expiry'] ?: null,
                $data['visa_type'] ?: null,
                $data['visa_number'] ?: null,
                $data['visa_expiry'] ?: null,
                $data['emirates_id_number'] ?: null,
                $data['emirates_id_expiry'] ?: null,
                $data['joining_date'],
                $data['contract_number'] ?: null,
                $data['contract_start_date'] ?: null,
                $data['contract_end_date'] ?: null,
                $data['phone'],
                $data['email'],
                $data['address'],
                $data['department_id'],
                $data['designation_id'],
                $data['employment_type'],
                $data['reporting_manager_id'] ?: null,
                $data['work_location'],
                $data['status']
            ]);

            $employeeId = (int) $db->lastInsertId();
            break;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062 && $attempt < $maxRetries - 1) {
                continue;
            }
            $errors['_general'] = 'Failed to create employee: ' . $e->getMessage();
            break;
        } catch (Exception $e) {
            $errors['_general'] = 'Failed to create employee: ' . $e->getMessage();
            break;
        }
    }

    if ($employeeId > 0) {
        logAudit($_SESSION['user_id'], 'create', 'employees');

        // The record now exists, so the scans can be filed against it. Anything
        // that fails here is reported on the edit form rather than rolled back.
        $uploadErrors = [];
        foreach (array_keys(employeeDocSlots()) as $slot) {
            $message = employeeSaveSlot($db, $employeeId, $slot, $_FILES[$slot] ?? null, !empty($_POST['remove_' . $slot]), $_SESSION['user_id']);
            if ($message !== null) {
                $uploadErrors[$slot] = $message;
            }
        }
        $photoMessage = employeeSavePhoto($db, $employeeId, $_FILES['photo'] ?? null, !empty($_POST['remove_photo']), '');
        if ($photoMessage !== null) {
            $uploadErrors['photo'] = $photoMessage;
        }

        if ($uploadErrors) {
            flash('error', 'Employee created, but these files were not accepted: ' . implode(' ', $uploadErrors));
            to('modules/employees/edit.php?id=' . $employeeId);
        }

        flash('success', 'Employee created successfully');
        to('modules/employees/index.php');
    }
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Employees
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Employee</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="employeeForm" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= old('first_name') ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= old('last_name') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-600">*</span></label>
                    <select id="gender" name="gender" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select</option>
                        <option value="male" <?= old('gender') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= old('gender') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= old('gender') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= old('date_of_birth') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marital Status</label>
                    <select name="marital_status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="single" <?= old('marital_status') === 'single' ? 'selected' : '' ?>>Single</option>
                        <option value="married" <?= old('marital_status') === 'married' ? 'selected' : '' ?>>Married</option>
                        <option value="divorced" <?= old('marital_status') === 'divorced' ? 'selected' : '' ?>>Divorced</option>
                        <option value="widowed" <?= old('marital_status') === 'widowed' ? 'selected' : '' ?>>Widowed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= old('phone') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" value="<?= old('address') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Employment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-600">*</span></label>
                    <select id="department_id" name="department_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= old('department_id') == $dept['id'] ? 'selected' : '' ?>><?= sanitize($dept['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Designation <span class="text-red-600">*</span></label>
                    <select id="designation_id" name="designation_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Designation</option>
                        <?php foreach ($designations as $desig): ?>
                            <option value="<?= $desig['id'] ?>" <?= old('designation_id') == $desig['id'] ? 'selected' : '' ?>><?= sanitize($desig['designation_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date <span class="text-red-600">*</span></label>
                    <input type="date" id="joining_date" name="joining_date" value="<?= old('joining_date') ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
                    <select name="employment_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="full_time" <?= old('employment_type') === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                        <option value="part_time" <?= old('employment_type') === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                        <option value="contract" <?= old('employment_type') === 'contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="intern" <?= old('employment_type') === 'intern' ? 'selected' : '' ?>>Intern</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Manager</label>
                    <select name="reporting_manager_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?= $mgr['id'] ?>" <?= old('reporting_manager_id') == $mgr['id'] ? 'selected' : '' ?>><?= sanitize($mgr['first_name'] . ' ' . $mgr['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Work Location</label>
                    <input type="text" id="work_location" name="work_location" value="<?= old('work_location') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="active" <?= old('status') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/identification_form.php'; ?>

        <div class="pt-4 border-t">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Employee
            </button>
            <a href="index.php"
                class="ml-4 px-6 py-2 text-gray-600 border border-gray-200 rounded hover:bg-gray-100 hover:text-gray-900 hover:border-gray-400 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<style>
    #employeeForm label.error,
    #employeeForm span.error {
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

    #employeeForm select.error,
    #employeeForm input.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #employeeForm select.valid,
    #employeeForm input.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>
<script>
    $(document).ready(function() {

        /*
         * Custom validation method
         * First Name / Last Name
         * Only English letters and spaces allowed
         */
        $.validator.addMethod("lettersOnly", function(value, element) {
            return this.optional(element) || /^[A-Za-z]+(?: [A-Za-z]+)*$/.test(value);
        }, "Only letters and spaces are allowed");


        /*
         * First Name
         * Remove numbers and special characters while typing/pasting
         */
        $("#first_name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, "");
        });


        /*
         * Last Name
         * Remove numbers and special characters while typing/pasting
         */
        $("#last_name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, "");
        });


        /*
         * Phone
         * Numbers only
         */
        $("#phone").on("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "");
        });


        /*
         * Email
         * Allow only valid email characters
         */
        $("#email").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z0-9._%+\-@]/g, "");
        });


        /*
         * Work Location
         * Letters, numbers and spaces only
         */
        $("#work_location").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z0-9 ]/g, "");
        });


        /*
         * Nationality
         * Letters and spaces only
         */
        $("#nationality").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, "");
        });


        /*
         * Passport / Visa Number
         * Letters and numbers only, stored uppercase
         */
        $("#passport_number, #visa_number").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z0-9]/g, "").toUpperCase();
        });


        /*
         * Emirates ID Number
         * Digits only
         */
        $("#emirates_id_number").on("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "");
        });


        /*
         * Contract Number
         * Letters, numbers, dashes and slashes, stored uppercase
         */
        $("#contract_number").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z0-9\/-]/g, "").toUpperCase();
        });


        /*
         * Document scans
         * Client-side size gate so an oversized file is rejected before upload
         */
        $("#passport_copy, #visa_copy, #emirates_id_copy, #employment_contract_copy").on("change", function() {
            if (this.files.length && this.files[0].size > 5 * 1024 * 1024) {
                alert(this.files[0].name + " is larger than 5 MB.");
                this.value = "";
            }
        });

        $("#photo").on("change", function() {
            if (this.files.length && this.files[0].size > 2 * 1024 * 1024) {
                alert(this.files[0].name + " is larger than 2 MB.");
                this.value = "";
            }
        });


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
                    date: true
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

                nationality: {
                    maxlength: 100,
                    lettersOnly: true
                },

                passport_number: {
                    maxlength: 50
                },

                passport_expiry: {
                    date: true
                },

                visa_number: {
                    maxlength: 50
                },

                visa_expiry: {
                    date: true
                },

                emirates_id_number: {
                    digits: true,
                    maxlength: 20
                },

                emirates_id_expiry: {
                    date: true
                },

                contract_number: {
                    maxlength: 50
                },

                contract_start_date: {
                    date: true
                },

                contract_end_date: {
                    date: true
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

                gender: {
                    required: "Please select gender"
                },

                marital_status: {
                    required: "Please select marital status"
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

                nationality: {
                    lettersOnly: "Nationality can contain letters and spaces only",
                    maxlength: "Nationality must not exceed 100 characters"
                },

                passport_number: {
                    maxlength: "Passport Number must not exceed 50 characters"
                },

                visa_number: {
                    maxlength: "Visa Number must not exceed 50 characters"
                },

                emirates_id_number: {
                    digits: "Emirates ID Number can contain digits only",
                    maxlength: "Emirates ID Number must not exceed 20 digits"
                },

                status: {
                    required: "Please select status"
                }
            },

            errorElement: "span",
            errorClass: "error text-red-500 text-sm mt-1 block",

            errorPlacement: function(error, element) {
                if (element.is("select")) {
                    error.appendTo(element.parent());
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
<?php require __DIR__ . '/../../templates/document_viewer.php'; ?>

<?php require_once '../../includes/footer.php'; ?>