<?php
require_once 'config/database.php';

echo "=== Creating user_roles table ===\n\n";

try {
    $pdo = getDB();
    
    // Create user_roles table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        role_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_role (user_id, role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "������ user_roles table created\n";
    
    // Assign super_admin role (role_id = 1) to user 1 (Super Admin)
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $stmt->execute([1, 1]);
    echo "������ Assigned super_admin role to user 1\n";
    
    // Also assign to user 2 if needed
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $stmt->execute([2, 1]);
    echo "������ Assigned super_admin role to user 2\n";
    
    // Verify
    $stmt = $pdo->query("SELECT ur.*, u.name as user_name, r.role_name FROM user_roles ur JOIN users u ON ur.user_id = u.id JOIN roles r ON ur.role_id = r.id");
    $results = $stmt->fetchAll();
    echo "\nUser roles:\n";
    foreach ($results as $r) {
        echo "  User {$r['user_id']} ({$r['user_name']}) -> Role {$r['role_id']} ({$r['role_name']})\n";
    }
    
    echo "\n=== user_roles table created and populated ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}