<?php
/**
 * Company Master migration — widens the `companies` table from an 8-field
 * registration form into an enterprise company master.
 *
 * Field groups are benchmarked against SAP company-code global/company-code
 * parameters, Oracle Fusion legal-entity + business-unit setup (including the
 * BU function flags), Odoo res.company configuration, and Dynamics 365 legal
 * entity attributes.
 *
 * `companies` is kept as the physical table: 13 tables hold FK constraints
 * against it (procurement, inventory, warehouses) and 130+ carry a company_id.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_12_company_master.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;
$added = 0;

function hasColumn($db, $table, $col) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
    $s->execute([$DB, $table, $col]);
    return (bool) $s->fetchColumn();
}

function addColumn($db, $table, $col, $definition, $after = null) {
    global $added;
    if (hasColumn($db, $table, $col)) return;
    $pos = $after ? " AFTER `$after`" : '';
    $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition$pos");
    $added++;
    echo "  + $table.$col\n";
}

function hasIndex($db, $table, $name) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}

function addIndex($db, $table, $name, $cols) {
    if (hasIndex($db, $table, $name)) return;
    $db->exec("ALTER TABLE `$table` ADD INDEX `$name` ($cols)");
    echo "  i $table.$name\n";
}

function hasConstraint($db, $table, $name) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}

function addFk($db, $table, $name, $col, $refTable, $refCol = 'id') {
    if (hasConstraint($db, $table, $name)) return;
    $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$col`) REFERENCES `$refTable`(`$refCol`) ON DELETE SET NULL");
    echo "  -> $table.$col FK $refTable\n";
}

echo "== Company Master migration ==\n\n-- identity & classification --\n";

addColumn($db, 'companies', 'trade_name',        'VARCHAR(255) NULL', 'legal_name');
addColumn($db, 'companies', 'short_name',        'VARCHAR(60) NULL', 'trade_name');
addColumn($db, 'companies', 'search_term',       'VARCHAR(120) NULL', 'short_name');
addColumn($db, 'companies', 'entity_type',       "ENUM('limited_liability','sole_proprietorship','partnership','free_zone_establishment','branch','subsidiary','holding','ngo','government','trust') NOT NULL DEFAULT 'limited_liability'", 'search_term');
addColumn($db, 'companies', 'company_category',  "ENUM('operating','holding','service_provider','special_purpose','dormant') NOT NULL DEFAULT 'operating'", 'entity_type');
addColumn($db, 'companies', 'industry',          'VARCHAR(120) NULL', 'company_category');
addColumn($db, 'companies', 'sub_industry',      'VARCHAR(120) NULL', 'industry');
addColumn($db, 'companies', 'group_id',          'INT UNSIGNED NULL', 'sub_industry');
addColumn($db, 'companies', 'parent_company_id', 'INT UNSIGNED NULL', 'group_id');
addColumn($db, 'companies', 'is_legal_entity',   'TINYINT(1) NOT NULL DEFAULT 1', 'parent_company_id');
addColumn($db, 'companies', 'is_operating_unit', 'TINYINT(1) NOT NULL DEFAULT 1', 'is_legal_entity');
addColumn($db, 'companies', 'is_business_unit',  'TINYINT(1) NOT NULL DEFAULT 0', 'is_operating_unit');
addColumn($db, 'companies', 'established_date',  'DATE NULL', 'is_business_unit');
addColumn($db, 'companies', 'duns_number',       'VARCHAR(20) NULL', 'established_date');
addColumn($db, 'companies', 'lei_number',        'VARCHAR(30) NULL', 'duns_number');

echo "\n-- business unit functions (Oracle Fusion BU roles) --\n";
foreach (['bu_sales' => 'Sales', 'bu_purchasing' => 'Purchasing', 'bu_inventory' => 'Inventory', 'bu_payables' => 'Payables', 'bu_receivables' => 'Receivables'] as $col => $lbl) {
    addColumn($db, 'companies', $col, 'TINYINT(1) NOT NULL DEFAULT 1', 'is_business_unit');
}

echo "\n-- registration, identifiers & statutory --\n";

addColumn($db, 'companies', 'vat_number',       'VARCHAR(60) NULL', 'tax_number');
addColumn($db, 'companies', 'trn_number',       'VARCHAR(60) NULL', 'vat_number');
addColumn($db, 'companies', 'tin_number',       'VARCHAR(60) NULL', 'trn_number');
addColumn($db, 'companies', 'ein_number',       'VARCHAR(60) NULL', 'tin_number');
addColumn($db, 'companies', 'gst_state_code',   'VARCHAR(12) NULL', 'ein_number');
addColumn($db, 'companies', 'free_zone_name',   'VARCHAR(120) NULL', 'gst_state_code');
addColumn($db, 'companies', 'free_zone_license','VARCHAR(80) NULL', 'free_zone_name');
addColumn($db, 'companies', 'free_zone_expiry', 'DATE NULL', 'free_zone_license');
addColumn($db, 'companies', 'license_number',   'VARCHAR(80) NULL', 'free_zone_expiry');
addColumn($db, 'companies', 'license_expiry',   'DATE NULL', 'license_number');
addColumn($db, 'companies', 'regulatory_authority', 'VARCHAR(150) NULL', 'license_expiry');
addColumn($db, 'companies', 'share_capital',    'DECIMAL(18,2) NULL', 'regulatory_authority');
addColumn($db, 'companies', 'audit_firm',       'VARCHAR(190) NULL', 'share_capital');
addColumn($db, 'companies', 'statutory_auditor','VARCHAR(190) NULL', 'audit_firm');
addColumn($db, 'companies', 'financial_framework', "ENUM('IFRS','IFRS_for_SMEs','Local_GAAP','US_GAAP','ISFR','None') NOT NULL DEFAULT 'IFRS'", 'statutory_auditor');
addColumn($db, 'companies', 'consolidation_method', "ENUM('full','proportionate','equity','none') NOT NULL DEFAULT 'full'", 'financial_framework');
addColumn($db, 'companies', 'is_reporting_entity', 'TINYINT(1) NOT NULL DEFAULT 1', 'consolidation_method');

echo "\n-- registered address & contact --\n";

addColumn($db, 'companies', 'address_line1', 'VARCHAR(255) NULL', 'country');
addColumn($db, 'companies', 'address_line2', 'VARCHAR(255) NULL', 'address_line1');
addColumn($db, 'companies', 'city',          'VARCHAR(120) NULL', 'address_line2');
addColumn($db, 'companies', 'state',         'VARCHAR(120) NULL', 'city');
addColumn($db, 'companies', 'postal_code',   'VARCHAR(20) NULL', 'state');
addColumn($db, 'companies', 'region',        'VARCHAR(120) NULL', 'postal_code');
addColumn($db, 'companies', 'phone',         'VARCHAR(40) NULL', 'region');
addColumn($db, 'companies', 'alternate_phone','VARCHAR(40) NULL', 'phone');
addColumn($db, 'companies', 'fax',           'VARCHAR(40) NULL', 'alternate_phone');
addColumn($db, 'companies', 'email',         'VARCHAR(190) NULL', 'fax');
addColumn($db, 'companies', 'support_email', 'VARCHAR(190) NULL', 'email');
addColumn($db, 'companies', 'website',       'VARCHAR(255) NULL', 'support_email');
addColumn($db, 'companies', 'contact_person','VARCHAR(190) NULL', 'website');
addColumn($db, 'companies', 'contact_designation', 'VARCHAR(120) NULL', 'contact_person');
addColumn($db, 'companies', 'language',      'VARCHAR(40) NOT NULL DEFAULT \'English\'', 'contact_designation');

echo "\n-- ledger & accounting controls --\n";

$glAccounts = [
    'default_receivable_account_id', 'default_payable_account_id', 'default_bank_account_id',
    'default_cash_account_id', 'default_inventory_account_id', 'default_cogs_account_id',
    'default_sales_account_id', 'default_purchase_account_id', 'default_tax_account_id',
    'default_intercompany_account_id', 'default_retained_earnings_account_id',
    'default_fx_difference_account_id',
];
$prev = 'language';
foreach ($glAccounts as $col) { addColumn($db, 'companies', $col, 'INT UNSIGNED NULL', $prev); $prev = $col; }

addColumn($db, 'companies', 'periods_per_year',        'TINYINT UNSIGNED NOT NULL DEFAULT 12', $prev);
addColumn($db, 'companies', 'posting_tolerance',       'DECIMAL(12,2) NOT NULL DEFAULT 0.01', 'periods_per_year');
addColumn($db, 'companies', 'allow_posting_to_closed', 'TINYINT(1) NOT NULL DEFAULT 0', 'posting_tolerance');
addColumn($db, 'companies', 'require_cost_center',     'TINYINT(1) NOT NULL DEFAULT 0', 'allow_posting_to_closed');
addColumn($db, 'companies', 'require_project',         'TINYINT(1) NOT NULL DEFAULT 0', 'require_cost_center');
addColumn($db, 'companies', 'enable_multicurrency',    'TINYINT(1) NOT NULL DEFAULT 1', 'require_project');
addColumn($db, 'companies', 'cash_basis_accounting',   'TINYINT(1) NOT NULL DEFAULT 0', 'enable_multicurrency');
addColumn($db, 'companies', 'allow_negative_stock',    'TINYINT(1) NOT NULL DEFAULT 0', 'cash_basis_accounting');

echo "\n-- document numbering & default trading terms --\n";

addColumn($db, 'companies', 'invoice_prefix',      'VARCHAR(12) NOT NULL DEFAULT \'INV\'', 'allow_negative_stock');
addColumn($db, 'companies', 'invoice_padding',     'SMALLINT UNSIGNED NOT NULL DEFAULT 4', 'invoice_prefix');
addColumn($db, 'companies', 'quote_prefix',        'VARCHAR(12) NOT NULL DEFAULT \'QTN\'', 'invoice_padding');
addColumn($db, 'companies', 'po_prefix',           'VARCHAR(12) NOT NULL DEFAULT \'PO\'', 'quote_prefix');
addColumn($db, 'companies', 'pr_prefix',           'VARCHAR(12) NOT NULL DEFAULT \'PR\'', 'po_prefix');
addColumn($db, 'companies', 'gr_prefix',           'VARCHAR(12) NOT NULL DEFAULT \'GRN\'', 'pr_prefix');
addColumn($db, 'companies', 'je_prefix',           'VARCHAR(12) NOT NULL DEFAULT \'JV\'', 'gr_prefix');
addColumn($db, 'companies', 'receipt_prefix',      'VARCHAR(12) NOT NULL DEFAULT \'RCP\'', 'je_prefix');
addColumn($db, 'companies', 'vendor_bill_prefix',  'VARCHAR(12) NOT NULL DEFAULT \'VB\'', 'receipt_prefix');
addColumn($db, 'companies', 'payment_reference_format', 'VARCHAR(60) NULL', 'vendor_bill_prefix');
addColumn($db, 'companies', 'invoice_terms',       'TEXT NULL', 'payment_reference_format');
addColumn($db, 'companies', 'quote_validity_days', 'SMALLINT UNSIGNED NOT NULL DEFAULT 30', 'invoice_terms');
addColumn($db, 'companies', 'default_payment_terms','VARCHAR(50) NULL', 'quote_validity_days');
addColumn($db, 'companies', 'default_shipping_condition','VARCHAR(60) NULL', 'default_payment_terms');
addColumn($db, 'companies', 'default_incoterms',   'VARCHAR(10) NULL', 'default_shipping_condition');
addColumn($db, 'companies', 'default_price_list',  'VARCHAR(60) NULL', 'default_incoterms');

echo "\n-- tax reporting & e-invoicing --\n";

addColumn($db, 'companies', 'tax_registration_type', "ENUM('standard','vat_registered','non_registered','exempt','reverse_charge') NOT NULL DEFAULT 'standard'", 'default_price_list');
addColumn($db, 'companies', 'tax_authority',         'VARCHAR(150) NULL', 'tax_registration_type');
addColumn($db, 'companies', 'tax_office_code',       'VARCHAR(40) NULL', 'tax_authority');
addColumn($db, 'companies', 'vat_return_frequency',  "ENUM('monthly','quarterly','semi_annual','annual','none') NOT NULL DEFAULT 'monthly'", 'tax_office_code');
addColumn($db, 'companies', 'vat_return_day',        'TINYINT UNSIGNED NOT NULL DEFAULT 15', 'vat_return_frequency');
addColumn($db, 'companies', 'withholding_agent',     'TINYINT(1) NOT NULL DEFAULT 1', 'vat_return_day');
addColumn($db, 'companies', 'default_withholding_rate','DECIMAL(5,2) NOT NULL DEFAULT 0.00', 'withholding_agent');
addColumn($db, 'companies', 'e_invoicing_enabled',   'TINYINT(1) NOT NULL DEFAULT 0', 'default_withholding_rate');
addColumn($db, 'companies', 'e_invoicing_system',    "ENUM('none','zatca_fatoora','gst_e_invoice','fbr_iris','peppol','other') NOT NULL DEFAULT 'none'", 'e_invoicing_enabled');
addColumn($db, 'companies', 'e_invoicing_stage',     "ENUM('phase_1_generation','phase_2_integration','cleared_only') NULL", 'e_invoicing_system');
addColumn($db, 'companies', 'direct_debit_prefix',   'VARCHAR(30) NULL', 'e_invoicing_stage');

echo "\n-- branding & documents --\n";

addColumn($db, 'companies', 'logo_path',              'VARCHAR(255) NULL', 'direct_debit_prefix');
addColumn($db, 'companies', 'letterhead_template_id', 'INT UNSIGNED NULL', 'logo_path');
addColumn($db, 'companies', 'signature_text',         'TEXT NULL', 'letterhead_template_id');
addColumn($db, 'companies', 'document_footer_note',   'TEXT NULL', 'signature_text');
addColumn($db, 'companies', 'theme_color',            'VARCHAR(9) NULL', 'document_footer_note');

echo "\n-- operations & modules --\n";

addColumn($db, 'companies', 'default_warehouse_id',  'INT UNSIGNED NULL', 'theme_color');
addColumn($db, 'companies', 'default_cost_center_id','INT UNSIGNED NULL', 'default_warehouse_id');
addColumn($db, 'companies', 'working_days',          'VARCHAR(40) NOT NULL DEFAULT \'1,2,3,4,5\'', 'default_cost_center_id');
addColumn($db, 'companies', 'weekend_days',          'VARCHAR(20) NOT NULL DEFAULT \'5,6\'', 'working_days');
addColumn($db, 'companies', 'working_hours',         'VARCHAR(30) NOT NULL DEFAULT \'09:00-18:00\'', 'weekend_days');
addColumn($db, 'companies', 'employee_count',        'INT UNSIGNED NULL', 'working_hours');
addColumn($db, 'companies', 'annual_revenue',        'DECIMAL(18,2) NULL', 'employee_count');
addColumn($db, 'companies', 'enable_inventory',      'TINYINT(1) NOT NULL DEFAULT 1', 'annual_revenue');
addColumn($db, 'companies', 'enable_projects',       'TINYINT(1) NOT NULL DEFAULT 1', 'enable_inventory');
addColumn($db, 'companies', 'enable_hr',             'TINYINT(1) NOT NULL DEFAULT 1', 'enable_projects');
addColumn($db, 'companies', 'enable_pos',            'TINYINT(1) NOT NULL DEFAULT 0', 'enable_hr');
addColumn($db, 'companies', 'enable_manufacturing',  'TINYINT(1) NOT NULL DEFAULT 0', 'enable_pos');
addColumn($db, 'companies', 'branch_code',           'VARCHAR(30) NULL', 'enable_manufacturing');
addColumn($db, 'companies', 'is_branch',             'TINYINT(1) NOT NULL DEFAULT 0', 'branch_code');

echo "\n-- audit & soft delete --\n";

addColumn($db, 'companies', 'created_by', 'INT UNSIGNED NULL', 'updated_at');
addColumn($db, 'companies', 'updated_by', 'INT UNSIGNED NULL', 'created_by');
addColumn($db, 'companies', 'deleted_at', 'TIMESTAMP NULL', 'updated_by');

// Widen status only if the new values are genuinely missing.
$cur = $db->query("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = '$DB' AND table_name = 'companies' AND column_name = 'status'")->fetchColumn();
if ($cur && !str_contains($cur, 'dormant')) {
    $vals = trim($cur, "enum()");
    $list = array_values(array_unique(array_merge(array_map('trim', explode(',', $vals)), ["'active'", "'inactive'", "'dormant'", "'archived'"])));
    $db->exec("ALTER TABLE `companies` MODIFY `status` ENUM(" . implode(',', $list) . ") NOT NULL DEFAULT 'active'");
    echo "  ~ companies.status widened to " . implode(', ', array_map(fn($v) => trim($v, "'"), $list)) . "\n";
}

echo "\n-- relations --\n";
addFk($db, 'companies', 'fk_co_group',  'group_id',          'company_groups');
addFk($db, 'companies', 'fk_co_parent', 'parent_company_id', 'companies');
addFk($db, 'companies', 'fk_co_warehouse', 'default_warehouse_id', 'warehouses');

addIndex($db, 'companies', 'idx_co_entity_type', '`entity_type`');
addIndex($db, 'companies', 'idx_co_parent',      '`parent_company_id`');
addIndex($db, 'companies', 'idx_co_status',      '`status`');
addIndex($db, 'companies', 'idx_co_search',      '`search_term`');

echo "\n-- company_bank_accounts (multi-bank, with primary flag) --\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `company_bank_accounts` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id`     INT UNSIGNED NOT NULL,
        `account_label`  VARCHAR(120) NOT NULL,
        `bank_name`      VARCHAR(150) NOT NULL,
        `branch`         VARCHAR(150) NULL,
        `account_name`   VARCHAR(190) NULL,
        `account_number` VARCHAR(60) NULL,
        `iban`           VARCHAR(40) NULL,
        `swift_bic`      VARCHAR(20) NULL,
        `currency_code`  CHAR(3) NOT NULL DEFAULT 'USD',
        `account_type`   ENUM('current','savings','margin','escrow','fx','fixed_deposit') NOT NULL DEFAULT 'current',
        `is_primary`     TINYINT(1) NOT NULL DEFAULT 0,
        `gl_account_id`  INT UNSIGNED NULL,
        `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        `status`         ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at`     TIMESTAMP NULL,
        PRIMARY KEY (`id`),
        KEY `idx_cba_company` (`company_id`),
        CONSTRAINT `fk_cba_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  = company_bank_accounts ready\n";

// Carry the existing tax number into vat_number so documents have an issuer
// tax reference on first run. Guarded, so it never overwrites a deliberate edit.
$db->exec("UPDATE companies SET vat_number = tax_number WHERE (vat_number IS NULL OR vat_number = '') AND tax_number IS NOT NULL AND tax_number <> ''");
echo "\n  ~ backfilled vat_number from tax_number\n";

echo "\n-- permissions --\n";
$permIds = [];
foreach (['delete', 'manage_access', 'view', 'create', 'edit'] as $pname) {
    $s = $db->prepare("SELECT id FROM permissions WHERE module = 'company' AND name = ? LIMIT 1");
    $s->execute([$pname]);
    $id = $s->fetchColumn();
    if ($id) { $permIds[$pname] = (int)$id; echo "  = company.$pname exists (#$id)\n"; continue; }
    $db->prepare("INSERT INTO permissions (module, name, created_at) VALUES ('company', ?, NOW())")->execute([$pname]);
    $permIds[$pname] = (int) $db->lastInsertId();
    echo "  + company.$pname (#{$permIds[$pname]})\n";
}

$grants = [];
$sa = $db->prepare("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1"); $sa->execute();
if ($saId = $sa->fetchColumn()) { foreach ($permIds as $pid) $grants[(int)$saId][] = $pid; }
$ad = $db->prepare("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1"); $ad->execute();
if ($adId = $ad->fetchColumn()) { $grants[(int)$adId] = [$permIds['view']]; }

foreach ($grants as $roleId => $pids) {
    foreach (array_unique($pids) as $pid) {
        $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $chk->execute([$roleId, $pid]);
        if ($chk->fetchColumn()) continue;
        $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$roleId, $pid]);
        echo "  -> role #$roleId granted permission #$pid\n";
    }
}

$cols = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$DB' AND table_name = 'companies'")->fetchColumn();
echo "\n== Done: $added columns added; companies now has $cols columns ==\n";
