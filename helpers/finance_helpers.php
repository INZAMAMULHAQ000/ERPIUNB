<?php

/**
 * Finance Helper Functions
 * Provides common formatting and utility functions for finance module
 */




if (!function_exists('formatCurrency')) {
function formatCurrency($amount, $currency = 'AED') {
    $symbols = [
        'AED' => 'AED ',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format((float)$amount, 2, '.', ',');
}
}

if (!function_exists('getActiveFinancialYear')) {
function getActiveFinancialYear($db) {
    // No static caching: the active FY legitimately CHANGES mid-process
    // (year-end closing activates the next FY). Always read fresh.
    $stmt = $db->query("SELECT * FROM financial_years WHERE status = 'active' LIMIT 1");
    return $stmt->fetch();
}
}

if (!function_exists('generateVoucherNo')) {
function generateVoucherNo($voucherTypeId, $db) {
    $stmt = $db->prepare("SELECT voucher_prefix FROM voucher_types WHERE id = ?");
    $stmt->execute([$voucherTypeId]);
    $type = $stmt->fetch();
    if (!$type) return null;

    $prefix = $type['voucher_prefix'];
    $fy = getActiveFinancialYear($db);
    if (!$fy) return null;

    $fyStart = date('Y', strtotime($fy['start_date']));
    $fyEnd = date('Y', strtotime($fy['end_date']));
    $fyLabel = $fyStart . '-' . $fyEnd;

    // Get the highest existing number for this prefix in this FY
    // Exclude reversal suffixes (-REV) from the search to avoid parsing issues
    $stmt = $db->prepare("
        SELECT voucher_no FROM journal_entries
        WHERE voucher_no LIKE ?
        AND voucher_no NOT LIKE '%-REV'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$prefix . '-' . $fyLabel . '-%']);
    $last = $stmt->fetch();

    if ($last) {
        $parts = explode('-', $last['voucher_no']);
        $lastNum = (int)end($parts);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    return $prefix . '-' . $fyLabel . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}
}

if (!function_exists('formatVoucherStatus')) {
function formatVoucherStatus($status) {
    $badges = [
        'draft'    => ['bg-yellow-100 text-yellow-800', 'Draft'],
        'posted'   => ['bg-green-100 text-green-800', 'Posted'],
        'reversed' => ['bg-red-100 text-red-800', 'Reversed'],
    ];
    $badge = $badges[$status] ?? ['bg-gray-100 text-gray-800', ucfirst($status)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
}
}
if (!function_exists('getPayrollGlMappings')) {
    function getPayrollGlMappings($search = '', $mappingType = '', $status = '') {
        $db = getDB();
        $where = [];
        $params = [];
        if (!empty($search)) {
            $where[] = "(payroll_component LIKE ?)";
            $params[] = "%$search%";
        }
        if (!empty($mappingType)) {
            $where[] = "mapping_type = ?";
            $params[] = $mappingType;
        }
        if (!empty($status)) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT m.*, coa.account_code, coa.account_name
            FROM payroll_finance_mappings m
            JOIN chart_of_accounts coa ON m.chart_account_id = coa.id
            $whereClause
            ORDER BY m.payroll_component
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getPayrollMappingTypes')) {
    function getPayrollMappingTypes() {
        return ['expense', 'liability', 'asset'];
    }
}

if (!function_exists('getMappingStatuses')) {
    function getMappingStatuses() {
        return ['Active', 'Inactive'];
    }
}

if (!function_exists('getPayrollFinancePostings')) {
    function getPayrollFinancePostings($search = '', $status = '') {
        $db = getDB();
        $where = [];
        $params = [];
        if (!empty($search)) {
            $where[] = "(pr.period_name LIKE ?)";
            $params[] = "%$search%";
        }
        if (!empty($status)) {
            $where[] = "p.status = ?";
            $params[] = $status;
        }
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT p.*, je.voucher_no, u.name as created_by_name, pr.period_name as payroll_run_name
            FROM payroll_finance_postings p
            LEFT JOIN journal_entries je ON p.journal_entry_id = je.id
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN payroll_runs pr ON p.payroll_run_id = pr.id
            $whereClause
            ORDER BY p.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getPayrollFinancePostingById')) {
    function getPayrollFinancePostingById($postingId, $withDetails = false) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT p.*, je.voucher_no, u.name as created_by_name, pr.period_name as payroll_run_name
            FROM payroll_finance_postings p
            LEFT JOIN journal_entries je ON p.journal_entry_id = je.id
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN payroll_runs pr ON p.payroll_run_id = pr.id
            WHERE p.id = ?
        ");
        $stmt->execute([$postingId]);
        return $stmt->fetch();
    }
}

if (!function_exists('getJournalEntryDetailsById')) {
    function getJournalEntryDetailsById($journalEntryId) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT jd.*, coa.account_code, coa.account_name
            FROM journal_entry_details jd
            JOIN chart_of_accounts coa ON jd.account_id = coa.id
            WHERE jd.journal_entry_id = ?
            ORDER BY jd.id
        ");
        $stmt->execute([$journalEntryId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getPayrollPaymentBatches')) {
    function getPayrollPaymentBatches($search = '', $status = '') {
        $db = getDB();
        $where = [];
        $params = [];
        if (!empty($search)) {
            $where[] = "(pr.period_name LIKE ? OR pb.reference_number LIKE ?)";
            $s = "%$search%";
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($status)) {
            $where[] = "pb.status = ?";
            $params[] = $status;
        }
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT pb.id, pb.payment_date, pb.amount, pb.reference_number, pb.status, 
                   pr.id AS run_id, pr.period_name, ba.account_name AS bank_account, je.voucher_no
            FROM payroll_payment_batches pb
            JOIN payroll_runs pr ON pb.payroll_run_id = pr.id
            LEFT JOIN bank_accounts ba ON pb.bank_account_id = ba.id
            LEFT JOIN journal_entries je ON pb.journal_entry_id = je.id
            $whereClause
            ORDER BY pb.payment_date DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getPayrollReconciliation')) {
    function getPayrollReconciliation($search = '', $status = '') {
        $db = getDB();
        $where = [];
        $params = [];
        if (!empty($search)) {
            $where[] = "(pr.id LIKE ? OR pr.period_name LIKE ?)";
            $s = "%$search%";
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($status)) {
            $where[] = "r.status = ?";
            $params[] = $status;
        }
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT r.*, u.name as checked_by_name
            FROM payroll_reconciliation r
            LEFT JOIN users u ON r.checked_by = u.id
            $whereClause
            ORDER BY r.checked_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getChartOfAccounts')) {
    function getChartOfAccounts($type = null) {
        $db = getDB();
        $where = [];
        $params = [];
        if ($type !== null) {
            $where[] = "ca.account_type = ?";
            $params[] = $type;
        }
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT ca.id, ca.account_code, ca.account_name, cat.category_name as account_type
            FROM chart_of_accounts ca
            JOIN account_categories cat ON ca.category_id = cat.id
            $whereClause
            ORDER BY ca.account_code
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getBankAccounts')) {
    function getBankAccounts() {
        $db = getDB();
        $sql = "
            SELECT ba.id, ba.account_name, ba.account_number
            FROM bank_accounts ba
            WHERE ba.status = 'active'
            ORDER BY ba.account_name
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

if (!function_exists('getUsers')) {
    function getUsers() {
        $db = getDB();
        $sql = "
            SELECT u.id, u.name, u.email
            FROM users u
            WHERE u.status = 'active'
            ORDER BY u.name
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

if (!function_exists('getPostingStatuses')) {
    function getPostingStatuses() {
        return ['Draft', 'Posted', 'Reversed'];
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if ($date == null || $date == '') {
            return '';
        }
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('formatPayrollAmount')) {
    function formatPayrollAmount($amount) {
        return 'AED ' . number_format((float)$amount, 2, '.', ',');
    }
}

if (!function_exists('formatNumber')) {
    function formatNumber($number) {
        if ($number == null) {
            return '0';
        }
        return number_format((float)$number, 2, '.', ',');
    }
}

if (!function_exists('formatPaymentBatchStatus')) {
    function formatPaymentBatchStatus($status) {
        $badges = [
            'draft'      => ['bg-yellow-100 text-yellow-800', 'Draft'],
            'posted'     => ['bg-green-100 text-green-800', 'Posted'],
            'reconciled' => ['bg-blue-100 text-blue-800', 'Reconciled'],
            'Approved'   => ['bg-green-100 text-green-800', 'Approved'],
            'Paid'       => ['bg-green-100 text-green-800', 'Paid'],
            'Failed'     => ['bg-red-100 text-red-800', 'Failed'],
            'Cancelled'  => ['bg-gray-100 text-gray-800', 'Cancelled'],
        ];
        $badge = $badges[$status] ?? ['bg-gray-100 text-gray-800', ucfirst($status)];
        return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
    }
}

if (!function_exists('sanitize')) {
    function sanitize($string) {
        if (is_array($string)) {
            return array_map('sanitize', $string);
        }
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}
