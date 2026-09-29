<?php
/**
 * FinancialReportService — THE centralized financial reporting engine.
 * 
 * F11 — Financial Reporting & Financial Statements
 * 
 * CORE PRINCIPLE: Reports NEVER maintain independent financial balances.
 * All data is derived from Journal Entries, Journal Entry Lines, General Ledger,
 * Chart of Accounts, and subsidiary ledgers (AR, AP, Tax, Fixed Assets, Payroll, Budgets).
 * 
 * The General Ledger is the accounting source of truth.
 */

require_once __DIR__ . '/../helpers/finance_helpers.php';

class FinancialReportService {
    private static function tableExists(PDO $db, string $table): bool {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($table));
        return (bool)$stmt->fetchColumn();
    }

    private static function columnExists(PDO $db, string $table, string $column): bool {
        if (!self::tableExists($db, $table)) {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }
        $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($column));
        return (bool)$stmt->fetchColumn();
    }

    private static function normalizeDateRange($db, int &$fyId, ?string &$dateFrom, ?string &$dateTo): ?array {
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }

        $fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fy = $fyStmt->fetch();

        if (!$fy) {
            return null;
        }

        if (!$dateFrom) {
            $dateFrom = $fy['start_date'];
        }
        if (!$dateTo) {
            $dateTo = $fy['end_date'];
        }

        return $fy;
    }

    private static function signedOpeningBalance(array $account): float {
        $openingBal = (float)($account['opening_balance'] ?? 0);
        return (($account['opening_balance_type'] ?? 'debit') === 'credit') ? -$openingBal : $openingBal;
    }
    
    // =========================================================================
    // TRIAL BALANCE
    // =========================================================================
    
    /**
     * Generate Trial Balance for a period.
     */
    public static function generateTrialBalance(array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $periodId = (int)($params['period_id'] ?? 0);
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }
        
        $fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fy = $fyStmt->fetch();
        
        if (!$fy) {
            return ['success' => false, 'message' => 'Financial year not found'];
        }
        
        if ($periodId) {
            $periodStmt = $db->prepare("SELECT start_date, end_date FROM accounting_periods WHERE id = ?");
            $periodStmt->execute([$periodId]);
            $period = $periodStmt->fetch();
            if ($period) {
                $dateFrom = $dateFrom ?: $period['start_date'];
                $dateTo = $dateTo ?: $period['end_date'];
            }
        }
        
        if (!$dateTo) $dateTo = $fy['end_date'];
        
        $accounts = $db->query("
            SELECT coa.id, coa.account_code, coa.account_name, coa.opening_balance, coa.opening_balance_type,
                   ag.group_name, ag.nature, ag.id as group_id
            FROM chart_of_accounts coa
            JOIN account_groups ag ON ag.id = coa.group_id
            WHERE coa.status = 'active'
            ORDER BY coa.account_code
        ")->fetchAll();
        
        $lines = [];
        $totals = ['opening_debit' => 0, 'opening_credit' => 0, 'period_debit' => 0, 'period_credit' => 0, 'closing_debit' => 0, 'closing_credit' => 0];
        
        foreach ($accounts as $acct) {
            $accountId = (int)$acct['id'];
            
            $openingBal = (float)$acct['opening_balance'];
            $openingType = $acct['opening_balance_type'] ?? 'debit';
            $effectiveOpening = ($openingType === 'credit') ? -$openingBal : $openingBal;
            
            if ($dateFrom) {
                $glOpeningStmt = $db->prepare("
                    SELECT COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit
                    FROM general_ledger WHERE account_id = ? AND financial_year_id = ? AND posting_date < ?
                ");
                $glOpeningStmt->execute([$accountId, $fyId, $dateFrom]);
                $glOpening = $glOpeningStmt->fetch();
                $effectiveOpening += (float)$glOpening['debit'] - (float)$glOpening['credit'];
            }
            
            $periodStmt = $db->prepare("
                SELECT COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit
                FROM general_ledger WHERE account_id = ? AND financial_year_id = ? AND posting_date >= ? AND posting_date <= ?
            ");
            $periodStmt->execute([$accountId, $fyId, $dateFrom ?: $fy['start_date'], $dateTo]);
            $period = $periodStmt->fetch();
            
            $periodDebit = (float)$period['debit'];
            $periodCredit = (float)$period['credit'];
            $closingBal = $effectiveOpening + $periodDebit - $periodCredit;
            
            if (abs($effectiveOpening) < 0.01 && $periodDebit < 0.01 && $periodCredit < 0.01) continue;
            
            $lines[] = [
                'account_id' => $accountId, 'account_code' => $acct['account_code'], 'account_name' => $acct['account_name'],
                'group_name' => $acct['group_name'], 'nature' => $acct['nature'],
                'opening_debit' => $effectiveOpening > 0 ? abs($effectiveOpening) : 0,
                'opening_credit' => $effectiveOpening < 0 ? abs($effectiveOpening) : 0,
                'period_debit' => $periodDebit, 'period_credit' => $periodCredit,
                'closing_debit' => $closingBal > 0 ? abs($closingBal) : 0,
                'closing_credit' => $closingBal < 0 ? abs($closingBal) : 0,
            ];
            
            $totals['opening_debit'] += $effectiveOpening > 0 ? abs($effectiveOpening) : 0;
            $totals['opening_credit'] += $effectiveOpening < 0 ? abs($effectiveOpening) : 0;
            $totals['period_debit'] += $periodDebit;
            $totals['period_credit'] += $periodCredit;
            $totals['closing_debit'] += $closingBal > 0 ? abs($closingBal) : 0;
            $totals['closing_credit'] += $closingBal < 0 ? abs($closingBal) : 0;
        }
        
        $isBalanced = abs($totals['closing_debit'] - $totals['closing_credit']) < 0.01;
        
        return [
            'success' => true, 'params' => compact('fyId', 'periodId', 'dateFrom', 'dateTo'),
            'lines' => $lines, 'totals' => $totals,
            'integrity' => [
                'is_balanced' => $isBalanced,
                'message' => $isBalanced ? 'Trial Balance is balanced.' : 'CRITICAL: Trial Balance NOT balanced!',
            ],
        ];
    }
    
    // =========================================================================
    // PROFIT & LOSS
    // =========================================================================
    
    public static function generateProfitLoss(array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }
        
        $fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fy = $fyStmt->fetch();
        
        if (!$fy) return ['success' => false, 'message' => 'Financial year not found'];
        
        if (!$dateFrom) $dateFrom = $fy['start_date'];
        if (!$dateTo) $dateTo = $fy['end_date'];
        
        $sumByNature = function($nature, array $nameIncludes = [], array $nameExcludes = []) use ($db, $fyId, $dateFrom, $dateTo) {
            $filters = '';
            $params = [$nature, $fyId, $dateFrom, $dateTo];

            foreach ($nameIncludes as $needle) {
                $filters .= " AND (LOWER(ag.group_name) LIKE ? OR LOWER(coa.account_name) LIKE ?)";
                $params[] = '%' . strtolower($needle) . '%';
                $params[] = '%' . strtolower($needle) . '%';
            }

            foreach ($nameExcludes as $needle) {
                $filters .= " AND LOWER(ag.group_name) NOT LIKE ? AND LOWER(coa.account_name) NOT LIKE ?";
                $params[] = '%' . strtolower($needle) . '%';
                $params[] = '%' . strtolower($needle) . '%';
            }

            $stmt = $db->prepare("
                SELECT COALESCE(SUM(gl.debit - gl.credit), 0) as balance
                FROM general_ledger gl
                JOIN chart_of_accounts coa ON coa.id = gl.account_id
                JOIN account_groups ag ON ag.id = coa.group_id
                WHERE ag.nature = ? AND gl.financial_year_id = ? AND gl.posting_date BETWEEN ? AND ?
                $filters
            ");
            $stmt->execute($params);
            return (float)$stmt->fetchColumn();
        };
        
        $revenue = -$sumByNature('income', [], ['other income', 'discount received']);
        $costOfSales = $sumByNature('expense', ['cost']);
        $operatingExpenses = $sumByNature('expense', [], ['cost', 'tax']);
        $otherIncome = -$sumByNature('income', ['other']);
        $otherExpenses = 0;
        $taxExpense = $sumByNature('expense', ['tax']);
        
        $grossProfit = $revenue - $costOfSales;
        $operatingProfit = $grossProfit - $operatingExpenses;
        $profitBeforeTax = $operatingProfit + $otherIncome - $otherExpenses;
        $netProfit = $profitBeforeTax - $taxExpense;
        
        return [
            'success' => true,
            'params' => compact('fyId', 'dateFrom', 'dateTo'),
            'profit_loss' => [
                'revenue' => ['label' => 'Revenue', 'amount' => $revenue],
                'cost_of_sales' => ['label' => 'Cost of Sales', 'amount' => $costOfSales],
                'gross_profit' => ['label' => 'Gross Profit', 'amount' => $grossProfit, 'is_subtotal' => true],
                'operating_expenses' => ['label' => 'Operating Expenses', 'amount' => $operatingExpenses],
                'operating_profit' => ['label' => 'Operating Profit', 'amount' => $operatingProfit, 'is_subtotal' => true],
                'other_income' => ['label' => 'Other Income', 'amount' => $otherIncome],
                'other_expenses' => ['label' => 'Other Expenses', 'amount' => $otherExpenses],
                'profit_before_tax' => ['label' => 'Profit Before Tax', 'amount' => $profitBeforeTax, 'is_subtotal' => true],
                'tax_expense' => ['label' => 'Tax Expense', 'amount' => $taxExpense],
                'net_profit' => ['label' => 'Net Profit', 'amount' => $netProfit, 'is_total' => true],
            ],
            'margins' => [
                'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                'operating_margin' => $revenue > 0 ? round(($operatingProfit / $revenue) * 100, 2) : 0,
                'net_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0,
            ],
        ];
    }
    
    // =========================================================================
    // BALANCE SHEET
    // =========================================================================
    
    public static function generateBalanceSheet(array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateAsOf = $params['date_as_of'] ?? null;
        
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }
        
        $fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fy = $fyStmt->fetch();
        
        if (!$fy) return ['success' => false, 'message' => 'Financial year not found'];
        if (!$dateAsOf) $dateAsOf = $fy['end_date'];
        
        $getBalance = function($nature) use ($db, $fyId, $dateAsOf) {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(gl.debit - gl.credit), 0) as balance
                FROM general_ledger gl
                JOIN chart_of_accounts coa ON coa.id = gl.account_id
                JOIN account_groups ag ON ag.id = coa.group_id
                WHERE ag.nature = ? AND gl.financial_year_id = ? AND gl.posting_date <= ?
            ");
            $stmt->execute([$nature, $fyId, $dateAsOf]);
            return (float)$stmt->fetchColumn();
        };
        
        $addOpening = function($nature) use ($db) {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(CASE coa.opening_balance_type WHEN 'credit' THEN -coa.opening_balance ELSE coa.opening_balance END), 0)
                FROM chart_of_accounts coa JOIN account_groups ag ON ag.id = coa.group_id
                WHERE ag.nature = ? AND coa.status = 'active'
            ");
            $stmt->execute([$nature]);
            return (float)$stmt->fetchColumn();
        };
        
        // Assets are controlled by GL. The F9 fixed-asset subledger is used to
        // split the asset line without adding a second independent balance.
        $totalAssets = $getBalance('asset') + $addOpening('asset');
        $fixedAssets = self::calculateFixedAssetNBV($db, $dateAsOf);
        $currentAssets = $totalAssets - $fixedAssets;
        
        // Liabilities
        $currentLiabilities = abs($getBalance('liability') + $addOpening('liability'));
        $longTermLiabilities = abs($getBalance('long_term_liability') + $addOpening('long_term_liability'));
        $totalLiabilities = $currentLiabilities + $longTermLiabilities;
        
        // Equity
        $shareCapital = abs($getBalance('equity') + $addOpening('equity'));
        $retainedEarnings = self::calculateRetainedEarnings($db, $fyId, $dateAsOf);
        $currentYearPL = self::calculateCurrentYearProfit($db, $fyId, $dateAsOf);
        $totalEquity = $shareCapital + $retainedEarnings + $currentYearPL;
        
        $totalLiabilitiesEquity = $totalLiabilities + $totalEquity;
        $isBalanced = abs($totalAssets - $totalLiabilitiesEquity) < 0.01;
        
        return [
            'success' => true,
            'params' => compact('fyId', 'dateAsOf'),
            'balance_sheet' => [
                'assets' => [
                    'current' => ['label' => 'Current Assets', 'amount' => $currentAssets],
                    'fixed' => ['label' => 'Fixed Assets (NBV)', 'amount' => $fixedAssets],
                    'total' => ['label' => 'TOTAL ASSETS', 'amount' => $totalAssets, 'is_total' => true],
                ],
                'liabilities' => [
                    'current' => ['label' => 'Current Liabilities', 'amount' => $currentLiabilities],
                    'long_term' => ['label' => 'Long-Term Liabilities', 'amount' => $longTermLiabilities],
                    'total' => ['label' => 'TOTAL LIABILITIES', 'amount' => $totalLiabilities, 'is_total' => true],
                ],
                'equity' => [
                    'share_capital' => ['label' => 'Share Capital', 'amount' => $shareCapital],
                    'retained_earnings' => ['label' => 'Retained Earnings', 'amount' => $retainedEarnings],
                    'current_year_pl' => ['label' => 'Current Year Profit/(Loss)', 'amount' => $currentYearPL],
                    'total' => ['label' => 'TOTAL EQUITY', 'amount' => $totalEquity, 'is_total' => true],
                ],
                'total_liabilities_equity' => ['label' => 'TOTAL LIABILITIES + EQUITY', 'amount' => $totalLiabilitiesEquity, 'is_grand_total' => true],
            ],
            'integrity' => [
                'is_balanced' => $isBalanced,
                'message' => $isBalanced ? 'Balance Sheet balanced.' : 'CRITICAL: Balance Sheet NOT balanced!',
                'total_assets' => $totalAssets,
                'total_liabilities_equity' => $totalLiabilitiesEquity,
                'difference' => $totalAssets - $totalLiabilitiesEquity,
            ],
        ];
    }
    
    // =========================================================================
    // CASH FLOW STATEMENT
    // =========================================================================
    
    public static function generateCashFlow(array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;

        $fy = self::normalizeDateRange($db, $fyId, $dateFrom, $dateTo);
        if (!$fy) return ['success' => false, 'message' => 'Financial year not found'];

        // Get cash/bank accounts
        $cashAccounts = $db->query("
            SELECT coa.id, coa.account_code, coa.account_name
            FROM chart_of_accounts coa
            JOIN account_groups ag ON ag.id = coa.group_id
            WHERE ag.nature = 'asset' AND (coa.account_name LIKE '%cash%' OR coa.account_name LIKE '%bank%')
        ")->fetchAll();
        
        $cashAccountIds = array_map(fn($row) => (int)$row['id'], $cashAccounts);
        
        // Cash movement in period
        $netCashFlow = 0;
        $openingCash = 0;
        $cashDetails = [];

        if (!empty($cashAccountIds)) {
            $placeholders = implode(',', array_fill(0, count($cashAccountIds), '?'));
            $cfStmt = $db->prepare("
                SELECT COALESCE(SUM(debit - credit), 0) as net_cash
                FROM general_ledger
                WHERE account_id IN ($placeholders) AND financial_year_id = ? AND posting_date BETWEEN ? AND ?
            ");
            $cfStmt->execute(array_merge($cashAccountIds, [$fyId, $dateFrom, $dateTo]));
            $netCashFlow = (float)$cfStmt->fetchColumn();

            $openStmt = $db->prepare("
                SELECT COALESCE(SUM(debit - credit), 0) as opening
                FROM general_ledger WHERE account_id IN ($placeholders) AND financial_year_id = ? AND posting_date < ?
            ");
            $openStmt->execute(array_merge($cashAccountIds, [$fyId, $dateFrom]));
            $openingCash = (float)$openStmt->fetchColumn();

            $obStmt = $db->prepare("
                SELECT COALESCE(SUM(CASE opening_balance_type WHEN 'credit' THEN -opening_balance ELSE opening_balance END), 0)
                FROM chart_of_accounts WHERE id IN ($placeholders)
            ");
            $obStmt->execute($cashAccountIds);
            $openingCash += (float)$obStmt->fetchColumn();

            $cashDetailStmt = $db->prepare("
                SELECT coa.id, coa.account_code, coa.account_name,
                       COALESCE(SUM(gl.debit - gl.credit), 0) as balance
                FROM chart_of_accounts coa
                LEFT JOIN general_ledger gl ON gl.account_id = coa.id
                    AND gl.financial_year_id = ?
                    AND gl.posting_date BETWEEN ? AND ?
                WHERE coa.id IN ($placeholders)
                GROUP BY coa.id, coa.account_code, coa.account_name
                ORDER BY coa.account_code
            ");
            $cashDetailStmt->execute(array_merge([$fyId, $dateFrom, $dateTo], $cashAccountIds));
            $cashDetails = $cashDetailStmt->fetchAll();
        }
        
        $closingCash = $openingCash + $netCashFlow;

        $categories = [
            'operating' => ['label' => 'Net Cash from Operating Activities', 'amount' => 0, 'items' => []],
            'investing' => ['label' => 'Net Cash from Investing Activities', 'amount' => 0, 'items' => []],
            'financing' => ['label' => 'Net Cash from Financing Activities', 'amount' => 0, 'items' => []],
            'non_cash' => ['label' => 'Non-Cash Classified Movement', 'amount' => 0, 'items' => []],
        ];

        if (self::tableExists($db, 'cash_flow_classifications')) {
            $classifiedStmt = $db->prepare("
                SELECT cfc.classification, coa.account_code, coa.account_name,
                       COALESCE(SUM(gl.credit - gl.debit), 0) as amount
                FROM cash_flow_classifications cfc
                JOIN chart_of_accounts coa ON coa.id = cfc.account_id
                LEFT JOIN general_ledger gl ON gl.account_id = cfc.account_id
                    AND gl.financial_year_id = ?
                    AND gl.posting_date BETWEEN ? AND ?
                WHERE cfc.is_active = 1
                GROUP BY cfc.classification, coa.id, coa.account_code, coa.account_name
                HAVING ABS(amount) >= 0.01
                ORDER BY cfc.classification, coa.account_code
            ");
            $classifiedStmt->execute([$fyId, $dateFrom, $dateTo]);
            foreach ($classifiedStmt->fetchAll() as $row) {
                $classification = $row['classification'];
                if (!isset($categories[$classification])) {
                    continue;
                }
                $amount = (float)$row['amount'];
                $categories[$classification]['amount'] += $amount;
                $categories[$classification]['items'][] = [
                    'account_code' => $row['account_code'],
                    'account_name' => $row['account_name'],
                    'amount' => $amount,
                ];
            }
        }

        $classifiedCashFlow = $categories['operating']['amount'] + $categories['investing']['amount'] + $categories['financing']['amount'];
        $unclassifiedDifference = $netCashFlow - $classifiedCashFlow;
        if (abs($unclassifiedDifference) >= 0.01) {
            $categories['operating']['amount'] += $unclassifiedDifference;
            $categories['operating']['items'][] = [
                'account_code' => '',
                'account_name' => 'Unclassified cash movement',
                'amount' => $unclassifiedDifference,
            ];
        }
        
        return [
            'success' => true,
            'params' => compact('fyId', 'dateFrom', 'dateTo'),
            'cash_flow' => [
                'operating' => $categories['operating'],
                'investing' => $categories['investing'],
                'financing' => $categories['financing'],
                'non_cash' => $categories['non_cash'],
                'net_cash_flow' => ['label' => 'Net Increase/(Decrease) in Cash', 'amount' => $netCashFlow],
                'opening_cash' => ['label' => 'Opening Cash and Bank Balances', 'amount' => $openingCash],
                'closing_cash' => ['label' => 'Closing Cash and Bank Balances', 'amount' => $closingCash, 'is_total' => true],
            ],
            'cash_accounts' => $cashDetails,
            'classification_note' => abs($unclassifiedDifference) >= 0.01
                ? 'Unclassified movement was assigned to operating activities so the statement reconciles to cash/bank movement.'
                : 'Cash-flow classifications reconcile to cash/bank movement.',
        ];
    }

    // =========================================================================
    // GENERAL LEDGER
    // =========================================================================

    public static function generateGeneralLedger(array $params = []) {
        $db = getDB();

        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $accountId = (int)($params['account_id'] ?? 0);
        $voucherType = trim((string)($params['voucher_type'] ?? ''));
        $reference = trim((string)($params['reference'] ?? ''));
        $userId = (int)($params['user_id'] ?? 0);

        $fy = self::normalizeDateRange($db, $fyId, $dateFrom, $dateTo);
        if (!$fy) return ['success' => false, 'message' => 'Financial year not found'];

        $where = "WHERE gl.financial_year_id = ? AND gl.posting_date BETWEEN ? AND ?";
        $queryParams = [$fyId, $dateFrom, $dateTo];

        if ($accountId) {
            $where .= " AND gl.account_id = ?";
            $queryParams[] = $accountId;
        }
        if ($voucherType !== '') {
            $where .= " AND gl.voucher_type = ?";
            $queryParams[] = $voucherType;
        }
        if ($reference !== '') {
            $where .= " AND (gl.voucher_no LIKE ? OR je.reference_no LIKE ? OR je.description LIKE ?)";
            $like = '%' . $reference . '%';
            $queryParams[] = $like;
            $queryParams[] = $like;
            $queryParams[] = $like;
        }
        if ($userId) {
            $where .= " AND je.created_by = ?";
            $queryParams[] = $userId;
        }

        $stmt = $db->prepare("
            SELECT gl.*, coa.account_code, coa.account_name, ag.group_name, ag.nature,
                   je.voucher_no AS journal_no, je.reference_no, je.description, je.created_by,
                   u.name AS created_by_name
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            JOIN account_groups ag ON ag.id = coa.group_id
            LEFT JOIN journal_entries je ON je.id = gl.journal_entry_id
            LEFT JOIN users u ON u.id = je.created_by
            $where
            ORDER BY gl.posting_date, gl.id
        ");
        $stmt->execute($queryParams);
        $rows = $stmt->fetchAll();

        $totals = ['debit' => 0, 'credit' => 0, 'net' => 0];
        foreach ($rows as $row) {
            $totals['debit'] += (float)$row['debit'];
            $totals['credit'] += (float)$row['credit'];
        }
        $totals['net'] = $totals['debit'] - $totals['credit'];

        return [
            'success' => true,
            'params' => compact('fyId', 'dateFrom', 'dateTo', 'accountId', 'voucherType', 'reference', 'userId'),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
    
    // =========================================================================
    // ACCOUNT LEDGER
    // =========================================================================
    
    public static function generateAccountLedger($accountId, array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }
        
        $acctStmt = $db->prepare("SELECT coa.*, ag.group_name, ag.nature FROM chart_of_accounts coa JOIN account_groups ag ON ag.id = coa.group_id WHERE coa.id = ?");
        $acctStmt->execute([$accountId]);
        $account = $acctStmt->fetch();
        
        if (!$account) return ['success' => false, 'message' => 'Account not found'];
        
        $fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fy = $fyStmt->fetch();
        
        $openingBal = (float)$account['opening_balance'];
        $effectiveOpening = ($account['opening_balance_type'] ?? 'debit') === 'credit' ? -$openingBal : $openingBal;
        
        if ($dateFrom && $fy) {
            $glOpenStmt = $db->prepare("SELECT COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c FROM general_ledger WHERE account_id = ? AND financial_year_id = ? AND posting_date < ?");
            $glOpenStmt->execute([$accountId, $fyId, $dateFrom]);
            $glOpen = $glOpenStmt->fetch();
            $effectiveOpening += (float)$glOpen['d'] - (float)$glOpen['c'];
        }
        
        $where = "WHERE gl.account_id = ? AND gl.financial_year_id = ?";
        $p = [$accountId, $fyId];
        if ($dateFrom) { $where .= " AND gl.posting_date >= ?"; $p[] = $dateFrom; }
        if ($dateTo) { $where .= " AND gl.posting_date <= ?"; $p[] = $dateTo; }
        
        $stmt = $db->prepare("SELECT gl.*, je.description, je.reference_no FROM general_ledger gl LEFT JOIN journal_entries je ON je.id = gl.journal_entry_id $where ORDER BY gl.posting_date, gl.id");
        $stmt->execute($p);
        $transactions = $stmt->fetchAll();
        
        $runningBalance = $effectiveOpening;
        $totalDebit = 0; $totalCredit = 0;
        foreach ($transactions as &$txn) {
            $runningBalance += (float)$txn['debit'] - (float)$txn['credit'];
            $txn['running_balance'] = $runningBalance;
            $totalDebit += (float)$txn['debit'];
            $totalCredit += (float)$txn['credit'];
        }
        
        return [
            'success' => true, 'account' => $account,
            'opening_balance' => $effectiveOpening,
            'transactions' => $transactions,
            'totals' => ['debit' => $totalDebit, 'credit' => $totalCredit],
            'closing_balance' => $runningBalance,
        ];
    }
    
    // =========================================================================
    // AR REPORTS
    // =========================================================================
    
    public static function generateARAging(array $params = []) {
        $db = getDB();
        
        $asOfDate = $params['as_of_date'] ?? date('Y-m-d');
        $clientId = (int)($params['client_id'] ?? 0);
        
        $where = "WHERE ci.status IN ('sent','partially_paid','overdue') AND ci.balance_amount > 0";
        $p = [];
        if ($clientId) { $where .= " AND ci.client_id = ?"; $p[] = $clientId; }
        
        $stmt = $db->prepare("SELECT ci.*, c.company_name, c.client_code FROM customer_invoices ci JOIN clients c ON c.id = ci.client_id $where ORDER BY c.company_name, ci.due_date");
        $stmt->execute($p);
        $invoices = $stmt->fetchAll();
        
        $asOfTs = strtotime($asOfDate);
        $buckets = ['current' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $clientAging = [];
        
        foreach ($invoices as $inv) {
            $dueTs = strtotime($inv['due_date']);
            $daysOverdue = floor(($asOfTs - $dueTs) / 86400);
            $balance = (float)$inv['balance_amount'];
            
            $bucket = $daysOverdue <= 30 ? 'current' : ($daysOverdue <= 60 ? '31_60' : ($daysOverdue <= 90 ? '61_90' : '90_plus'));
            $buckets[$bucket] += $balance;
            
            $cid = $inv['client_id'];
            if (!isset($clientAging[$cid])) $clientAging[$cid] = ['name' => $inv['company_name'], 'current' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0, 'total' => 0];
            $clientAging[$cid][$bucket] += $balance;
            $clientAging[$cid]['total'] += $balance;
        }
        
        return [
            'success' => true, 'as_of_date' => $asOfDate,
            'buckets' => $buckets,
            'client_aging' => $clientAging,
            'invoices' => $invoices,
            'total_outstanding' => array_sum($buckets),
        ];
    }
    
    public static function generateCustomerStatement($clientId, array $params = []) {
        $db = getDB();
        
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? date('Y-m-d');
        
        $clientStmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $clientStmt->execute([$clientId]);
        $client = $clientStmt->fetch();
        
        if (!$client) return ['success' => false, 'message' => 'Client not found'];
        
        // Invoices
        $invStmt = $db->prepare("SELECT 'invoice' as type, invoice_number as doc_no, invoice_date as doc_date, total_amount as debit, 0 as credit FROM customer_invoices WHERE client_id = ? AND status NOT IN ('cancelled','draft') AND invoice_date <= ?");
        $invStmt->execute([$clientId, $dateTo]);
        $invoices = $invStmt->fetchAll();
        
        // Receipts
        $rcptStmt = $db->prepare("SELECT 'receipt' as type, receipt_number as doc_no, receipt_date as doc_date, 0 as debit, amount as credit FROM customer_receipts WHERE client_id = ? AND status = 'posted' AND receipt_date <= ?");
        $rcptStmt->execute([$clientId, $dateTo]);
        $receipts = $rcptStmt->fetchAll();
        
        $transactions = array_merge($invoices, $receipts);
        usort($transactions, fn($a, $b) => strtotime($a['doc_date']) - strtotime($b['doc_date']));
        
        $balance = 0;
        foreach ($transactions as &$txn) {
            $balance += (float)$txn['debit'] - (float)$txn['credit'];
            $txn['balance'] = $balance;
        }
        
        return ['success' => true, 'client' => $client, 'transactions' => $transactions, 'closing_balance' => $balance];
    }
    
    // =========================================================================
    // AP REPORTS
    // =========================================================================
    
    public static function generateAPAging(array $params = []) {
        $db = getDB();
        
        $asOfDate = $params['as_of_date'] ?? date('Y-m-d');
        $vendorId = (int)($params['vendor_id'] ?? 0);
        
        $where = "WHERE vb.balance_amount > 0 AND vb.status IN ('approved','partially_paid','overdue')";
        $p = [];
        if ($vendorId) { $where .= " AND vb.vendor_id = ?"; $p[] = $vendorId; }
        
        $stmt = $db->prepare("SELECT vb.*, v.vendor_name FROM vendor_bills vb JOIN vendors v ON v.id = vb.vendor_id $where ORDER BY v.vendor_name, vb.due_date");
        $stmt->execute($p);
        $bills = $stmt->fetchAll();
        
        $asOfTs = strtotime($asOfDate);
        $buckets = ['current' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $vendorAging = [];
        
        foreach ($bills as $bill) {
            $dueTs = strtotime($bill['due_date']);
            $daysOverdue = floor(($asOfTs - $dueTs) / 86400);
            $balance = (float)$bill['balance_amount'];
            
            $bucket = $daysOverdue <= 30 ? 'current' : ($daysOverdue <= 60 ? '31_60' : ($daysOverdue <= 90 ? '61_90' : '90_plus'));
            $buckets[$bucket] += $balance;
            
            $vid = $bill['vendor_id'];
            if (!isset($vendorAging[$vid])) $vendorAging[$vid] = ['name' => $bill['vendor_name'], 'current' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0, 'total' => 0];
            $vendorAging[$vid][$bucket] += $balance;
            $vendorAging[$vid]['total'] += $balance;
        }
        
        return [
            'success' => true, 'as_of_date' => $asOfDate,
            'buckets' => $buckets,
            'vendor_aging' => $vendorAging,
            'bills' => $bills,
            'total_outstanding' => array_sum($buckets),
        ];
    }

    public static function generateVendorStatement($vendorId, array $params = []) {
        $db = getDB();

        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? date('Y-m-d');

        $vendorStmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
        $vendorStmt->execute([$vendorId]);
        $vendor = $vendorStmt->fetch();

        if (!$vendor) return ['success' => false, 'message' => 'Vendor not found'];

        $whereDate = $dateFrom ? "AND bill_date >= ?" : "";
        $billParams = [$vendorId];
        if ($dateFrom) $billParams[] = $dateFrom;
        $billParams[] = $dateTo;

        $billStmt = $db->prepare("
            SELECT 'bill' as type, bill_number as doc_no, bill_date as doc_date,
                   total_amount as credit, 0 as debit, journal_entry_id
            FROM vendor_bills
            WHERE vendor_id = ? AND status NOT IN ('cancelled','draft') $whereDate AND bill_date <= ?
        ");
        $billStmt->execute($billParams);
        $bills = $billStmt->fetchAll();

        $wherePayDate = $dateFrom ? "AND payment_date >= ?" : "";
        $paymentParams = [$vendorId];
        if ($dateFrom) $paymentParams[] = $dateFrom;
        $paymentParams[] = $dateTo;

        $payStmt = $db->prepare("
            SELECT 'payment' as type, payment_number as doc_no, payment_date as doc_date,
                   0 as credit, amount as debit, journal_entry_id
            FROM vendor_payments
            WHERE vendor_id = ? AND status = 'posted' $wherePayDate AND payment_date <= ?
        ");
        $payStmt->execute($paymentParams);
        $payments = $payStmt->fetchAll();

        $transactions = array_merge($bills, $payments);
        usort($transactions, fn($a, $b) => strtotime($a['doc_date']) <=> strtotime($b['doc_date']));

        $balance = 0;
        foreach ($transactions as &$txn) {
            $balance += (float)$txn['credit'] - (float)$txn['debit'];
            $txn['balance'] = $balance;
        }

        return [
            'success' => true,
            'vendor' => $vendor,
            'transactions' => $transactions,
            'closing_balance' => $balance,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }
    
    // =========================================================================
    // TAX REPORTS
    // =========================================================================
    
    public static function generateTaxSummary(array $params = []) {
        $db = getDB();
        
        $taxTypeId = (int)($params['tax_type_id'] ?? 0);
        $dateFrom = $params['date_from'] ?? date('Y-m-01');
        $dateTo = $params['date_to'] ?? date('Y-m-t');
        
        $where = "WHERE tt.transaction_date BETWEEN ? AND ? AND tt.status = 'active'";
        $p = [$dateFrom, $dateTo];
        if ($taxTypeId) { $where .= " AND tt.tax_type_id = ?"; $p[] = $taxTypeId; }
        
        $stmt = $db->prepare("
            SELECT tt.tax_direction, 
                   SUM(tt.taxable_amount) as taxable, 
                   SUM(tt.tax_amount) as tax,
                   COUNT(*) as count
            FROM tax_transactions tt
            $where
            GROUP BY tt.tax_direction
        ");
        $stmt->execute($p);
        $rows = $stmt->fetchAll();
        
        $output = ['taxable' => 0, 'tax' => 0];
        $input = ['taxable' => 0, 'tax' => 0];
        
        foreach ($rows as $r) {
            if ($r['tax_direction'] === 'output') {
                $output['taxable'] += (float)$r['taxable'];
                $output['tax'] += (float)$r['tax'];
            } elseif ($r['tax_direction'] === 'input') {
                $input['taxable'] += (float)$r['taxable'];
                $input['tax'] += (float)$r['tax'];
            }
        }
        
        $netPayable = $output['tax'] - $input['tax'];
        
        return [
            'success' => true,
            'output_tax' => $output,
            'input_tax' => $input,
            'net_payable' => $netPayable,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }
    
    // =========================================================================
    // FIXED ASSET REPORTS
    // =========================================================================
    
    public static function generateAssetRegister(array $params = []) {
        $db = getDB();
        
        $categoryId = (int)($params['category_id'] ?? 0);
        $asOfDate = $params['as_of_date'] ?? date('Y-m-d');
        
        if (!self::tableExists($db, 'fixed_asset_financials')) {
            return [
                'success' => true,
                'as_of_date' => $asOfDate,
                'assets' => [],
                'totals' => ['cost' => 0, 'accumulated_depreciation' => 0, 'nbv' => 0],
                'message' => 'Fixed asset financial records table is not available.',
            ];
        }

        $where = "WHERE faf.status IN ('active','impaired')";
        $p = [];
        if ($categoryId) { $where .= " AND a.category_id = ?"; $p[] = $categoryId; }
        if ($asOfDate) { $where .= " AND faf.capitalization_date <= ?"; $p[] = $asOfDate; }
        
        $stmt = $db->prepare("
            SELECT
                faf.id AS financial_asset_id,
                a.id AS asset_id,
                a.asset_code,
                a.asset_name,
                ac.category_name,
                faf.capitalization_date AS acquisition_date,
                faf.capitalized_cost AS acquisition_cost,
                faf.accumulated_depreciation,
                faf.impairment_amount,
                COALESCE(faf.net_book_value, faf.capitalized_cost - faf.accumulated_depreciation - faf.impairment_amount) AS net_book_value,
                faf.status,
                faf.useful_life_months,
                faf.residual_value
            FROM fixed_asset_financials faf
            JOIN assets a ON a.id = faf.asset_id
            LEFT JOIN asset_categories ac ON ac.id = a.category_id
            $where
            ORDER BY a.asset_code
        ");
        $stmt->execute($p);
        $assets = $stmt->fetchAll();
        
        $totalCost = 0; $totalAccDep = 0; $totalNBV = 0;
        foreach ($assets as &$a) {
            $a['nbv'] = (float)$a['net_book_value'];
            $totalCost += (float)$a['acquisition_cost'];
            $totalAccDep += (float)$a['accumulated_depreciation'];
            $totalNBV += $a['nbv'];
        }
        
        return [
            'success' => true, 'as_of_date' => $asOfDate,
            'assets' => $assets,
            'totals' => ['cost' => $totalCost, 'accumulated_depreciation' => $totalAccDep, 'nbv' => $totalNBV],
        ];
    }
    
    // =========================================================================
    // EXPENSE REPORTS
    // =========================================================================
    
    public static function generateExpenseByCategory(array $params = []) {
        $db = getDB();
        
        $dateFrom = $params['date_from'] ?? date('Y-m-01');
        $dateTo = $params['date_to'] ?? date('Y-m-t');
        
        $stmt = $db->prepare("
            SELECT ec.name as category, 
                   COALESCE(SUM(ce.total_amount), 0) as company_expenses,
                   (SELECT COALESCE(SUM(eec.total_amount), 0) FROM employee_expense_claims eec 
                    WHERE eec.category_id = ec.id AND eec.expense_date BETWEEN ? AND ? AND eec.status NOT IN ('cancelled','draft')) as employee_claims
            FROM expense_categories ec
            LEFT JOIN company_expenses ce ON ce.category_id = ec.id AND ce.expense_date BETWEEN ? AND ? AND ce.status NOT IN ('cancelled','draft')
            GROUP BY ec.id ORDER BY company_expenses DESC
        ");
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        $categories = $stmt->fetchAll();
        
        $total = 0;
        foreach ($categories as &$c) {
            $c['total'] = (float)$c['company_expenses'] + (float)$c['employee_claims'];
            $total += $c['total'];
        }
        
        return ['success' => true, 'categories' => $categories, 'total' => $total, 'date_from' => $dateFrom, 'date_to' => $dateTo];
    }
    
    // =========================================================================
    // BUDGET VS ACTUAL
    // =========================================================================
    
    public static function generateBudgetVsActual(array $params = []) {
        $db = getDB();
        
        $budgetId = (int)($params['budget_id'] ?? 0);
        $fyId = (int)($params['financial_year_id'] ?? 0);
        
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }
        
        $fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fy = $fyStmt->fetch();

        if (!$fy) return ['success' => false, 'message' => 'Financial year not found'];

        $where = $budgetId
            ? "WHERE bl.budget_id = ?"
            : "WHERE bl.budget_id IN (SELECT id FROM budgets WHERE financial_year_id = ?)";
        $p = [$budgetId ?: $fyId];
        
        $stmt = $db->prepare("
            SELECT bl.*, coa.account_code, coa.account_name, d.department_name, p.project_name
            FROM budget_lines bl
            JOIN chart_of_accounts coa ON coa.id = bl.chart_of_accounts_id
            LEFT JOIN departments d ON d.id = bl.department_id
            LEFT JOIN projects p ON p.id = bl.project_id
            $where
        ");
        $stmt->execute($p);
        $lines = $stmt->fetchAll();
        
        $results = [];
        foreach ($lines as $l) {
            // Calculate actual from GL
            $actualStmt = $db->prepare("
                SELECT COALESCE(SUM(gl.debit - gl.credit), 0) as actual
                FROM general_ledger gl
                WHERE gl.account_id = ? AND gl.financial_year_id = ? AND gl.posting_date BETWEEN ? AND ?
            ");
            $actualStmt->execute([$l['chart_of_accounts_id'], $fyId, $fy['start_date'], $fy['end_date']]);
            $actual = (float)$actualStmt->fetchColumn();
            
            $budget = (float)$l['budget_amount'];
            $variance = $budget - $actual;
            $utilization = $budget > 0 ? ($actual / $budget) * 100 : 0;
            
            $results[] = [
                'account_code' => $l['account_code'],
                'account_name' => $l['account_name'],
                'department' => $l['department_name'],
                'project' => $l['project_name'],
                'budget' => $budget,
                'actual' => $actual,
                'variance' => $variance,
                'utilization' => round($utilization, 2),
            ];
        }
        
        return ['success' => true, 'lines' => $results, 'financial_year_id' => $fyId];
    }
    
    // =========================================================================
    // PAYROLL REPORTS
    // =========================================================================
    
    public static function generatePayrollSummary(array $params = []) {
        $db = getDB();
        
        $dateFrom = $params['date_from'] ?? date('Y-m-01');
        $dateTo = $params['date_to'] ?? date('Y-m-t');
        $departmentId = (int)($params['department_id'] ?? 0);

        $dateColumn = self::columnExists($db, 'payroll_runs', 'run_date') ? 'run_date' : 'pay_date';
        $deductionColumn = self::columnExists($db, 'payroll_runs', 'total_deductions') ? 'total_deductions' : 'employee_deductions';
        $hasDepartment = self::columnExists($db, 'payroll_runs', 'department_id');
        
        $where = "WHERE pr.$dateColumn BETWEEN ? AND ?";
        $p = [$dateFrom, $dateTo];
        if ($departmentId && $hasDepartment) { $where .= " AND pr.department_id = ?"; $p[] = $departmentId; }

        $departmentSelect = $hasDepartment ? "COALESCE(d.department_name, 'Unassigned') AS department_name" : "'All Departments' AS department_name";
        $departmentJoin = $hasDepartment ? "LEFT JOIN departments d ON d.id = pr.department_id" : "";
        $departmentGroup = $hasDepartment ? "GROUP BY d.id, d.department_name" : "";

        $stmt = $db->prepare("
            SELECT $departmentSelect,
                   COUNT(DISTINCT pr.id) as runs,
                   SUM(pr.gross_salary) as gross,
                   SUM(pr.$deductionColumn) as deductions,
                   SUM(pr.net_salary) as net,
                   SUM(pr.employer_contributions) as employer_contributions
            FROM payroll_runs pr
            $departmentJoin
            $where
            $departmentGroup
        ");
        $stmt->execute($p);
        $departments = $stmt->fetchAll();
        
        $total = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'employer_contributions' => 0];
        foreach ($departments as $d) {
            $total['gross'] += (float)$d['gross'];
            $total['deductions'] += (float)$d['deductions'];
            $total['net'] += (float)$d['net'];
            $total['employer_contributions'] += (float)$d['employer_contributions'];
        }
        
        return ['success' => true, 'departments' => $departments, 'totals' => $total, 'date_column' => $dateColumn];
    }
    
    // =========================================================================
    // FINANCIAL RATIOS
    // =========================================================================
    
    public static function calculateRatios(array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateAsOf = $params['date_as_of'] ?? date('Y-m-d');
        
        // Get BS data
        $bs = self::generateBalanceSheet(['financial_year_id' => $fyId, 'date_as_of' => $dateAsOf]);
        $pl = self::generateProfitLoss(['financial_year_id' => $fyId]);
        
        if (!$bs['success'] || !$pl['success']) {
            return ['success' => false, 'message' => 'Unable to generate base reports'];
        }
        
        $totalAssets = $bs['balance_sheet']['assets']['total']['amount'] ?? 0;
        $currentAssets = $bs['balance_sheet']['assets']['current']['amount'] ?? 0;
        $currentLiabilities = $bs['balance_sheet']['liabilities']['current']['amount'] ?? 0;
        $totalLiabilities = $bs['balance_sheet']['liabilities']['total']['amount'] ?? 0;
        $totalEquity = $bs['balance_sheet']['equity']['total']['amount'] ?? 0;
        
        $revenue = $pl['profit_loss']['revenue']['amount'] ?? 0;
        $grossProfit = $pl['profit_loss']['gross_profit']['amount'] ?? 0;
        $netProfit = $pl['profit_loss']['net_profit']['amount'] ?? 0;
        
        // Get AR and AP from aging
        $arAging = self::generateARAging(['as_of_date' => $dateAsOf]);
        $apAging = self::generateAPAging(['as_of_date' => $dateAsOf]);
        $totalAR = $arAging['total_outstanding'] ?? 0;
        $totalAP = $apAging['total_outstanding'] ?? 0;
        
        $ratios = [
            'current_ratio' => [
                'label' => 'Current Ratio',
                'formula' => 'Current Assets / Current Liabilities',
                'value' => $currentLiabilities > 0 ? round($currentAssets / $currentLiabilities, 2) : 0,
                'benchmark' => '≥ 1.5',
            ],
            'quick_ratio' => [
                'label' => 'Quick Ratio',
                'formula' => '(Current Assets - Inventory) / Current Liabilities',
                'value' => $currentLiabilities > 0 ? round(($currentAssets) / $currentLiabilities, 2) : 0,
                'benchmark' => '≥ 1.0',
            ],
            'debt_to_equity' => [
                'label' => 'Debt-to-Equity Ratio',
                'formula' => 'Total Liabilities / Total Equity',
                'value' => $totalEquity > 0 ? round($totalLiabilities / $totalEquity, 2) : 0,
                'benchmark' => '≤ 2.0',
            ],
            'gross_margin' => [
                'label' => 'Gross Profit Margin',
                'formula' => 'Gross Profit / Revenue × 100',
                'value' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
                'unit' => '%',
                'benchmark' => 'Industry specific',
            ],
            'net_margin' => [
                'label' => 'Net Profit Margin',
                'formula' => 'Net Profit / Revenue × 100',
                'value' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0,
                'unit' => '%',
                'benchmark' => '> 5%',
            ],
            'roa' => [
                'label' => 'Return on Assets',
                'formula' => 'Net Profit / Total Assets × 100',
                'value' => $totalAssets > 0 ? round(($netProfit / $totalAssets) * 100, 2) : 0,
                'unit' => '%',
            ],
            'roe' => [
                'label' => 'Return on Equity',
                'formula' => 'Net Profit / Total Equity × 100',
                'value' => $totalEquity > 0 ? round(($netProfit / $totalEquity) * 100, 2) : 0,
                'unit' => '%',
            ],
            'receivables_days' => [
                'label' => 'Days Sales Outstanding',
                'formula' => '(AR / Revenue) × 365',
                'value' => $revenue > 0 ? round(($totalAR / $revenue) * 365, 0) : 0,
                'unit' => 'days',
            ],
            'payables_days' => [
                'label' => 'Days Payable Outstanding',
                'formula' => '(AP / Cost of Sales) × 365',
                'value' => ($pl['profit_loss']['cost_of_sales']['amount'] ?? 0) > 0 
                    ? round(($totalAP / $pl['profit_loss']['cost_of_sales']['amount']) * 365, 0) : 0,
                'unit' => 'days',
            ],
        ];
        
        return ['success' => true, 'ratios' => $ratios, 'as_of_date' => $dateAsOf];
    }
    
    // =========================================================================
    // INTEGRITY CHECKER
    // =========================================================================
    
    public static function runIntegrityCheck(array $params = []) {
        $db = getDB();
        
        $fyId = (int)($params['financial_year_id'] ?? 0);
        $dateAsOf = $params['date_as_of'] ?? date('Y-m-d');
        
        if (!$fyId) {
            $activeFY = getActiveFinancialYear($db);
            $fyId = $activeFY ? (int)$activeFY['id'] : 0;
        }
        
        $checks = [];
        
        // 1. Trial Balance Check
        $tb = self::generateTrialBalance(['financial_year_id' => $fyId]);
        $checks['trial_balance'] = [
            'label' => 'Trial Balance',
            'status' => $tb['integrity']['is_balanced'] ? 'pass' : 'fail',
            'message' => $tb['integrity']['message'],
            'details' => $tb['totals'],
        ];
        
        // 2. Balance Sheet Check
        $bs = self::generateBalanceSheet(['financial_year_id' => $fyId, 'date_as_of' => $dateAsOf]);
        $checks['balance_sheet'] = [
            'label' => 'Balance Sheet',
            'status' => $bs['integrity']['is_balanced'] ? 'pass' : 'fail',
            'message' => $bs['integrity']['message'],
        ];
        
        // 3. AR Control Account Reconciliation
        $arAging = self::generateARAging(['as_of_date' => $dateAsOf]);
        $arGLStmt = $db->prepare("
            SELECT COALESCE(SUM(gl.debit - gl.credit), 0) as ar_balance
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            WHERE coa.account_code LIKE '130%' AND gl.financial_year_id = ? AND gl.posting_date <= ?
        ");
        $arGLStmt->execute([$fyId, $dateAsOf]);
        $arGLBalance = (float)$arGLStmt->fetchColumn();
        $arSubledger = $arAging['total_outstanding'];
        $arDiff = abs($arGLBalance - $arSubledger);
        
        $checks['ar_reconciliation'] = [
            'label' => 'AR Control Account',
            'status' => $arDiff < 0.01 ? 'pass' : 'warning',
            'message' => $arDiff < 0.01 ? 'AR reconciled with GL' : "AR subledger differs from GL by " . formatMoney($arDiff),
            'gl_balance' => $arGLBalance,
            'subledger_balance' => $arSubledger,
            'difference' => $arDiff,
        ];
        
        // 4. AP Control Account Reconciliation
        $apAging = self::generateAPAging(['as_of_date' => $dateAsOf]);
        $apGLStmt = $db->prepare("
            SELECT COALESCE(SUM(gl.credit - gl.debit), 0) as ap_balance
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            WHERE coa.account_code LIKE '210%' AND gl.financial_year_id = ? AND gl.posting_date <= ?
        ");
        $apGLStmt->execute([$fyId, $dateAsOf]);
        $apGLBalance = (float)$apGLStmt->fetchColumn();
        $apSubledger = $apAging['total_outstanding'];
        $apDiff = abs($apGLBalance - $apSubledger);
        
        $checks['ap_reconciliation'] = [
            'label' => 'AP Control Account',
            'status' => $apDiff < 0.01 ? 'pass' : 'warning',
            'message' => $apDiff < 0.01 ? 'AP reconciled with GL' : "AP subledger differs from GL by " . formatMoney($apDiff),
            'gl_balance' => $apGLBalance,
            'subledger_balance' => $apSubledger,
            'difference' => $apDiff,
        ];
        
        // 5. Tax Reconciliation
        $taxSum = self::generateTaxSummary(['date_to' => $dateAsOf]);
        $taxGLStmt = $db->prepare("
            SELECT COALESCE(SUM(gl.credit - gl.debit), 0) as tax_payable
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            WHERE coa.account_code LIKE '220%' AND gl.posting_date <= ?
        ");
        $taxGLStmt->execute([$dateAsOf]);
        $taxGLBalance = (float)$taxGLStmt->fetchColumn();
        
        $checks['tax_reconciliation'] = [
            'label' => 'Tax Payable',
            'status' => abs($taxGLBalance - $taxSum['net_payable']) < 0.01 ? 'pass' : 'warning',
            'message' => abs($taxGLBalance - $taxSum['net_payable']) < 0.01 ? 'Tax reconciled with GL' : 'Tax subledger differs from GL',
            'gl_balance' => $taxGLBalance,
            'net_payable' => $taxSum['net_payable'],
            'difference' => abs($taxGLBalance - $taxSum['net_payable']),
        ];

        // 6. Fixed Asset Reconciliation
        $fixedAssetSubledger = self::calculateFixedAssetNBV($db, $dateAsOf);
        $fixedAssetGL = self::calculateFixedAssetGLBalance($db, $fyId, $dateAsOf);
        $fixedAssetDiff = abs($fixedAssetGL - $fixedAssetSubledger);
        $checks['fixed_assets_reconciliation'] = [
            'label' => 'Fixed Assets',
            'status' => $fixedAssetDiff < 0.01 ? 'pass' : 'warning',
            'message' => $fixedAssetDiff < 0.01 ? 'Fixed asset register reconciles with GL' : 'Fixed asset register differs from GL',
            'gl_balance' => $fixedAssetGL,
            'subledger_balance' => $fixedAssetSubledger,
            'difference' => $fixedAssetDiff,
        ];

        // 7. Payroll Reconciliation
        $payrollSubledger = self::calculatePayrollRunLiability($db, $dateAsOf);
        $payrollGL = self::calculatePayrollGLBalance($db, $fyId, $dateAsOf);
        $payrollDiff = abs($payrollGL - $payrollSubledger);
        $checks['payroll_reconciliation'] = [
            'label' => 'Payroll Payable',
            'status' => $payrollDiff < 0.01 ? 'pass' : 'warning',
            'message' => $payrollDiff < 0.01 ? 'Payroll payable reconciles with GL' : 'Payroll payable differs from GL or no payroll payable account is mapped',
            'gl_balance' => $payrollGL,
            'subledger_balance' => $payrollSubledger,
            'difference' => $payrollDiff,
        ];

        // 8. Bank Reconciliation
        $bankGL = self::calculateCashBalance($db, $fyId, $dateAsOf);
        $bankSubledger = self::calculateBankSubledgerBalance($db, $dateAsOf);
        $bankDiff = abs($bankGL - $bankSubledger);
        $checks['bank_reconciliation'] = [
            'label' => 'Bank and Cash',
            'status' => $bankDiff < 0.01 ? 'pass' : 'warning',
            'message' => $bankDiff < 0.01 ? 'Bank subledger reconciles with GL' : 'Bank subledger differs from GL',
            'gl_balance' => $bankGL,
            'subledger_balance' => $bankSubledger,
            'difference' => $bankDiff,
        ];

        // 9. Budget Check
        $budget = self::generateBudgetVsActual(['financial_year_id' => $fyId]);
        $budgetVariance = 0;
        if (($budget['success'] ?? false) && !empty($budget['lines'])) {
            foreach ($budget['lines'] as $line) {
                $budgetVariance += abs((float)$line['variance']);
            }
        }
        $checks['budget_reconciliation'] = [
            'label' => 'Budget vs Actual',
            'status' => ($budget['success'] ?? false) ? 'pass' : 'warning',
            'message' => ($budget['success'] ?? false) ? 'Budget actuals calculated from GL' : ($budget['message'] ?? 'Budget data not available'),
            'absolute_variance' => $budgetVariance,
        ];
        
        // Overall status
        $allPass = true;
        foreach ($checks as $c) {
            if (($c['status'] ?? 'warning') !== 'pass') $allPass = false;
        }
        
        return [
            'success' => true,
            'overall_status' => $allPass ? 'pass' : 'issues_found',
            'checks' => $checks,
            'financial_year_id' => $fyId,
            'as_of_date' => $dateAsOf,
        ];
    }
    
    // =========================================================================
    // HELPER FUNCTIONS
    // =========================================================================
    
    private static function getAccountBreakdown($db, $nature, $fyId, $dateFrom, $dateTo) {
        $stmt = $db->prepare("
            SELECT coa.account_code, coa.account_name, SUM(gl.debit - gl.credit) as balance
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            JOIN account_groups ag ON ag.id = coa.group_id
            WHERE ag.nature = ? AND gl.financial_year_id = ? AND gl.posting_date BETWEEN ? AND ?
            GROUP BY coa.id
            HAVING balance != 0
            ORDER BY coa.account_code
        ");
        $stmt->execute([$nature, $fyId, $dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    private static function getAccountBreakdownAsOf($db, $nature, $fyId, $dateAsOf) {
        $stmt = $db->prepare("
            SELECT coa.account_code, coa.account_name, SUM(gl.debit - gl.credit) as balance
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            JOIN account_groups ag ON ag.id = coa.group_id
            WHERE ag.nature = ? AND gl.financial_year_id = ? AND gl.posting_date <= ?
            GROUP BY coa.id
            HAVING balance != 0
            ORDER BY coa.account_code
        ");
        $stmt->execute([$nature, $fyId, $dateAsOf]);
        return $stmt->fetchAll();
    }

    private static function calculateFixedAssetNBV($db, $dateAsOf): float {
        if (!self::tableExists($db, 'fixed_asset_financials')) {
            return 0;
        }

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(COALESCE(net_book_value, capitalized_cost - accumulated_depreciation - impairment_amount)), 0)
            FROM fixed_asset_financials
            WHERE status IN ('active','impaired') AND capitalization_date <= ?
        ");
        $stmt->execute([$dateAsOf]);
        return (float)$stmt->fetchColumn();
    }

    private static function calculateFixedAssetGLBalance($db, int $fyId, string $dateAsOf): float {
        if (!self::tableExists($db, 'fixed_asset_categories')) {
            return 0;
        }

        $accountIds = $db->query("
            SELECT asset_gl_account_id FROM fixed_asset_categories WHERE status = 'active'
            UNION
            SELECT accumulated_depreciation_gl_account_id FROM fixed_asset_categories WHERE status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);

        $accountIds = array_values(array_unique(array_filter(array_map('intval', $accountIds))));
        if (empty($accountIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(gl.debit - gl.credit), 0)
            FROM general_ledger gl
            WHERE gl.account_id IN ($placeholders)
                AND gl.financial_year_id = ?
                AND gl.posting_date <= ?
        ");
        $stmt->execute(array_merge($accountIds, [$fyId, $dateAsOf]));
        $movement = (float)$stmt->fetchColumn();

        $openingStmt = $db->prepare("
            SELECT COALESCE(SUM(CASE opening_balance_type WHEN 'credit' THEN -opening_balance ELSE opening_balance END), 0)
            FROM chart_of_accounts
            WHERE id IN ($placeholders)
        ");
        $openingStmt->execute($accountIds);
        return $movement + (float)$openingStmt->fetchColumn();
    }

    private static function calculatePayrollRunLiability($db, string $dateAsOf): float {
        if (!self::tableExists($db, 'payroll_runs')) {
            return 0;
        }
        $dateColumn = self::columnExists($db, 'payroll_runs', 'run_date') ? 'run_date' : 'pay_date';
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(net_salary), 0)
            FROM payroll_runs
            WHERE $dateColumn <= ? AND status IN ('Approved','Posted','approved','posted')
        ");
        $stmt->execute([$dateAsOf]);
        return (float)$stmt->fetchColumn();
    }

    private static function calculatePayrollGLBalance($db, int $fyId, string $dateAsOf): float {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(gl.credit - gl.debit), 0)
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            WHERE gl.financial_year_id = ?
                AND gl.posting_date <= ?
                AND (LOWER(coa.account_name) LIKE '%payroll%' OR LOWER(coa.account_name) LIKE '%salary payable%' OR LOWER(coa.account_name) LIKE '%wages payable%')
        ");
        $stmt->execute([$fyId, $dateAsOf]);
        return (float)$stmt->fetchColumn();
    }

    private static function calculateBankSubledgerBalance($db, string $dateAsOf): float {
        if (!self::tableExists($db, 'bank_accounts')) {
            return 0;
        }

        $bankStmt = $db->prepare("
            SELECT COALESCE(SUM(opening_balance), 0)
            FROM bank_accounts
            WHERE status = 'active'
        ");
        $bankStmt->execute();
        $balance = (float)$bankStmt->fetchColumn();

        if (self::tableExists($db, 'bank_transactions')) {
            $txnStmt = $db->prepare("
                SELECT COALESCE(SUM(CASE transaction_type
                    WHEN 'deposit' THEN amount
                    WHEN 'interest' THEN amount
                    ELSE -amount
                END), 0)
                FROM bank_transactions
                WHERE status = 'posted' AND transaction_date <= ?
            ");
            $txnStmt->execute([$dateAsOf]);
            $balance += (float)$txnStmt->fetchColumn();
        }

        return $balance;
    }
    
    private static function calculateRetainedEarnings($db, $fyId, $dateAsOf) {
        // Sum of prior years' profits (simplified)
        $stmt = $db->prepare("SELECT id FROM financial_years WHERE id < ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$fyId]);
        $prevFY = $stmt->fetch();
        
        if (!$prevFY) return 0;
        
        // Calculate profit for previous FY
        $pl = self::generateProfitLoss(['financial_year_id' => $prevFY['id']]);
        return $pl['profit_loss']['net_profit']['amount'] ?? 0;
    }
    
    private static function calculateCurrentYearProfit($db, $fyId, $dateAsOf) {
        $pl = self::generateProfitLoss(['financial_year_id' => $fyId, 'date_to' => $dateAsOf]);
        return $pl['profit_loss']['net_profit']['amount'] ?? 0;
    }
    
    private static function getPreviousFinancialYear($db, $currentFYId) {
        $stmt = $db->prepare("SELECT * FROM financial_years WHERE id < ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$currentFYId]);
        return $stmt->fetch();
    }
    
    private static function calculateCashBalance($db, $fyId, $dateAsOf) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(gl.debit - gl.credit), 0) as cash
            FROM general_ledger gl
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            JOIN account_groups ag ON ag.id = coa.group_id
            WHERE ag.nature = 'asset' 
                AND (coa.account_name LIKE '%cash%' OR coa.account_name LIKE '%bank%')
                AND gl.financial_year_id = ?
                AND gl.posting_date <= ?
        ");
        $stmt->execute([$fyId, $dateAsOf]);
        return (float)$stmt->fetchColumn();
    }
    
    private static function getCashFlowClassifications($db) {
        // Return configured classifications (from settings or default)
        return [
            'operating' => ['accounts' => []],
            'investing' => ['accounts' => []],
            'financing' => ['accounts' => []],
        ];
    }
    
    private static function calculateCashFlowByCategory($db, $category, $fyId, $dateFrom, $dateTo, $classifications) {
        // Simplified - would use classification config
        return ['label' => ucfirst($category) . ' Activities', 'total' => 0, 'items' => []];
    }
    
    // =========================================================================
    // REPORT AUDIT LOG
    // =========================================================================
    
    public static function logReportGeneration($reportType, $params, $userId, $action = 'generated', $exportFormat = null, $fileName = null) {
        $db = getDB();

        if (!self::tableExists($db, 'report_audit_log')) {
            return 0;
        }

        $parameters = json_encode($params);
        $userId = $userId ?: null;

        if (self::columnExists($db, 'report_audit_log', 'action')) {
            $stmt = $db->prepare("
                INSERT INTO report_audit_log (report_type, action, parameters, generated_by, generated_at, export_format, file_name, ip_address)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            $stmt->execute([
                $reportType,
                in_array($action, ['generated', 'exported'], true) ? $action : 'generated',
                $parameters,
                $userId,
                $exportFormat,
                $fileName,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO report_audit_log (report_type, parameters, generated_by, generated_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$reportType, $parameters, $userId]);
        }
        
        return (int)$db->lastInsertId();
    }

    public static function logReportExport($reportType, $params, $userId, $exportFormat, $fileName = null) {
        return self::logReportGeneration($reportType, $params, $userId, 'exported', $exportFormat, $fileName);
    }
}
