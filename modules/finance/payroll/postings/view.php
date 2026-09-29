<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/**
 * View Payroll Finance Posting Details
 *
 * @package XXX
 * @submodule Finance.Payroll.Postings
 */

require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get posting ID from URL
$postingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($postingId <= 0) {
    redirect_to('index.php');
}

// Include finance helpers for payroll posting functions
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';

// Fetch posting data with details
$posting = getPayrollFinancePostingById($postingId, true);
if (!$posting) {
    redirect_to('index.php');
}

// Get journal entry details if posted
$journalLines = [];
if (!empty($posting['journal_entry_id'])) {
    require_once __DIR__ . '/../../../../helpers/finance_helpers.php';
    $journalLines = getJournalEntryDetailsById($posting['journal_entry_id']);
}

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Posting Details</h1>
        <div class="flex space-x-3">
            <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Back to List</a>
            <?php if (hasPermission('payrollfinance.post') && $posting['status'] === 'Draft'): ?>
                <a href="edit.php?id=<?= $postingId ?>" class="btn-secondary px-4 py-2 border border-gray-500 text-gray-600 hover:bg-gray-50 transition">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6">
            <div class="mb-4 px-4 py-2 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-lg font-semibold"><?= htmlspecialchars($posting['payroll_run_name'] ?? ('Run #' . $posting['payroll_run_id'])) ?></h2>
                        <p class="text-sm text-gray-500">Payroll Run • Posted on <?= htmlspecialchars($posting['created_at'] ?? '-') ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs px-2 py-1 rounded-full font-medium <?= $posting['status'] === 'Posted' ? 'bg-green-100 text-green-800' : ($posting['status'] === 'Draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= htmlspecialchars($posting['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Posting Summary -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Posting Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="text-lg font-medium mb-2">Total Earnings</h3>
                        <p class="text-2xl font-bold text-green-600">AED <?= number_format($posting['total_gross'] ?? 0, 2) ?></p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium mb-2">Total Deductions</h3>
                        <p class="text-2xl font-bold text-red-600">AED <?= number_format($posting['total_deductions'] ?? 0, 2) ?></p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium mb-2">Net Payable</h3>
                        <p class="text-2xl font-bold text-blue-600">AED <?= number_format($posting['total_net'] ?? 0, 2) ?></p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium mb-2">Posting Date</h3>
                        <p class="text-2xl font-bold"><?= htmlspecialchars($posting['posting_date'] ?? '-') ?></p>
                    </div>
                </div>
            </div>

            <!-- Journal Entry Details (if posted) -->
            <?php if (!empty($posting['journal_entry_id'])): ?>
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h2 class="text-xl font-semibold mb-4">Journal Entry Details</h2>
                    <p class="text-sm text-gray-500 mb-2">Journal Entry #: <?= htmlspecialchars($posting['journal_entry_id']) ?></p>

                    <?php if (!empty($journalLines)): ?>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th class="text-left">Account</th>
                                        <th class="text-center">Debit</th>
                                        <th class="text-center">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($journalLines as $line): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($line['account_name']) ?> (<?= htmlspecialchars($line['account_code']) ?>)</td>
                                            <td class="text-right"><?= number_format(max(0, $line['amount']), 2) ?></td>
                                            <td class="text-right"><?= number_format(max(0, -$line['amount']), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="border-t pt-4 font-bold">
                                        <td>Total</td>
                                        <td class="text-right"><?= number_format(array_sum(array_map(function($l) { return max(0, $l['amount']); }, $journalLines)), 2) ?></td>
                                        <td class="text-right"><?= number_format(array_sum(array_map(function($l) { return max(0, -$l['amount']); }, $journalLines)), 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Audit / Linked Records -->
            <?php if (!empty($posting['journal_entry_id'])): ?>
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Audit Chain</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div class="bg-white p-3 rounded border">
                        <p class="font-medium text-gray-600">Payroll Run</p>
                        <p class="font-bold"><?= htmlspecialchars($posting['payroll_run_id'] ?? '-') ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($posting['payroll_run_name'] ?? '') ?></p>
                    </div>
                    <div class="bg-white p-3 rounded border">
                        <p class="font-medium text-gray-600">Finance Posting</p>
                        <p class="font-bold">#<?= htmlspecialchars($posting['id']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($posting['posting_date']) ?> — <?= htmlspecialchars($posting['status']) ?></p>
                    </div>
                    <div class="bg-white p-3 rounded border">
                        <p class="font-medium text-gray-600">Journal Entry</p>
                        <p class="font-bold">#<?= htmlspecialchars($posting['journal_entry_id']) ?></p>
                        <?php
                        $jeStmt = $db->prepare("SELECT voucher_no, status FROM journal_entries WHERE id = ?");
                        $jeStmt->execute([$posting['journal_entry_id']]);
                        $je = $jeStmt->fetch();
                        if ($je): ?>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($je['voucher_no']) ?> — <?= htmlspecialchars($je['status']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Posting Actions -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Actions</h2>
                <div class="flex flex-wrap space-x-3">
                    <?php if ($posting['status'] === 'Posted'): ?>
                        <a href="reversal.php?id=<?= $postingId ?>" class="bg-yellow-500 text-white px-4 py-2 hover:bg-yellow-600 transition">Create Reversal</a>
                    <?php endif; ?>
                    <?php if ($posting['status'] === 'Draft' || $posting['status'] === 'Posted'): ?>
                        <a href="print.php?id=<?= $postingId ?>" class="btn-secondary px-4 py-2 border border-gray-500 text-gray-600 hover:bg-gray-50 transition">Print Report</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>