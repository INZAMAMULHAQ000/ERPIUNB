<?php
/**
 * Edit Payroll Deduction Type
 * 
 * @package XXX
 * @submodule Finance.Payroll.Deductions.Types
 */

// Check permission
if (!hasPermission('payrollfinance.configure')) {
    redirect_to('no-access.php');
}

// Get deduction type ID from URL
$ = isset($['id']) ? (int)$['id'] : 0;
if ($ <= 0) {
    redirect_to('index.php');
}

// Handle form submission
if ($['REQUEST_METHOD'] === 'POST') {
    // Process form data and update deduction type
    // This would validate input, update deduction type record, then redirect
    header('Location: view.php?id=' . $ . '&success=1');
    exit;
}

// Fetch deduction type data
$ = getPayrollDeductionTypeById($, $);
if (!$) {
    redirect_to('index.php');
}

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>
