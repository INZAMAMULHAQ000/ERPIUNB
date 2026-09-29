<?php
$pageTitle = 'Employee Profile';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('employees.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT e.*, d.department_name, desig.designation_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN designations desig ON e.designation_id = desig.id WHERE e.id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash('error', 'Employee not found');
    to('modules/employees/index.php');
}

$manager = null;
if ($emp['reporting_manager_id']) {
    $mgrStmt = $db->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
    $mgrStmt->execute([$emp['reporting_manager_id']]);
    $manager = $mgrStmt->fetch();
}

$documents = $db->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC");
$documents->execute([$id]);
$docs = $documents->fetchAll();

$statusColors = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-800', 'terminated' => 'bg-red-100 text-red-800', 'resigned' => 'bg-yellow-100 text-yellow-800'];
$typeLabels = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Employees
    </a>
    <?php if (hasPermission('employees.edit')): ?>
    <a href="edit.php?id=<?= $id ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-edit mr-2"></i>Edit Employee
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Personal Information</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Employee Code</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($emp['employee_code']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Full Name</label>
                    <p class="text-gray-900"><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Gender</label>
                    <p class="text-gray-900 capitalize"><?= sanitize($emp['gender']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Date of Birth</label>
                    <p class="text-gray-900"><?= $emp['date_of_birth'] ? date('M d, Y', strtotime($emp['date_of_birth'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Marital Status</label>
                    <p class="text-gray-900 capitalize"><?= sanitize($emp['marital_status'] ?? 'Single') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Phone</label>
                    <p class="text-gray-900"><?= sanitize($emp['phone'] ?? '-') ?></p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-500">Email</label>
                    <p class="text-gray-900"><?= sanitize($emp['email']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Address</label>
                    <p class="text-gray-900"><?= sanitize($emp['address'] ?? '-') ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Employment Details</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Department</label>
                    <p class="text-gray-900"><?= sanitize($emp['department_name'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Designation</label>
                    <p class="text-gray-900"><?= sanitize($emp['designation_name'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Employment Type</label>
                    <p class="text-gray-900"><?= $typeLabels[$emp['employment_type']] ?? $emp['employment_type'] ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Joining Date</label>
                    <p class="text-gray-900"><?= $emp['joining_date'] ? date('M d, Y', strtotime($emp['joining_date'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Reporting Manager</label>
                    <p class="text-gray-900"><?= $manager ? sanitize($manager['first_name'] . ' ' . $manager['last_name']) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Work Location</label>
                    <p class="text-gray-900"><?= sanitize($emp['work_location'] ?? '-') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Status</label>
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$emp['status']] ?? 'bg-gray-100' ?>">
                        <?= ucfirst($emp['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="h-24 w-24 mx-auto rounded-full bg-blue-500 flex items-center justify-center text-white text-3xl font-bold">
                <?= strtoupper(substr($emp['first_name'], 0, 1)) ?>
            </div>
            <h3 class="mt-4 text-xl font-semibold"><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></h3>
            <p class="text-gray-600"><?= sanitize($emp['designation_name'] ?? 'No Designation') ?></p>
            <span class="inline-block mt-2 px-2 py-1 text-xs rounded <?= $statusColors[$emp['status']] ?? 'bg-gray-100' ?>">
                <?= ucfirst($emp['status']) ?>
            </span>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Documents</h3>
                <?php if (hasPermission('employees.edit')): ?>
                <a href="documents.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900 text-sm">
                    <i class="fas fa-plus mr-1"></i>Add
                </a>
                <?php endif; ?>
            </div>
            <?php if ($docs): ?>
            <ul class="space-y-2">
                <?php foreach ($docs as $doc): ?>
                <li class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-file-alt text-gray-400 mr-2"></i>
                        <span class="text-sm"><?= sanitize($doc['document_name']) ?></span>
                    </div>
                    <a href="../../<?= sanitize($doc['file_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                        <i class="fas fa-download"></i>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-gray-500 text-sm">No documents uploaded</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>