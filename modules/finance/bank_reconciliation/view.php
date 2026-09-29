<?php
$pageTitle = 'View Reconciliation';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.reconciliation.manage')) { die('Access Denied'); }

$db = getDB();
$errors = [];
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT br.*, ba.account_name, ba.bank_name, ba.account_number, u.name AS created_by_name FROM bank_reconciliation br JOIN bank_accounts ba ON br.bank_account_id = ba.id JOIN users u ON br.created_by = u.id WHERE br.id = ?");
$stmt->execute([$id]);
$recon = $stmt->fetch();
if (!$recon) { flash('error', 'Reconciliation not found.'); to('modules/finance/bank_reconciliation/index.php'); }

// Get reconciliation items
$items = $db->prepare("SELECT bri.*, bt.transaction_date, bt.transaction_type, bt.reference_number, bt.description FROM bank_reconciliation_items bri LEFT JOIN bank_transactions bt ON bri.bank_transaction_id = bt.id WHERE bri.reconciliation_id = ? ORDER BY COALESCE(bt.transaction_date, '1900-01-01'), bt.id");
$items->execute([$id]);
$items = $items->fetchAll();

$matchedTotal = 0;
$unmatchedTotal = 0;
foreach ($items as $item) {
    if ($item['is_matched']) { $matchedTotal += (float)$item['amount']; }
    else { $unmatchedTotal += (float)$item['amount']; }
}

// Handle complete
if (isset($_GET['complete'])) {
    if (!verifyCsrf($_GET['_token'] ?? '')) { $errors[] = 'Invalid token.'; }
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE bank_reconciliation SET status = 'completed' WHERE id = ? AND status = 'in_progress'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) throw new Exception('Reconciliation already completed or cancelled.');

        $db->commit();
        logAudit($_SESSION['user_id'], 'Reconciliation completed: ' . sanitize($recon['account_name']) . ' (Diff: ' . formatNumber($recon['difference']) . ')', 'finance');
        flash('success', 'Reconciliation completed successfully.');
        to('modules/finance/bank_reconciliation/view.php?id=' . $id);
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = $e->getMessage();
    }
}

// Handle toggle match
if (isset($_GET['toggle']) && $recon['status'] === 'in_progress') {
    if (!verifyCsrf($_GET['_token'] ?? '')) { $errors[] = 'Invalid token.'; }
    $itemId = (int)$_GET['toggle'];
    $stmt = $db->prepare("UPDATE bank_reconciliation_items SET is_matched = NOT is_matched WHERE id = ? AND reconciliation_id = ?");
    $stmt->execute([$itemId, $id]);
    to('modules/finance/bank_reconciliation/view.php?id=' . $id);
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="index.php" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Reconciliations</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-2">Reconciliation Details</h1>
    </div>
    <div class="flex gap-2">
        <?php if ($recon['status'] === 'in_progress'): ?>
        <a href="view.php?id=<?= $id ?>&complete=1&_token=<?= csrfToken() ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" onclick="return confirm('Complete this reconciliation?')"><i class="fas fa-check mr-2"></i>Complete</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($errors): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- Summary -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <p class="text-sm text-gray-500">Bank Account</p>
            <p class="text-lg font-semibold"><?= sanitize($recon['account_name']) ?></p>
            <p class="text-xs text-gray-500"><?= sanitize($recon['bank_name']) ?> (<?= sanitize($recon['account_number']) ?>)</p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Statement Date</p>
            <p class="text-lg font-semibold"><?= date('M d, Y', strtotime($recon['statement_date'])) ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Status</p>
            <p class="mt-1"><?= formatReconStatus($recon['status']) ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Created By</p>
            <p class="text-lg font-semibold"><?= sanitize($recon['created_by_name']) ?></p>
        </div>
    </div>
    <div class="border-t mt-4 pt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-blue-50 p-3 rounded">
            <p class="text-sm text-blue-600">Statement Balance</p>
            <p class="text-xl font-bold text-blue-800">AED <?= formatNumber($recon['statement_balance']) ?></p>
        </div>
        <div class="bg-green-50 p-3 rounded">
            <p class="text-sm text-green-600">Book Balance (Ledger)</p>
            <p class="text-xl font-bold text-green-800">AED <?= formatNumber($recon['book_balance']) ?></p>
        </div>
        <div class="bg-<?= (float)$recon['difference'] == 0 ? 'green' : 'red' ?>-50 p-3 rounded">
            <p class="text-sm text-<?= (float)$recon['difference'] == 0 ? 'green' : 'red' ?>-600">Difference</p>
            <p class="text-xl font-bold text-<?= (float)$recon['difference'] == 0 ? 'green' : 'red' ?>-800 <?= (float)$recon['difference'] != 0 ? 'animate-pulse' : '' ?>">AED <?= formatNumber($recon['difference']) ?></p>
            <?php if ((float)$recon['difference'] != 0): ?>
            <p class="text-xs text-red-600 mt-1">Items need matching</p>
            <?php else: ?>
            <p class="text-xs text-green-600 mt-1">Balanced</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($recon['notes']): ?>
    <div class="mt-4 p-3 bg-gray-50 rounded">
        <p class="text-sm text-gray-700"><?= sanitize($recon['notes']) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Transactions -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Unreconciled Transactions</h3>
        <div class="text-sm text-gray-500">
            Matched: AED <?= formatNumber($matchedTotal) ?> &middot;
            Unmatched: AED <span class="font-medium text-orange-600"><?= formatNumber($unmatchedTotal) ?></span>
        </div>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50"><tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">Match</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref No</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($items as $item): ?>
            <tr class="hover:bg-gray-50 <?= $item['is_matched'] ? 'bg-green-50' : '' ?>">
                <td class="px-4 py-3 text-center">
                    <?php if ($recon['status'] === 'in_progress'): ?>
                    <a href="view.php?id=<?= $id ?>&toggle=<?= $item['id'] ?>&_token=<?= csrfToken() ?>" class="inline-block w-5 h-5 border-2 rounded <?= $item['is_matched'] ? 'bg-green-500 border-green-500' : 'border-gray-300 hover:border-green-400' ?>">
                        <?php if ($item['is_matched']): ?><i class="fas fa-check text-white text-xs leading-5"></i><?php endif; ?>
                    </a>
                    <?php else: ?>
                    <span class="inline-block w-5 h-5 border-2 rounded <?= $item['is_matched'] ? 'bg-green-500 border-green-500' : 'border-gray-300' ?>">
                        <?php if ($item['is_matched']): ?><i class="fas fa-check text-white text-xs leading-5"></i><?php endif; ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= $item['transaction_date'] ? date('M d, Y', strtotime($item['transaction_date'])) : '-' ?></td>
                <td class="px-4 py-3"><?= $item['transaction_type'] ? formatTxType($item['transaction_type']) : '-' ?></td>
                <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($item['reference_number'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono"><?= formatNumber($item['amount']) ?></td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= sanitize($item['description'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-exchange-alt text-4xl mb-3 text-gray-300"></i><p>No transactions to reconcile</p></td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="bg-gray-50">
            <tr>
                <td colspan="4" class="px-4 py-3 text-sm font-medium text-gray-700 text-right">Totals:</td>
                <td class="px-4 py-3 text-sm text-right font-mono font-bold">Matched: AED <?= formatNumber($matchedTotal) ?></td>
                <td class="px-4 py-3 text-sm text-left font-mono text-orange-600">Unmatched: AED <?= formatNumber($unmatchedTotal) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php require_once '../../../includes/footer.php'; ?>

