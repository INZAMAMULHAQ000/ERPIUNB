<?php
$pageTitle = 'Holiday View';
require_once '../../includes/header.php';
if (!hasPermission('holidays.view')) { die('Access Denied'); }
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM holidays WHERE id = ?");
$stmt->execute([$id]);
$holiday = $stmt->fetch();
if (!$holiday) { die('Holiday not found'); }
?>
<div class="mb-6"><a href="index.php" class="text-blue-600 hover:underline">← Back to Holiday Calendar</a></div>
<div class="bg-white rounded-lg shadow p-6">
  <h1 class="text-2xl font-bold mb-2"><?= sanitize($holiday['holiday_name']) ?></h1>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
    <div><b>Date:</b> <?= date('M d, Y', strtotime($holiday['holiday_date'])) ?></div>
    <div><b>Type:</b> <span class="capitalize font-medium"><?= sanitize($holiday['holiday_type']) ?></span></div>
    <div class="md:col-span-2"><b>Description:</b> <?= sanitize($holiday['description'] ?? '-') ?></div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
