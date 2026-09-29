<?php
$pageTitle = 'Department View';
require_once '../../includes/header.php';
if (!hasPermission('departments.view')) { die('Access Denied'); }
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT d.*, (SELECT COUNT(*) FROM designations WHERE department_id = d.id AND status = 'active') as desig_count FROM departments d WHERE d.id = ?");
$stmt->execute([$id]);
$dept = $stmt->fetch();
if (!$dept) { die('Department not found'); }
?>
<div class="mb-6"><a href="index.php" class="text-blue-600 hover:underline">← Back to Departments</a></div>
<div class="bg-white rounded-lg shadow p-6">
  <h1 class="text-2xl font-bold mb-4"><?= sanitize($dept['department_name']) ?></h1>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
    <div><b>Code:</b> <?= sanitize($dept['department_code']) ?></div>
    <div><b>Status:</b> <span class="<?= $dept['status']==='active'?'text-green-600':'text-gray-500' ?> font-medium"><?= sanitize($dept['status']) ?></span></div>
    <div><b>Description:</b> <?= sanitize($dept['description'] ?? '-') ?></div>
    <div><b>Active Designations:</b> <?= $dept['desig_count'] ?></div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
