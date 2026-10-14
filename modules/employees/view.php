<?php
$pageTitle = 'Employee Profile';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once __DIR__ . '/document_service.php';

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

$slotDocs = employeeSlotDocuments($db, $id);
$slotKeys = array_keys(employeeDocSlots());
$photoPath = $emp['photo_path'] ?? '';

$statusColors = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-800', 'terminated' => 'bg-red-100 text-red-800', 'resigned' => 'bg-yellow-100 text-yellow-800'];
$typeLabels = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern'];
$visaLabels = employeeVisaTypes();
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
            <h2 class="text-xl font-semibold mb-4">Identification</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Nationality</label>
                    <p class="text-gray-900"><?= sanitize($emp['nationality'] ?? '') ?: '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Passport Number</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($emp['passport_number'] ?? '') ?: '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Passport Expiry</label>
                    <p class="<?= employeeExpiryClass($emp['passport_expiry'] ?? '') ?>"><?= $emp['passport_expiry'] ? date('M d, Y', strtotime($emp['passport_expiry'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Emirates ID Number</label>
                    <p class="text-gray-900 font-medium"><?= sanitize(employeeFormatEmiratesId($emp['emirates_id_number'] ?? '')) ?: '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Visa Type</label>
                    <p class="text-gray-900"><?= $visaLabels[$emp['visa_type'] ?? ''] ?? '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Visa Number</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($emp['visa_number'] ?? '') ?: '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Visa Expiry</label>
                    <p class="<?= employeeExpiryClass($emp['visa_expiry'] ?? '') ?>"><?= $emp['visa_expiry'] ? date('M d, Y', strtotime($emp['visa_expiry'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Emirates ID Expiry</label>
                    <p class="<?= employeeExpiryClass($emp['emirates_id_expiry'] ?? '') ?>"><?= $emp['emirates_id_expiry'] ? date('M d, Y', strtotime($emp['emirates_id_expiry'])) : '-' ?></p>
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
                    <label class="block text-sm font-medium text-gray-500">Contract Number</label>
                    <p class="text-gray-900 font-medium"><?= sanitize($emp['contract_number'] ?? '') ?: '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Contract Start Date</label>
                    <p class="<?= employeeExpiryClass($emp['contract_start_date'] ?? '') ?>"><?= $emp['contract_start_date'] ? date('M d, Y', strtotime($emp['contract_start_date'])) : '-' ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Contract End Date</label>
                    <p class="<?= employeeExpiryClass($emp['contract_end_date'] ?? '') ?>"><?= $emp['contract_end_date'] ? date('M d, Y', strtotime($emp['contract_end_date'])) : '-' ?></p>
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

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Document Scans</h2>
                <?php if (hasPermission('employees.edit')): ?>
                <a href="edit.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900 text-sm">
                    <i class="fas fa-upload mr-1"></i>Upload / Replace
                </a>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach (employeeDocSlots() as $slot => $label): ?>
                <?php
                    $doc = $slotDocs[$slot] ?? null;
                    $expiryColumn = employeeSlotExpiryColumn($slot);
                    $expiry = $expiryColumn ? ($emp[$expiryColumn] ?? '') : '';
                ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <p class="text-sm font-medium text-gray-700"><?= $label ?></p>
                        <?php if ($expiry): ?>
                        <span class="text-[11px] px-2 py-0.5 rounded-full shrink-0 <?= employeeExpiryChipClass($expiry) ?>"><?= date('M d, Y', strtotime($expiry)) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($doc): ?>
                        <?php if (strpos($doc['mime_type'] ?? '', 'image/') === 0): ?>
                        <img src="<?= attachmentFileUrl($doc['file_path']) ?>" alt="<?= sanitize($label) ?>"
                            class="w-full h-28 object-cover rounded border mb-2">
                        <?php else: ?>
                        <div class="w-full h-28 rounded border bg-gray-50 flex items-center justify-center text-gray-400 mb-2">
                            <i class="fas <?= strpos($doc['mime_type'] ?? '', 'pdf') !== false ? 'fa-file-pdf text-3xl' : 'fa-file-word text-3xl' ?>"></i>
                        </div>
                        <?php endif; ?>
                        <?= attachmentViewButton('View', $doc['file_path'], $doc['mime_type'] ?? '', $doc['document_name']) ?>
                        <p class="text-xs text-gray-500 mt-2">
                            <?= attachmentSizeLabel($doc['file_size']) ?> &middot; <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                        </p>
                    <?php else: ?>
                        <div class="w-full h-28 rounded border border-dashed flex items-center justify-center text-gray-300 mb-2">
                            <i class="fas fa-file-upload text-2xl"></i>
                        </div>
                        <p class="text-xs text-gray-500">Not on record</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <?php if ($photoPath): ?>
            <button type="button" onclick="attachmentViewerOpen(this)"
                data-url="<?= attachmentFileUrl($photoPath) ?>" data-mime="<?= attachmentMimeFromPath($photoPath) ?>"
                data-name="Profile Photo — <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>"
                class="block mx-auto" title="View photo">
                <img src="<?= attachmentFileUrl($photoPath) ?>" alt="<?= sanitize($emp['first_name']) ?>"
                    class="h-24 w-24 mx-auto rounded-full object-cover border-2 border-white shadow">
            </button>
            <?php else: ?>
            <div class="h-24 w-24 mx-auto rounded-full bg-blue-500 flex items-center justify-center text-white text-3xl font-bold">
                <?= strtoupper(substr($emp['first_name'], 0, 1)) ?>
            </div>
            <?php endif; ?>
            <h3 class="mt-4 text-xl font-semibold"><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></h3>
            <p class="text-gray-600"><?= sanitize($emp['designation_name'] ?? 'No Designation') ?></p>
            <span class="inline-block mt-2 px-2 py-1 text-xs rounded <?= $statusColors[$emp['status']] ?? 'bg-gray-100' ?>">
                <?= ucfirst($emp['status']) ?>
            </span>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Other Documents</h3>
                <?php if (hasPermission('employees.edit')): ?>
                <a href="documents.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900 text-sm">
                    <i class="fas fa-plus mr-1"></i>Add
                </a>
                <?php endif; ?>
            </div>
            <?php $otherDocs = array_filter($docs, fn($d) => !in_array($d['document_type'], $slotKeys, true)); ?>
            <?php if ($otherDocs): ?>
            <ul class="space-y-2">
                <?php foreach ($otherDocs as $doc): ?>
                <li class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <div class="flex items-center min-w-0">
                        <i class="fas fa-file-alt text-gray-400 mr-2"></i>
                        <span class="text-sm truncate"><?= sanitize($doc['document_name']) ?></span>
                    </div>
                    <span class="flex items-center space-x-3 ml-2 shrink-0">
                        <?= attachmentViewButton('View', $doc['file_path'], $doc['mime_type'] ?? '', $doc['document_name']) ?>
                        <a href="<?= attachmentFileUrl($doc['file_path']) ?>" class="text-blue-600 hover:text-blue-900" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-gray-500 text-sm">No documents uploaded</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../templates/document_viewer.php'; ?>

<?php require_once '../../includes/footer.php'; ?>
