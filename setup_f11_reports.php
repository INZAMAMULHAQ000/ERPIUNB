<?php
/**
 * F11 - Financial Reports Setup / Upgrader
 *
 * This script is idempotent. It only creates reporting metadata structures,
 * permissions, menu links, templates, periods, and cash-flow classifications.
 * It does not create or maintain duplicate financial balances.
 */
require_once __DIR__ . '/config/database.php';

echo "=== F11 Financial Reports Setup ===\n\n";

function f11TableExists(PDO $db, string $table): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }
    $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($table));
    return (bool)$stmt->fetchColumn();
}

function f11ColumnExists(PDO $db, string $table, string $column): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($column));
    return (bool)$stmt->fetchColumn();
}

function f11AddColumnIfMissing(PDO $db, string $table, string $column, string $definition): void {
    if (!f11ColumnExists($db, $table, $column)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

try {
    $db = getDB();
    echo "✓ Connected to database\n\n";

    // 1. Reporting metadata tables
    echo "1. Creating/upgrading F11 metadata tables...\n";

    $db->exec("CREATE TABLE IF NOT EXISTS accounting_periods (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        financial_year_id INT UNSIGNED NOT NULL,
        period_name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_accounting_period (financial_year_id, start_date, end_date),
        INDEX idx_accounting_period_fy (financial_year_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS report_audit_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        report_type VARCHAR(100) NOT NULL,
        action ENUM('generated','exported') NOT NULL DEFAULT 'generated',
        parameters JSON NULL,
        generated_by INT UNSIGNED NULL,
        generated_at DATETIME NOT NULL,
        export_format VARCHAR(20) NULL,
        file_name VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        INDEX idx_report_type (report_type),
        INDEX idx_generated_at (generated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    f11AddColumnIfMissing($db, 'report_audit_log', 'action', "ENUM('generated','exported') NOT NULL DEFAULT 'generated' AFTER report_type");
    f11AddColumnIfMissing($db, 'report_audit_log', 'export_format', "VARCHAR(20) NULL AFTER generated_at");
    f11AddColumnIfMissing($db, 'report_audit_log', 'file_name', "VARCHAR(255) NULL AFTER export_format");
    f11AddColumnIfMissing($db, 'report_audit_log', 'ip_address', "VARCHAR(45) NULL AFTER file_name");

    $db->exec("CREATE TABLE IF NOT EXISTS cash_flow_classifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_id INT UNSIGNED NOT NULL,
        classification ENUM('operating', 'investing', 'financing', 'non_cash') NOT NULL DEFAULT 'operating',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_cash_flow_account (account_id),
        INDEX idx_cash_flow_classification (classification)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    f11AddColumnIfMissing($db, 'cash_flow_classifications', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1 AFTER classification");
    f11AddColumnIfMissing($db, 'cash_flow_classifications', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

    $db->exec("CREATE TABLE IF NOT EXISTS report_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(150) NOT NULL,
        report_type VARCHAR(100) NOT NULL,
        template_config JSON NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_report_template (template_name, report_type),
        INDEX idx_report_templates_type (report_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!f11TableExists($db, 'saved_reports')) {
        $db->exec("CREATE TABLE saved_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            report_type VARCHAR(100) NOT NULL,
            filters JSON NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_saved_reports_type (report_type),
            INDEX idx_saved_reports_user (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    echo "   ✓ Metadata tables ready\n";

    // 2. Seed accounting periods from financial years
    echo "2. Seeding accounting periods...\n";
    $periodInsert = $db->prepare("INSERT IGNORE INTO accounting_periods (financial_year_id, period_name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
    $financialYears = $db->query("SELECT id, year_name, start_date, end_date, status FROM financial_years ORDER BY start_date")->fetchAll(PDO::FETCH_ASSOC);
    $periodCount = 0;
    foreach ($financialYears as $fy) {
        $start = new DateTime($fy['start_date']);
        $end = new DateTime($fy['end_date']);
        $cursor = new DateTime($start->format('Y-m-01'));
        while ($cursor <= $end) {
            $periodStart = clone $cursor;
            if ($periodStart < $start) {
                $periodStart = clone $start;
            }
            $periodEnd = (clone $cursor)->modify('last day of this month');
            if ($periodEnd > $end) {
                $periodEnd = clone $end;
            }
            $periodName = $cursor->format('M Y');
            $status = ($fy['status'] === 'closed') ? 'closed' : 'open';
            $periodInsert->execute([
                $fy['id'],
                $periodName,
                $periodStart->format('Y-m-d'),
                $periodEnd->format('Y-m-d'),
                $status,
            ]);
            $periodCount += $periodInsert->rowCount();
            $cursor->modify('first day of next month');
        }
    }
    echo "   ✓ Accounting periods seeded/verified ({$periodCount} new)\n";

    // 3. Add F11 permissions using the RBAC module.name convention
    echo "3. Adding F11 report permissions...\n";
    $permissions = [
        ['view', 'report'],
        ['export', 'report'],
        ['financial', 'report'],
        ['management', 'report'],
        ['audit', 'report'],
        ['dashboard.view', 'report'],
        ['trial_balance.view', 'report'],
        ['trial_balance.export', 'report'],
        ['profit_loss.view', 'report'],
        ['profit_loss.export', 'report'],
        ['balance_sheet.view', 'report'],
        ['balance_sheet.export', 'report'],
        ['cash_flow.view', 'report'],
        ['cash_flow.export', 'report'],
        ['general_ledger.view', 'report'],
        ['general_ledger.export', 'report'],
        ['account_ledger.view', 'report'],
        ['account_ledger.export', 'report'],
        ['ar.view', 'report'],
        ['ap.view', 'report'],
        ['tax.view', 'report'],
        ['assets.view', 'report'],
        ['expenses.view', 'report'],
        ['budget.view', 'report'],
        ['payroll.view', 'report'],
        ['ratios.view', 'report'],
        ['integrity.view', 'report'],
    ];

    $permStmt = $db->prepare("INSERT IGNORE INTO permissions (name, module) VALUES (?, ?)");
    foreach ($permissions as $permission) {
        $permStmt->execute($permission);
    }
    echo "   ✓ Permissions inserted/verified\n";

    echo "4. Granting F11 permissions to admin role...\n";
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 1, id FROM permissions WHERE module = 'report'");
    echo "   ✓ Admin role permissions verified\n";

    // 5. Add/update sidebar menu entries
    echo "5. Adding/updating sidebar menu entries...\n";
    $reportsMenuId = 400;
    $db->prepare("UPDATE sidebar_menu SET route = ? WHERE id = ?")->execute(['#financial-reports', $reportsMenuId]);

    $menuEntries = [
        [$reportsMenuId, null, 'Finance', 5, 'Financial Reports', 'fas fa-chart-pie', '#financial-reports', 'report.dashboard.view', 20, 1, 0],
        [$reportsMenuId + 1, $reportsMenuId, 'Finance', 5, 'Dashboard', 'fas fa-tachometer-alt', 'modules/finance/reports/dashboard/index.php', 'report.dashboard.view', 1, 1, 1],
        [$reportsMenuId + 2, $reportsMenuId, 'Finance', 5, 'Trial Balance', 'fas fa-balance-scale', 'modules/finance/reports/trial_balance/index.php', 'report.trial_balance.view', 2, 1, 1],
        [$reportsMenuId + 3, $reportsMenuId, 'Finance', 5, 'Profit & Loss', 'fas fa-chart-line', 'modules/finance/reports/profit_loss/index.php', 'report.profit_loss.view', 3, 1, 1],
        [$reportsMenuId + 4, $reportsMenuId, 'Finance', 5, 'Balance Sheet', 'fas fa-landmark', 'modules/finance/reports/balance_sheet/index.php', 'report.balance_sheet.view', 4, 1, 1],
        [$reportsMenuId + 5, $reportsMenuId, 'Finance', 5, 'Cash Flow', 'fas fa-money-bill-wave', 'modules/finance/reports/cash_flow/index.php', 'report.cash_flow.view', 5, 1, 1],
        [$reportsMenuId + 6, $reportsMenuId, 'Finance', 5, 'General Ledger', 'fas fa-book', 'modules/finance/reports/general_ledger/index.php', 'report.general_ledger.view', 6, 1, 1],
        [$reportsMenuId + 7, $reportsMenuId, 'Finance', 5, 'Account Ledger', 'fas fa-list-alt', 'modules/finance/reports/account_ledger/index.php', 'report.account_ledger.view', 7, 1, 1],
        [$reportsMenuId + 10, $reportsMenuId, 'Finance', 5, 'AR Reports', 'fas fa-users', 'modules/finance/reports/ar/index.php', 'report.ar.view', 10, 1, 1],
        [$reportsMenuId + 11, $reportsMenuId, 'Finance', 5, 'AP Reports', 'fas fa-file-invoice-dollar', 'modules/finance/reports/ap/index.php', 'report.ap.view', 11, 1, 1],
        [$reportsMenuId + 12, $reportsMenuId, 'Finance', 5, 'Tax Reports', 'fas fa-file-invoice', 'modules/finance/reports/tax/index.php', 'report.tax.view', 12, 1, 1],
        [$reportsMenuId + 13, $reportsMenuId, 'Finance', 5, 'Fixed Asset Reports', 'fas fa-building', 'modules/finance/reports/assets/index.php', 'report.assets.view', 13, 1, 1],
        [$reportsMenuId + 20, $reportsMenuId, 'Finance', 5, 'Expense Reports', 'fas fa-receipt', 'modules/finance/reports/expenses/index.php', 'report.expenses.view', 20, 1, 1],
        [$reportsMenuId + 21, $reportsMenuId, 'Finance', 5, 'Budget Reports', 'fas fa-piggy-bank', 'modules/finance/reports/budget/index.php', 'report.budget.view', 21, 1, 1],
        [$reportsMenuId + 22, $reportsMenuId, 'Finance', 5, 'Payroll Reports', 'fas fa-users-cog', 'modules/finance/reports/payroll/index.php', 'report.payroll.view', 22, 1, 1],
        [$reportsMenuId + 23, $reportsMenuId, 'Finance', 5, 'Financial Ratios', 'fas fa-percentage', 'modules/finance/reports/ratios/index.php', 'report.ratios.view', 23, 1, 1],
        [$reportsMenuId + 30, $reportsMenuId, 'Finance', 5, 'Export Reports', 'fas fa-download', 'modules/finance/reports/exports/index.php', 'report.export', 30, 1, 1],
        [$reportsMenuId + 31, $reportsMenuId, 'Finance', 5, 'Integrity Checker', 'fas fa-check-double', 'modules/finance/reports/integrity/index.php', 'report.integrity.view', 31, 1, 1],
    ];

    $menuStmt = $db->prepare("
        INSERT INTO sidebar_menu (id, parent_id, section, section_order, label, icon, route, permission, sort_order, is_active, is_indented)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            parent_id = VALUES(parent_id),
            section = VALUES(section),
            section_order = VALUES(section_order),
            label = VALUES(label),
            icon = VALUES(icon),
            route = VALUES(route),
            permission = VALUES(permission),
            sort_order = VALUES(sort_order),
            is_active = VALUES(is_active),
            is_indented = VALUES(is_indented)
    ");
    foreach ($menuEntries as $entry) {
        $menuStmt->execute($entry);
    }
    echo "   ✓ Sidebar menu entries verified\n";

    // 6. Default cash-flow classifications
    echo "6. Seeding default cash-flow classifications...\n";
    $db->exec("
        INSERT IGNORE INTO cash_flow_classifications (account_id, classification, is_active)
        SELECT coa.id,
            CASE
                WHEN LOWER(coa.account_name) LIKE '%cash%' OR LOWER(coa.account_name) LIKE '%bank%' THEN 'non_cash'
                WHEN LOWER(ag.group_name) LIKE '%fixed%' OR LOWER(ag.group_name) LIKE '%building%' OR LOWER(ag.group_name) LIKE '%machinery%' OR LOWER(ag.group_name) LIKE '%vehicle%' OR LOWER(ag.group_name) LIKE '%furniture%' THEN 'investing'
                WHEN ag.nature IN ('equity') OR LOWER(ag.group_name) LIKE '%loan%' OR LOWER(coa.account_name) LIKE '%loan%' OR LOWER(coa.account_name) LIKE '%capital%' THEN 'financing'
                ELSE 'operating'
            END,
            1
        FROM chart_of_accounts coa
        JOIN account_groups ag ON ag.id = coa.group_id
        WHERE coa.status = 'active'
    ");
    echo "   ✓ Default classifications seeded/verified\n";

    // 7. Default report templates
    echo "7. Seeding default report templates...\n";
    $templateStmt = $db->prepare("INSERT IGNORE INTO report_templates (template_name, report_type, template_config, is_default) VALUES (?, ?, ?, ?)");
    $templates = [
        ['Standard Trial Balance', 'trial_balance', '{"show_opening": true, "show_closing": true, "group_by_nature": true}', 1],
        ['Standard Profit & Loss', 'profit_loss', '{"show_margins": true, "show_comparative": false}', 1],
        ['Standard Balance Sheet', 'balance_sheet', '{"show_subtotals": true, "show_integrity": true}', 1],
        ['Standard Cash Flow', 'cash_flow', '{"method": "indirect", "use_configured_classifications": true}', 1],
        ['Standard General Ledger', 'general_ledger', '{"show_running_balance": true, "show_reference": true}', 1],
    ];
    foreach ($templates as $template) {
        $templateStmt->execute($template);
    }
    echo "   ✓ Templates verified\n";

    echo "\n=== F11 Setup Complete ===\n";
    echo "Access: Finance > Financial Reports\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
