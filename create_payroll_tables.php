<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

// Array of table creation SQL statements
$tables = [
    "
    CREATE TABLE IF NOT EXISTS payroll_finance_mappings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payroll_component VARCHAR(100) NOT NULL,
        chart_account_id INT UNSIGNED NOT NULL,
        mapping_type ENUM('expense', 'liability', 'asset') NOT NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_payroll_component (payroll_component),
        INDEX idx_chart_account (chart_account_id),
        INDEX idx_status (status),
        FOREIGN KEY (chart_account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "
    CREATE TABLE IF NOT EXISTS payroll_finance_postings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT UNSIGNED NOT NULL,
        journal_entry_id INT UNSIGNED,
        posting_date DATE NOT NULL,
        total_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_net DECIMAL(15,2) NOT NULL DEFAULT 0,
        status ENUM('Draft', 'Posted', 'Reversed') DEFAULT 'Draft',
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payroll_run (payroll_run_id),
        INDEX idx_journal_entry (journal_entry_id),
        INDEX idx_posting_date (posting_date),
        INDEX idx_status (status),
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "
    CREATE TABLE IF NOT EXISTS payroll_payment_batches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT UNSIGNED NOT NULL,
        bank_account_id INT UNSIGNED NOT NULL,
        payment_date DATE NOT NULL,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        reference_number VARCHAR(100),
        status ENUM('Pending', 'Approved', 'Paid', 'Failed') DEFAULT 'Pending',
        journal_entry_id INT UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payroll_run (payroll_run_id),
        INDEX idx_bank_account (bank_account_id),
        INDEX idx_payment_date (payment_date),
        INDEX idx_status (status),
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT,
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "
    CREATE TABLE IF NOT EXISTS payroll_reconciliation (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT UNSIGNED NOT NULL,
        payroll_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        posted_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        difference DECIMAL(15,2) NOT NULL DEFAULT 0,
        status ENUM('Reconciled', 'Unreconciled', 'Investigation') DEFAULT 'Unreconciled',
        checked_by INT UNSIGNED,
        checked_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_payroll_run (payroll_run_id),
        INDEX idx_status (status),
        FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

$tableNames = [
    'payroll_finance_mappings',
    'payroll_finance_postings', 
    'payroll_payment_batches',
    'payroll_reconciliation'
];

// Create each table
foreach ($tables as $index => $sql) {
    try {
        $db->exec($sql);
        echo "Table ${[$index]} created successfully\n";
    } catch (Exception $e) {
        echo "Error creating ${[$index]}: " . $e->getMessage() . "\n";
    }
}

echo "All payroll finance tables created!\n";
?>
