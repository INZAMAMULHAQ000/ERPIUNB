<?php
// Standalone print view — no header/footer
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/helpers/security.php';
require_once dirname(__DIR__, 2) . '/helpers/currency.php';
require_once dirname(__DIR__, 2) . '/helpers/rbac.php';
require_once dirname(__DIR__, 2) . '/middleware/auth.php';

requireAuth();

if (!hasPermission('customer_invoices.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid invoice ID.');
}

// Load invoice with client info
$stmt = $db->prepare("
    SELECT ci.*, c.company_name, c.client_code, c.email, c.phone, c.address, c.gst_vat_number,
           p.project_name, p.project_code,
           u.name AS creator_name
    FROM customer_invoices ci
    JOIN clients c ON ci.client_id = c.id
    LEFT JOIN projects p ON ci.project_id = p.id
    LEFT JOIN users u ON ci.created_by = u.id
    WHERE ci.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found.');
}

// Load items
$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$statusBadges = [
    'draft' => 'background: #e5e7eb; color: #374151;',
    'approved' => 'background: #dbeafe; color: #1e40af;',
    'sent' => 'background: #e0e7ff; color: #3730a3;',
    'partially_paid' => 'background: #fef9c3; color: #854d0e;',
    'paid' => 'background: #d1fae5; color: #065f46;',
    'overdue' => 'background: #fee2e2; color: #991b1b;',
    'cancelled' => 'background: #e5e7eb; color: #4b5563;',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= sanitize($invoice['invoice_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { margin: 20mm; }
            body { font-size: 10pt; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Print Button -->
        <div class="no-print text-right mb-4">
            <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-print mr-2"></i>Print
            </button>
            <a href="view.php?id=<?= $id ?>" class="ml-2 bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>

        <!-- Invoice -->
        <div class="bg-white rounded-lg shadow p-8">
            <!-- Header -->
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">INVOICE</h1>
                    <p class="text-gray-500 text-lg">#<?= sanitize($invoice['invoice_number']) ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-bold text-gray-800"><?= sanitize($companyName ?? 'Your Company Name') ?></h2>
                    <p class="text-sm text-gray-600"><?= sanitize($companyAddress ?? 'Your Company Address') ?></p>
                    <p class="text-sm text-gray-600">Tax: <?= sanitize($companyTaxNumber ?? 'N/A') ?></p>
                </div>
            </div>

            <div class="border-t border-b border-gray-300 py-6 mb-6">
                <div class="flex justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Bill To</h3>
                        <p class="font-semibold text-gray-800"><?= sanitize($invoice['company_name']) ?></p>
                        <p class="text-sm text-gray-600"><?= nl2br(sanitize($invoice['address'] ?? '')) ?></p>
                        <p class="text-sm text-gray-600">Email: <?= sanitize($invoice['email'] ?? '') ?></p>
                        <p class="text-sm text-gray-600">Phone: <?= sanitize($invoice['phone'] ?? '') ?></p>
                        <?php if ($invoice['gst_vat_number']): ?>
                        <p class="text-sm text-gray-600">Tax Reg: <?= sanitize($invoice['gst_vat_number']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <table class="text-sm">
                            <tr>
                                <td class="text-gray-500 pr-4">Invoice Date:</td>
                                <td class="font-medium text-gray-800"><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-gray-500 pr-4">Due Date:</td>
                                <td class="font-medium text-gray-800"><?= date('M d, Y', strtotime($invoice['due_date'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-gray-500 pr-4">Currency:</td>
                                <td class="font-medium text-gray-800"><?= sanitize($invoice['currency']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-gray-500 pr-4">Payment Terms:</td>
                                <td class="font-medium text-gray-800"><?= sanitize($invoice['payment_terms'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td class="text-gray-500 pr-4">Status:</td>
                                <td><span style="<?= $statusBadges[$invoice['status']] ?? 'background: #e5e7eb; color: #374151;' ?> padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?= ucwords(str_replace('_', ' ', $invoice['status'])) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="w-full mb-6" style="border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">#</th>
                        <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Description</th>
                        <th style="padding: 10px 12px; text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Qty</th>
                        <th style="padding: 10px 12px; text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Unit</th>
                        <th style="padding: 10px 12px; text-align: right; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Unit Price</th>
                        <th style="padding: 10px 12px; text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Disc %</th>
                        <th style="padding: 10px 12px; text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Tax %</th>
                        <th style="padding: 10px 12px; text-align: right; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($items as $item): ?>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 12px; color: #6b7280; font-size: 13px;"><?= $idx++ ?></td>
                        <td style="padding: 10px 12px; color: #111827; font-size: 13px;"><?= sanitize($item['description']) ?></td>
                        <td style="padding: 10px 12px; text-align: center; color: #6b7280; font-size: 13px;"><?= (float)$item['quantity'] ?></td>
                        <td style="padding: 10px 12px; text-align: center; color: #6b7280; font-size: 13px;"><?= sanitize($item['unit'] ?? '-') ?></td>
                        <td style="padding: 10px 12px; text-align: right; font-family: monospace; color: #111827; font-size: 13px;"><?= formatMoney((float)$item['unit_price']) ?></td>
                        <td style="padding: 10px 12px; text-align: center; color: #6b7280; font-size: 13px;"><?= (float)$item['discount_percent'] > 0 ? (float)$item['discount_percent'] . '%' : '-' ?></td>
                        <td style="padding: 10px 12px; text-align: center; color: #6b7280; font-size: 13px;"><?= (float)$item['tax_percent'] > 0 ? (float)$item['tax_percent'] . '%' : '-' ?></td>
                        <td style="padding: 10px 12px; text-align: right; font-family: monospace; font-weight: 600; color: #111827; font-size: 13px;"><?= formatMoney((float)$item['line_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="flex justify-end mb-6">
                <table style="width: 300px;">
                    <tr>
                        <td style="padding: 6px 12px; text-align: right; color: #6b7280; font-size: 13px;">Subtotal:</td>
                        <td style="padding: 6px 12px; text-align: right; font-family: monospace; color: #111827; font-size: 13px;"><?= formatMoney((float)$invoice['subtotal']) ?></td>
                    </tr>
                    <?php if ((float)$invoice['discount_amount'] > 0): ?>
                    <tr>
                        <td style="padding: 6px 12px; text-align: right; color: #6b7280; font-size: 13px;">Discount:</td>
                        <td style="padding: 6px 12px; text-align: right; font-family: monospace; color: #6b7280; font-size: 13px;">-<?= formatMoney((float)$invoice['discount_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding: 6px 12px; text-align: right; color: #6b7280; font-size: 13px;">Tax:</td>
                        <td style="padding: 6px 12px; text-align: right; font-family: monospace; color: #6b7280; font-size: 13px;"><?= formatMoney((float)$invoice['tax_amount']) ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #111827;">
                        <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #111827; font-size: 15px;">Total:</td>
                        <td style="padding: 10px 12px; text-align: right; font-family: monospace; font-weight: 700; color: #111827; font-size: 15px;"><?= formatMoney((float)$invoice['total_amount']) ?></td>
                    </tr>
                    <?php if ((float)$invoice['paid_amount'] > 0): ?>
                    <tr>
                        <td style="padding: 6px 12px; text-align: right; color: #059669; font-size: 13px;">Paid:</td>
                        <td style="padding: 6px 12px; text-align: right; font-family: monospace; color: #059669; font-size: 13px;"><?= formatMoney((float)$invoice['paid_amount']) ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 12px; text-align: right; font-weight: 700; color: <?= (float)$invoice['balance_amount'] > 0 ? '#dc2626' : '#111827' ?>; font-size: 14px;">Balance Due:</td>
                        <td style="padding: 6px 12px; text-align: right; font-family: monospace; font-weight: 700; color: <?= (float)$invoice['balance_amount'] > 0 ? '#dc2626' : '#059669' ?>; font-size: 14px;"><?= formatMoney((float)$invoice['balance_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Terms & Notes -->
            <?php if ($invoice['notes']): ?>
            <div class="border-t border-gray-300 pt-4">
                <h4 style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">Notes</h4>
                <p style="font-size: 12px; color: #374151;"><?= nl2br(sanitize($invoice['notes'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($invoice['terms_conditions']): ?>
            <div class="border-t border-gray-300 pt-4 mt-4">
                <h4 style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">Terms & Conditions</h4>
                <p style="font-size: 12px; color: #374151;"><?= nl2br(sanitize($invoice['terms_conditions'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Print footer -->
            <div class="print-only mt-8 text-center text-xs text-gray-400">
                <p>Generated on <?= date('M d, Y h:i A') ?> | Invoice #<?= sanitize($invoice['invoice_number']) ?></p>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Auto-print when page loads?
        if (window.location.search.indexOf('auto=1') !== -1) {
            window.onload = function() { window.print(); };
        }
    </script>
</body>
</html>
