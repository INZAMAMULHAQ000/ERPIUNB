<?php
/**
 * Edit Payroll GL Mapping
 * 
 * @package XXX
 * @submodule Finance.Payroll.Mappings
 */

// Check permission
if (!hasPermission('payrollfinance.configure')) {
    redirect_to('no-access.php');
}

// Get mapping ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect_to('index.php');
}

// Fetch mapping data
$mapping = getPayrollGlMappingById($id);
if (!$mapping) {
    $_SESSION['error'] = 'Mapping not found';
    redirect_to('index.php');
}

// Fetch active chart of accounts for dropdown
$chartOfAccounts = getChartOfAccounts(true); // true for active only

// Fetch payroll components for dropdown
$payrollComponents = getPayrollComponents(true); // true for active only

// Define mapping types and statuses
$mappingTypes = ['expense', 'liability', 'asset'];
$statuses = ['active', 'inactive'];

// Process form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'payroll_component_id' => $_POST['payroll_component_id'] ?? '',
        'gl_account_id' => $_POST['gl_account_id'] ?? '',
        'mapping_type' => $_POST['mapping_type'] ?? '',
        'status' => $_POST['status'] ?? '',
    ];

    // Basic validation
    if (empty($data['payroll_component_id'])) {
        $errors['payroll_component_id'] = 'Payroll component is required';
    }
    if (empty($data['gl_account_id'])) {
        $errors['gl_account_id'] = 'GL account is required';
    }
    if (empty($data['mapping_type'])) {
        $errors['mapping_type'] = 'Mapping type is required';
    }
    if (!in_array($data['mapping_type'], $mappingTypes)) {
        $errors['mapping_type'] = 'Invalid mapping type';
    }
    if (empty($data['status'])) {
        $errors['status'] = 'Status is required';
    }
    if (!in_array($data['status'], $statuses)) {
        $errors['status'] = 'Invalid status';
    }

    if (empty($errors)) {
        // Update mapping
        $result = updatePayrollGlMapping($id, $data);
        if ($result) {
            logAudit($_SESSION['user_id'], 'Updated payroll GL mapping ID: ' . $id, 'finance');
            flash('success', 'Mapping updated successfully');
            redirect_to('index.php');
        } else {
            $errors['db'] = 'Failed to update mapping';
        }
    }
}

// Prepare form data: if there are errors, use submitted data; otherwise use mapping data
$formData = [];
if (!empty($errors)) {
    $formData = $_POST;
} else {
    $formData = [
        'payroll_component_id' => $mapping['payroll_component_id'] ?? '',
        'gl_account_id' => $mapping['gl_account_id'] ?? '',
        'mapping_type' => $mapping['mapping_type'] ?? '',
        'status' => $mapping['status'] ?? '',
    ];
}

// Include header
include_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Edit Payroll GL Mapping</h1>
        <div class="flex items-center space-x-2">
            <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Cancel</a>
            <a href="view.php?id=<?= htmlspecialchars($id) ?>" class="btn-xs px-2 py-1 bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition text-sm">View</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-lg shadow p-6 max-w-2xl space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payroll Component <span class="text-red-600">*</span></labe>
                <select name="payroll_component_id" required class="w-full">
                    <option value="">Select Payroll Component</option>
                    <?php foreach ($payrollComponents as $component): ?>
                        <option value="<?= htmlspecialchars($component['id']) ?>" 
                            <?= $formData['payroll_component_id'] == $component['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($component['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">GL Account <span class="text-red-600">*</span></labe>
                <select name="gl_account_id" required class="w-full">
                    <option value="">Select GL Account</option>
                    <?php foreach ($chartOfAccounts as $account): ?>
                        <option value="<?= htmlspecialchars($account['id']) ?>" 
                            <?= $formData['gl_account_id'] == $account['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mapping Type <span class="text-red-600">*</span></labe>
                <select name="mapping_type" required class="w-full">
                    <?php foreach ($mappingTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" 
                            <?= $formData['mapping_type'] == $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-600">*</span></labe>
                <select name="status" required class="w-full">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" 
                            <?= $formData['status'] == $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="pt-4 flex justify-end space-x-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Update Mapping</button>
            <a href="index.php" class="border border-blue-600 text-blue-600 px-4 py-2 hover:bg-blue-50 transition">Cancel</a>
        </div>
    </form>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../includes/footer.php';
?>
