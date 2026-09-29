<?php
$pageTitle = 'Add Holiday';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('holidays.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'holiday_name' => $_POST['holiday_name'] ?? '',
        'holiday_date' => $_POST['holiday_date'] ?? '',
        'holiday_type' => $_POST['holiday_type'] ?? 'national',
        'description' => $_POST['description'] ?? ''
    ];
    
    $errors = validate($data, [
        'holiday_name' => 'required|min:2|max:200',
        'holiday_date' => 'required',
        'holiday_type' => 'required'
    ]);
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/holidays/create.php');
    }

    
        $stmt = $db->prepare("INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['holiday_name'], $data['holiday_date'], $data['holiday_type'], $data['description']]);
        
        logAudit($_SESSION['user_id'], 'create_holiday', 'holidays');
        flash('success', 'Holiday added successfully');
        to('http://localhost/ERP/modules/holidays/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/holidays/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Holidays
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Add Holiday</h2>
    
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Holiday Name <span class="text-red-600">*</span></labe>
                <input type="text" name="holiday_name" value="<?= old('holiday_name') ?>" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-600">*</span></labe>
                <input type="date" name="holiday_date" value="<?= old('holiday_date') ?>" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-600">*</span></labe>
                <select name="holiday_type" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="national" <?= old('holiday_type', 'national') === 'national' ? 'selected' : '' ?>>National</option>
                    <option value="festival" <?= old('holiday_type') === 'festival' ? 'selected' : '' ?>>Festival</option>
                    <option value="optional" <?= old('holiday_type') === 'optional' ? 'selected' : '' ?>>Optional</option>
                </select>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Add Holiday</button>
            <a href="<?= url('modules/holidays/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
