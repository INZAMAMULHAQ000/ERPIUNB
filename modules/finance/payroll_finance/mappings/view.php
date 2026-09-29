<?php
/**
 * Payroll GL Mapping View
 * Phase F10 - Payroll Finance Integration
 * 
 * @package XXX
 * @submodule Finance.F10.Mappings.View
 */
$pageTitle = 'GL Mapping Details';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.view')) {
    redirect_to('no-access.php');
}

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("
    SELECT pfm.*, coa.account_code, coa.account_name
    FROM payroll_finance_mappings pfm
    LEFT JOIN chart_of_accounts coa ON pfm.chart_account_id = coa.id
    WHERE pfm.id = ?
");
$stmt->execute([$id]);
$mapping = $stmt->fetch();

if (!$mapping) {
    redirect_to('index.php');
}
?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-8 mb-8 max-w-4xl">
        <h1 class="text-2xl font-bold mb-6">Mapping Details</h1>

        <div>
            <p class="text-sm text-gray-500 mb-2">Payroll Component</p>
            <p class="text-3xl font-bold"><?= htmlspecialchars($mapping['payroll_component']) ?></p>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-sm text-gray-500">GL Account</p>
                <p class="text-3xl font-bold text-blue-600">
                    <?php if ($mapping['account_code']): ?>
                        <?= htmlspecialchars($mapping['account_code']) ?> - <?= htmlspecialchars($mapping['account_name']) ?>
                    <?php else: ?>
                        Account #<?= (int)$mapping['chart_account_id'] ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Mapping Type</p>
                <p class="text-3xl font-bold text-green-600"><?= htmlspecialchars(ucfirst($mapping['mapping_type'])) ?></p>
            </div>
        </div>

        <div>
            <p class="text-sm text-gray-500">Status</p>
            <p class="text-3xl font-bold text-green-600"><?= htmlspecialchars($mapping['status']) ?></p>
        </div>

        <div class="mt-8 pt-6 border-t">
            <p class="text-sm text-gray-500">Created At</p>
            <p class="font-medium"><?= htmlspecialchars($mapping['created_at']) ?></p>
            <p class="text-xs text-gray-400">Updated At</p>
            <p class="font-medium"><?= htmlspecialchars($mapping['updated_at']) ?></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>