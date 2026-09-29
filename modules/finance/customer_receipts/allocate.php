<?php
$pageTitle = 'Allocate Receipt';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('customer_receipts.create')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$receipt_id = (int)($_GET['id'] ?? 0);
if ($receipt_id <= 0) {
    flash('error', 'Invalid receipt ID.');
    redirect('index.php');
}

// Load receipt with client info
$stmt = $db->prepare("
    SELECT cr.*, c.company_name, c.client_code
    FROM customer_receipts cr
    JOIN clients c ON cr.client_id = c.id
    WHERE cr.id = ?
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    flash('error', 'Receipt not found.');
    redirect('index.php');
}

$unallocated = (float)$receipt['unallocated_amount'];
if ($unallocated <= 0) {
    flash('error', 'This receipt is fully allocated. No unallocated balance remaining.');
    redirect('view.php?id=' . $receipt_id);
}

// Load open invoices for this client (excluding invoices already linked to this receipt)
$alreadyAllocatedStmt = $db->prepare("SELECT invoice_id FROM receipt_allocations WHERE receipt_id = ?");
$alreadyAllocatedStmt->execute([$receipt_id]);
$alreadyLinked = $alreadyAllocatedStmt->fetchAll(PDO::FETCH_COLUMN);

$invStmt = $db->prepare("
    SELECT id, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_amount, status
    FROM customer_invoices
    WHERE client_id = ? AND status IN ('approved','sent','partially_paid','overdue') AND balance_amount > 0
    ORDER BY due_date ASC
");
$invStmt->execute([$receipt['client_id']]);
$openInvoices = $invStmt->fetchAll();

// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token. Please try again.';
    }

    $allocations = $_POST['allocations'] ?? [];

    // Validate allocations
    $total_allocated = 0;
    if (!empty($allocations) && is_array($allocations)) {
        foreach ($allocations as $inv_id => $alloc) {
            $alloc_amount = (float)str_replace(',', '', $alloc['amount'] ?? 0);
            if ($alloc_amount > 0) {
                $total_allocated += $alloc_amount;
            }
        }
    }

    if ($total_allocated <= 0) {
        $errors[] = 'Please allocate at least some amount to one or more invoices.';
    }
    if ($total_allocated > $unallocated) {
        $errors[] = 'Total allocated amount (' . number_format($total_allocated, 2) . ') exceeds unallocated balance (' . number_format($unallocated, 2) . ').';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/customer_receipts/allocate.php?id=' . $receipt_id);
    }

    try {
        $db->beginTransaction();

        // Process each allocation
        foreach ($allocations as $inv_id => $alloc) {
            $alloc_amount = (float)str_replace(',', '', $alloc['amount'] ?? 0);
            if ($alloc_amount <= 0) continue;

            $invId = (int)$inv_id;

            // Insert allocation record
            $stmt = $db->prepare("INSERT INTO receipt_allocations (receipt_id, invoice_id, amount) VALUES (?, ?, ?)");
            $stmt->execute([$receipt_id, $invId, $alloc_amount]);

            // Update invoice paid_amount, balance_amount, status with row lock
            $invStmt = $db->prepare("SELECT total_amount, paid_amount, balance_amount, status FROM customer_invoices WHERE id = ? FOR UPDATE");
            $invStmt->execute([$invId]);
            $inv = $invStmt->fetch();
            if ($inv) {
                $new_paid = (float)$inv['paid_amount'] + $alloc_amount;
                $new_balance = (float)$inv['balance_amount'] - $alloc_amount;
                if ($new_balance <= 0) {
                    $new_status = 'paid';
                    $new_balance = 0;
                } else {
                    $new_status = 'partially_paid';
                }

                $updateStmt = $db->prepare("UPDATE customer_invoices SET paid_amount = ?, balance_amount = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$new_paid, $new_balance, $new_status, $invId]);

                // Record status history
                if ($new_status !== $inv['status']) {
                    $histStmt = $db->prepare("INSERT INTO invoice_status_history (invoice_id, from_status, to_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
                    $histStmt->execute([$invId, $inv['status'], $new_status, $_SESSION['user_id'], 'Allocated from receipt ' . $receipt['receipt_number']]);
                }
            }
        }

        // Reduce receipt unallocated_amount
        $new_unallocated = $unallocated - $total_allocated;
        $stmt = $db->prepare("UPDATE customer_receipts SET unallocated_amount = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_unallocated, $receipt_id]);

        $db->commit();

        logAudit($_SESSION['user_id'], 'Allocated ' . number_format($total_allocated, 2) . ' from receipt ' . $receipt['receipt_number'] . ' to invoices', 'customer_receipts');
        flash('success', 'Allocation successful. ' . number_format($total_allocated, 2) . ' allocated from receipt ' . $receipt['receipt_number'] . '.', 'success');
        redirect('view.php?id=' . $receipt_id);
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Allocation failed: ' . $e->getMessage());
        to('modules/finance/customer_receipts/allocate.php?id=' . $receipt_id);
    }
}

// Load existing allocations for display
$allocStmt = $db->prepare("
    SELECT ra.amount, ci.invoice_number, ci.total_amount, ci.paid_amount, ci.balance_amount, ci.status
    FROM receipt_allocations ra
    JOIN customer_invoices ci ON ra.invoice_id = ci.id
    WHERE ra.receipt_id = ?
    ORDER BY ra.created_at ASC
");
$allocStmt->execute([$receipt_id]);
$existingAllocations = $allocStmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Allocate Receipt</h1>
        <p class="text-gray-600">Allocate unallocated receipt amount to open invoices</p>
    </div>
    <div class="flex gap-2">
        <a href="view.php?id=<?= $receipt_id ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Receipt
        </a>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-list mr-2"></i>All Receipts
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <ul class="list-disc pl-5">
        <?php foreach ($errors as $err): ?>
        <li><?= sanitize($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Receipt Summary -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <p class="text-xs text-gray-500 uppercase">Receipt</p>
            <p class="text-lg font-mono font-bold text-gray-800"><?= sanitize($receipt['receipt_number']) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase">Client</p>
            <p class="text-lg font-semibold text-gray-800"><?= sanitize($receipt['company_name']) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase">Receipt Date</p>
            <p class="text-lg text-gray-800"><?= date('M d, Y', strtotime($receipt['receipt_date'])) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase">Unallocated Balance</p>
            <p class="text-2xl font-bold text-yellow-600"><?= number_format($unallocated, 2) ?></p>
        </div>
    </div>
</div>

<!-- Existing Allocations (if any) -->
<?php if (!empty($existingAllocations)): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-800 mb-3">Previously Allocated</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($existingAllocations as $ea): ?>
                <tr class="bg-gray-50">
                    <td class="px-4 py-2 font-mono text-gray-800"><?= sanitize($ea['invoice_number']) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$ea['total_amount'], 2) ?></td>
                    <td class="px-4 py-2 text-right font-mono text-green-600"><?= number_format((float)$ea['paid_amount'], 2) ?></td>
                    <td class="px-4 py-2 text-right font-mono <?= (float)$ea['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= number_format((float)$ea['balance_amount'], 2) ?></td>
                    <td class="px-4 py-2 text-center">
                        <span class="px-2 py-0.5 text-xs rounded font-medium <?= $ea['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= ucwords(str_replace('_', ' ', $ea['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right font-mono font-semibold text-blue-600"><?= number_format((float)$ea['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Allocation Form -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Allocate to Invoices</h3>
    <p class="text-sm text-gray-500 mb-4">Amount available to allocate: <strong class="text-yellow-600"><?= number_format($unallocated, 2) ?></strong></p>

    <?php if (!empty($openInvoices)): ?>
    <form method="POST" id="allocateForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="overflow-x-auto">
            <table class="w-full" id="allocationsTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($openInvoices as $inv):
                        // Skip invoices already fully paid
                        if ((float)$inv['balance_amount'] <= 0) continue;
                    ?>
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= sanitize($inv['invoice_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($inv['invoice_date'])) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= number_format((float)$inv['total_amount'], 2) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono <?= (float)$inv['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= number_format((float)$inv['balance_amount'], 2) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 text-xs rounded font-medium <?= $inv['status'] === 'sent' ? 'bg-indigo-100 text-indigo-800' : ($inv['status'] === 'partially_paid' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                <?= ucwords(str_replace('_', ' ', $inv['status'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <input type="number" name="allocations[<?= $inv['id'] ?>][amount]" step="0.01" min="0"
                                   max="<?= min((float)$inv['balance_amount'], $unallocated) ?>"
                                   value="0" class="w-32 px-2 py-2 border rounded text-right allocation-input"
                                   data-balance="<?= (float)$inv['balance_amount'] ?>"
                                   oninput="validateAllocations()">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right text-sm text-gray-600">
                            Available: <span class="font-bold text-yellow-600"><?= number_format($unallocated, 2) ?></span> |
                            Total to Allocate:
                        </td>
                        <td class="px-4 py-3 text-right font-mono" id="totalAllocated">0.00</td>
                    </tr>
                    <tr id="allocationWarning" class="hidden">
                        <td colspan="6" class="px-4 py-2 text-sm text-red-600 text-right">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Allocated amount exceeds available balance
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="pt-4 border-t flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Allocating will reduce the receipt's unallocated balance and update invoice balances.
                </span>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition" id="submitBtn">
                    <i class="fas fa-check mr-2"></i>Confirm Allocation
                </button>
            </div>
        </div>
    </form>
    <?php else: ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
        <i class="fas fa-info-circle mr-2"></i>
        No open invoices available for allocation. This client has no invoices in 'sent' or 'partially_paid' status with outstanding balances.
    </div>
    <div class="mt-4">
        <a href="view.php?id=<?= $receipt_id ?>" class="text-blue-600 hover:text-blue-900">
            <i class="fas fa-arrow-left mr-1"></i>Back to Receipt
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function validateAllocations() {
    const maxAllocatable = <?= $unallocated ?>;
    const inputs = document.querySelectorAll('.allocation-input');
    let totalAllocated = 0;

    inputs.forEach(function(input) {
        const val = parseFloat(input.value) || 0;
        const balance = parseFloat(input.getAttribute('data-balance')) || 0;
        // Clamp to balance
        if (val > balance) {
            input.value = balance.toFixed(2);
            totalAllocated += balance;
        } else {
            totalAllocated += val;
        }
    });

    document.getElementById('totalAllocated').textContent = totalAllocated.toFixed(2);

    const warning = document.getElementById('allocationWarning');
    const submitBtn = document.getElementById('submitBtn');

    if (totalAllocated > maxAllocatable) {
        warning.classList.remove('hidden');
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        warning.classList.add('hidden');
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    validateAllocations();
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
