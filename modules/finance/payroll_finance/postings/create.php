<?php
/**
 * Payroll Posting Create
 * Preview and create payroll finance posting
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Create Payroll Posting';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.post')) {
    redirect_to('no-access.php');
}

// Get payroll runs that can be posted (not yet posted to finance)
$db = getDB();
$stmt = $db->prepare("
    SELECT prr.payroll_run_id, prr.payroll_total, prr.status AS recon_status
    FROM payroll_reconciliation prr
    WHERE prr.payroll_run_id NOT IN (
        SELECT payroll_run_id FROM payroll_finance_postings WHERE status = 'Posted'
    )
    ORDER BY prr.checked_at DESC, prr.id DESC
");
$stmt->execute();
$runs = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">

    <div class="bg-white rounded-lg shadow p-8 mb-8 max-w-4xl">
        <h1 class="text-2xl font-bold mb-6">Create Payroll Posting</h1>

        <?php if (empty($runs)): ?>
            <div class="bg-blue-50 border-l-4 border-blue-600 p-4 text-blue-800">
                <p>No approved payroll runs found eligible for posting.</p>
                <p>All payroll runs have already been posted, or there are no reconciliation records yet.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="process.php">
                <input type="hidden" name="action" value="create_posting">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block mb-2 font-medium">Select Payroll Run</label>
                        <select name="payroll_run_id" class="w-full" required>
                            <option value="">-- Select Payroll Run --</option>
                            <?php foreach ($runs as $run): ?>
                                <option value="<?= $run['payroll_run_id'] ?>">
                                    Payroll Run #<?= (int)$run['payroll_run_id'] ?> -
                                    AED <?= number_format((float)$run['payroll_total'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-2 font-medium">Posting Date</label>
                        <input type="date" name="posting_date" class="w-full" required>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Generate Journal Entry</button>
                    <a href="index.php" class="border border-blue-600 text-blue-600 px-4 py-2 hover:bg-blue-50 transition">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>