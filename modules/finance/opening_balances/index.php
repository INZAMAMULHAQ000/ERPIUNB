<?php
$pageTitle = 'Opening Balances';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.balance.edit')) { die('Access Denied'); }

$db = getDB();

// ——— Handle POST (bulk save) ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['edit'])) {
    $fyId = (int)($_POST['fy_id'] ?? 0);
    $balances = $_POST['balances'] ?? [];

    if (empty($balances)) {
        flash('error', 'No balance data received.');
        to('modules/finance/opening_balances/index.php?fy_id=' . $fyId);
    }

    try {
        $db->beginTransaction();

        $selectStmt = $db->prepare("SELECT opening_balance, opening_balance_type FROM chart_of_accounts WHERE id = ?");
        $updateStmt = $db->prepare("UPDATE chart_of_accounts SET opening_balance = ?, opening_balance_type = ? WHERE id = ?");
        $historyStmt = $db->prepare("INSERT INTO opening_balance_history (account_id, financial_year_id, old_balance, new_balance, balance_type, changed_by) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($balances as $accountId => $data) {
            $accountId = (int)$accountId;
            $newBalance = (float)($data['balance'] ?? 0);
            $newType = ($data['type'] ?? 'debit') === 'credit' ? 'credit' : 'debit';

            // Get current balance
            $selectStmt->execute([$accountId]);
            $current = $selectStmt->fetch();

            if ($current) {
                $oldBalance = (float)$current['opening_balance'];
                $oldType = $current['opening_balance_type'];

                // Only record history if something changed
                if ($oldBalance !== $newBalance || $oldType !== $newType) {
                    $historyStmt->execute([
                        $accountId,
                        $fyId ?: null,
                        $oldBalance,
                        $newBalance,
                        $newType,
                        $_SESSION['user_id']
                    ]);
                }

                // Always update so the displayed value is current
                $updateStmt->execute([$newBalance, $newType, $accountId]);
            }
        }

        $db->commit();
        logAudit($_SESSION['user_id'], 'Bulk updated opening balances', 'finance');
        flash('success', 'Opening balances updated successfully.');
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to update balances: ' . $e->getMessage());
    }

    to('modules/finance/opening_balances/index.php?fy_id=' . $fyId);
}

// ——— Load data ———
$fyId = (int)($_GET['fy_id'] ?? 0);

// Financial years for selector
$financialYears = $db->query("SELECT id, year_name, status FROM financial_years ORDER BY start_date DESC")->fetchAll();

// If no FY selected, default to active
if (!$fyId) {
    $activeFy = $db->query("SELECT id FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();
    $fyId = $activeFy ? (int)$activeFy['id'] : 0;
}

// Active accounts with group name and last modified info
$accounts = $db->prepare("
    SELECT
        coa.id,
        coa.account_code,
        coa.account_name,
        ag.group_name,
        coa.opening_balance,
        coa.opening_balance_type,
        coa.updated_at,
        (SELECT obh.new_balance
         FROM opening_balance_history obh
         WHERE obh.account_id = coa.id
           AND (obh.financial_year_id = ? OR ? = 0)
         ORDER BY obh.changed_at DESC
         LIMIT 1) as last_modified_balance,
        (SELECT obh.changed_at
         FROM opening_balance_history obh
         WHERE obh.account_id = coa.id
           AND (obh.financial_year_id = ? OR ? = 0)
         ORDER BY obh.changed_at DESC
         LIMIT 1) as last_modified_at,
        (SELECT u.name
         FROM opening_balance_history obh
         JOIN users u ON obh.changed_by = u.id
         WHERE obh.account_id = coa.id
           AND (obh.financial_year_id = ? OR ? = 0)
         ORDER BY obh.changed_at DESC
         LIMIT 1) as last_modified_by
    FROM chart_of_accounts coa
    JOIN account_groups ag ON coa.group_id = ag.id
    WHERE coa.status = 'active'
    ORDER BY coa.account_code ASC
");
$accounts->execute([$fyId, $fyId, $fyId, $fyId, $fyId, $fyId]);
$accounts = $accounts->fetchAll();

$isEdit = isset($_GET['edit']);
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Opening Balances</h1>
        <p class="text-gray-600">Manage opening balances for accounts</p>
    </div>
    <div class="flex items-center gap-3">
        <?php if ($isEdit): ?>
            <a href="?fy_id=<?= $fyId ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 text-sm">
                <i class="fas fa-times"></i> Cancel
            </a>
        <?php else: ?>
            <a href="?edit=1&fy_id=<?= $fyId ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-pencil"></i> Edit Opening Balances
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Year Selector -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex items-center gap-4">
        <?php if ($isEdit): ?>
            <input type="hidden" name="edit" value="1">
        <?php endif; ?>
        <label class="text-sm font-medium text-gray-700">Financial Year:</label>
        <select name="fy_id" onchange="this.form.submit()" class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <option value="">-- Select Financial Year --</option>
            <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId === (int)$fy['id'] ? 'selected' : '' ?>>
                    <?= sanitize($fy['year_name']) ?> (<?= ucfirst($fy['status']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded text-sm">Go</button></noscript>
    </form>
</div>

<!-- Balances Table -->
<?php if ($isEdit): ?>
    <!-- EDIT MODE -->
    <form method="POST" id="balances-form">
        <input type="hidden" name="fy_id" value="<?= $fyId ?>">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($accounts as $acc): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($acc['account_code']) ?></td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($acc['account_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($acc['group_name']) ?></td>
                        <td class="px-4 py-3 text-right">
                            <input type="number" name="balances[<?= $acc['id'] ?>][balance]"
                                   value="<?= number_format((float)$acc['opening_balance'], 2, '.', '') ?>"
                                   step="0.01" class="w-40 px-3 py-2 border rounded text-right text-sm focus:ring-2 focus:ring-blue-500">
                        </td>
                        <td class="px-4 py-3 text-center">
                            <select name="balances[<?= $acc['id'] ?>][type]" class="px-2 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="debit" <?= $acc['opening_balance_type'] === 'debit' ? 'selected' : '' ?>>Debit</option>
                                <option value="credit" <?= $acc['opening_balance_type'] === 'credit' ? 'selected' : '' ?>>Credit</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($accounts): ?>
        <div class="mt-4 flex justify-end gap-3">
            <a href="?fy_id=<?= $fyId ?>" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
        <?php endif; ?>
    </form>
<?php else: ?>
    <!-- VIEW MODE -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Modified</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($accounts as $acc): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($acc['account_code']) ?></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($acc['account_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($acc['group_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-medium <?= (float)$acc['opening_balance'] >= 0 ? 'text-gray-900' : 'text-red-600' ?>">
                        <?= number_format((float)$acc['opening_balance'], 2) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 text-xs rounded <?= $acc['opening_balance_type'] === 'debit' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800' ?>">
                            <?= ucfirst($acc['opening_balance_type']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">
                        <?php if ($acc['last_modified_at']): ?>
                            <span title="By: <?= sanitize($acc['last_modified_by'] ?? 'Unknown') ?>">
                                <?= date('M d, Y H:i', strtotime($acc['last_modified_at'])) ?>
                                <?php if ($acc['last_modified_by']): ?>
                                    <span class="text-xs text-gray-400">(<?= sanitize($acc['last_modified_by']) ?>)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-400">Not modified</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$accounts): ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-book text-4xl mb-3 text-gray-300"></i>
            <p>No active accounts found.</p>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
