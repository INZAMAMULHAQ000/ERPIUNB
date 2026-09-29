<?php
/**
 * View Payroll Deduction Type Details
 * 
 * @package XXX
 * @submodule Finance.Payroll.Deductions.Types
 */

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get deduction type ID from URL
$ = isset($['id']) ? (int)$['id'] : 0;
if ($ <= 0) {
    redirect_to('index.php');
}

// Fetch deduction type data
$ = getPayrollDeductionTypeById($, $);
if (!$) {
    redirect_to('index.php');
}

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>
