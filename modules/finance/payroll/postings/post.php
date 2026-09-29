<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/**
 * Payroll Posting
 * 
 * @package XXX
 * @submodule Finance.Payroll.Postings
 */

require_once __DIR__ . '/../../../../helpers/rbac.php';

// Check permission
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';
require_once __DIR__ . '/../../../posting_engine/PostingEngine.php';

if (!hasPermission('payrollfinance.post')) {
    redirect_to('no-access.php');
}


// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect_to('index.php');
}

// Verify CSRF token
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token.');
    redirect_to('index.php');
}

// Get database connection
$db = getDB();

// Get payroll run ID from POST
$payrollRunId = (int)($_POST['payroll_run_id'] ?? 0);
if ($payrollRunId <= 0) {
    flash('error', 'Invalid payroll run ID.');
    redirect_to('index.php');
}

// Fetch payroll run details
$stmt = $db->prepare("
    SELECT id, status, pay_date, description, basic_salary, allowances, overtime, employer_contributions, employee_deductions, net_salary, gross_salary, payroll_period_start, payroll_period_end FROM payroll_runs WHERE id = ?
");
$stmt->execute([$payrollRunId]);
$payrollRun = $stmt->fetch();

if (!$payrollRun) {
    flash('error', 'Payroll run not found.');
    redirect_to('index.php');
}

// Validate payroll run status
$allowedStatuses = ['Approved']; // Only approved payroll runs can be posted
if (!in_array($payrollRun['status'], $allowedStatuses)) {
    flash('error', 'Only approved payroll runs can be posted. Current status: ' . htmlspecialchars($payrollRun['status']));
    redirect_to('index.php');
}

// Check if already posted
if ($payrollRun['status'] === 'Posted') {
    flash('error', 'This payroll run has already been posted.');
    redirect_to('index.php');
}

// Begin transaction
try {
    $db->beginTransaction();

    // Get active financial year
    $fy = getActiveFinancialYear($db);
    if (!$fy) {
        throw new Exception('No active financial year found.');
    }

    // Validate posting date (using payroll run's pay date or current date?)
    $postDate = $payrollRun['pay_date'] ?? date('Y-m-d');
    if ($postDate < $fy['start_date'] || $postDate > $fy['end_date']) {
        throw new Exception("Posting date $postDate is outside active financial year ({$fy['start_date']} to {$fy['end_date']}).");
    }


// Validate that the entire payroll period falls within the active financial year (accounting period check)
    $payrollPeriodStart = $payrollRun['payroll_period_start'];
    $payrollPeriodEnd = $payrollRun['payroll_period_end'];
    if ($payrollPeriodStart && $payrollPeriodEnd) {
        if ($payrollPeriodStart < $fy['start_date'] || $payrollPeriodEnd > $fy['end_date']) {
            throw new Exception(""Payroll period ($payrollPeriodStart to $payrollPeriodEnd) is outside active financial year ({$fy['start_date']} to {$fy['end_date']})."");
        }
    } elseif ($payrollPeriodStart) {
        // Only start date is set
        if ($payrollPeriodStart < $fy['start_date'] || $payrollPeriodStart > $fy['end_date']) {
            throw new Exception(""Payroll period start date $payrollPeriodStart is outside active financial year ({$fy['start_date']} to {$fy['end_date']})."");
        }
    } elseif ($payrollPeriodEnd) {
        // Only end date is set
        if ($payrollPeriodEnd < $fy['start_date'] || $payrollPeriodEnd > $fy['end_date']) {
            throw new Exception(""Payroll period end date $payrollPeriodEnd is outside active financial year ({$fy['start_date']} to {$fy['end_date']})."");
        }
    

    // Get voucher type for payroll journal
    $stmt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Payroll Journal' LIMIT 1");
    $stmt->execute();
    $voucherType = $stmt->fetch();
    if (!$voucherType) {
        // Create Payroll Journal voucher type on the fly if not exists
        $stmt = $db->prepare("INSERT INTO voucher_types (voucher_name, voucher_prefix) VALUES ('Payroll Journal', 'PAY')");
        $stmt->execute();
        $voucherTypeId = (int)$db->lastInsertId();
        $voucherPrefix = 'PAY';
    } else {
        $voucherTypeId = (int)$voucherType['id'];
        $voucherPrefix = $voucherType['voucher_prefix'];
    }

    // Generate voucher number
    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    // Create journal entry (draft)
    $stmt = $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $voucherNo, $voucherTypeId, $postDate,
        'Payroll Run #' . $payrollRunId . ' - ' . ($payrollRun['description'] ?? ''),
        $_SESSION['user_id']
    ]);
    $journalId = (int)$db->lastInsertId();

    // Prepare journal line details
    $detailStmt = $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    // Define payroll components to process
    // We assume the payroll run table has these columns
    $components = [
        ['name' => 'Basic Salary', 'amount' => (float)($payrollRun['basic_salary'] ?? 0), 'mappingType' => 'expense'],
        ['name' => 'Allowances', 'amount' => (float)($payrollRun['allowances'] ?? 0), 'mappingType' => 'expense'],
        ['name' => 'Overtime', 'amount' => (float)($payrollRun['overtime'] ?? 0), 'mappingType' => 'expense'],
        ['name' => 'Employer Contributions', 'amount' => (float)($payrollRun['employer_contributions'] ?? 0), 'mappingType' => 'expense'],
        ['name' => 'Employee Deductions', 'amount' => (float)($payrollRun['employee_deductions'] ?? 0), 'mappingType' => 'liability'],
        ['name' => 'Employee Salary Payable', 'amount' => (float)($payrollRun['net_salary'] ?? 0), 'mappingType' => 'liability'],
    ];

    $hasError = false;
    foreach ($components as $component) {
        $amount = $component['amount'];
        if ($amount <= 0) {
            // Skip zero amounts
            continue;
        }

        // Get mapped GL account for this component
        $stmt = $db->prepare("
            SELECT m.chart_account_id, m.mapping_type
            FROM payroll_finance_mappings m
            WHERE m.payroll_component = ? AND m.status = 'active'
        ");
        $stmt->execute([$component['name']]);
        $mapping = $stmt->fetch();

        if (!$mapping) {
            flash('error', 'No active GL mapping found for payroll component: ' . htmlspecialchars($component['name']));
            $hasError = true;
            break;
        }

        $glAccountId = (int)$mapping['chart_account_id'];
        $mappingType = $mapping['mapping_type'];

        // Determine debit and credit based on mapping type
        // expense and asset accounts are debited to increase
        // liability accounts are credited to increase
        if (in_array($mappingType, ['expense', 'asset'])) {
            $debit = $amount;
            $credit = 0.0;
        } elseif ($mappingType === 'liability') {
            $debit = 0.0;
            $credit = $amount;
        } else {
            // Default to debit for unknown types (should not happen)
            $debit = $amount;
            $credit = 0.0;
        }

        // Insert journal line
        $detailStmt->execute([
            $journalId, $glAccountId, $debit, $credit,
            'Payroll Component: ' . htmlspecialchars($component['name'])
        ]);
    }

    if ($hasError) {
        throw new Exception('Missing GL mapping for one or more payroll components.');
    }

    // Post the journal entry using the Posting Engine
    $result = PostingEngine::post($journalId, $_SESSION['user_id']);
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Update payroll run status to posted
    $stmt = $db->prepare("
        UPDATE payroll_runs SET status = 'Posted', updated_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$payrollRunId]);

    // Create record in payroll_finance_postings table
    $stmt = $db->prepare("
        INSERT INTO payroll_finance_postings (
            payroll_run_id, journal_entry_id, posting_date, total_gross, employee_deductions, employer_contributions, total_net, payroll_period_start, payroll_period_end, status,
            created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?, NOW())
    ");
    $stmt->execute([
        $payrollRunId,
        $journalId,
        $postDate,
        (float)($payrollRun['gross_salary'] ?? 0),
        (float)($payrollRun['employee_deductions'] ?? 0),
        (float)($payrollRun['employer_contributions'] ?? 0),
        (float)($payrollRun['net_salary'] ?? 0),
        $payrollRun['payroll_period_start'] ?? null,
        $payrollRun['payroll_period_end'] ?? null,
        $_SESSION['user_id'],
    ]);
    // Audit log
    logAudit($_SESSION['user_id'], 'Posted payroll run: ' . $payrollRunId . ' (Voucher: ' . $voucherNo . ')', 'finance');

    flash('success', 'Payroll run posted successfully. Voucher #' . $voucherNo);
    redirect_to('view.php?id=' . $payrollRunId);

} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to post payroll run: ' . $e->getMessage());
    redirect_to('index.php');
}
