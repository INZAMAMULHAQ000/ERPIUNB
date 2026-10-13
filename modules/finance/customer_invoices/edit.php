<?php
$pageTitle = 'Edit Customer Invoice';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../tax/helpers/tax_ui.php';
if (!hasPermission('customer_invoices.edit')) {
    die('Access Denied');
}
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid invoice ID.');
    redirect('index.php');
}
// Load invoice
$stmt = $db->prepare("
    SELECT *
    FROM customer_invoices
    WHERE id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('index.php');
}
if ($invoice['status'] !== 'draft') {
    flash('error', 'Only draft invoices can be edited.');
    redirect('view.php?id=' . $id);
}
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
$clients = $db->query("
    SELECT id, client_code, company_name
    FROM clients
    WHERE status = 'active' AND deleted_at IS NULL
    ORDER BY company_name
")->fetchAll();
$projects = $db->query("
    SELECT id, project_code, project_name
    FROM projects
    WHERE status IN ('planning','active')
    ORDER BY project_name
")->fetchAll();
$defaultType = TaxService::getDefaultTaxType();
$defaultTypeId =
    $defaultType
    ? (int)$defaultType['id']
    : 0;
// Load items
$stmt = $db->prepare("
    SELECT *
    FROM invoice_items
    WHERE invoice_id = ?
    ORDER BY sort_order
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
// ---------------------------------------------------------
// POST Handler
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] =
            'Invalid or expired form token. Please try again.';
    }
    $client_id =
        (int)($_POST['client_id'] ?? 0);
    $project_id =
        !empty($_POST['project_id'])
        ? (int)$_POST['project_id']
        : null;
    $invoice_date =
        trim($_POST['invoice_date'] ?? '');
    $due_date =
        trim($_POST['due_date'] ?? '');
    $currency =
        trim($_POST['currency'] ?? 'AED');
    $payment_terms =
        trim($_POST['payment_terms'] ?? '');
    $notes =
        trim($_POST['notes'] ?? '');
    $posted_items =
        $_POST['items'] ?? [];
    // -----------------------------------------------------
    // Server-side validation
    // -----------------------------------------------------
    if ($client_id <= 0) {
        $errors[] =
            'Please select a client.';
    }
    if (empty($invoice_date)) {
        $errors[] =
            'Invoice date is required.';
    }
    if (empty($due_date)) {
        $errors[] =
            'Due date is required.';
    }
    if (
        $due_date &&
        $invoice_date &&
        $due_date < $invoice_date
    ) {
        $errors[] =
            'Due date cannot be before invoice date.';
    }
    if (
        empty($posted_items) ||
        !is_array($posted_items)
    ) {
        $errors[] =
            'At least one invoice item is required.';
    } else {
        $validItems = 0;
        foreach ($posted_items as $i => $item) {
            $desc =
                trim($item['description'] ?? '');
            $qty =
                (float)str_replace(
                    ',',
                    '',
                    $item['quantity'] ?? 0
                );
            $price =
                (float)str_replace(
                    ',',
                    '',
                    $item['unit_price'] ?? 0
                );
            if (
                empty($desc) ||
                $qty <= 0 ||
                $price <= 0
            ) {
                continue;
            }
            $validItems++;
        }
        if ($validItems === 0) {
            $errors[] =
                'At least one valid item (with description, quantity, and unit price) is required.';
        }
    }
    if (!empty($errors)) {
        saveFormState($errors);
        to(
            'modules/finance/customer_invoices/edit.php?id=' . $id
        );
    }
    // -----------------------------------------------------
    // Database transaction
    // -----------------------------------------------------
    try {
        $db->beginTransaction();
        $subtotal = 0;
        $total_discount_amount = 0;
        $total_tax_amount = 0;
        $total_amount = 0;
        $validLines = [];
        foreach ($posted_items as $i => $item) {
            $desc =
                trim($item['description'] ?? '');
            $qty =
                (float)str_replace(
                    ',',
                    '',
                    $item['quantity'] ?? 0
                );
            $price =
                (float)str_replace(
                    ',',
                    '',
                    $item['unit_price'] ?? 0
                );
            if (
                empty($desc) ||
                $qty <= 0 ||
                $price <= 0
            ) {
                continue;
            }
            $unit =
                trim($item['unit'] ?? '');
            $disc_pct =
                (float)str_replace(
                    ',',
                    '',
                    $item['discount_percent'] ?? 0
                );
            $tax_rate_id =
                (int)($item['tax_rate_id'] ?? 0);
            $tax_status =
                $item['tax_status'] ?? 'taxable';
            $exemption_id =
                (int)($item['exemption_id'] ?? 0);
            $gross_base =
                $qty * $price;
            $line_discount =
                $gross_base * ($disc_pct / 100);
            $base_after_discount =
                $gross_base - $line_discount;
            $calc =
                TaxService::calculateLineTax(
                    $base_after_discount,
                    [
                        'tax_rate_id' =>
                        $tax_rate_id ?: null,
                        'tax_status' =>
                        $tax_status,
                        'exemption_id' =>
                        $exemption_id ?: null,
                        'date' =>
                        $invoice_date,
                    ]
                );
            $subtotal +=
                $gross_base;
            $total_discount_amount +=
                $line_discount;
            $total_tax_amount +=
                (float)$calc['tax_amount'];
            $total_amount +=
                (float)$calc['total_amount'];
            $validLines[] = [
                'desc' =>
                $desc,
                'qty' =>
                $qty,
                'unit' =>
                $unit,
                'price' =>
                $price,
                'disc_pct' =>
                $disc_pct,
                'tax_rate_id' =>
                (int)$calc['tax_rate_id'],
                'tax_type_id' =>
                (int)$calc['tax_type_id'],
                'tax_status' =>
                $calc['tax_status'],
                'exemption_id' =>
                (int)$calc['exemption_id'],
                'taxable' =>
                (float)$calc['net_amount'],
                'tax' =>
                (float)$calc['tax_amount'],
                'total' =>
                (float)$calc['total_amount'],
                'pct' =>
                (float)$calc['rate_percentage'],
            ];
        }
        $balance =
            $total_amount -
            (float)$invoice['paid_amount'];
        // -------------------------------------------------
        // Update invoice
        // -------------------------------------------------
        $stmt = $db->prepare("
            UPDATE customer_invoices SET
                client_id = ?,
                project_id = ?,
                invoice_date = ?,
                due_date = ?,
                currency = ?,
                payment_terms = ?,
                notes = ?,
                subtotal = ?,
                discount_amount = ?,
                tax_amount = ?,
                total_amount = ?,
                balance_amount = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $client_id,
            $project_id,
            $invoice_date,
            $due_date,
            $currency,
            $payment_terms,
            $notes,
            $subtotal,
            $total_discount_amount,
            $total_tax_amount,
            $total_amount,
            $balance,
            $id
        ]);
        // Delete old items
        $db
            ->prepare("
                DELETE FROM invoice_items
                WHERE invoice_id = ?
            ")
            ->execute([$id]);
        // Re-insert items
        $sort = 0;
        foreach ($validLines as $L) {
            $stmt = $db->prepare("
                INSERT INTO invoice_items
                    (
                        invoice_id,
                        description,
                        quantity,
                        unit,
                        unit_price,
                        discount_percent,
                        tax_type_id,
                        tax_rate_id,
                        tax_status,
                        taxable_amount,
                        tax_exemption_id,
                        tax_amount,
                        line_total,
                        sort_order,
                        tax_percent,
                        tax_percentage,
                        total
                    )
                VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?
                    )
            ");
            $stmt->execute([
                $id,
                $L['desc'],
                $L['qty'],
                $L['unit'],
                $L['price'],
                $L['disc_pct'],
                $L['tax_type_id'] ?: null,
                $L['tax_rate_id'] ?: null,
                $L['tax_status'],
                $L['taxable'],
                $L['exemption_id'] ?: null,
                $L['tax'],
                $L['total'],
                $sort,
                $L['pct'],
                $L['pct'],
                $L['total'],
            ]);
            $sort++;
        }
        $db->commit();
        logAudit(
            $_SESSION['user_id'],
            'Updated invoice: ' . $invoice['invoice_number'],
            'customer_invoices'
        );
        flash(
            'success',
            'Invoice updated successfully.',
            'success'
        );
        redirect('index.php');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash(
            'error',
            'Database error: ' . $e->getMessage()
        );
        to(
            'modules/finance/customer_invoices/edit.php?id=' . $id
        );
    }
}
?>
<!-- =====================================================
     PAGE HEADER
===================================================== -->
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Edit Invoice
        </h1>
        <p class="text-gray-600">
            Editing invoice #<?= sanitize($invoice['invoice_number']) ?>
        </p>
    </div>
    <div class="flex gap-2">
        <a
            href="view.php?id=<?= $id ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded
                   hover:bg-gray-700 transition">
            <i class="fas fa-eye mr-2"></i>
            View Invoice
        </a>
        <a
            href="index.php"
            class="bg-gray-300 text-gray-700 px-4 py-2 rounded
                   hover:bg-gray-400 transition">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Invoices
        </a>
    </div>
</div>
<!-- =====================================================
     SERVER ERRORS
===================================================== -->
<?php if (!empty($errors)): ?>
    <div
        class="bg-red-100 border border-red-400
               text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $err): ?>
                <li>
                    <?= sanitize($err) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<!-- =====================================================
     FORM
===================================================== -->
<div class="bg-white rounded-lg shadow p-6">
    <form
        method="POST"
        id="invoiceForm"
        class="space-y-4"
        novalidate>
        <input
            type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">
        <!-- =================================================
             HEADER
        ================================================== -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Invoice Number -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Invoice Number
                </label>
                <input
                    type="text"
                    value="<?= sanitize($invoice['invoice_number']) ?>"
                    readonly
                    class="w-full px-3 py-2 border rounded
                           bg-gray-100 text-gray-600">
            </div>
            <!-- Client -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Client
                    <span class="text-red-500">*</span>
                </label>
                <select
                    name="client_id"
                    id="client_id"
                    class="w-full px-3 py-2 border rounded
                           focus:ring-2 focus:ring-blue-500">
                    <option value="">
                        Select Client
                    </option>
                    <?php foreach ($clients as $c): ?>
                        <option
                            value="<?= $c['id'] ?>"
                            <?= ($invoice['client_id'] == $c['id'])
                                ? 'selected'
                                : '' ?>>
                            <?= sanitize($c['company_name']) ?>
                            (<?= sanitize($c['client_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Project -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Project (Optional)
                </label>
                <select
                    name="project_id"
                    class="w-full px-3 py-2 border rounded
                           focus:ring-2 focus:ring-blue-500">
                    <option value="">
                        Select Project
                    </option>
                    <?php foreach ($projects as $p): ?>
                        <option
                            value="<?= $p['id'] ?>"
                            <?= ($invoice['project_id'] == $p['id'])
                                ? 'selected'
                                : '' ?>>
                            <?= sanitize($p['project_name']) ?>
                            (<?= sanitize($p['project_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- =================================================
             DATE / CURRENCY / PAYMENT TERMS
        ================================================== -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Invoice Date -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Invoice Date
                    <span class="text-red-500">*</span>
                </label>
                <input
                    type="date"
                    name="invoice_date"
                    id="invoice_date"
                    value="<?= $invoice['invoice_date'] ?>"
                    class="w-full px-3 py-2 border rounded
                           focus:ring-2 focus:ring-blue-500">
            </div>
            <!-- Due Date -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Due Date
                    <span class="text-red-500">*</span>
                </label>
                <input
                    type="date"
                    name="due_date"
                    id="due_date"
                    value="<?= $invoice['due_date'] ?>"
                    class="w-full px-3 py-2 border rounded
                           focus:ring-2 focus:ring-blue-500">
            </div>
            <!-- Currency -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Currency
                </label>
                <select
                    name="currency"
                    class="w-full px-3 py-2 border rounded
                           focus:ring-2 focus:ring-blue-500">
                    <?php
                    $currencies = [
                        'AED' =>
                        'AED - UAE Dirham',
                        'USD' =>
                        'USD - US Dollar',
                        'EUR' =>
                        'EUR - Euro',
                        'GBP' =>
                        'GBP - British Pound',
                        'SAR' =>
                        'SAR - Saudi Riyal',
                        'INR' =>
                        'INR - Indian Rupee'
                    ];
                    foreach (
                        $currencies
                        as $code => $label
                    ):
                    ?>
                        <option
                            value="<?= $code ?>"
                            <?= $invoice['currency'] === $code
                                ? 'selected'
                                : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Payment Terms -->
            <div>
                <label
                    class="block text-sm font-medium
                           text-gray-700 mb-1">
                    Payment Terms
                </label>
                <select
                    name="payment_terms"
                    class="w-full px-3 py-2 border rounded
                           focus:ring-2 focus:ring-blue-500"
                    onchange="setDueDate(this.value)">
                    <option value="">
                        Select Terms
                    </option>
                    <option
                        value="Due on Receipt"
                        <?= $invoice['payment_terms'] ===
                            'Due on Receipt'
                            ? 'selected'
                            : '' ?>>
                        Due on Receipt
                    </option>
                    <option
                        value="Net 15"
                        <?= $invoice['payment_terms'] ===
                            'Net 15'
                            ? 'selected'
                            : '' ?>>
                        Net 15
                    </option>
                    <option
                        value="Net 30"
                        <?= $invoice['payment_terms'] ===
                            'Net 30'
                            ? 'selected'
                            : '' ?>>
                        Net 30
                    </option>
                    <option
                        value="Net 60"
                        <?= $invoice['payment_terms'] ===
                            'Net 60'
                            ? 'selected'
                            : '' ?>>
                        Net 60
                    </option>
                </select>
            </div>
        </div>
        <!-- =================================================
             NOTES
        ================================================== -->
        <div>
            <label
                class="block text-sm font-medium
                       text-gray-700 mb-1">
                Notes
            </label>
            <textarea
                name="notes"
                rows="2"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded
                       focus:ring-2 focus:ring-blue-500"
                placeholder="Optional notes"><?= sanitize($invoice['notes'] ?? '') ?></textarea>
        </div>
        <!-- =================================================
             INVOICE ITEMS
        ================================================== -->
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-2">
                Invoice Items
            </h3>
            <div class="overflow-x-auto">
                <table
                    class="w-full"
                    id="itemsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-3 py-2 text-left
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:22%">
                                Description
                                <span class="text-red-500">*</span>
                            </th>
                            <th
                                class="px-3 py-2 text-center
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:7%">
                                Qty
                                <span class="text-red-500">*</span>
                            </th>
                            <th
                                class="px-3 py-2 text-center
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:7%">
                                Unit
                            </th>
                            <th
                                class="px-3 py-2 text-right
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:10%">
                                Unit Price
                                <span class="text-red-500">*</span>
                            </th>
                            <th
                                class="px-3 py-2 text-center
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:6%">
                                Disc %
                            </th>
                            <th
                                class="px-3 py-2 text-center
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:20%">
                                Tax
                            </th>
                            <th
                                class="px-3 py-2 text-right
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:10%">
                                Tax Amt
                            </th>
                            <th
                                class="px-3 py-2 text-right
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:10%">
                                Line Total
                            </th>
                            <th
                                class="px-3 py-2 text-center
                                       text-xs font-medium
                                       text-gray-500 uppercase"
                                style="width:4%">
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        id="itemsBody"
                        class="divide-y divide-gray-200">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $idx => $item): ?>
                                <tr class="item-row">
                                    <!-- Description -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            name="items[<?= $idx ?>][description]"
                                            class="w-full px-2 py-2 border rounded"
                                            value="<?= sanitize($item['description']) ?>">
                                    </td>
                                    <!-- Quantity -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            name="items[<?= $idx ?>][quantity]"
                                            step="0.01"
                                            min="0.01"
                                            value="<?= $item['quantity'] ?>"
                                            class="w-full px-2 py-2 border rounded
                                                   text-center qty-input">
                                    </td>
                                    <!-- Unit -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            name="items[<?= $idx ?>][unit]"
                                            class="w-full px-2 py-2 border rounded
                                                   text-center"
                                            value="<?= sanitize($item['unit'] ?? '') ?>">
                                    </td>
                                    <!-- Unit Price -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            name="items[<?= $idx ?>][unit_price]"
                                            step="0.01"
                                            min="0.01"
                                            value="<?= $item['unit_price'] ?>"
                                            class="w-full px-2 py-2 border rounded
                                                   text-right price-input">
                                    </td>
                                    <!-- Discount -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            name="items[<?= $idx ?>][discount_percent]"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            value="<?= $item['discount_percent'] ?>"
                                            class="w-full px-2 py-2 border rounded
                                                   text-center disc-input">
                                    </td>
                                    <!-- Tax -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="hidden"
                                            class="base-before-tax"
                                            value="<?= (float)$item['taxable_amount'] ?>">
                                        <?= taxui_doc_line_picker(
                                            'items[' . $idx . ']',
                                            $item['tax_rate_id']
                                                ? (int)$item['tax_rate_id']
                                                : null,
                                            $item['tax_type_id']
                                                ? (int)$item['tax_type_id']
                                                : $defaultTypeId,
                                            $item['tax_status'] ?? 'taxable',
                                            $item['tax_exemption_id']
                                                ? (int)$item['tax_exemption_id']
                                                : null
                                        ) ?>
                                    </td>
                                    <!-- Tax Amount -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            class="w-full px-2 py-2
                                                   border-0 bg-transparent
                                                   text-right font-mono
                                                   line-tax"
                                            value="<?= number_format(
                                                        $item['tax_amount'],
                                                        2
                                                    ) ?>"
                                            readonly>
                                    </td>
                                    <!-- Line Total -->
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            class="w-full px-2 py-2
                                                   border-0 bg-transparent
                                                   text-right font-mono
                                                   font-bold line-total"
                                            value="<?= number_format(
                                                        $item['line_total'],
                                                        2
                                                    ) ?>"
                                            readonly>
                                    </td>
                                    <!-- Remove -->
                                    <td
                                        class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            onclick="removeItem(this)"
                                            class="text-red-500
                                                   hover:text-red-700">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Empty item row -->
                            <tr class="item-row">
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        name="items[0][description]"
                                        class="w-full px-2 py-2 border rounded"
                                        placeholder="Item description">
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        name="items[0][quantity]"
                                        step="0.01"
                                        min="0.01"
                                        value="1"
                                        class="w-full px-2 py-2 border rounded
                                               text-center qty-input">
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        name="items[0][unit]"
                                        class="w-full px-2 py-2 border rounded
                                               text-center"
                                        placeholder="pcs">
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        name="items[0][unit_price]"
                                        step="0.01"
                                        min="0.01"
                                        value="0"
                                        class="w-full px-2 py-2 border rounded
                                               text-right price-input">
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        name="items[0][discount_percent]"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value="0"
                                        class="w-full px-2 py-2 border rounded
                                               text-center disc-input">
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="hidden"
                                        class="base-before-tax"
                                        value="0">
                                    <?= taxui_doc_line_picker(
                                        'items[0]',
                                        null,
                                        $defaultTypeId
                                    ) ?>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        class="w-full px-2 py-2
                                               border-0 bg-transparent
                                               text-right font-mono line-tax"
                                        value="0.00"
                                        readonly>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        class="w-full px-2 py-2
                                               border-0 bg-transparent
                                               text-right font-mono
                                               font-bold line-total"
                                        value="0.00"
                                        readonly>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        onclick="removeItem(this)"
                                        class="text-red-500
                                               hover:text-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <!-- =================================================
                         TOTALS
                    ================================================== -->
                    <tfoot
                        class="bg-gray-50 font-semibold">
                        <tr>
                            <td
                                colspan="7"
                                class="px-3 py-2 text-right">
                                Subtotal (before discount):
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono"
                                id="displaySubtotal">
                                <?= number_format(
                                    $invoice['subtotal'],
                                    2
                                ) ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td
                                colspan="7"
                                class="px-3 py-2 text-right
                                       text-gray-600">
                                Discount:
                            </td>
                            <td
                                class="px-3 py-2 text-right
                                       font-mono text-gray-600"
                                id="displayDiscount">
                                <?= number_format(
                                    $invoice['discount_amount'],
                                    2
                                ) ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td
                                colspan="7"
                                class="px-3 py-2 text-right
                                       text-gray-600">
                                Tax:
                            </td>
                            <td
                                class="px-3 py-2 text-right
                                       font-mono text-gray-600"
                                id="displayTax">
                                <?= number_format(
                                    $invoice['tax_amount'],
                                    2
                                ) ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr class="text-lg">
                            <td
                                colspan="7"
                                class="px-3 py-2 text-right
                                       text-gray-900">
                                Total:
                            </td>
                            <td
                                class="px-3 py-2 text-right
                                       font-mono font-bold
                                       text-gray-900"
                                id="displayTotal">
                                <?= number_format(
                                    $invoice['total_amount'],
                                    2
                                ) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <!-- Add Item -->
            <button
                type="button"
                onclick="addItem()"
                class="mt-2 text-blue-600
                       hover:text-blue-800">
                <i class="fas fa-plus mr-1"></i>
                Add Item
            </button>
        </div>
        <!-- =================================================
             SUBMIT
        ================================================== -->
        <div class="pt-4 border-t">
            <input
                type="hidden"
                name="subtotal"
                id="subtotal_hidden"
                value="<?= $invoice['subtotal'] ?>">
            <input
                type="hidden"
                name="total_discount"
                id="discount_hidden"
                value="<?= $invoice['discount_amount'] ?>">
            <input
                type="hidden"
                name="total_tax"
                id="tax_hidden"
                value="<?= $invoice['tax_amount'] ?>">
            <input
                type="hidden"
                name="total_amount"
                id="total_hidden"
                value="<?= $invoice['total_amount'] ?>">
            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2
                       rounded hover:bg-blue-700 transition">
                <i class="fas fa-save mr-2"></i>
                Update Invoice
            </button>
            <a
                href="index.php"
                class="ml-4 text-gray-600
                       hover:text-gray-900">
                Cancel
            </a>
        </div>
    </form>
</div>
<!-- =====================================================
     TAX JS
===================================================== -->
<script src="../tax/helpers/tax_doc.js"></script>

<!-- =====================================================
     JQUERY
===================================================== -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    /*
    |--------------------------------------------------------------------------
    | Tax Inclusive
    |--------------------------------------------------------------------------
    */

    window.TAX_INCLUSIVE =
        <?= TaxService::pricesInclusive() ? '1' : '0' ?>;


    /*
    |--------------------------------------------------------------------------
    | Item Index
    |--------------------------------------------------------------------------
    */

    let itemIndex =
        <?= max(count($items), 1) ?>;


    /*
    |--------------------------------------------------------------------------
    | CSS
    |--------------------------------------------------------------------------
    */

    const addItemStyle =
        document.createElement('style');

    addItemStyle.textContent = `
        .add-item-error-border {
            border-color: #ef4444 !important;
        }

        .add-item-error {
            display: block;
            margin-top: 4px;
            color: #ef4444;
            font-size: 12px;
            line-height: 1.4;
        }

        .invoice-items-error {
            display: block;
            margin-top: 4px;
            color: #ef4444;
            font-size: 12px;
        }
    `;

    document.head.appendChild(addItemStyle);


    /*
    |--------------------------------------------------------------------------
    | Show Last Row Error
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | This function MUST be outside setDueDate().
    |
    */

    function showLastRowError(input, message) {

        if (!input) {
            return;
        }

        /*
        |--------------------------------------------------------------
        | Remove existing error for this input
        |--------------------------------------------------------------
        */

        const parent =
            input.parentElement;

        if (!parent) {
            return;
        }

        parent
            .querySelectorAll('.add-item-error')
            .forEach(function(error) {
                error.remove();
            });


        /*
        |--------------------------------------------------------------
        | Red border
        |--------------------------------------------------------------
        */

        input.classList.add(
            'add-item-error-border'
        );


        /*
        |--------------------------------------------------------------
        | Create error
        |--------------------------------------------------------------
        */

        const error =
            document.createElement('div');

        error.className =
            'add-item-error';

        error.textContent =
            message;


        /*
        |--------------------------------------------------------------
        | Display below input
        |--------------------------------------------------------------
        */

        parent.appendChild(error);
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Last Row Errors
    |--------------------------------------------------------------------------
    */

    function clearLastRowErrors(row) {

        if (!row) {
            return;
        }

        row
            .querySelectorAll('.add-item-error')
            .forEach(function(error) {
                error.remove();
            });

        row
            .querySelectorAll('.add-item-error-border')
            .forEach(function(input) {
                input.classList.remove(
                    'add-item-error-border'
                );
            });
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Last Item Row
    |--------------------------------------------------------------------------
    |
    | Only LAST row is validated before adding another row.
    |
    */

    function validateLastItemRow() {

        const tbody =
            document.querySelector('#itemsBody');

        if (!tbody) {
            return false;
        }


        const rows =
            tbody.querySelectorAll('.item-row');

        if (!rows.length) {
            return false;
        }


        /*
        |--------------------------------------------------------------
        | Get LAST row
        |--------------------------------------------------------------
        */

        const lastRow =
            rows[rows.length - 1];


        /*
        |--------------------------------------------------------------
        | Clear previous errors
        |--------------------------------------------------------------
        */

        clearLastRowErrors(lastRow);


        /*
        |--------------------------------------------------------------
        | Inputs
        |--------------------------------------------------------------
        */

        const descriptionInput =
            lastRow.querySelector(
                'input[name*="[description]"]'
            );

        const quantityInput =
            lastRow.querySelector(
                'input[name*="[quantity]"]'
            );

        const unitPriceInput =
            lastRow.querySelector(
                'input[name*="[unit_price]"]'
            );


        let hasError = false;

        let firstError = null;


        /*
        |--------------------------------------------------------------
        | Description
        |--------------------------------------------------------------
        */

        if (
            !descriptionInput ||
            descriptionInput.value.trim() === ''
        ) {

            showLastRowError(
                descriptionInput,
                'Please enter item description.'
            );

            hasError = true;

            if (!firstError) {
                firstError =
                    descriptionInput;
            }
        }


        /*
        |--------------------------------------------------------------
        | Quantity
        |--------------------------------------------------------------
        */

        const quantity =
            parseFloat(
                quantityInput?.value || 0
            );

        if (
            !quantityInput ||
            isNaN(quantity) ||
            quantity <= 0
        ) {

            showLastRowError(
                quantityInput,
                'Quantity must be greater than zero.'
            );

            hasError = true;

            if (!firstError) {
                firstError =
                    quantityInput;
            }
        }


        /*
        |--------------------------------------------------------------
        | Unit Price
        |--------------------------------------------------------------
        */

        const unitPrice =
            parseFloat(
                unitPriceInput?.value || 0
            );

        if (
            !unitPriceInput ||
            isNaN(unitPrice) ||
            unitPrice <= 0
        ) {

            showLastRowError(
                unitPriceInput,
                'Unit price must be greater than zero.'
            );

            hasError = true;

            if (!firstError) {
                firstError =
                    unitPriceInput;
            }
        }


        /*
        |--------------------------------------------------------------
        | Focus first invalid input
        |--------------------------------------------------------------
        */

        if (
            hasError &&
            firstError
        ) {

            firstError.focus();
        }


        return !hasError;
    }


    /*
    |--------------------------------------------------------------------------
    | Add Item
    |--------------------------------------------------------------------------
    */

    function addItem() {

        const tbody =
            document.querySelector('#itemsBody');

        if (!tbody) {
            return;
        }


        /*
        |--------------------------------------------------------------
        | Get all rows
        |--------------------------------------------------------------
        */

        const rows =
            tbody.querySelectorAll('.item-row');

        if (!rows.length) {
            return;
        }


        /*
        |--------------------------------------------------------------
        | IMPORTANT
        |
        | Validate ONLY LAST ROW.
        |
        | If last row is empty/invalid:
        | STOP.
        |
        | Do NOT create next row.
        |--------------------------------------------------------------
        */

        if (!validateLastItemRow()) {
            return;
        }


        /*
        |--------------------------------------------------------------
        | Last row is valid
        | Create new row
        |--------------------------------------------------------------
        */

        const lastRow =
            rows[rows.length - 1];

        const newRow =
            lastRow.cloneNode(true);


        /*
        |--------------------------------------------------------------
        | Current item index
        |--------------------------------------------------------------
        */

        const currentIndex =
            itemIndex;


        /*
        |--------------------------------------------------------------
        | Update input/select names
        |--------------------------------------------------------------
        */

        newRow
            .querySelectorAll('[name]')
            .forEach(function(element) {

                element.name =
                    element.name.replace(
                        /items\[\d+\]/,
                        'items[' +
                        currentIndex +
                        ']'
                    );
            });


        /*
        |--------------------------------------------------------------
        | Reset all input values
        |--------------------------------------------------------------
        */

        newRow
            .querySelectorAll('input')
            .forEach(function(input) {

                /*
                |------------------------------------------------------
                | Hidden input
                |------------------------------------------------------
                */

                if (
                    input.type === 'hidden'
                ) {

                    input.value = '';

                    return;
                }


                /*
                |------------------------------------------------------
                | Description
                |------------------------------------------------------
                */

                if (
                    input.name.includes(
                        '[description]'
                    )
                ) {

                    input.value = '';

                    return;
                }


                /*
                |------------------------------------------------------
                | Quantity
                |------------------------------------------------------
                */

                if (
                    input.name.includes(
                        '[quantity]'
                    )
                ) {

                    input.value = '1';

                    return;
                }


                /*
                |------------------------------------------------------
                | Unit
                |------------------------------------------------------
                */

                if (
                    input.name.includes(
                        '[unit]'
                    )
                ) {

                    input.value = '';

                    return;
                }


                /*
                |------------------------------------------------------
                | Unit Price
                |------------------------------------------------------
                */

                if (
                    input.name.includes(
                        '[unit_price]'
                    )
                ) {

                    input.value = '0';

                    return;
                }


                /*
                |------------------------------------------------------
                | Discount
                |------------------------------------------------------
                */

                if (
                    input.name.includes(
                        '[discount_percent]'
                    )
                ) {

                    input.value = '0';

                    return;
                }


                /*
                |------------------------------------------------------
                | Tax Amount
                |------------------------------------------------------
                */

                if (
                    input.classList.contains(
                        'line-tax'
                    )
                ) {

                    input.value = '0.00';

                    return;
                }


                /*
                |------------------------------------------------------
                | Line Total
                |------------------------------------------------------
                */

                if (
                    input.classList.contains(
                        'line-total'
                    )
                ) {

                    input.value = '0.00';

                    return;
                }


                /*
                |------------------------------------------------------
                | Base Before Tax
                |------------------------------------------------------
                */

                if (
                    input.classList.contains(
                        'base-before-tax'
                    )
                ) {

                    input.value = '0';

                    return;
                }


                /*
                |------------------------------------------------------
                | Other text inputs
                |------------------------------------------------------
                */

                if (
                    input.type === 'text'
                ) {

                    input.value = '';
                }

            });


        /*
        |--------------------------------------------------------------
        | Reset selects
        |--------------------------------------------------------------
        */

        newRow
            .querySelectorAll('select')
            .forEach(function(select) {

                select.selectedIndex = 0;
            });


        /*
        |--------------------------------------------------------------
        | Remove validation/error classes
        |--------------------------------------------------------------
        */

        clearLastRowErrors(newRow);


        /*
        |--------------------------------------------------------------
        | Remove jQuery validation error labels
        |--------------------------------------------------------------
        */

        newRow
            .querySelectorAll(
                '.error'
            )
            .forEach(function(error) {

                error.remove();
            });


        /*
        |--------------------------------------------------------------
        | Append new row
        |--------------------------------------------------------------
        */

        tbody.appendChild(newRow);


        /*
        |--------------------------------------------------------------
        | Wire Tax
        |--------------------------------------------------------------
        */

        if (
            typeof window.wireTaxRow ===
            'function'
        ) {

            window.wireTaxRow(
                newRow
            );
        }


        /*
        |--------------------------------------------------------------
        | Apply validation
        |--------------------------------------------------------------
        */

        if (
            typeof applyItemValidation ===
            'function'
        ) {

            applyItemValidation(
                newRow
            );
        }


        /*
        |--------------------------------------------------------------
        | Increase item index
        |--------------------------------------------------------------
        */

        itemIndex++;


        /*
        |--------------------------------------------------------------
        | Recalculate totals
        |--------------------------------------------------------------
        */

        if (
            typeof recalcDocTotals ===
            'function'
        ) {

            recalcDocTotals(
                'itemsTable'
            );
        }


        /*
        |--------------------------------------------------------------
        | Focus new description
        |--------------------------------------------------------------
        */

        const newDescription =
            newRow.querySelector(
                'input[name*="[description]"]'
            );

        if (newDescription) {
            newDescription.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    function removeItem(btn) {

        const row =
            btn.closest('tr');

        if (!row) {
            return;
        }


        const rows =
            document.querySelectorAll(
                '#itemsBody .item-row'
            );


        /*
        |--------------------------------------------------------------
        | Always keep at least one row
        |--------------------------------------------------------------
        */

        if (
            rows.length <= 1
        ) {

            return;
        }


        /*
        |--------------------------------------------------------------
        | Remove row
        |--------------------------------------------------------------
        */

        row.remove();


        /*
        |--------------------------------------------------------------
        | Clear old errors
        |--------------------------------------------------------------
        */

        $('.invoice-items-error').remove();


        /*
        |--------------------------------------------------------------
        | Recalculate
        |--------------------------------------------------------------
        */

        if (
            typeof recalcDocTotals ===
            'function'
        ) {

            recalcDocTotals(
                'itemsTable'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Set Due Date
    |--------------------------------------------------------------------------
    */

    function setDueDate(terms) {

        const invoiceDateElement =
            document.querySelector(
                'input[name="invoice_date"]'
            );


        const dueDateElement =
            document.getElementById(
                'due_date'
            );


        if (!dueDateElement) {
            return;
        }


        const invDate =
            invoiceDateElement?.value ||
            new Date()
            .toISOString()
            .split('T')[0];


        const d =
            new Date(invDate);


        /*
        |--------------------------------------------------------------
        | Due on Receipt
        |--------------------------------------------------------------
        */

        if (
            terms ===
            'Due on Receipt'
        ) {

            dueDateElement.value =
                invDate;

            return;
        }


        /*
        |--------------------------------------------------------------
        | Net 15
        |--------------------------------------------------------------
        */

        if (
            terms ===
            'Net 15'
        ) {

            d.setDate(
                d.getDate() + 15
            );

            dueDateElement.value =
                d.toISOString()
                .split('T')[0];

            return;
        }


        /*
        |--------------------------------------------------------------
        | Net 30
        |--------------------------------------------------------------
        */

        if (
            terms ===
            'Net 30'
        ) {

            d.setDate(
                d.getDate() + 30
            );

            dueDateElement.value =
                d.toISOString()
                .split('T')[0];

            return;
        }


        /*
        |--------------------------------------------------------------
        | Net 60
        |--------------------------------------------------------------
        */

        if (
            terms ===
            'Net 60'
        ) {

            d.setDate(
                d.getDate() + 60
            );

            dueDateElement.value =
                d.toISOString()
                .split('T')[0];

            return;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Document Ready
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {


        /*
        |------------------------------------------------------------------
        | Description
        |------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name*="[description]"]',
            function() {

                let value =
                    $(this).val();


                value =
                    value.replace(
                        /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                        ''
                    );


                value =
                    value.replace(
                        /\s{2,}/g,
                        ' '
                    );


                $(this).val(
                    value.substring(
                        0,
                        500
                    )
                );


                /*
                |----------------------------------------------------------
                | Remove add-item error
                |----------------------------------------------------------
                */

                if (
                    $(this).val().trim() !== ''
                ) {

                    $(this)
                        .removeClass(
                            'add-item-error-border'
                        );

                    $(this)
                        .parent()
                        .find(
                            '.add-item-error'
                        )
                        .remove();
                }
            }
        );


        /*
        |------------------------------------------------------------------
        | Unit
        |------------------------------------------------------------------
        |
        | ONLY NUMBERS
        |
        */

        $(document).on(
            'input',
            'input[name*="[unit]"]',
            function() {

                let value =
                    $(this).val();


                /*
                |----------------------------------------------------------
                | Only numbers allowed
                |----------------------------------------------------------
                */

                value =
                    value.replace(
                        /[^0-9]/g,
                        ''
                    );


                $(this).val(
                    value.substring(
                        0,
                        10
                    )
                );
            }
        );


        /*
        |------------------------------------------------------------------
        | Unit - Block paste characters
        |------------------------------------------------------------------
        */

        $(document).on(
            'paste',
            'input[name*="[unit]"]',
            function(event) {

                const pastedText =
                    (
                        event.originalEvent ||
                        event
                    )
                    .clipboardData
                    .getData('text');


                if (
                    !/^\d*$/.test(
                        pastedText
                    )
                ) {

                    event.preventDefault();
                }
            }
        );


        /*
        |------------------------------------------------------------------
        | Unit - Block non numeric keyboard characters
        |------------------------------------------------------------------
        */

        $(document).on(
            'keydown',
            'input[name*="[unit]"]',
            function(event) {

                /*
                |----------------------------------------------------------
                | Allow control/navigation keys
                |----------------------------------------------------------
                */

                const allowedKeys = [
                    'Backspace',
                    'Delete',
                    'Tab',
                    'ArrowLeft',
                    'ArrowRight',
                    'Home',
                    'End'
                ];


                if (
                    allowedKeys.includes(
                        event.key
                    )
                ) {

                    return;
                }


                /*
                |----------------------------------------------------------
                | Allow Ctrl/Cmd combinations
                |----------------------------------------------------------
                */

                if (
                    event.ctrlKey ||
                    event.metaKey
                ) {

                    return;
                }


                /*
                |----------------------------------------------------------
                | Only 0-9
                |----------------------------------------------------------
                */

                if (
                    !/^[0-9]$/.test(
                        event.key
                    )
                ) {

                    event.preventDefault();
                }
            }
        );


        /*
        |------------------------------------------------------------------
        | Quantity
        |------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name*="[quantity]"]',
            function() {

                let value =
                    $(this).val();


                value =
                    value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                const parts =
                    value.split('.');


                if (
                    parts.length > 2
                ) {

                    value =
                        parts[0] +
                        '.' +
                        parts
                        .slice(1)
                        .join('');
                }


                if (
                    value.includes('.')
                ) {

                    const decimalParts =
                        value.split('.');


                    value =
                        decimalParts[0] +
                        '.' +
                        decimalParts[1]
                        .substring(
                            0,
                            2
                        );
                }


                $(this).val(
                    value
                );


                /*
                |----------------------------------------------------------
                | Remove error when valid
                |----------------------------------------------------------
                */

                const number =
                    parseFloat(
                        value
                    );


                if (
                    !isNaN(number) &&
                    number > 0
                ) {

                    $(this)
                        .removeClass(
                            'add-item-error-border'
                        );

                    $(this)
                        .parent()
                        .find(
                            '.add-item-error'
                        )
                        .remove();
                }
            }
        );


        /*
        |------------------------------------------------------------------
        | Unit Price
        |------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name*="[unit_price]"]',
            function() {

                let value =
                    $(this).val();


                value =
                    value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                const parts =
                    value.split('.');


                if (
                    parts.length > 2
                ) {

                    value =
                        parts[0] +
                        '.' +
                        parts
                        .slice(1)
                        .join('');
                }


                if (
                    value.includes('.')
                ) {

                    const decimalParts =
                        value.split('.');


                    value =
                        decimalParts[0] +
                        '.' +
                        decimalParts[1]
                        .substring(
                            0,
                            2
                        );
                }


                $(this).val(
                    value
                );


                /*
                |----------------------------------------------------------
                | Remove error when valid
                |----------------------------------------------------------
                */

                const number =
                    parseFloat(
                        value
                    );


                if (
                    !isNaN(number) &&
                    number > 0
                ) {

                    $(this)
                        .removeClass(
                            'add-item-error-border'
                        );

                    $(this)
                        .parent()
                        .find(
                            '.add-item-error'
                        )
                        .remove();
                }
            }
        );


        /*
        |------------------------------------------------------------------
        | Discount
        |------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name*="[discount_percent]"]',
            function() {

                let value =
                    $(this).val();


                value =
                    value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                const parts =
                    value.split('.');


                if (
                    parts.length > 2
                ) {

                    value =
                        parts[0] +
                        '.' +
                        parts
                        .slice(1)
                        .join('');
                }


                if (
                    value.includes('.')
                ) {

                    const decimalParts =
                        value.split('.');


                    value =
                        decimalParts[0] +
                        '.' +
                        decimalParts[1]
                        .substring(
                            0,
                            2
                        );
                }


                const number =
                    parseFloat(
                        value
                    );


                if (
                    !isNaN(number) &&
                    number > 100
                ) {

                    value =
                        '100';
                }


                $(this).val(
                    value
                );
            }
        );


        /*
        |------------------------------------------------------------------
        | Notes
        |------------------------------------------------------------------
        */

        $('textarea[name="notes"]').on(
            'input',
            function() {

                $(this).val(
                    $(this)
                    .val()
                    .substring(
                        0,
                        1000
                    )
                );
            }
        );


        /*
        |------------------------------------------------------------------
        | Due Date Custom Validation
        |------------------------------------------------------------------
        */

        $.validator.addMethod(
            'notBeforeInvoiceDate',
            function(
                value,
                element
            ) {

                const invoiceDate =
                    $(
                        'input[name="invoice_date"]'
                    ).val();


                if (
                    !value ||
                    !invoiceDate
                ) {

                    return true;
                }


                return value >= invoiceDate;
            },
            'Due date cannot be before invoice date.'
        );


        /*
        |------------------------------------------------------------------
        | Invoice Form Validation
        |------------------------------------------------------------------
        */

        $('#invoiceForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',


            /*
            |--------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------
            */

            errorPlacement: function(
                error,
                element
            ) {

                if (
                    element.is('select')
                ) {

                    element
                        .parent()
                        .append(error);

                } else {

                    error.insertAfter(
                        element
                    );
                }
            },


            /*
            |--------------------------------------------------------------
            | Highlight
            |--------------------------------------------------------------
            */

            highlight: function(element) {

                $(element)
                    .addClass(
                        'border-red-500'
                    );
            },


            /*
            |--------------------------------------------------------------
            | Unhighlight
            |--------------------------------------------------------------
            */

            unhighlight: function(element) {

                $(element)
                    .removeClass(
                        'border-red-500'
                    );
            },


            /*
            |--------------------------------------------------------------
            | Rules
            |--------------------------------------------------------------
            */

            rules: {

                client_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                invoice_date: {
                    required: true,
                    dateISO: true
                },

                due_date: {
                    required: true,
                    dateISO: true,
                    notBeforeInvoiceDate: true
                },

                currency: {
                    required: true
                },

                payment_terms: {
                    maxlength: 50
                },

                notes: {
                    maxlength: 1000
                }
            },


            /*
            |--------------------------------------------------------------
            | Messages
            |--------------------------------------------------------------
            */

            messages: {

                client_id: {
                    required: 'Please select a client.',

                    digits: 'Invalid client.',

                    min: 'Please select a valid client.'
                },

                invoice_date: {
                    required: 'Please select the invoice date.',

                    dateISO: 'Please enter a valid invoice date.'
                },

                due_date: {
                    required: 'Please select the due date.',

                    dateISO: 'Please enter a valid due date.',

                    notBeforeInvoiceDate: 'Due date cannot be before invoice date.'
                },

                currency: {
                    required: 'Please select a currency.'
                },

                payment_terms: {
                    maxlength: 'Payment terms cannot exceed 50 characters.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                }
            },


            /*
            |--------------------------------------------------------------
            | Submit Handler
            |--------------------------------------------------------------
            */

            submitHandler: function(form) {

                let validItems = 0;

                let hasInvalidItem = false;


                /*
                |------------------------------------------------------
                | Check all rows
                |------------------------------------------------------
                */

                $('#itemsBody .item-row')
                    .each(function() {

                        const description =
                            $(this)
                            .find(
                                'input[name*="[description]"]'
                            )
                            .val()
                            .trim();


                        const quantity =
                            parseFloat(
                                $(this)
                                .find(
                                    'input[name*="[quantity]"]'
                                )
                                .val()
                            ) || 0;


                        const price =
                            parseFloat(
                                $(this)
                                .find(
                                    'input[name*="[unit_price]"]'
                                )
                                .val()
                            ) || 0;


                        if (
                            description !== '' &&
                            quantity > 0 &&
                            price > 0
                        ) {

                            validItems++;

                        } else {

                            hasInvalidItem = true;
                        }

                    });


                /*
                |------------------------------------------------------
                | No valid item
                |------------------------------------------------------
                */

                if (
                    validItems === 0
                ) {

                    showInvoiceItemError();

                    return false;
                }


                /*
                |------------------------------------------------------
                | IMPORTANT
                |
                | If any row exists but is incomplete,
                | show error on LAST row.
                |------------------------------------------------------
                */

                if (
                    hasInvalidItem
                ) {

                    const rows =
                        $('#itemsBody .item-row');

                    const lastRow =
                        rows.last();


                    /*
                    |--------------------------------------------------
                    | Validate last row
                    |--------------------------------------------------
                    */

                    const description =
                        lastRow.find(
                            'input[name*="[description]"]'
                        );

                    const quantity =
                        lastRow.find(
                            'input[name*="[quantity]"]'
                        );

                    const price =
                        lastRow.find(
                            'input[name*="[unit_price]"]'
                        );


                    let invalid =
                        false;


                    if (
                        !description.val().trim()
                    ) {

                        showLastRowError(
                            description[0],
                            'Please enter item description.'
                        );

                        invalid = true;
                    }


                    const qtyValue =
                        parseFloat(
                            quantity.val()
                        ) || 0;


                    if (
                        qtyValue <= 0
                    ) {

                        showLastRowError(
                            quantity[0],
                            'Quantity must be greater than zero.'
                        );

                        invalid = true;
                    }


                    const priceValue =
                        parseFloat(
                            price.val()
                        ) || 0;


                    if (
                        priceValue <= 0
                    ) {

                        showLastRowError(
                            price[0],
                            'Unit price must be greater than zero.'
                        );

                        invalid = true;
                    }


                    if (
                        invalid
                    ) {

                        const firstError =
                            lastRow.find(
                                '.add-item-error-border'
                            ).first();


                        if (
                            firstError.length
                        ) {

                            firstError.focus();
                        }


                        return false;
                    }
                }


                /*
                |------------------------------------------------------
                | Total Check
                |------------------------------------------------------
                */

                const total =
                    parseFloat(
                        $('#total_hidden').val()
                    ) || 0;


                if (
                    total <= 0
                ) {

                    showInvoiceTotalError();

                    return false;
                }


                /*
                |------------------------------------------------------
                | Submit
                |------------------------------------------------------
                */

                form.submit();
            }
        });


        /*
        |------------------------------------------------------------------
        | Apply validation to existing rows
        |------------------------------------------------------------------
        */

        $('#itemsBody .item-row')
            .each(
                function() {

                    applyItemValidation(
                        this
                    );
                }
            );
    });


    /*
    |--------------------------------------------------------------------------
    | Apply Item Validation
    |--------------------------------------------------------------------------
    */

    function applyItemValidation(row) {

        const description =
            $(row).find(
                'input[name*="[description]"]'
            );


        const quantity =
            $(row).find(
                'input[name*="[quantity]"]'
            );


        const unitPrice =
            $(row).find(
                'input[name*="[unit_price]"]'
            );


        const discount =
            $(row).find(
                'input[name*="[discount_percent]"]'
            );


        /*
        |------------------------------------------------------------------
        | Description
        |------------------------------------------------------------------
        */

        if (
            description.length
        ) {

            description.rules(
                'add', {
                    required: true,

                    minlength: 2,

                    maxlength: 500,

                    messages: {

                        required: 'Please enter item description.',

                        minlength: 'Description must be at least 2 characters.',

                        maxlength: 'Description cannot exceed 500 characters.'
                    }
                }
            );
        }


        /*
        |------------------------------------------------------------------
        | Quantity
        |------------------------------------------------------------------
        */

        if (
            quantity.length
        ) {

            quantity.rules(
                'add', {
                    required: true,

                    number: true,

                    min: 0.01,

                    messages: {

                        required: 'Please enter quantity.',

                        number: 'Please enter a valid quantity.',

                        min: 'Quantity must be greater than zero.'
                    }
                }
            );
        }


        /*
        |------------------------------------------------------------------
        | Unit Price
        |------------------------------------------------------------------
        */

        if (
            unitPrice.length
        ) {

            unitPrice.rules(
                'add', {
                    required: true,

                    number: true,

                    min: 0.01,

                    messages: {

                        required: 'Please enter unit price.',

                        number: 'Please enter a valid unit price.',

                        min: 'Unit price must be greater than zero.'
                    }
                }
            );
        }


        /*
        |------------------------------------------------------------------
        | Discount
        |------------------------------------------------------------------
        */

        if (
            discount.length
        ) {

            discount.rules(
                'add', {
                    number: true,

                    min: 0,

                    max: 100,

                    messages: {

                        number: 'Please enter a valid discount.',

                        min: 'Discount cannot be negative.',

                        max: 'Discount cannot exceed 100%.'
                    }
                }
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Invoice Item Error
    |--------------------------------------------------------------------------
    */

    function showInvoiceItemError() {

        $('.invoice-items-error')
            .remove();


        const firstRow =
            $('#itemsBody .item-row')
            .first();


        if (!firstRow.length) {
            return;
        }


        firstRow
            .find('td:first')
            .append(
                '<span class="invoice-items-error">' +
                'At least one valid invoice item is required.' +
                '</span>'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Invoice Total Error
    |--------------------------------------------------------------------------
    */

    function showInvoiceTotalError() {

        $('#invoice-total-error')
            .remove();


        $('#displayTotal')
            .after(
                '<span id="invoice-total-error" ' +
                'class="text-red-500 text-sm ml-2">' +
                'Invoice total must be greater than zero.' +
                '</span>'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Custom Errors When User Types
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        '#itemsBody input',
        function() {

            /*
            |--------------------------------------------------------------
            | Remove general item error
            |--------------------------------------------------------------
            */

            $('.invoice-items-error')
                .remove();


            /*
            |--------------------------------------------------------------
            | Remove total error
            |--------------------------------------------------------------
            */

            $('#invoice-total-error')
                .remove();


            /*
            |--------------------------------------------------------------
            | Remove Add Item error for current input
            |--------------------------------------------------------------
            */

            $(this)
                .removeClass(
                    'add-item-error-border'
                );


            $(this)
                .parent()
                .find(
                    '.add-item-error'
                )
                .remove();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Tax Row Initialization
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            document
                .querySelectorAll(
                    '#itemsBody .item-row'
                )
                .forEach(
                    function(row) {

                        if (
                            typeof window.wireTaxRow ===
                            'function'
                        ) {

                            window.wireTaxRow(
                                row
                            );
                        }
                    }
                );


            /*
            |--------------------------------------------------------------
            | Initial totals
            |--------------------------------------------------------------
            */

            if (
                typeof recalcDocTotals ===
                'function'
            ) {

                recalcDocTotals(
                    'itemsTable'
                );
            }
        }
    );
</script>

<?php require_once '../../../includes/footer.php'; ?>
<?php require_once '../../../includes/footer.php'; ?>