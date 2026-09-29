<?php
/**
 * Payroll GL Mapping Add
 * Phase F10 - Payroll Finance Integration
 * 
 * @package XXX
 * @submodule Finance.F10.Mappings.Add
 */
$pageTitle = 'Add GL Mapping';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.create')) {
    redirect_to('no-access.php');
}

$db = getDB();
$accounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' ORDER BY account_code")->fetchAll();
?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Add GL Mapping</h1>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6  w-full">
        <form method="POST" action="process.php">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-1 gap-4 mb-6">
                <div>
                    <label class="block mb-1 font-medium" for="payroll_component">Payroll Component</label>
                    <input type="text" name="payroll_component" id="payroll_component" class="w-full" required placeholder="e.g. Basic Salary">
                </div>
                <div>
                    <label class="block mb-1 font-medium" for="chart_account_id">GL Account</label>
                    <select name="chart_account_id" id="chart_account_id" class="w-full" required>
                        <option value="">-- Select Account --</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['account_code']) ?> - <?= htmlspecialchars($a['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-medium" for="mapping_type">Mapping Type</label>
                    <select name="mapping_type" id="mapping_type" class="w-full">
                        <option value="expense">Expense</option>
                        <option value="liability">Liability</option>
                        <option value="asset">Asset</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-medium" for="status">Status</label>
                    <select name="status" id="status" class="w-full">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Create Mapping</button>
                <a href="index.php" class="border border-blue-600 text-blue-600 px-4 py-2 hover:bg-blue-50 transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>