<?php
$pageTitle = 'Create Person';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';
require_once __DIR__ . '/../contractors/document_service.php';
require_once __DIR__ . '/../contractors/contract_service.php';

if (!hasPermission('people_management.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

// Get departments & designations for employee fields
$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$designations = $db->query("SELECT * FROM designations WHERE status = 'active' ORDER BY designation_name")->fetchAll();
$managers = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$roles = $db->query("SELECT * FROM roles WHERE deleted_at IS NULL")->fetchAll();

function generatePersonCode($db)
{
    $year = date('Y');
    $prefix = 'PER' . $year;
    $stmt = $db->query("SELECT person_code FROM people WHERE person_code LIKE '$prefix%' ORDER BY person_code DESC LIMIT 1");
    $last = $stmt->fetch();
    if ($last) {
        $num = (int) substr($last['person_code'], -4) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

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
    $category = $_POST['category'] ?? '';
    $createLogin = !empty($_POST['create_login']);
    $assignRole = $_POST['assign_role'] ?? '';

    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'category' => $category,
        'customer_id' => $_POST['customer_id'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'status' => $_POST['status'] ?? 'active',
    ];

    $validationRules = [
        'first_name' => 'required|min:2|max:100',
        'category' => 'required',
        'email' => 'required|email',
    ];

    $errors = validate($data, $validationRules);

    // Validate email uniqueness
    if (empty($errors['email'])) {
        $check = $db->prepare("SELECT id FROM people WHERE email = ?");
        $check->execute([$data['email']]);
        if ($check->fetch()) {
            $errors['email'] = 'A person with this email already exists';
        }
    }

    // Login password validation
    if ($createLogin) {
        $loginName = $_POST['login_name'] ?? ($data['first_name'] . ' ' . $data['last_name']);
        $loginEmail = $_POST['login_email'] ?? $data['email'];
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }
        // Check login email uniqueness
        if (empty($errors['email'])) {
            $checkUser = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
            $checkUser->execute([$loginEmail]);
            if ($checkUser->fetch()) {
                $errors['login_email'] = 'A user with this email already exists';
            }
        }
    }

    // Employee extra validation
    if ($category === 'employee') {
        if (empty($_POST['department_id'])) $errors['department_id'] = 'Department is required';
        if (empty($_POST['designation_id'])) $errors['designation_id'] = 'Designation is required';
        if (empty($_POST['joining_date'])) $errors['joining_date'] = 'Joining date is required';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/people_management/create.php');
    }


    $maxRetries = 5;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $db->beginTransaction();

            $personCode = generatePersonCode($db);
            $userId = null;
            $employeeId = null;

            // 1. Create login credentials if requested
            if ($createLogin) {
                $loginEmail = $_POST['login_email'] ?? $data['email'];
                $loginName = $_POST['login_name'] ?? ($data['first_name'] . ' ' . $data['last_name']);
                $password = $_POST['password'] ?? '';

                $userStmt = $db->prepare("INSERT INTO users (name, email, password, status, created_by) VALUES (?, ?, ?, 'active', ?)");
                $userStmt->execute([$loginName, $loginEmail, password_hash($password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                $userId = $db->lastInsertId();

                if ($assignRole) {
                    $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $roleStmt->execute([$userId, $assignRole]);
                }
            }

            // 2. Create employee record if category = employee
            if ($category === 'employee') {
                $empCode = generateEmployeeCode($db);
                $empStmt = $db->prepare("INSERT INTO employees (
                    employee_code, first_name, last_name, gender, date_of_birth,
                    phone, email, address, department_id, designation_id, joining_date,
                    employment_type, reporting_manager_id, work_location, status, user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $empStmt->execute([
                    $empCode,
                    $data['first_name'],
                    $data['last_name'] ?: null,
                    $data['gender'] ?: 'male',
                    $data['date_of_birth'] ?: null,
                    $data['phone'] ?: null,
                    $data['email'],
                    $data['address'] ?: null,
                    $_POST['department_id'] ?: null,
                    $_POST['designation_id'] ?: null,
                    $_POST['joining_date'] ?: null,
                    $_POST['employment_type'] ?? 'full_time',
                    $_POST['reporting_manager_id'] ?: null,
                    $_POST['work_location'] ?: null,
                    $data['status'] === 'active' ? 'active' : 'inactive',
                    $userId
                ]);
                $employeeId = $db->lastInsertId();
            }

            // 2b. Create the contractor register entry if category = contractor
            $contractorId = null;
            if ($category === 'contractor') {
                $ctrCode = contractorAllocateNumber($db, 'contractor', 'contractors', 'contractor_code');
                $ctrStmt = $db->prepare("INSERT INTO contractors (
                    contractor_code, first_name, last_name, gender, date_of_birth, email, phone, address,
                    work_location, status, notes, user_id, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ctrStmt->execute([
                    $ctrCode,
                    $data['first_name'],
                    $data['last_name'] ?: null,
                    $data['gender'] ?: null,
                    $data['date_of_birth'] ?: null,
                    $data['email'] ?: null,
                    $data['phone'] ?: null,
                    $data['address'] ?: null,
                    $_POST['work_location'] ?: null,
                    $data['status'] === 'active' ? 'active' : 'suspended',
                    $data['notes'] ?: null,
                    $userId,
                    $_SESSION['user_id'],
                ]);
                $contractorId = (int) $db->lastInsertId();
            }

            // 3. Create the person record
            $personStmt = $db->prepare("INSERT INTO people (
                person_code, first_name, last_name, email, phone, address,
                gender, date_of_birth, category, user_id, employee_id, customer_id, contractor_id, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $personStmt->execute([
                $personCode,
                $data['first_name'],
                $data['last_name'] ?: null,
                $data['email'],
                $data['phone'] ?: null,
                $data['address'] ?: null,
                $data['gender'] ?: null,
                $data['date_of_birth'] ?: null,
                $data['category'],
                $userId,
                $employeeId,
                $category === 'customer' && !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
                $contractorId,
                $data['notes'] ?: null,
                $data['status']
            ]);

            $db->commit();

            logAudit($_SESSION['user_id'], 'create', 'people_management');
            if ($contractorId) {
                flash('success', 'Person created and registered as a contractor (CTR #' . $contractorId . '). Add their right-to-work documents on the contractor profile.');
            } else {
                flash('success', 'Person created successfully');
            }
            to('modules/people_management/index.php');
        } catch (PDOException $e) {
            $db->rollBack();
            if ($e->errorInfo[1] == 1062 && $attempt < $maxRetries - 1) {
                continue;
            }
            $errors['_general'] = 'Failed to create person: ' . $e->getMessage();
            break;
        } catch (Exception $e) {
            $db->rollBack();
            $errors['_general'] = 'Failed to create person: ' . $e->getMessage();
            break;
        }
    }
}
?>
<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to People
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Person</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $field => $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="personForm" class="space-y-6">
        <!-- Identity -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Identity Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></labe>
                    <input type="text" id="first_name" name="first_name" value="<?= old('first_name') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= old('last_name') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select name="gender" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
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
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></labe>
                    <input type="email" name="email" value="<?= old('email') ?>" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="<?= old('phone') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" value="<?= old('address') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Category -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Category</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php
                $cats = [
                    'employee' => ['icon' => 'fa-user-tie', 'desc' => 'Full HR record, appears in Employees module', 'color' => 'blue'],
                    'customer' => ['icon' => 'fa-handshake', 'desc' => 'Individual customer / client contact', 'color' => 'purple'],
                    'contractor' => ['icon' => 'fa-file-signature', 'desc' => 'External contractor or consultant', 'color' => 'orange'],
                ];
                foreach ($cats as $val => $cat): ?>
                    <label class="relative border rounded-lg p-4 cursor-pointer hover:border-blue-400 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="category" value="<?= $val ?>" class="sr-only"
                            <?= old('category') === $val || ($val === 'employee' && !old('category')) ? 'checked' : '' ?>
                            onchange="toggleCategory(this.value)" required>
                        <div class="text-center">
                            <i class="fas <?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 text-2xl mb-2"></i>
                            <div class="font-medium"><?= ucfirst($val) ?></div>
                            <div class="text-xs text-gray-500 mt-1"><?= $cat['desc'] ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Customer-only fields -->
        <?php $customerOptions = $db->query("SELECT id, client_code, company_name FROM clients WHERE deleted_at IS NULL AND status IN ('active','prospect') ORDER BY company_name")->fetchAll(); ?>
        <div id="customerFields" class="mt-6" style="<?= old('category') === 'customer' ? '' : 'display:none' ?>">
            <div class="border-t pt-4">
                <h2 class="text-lg font-semibold mb-1">Customer Link</h2>
                <p class="text-xs text-gray-500 mb-3">Attach this person to an existing organisation in the Customer Master.</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Linked Customer</label>
                <div class="flex gap-2">
                    <select name="customer_id" class="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">— Not linked —</option>
                        <?php foreach ($customerOptions as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= (string) old('customer_id') === (string) $co['id'] ? 'selected' : '' ?>>
                            <?= sanitize($co['company_name']) ?> (<?= sanitize($co['client_code']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (hasPermission('customers.create')): ?>
                    <a href="<?= url('modules/customers/form.php') ?>" target="_blank" rel="noopener"
                       class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 whitespace-nowrap">
                        <i class="fas fa-plus mr-1"></i>New
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contractor-only fields -->
        <div id="contractorFields" class="mt-6" style="<?= old('category') === 'contractor' ? '' : 'display:none' ?>">
            <div class="border-t pt-4">
                <h2 class="text-lg font-semibold mb-1">Contractor Register</h2>
                <p class="text-xs text-gray-500 mb-3">
                    Saving creates a register entry (CTR code) that holds this person's right-to-work
                    documents, engagement contracts and rates.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Work Location</label>
                        <input type="text" name="work_location" value="<?= old('work_location') ?>" placeholder="Dubai"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="text-xs text-gray-500 self-end">
                        <i class="fas fa-circle-info mr-1"></i>
                        Nationality, Emirates ID, passport, visa, work permit and labour card are completed on
                        the contractor profile after saving.
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee-only fields -->
        <div id="employeeFields" style="<?= old('category') === 'employee' || !old('category') ? '' : 'display:none' ?>">
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Employment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-600">*</span></labe>
                    <select name="department_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= old('department_id') == $dept['id'] ? 'selected' : '' ?>><?= sanitize($dept['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Designation <span class="text-red-600">*</span></labe>
                    <select name="designation_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Designation</option>
                        <?php foreach ($designations as $desig): ?>
                            <option value="<?= $desig['id'] ?>" <?= old('designation_id') == $desig['id'] ? 'selected' : '' ?>><?= sanitize($desig['designation_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="joining_date" value="<?= old('joining_date') ?>"
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
                    <input type="text" name="work_location" value="<?= old('work_location') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Login Credentials -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">System Access</h3>
            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="create_login" value="1" class="rounded text-blue-600"
                        <?= old('create_login') ? 'checked' : '' ?>
                        onchange="toggleLoginFields(this.checked)">
                    <span class="ml-2 text-sm font-medium text-gray-700">Create login credentials</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">When enabled, this person can log in to the ERP system.</p>
            </div>
            <div id="loginFields" style="<?= old('create_login') ? '' : 'display:none' ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Login Name</label>
                        <input type="text" name="login_name" value="<?= old('login_name') ?>"
                            placeholder="Full name for login"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Defaults to person's full name</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Login Email</label>
                        <input type="email" name="login_email" value="<?= old('login_email') ?>"
                            placeholder="Email for login"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Defaults to person's email</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-600">*</span></labe>
                        <input type="password" name="password" minlength="6"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign Role</label>
                        <select name="assign_role" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                            <option value="">No role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= old('assign_role') == $role['id'] ? 'selected' : '' ?>><?= sanitize($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes & Status -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Additional</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>
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

        <div class="pt-4 border-t">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Person
            </button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>


<script>
    function toggleCategory(val) {
        var empFields = document.getElementById('employeeFields');
        if (empFields) {
            empFields.style.display = val === 'employee' ? '' : 'none';
        }
        var custFields = document.getElementById('customerFields');
        if (custFields) {
            custFields.style.display = val === 'customer' ? '' : 'none';
        }
        var ctrFields = document.getElementById('contractorFields');
        if (ctrFields) {
            ctrFields.style.display = val === 'contractor' ? '' : 'none';
        }
    }

    function toggleLoginFields(checked) {
        var loginFields = document.getElementById('loginFields');
        if (loginFields) {
            loginFields.style.display = checked ? '' : 'none';
        }
    }
</script>
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