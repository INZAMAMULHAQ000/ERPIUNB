<?php
$pageTitle = 'View Person';
require_once '../../includes/header.php';

if (!hasPermission('people_management.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT p.*, u.name as user_name, u.email as user_email, u.status as user_status
    FROM people p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$person = $stmt->fetch();

if (!$person) {
    flash('error', 'Person not found', 'error');
    to('modules/people_management/index.php');
}

// Load linked employee data if applicable
$employee = null;
if ($person['employee_id']) {
    $empStmt = $db->prepare("
        SELECT e.*, d.department_name, desig.designation_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN designations desig ON e.designation_id = desig.id
        WHERE e.id = ?
    ");
    $empStmt->execute([$person['employee_id']]);
    $employee = $empStmt->fetch();
}

// Get user roles if login exists
$userRoles = [];
if ($person['user_id']) {
    $roleStmt = $db->prepare("
        SELECT r.name FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $roleStmt->execute([$person['user_id']]);
    $userRoles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
}

$catLabels = ['employee' => 'Employee', 'customer' => 'Customer', 'contractor' => 'Contractor'];
$catColors = ['employee' => 'bg-blue-100 text-blue-800', 'customer' => 'bg-purple-100 text-purple-800', 'contractor' => 'bg-orange-100 text-orange-800'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="index.php" class="text-blue-600 hover:text-blue-900 mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i>Back to People
        </a>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($person['first_name'] . ' ' . $person['last_name']) ?></h1>
        <p class="text-gray-600">
            <span class="px-2 py-0.5 text-xs rounded <?= $catColors[$person['category']] ?? 'bg-gray-100' ?>"><?= $catLabels[$person['category']] ?? $person['category'] ?></span>
            <span class="ml-2 text-sm">Code: <?= sanitize($person['person_code']) ?></span>
        </p>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('people_management.edit')): ?>
        <a href="edit.php?id=<?= $person['id'] ?>" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition">
            <i class="fas fa-edit mr-2"></i>Edit
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Info -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Person Details -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Personal Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">First Name</div>
                    <div class="font-medium"><?= sanitize($person['first_name']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Last Name</div>
                    <div class="font-medium"><?= sanitize($person['last_name'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Email</div>
                    <div class="font-medium"><?= sanitize($person['email'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Phone</div>
                    <div class="font-medium"><?= sanitize($person['phone'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Gender</div>
                    <div class="font-medium"><?= $person['gender'] ? ucfirst($person['gender']) : '-' ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Date of Birth</div>
                    <div class="font-medium"><?= $person['date_of_birth'] ? date('M d, Y', strtotime($person['date_of_birth'])) : '-' ?></div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Address</div>
                    <div class="font-medium"><?= sanitize($person['address'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Status</div>
                    <div>
                        <span class="px-2 py-1 text-xs rounded <?= $person['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($person['status']) ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Created</div>
                    <div class="font-medium"><?= date('M d, Y h:i A', strtotime($person['created_at'])) ?></div>
                </div>
            </div>
            <?php if ($person['notes']): ?>
            <div class="mt-4 pt-4 border-t">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Notes</div>
                <div class="text-gray-700"><?= nl2br(sanitize($person['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Linked Customer -->
        <?php
        $linkedCustomer = null;
        if (!empty($person['customer_id'])) {
            $custStmt = $db->prepare("SELECT id, client_code, company_name, status, currency_code, credit_limit FROM clients WHERE id = ? AND deleted_at IS NULL");
            $custStmt->execute([$person['customer_id']]);
            $linkedCustomer = $custStmt->fetch();
        }
        ?>
        <?php if ($linkedCustomer): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                Customer Master
                <a href="<?= url('modules/customers/view.php?id=' . $linkedCustomer['id']) ?>" class="text-blue-600 text-sm font-normal ml-2">
                    <i class="fas fa-external-link-alt mr-1"></i>Open Customer Record
                </a>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Customer Code</div>
                    <div class="font-medium font-mono"><?= sanitize($linkedCustomer['client_code']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Organisation</div>
                    <div class="font-medium"><?= sanitize($linkedCustomer['company_name']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Status</div>
                    <div class="font-medium capitalize"><?= sanitize($linkedCustomer['status']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Credit Limit</div>
                    <div class="font-medium"><?= sanitize($linkedCustomer['currency_code'] . ' ' . number_format((float) $linkedCustomer['credit_limit'], 2)) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Employee Details -->
        <?php if ($employee): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                Employee Record
                <a href="<?= url('modules/employees/view.php?id=' . $employee['id']) ?>" class="text-blue-600 text-sm font-normal ml-2">
                    <i class="fas fa-external-link-alt mr-1"></i>View in Employees Module
                </a>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Employee Code</div>
                    <div class="font-medium"><?= sanitize($employee['employee_code']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Department</div>
                    <div class="font-medium"><?= sanitize($employee['department_name'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Designation</div>
                    <div class="font-medium"><?= sanitize($employee['designation_name'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Joining Date</div>
                    <div class="font-medium"><?= $employee['joining_date'] ? date('M d, Y', strtotime($employee['joining_date'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Employment Type</div>
                    <div class="font-medium"><?= ucfirst(str_replace('_', ' ', $employee['employment_type'] ?? '-')) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Work Location</div>
                    <div class="font-medium"><?= sanitize($employee['work_location'] ?? '-') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- System Access -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">System Access</h3>
            <?php if ($person['user_id']): ?>
                <div class="space-y-3">
                    <div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Login Email</div>
                        <div class="font-medium"><?= sanitize($person['user_email'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Login Name</div>
                        <div class="font-medium"><?= sanitize($person['user_name'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Account Status</div>
                        <span class="px-2 py-1 text-xs rounded <?= $person['user_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($person['user_status'] ?? 'unknown') ?>
                        </span>
                    </div>
                    <?php if ($userRoles): ?>
                    <div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Roles</div>
                        <?php foreach ($userRoles as $role): ?>
                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1"><?= sanitize($role) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="pt-2">
                        <a href="<?= url('modules/users/view.php?id=' . $person['user_id']) ?>" class="text-blue-600 text-sm hover:text-blue-800">
                            <i class="fas fa-external-link-alt mr-1"></i>View User Record
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-slash text-gray-300 text-4xl mb-2"></i>
                    <p class="text-gray-500 text-sm">No login credentials created</p>
                    <p class="text-xs text-gray-400 mt-1">This person cannot log in to the ERP system</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Quick Actions</h3>
            <div class="space-y-2">
                <?php if (hasPermission('people_management.edit')): ?>
                <a href="edit.php?id=<?= $person['id'] ?>" class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-edit text-yellow-500 mr-2"></i>Edit this person
                </a>
                <?php endif; ?>
                <?php if ($employee && hasPermission('employees.edit')): ?>
                <a href="<?= url('modules/employees/edit.php?id=' . $employee['id']) ?>" class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-user-tie text-blue-500 mr-2"></i>Edit Employee Record
                </a>
                <?php endif; ?>
                <?php if (hasPermission('people_management.create')): ?>
                <a href="create.php" class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                    <i class="fas fa-plus text-green-500 mr-2"></i>Add another person
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
