<?php
$pageTitle = 'Fixed Asset History';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/currency.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();
$asset_id = $_GET['asset_id'] ?? '';

if ($asset_id) {
    // Get financial asset
    $stmt = $db->prepare("
        SELECT f.*, a.asset_code, a.asset_name
        FROM fixed_asset_financials f
        LEFT JOIN assets a ON f.asset_id = a.id
        WHERE f.id = ?
    ");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
    
    if (!$asset) {
        flash('error', 'Asset not found');
        to('modules/finance/fixed_assets/financial_records/index.php');
    }
    
    // Build history from existing tables
    $history = [];
    
    // Capitalization events (from journal_entries)
    $stmt = $db->prepare("
        SELECT je.voucher_no, je.voucher_date as created_at, 'capitalized' as event_type,
               je.description, '0' as amount, u.name as user_name
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        WHERE je.description LIKE ? AND je.status = 'posted'
        ORDER BY je.voucher_date DESC
    ");
    $stmt->execute(["%Asset {$asset['asset_id']} capitalized%"]);
    $capitalizations = $stmt->fetchAll();
    $history = array_merge($history, $capitalizations);
    
    // Depreciation events
    $stmt = $db->prepare("
        SELECT je.voucher_no, je.voucher_date as created_at, 'depreciation_posted' as event_type,
               je.description, je.description as amount, u.name as user_name
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        WHERE je.description LIKE ? AND je.status = 'posted'
        ORDER BY je.voucher_date DESC
    ");
    $stmt->execute(["%Depreciation for asset {$asset['asset_id']}%"]);
    $deprecations = $stmt->fetchAll();
    $history = array_merge($history, $deprecations);
    
    // Impairment events
    $stmt = $db->prepare("
        SELECT ai.id as voucher_no, ai.impairment_date as created_at, 'impairment_recorded' as event_type,
               ai.reason as description, ai.impairment_loss as amount, u.name as user_name
        FROM asset_impairment ai
        LEFT JOIN users u ON ai.approved_by = u.id
        WHERE ai.asset_id = ? AND ai.approval_status = 'approved'
        ORDER BY ai.impairment_date DESC
    ");
    $stmt->execute([$asset['asset_id']]);
    $impairments = $stmt->fetchAll();
    $history = array_merge($history, $impairments);
    
    // Disposal events
    $stmt = $db->prepare("
        SELECT ad.id as voucher_no, ad.disposal_date as created_at, 'disposal_recorded' as event_type,
               CONCAT('Disposal via ', ad.disposal_type, ': ', ad.disposal_proceeds) as description,
               ad.gain_loss as amount, '' as user_name
        FROM asset_disposal ad
        WHERE ad.asset_id = ?
        ORDER BY ad.disposal_date DESC
    ");
    $stmt->execute([$asset['asset_id']]);
    $disposals = $stmt->fetchAll();
    $history = array_merge($history, $disposals);
    
    // Transfer events
    $stmt = $db->prepare("
        SELECT ath.id as voucher_no, ath.transfer_date as created_at, 'transfer_recorded' as event_type,
               ath.transfer_reason as description, 0 as amount, u.name as user_name
        FROM asset_transfer_history ath
        LEFT JOIN users u ON ath.approved_by = u.id
        WHERE ath.asset_id = ?
        ORDER BY ath.transfer_date DESC
    ");
    $stmt->execute([$asset['asset_id']]);
    $transfers = $stmt->fetchAll();
    $history = array_merge($history, $transfers);
    
    // Sort by date descending
    usort($history, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    $history = array_slice($history, 0, 50);
    
} else {
    // All assets - show recent history from all tables
    $history = [];
    
    // Recent capitalizations
    $stmt = $db->query("
        SELECT je.voucher_no, je.voucher_date as created_at, 'capitalized' as event_type,
               je.description, '0' as amount, u.name as user_name
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        WHERE je.description LIKE '%capitalized%' AND je.status = 'posted'
        ORDER BY je.voucher_date DESC
        LIMIT 20
    ");
    $history = array_merge($history, $stmt->fetchAll());
    
    // Recent depreciations
    $stmt = $db->query("
        SELECT je.voucher_no, je.voucher_date as created_at, 'depreciation_posted' as event_type,
               je.description, je.description as amount, u.name as user_name
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        WHERE je.description LIKE '%Depreciation%' AND je.status = 'posted'
        ORDER BY je.voucher_date DESC
        LIMIT 20
    ");
    $history = array_merge($history, $stmt->fetchAll());
    
    // Recent impairments
    $stmt = $db->query("
        SELECT ai.id as voucher_no, ai.impairment_date as created_at, 'impairment_recorded' as event_type,
               ai.reason as description, ai.impairment_loss as amount, u.name as user_name
        FROM asset_impairment ai
        LEFT JOIN users u ON ai.approved_by = u.id
        WHERE ai.approval_status = 'approved'
        ORDER BY ai.impairment_date DESC
        LIMIT 10
    ");
    $history = array_merge($history, $stmt->fetchAll());
    
    // Recent disposals
    $stmt = $db->query("
        SELECT ad.id as voucher_no, ad.disposal_date as created_at, 'disposal_recorded' as event_type,
               CONCAT('Disposal via ', ad.disposal_type, ': ', ad.disposal_proceeds) as description,
               ad.gain_loss as amount, '' as user_name
        FROM asset_disposal ad
        ORDER BY ad.disposal_date DESC
        LIMIT 10
    ");
    $history = array_merge($history, $stmt->fetchAll());
    
    // Sort and limit
    usort($history, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $history = array_slice($history, 0, 50);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?? 'Fixed Asset History' ?> - ERP System</title>
</head>
<body>
<div class="mb-6">
    <?php if ($asset_id): ?>
    <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Asset</a>
    <?php else: ?>
    <a href="<?= url('modules/finance/fixed_assets/financial_records/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Register</a>
    <?php endif; ?>
</div>

<?php if ($asset): ?>
<div class="bg-blue-50 border border-blue-200 p-4 rounded mb-6">
    <h2 class="text-xl font-semibold text-blue-800">
        Financial History for: <?= sanitize($asset['asset_code']) ?> - <?= sanitize($asset['asset_name']) ?>
    </h2>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Journal</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($history as $event): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                    <?= date('M d, Y H:i', strtotime($event['created_at'])) ?>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded font-medium
                        <?= $event['event_type'] == 'capitalized' ? 'bg-green-100 text-green-800' :
                           ($event['event_type'] == 'depreciation_posted' ? 'bg-blue-100 text-blue-800' :
                           ($event['event_type'] == 'impairment_recorded' ? 'bg-red-100 text-red-800' :
                           ($event['event_type'] == 'disposal_recorded' ? 'bg-gray-100 text-gray-800' :
                           ($event['event_type'] == 'transfer_recorded' ? 'bg-purple-100 text-purple-800' :
                           'bg-purple-100 text-purple-800')))) ?>">
                         <?= str_replace('_', ' ', ucfirst($event['event_type'])) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-600">
                    <?= sanitize($event['asset_code'] ?? ($asset['asset_code'] ?? '-')) ?>
                </td>
                <td class="px-6 py-4 text-gray-600">
                    <?= $event['voucher_no'] ? '<span class="text-blue-600">' . sanitize($event['voucher_no']) . '</span>' : '-' ?>
                </td>
                <td class="px-6 py-4 text-right font-medium text-gray-900">
                    <?php 
                        $amt = $event['amount'] ?? 0;
                        echo $amt > 0 ? '+' . formatMoney($amt) : formatMoney($amt);
                    ?>
                </td>
                <td class="px-6 py-4 text-gray-600">
                    <?= sanitize($event['user_name'] ?? '-') ?>
                </td>
                <td class="px-6 py-4 text-gray-600">
                    <?= sanitize($event['description'] ?? $event['notes'] ?? '-') ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No history records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>