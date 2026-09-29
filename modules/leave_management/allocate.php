<?php
$pageTitle = 'Allocate Leave Balances';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('leave_management.allocate')) {
    die('Access Denied');
}

$db = getDB();
$errors = [];
$success = false;

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE status = 'active' ORDER BY leave_name")->fetchAll();
$currentYear = date('Y');

// Bulk allocate form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_allocate'])) {
    $year = (int)($_POST['year'] ?? $currentYear);

    try {
        $db->beginTransaction();
        $count = 0;

        foreach ($_POST['balances'] ?? [] as $empId => $typeAllocs) {
            foreach ($typeAllocs as $typeId => $days) {
                $days = (float)$days;
                if ($days <= 0) continue;

                // Upsert: insert or update existing balance
                $check = $db->prepare("SELECT id FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                $check->execute([$empId, $typeId, $year]);
                $existing = $check->fetch();

                if ($existing) {
                    $upd = $db->prepare("UPDATE leave_balances SET total_days = ? WHERE id = ?");
                    $upd->execute([$days, $existing['id']]);
                } else {
                    $ins = $db->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, total_days, used_days) VALUES (?, ?, ?, ?, 0)");
                    $ins->execute([$empId, $typeId, $year, $days]);
                }
                $count++;
            }
        }

        $db->commit();
        logAudit($_SESSION['user_id'], 'allocate_balances', 'leave_management');
        flash('success', "Balances allocated/updated for $count leave type(s)");
        to('modules/leave_management/allocate.php');

    } catch (Exception $e) {
        $db->rollBack();
        $errors['_general'] = 'Failed to allocate: ' . $e->getMessage();
    }
}

// Pre-select employee from query param
$selectedEmployeeId = $_GET['employee_id'] ?? '';

// Get existing balances if editing a specific employee's allocations
$existingBalances = [];
if ($selectedEmployeeId) {
    $balStmt = $db->prepare("SELECT leave_type_id, total_days FROM leave_balances WHERE employee_id = ? AND year = ?");
    $balStmt->execute([$selectedEmployeeId, $currentYear]);
    foreach ($balStmt->fetchAll() as $b) {
        $existingBalances[$b['leave_type_id']] = $b['total_days'];
    }
}
?>
<div class="mb-6">
    <a href="dashboard.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
    </a>
</div>

<?php if ($errors): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <ul class="list-disc list-inside">
        <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">Allocate Leave Balances for <?= $currentYear ?></h2>
    <p class="text-sm text-gray-600 mb-6">Set the number of leave days allocated to each employee for the current year. Existing values will be updated.</p>

    <form method="POST" id="allocateForm">
        <input type="hidden" name="year" value="<?= $currentYear ?>">
        <input type="hidden" name="bulk_allocate" value="1">

        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase border">Employee</th>
                        <?php foreach ($leaveTypes as $lt): ?>
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase border">
                            <?= sanitize($lt['leave_code']) ?>
                            <div class="text-[10px] font-normal text-gray-400">(<?= $lt['total_days'] ?> dflt)</div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($employees as $emp): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 border">
                            <div class="font-medium text-sm text-gray-900"><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= sanitize($emp['employee_code']) ?></div>
                        </td>
                        <?php foreach ($leaveTypes as $lt):
                            $existingVal = $existingBalances[$lt['id']] ?? $lt['total_days'];
                            $isSelected = $selectedEmployeeId && $selectedEmployeeId != $emp['id'];
                        ?>
                        <td class="px-3 py-2 border text-center">
                            <input type="number" name="balances[<?= $emp['id'] ?>][<?= $lt['id'] ?>]"
                                value="<?= $existingVal ?>"
                                min="0" step="0.5"
                                class="w-20 text-center px-2 py-1 border rounded text-sm focus:ring-2 focus:ring-blue-500"
                                <?= $selectedEmployeeId && $selectedEmployeeId != $emp['id'] ? 'disabled' : '' ?>>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 pt-4 border-t">
            <?php if ($selectedEmployeeId): ?>
            <?php
                $selEmp = array_filter($employees, fn($e) => $e['id'] == $selectedEmployeeId);
                $selEmp = reset($selEmp);
            ?>
            <p class="text-sm text-gray-600 mb-2">
                Editing balances for: <strong><?= sanitize($selEmp['first_name'] . ' ' . $selEmp['last_name']) ?></strong>
                <a href="allocate.php" class="text-blue-600 ml-2 text-xs">Edit all employees</a>
            </p>
            <?php endif; ?>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-save mr-2"></i>Save All Balances
            </button>
            <a href="dashboard.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script>
// Disable inputs for non-selected employee when filtering
document.querySelector('select[name="employee_filter"]')?.addEventListener('change', function() {
    if (this.value) {
        window.location.href = 'allocate.php?employee_id=' + this.value;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
