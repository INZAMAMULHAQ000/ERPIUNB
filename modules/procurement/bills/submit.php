<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect('index.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token. Please try again.');
    redirect('index.php');
}

if (!hasPermission('procurement.bills.create')) { die('Access Denied'); }

$db = getDB();
$billId = (int)($_POST['id'] ?? 0);
if ($billId <= 0) { flash('error', 'Invalid bill ID.'); redirect('index.php'); }

$result = BillService::submit($db, $billId, $_SESSION['user_id']);

if ($result['success']) {
    flash('success', 'Vendor bill submitted for approval.');
} else {
    flash('error', $result['message']);
}

redirect('view.php?id=' . $billId);
