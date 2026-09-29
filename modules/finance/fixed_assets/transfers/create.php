<?php
$pageTitle = 'Transfer Asset Financially';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';

if (!hasPermission('fixedasset.transfer')) { die('Access Denied'); }

$db = getDB();
$asset_id = $_GET['asset_id'] ?? '';

if (!$asset_id) {
    flash('error', 'Asset ID required');
    to('modules/finance/fixed_assets/financial_records/index.php');
}

// Get financial asset
$stmt = $db->prepare("
    SELECT f.*, a.asset_code, a.asset_name, ac.category_name,
           COALESCE(ai.impairment_loss, 0) as impairment_amount
    FROM fixed_asset_financials f
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
    WHERE f.id = ? AND f.status IN ('active', 'transferred')
");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    flash('error', 'Asset not found or already disposed');
    to('modules/finance/fixed_assets/financial_records/index.php');
}

$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'financial_asset_id' => $asset_id,
        'transfer_date' => $_POST['transfer_date'] ?? date('Y-m-d'),
        'from_department' => $_POST['from_department'] ?? '',
        'to_department' => $_POST['to_department'] ?? '',
        'from_project' => $_POST['from_project'] ?? '',
        'to_project' => $_POST['to_project'] ?? '',
        'from_location' => $_POST['from_location'] ?? '',
        'to_location' => $_POST['to_location'] ?? '',
        'transfer_type' => $_POST['transfer_type'] ?? 'department',
        'notes' => $_POST['notes'] ?? '',
    ];

    $errors = validate($data, [
        'financial_asset_id' => 'required',
        'transfer_date' => 'required',
        'transfer_type' => 'required',
        'to_department' => 'required_if:transfer_type,department',
        'to_project' => 'required_if:transfer_type,project',
        'to_location' => 'required_if:transfer_type,location',
    ]);

    if (empty($errors)) {
        require_once __DIR__ . '/../DepreciationService.php';
        $deprService = new DepreciationService();
        $result = $deprService->transferAsset($data, $_SESSION['user_id']);
        
        if ($result['success']) {
            logAudit($_SESSION['user_id'], 'Transferred asset: ' . $asset['asset_code'], 'fixed_assets');
            flash('success', 'Asset transfer recorded successfully');
            to('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id);
        } else {
            $errors['general'] = $result['error'];
        }
    }
}

// Get departments (from HRMS if available)
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
?>

<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Asset</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <h2 class="text-xl font-semibold mb-6">
        <i class="fas fa-random mr-2 text-purple-500"></i>
        Transfer Asset Financially
    </h2>

    <!-- Asset Summary -->
    <div class="bg-purple-50 border border-purple-200 p-4 rounded mb-6">
        <h3 class="font-semibold text-purple-800">Asset Details</h3>
        <div class="grid grid-cols-2 gap-2 mt-2">
            <div><span class="font-medium">Asset Code:</span> <?= sanitize($asset['asset_code']) ?></div>
            <div><span class="font-medium">Name:</span> <?= sanitize($asset['asset_name']) ?></div>
            <div><span class="font-medium">Category:</span> <?= sanitize($asset['category_name']) ?></div>
            <div><span class="font-medium">Current Cost:</span> <?= formatAmount($asset['acquisition_cost']) ?></div>
            <div><span class="font-medium">Current NBV:</span> <?= formatAmount($asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount']) ?></div>
        </div>
    </div>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="financial_asset_id" value="<?= $asset_id ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Transfer Date <span class="text-red-600">*</span></labe>
                <input type="date" name="transfer_date" value="<?= old('transfer_date', date('Y-m-d')) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Transfer Type <span class="text-red-600">*</span></labe>
                <select name="transfer_type" id="transferType" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                    <option value="department">Department</option>
                    <option value="project">Project</option>
                    <option value="location">Location</option>
                </select>
            </div>
        </div>

        <div id="departmentFields">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Department</label>
                    <select name="from_department" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= sanitize($dept['department_name']) ?>" <?= old('from_department') == $dept['department_name'] ? 'selected' : '' ?>>
                            <?= sanitize($dept['department_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Department <span class="text-red-600">*</span></labe>
                    <select name="to_department" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= sanitize($dept['department_name']) ?>" <?= old('to_department') == $dept['department_name'] ? 'selected' : '' ?>>
                            <?= sanitize($dept['department_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="projectFields" style="display: none;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Project</label>
                    <select name="from_project" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= sanitize($proj['project_name']) ?>" <?= old('from_project') == $proj['project_name'] ? 'selected' : '' ?>>
                            <?= sanitize($proj['project_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Project <span class="text-red-600">*</span></labe>
                    <select name="to_project" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?= sanitize($proj['project_name']) ?>" <?= old('to_project') == $proj['project_name'] ? 'selected' : '' ?>>
                            <?= sanitize($proj['project_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="locationFields" style="display: none;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Location</label>
                    <input type="text" name="from_location" value="<?= old('from_location') ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Location <span class="text-red-600">*</span></labe>
                    <input type="text" name="to_location" value="<?= old('to_location') ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Add any notes about this transfer"><?= old('notes') ?></textarea>
        </div>

        <div class="pt-4 flex gap-4">
            <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded hover:bg-purple-700 transition">
                <i class="fas fa-check mr-2"></i>Record Transfer
            </button>
            <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-gray-600 hover:text-gray-900 px-6 py-2 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const transferType = document.getElementById('transferType');
    const departmentFields = document.getElementById('departmentFields');
    const projectFields = document.getElementById('projectFields');
    const locationFields = document.getElementById('locationFields');

    function toggleFields() {
        const type = transferType.value;
        
        departmentFields.style.display = type === 'department' ? 'grid' : 'none';
        projectFields.style.display = type === 'project' ? 'grid' : 'none';
        locationFields.style.display = type === 'location' ? 'grid' : 'none';
        
        // Make fields required based on type
        if (type === 'department') {
            document.querySelector('select[name="to_department"]').required = true;
            document.querySelector('select[name="to_project"]').required = false;
            document.querySelector('input[name="to_location"]').required = false;
        } else if (type === 'project') {
            document.querySelector('select[name="to_department"]').required = false;
            document.querySelector('select[name="to_project"]').required = true;
            document.querySelector('input[name="to_location"]').required = false;
        } else {
            document.querySelector('select[name="to_department"]').required = false;
            document.querySelector('select[name="to_project"]').required = false;
            document.querySelector('input[name="to_location"]').required = true;
        }
    }

    transferType.addEventListener('change', toggleFields);
    toggleFields();
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
