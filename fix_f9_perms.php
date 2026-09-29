<?php
require_once 'config/database.php';

echo "=== Fixing F9 Permissions ===\n\n";

try {
    $pdo = getDB();
    
    // Check current role_permissions for fixedasset
    $stmt = $pdo->query("
        SELECT rp.*, p.name 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE p.module = 'fixed_assets'
    ");
    $perms = $stmt->fetchAll();
    echo "Current fixedasset permissions:\n";
    foreach ($perms as $p) {
        echo "  Role {$p['role_id']}: {$p['name']}\n";
    }
    
    // Get super_admin role_id
    $stmt = $pdo->query("SELECT id FROM roles WHERE role_name = 'super_admin'");
    $adminRole = $stmt->fetch();
    $adminRoleId = $adminRole ? $adminRole['id'] : 1;
    echo "\nSuper admin role_id: $adminRoleId\n";
    
    // Get all fixedasset permissions
    $stmt = $pdo->query("SELECT id FROM permissions WHERE module = 'fixed_assets'");
    $permIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Fixed asset permission IDs: " . implode(', ', $permIds) . "\n";
    
    // Assign all to super_admin
    foreach ($permIds as $pid) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        $stmt->execute([$adminRoleId, $pid]);
    }
    echo "Assigned all fixedasset permissions to super_admin\n";
    
    // Also assign to admin role (if exists)
    $stmt = $pdo->query("SELECT id FROM roles WHERE role_name = 'admin'");
    $adminRole2 = $stmt->fetch();
    if ($adminRole2) {
        foreach ($permIds as $pid) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$adminRole2['id'], $pid]);
        }
        echo "Assigned all fixedasset permissions to admin\n";
    }
    
    // Verify
    $stmt = $pdo->query("
        SELECT rp.*, p.name 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE p.module = 'fixed_assets'
    ");
    $perms = $stmt->fetchAll();
    echo "\nUpdated fixedasset permissions:\n";
    foreach ($perms as $p) {
        echo "  Role {$p['role_id']}: {$p['name']}\n";
    }
    
    echo "\n=== Permissions Fixed ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}