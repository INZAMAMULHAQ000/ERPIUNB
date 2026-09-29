<?php

function formatBankStatus($status) {
    $badges = [
        'active'   => ['bg-green-100 text-green-800', 'Active'],
        'inactive' => ['bg-red-100 text-red-800', 'Inactive'],
    ];
    $badge = $badges[$status] ?? ['bg-gray-100 text-gray-800', ucfirst($status)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
}

function formatTxType($type) {
    $labels = [
        'deposit'        => ['bg-green-100 text-green-800', 'Deposit'],
        'withdrawal'     => ['bg-red-100 text-red-800', 'Withdrawal'],
        'interest'       => ['bg-blue-100 text-blue-800', 'Interest'],
        'bank_charges'   => ['bg-yellow-100 text-yellow-800', 'Bank Charges'],
        'adjustment'     => ['bg-purple-100 text-purple-800', 'Adjustment'],
        'receipt'        => ['bg-green-100 text-green-800', 'Receipt'],
        'payment'        => ['bg-red-100 text-red-800', 'Payment'],
        'cash_deposit'   => ['bg-blue-100 text-blue-800', 'Cash Deposit'],
        'cash_withdrawal'=> ['bg-orange-100 text-orange-800', 'Cash Withdrawal'],
    ];
    $badge = $labels[$type] ?? ['bg-gray-100 text-gray-800', ucfirst(str_replace('_', ' ', $type))];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
}

function formatTxStatus($status) {
    $badges = [
        'posted'   => ['bg-green-100 text-green-800', 'Posted'],
        'draft'    => ['bg-yellow-100 text-yellow-800', 'Draft'],
        'cancelled'=> ['bg-red-100 text-red-800', 'Cancelled'],
    ];
    $badge = $badges[$status] ?? ['bg-gray-100 text-gray-800', ucfirst($status)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
}

function formatReconStatus($status) {
    $badges = [
        'in_progress' => ['bg-yellow-100 text-yellow-800', 'In Progress'],
        'completed'   => ['bg-green-100 text-green-800', 'Completed'],
        'cancelled'   => ['bg-red-100 text-red-800', 'Cancelled'],
    ];
    $badge = $badges[$status] ?? ['bg-gray-100 text-gray-800', ucfirst(str_replace('_', ' ', $status))];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
}

/**
 * Get the chart_of_accounts ID linked to a bank account.
 */
function getBankAccountChartId($bankAccountId, $db) {
    $stmt = $db->prepare("SELECT chart_account_id FROM bank_accounts WHERE id = ?");
    $stmt->execute([$bankAccountId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['chart_account_id'] : null;
}

/**
 * Get the chart_of_accounts ID linked to a cash account.
 */
function getCashAccountChartId($cashAccountId, $db) {
    $stmt = $db->prepare("SELECT chart_account_id FROM cash_accounts WHERE id = ?");
    $stmt->execute([$cashAccountId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['chart_account_id'] : null;
}

/**
 * Get running balance for a chart of accounts from the general ledger.
 */
function getAccountBalance($chartAccountId, $db) {
    $fy = getActiveFinancialYear($db);
    if (!$fy) return 0.00;
    $stmt = $db->prepare("SELECT running_balance FROM general_ledger WHERE account_id = ? AND financial_year_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$chartAccountId, $fy['id']]);
    $row = $stmt->fetch();
    if ($row) return (float)$row['running_balance'];
    // Fallback to opening balance
    $stmt = $db->prepare("SELECT opening_balance, opening_balance_type FROM chart_of_accounts WHERE id = ?");
    $stmt->execute([$chartAccountId]);
    $acct = $stmt->fetch();
    if (!$acct) return 0.00;
    $bal = (float)$acct['opening_balance'];
    return $acct['opening_balance_type'] === 'credit' ? -$bal : $bal;
}

/**
 * Generate a reference number for banking transactions.
 */
function generateBankRefNo($prefix, $db) {
    $fy = getActiveFinancialYear($db);
    if (!$fy) return $prefix . '-' . time();
    $fyShort = date('y', strtotime($fy['start_date'])) . date('y', strtotime($fy['end_date']));
    $stmt = $db->prepare("SELECT COUNT(*) FROM bank_transactions WHERE reference_number LIKE ?");
    $stmt->execute([$prefix . '-' . $fyShort . '-%']);
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . '-' . $fyShort . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
