<?php
/**
 * Complete F9 Database Setup - Fixed version
 */
require_once 'config/database.php';

echo "=== F9 Fixed Assets Database Setup ===\n\n";

try {
    $pdo = getDB();
    echo "��� Connected to database\n\n";
    
    // 1. First, ensure all referenced tables exist
    echo "1. Creating/verifying referenced tables...\n";
    
    // depreciation_methods
    $pdo->exec("CREATE TABLE IF NOT EXISTS depreciation_methods (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        method_name VARCHAR(100) NOT NULL,
        method_code VARCHAR(10) NOT NULL UNIQUE,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("INSERT IGNORE INTO depreciation_methods (id, method_name, method_code, description, status) VALUES
        (1, 'Straight Line', 'SL', 'Depreciates evenly over useful life', 'active'),
        (2, 'Declining Balance', 'DB', 'Depreciates more in early years', 'active'),
        (3, 'Double Declining Balance', 'DDB', 'Accelerated depreciation at 2x rate', 'active')");
    echo "   ���� depreciation_methods\n";
    
    // account_groups
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_groups (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL,
        parent_group_id INT UNSIGNED DEFAULT NULL,
        nature ENUM('asset', 'liability', 'equity', 'income', 'expense') NOT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_group_id) REFERENCES account_groups(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("INSERT IGNORE INTO account_groups (id, group_name, parent_group_id, nature, display_order, status) VALUES
        (1, 'Assets', NULL, 'asset', 1, 'active'),
        (2, 'Liabilities', NULL, 'liability', 2, 'active'),
        (3, 'Equity', NULL, 'equity', 3, 'active'),
        (4, 'Income', NULL, 'income', 4, 'active'),
        (5, 'Expenses', NULL, 'expense', 5, 'active'),
        (10, 'Current Assets', 1, 'asset', 1, 'active'),
        (11, 'Fixed Assets', 1, 'asset', 2, 'active')");
    echo "   ���� account_groups\n";
    
    // account_types
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_types (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type_name VARCHAR(100) NOT NULL,
        group_id INT UNSIGNED NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES account_groups(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("INSERT IGNORE INTO account_types (id, type_name, group_id, description) VALUES
        (11, 'Fixed Asset', 11, 'Long-term tangible assets'),
        (12, 'Depreciation', 11, 'Asset depreciation'),
        (6, 'Other Income', 4, 'Other income sources')");
    echo "   ���� account_types\n";
    
    // chart_of_accounts
    $pdo->exec("CREATE TABLE IF NOT EXISTS chart_of_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_code VARCHAR(50) NOT NULL UNIQUE,
        account_name VARCHAR(200) NOT NULL,
        group_id INT UNSIGNED NOT NULL,
        type_id INT UNSIGNED NOT NULL,
        opening_balance DECIMAL(15,2) DEFAULT 0.00,
        opening_balance_type ENUM('debit', 'credit') DEFAULT 'debit',
        allow_manual_entry TINYINT(1) DEFAULT 1,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES account_groups(id) ON DELETE RESTRICT,
        FOREIGN KEY (type_id) REFERENCES account_types(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, group_id, type_id, allow_manual_entry, status) VALUES
        ('1101', 'Fixed Assets', 11, 11, 1, 'active'),
        ('1201', 'Accumulated Depreciation', 11, 12, 1, 'active'),
        ('5301', 'Depreciation Expense', 5, 12, 1, 'active'),
        ('5401', 'Gain on Asset Disposal', 4, 6, 1, 'active'),
        ('6301', 'Loss on Asset Disposal', 5, 12, 1, 'active')");
    echo "   ���� chart_of_accounts\n";
    
    // Check existing roles table structure
    $stmt = $pdo->query("DESCRIBE roles");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasRoleName = in_array('role_name', $cols);
    
    // permissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        module VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("INSERT IGNORE INTO permissions (name, module) VALUES
        ('fixedasset.view', 'fixed_assets'),
        ('fixedasset.create', 'fixed_assets'),
        ('fixedasset.edit', 'fixed_assets'),
        ('fixedasset.capitalize', 'fixed_assets'),
        ('fixedasset.depreciate', 'fixed_assets'),
        ('fixedasset.transfer', 'fixed_assets'),
        ('fixedasset.impair', 'fixed_assets'),
        ('fixedasset.dispose', 'fixed_assets'),
        ('fixedasset.report', 'fixed_assets')");
    echo "   ���� permissions\n";
    
    // role_permissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        role_id INT UNSIGNED NOT NULL,
        permission_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_role_permission (role_id, permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Grant permissions to super_admin (role_id = 1)
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 1, id FROM permissions WHERE module = 'fixed_assets'");
    echo "   ���� role_permissions\n";
    
    // sidebar_menu
    $pdo->exec("CREATE TABLE IF NOT EXISTS sidebar_menu (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED DEFAULT NULL,
        section VARCHAR(50) NOT NULL,
        section_order INT DEFAULT 0,
        label VARCHAR(100) NOT NULL,
        icon VARCHAR(50),
        route VARCHAR(200) NOT NULL,
        permission VARCHAR(100),
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        is_indented TINYINT(1) DEFAULT 0,
        FOREIGN KEY (parent_id) REFERENCES sidebar_menu(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // F9 sidebar entries
    $menuEntries = [
        [350, null, 'Finance', 5, 'Fixed Assets Dashboard', 'fas fa-building', 'modules/finance/fixed_assets/dashboard/index.php', 'fixedasset.view', 1, 1, 0],
        [351, 350, 'Finance', 5, 'Asset Categories', 'fas fa-list-ol', 'modules/finance/fixed_assets/categories/index.php', 'fixedasset.view', 2, 1, 1],
        [352, 350, 'Finance', 5, 'Financial Assets', 'fas fa-money-bill', 'modules/finance/fixed_assets/financial_records/index.php', 'fixedasset.view', 3, 1, 1],
        [353, 350, 'Finance', 5, 'Capitalization', 'fas fa-plus-circle', 'modules/finance/fixed_assets/acquisitions/index.php', 'fixedasset.capitalize', 4, 1, 1],
        [354, 350, 'Finance', 5, 'Depreciation', 'fas fa-sync', 'modules/finance/fixed_assets/depreciation/index.php', 'fixedasset.depreciate', 5, 1, 1],
        [355, 350, 'Finance', 5, 'Transfers', 'fas fa-exchange-alt', 'modules/finance/fixed_assets/transfers/index.php', 'fixedasset.transfer', 6, 1, 1],
        [356, 350, 'Finance', 5, 'Impairment', 'fas fa-ban', 'modules/finance/fixed_assets/impairment/index.php', 'fixedasset.impair', 7, 1, 1],
        [357, 350, 'Finance', 5, 'Disposal', 'fas fa-trash-alt', 'modules/finance/fixed_assets/disposal/index.php', 'fixedasset.dispose', 8, 1, 1],
        [358, 350, 'Finance', 5, 'Financial Reports', 'fas fa-file-invoice-dollar', 'modules/finance/fixed_assets/reports/index.php', 'fixedasset.report', 9, 1, 1],
        [359, 350, 'Finance', 5, 'Financial History', 'fas fa-history', 'modules/finance/fixed_assets/financial_history/index.php', 'fixedasset.view', 10, 1, 1],
    ];
    
    foreach ($menuEntries as $entry) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO sidebar_menu (id, parent_id, section, section_order, label, icon, route, permission, sort_order, is_active, is_indented) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($entry);
    }
    echo "   ���� sidebar_menu\n";
    
    // 2. Now create the main F9 tables
    echo "\n2. Creating F9 Fixed Assets tables...\n";
    
    // fixed_asset_categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS fixed_asset_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asset_category_id INT UNSIGNED NOT NULL UNIQUE,
        depreciation_method_id INT UNSIGNED NOT NULL,
        useful_life_months INT NOT NULL,
        residual_value_percentage DECIMAL(5,2) DEFAULT 0.00,
        asset_gl_account_id INT UNSIGNED NOT NULL,
        accumulated_depreciation_gl_account_id INT UNSIGNED NOT NULL,
        depreciation_expense_gl_account_id INT UNSIGNED NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_category_id) REFERENCES asset_categories(id) ON DELETE RESTRICT,
        FOREIGN KEY (depreciation_method_id) REFERENCES depreciation_methods(id) ON DELETE RESTRICT,
        FOREIGN KEY (asset_gl_account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT,
        FOREIGN KEY (accumulated_depreciation_gl_account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT,
        FOREIGN KEY (depreciation_expense_gl_account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ���� fixed_asset_categories\n";
    
    // fixed_asset_financials
    $pdo->exec("CREATE TABLE IF NOT EXISTS fixed_asset_financials (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asset_id INT UNSIGNED NOT NULL UNIQUE,
        asset_category_id INT UNSIGNED NOT NULL,
        capitalization_date DATE NOT NULL,
        in_service_date DATE,
        acquisition_cost DECIMAL(15,2) NOT NULL,
        capitalized_cost DECIMAL(15,2) NOT NULL,
        residual_value DECIMAL(15,2) NOT NULL,
        useful_life_months INT NOT NULL,
        depreciation_method_id INT UNSIGNED NOT NULL,
        depreciation_start_date DATE NOT NULL,
        accumulated_depreciation DECIMAL(15,2) DEFAULT 0.00,
        net_book_value DECIMAL(15,2) GENERATED ALWAYS AS (
            CASE 
                WHEN capitalized_cost - accumulated_depreciation > residual_value 
                THEN capitalized_cost - accumulated_depreciation 
                ELSE residual_value 
            END
        ) STORED,
        status ENUM('active', 'disposed', 'impaired') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
        FOREIGN KEY (asset_category_id) REFERENCES fixed_asset_categories(id) ON DELETE RESTRICT,
        FOREIGN KEY (depreciation_method_id) REFERENCES depreciation_methods(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ���� fixed_asset_financials\n";
    
    // asset_depreciation_schedules
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_depreciation_schedules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        financial_asset_id INT UNSIGNED NOT NULL,
        period_date DATE NOT NULL,
        opening_book_value DECIMAL(15,2) NOT NULL,
        depreciation_amount DECIMAL(15,2) NOT NULL,
        accumulated_depreciation DECIMAL(15,2) NOT NULL,
        closing_book_value DECIMAL(15,2) NOT NULL,
        status ENUM('scheduled', 'posted') DEFAULT 'scheduled',
        journal_entry_id INT UNSIGNED DEFAULT NULL,
        posted_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (financial_asset_id) REFERENCES fixed_asset_financials(id) ON DELETE CASCADE,
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
        INDEX idx_asset_period (financial_asset_id, period_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ���� asset_depreciation_schedules\n";
    
    // asset_transfer_history
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_transfer_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asset_id INT UNSIGNED NOT NULL,
        from_department_id INT UNSIGNED,
        to_department_id INT UNSIGNED,
        from_cost_center_id INT UNSIGNED,
        to_cost_center_id INT UNSIGNED,
        from_location_id INT UNSIGNED,
        to_location_id INT UNSIGNED,
        from_project_id INT UNSIGNED,
        to_project_id INT UNSIGNED,
        transfer_date DATE NOT NULL,
        transfer_reason TEXT,
        approved_by INT UNSIGNED NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (from_department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (to_department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (from_cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
        FOREIGN KEY (to_cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
        FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (from_project_id) REFERENCES projects(id) ON DELETE SET NULL,
        FOREIGN KEY (to_project_id) REFERENCES projects(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ���� asset_transfer_history\n";
    
    // asset_impairment
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_impairment (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asset_id INT UNSIGNED NOT NULL,
        impairment_date DATE NOT NULL,
        carrying_amount DECIMAL(15,2) NOT NULL,
        recoverable_amount DECIMAL(15,2) NOT NULL,
        impairment_loss DECIMAL(15,2) GENERATED ALWAYS AS (
            CASE 
                WHEN carrying_amount > recoverable_amount 
                THEN carrying_amount - recoverable_amount 
                ELSE 0.00 
            END
        ) STORED,
        reason TEXT,
        approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        approved_by INT UNSIGNED,
        journal_entry_id INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ���� asset_impairment\n";
    
    // asset_disposal
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_disposal (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asset_id INT UNSIGNED NOT NULL UNIQUE,
        disposal_date DATE NOT NULL,
        disposal_type ENUM('sale', 'scrap', 'write-off', 'donation') NOT NULL,
        disposal_proceeds DECIMAL(15,2) DEFAULT 0.00,
        original_cost DECIMAL(15,2) NOT NULL,
        accumulated_depreciation DECIMAL(15,2) NOT NULL,
        net_book_value DECIMAL(15,2) NOT NULL,
        gain_loss DECIMAL(15,2) GENERATED ALWAYS AS (
            CASE 
                WHEN disposal_proceeds > net_book_value 
                THEN disposal_proceeds - net_book_value 
                ELSE net_book_value - disposal_proceeds 
            END
        ) STORED,
        journal_entry_id INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ���� asset_disposal\n";
    
    // 3. Seed fixed_asset_categories from existing asset_categories
    echo "\n3. Seeding fixed_asset_categories from existing asset_categories...\n";
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO fixed_asset_categories 
        (asset_category_id, depreciation_method_id, useful_life_months, residual_value_percentage, 
         asset_gl_account_id, accumulated_depreciation_gl_account_id, depreciation_expense_gl_account_id) 
        SELECT 
            a.id, 
            1,  -- Straight Line
            60,  -- 5 years
            5.00,  -- 5% residual
            (SELECT id FROM chart_of_accounts WHERE account_code = '1101'),
            (SELECT id FROM chart_of_accounts WHERE account_code = '1201'),
            (SELECT id FROM chart_of_accounts WHERE account_code = '5301')
        FROM asset_categories a
    ");
    $stmt->execute();
    echo "   ���� Seeded " . $stmt->rowCount() . " categories\n";
    
    // 4. Verify all tables
    echo "\n4. Verifying all F9 tables...\n";
    $tables = [
        'fixed_asset_financials',
        'fixed_asset_categories', 
        'asset_depreciation_schedules',
        'asset_impairment',
        'asset_disposal',
        'asset_transfer_history',
        'depreciation_methods',
        'chart_of_accounts',
        'permissions',
        'sidebar_menu'
    ];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `$table`");
        $count = $stmt->fetch()['cnt'];
        echo "   ���� $table: $count rows\n";
    }
    
    echo "\n=== F9 Database Setup Complete ===\n";
    echo "All tables created and seeded successfully!\n";
    
} catch (PDOException $e) {
    echo "\n��� ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}