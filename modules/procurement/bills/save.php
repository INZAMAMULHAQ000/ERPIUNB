<?php
$pageTitle = 'Save Vendor Bill';
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
$userId = $_SESSION['user_id'];

$header = [
    'vendor_invoice_number' => trim((string)($_POST['vendor_invoice_number'] ?? '')),
    'vendor_id' => (int)($_POST['vendor_id'] ?? 0),
    'company_id' => (int)($_POST['company_id'] ?? 0),
    'bill_date' => $_POST['bill_date'] ?? '',
    'due_date' => $_POST['due_date'] ?? '',
    'currency' => $_POST['currency'] ?? 'AED',
    'payment_terms' => trim((string)($_POST['payment_terms'] ?? '')),
    'notes' => trim((string)($_POST['notes'] ?? '')),
    'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
    'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
    'cost_center_id' => !empty($_POST['cost_center_id']) ? (int)$_POST['cost_center_id'] : null,
    'procurement_po_id' => !empty($_POST['procurement_po_id']) ? (int)$_POST['procurement_po_id'] : null,
    'procurement_gr_id' => !empty($_POST['gr_id']) ? (int)$_POST['gr_id'] : null,
    'procurement_sr_id' => !empty($_POST['sr_id']) ? (int)$_POST['sr_id'] : null,
    'tolerance_quantity' => (float)($_POST['tolerance_quantity'] ?? BillService::getSetting($db, 'bill_quantity_tolerance', 5)),
    'tolerance_price' => (float)($_POST['tolerance_price'] ?? BillService::getSetting($db, 'bill_price_tolerance', 5)),
    'tolerance_amount' => (float)($_POST['tolerance_amount'] ?? BillService::getSetting($db, 'bill_amount_tolerance', 5)),
];

$lines = [];
if (!empty($_POST['lines']) && is_array($_POST['lines'])) {
    foreach ($_POST['lines'] as $l) {
        if (trim((string)($l['item_name'] ?? '')) === '') continue;
        $lines[] = [
            'procurement_po_item_id' => !empty($l['procurement_po_item_id']) ? (int)$l['procurement_po_item_id'] : null,
            'procurement_gr_item_id' => !empty($l['procurement_gr_item_id']) ? (int)$l['procurement_gr_item_id'] : null,
            'procurement_sr_item_id' => !empty($l['procurement_sr_item_id']) ? (int)$l['procurement_sr_item_id'] : null,
            'item_name' => trim($l['item_name']),
            'description' => trim((string)($l['description'] ?? '')),
            'quantity' => (float)($l['quantity'] ?? 0),
            'unit' => $l['unit'] ?? 'pcs',
            'unit_price' => (float)($l['unit_price'] ?? 0),
            'discount_amount' => (float)($l['discount_amount'] ?? 0),
            'tax_percent' => (float)($l['tax_percent'] ?? 0),
        ];
    }
}

$result = BillService::createBill($db, $header, $lines, $userId);

if ($result['success']) {
    flash('success', 'Vendor bill ' . $result['number'] . ' created successfully.');
    redirect('view.php?id=' . $result['id']);
} else {
    foreach ($result['errors'] as $err) {
        flash('error', $err);
    }
    redirect('create.php');
}
