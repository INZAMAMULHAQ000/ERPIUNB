<?php
$pageTitle = 'Create Leave Type';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('leaves.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'leave_name' => $_POST['leave_name'] ?? '',
        'leave_code' => strtoupper($_POST['leave_code'] ?? ''),
        'total_days' => $_POST['total_days'] ?? 0,
        'carry_forward' => isset($_POST['carry_forward']) ? 1 : 0,
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];
    
    $errors = validate($data, [
        'leave_name' => 'required|min:2|max:100',
        'leave_code' => 'required|min:1|max:20',
        'total_days' => 'required'
    ]);
    
    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM leave_types WHERE leave_code = ?");
        $checkStmt->execute([$data['leave_code']]);
        if ($checkStmt->fetch()) {
            $errors['leave_code'] = 'Leave code already exists';
        }
    }
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/leave_types/create.php');
    }

    
        $stmt = $db->prepare("INSERT INTO leave_types (leave_name, leave_code, total_days, carry_forward, description, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['leave_name'], $data['leave_code'], $data['total_days'], $data['carry_forward'], $data['description'], $data['status']]);
        
        logAudit($_SESSION['user_id'], 'create_leave_type', 'leaves');
        flash('success', 'Leave type created successfully');
        to('http://localhost/ERP/modules/leave_types/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/leave_types/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Leave Types
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create Leave Type</h2>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?= sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Leave Name <span class="text-red-600">*</span></labe>
                <input type="text" name="leave_name" value="<?= old('leave_name') ?>" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Leave Code <span class="text-red-600">*</span></labe>
                <input type="text" name="leave_code" value="<?= old('leave_code') ?>" required placeholder="e.g., CL, SL, EL"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total Days per Year <span class="text-red-600">*</span></labe>
                <input type="number" name="total_days" value="<?= old('total_days', 0) ?>" required min="0"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        
        <div class="flex items-center">
            <input type="checkbox" name="carry_forward" id="carry_forward" value="1" <?= old('carry_forward') ? 'checked' : '' ?>
                class="rounded text-blue-600">
            <label for="carry_forward" class="ml-2 text-sm font-medium text-gray-700">Allow carry forward to next year</label>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Create</button>
            <a href="<?= url('modules/leave_types/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
