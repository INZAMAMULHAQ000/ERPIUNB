<?php
/**
 * Payroll GL Mapping Edit
 * Phase F10 - Payroll Finance Integration
 * 
 * @package XXX
 * @submodule Finance.F10.Mappings.Edit
 */
$pageTitle = 'Edit GL Mapping';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.edit')) {
    redirect_to('no-access.php');
}

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM payroll_finance_mappings WHERE id = ?");
$stmt->execute([$id]);
$mapping = $stmt->fetch();

if (!$mapping) {
    redirect_to('index.php');
}

$accounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' ORDER BY account_code")->fetchAll();
?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-8 mb-8 max-w-md">
        <h1 class="text-2xl font-bold mb-6">Edit GL Mapping</h1>

        <form method="POST" action="process.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="grid grid-cols-1 gap-4 mb-6">
                <div>
                    <label class="block mb-1 font-medium" for="payroll_component">Payroll Component</label>
                    <input type="text" name="payroll_component" id="payroll_component"
                        class="w-full" required
                        value="<?= htmlspecialchars($mapping['payroll_component']) ?>">
                </div>
                <div>
                    <label class="block mb-1 font-medium" for="chart_account_id">GL Account</label>
                    <select name="chart_account_id" id="chart_account_id" class="w-full" required>
                        <option value="">-- Select Account --</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $a['id'] == $mapping['chart_account_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['account_code']) ?> - <?= htmlspecialchars($a['account_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-medium" for="mapping_type">Mapping Type</label>
                    <select name="mapping_type" id="mapping_type" class="w-full">
                        <option value="expense" <?= $mapping['mapping_type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                        <option value="liability" <?= $mapping['mapping_type'] === 'liability' ? 'selected' : '' ?>>Liability</option>
                        <option value="asset" <?= $mapping['mapping_type'] === 'asset' ? 'selected' : '' ?>>Asset</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-medium" for="status">Status</label>
                    <select name="status" id="status" class="w-full">
                        <option value="Active" <?= $mapping['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $mapping['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Update Mapping</button>
                <a href="index.php" class="border border-blue-600 text-blue-600 px-4 py-2 hover:bg-blue-50 transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>