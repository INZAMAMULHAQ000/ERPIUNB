<?php
$pageTitle = 'Edit SLA Policy';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('slas.edit')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM slas WHERE id = ?");
$stmt->execute([$id]);
$sla = $stmt->fetch();
if (!$sla) { flash('error', 'SLA not found'); to('http://localhost/ERP/modules/slas/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF token'); }

    $data = [
        'sla_name' => trim($_POST['sla_name'] ?? ''),
        'response_time_hours' => (int) ($_POST['response_time_hours'] ?? 24),
        'resolution_time_hours' => (int) ($_POST['resolution_time_hours'] ?? 72),
        'priority' => $_POST['priority'] ?? 'medium',
        'description' => trim($_POST['description'] ?? ''),
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'sla_name' => 'required|min:2|max:100'
    ]);

    if (empty($errors) && $data['response_time_hours'] <= 0) { $errors['response_time_hours'] = 'Must be greater than 0'; }
    if (empty($errors) && $data['resolution_time_hours'] <= 0) { $errors['resolution_time_hours'] = 'Must be greater than 0'; }

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM slas WHERE sla_name = ? AND id != ?");
        $checkStmt->execute([$data['sla_name'], $id]);
        if ($checkStmt->fetch()) { $errors['sla_name'] = 'SLA name already exists'; }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/slas/edit.php?id=' . $id);
    }

    
        $stmt = $db->prepare("UPDATE slas SET sla_name = ?, response_time_hours = ?, resolution_time_hours = ?, priority = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$data['sla_name'], $data['response_time_hours'], $data['resolution_time_hours'], $data['priority'], $data['description'], $data['status'], $id]);
    
        logAudit($_SESSION['user_id'], 'update_sla', 'slas');
        flash('success', 'SLA policy updated successfully');
        to('http://localhost/ERP/modules/slas/index.php'); 
     
 
} else { 
    $_POST = $sla; 
} 
?> 
 
<div class="mb-6"> 
    <a href="<?= url('modules/slas/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to SLAs</a> 
</div> 
 
<div class="bg-white rounded-lg shadow p-6"> 
    <h2 class="text-xl font-semibold mb-6">Edit SLA Policy</h2> 
 
    <?php if ($errors): ?> 
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"> 
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul> 
    </div> 
    <?php endif; ?> 
 
    <form method="POST" id="slaForm" class="space-y-4"> 
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"> 
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4"> 
            <div class="md:col-span-2"> 
                <label class="block text-sm font-medium text-gray-700 mb-1">SLA Name <span class="text-red-600">*</span></labe> 
                <input type="text" name="sla_name" value="<?= old('sla_name') ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"> 
            </div> 
            <div> 
                <label class="block text-sm font-medium text-gray-700 mb-1">Response Time (hours) <span class="text-red-600">*</span></labe> 
                <input type="number" name="response_time_hours" value="<?= old('response_time_hours') ?>" min="1" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"> 
            </div> 
            <div> 
                <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Time (hours) <span class="text-red-600">*</span></labe> 
                <input type="number" name="resolution_time_hours" value="<?= old('resolution_time_hours') ?>" min="1" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"> 
            </div> 
            <div> 
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label> 
                <select name="priority" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"> 
                    <?php foreach (['low', 'medium', 'high', 'critical'] as $p): ?> 
                    <option value="' . $p . '" <?= old('priority') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option> 
                    <?php endforeach; ?> 
                </select> 
            </div> 
            <div> 
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label> 
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"> 
                    <option value="active" <?= old('status') === 'active' ? 'selected' : '' ?>>Active</option> 
                    <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option> 
                </select> 
            </div> 
        </div> 
        <div> 
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label> 
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea> 
        </div> 
        <div class="pt-4"> 
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Update SLA</button> 
            <a href="<?= url('modules/slas/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a> 
        </div> 
    </form> 
</div> 

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    $("#slaForm").validate({

        rules: {

            sla_name: {
                required: true,
                minlength: 2,
                maxlength: 100
            },

            response_time_hours: {
                required: true,
                digits: true,
                min: 1
            },

            resolution_time_hours: {
                required: true,
                digits: true,
                min: 1
            }

        },

        messages: {

            sla_name: {
                required: "SLA name is required",
                minlength: "SLA name must be at least 2 characters",
                maxlength: "SLA name cannot exceed 100 characters"
            },

            response_time_hours: {
                required: "Response time is required",
                digits: "Please enter a valid whole number",
                min: "Response time must be greater than 0"
            },

            resolution_time_hours: {
                required: "Resolution time is required",
                digits: "Please enter a valid whole number",
                min: "Resolution time must be greater than 0"
            }

        },

        errorElement: "p",

        errorClass: "text-red-600 text-sm mt-1",

        highlight: function (element) {
            $(element).addClass("border-red-500");
        },

        unhighlight: function (element) {
            $(element).removeClass("border-red-500");
        },

        errorPlacement: function (error, element) {
            error.insertAfter(element);
        }

    });

});
</script>
 
<?php require_once '../../includes/footer.php'; ?> 
