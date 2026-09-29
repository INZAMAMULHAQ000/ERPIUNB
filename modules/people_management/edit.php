<?php
$pageTitle = 'Edit Person';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('people_management.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM people WHERE id = ?");
$stmt->execute([$id]);
$person = $stmt->fetch();

if (!$person) {
    flash('error', 'Person not found', 'error');
    to('modules/people_management/index.php');
}
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$designations = $db->query("SELECT * FROM designations WHERE status = 'active' ORDER BY designation_name")->fetchAll();
$managers = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$roles = $db->query("SELECT * FROM roles WHERE deleted_at IS NULL")->fetchAll();

// Load linked employee data
$employee = null;
if ($person['employee_id']) {
    $empStmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
    $empStmt->execute([$person['employee_id']]);
    $employee = $empStmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'category' => $_POST['category'] ?? $person['category'],
        'notes' => $_POST['notes'] ?? '',
        'status' => $_POST['status'] ?? 'active',
    ];

    $validationRules = [
        'first_name' => 'required|min:2|max:100',
        'email' => 'required|email',
    ];

    // Don't require category change validation
    $errors = validate($data, $validationRules);

    // Check email uniqueness (exclude self)
    if (empty($errors['email'])) {
        $check = $db->prepare("SELECT id FROM people WHERE email = ? AND id != ?");
        $check->execute([$data['email'], $id]);
        if ($check->fetch()) {
            $errors['email'] = 'A person with this email already exists';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/people_management/edit.php?id=' . $id);
    }

        try {
            $db->beginTransaction();
    
            // Update person record
            $updateStmt = $db->prepare("UPDATE people SET
                first_name = ?, last_name = ?, email = ?, phone = ?, address = ?,
                gender = ?, date_of_birth = ?, category = ?, notes = ?, status = ?
                WHERE id = ?");
    
            $updateStmt->execute([
                $data['first_name'],
                $data['last_name'] ?: null,
                $data['email'],
                $data['phone'] ?: null,
                $data['address'] ?: null,
                $data['gender'] ?: null,
                $data['date_of_birth'] ?: null,
                $data['category'],
                $data['notes'] ?: null,
                $data['status'],
                $id
            ]);
    
            // Update linked employee record if exists
            if ($person['employee_id'] && $person['category'] === 'employee') {
                $empUpdate = $db->prepare("UPDATE employees SET
                    first_name = ?, last_name = ?, email = ?, phone = ?, address = ?,
                    gender = ?, date_of_birth = ?,
                    department_id = ?, designation_id = ?, joining_date = ?,
                    employment_type = ?, reporting_manager_id = ?, work_location = ?,
                    status = ?
                    WHERE id = ?");
    
                $empUpdate->execute([
                    $data['first_name'],
                    $data['last_name'] ?: null,
                    $data['email'],
                    $data['phone'] ?: null,
                    $data['address'] ?: null,
                    $data['gender'] ?: 'male',
                    $data['date_of_birth'] ?: null,
                    $_POST['department_id'] ?: null,
                    $_POST['designation_id'] ?: null,
                    $_POST['joining_date'] ?: null,
                    $_POST['employment_type'] ?? 'full_time',
                    $_POST['reporting_manager_id'] ?: null,
                    $_POST['work_location'] ?: null,
                    $data['status'] === 'active' ? 'active' : 'inactive',
                    $person['employee_id']
                ]);
            }
    
            $db->commit();
    
            logAudit($_SESSION['user_id'], 'edit', 'people_management');
            flash('success', 'Person updated successfully');
            to('modules/people_management/view.php?id=' . $id);
    
        } catch (Exception $e) {
            $db->rollBack();
            $errors['_general'] = 'Failed to update: ' . $e->getMessage();
        }
    

}
?>
<div class="mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Person
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit Person — <?= sanitize($person['first_name'] . ' ' . $person['last_name']) ?></h2>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $field => $error): ?>
            <li><?= sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Identity -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Identity Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></labe>
                    <input type="text" name="first_name" value="<?= old('first_name') ?: sanitize($person['first_name']) ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="<?= old('last_name') ?: sanitize($person['last_name'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select name="gender" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select</option>
                        <option value="male" <?= (old('gender') ?: $person['gender']) === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= (old('gender') ?: $person['gender']) === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= (old('gender') ?: $person['gender']) === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= old('date_of_birth') ?: $person['date_of_birth'] ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></labe>
                    <input type="email" name="email" value="<?= old('email') ?: sanitize($person['email']) ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="<?= old('phone') ?: sanitize($person['phone'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" value="<?= old('address') ?: sanitize($person['address'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Category -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Category</h3>
            <div>
                <span class="px-3 py-1.5 text-sm rounded bg-gray-100 text-gray-700 inline-block">
                    <i class="fas fa-info-circle mr-1"></i>
                    <?php
                    $catLabels = ['employee' => 'Employee', 'customer' => 'Customer', 'contractor' => 'Contractor'];
                    ?>
                    <?= $catLabels[$person['category']] ?? $person['category'] ?>
                </span>
                <p class="text-xs text-gray-500 mt-1">Category cannot be changed after creation. Create a new record if the category needs to change.</p>
            </div>
        </div>

        <!-- Employee-only fields -->
        <?php if ($person['category'] === 'employee'): ?>
        <div id="employeeFields">
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Employment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-600">*</span></labe>
                    <select name="department_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"
                            <?= (old('department_id') ?: ($employee['department_id'] ?? '')) == $dept['id'] ? 'selected' : '' ?>>
                            <?= sanitize($dept['department_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Designation <span class="text-red-600">*</span></labe>
                    <select name="designation_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Designation</option>
                        <?php foreach ($designations as $desig): ?>
                        <option value="<?= $desig['id'] ?>"
                            <?= (old('designation_id') ?: ($employee['designation_id'] ?? '')) == $desig['id'] ? 'selected' : '' ?>>
                            <?= sanitize($desig['designation_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="joining_date"
                        value="<?= old('joining_date') ?: ($employee['joining_date'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
                    <select name="employment_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="full_time" <?= (old('employment_type') ?: ($employee['employment_type'] ?? '')) === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                        <option value="part_time" <?= (old('employment_type') ?: ($employee['employment_type'] ?? '')) === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                        <option value="contract" <?= (old('employment_type') ?: ($employee['employment_type'] ?? '')) === 'contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="intern" <?= (old('employment_type') ?: ($employee['employment_type'] ?? '')) === 'intern' ? 'selected' : '' ?>>Intern</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Manager</label>
                    <select name="reporting_manager_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $mgr): ?>
                        <option value="<?= $mgr['id'] ?>"
                            <?= (old('reporting_manager_id') ?: ($employee['reporting_manager_id'] ?? '')) == $mgr['id'] ? 'selected' : '' ?>>
                            <?= sanitize($mgr['first_name'] . ' ' . $mgr['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Work Location</label>
                    <input type="text" name="work_location"
                        value="<?= old('work_location') ?: sanitize($employee['work_location'] ?? '') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes & Status -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Additional</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?: sanitize($person['notes'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="active" <?= (old('status') ?: $person['status']) === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (old('status') ?: $person['status']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Person
            </button>
            <a href="view.php?id=<?= $id ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        $("#first_name,#last_name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, '');
        });

        $("#email,#login_email").on("input", function() {
           this.value = this.value.replace(/[^A-Za-z0-9._%+\-@]/g, '');
        });

        $("#personForm").validate({

            rules: {

                first_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                category: {
                    required: true
                },

                email: {
                    required: true,
                    email: true
                },

                phone: {
                    maxlength: 13
                },

                department_id: {
                    required: function() {
                        return $('input[name="category"]:checked').val() === 'employee';
                    }
                },

                designation_id: {
                    required: function() {
                        return $('input[name="category"]:checked').val() === 'employee';
                    }
                },

                joining_date: {
                    required: function() {
                        return $('input[name="category"]:checked').val() === 'employee';
                    }
                },

                login_email: {
                    email: true
                },

                password: {
                    minlength: 6
                }
            },

            messages: {

                first_name: {
                    required: "First Name is required",
                    minlength: "First Name must be at least 2 characters",
                    maxlength: "First Name must not exceed 100 characters"
                },

                category: {
                    required: "Please select a category"
                },

                email: {
                    required: "Email is required",
                    email: "Please enter a valid email address"
                },

                phone: {
                    maxlength: "Phone number must not exceed 20 characters"
                },

                department_id: {
                    required: "Department is required"
                },

                designation_id: {
                    required: "Designation is required"
                },

                joining_date: {
                    required: "Joining Date is required"
                },

                login_email: {
                    email: "Please enter a valid login email"
                },

                password: {
                    minlength: "Password must be at least 6 characters"
                }
            },

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

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
