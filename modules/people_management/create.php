<?php
$pageTitle = 'Create Person';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';
require_once '../../helpers/people_onboarding.php';

if (!hasPermission('people_management.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/people_management/create.php');
    }

    $roles = peopleNormaliseRoles($_POST['roles'] ?? []);

    $gender = $_POST['gender'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
    $dateOfBirth = $dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : '';

    $data = [
        'first_name'    => trim($_POST['first_name'] ?? ''),
        'last_name'     => trim($_POST['last_name'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'gender'        => in_array($gender, ['male', 'female', 'other'], true) ? $gender : '',
        'date_of_birth' => $dateOfBirth,
        'notes'         => trim($_POST['notes'] ?? ''),
        'status'        => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
    ];

    // Person-type data is not collected here any more, so nothing role-specific
    // is validated here either — only what a shared identity actually needs.
    $errors = validate($data, [
        'first_name' => 'required|min:2|max:100',
        'email'      => 'required|email|max:255',
    ]);

    if (!$roles) {
        $errors['roles'] = 'Select at least one person type';
    }
    if ($dob !== '' && $dateOfBirth === '') {
        $errors['date_of_birth'] = 'Date of Birth must be a valid date';
    }

    // The directory is keyed on email, so an existing person is never
    // re-created: the workflow resumes on their record instead.
    $existing = null;
    if (!$errors && $data['email'] !== '') {
        $check = $db->prepare("SELECT id, first_name, last_name, person_code FROM people WHERE email = ? LIMIT 1");
        $check->execute([$data['email']]);
        $existing = $check->fetch();
    }

    // Optional login account: this belongs to the person, not to any one role,
    // so it stays on this screen.
    $createLogin = !empty($_POST['create_login']);
    $assignRole = (int) ($_POST['assign_role'] ?? 0);
    $loginName = trim($_POST['login_name'] ?? '') ?: ($data['first_name'] . ' ' . $data['last_name']);
    $loginEmail = trim($_POST['login_email'] ?? '') ?: $data['email'];
    $password = (string) ($_POST['password'] ?? '');

    if ($createLogin) {
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }
        if (filter_var($loginEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['login_email'] = 'Enter a valid login email';
        } elseif (empty($errors['password'])) {
            $checkUser = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
            $checkUser->execute([$loginEmail]);
            if ($checkUser->fetch()) {
                $errors['login_email'] = 'A user with this email already exists';
            }
        }
        if ($assignRole > 0) {
            $roleCheck = $db->prepare("SELECT id FROM roles WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $roleCheck->execute([$assignRole]);
            if (!$roleCheck->fetch()) {
                $errors['assign_role'] = 'Choose a valid role';
            }
        }
    }

    if ($errors) {
        saveFormState($errors);
        to('modules/people_management/create.php');
    }

    if ($existing) {
        peopleOnboardingForgetInput();
        flash('info', $existing['first_name'] . ' ' . (string) $existing['last_name']
            . ' is already in the directory (' . $existing['person_code'] . '). Their onboarding was reopened so the selected person types can be added to that record — no second person was created.');
        to(peopleOnboardingUrl($existing['id'], '&suggest=' . implode(',', $roles)));
    }

    $personCode = generatePersonCode($db);

    try {
        $db->beginTransaction();

        $userId = null;
        if ($createLogin) {
            $userStmt = $db->prepare("INSERT INTO users (name, email, password, status, created_by) VALUES (?, ?, ?, 'active', ?)");
            $userStmt->execute([$loginName, $loginEmail, password_hash($password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $userId = (int) $db->lastInsertId();

            if ($assignRole > 0) {
                $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $assignRole]);
            }
        }

        // One person, one record, however many roles were selected. The role
        // rows are what make this a multi-role directory entry.
        $personStmt = $db->prepare("INSERT INTO people (
            person_code, first_name, last_name, email, phone, address,
            gender, date_of_birth, category, user_id, notes, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $personStmt->execute([
            $personCode,
            $data['first_name'],
            $data['last_name'] ?: null,
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['address'] ?: null,
            $data['gender'] ?: null,
            $data['date_of_birth'] ?: null,
            peoplePrimaryRole($roles),
            $userId ?: null,
            $data['notes'] ?: null,
            $data['status'],
        ]);
        $personId = (int) $db->lastInsertId();

        peopleRolesInsert($db, $personId, $roles);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        if ($e->errorInfo[1] == 1062) {
            $errors['_general'] = 'That email is already registered to another person. Search the directory before creating them again.';
        } else {
            $errors['_general'] = 'Failed to create person: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        $db->rollBack();
        $errors['_general'] = 'Failed to create person: ' . $e->getMessage();
    }

    if (!$errors) {
        logAudit($_SESSION['user_id'], 'Created person: ' . $data['first_name'] . ' ' . $data['last_name'] . " ($personCode)", 'people_management');
        flash('wiz', 'Person created. Complete the ' . count($roles) . ' selected person type(s) in their own modules.', 'info');
        to(peopleOnboardingUrl($personId));
    }
}
?>
<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to People
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-1">Create New Person</h2>
    <p class="text-sm text-gray-500 mb-6">
        Identity and person types only. Employee, customer and contractor details are collected in
        their own modules, on the next screen, and saved against this one person.
    </p>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $field => $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="personForm" class="space-y-6" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Identity -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Identity</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="first_name">First Name <span class="text-red-600">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= old('first_name') ?>"
                        autocomplete="off" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= old('last_name') ?>"
                        autocomplete="off"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="gender">Gender</label>
                    <select name="gender" id="gender" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select</option>
                        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= old('gender') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= old('date_of_birth') ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email <span class="text-red-600">*</span></label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="off"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Used to recognise this person in every module, and to stop a duplicate being created.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= old('phone') ?>" autocomplete="off"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= old('address') ?>" autocomplete="off"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Person types -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-1 border-b pb-2">Person Types</h3>
            <p class="text-xs text-gray-500 mb-3">
                Choose one or more. All selected types are recorded on this same person — no duplicate
                records are created — and each one opens its own module for the remaining details.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php
                $cards = [
                    'employee'   => ['icon' => 'fa-user-tie',      'color' => 'blue',   'desc' => 'HRMS record — department, joining date, contract terms, documents'],
                    'customer'   => ['icon' => 'fa-handshake',     'color' => 'purple', 'desc' => 'Customer Master record — billing profile, credit terms, TRN'],
                    'contractor' => ['icon' => 'fa-file-signature', 'color' => 'orange', 'desc' => 'Contractor register — work-permit documents, contracts, rates'],
                ];
                $postedRoles = peopleNormaliseRoles(old('roles'));
                foreach ($cards as $val => $cat): ?>
                    <label class="relative border rounded-lg p-4 cursor-pointer hover:border-blue-400 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="checkbox" name="roles[]" value="<?= $val ?>" class="sr-only role-check"
                            <?= in_array($val, $postedRoles, true) ? 'checked' : '' ?>>
                        <div class="text-center">
                            <i class="fas <?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 text-2xl mb-2"></i>
                            <div class="font-medium"><?= ucfirst($val) ?></div>
                            <div class="text-xs text-gray-500 mt-1"><?= $cat['desc'] ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div id="rolesError" class="text-red-600 text-sm mt-2" style="display:none">
                <i class="fas fa-circle-exclamation mr-1"></i>Select at least one person type.
            </div>
        </div>

        <!-- Login credentials -->
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
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="login_name">Login Name</label>
                        <input type="text" id="login_name" name="login_name" value="<?= old('login_name') ?>"
                            placeholder="Full name for login"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="login_email">Login Email</label>
                        <input type="email" id="login_email" name="login_email" value="<?= old('login_email') ?>"
                            placeholder="Email for login" autocomplete="off"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="password">Password <span class="text-red-600">*</span></label>
                        <input type="password" id="password" name="password" minlength="6" autocomplete="new-password"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="assign_role">Assign Role</label>
                        <select id="assign_role" name="assign_role" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                            <option value="">No role</option>
                            <?php
                            foreach ($db->query("SELECT id, name FROM roles WHERE deleted_at IS NULL ORDER BY name") as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= (string) old('assign_role') === (string) $role['id'] ? 'selected' : '' ?>><?= sanitize($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes & status -->
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Additional</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="status">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="active" <?= old('status') !== 'inactive' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t flex items-center gap-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Continue to Onboarding
            </button>
            <a href="index.php" class="text-gray-600 px-6 py-2 border border-gray-200 rounded hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script>
    function toggleLoginFields(checked) {
        var loginFields = document.getElementById('loginFields');
        if (loginFields) {
            loginFields.style.display = checked ? '' : 'none';
        }
    }

    document.querySelectorAll('.role-check').forEach(function (box) {
        box.addEventListener('change', function () {
            if (box.checked) {
                document.getElementById('rolesError').style.display = 'none';
            }
        });
    });

    // The server refuses a submission with no role; this only saves the round trip.
    document.getElementById('personForm').addEventListener('submit', function (event) {
        var chosen = document.querySelectorAll('.role-check:checked').length;
        var notice = document.getElementById('rolesError');
        notice.style.display = chosen ? 'none' : '';
        if (!chosen) {
            event.preventDefault();
            notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
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
                first_name: { required: true, minlength: 2, maxlength: 100 },
                email:      { required: true, email: true },
                phone:      { maxlength: 13 },
                login_email: { email: true },
                password:   { minlength: 6 }
            },

            messages: {
                first_name: {
                    required: "First Name is required",
                    minlength: "First Name must be at least 2 characters",
                    maxlength: "First Name must not exceed 100 characters"
                },
                email: {
                    required: "Email is required",
                    email: "Please enter a valid email address"
                },
                phone: {
                    maxlength: "Phone number must not exceed 13 characters"
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
