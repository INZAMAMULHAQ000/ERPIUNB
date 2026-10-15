<?php
/**
 * People directory access for Admins.
 *
 * `people_management.*` was created for Super Admin only, while
 * `employees.create`, `customers.create` and `contractors.create` are all held
 * by Admin. Since People Management is now the entry point that hands work to
 * those three modules, an Admin could complete every step of an onboarding but
 * not open the screen that starts it.
 *
 * Grants view / create / edit to Admin. `people_management.delete` is NOT
 * granted: removal from the directory is deliberately kept to Super Admin, and
 * deleting a person cascades to their role rows.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_15_people_directory_admin_access.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();

echo "== People directory admin access ==\n";

$roleStmt = $db->prepare("SELECT id FROM roles WHERE name = ? AND deleted_at IS NULL LIMIT 1");

$granted = 0;
$already = 0;

foreach (['Admin' => ['view', 'create', 'edit'], 'Super Admin' => ['view', 'create', 'edit', 'delete']] as $roleName => $names) {
    $roleStmt->execute([$roleName]);
    $roleId = (int) $roleStmt->fetchColumn();
    if (!$roleId) {
        echo "  ! role '$roleName' not found — skipped\n";
        continue;
    }

    foreach ($names as $name) {
        $permStmt = $db->prepare("SELECT id FROM permissions WHERE module = 'people_management' AND name = ? LIMIT 1");
        $permStmt->execute([$name]);
        $permId = (int) $permStmt->fetchColumn();
        if (!$permId) {
            echo "  ! permission people_management.$name not found — skipped\n";
            continue;
        }

        $linkStmt = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $linkStmt->execute([$roleId, $permId]);
        if ((int) $linkStmt->fetchColumn() > 0) {
            $already++;
            continue;
        }

        $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$roleId, $permId]);
        echo "  grant $roleName <- people_management.$name\n";
        $granted++;
    }
}

echo "\nDone. $granted grant(s) added, $already already present.\n";
