<?php
/**
 * Create ai_workflows table
 */
require_once __DIR__ . '/config/database.php';

$db = getDB();
try {
    $db->exec("CREATE TABLE IF NOT EXISTS ai_workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_key VARCHAR(100) NOT NULL UNIQUE,
        company_id INT DEFAULT NULL,
        module VARCHAR(50) DEFAULT 'ai',
        name VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('draft', 'testing', 'approved', 'published', 'enabled', 'disabled', 'archived') NOT NULL DEFAULT 'draft',
        safety_classification ENUM('low', 'medium', 'high', 'restricted') NOT NULL DEFAULT 'low',
        trigger_type ENUM('manual', 'scheduled', 'event') NOT NULL DEFAULT 'manual',
        current_version_number INT NOT NULL DEFAULT 1,
        current_version_id INT DEFAULT NULL,
        created_by INT NOT NULL,
        approved_by INT DEFAULT NULL,
        last_run_at DATETIME DEFAULT NULL,
        enabled_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ai_workflows_status (status),
        INDEX idx_ai_workflows_company (company_id),
        INDEX idx_ai_workflows_module (module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "ai_workflows table created successfully\n";

    // Verify
    $exists = $db->query("SHOW TABLES LIKE 'ai_workflows'")->rowCount() > 0;
    echo "Verification: " . ($exists ? "PASS" : "FAIL") . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
