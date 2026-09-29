<?php
$pageTitle = 'Assign License';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('licenses.edit')) { die('Access Denied'); }

$db = getDB();
$license_id = $_GET['id'] ?? 0;

$license = $db->prepare("SELECT * FROM software_licenses WHERE id = ? AND status = 'active'");
$license->execute([$license_id]);
$license = $license->fetch();

if (!$license) { die('License not found or inactive'); }

$assignedEmployees = $db->prepare("SELECT la.*, e.first_name, e.last_name, e.employee_code
    FROM license_assignments la
    JOIN employees e ON la.employee_id = e.id 
    WHERE la.license_id = ?");
$assignedEmployees->execute([$license_id]);
$assignedEmployees = $assignedEmployees->fetchAll();

$availableEmployees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$assignedIds = array_column($assignedEmployees, 'employee_id');
$availableEmployees = array_filter($availableEmployees, function($e) use ($assignedIds) {
    return !in_array($e['id'], $assignedIds);
});

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';
    
    $errors = validate(['employee_id' => $employee_id], ['employee_id' => 'required']);
    
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO license_assignments (license_id, employee_id, assigned_date) VALUES (?, ?, ?)");
        $stmt->execute([$license_id, $employee_id, date('Y-m-d')]);
        
        $db->prepare("UPDATE software_licenses SET assigned_seats = assigned_seats + 1 WHERE id = ?")->execute([$license_id]);
        
        logAudit($_SESSION['user_id'], 'assign_license', 'license_assignments');
        flash('success', 'License assigned successfully');
        to('http://localhost/ERP/modules/licenses/assign.php?id=' . $license_id);
    }
}

if (isset($_GET['revoke'])) {
    $assignment_id = $_GET['revoke'];
    $db->prepare("DELETE FROM license_assignments WHERE id = ?")->execute([$assignment_id]);
    $db->prepare("UPDATE software_licenses SET assigned_seats = GREATEST(assigned_seats - 1, 0) WHERE id = ?")->execute([$license_id]);
    flash('success', 'License revoked');
    to('http://localhost/ERP/modules/licenses/assign.php?id=' . $license_id);
}
?>

<div class="mb-6">
    <a href="<?= url('modules/licenses/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Licenses</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">License Details</h2>
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-gray-600">Software:</span><span class="font-medium"><?= sanitize($license['software_name']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Total Seats:</span><span><?= $license['seats'] ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Assigned:</span><span><?= $license['assigned_seats'] ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Available:</span><span class="text-green-600"><?= $license['seats'] - $license['assigned_seats'] ?></span></div>
        </div>
        
        <?php if (hasPermission('licenses.edit') && count($availableEmployees) > 0): ?>
        <div class="mt-6 pt-4 border-t">
            <h3 class="text-md font-semibold text-gray-800 mb-3">Assign New User</h3>
            <?php if ($errors): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>
            <form method="POST" class="flex gap-2">
                <select name="employee_id" required class="flex-1 px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Employee</option>
                    <?php foreach ($availableEmployees as $e): ?><option value="<?= $e['id'] ?>"><?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Assign</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Assigned Users</h2>
        <?php if ($assignedEmployees): ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Employee</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Assigned Date</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($assignedEmployees as $a): ?>
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium"><?= sanitize($a['first_name'] . ' ' . $a['last_name']) ?></div>
                        <div class="text-sm text-gray-500"><?= sanitize($a['employee_code']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= date('M d, Y', strtotime($a['assigned_date'])) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= url('modules/licenses/assign.php?id=' . $license_id . '&revoke=' . $a['id']) ?>" class="text-red-600 hover:text-red-900" title="Revoke"><i class="fas fa-user-minus"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-gray-500 text-center py-4">No users assigned</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

