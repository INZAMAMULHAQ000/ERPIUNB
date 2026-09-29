<?php
// modules/payroll/config.php

// Configuration for the Payroll‑Finance Integration module.
// Adjust DB connection details (PDO) and permission checks as needed.

return [
    // Database connection settings (adjust as per your env)
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=erp;charset=utf8mb4',
        'user' => 'root',
        'password' => '',
    ],

    // Permission map – adjust to your auth implementation
    'permissions' => [
        'payrollfinance.view'      => 'view',
        'payrollfinance.configure' => 'edit',
        'payrollfinance.post'      => 'post',
        'payrollfinance.pay'       => 'pay',
        'payrollfinance.reconcile' => 'reconcile',
        'payrollfinance.report'   => 'report',
    ],

    // Endpoints – route names used by the dispatcher
    'routes' => [
        'mappings.index'   => '/payroll/finance/mappings',
        'mappings.create'  => '/payroll/finance/mappings/create',
        'mappings.store'   => '/payroll/finance/mappings/store',
        'mappings.edit'   => '/payroll/finance/mappings/edit',
        'mappings.update'  => '/payroll/finance/mappings/update',
        'mappings.delete' => '/payroll/finance/mappings/delete',
        'postings.index'   => '/payroll/finance/postings',
        'postings.store'   => '/payroll/finance/postings/store',
        'postings.post'    => '/payroll/finance/postings/post',
        'batches.index'    => '/payroll/finance/batches',
        'batches.store'    => '/payroll/finance/batches/store',
        'reconciliation'   => '/payroll/finance/reconciliation',
        'dashboard'        => '/payroll/finance/dashboard',
    ],
];
