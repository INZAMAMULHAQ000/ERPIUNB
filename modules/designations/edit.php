<?php
$pageTitle = 'Edit Designation';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('designations.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM designations WHERE id = ?");
$stmt->execute([$id]);
$desig = $stmt->fetch();

if (!$desig) {
    flash('error', 'Designation not found');
    to('modules/designations/index.php');
}

$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'designation_name' => $_POST['designation_name'] ?? '',
        'department_id' => $_POST['department_id'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];
    
    $errors = validate($data, [
        'designation_name' => 'required|min:2|max:100',
        'department_id' => 'required'
    ]);
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/designations/edit.php?id=' . $id);
    }

        $stmt = $db->prepare("UPDATE designations SET designation_name = ?, department_id = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$data['designation_name'], $data['department_id'], $data['description'], $data['status'], $id]);
        
        logAudit($_SESSION['user_id'], 'update', 'designations');
        flash('success', 'Designation updated successfully');
        to('modules/designations/index.php');
    

    
    $desig = array_merge($desig, $data);
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Designations
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit Designation</h2>
    
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Designation Name <span class="text-red-600">*</span></labe>
                <input type="text" name="designation_name" value="<?= old('designation_name', $desig['designation_name']) ?>" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-600">*</span></labe>
                <select name="department_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= ($desig['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>><?= sanitize($dept['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description', $desig['description'] ?? '') ?></textarea>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= ($desig['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($desig['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Designation
            </button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>