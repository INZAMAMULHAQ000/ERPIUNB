<?php
$pageTitle = 'Allocate Vendor Payment';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('vendor_payments.create')) { die('Access Denied'); }

$db = getDB();
$payment_id = (int)($_GET['id'] ?? 0);
if ($payment_id <= 0) { flash('error', 'Invalid payment ID.'); redirect('index.php'); }

// Load payment
$stmt = $db->prepare("SELECT * FROM vendor_payments WHERE id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();
if (!$payment) { flash('error', 'Vendor payment not found.'); redirect('index.php'); }

$unallocated = (float)$payment['unallocated_amount'];
if ($unallocated <= 0) { flash('error', 'This payment has no unallocated amount.'); redirect('view.php?id=' . $payment_id); }

$errors = restoreFormErrors();
if (empty($errors)) { clearOldInput(); }

// Load existing allocations to exclude fully allocated bills
$existingAllocations = $db->prepare("SELECT bill_id FROM payment_allocations WHERE payment_id = ?");
$existingAllocations->execute([$payment_id]);
$allocatedBillIds = $existingAllocations->fetchAll(PDO::FETCH_COLUMN);

$vendorId = (int)$payment['vendor_id'];

// Load open bills for this vendor, excluding already-allocated ones
$bills = [];
if (!empty($allocatedBillIds)) {
    $placeholders = implode(',', array_fill(0, count($allocatedBillIds), '?'));
    $stmt = $db->prepare("
        SELECT id, bill_number, bill_date, due_date, total_amount, balance_amount
        FROM vendor_bills WHERE id NOT IN ($placeholders) AND vendor_id = ? AND balance_amount > 0 AND status IN ('approved', 'partially_paid', 'overdue')
        ORDER BY due_date ASC
    ");
    $params = array_merge($allocatedBillIds, [$vendorId]);
    $stmt->execute($params);
} else {
    $stmt = $db->prepare("
        SELECT id, bill_number, bill_date, due_date, total_amount, balance_amount
        FROM vendor_bills WHERE vendor_id = ? AND balance_amount > 0 AND status IN ('approved', 'partially_paid', 'overdue')
        ORDER BY due_date ASC
    ");
    $stmt->execute([$vendorId]);
}
$bills = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid or expired form token.'; }

    $allocations = $_POST['allocations'] ?? [];
    $totalAllocated = 0;

    if (!empty($allocations) && is_array($allocations)) {
        foreach ($allocations as $bill_id => $alloc_amt) {
            $alloc_amt = (float)str_replace(',', '', $alloc_amt ?? 0);
            if ($alloc_amt > 0) {
                $stmt = $db->prepare("SELECT balance_amount FROM vendor_bills WHERE id = ? AND vendor_id = ? AND balance_amount > 0");
                $stmt->execute([(int)$bill_id, $vendorId]);
                $bill = $stmt->fetch();
                if (!$bill) { $errors[] = "Bill ID $bill_id is not valid."; continue; }
                if ($alloc_amt > (float)$bill['balance_amount']) {
                    $errors[] = "Allocation exceeds outstanding balance.";
                }
                $totalAllocated += $alloc_amt;
            }
        }
    }

    if ($totalAllocated <= 0) { $errors[] = 'Please enter at least one allocation.'; }
    if ($totalAllocated > $unallocated) { $errors[] = 'Total allocated exceeds unallocated amount.'; }

    if (!empty($errors)) { saveFormState($errors); to('modules/finance/vendor_payments/allocate.php?id=' . $payment_id); }

    try {
        $db->beginTransaction();

        $allocStmt = $db->prepare("INSERT INTO payment_allocations (payment_id, bill_id, amount) VALUES (?, ?, ?)");
        $updateBill = $db->prepare("UPDATE vendor_bills SET paid_amount = paid_amount + ?, balance_amount = balance_amount - ?, updated_at = NOW() WHERE id = ?");

        foreach ($allocations as $bill_id => $alloc_amt) {
            $alloc_amt = (float)str_replace(',', '', $alloc_amt ?? 0);
            if ($alloc_amt <= 0) { continue; }
            $bill_id = (int)$bill_id;

            $allocStmt->execute([$payment_id, $bill_id, $alloc_amt]);
            $updateBill->execute([$alloc_amt, $alloc_amt, $bill_id]);

            // Update bill status
            $stmt = $db->prepare("SELECT total_amount, paid_amount, balance_amount FROM vendor_bills WHERE id = ?");
            $stmt->execute([$bill_id]);
            $bill = $stmt->fetch();
            if ($bill && (float)$bill['balance_amount'] <= 0) {
                $db->prepare("UPDATE vendor_bills SET status = 'paid', updated_at = NOW() WHERE id = ?")->execute([$bill_id]);
                $db->prepare("INSERT INTO bill_status_history (bill_id, from_status, to_status, changed_by, change_note) VALUES (?, 'approved', 'paid', ?, 'Payment allocation completed')")->execute([$bill_id, $_SESSION['user_id']]);
            } elseif ($bill && (float)$bill['paid_amount'] > 0) {
                $db->prepare("UPDATE vendor_bills SET status = 'partially_paid', updated_at = NOW() WHERE id = ? AND status = 'approved'")->execute([$bill_id]);
                $db->prepare("INSERT INTO bill_status_history (bill_id, from_status, to_status, changed_by, change_note) VALUES (?, 'approved', 'partially_paid', ?, 'Partial payment received')")->execute([$bill_id, $_SESSION['user_id']]);
            }
        }

        // Update payment unallocated amount
        $newUnallocated = $unallocated - $totalAllocated;
        $db->prepare("UPDATE vendor_payments SET unallocated_amount = ?, updated_at = NOW() WHERE id = ?")->execute([$newUnallocated, $payment_id]);

        $db->commit();
        logAudit($_SESSION['user_id'], 'Allocated payment: ' . $payment['payment_number'] . ' (' . $totalAllocated . ')', 'vendor_payments');
        flash('success', 'Payment allocation completed successfully.', 'success');
        redirect('view.php?id=' . $payment_id);
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Error: ' . $e->getMessage());
        to('modules/finance/vendor_payments/allocate.php?id=' . $payment_id);
    }
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Allocate Payment</h1>
        <p class="text-gray-600"><?= sanitize($payment['payment_number']) ?> — Unallocated: <?= formatMoney($unallocated) ?></p>
    </div>
    <a href="view.php?id=<?= $payment_id ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?php foreach ($errors as $e): ?><p><?= sanitize($e) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<?php if (empty($bills)): ?>
<div class="bg-white rounded-lg shadow p-6 text-center">
    <p class="text-gray-500">No outstanding bills available for allocation for this vendor.</p>
    <a href="view.php?id=<?= $payment_id ?>" class="text-blue-600 hover:underline mt-2 inline-block">Back to payment</a>
</div>
<?php else: ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Outstanding Bills</h3>
            <span class="text-sm text-gray-500">Unallocated amount: <strong><?= formatMoney($unallocated) ?></strong></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Due</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Allocation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($bills as $b): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm font-mono"><?= sanitize($b['bill_number']) ?></td>
                        <td class="px-3 py-2 text-sm"><?= date('M d, Y', strtotime($b['bill_date'])) ?></td>
                        <td class="px-3 py-2 text-sm"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td class="px-3 py-2 text-sm text-right font-mono"><?= formatMoney((float)$b['total_amount']) ?></td>
                        <td class="px-3 py-2 text-sm text-right font-mono text-red-600"><?= formatMoney((float)$b['balance_amount']) ?></td>
                        <td class="px-3 py-2 text-right">
                            <input type="number" name="allocations[<?= $b['id'] ?>]" value="0" step="0.01" min="0" max="<?= (float)$b['balance_amount'] ?>" class="w-28 px-2 py-1.5 border rounded text-sm text-right alloc-input" oninput="calcAllocTotal(<?= $unallocated ?>)">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="5" class="px-3 py-2 text-right text-sm font-medium text-gray-700">Total Allocated:</td>
                        <td class="px-3 py-2"><input type="text" id="totalAllocated" class="w-28 px-2 py-1.5 border-0 text-right font-mono font-bold bg-transparent" value="0.00" readonly></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-4 mb-8">
        <a href="view.php?id=<?= $payment_id ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">Cancel</a>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-save mr-2"></i>Save Allocations</button>
    </div>
</form>

<script>
function calcAllocTotal(maxUnallocated) {
    let total = 0;
    document.querySelectorAll('.alloc-input').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('totalAllocated').value = total.toFixed(2);

    document.querySelectorAll('.alloc-input').forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        const max = parseFloat(inp.getAttribute('max')) || 0;
        inp.style.color = (val > max) ? 'red' : '';
    });
}
</script>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
