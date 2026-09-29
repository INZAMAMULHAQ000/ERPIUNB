<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

// Create mappings table
$sql = "
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$db->exec($sql);
echo "Mappings table created\n";
?>
