<?php
$pageTitle = 'Process Asset Return';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('asset_returns.create')) { die('Access Denied'); }

$db = getDB();
$assignment_id = $_GET['assignment_id'] ?? 0;

$assignment = $db->prepare("SELECT aa.*, a.asset_code, a.asset_name, e.first_name, e.last_name 
    FROM asset_assignments aa 
    JOIN assets a ON aa.asset_id = a.id 
    JOIN employees e ON aa.employee_id = e.id 
    WHERE aa.id = ? AND aa.status = 'active'");
$assignment->execute([$assignment_id]);
$assignment = $assignment->fetch();

if (!$assignment) { die('Assignment not found or already returned'); }
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'return_date' => $_POST['return_date'] ?? '',
        'return_condition' => $_POST['return_condition'] ?? ''
    ];
    
    $errors = validate($data, ['return_date' => 'required', 'return_condition' => 'required']);
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/asset_returns/create.php');
    }

    
        $returnCondition = $data['return_condition'];
        $assetStatus = $returnCondition == 'damaged' ? 'damaged' : 'available';
        
        $stmt = $db->prepare("INSERT INTO asset_returns (assignment_id, return_date, return_condition, damage_notes, accepted_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $assignment_id,
            $data['return_date'],
            $data['return_condition'],
            $_POST['damage_notes'] ?: null,
            $_SESSION['user_id']
        ]);
        
        $db->prepare("UPDATE asset_assignments SET status = 'returned', actual_return_date = ? WHERE id = ?")->execute([$data['return_date'], $assignment_id]);
        
        $db->prepare("UPDATE assets SET status = ?, asset_condition = ? WHERE id = ?")->execute([$assetStatus, $returnCondition == 'needs_repair' ? 'damaged' : 'good', $assignment['asset_id']]);
        
        logAudit($_SESSION['user_id'], 'return_asset', 'asset_returns');
        flash('success', 'Asset returned successfully');
        to('http://localhost/ERP/modules/asset_assignments/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/asset_assignments/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Assignments</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-6">Process Asset Return</h2>
    
    <div class="bg-gray-50 p-4 rounded mb-6">
        <div class="text-sm text-gray-600">Asset: <span class="font-medium text-gray-900"><?= sanitize($assignment['asset_code']) ?> - <?= sanitize($assignment['asset_name']) ?></span></div>
        <div class="text-sm text-gray-600">Employee: <span class="font-medium text-gray-900"><?= sanitize($assignment['first_name'] . ' ' . $assignment['last_name']) ?></span></div>
        <div class="text-sm text-gray-600">Assigned Date: <span class="font-medium text-gray-900"><?= date('M d, Y', strtotime($assignment['assigned_date'])) ?></span></div>
    </div>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Return Date <span class="text-red-600">*</span></labe>
                <input type="date" name="return_date" value="<?= old('return_date', date('Y-m-d')) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Return Condition <span class="text-red-600">*</span></labe>
                <select name="return_condition" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Condition</option>
                    <option value="good" <?= old('return_condition') == 'good' ? 'selected' : '' ?>>Good</option>
                    <option value="damaged" <?= old('return_condition') == 'damaged' ? 'selected' : '' ?>>Damaged</option>
                    <option value="needs_repair" <?= old('return_condition') == 'needs_repair' ? 'selected' : '' ?>>Needs Repair</option>
                </select>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Damage Notes (if any)</label>
            <textarea name="damage_notes" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('damage_notes') ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Process Return</button>
            <a href="<?= url('modules/asset_assignments/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
