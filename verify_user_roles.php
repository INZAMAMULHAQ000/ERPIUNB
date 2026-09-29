<?php
require_once 'config/database.php';

try {
    $pdo = getDB();
    
    // Verify user_roles
    $stmt = $pdo->query("SELECT ur.*, u.name as user_name, r.id as role_id FROM user_roles ur JOIN users u ON ur.user_id = u.id JOIN roles r ON ur.role_id = r.id");
    $results = $stmt->fetchAll();
    echo "User roles:\n";
    foreach ($results as $r) {
        echo "  User {$r['user_id']} ({$r['user_name']}) -> Role {$r['role_id']}\n";
    }
    
    // Test hasPermission logic
    $userId = 1;
    $stmt = $pdo->prepare("
        SELECT CONCAT(p.module, '.', p.name) as perm FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $perms = array_column($stmt->fetchAll(), 'perm');
    echo "\nPermissions for user 1:\n";
    foreach ($perms as $p) {
        echo "  $p\n";
    }
    
    echo "\n=== Verification Complete ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}