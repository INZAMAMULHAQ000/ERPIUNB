<?php
/**
 * Customer Master migration — extends the existing `clients` table into a full
 * ERP customer master (benchmarked against SAP BP, Oracle Fusion TCA, Odoo
 * res.partner, Dynamics 365 and Infor).
 *
 * `clients` is deliberately kept as the physical table: customer_invoices,
 * invoices, credit_notes, customer_receipts, deals and tickets all hold FK
 * constraints against it, so the master is widened in place rather than forked.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_12_customer_master.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;
$added = 0;
$skipped = 0;

function hasColumn($db, $table, $col) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
    $s->execute([$DB, $table, $col]);
    return (bool) $s->fetchColumn();
}

function addColumn($db, $table, $col, $definition, $after = null) {
    global $added, $skipped;
    if (hasColumn($db, $table, $col)) { $skipped++; return; }
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

echo "== Customer Master migration ==\n\n-- clients: identity & classification --\n";

addColumn($db, 'clients', 'customer_type',        "ENUM('company','individual','partnership','sole_proprietorship','government','trust') NOT NULL DEFAULT 'company'", 'client_code');
addColumn($db, 'clients', 'account_group',        "VARCHAR(50) NULL", 'customer_type');
addColumn($db, 'clients', 'legal_name',           "VARCHAR(255) NULL", 'account_group');
addColumn($db, 'clients', 'trade_name',           "VARCHAR(255) NULL", 'legal_name');
addColumn($db, 'clients', 'search_term',          "VARCHAR(120) NULL", 'trade_name');
addColumn($db, 'clients', 'segment',              "VARCHAR(60) NULL", 'search_term');
addColumn($db, 'clients', 'parent_client_id',     "INT UNSIGNED NULL", 'segment');
addColumn($db, 'clients', 'is_one_time',          "TINYINT(1) NOT NULL DEFAULT 0", 'parent_client_id');
addColumn($db, 'clients', 'customer_since',       "DATE NULL", 'is_one_time');
addColumn($db, 'clients', 'lead_source',          "VARCHAR(80) NULL", 'customer_since');

echo "\n-- clients: registration & tax --\n";

addColumn($db, 'clients', 'registration_number',  "VARCHAR(80) NULL", 'industry');
addColumn($db, 'clients', 'tax_id',               "VARCHAR(80) NULL", 'registration_number');
addColumn($db, 'clients', 'tax_id_secondary',     "VARCHAR(80) NULL", 'tax_id');
addColumn($db, 'clients', 'tax_category',         "ENUM('regular','composition','unregistered','sez','export','deemed_export','overseas') NOT NULL DEFAULT 'regular'", 'tax_id_secondary');
addColumn($db, 'clients', 'tax_jurisdiction',     "VARCHAR(120) NULL", 'tax_category');
addColumn($db, 'clients', 'state_code',           "VARCHAR(12) NULL", 'tax_jurisdiction');
addColumn($db, 'clients', 'tax_registration_date',"DATE NULL", 'state_code');
addColumn($db, 'clients', 'tax_exemption_ref',    "VARCHAR(120) NULL", 'tax_registration_date');
addColumn($db, 'clients', 'tax_exemption_upto',   "DATE NULL", 'tax_exemption_ref');
addColumn($db, 'clients', 'withholding_tax_rate', "DECIMAL(5,2) NOT NULL DEFAULT 0.00", 'tax_exemption_upto');
addColumn($db, 'clients', 'export_code',          "VARCHAR(80) NULL", 'withholding_tax_rate');

echo "\n-- clients: structured billing address (legacy `address` text kept for existing consumers) --\n";

addColumn($db, 'clients', 'address_line1',        "VARCHAR(255) NULL", 'address');
addColumn($db, 'clients', 'address_line2',        "VARCHAR(255) NULL", 'address_line1');
addColumn($db, 'clients', 'city',                 "VARCHAR(120) NULL", 'address_line2');
addColumn($db, 'clients', 'state',                "VARCHAR(120) NULL", 'city');
addColumn($db, 'clients', 'postal_code',          "VARCHAR(20) NULL", 'state');

echo "\n-- clients: financial & receivables --\n";

addColumn($db, 'clients', 'currency_code',        "CHAR(3) NOT NULL DEFAULT 'USD'", 'postal_code');
addColumn($db, 'clients', 'payment_terms',        "VARCHAR(50) NULL", 'currency_code');
addColumn($db, 'clients', 'payment_term_days',    "SMALLINT UNSIGNED NOT NULL DEFAULT 30", 'payment_terms');
addColumn($db, 'clients', 'payment_method',       "VARCHAR(40) NULL", 'payment_term_days');
addColumn($db, 'clients', 'default_receivable_account_id', "INT UNSIGNED NULL", 'payment_method');
addColumn($db, 'clients', 'credit_limit',         "DECIMAL(15,2) NOT NULL DEFAULT 0.00", 'default_receivable_account_id');
addColumn($db, 'clients', 'credit_rating',        "VARCHAR(10) NULL", 'credit_limit');
addColumn($db, 'clients', 'credit_hold',          "ENUM('no','manual','automatic') NOT NULL DEFAULT 'no'", 'credit_rating');
addColumn($db, 'clients', 'collection_priority',  "TINYINT UNSIGNED NOT NULL DEFAULT 3", 'credit_hold');
addColumn($db, 'clients', 'interest_rate',        "DECIMAL(5,2) NOT NULL DEFAULT 0.00", 'collection_priority');
addColumn($db, 'clients', 'cash_discount_percent',"DECIMAL(5,2) NOT NULL DEFAULT 0.00", 'interest_rate');
addColumn($db, 'clients', 'discount_days',        "SMALLINT UNSIGNED NOT NULL DEFAULT 0", 'cash_discount_percent');
addColumn($db, 'clients', 'billing_cycle',        "ENUM('per_transaction','weekly','monthly','quarterly','annual') NOT NULL DEFAULT 'per_transaction'", 'discount_days');
addColumn($db, 'clients', 'consolidated_billing', "TINYINT(1) NOT NULL DEFAULT 0", 'billing_cycle');
addColumn($db, 'clients', 'allow_partial_payment',"TINYINT(1) NOT NULL DEFAULT 1", 'consolidated_billing');
addColumn($db, 'clients', 'price_list',           "VARCHAR(60) NULL", 'allow_partial_payment');
addColumn($db, 'clients', 'tax_inclusive',        "TINYINT(1) NOT NULL DEFAULT 0", 'price_list');
addColumn($db, 'clients', 'invoice_delivery',     "ENUM('email','print','portal','courier') NOT NULL DEFAULT 'email'", 'tax_inclusive');
addColumn($db, 'clients', 'invoice_email',        "VARCHAR(190) NULL", 'invoice_delivery');
addColumn($db, 'clients', 'invoice_cc_emails',    "VARCHAR(500) NULL", 'invoice_email');
addColumn($db, 'clients', 'bank_name',            "VARCHAR(150) NULL", 'invoice_cc_emails');
addColumn($db, 'clients', 'bank_account_name',    "VARCHAR(190) NULL", 'bank_name');
addColumn($db, 'clients', 'bank_account_number',  "VARCHAR(60) NULL", 'bank_account_name');
addColumn($db, 'clients', 'bank_branch',          "VARCHAR(150) NULL", 'bank_account_number');
addColumn($db, 'clients', 'bank_identifier',      "VARCHAR(60) NULL", 'bank_branch');

echo "\n-- clients: sales, service & commercial terms --\n";

addColumn($db, 'clients', 'sales_representative_id',  "INT UNSIGNED NULL", 'assigned_manager');
addColumn($db, 'clients', 'support_representative_id',"INT UNSIGNED NULL", 'sales_representative_id');
addColumn($db, 'clients', 'sales_region',         "VARCHAR(120) NULL", 'support_representative_id');
addColumn($db, 'clients', 'distribution_channel', "VARCHAR(60) NULL", 'sales_region');
addColumn($db, 'clients', 'shipping_condition',   "VARCHAR(60) NULL", 'distribution_channel');
addColumn($db, 'clients', 'incoterms',            "VARCHAR(10) NULL", 'shipping_condition');
addColumn($db, 'clients', 'incoterms_location',   "VARCHAR(150) NULL", 'incoterms');
addColumn($db, 'clients', 'delivery_lead_time_days', "SMALLINT UNSIGNED NOT NULL DEFAULT 0", 'incoterms_location');
addColumn($db, 'clients', 'default_discount_percent', "DECIMAL(5,2) NOT NULL DEFAULT 0.00", 'delivery_lead_time_days');
addColumn($db, 'clients', 'commission_rate',      "DECIMAL(5,2) NOT NULL DEFAULT 0.00", 'default_discount_percent');
addColumn($db, 'clients', 'contract_start_date',  "DATE NULL", 'commission_rate');
addColumn($db, 'clients', 'contract_end_date',    "DATE NULL", 'contract_start_date');
addColumn($db, 'clients', 'company_size',         "ENUM('micro','small','medium','large','enterprise') NULL", 'contract_end_date');
addColumn($db, 'clients', 'number_of_employees',  "INT UNSIGNED NULL", 'company_size');
addColumn($db, 'clients', 'annual_revenue',       "DECIMAL(18,2) NULL", 'number_of_employees');
addColumn($db, 'clients', 'year_established',     "SMALLINT UNSIGNED NULL", 'annual_revenue');

echo "\n-- clients: communication & preferences --\n";

addColumn($db, 'clients', 'alternate_phone',      "VARCHAR(40) NULL", 'phone');
addColumn($db, 'clients', 'whatsapp',             "VARCHAR(40) NULL", 'alternate_phone');
addColumn($db, 'clients', 'linkedin_url',         "VARCHAR(255) NULL", 'website');
addColumn($db, 'clients', 'preferred_language',   "VARCHAR(40) NULL", 'linkedin_url');
addColumn($db, 'clients', 'timezone',             "VARCHAR(60) NULL", 'preferred_language');
addColumn($db, 'clients', 'preferred_contact_method', "ENUM('email','phone','whatsapp','mail') NOT NULL DEFAULT 'email'", 'timezone');

echo "\n-- clients: compliance, control & audit --\n";

addColumn($db, 'clients', 'kyc_status',           "ENUM('not_started','pending','submitted','verified','rejected') NOT NULL DEFAULT 'not_started'", 'status');
addColumn($db, 'clients', 'kyc_verified_at',      "DATETIME NULL", 'kyc_status');
addColumn($db, 'clients', 'kyc_verified_by',      "INT UNSIGNED NULL", 'kyc_verified_at');
addColumn($db, 'clients', 'risk_level',           "ENUM('low','medium','high') NOT NULL DEFAULT 'low'", 'kyc_verified_by');
addColumn($db, 'clients', 'marketing_consent',    "TINYINT(1) NOT NULL DEFAULT 0", 'risk_level');
addColumn($db, 'clients', 'data_processing_consent', "TINYINT(1) NOT NULL DEFAULT 0", 'marketing_consent');
addColumn($db, 'clients', 'internal_notes',       "TEXT NULL", 'data_processing_consent');
addColumn($db, 'clients', 'created_by',           "INT UNSIGNED NULL", 'internal_notes');
addColumn($db, 'clients', 'updated_by',           "INT UNSIGNED NULL", 'created_by');
addColumn($db, 'clients', 'deleted_at',           "TIMESTAMP NULL", 'updated_by');

echo "\n-- widen status domain (blocked/prospect/archive are excluded by existing WHERE status='active' pickers) --\n";
$db->exec("ALTER TABLE `clients` MODIFY `status` ENUM('active','inactive','prospect','blocked','archived') NOT NULL DEFAULT 'active'");
echo "  ~ clients.status widened\n";

echo "\n-- relations --\n";
$s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = 'clients' AND constraint_name = 'fk_clients_parent'");
$s->execute([$DB]);
if (!$s->fetchColumn()) {
    $db->exec("ALTER TABLE `clients` ADD CONSTRAINT `fk_clients_parent` FOREIGN KEY (`parent_client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL");
    echo "  -> clients.parent_client_id FK\n";
}
$s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = 'clients' AND constraint_name = 'fk_clients_sales_rep'");
$s->execute([$DB]);
if (!$s->fetchColumn()) {
    $db->exec("ALTER TABLE `clients` ADD CONSTRAINT `fk_clients_sales_rep` FOREIGN KEY (`sales_representative_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL");
    echo "  -> clients.sales_representative_id FK\n";
}
$s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = 'clients' AND constraint_name = 'fk_clients_receivable_acct'");
$s->execute([$DB]);
if (!$s->fetchColumn()) {
    $db->exec("ALTER TABLE `clients` ADD CONSTRAINT `fk_clients_receivable_acct` FOREIGN KEY (`default_receivable_account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE SET NULL");
    echo "  -> clients.default_receivable_account_id FK\n";
}

addIndex($db, 'clients', 'idx_clients_type',       '`customer_type`');
addIndex($db, 'clients', 'idx_clients_segment',    '`segment`');
addIndex($db, 'clients', 'idx_clients_status',     '`status`');
addIndex($db, 'clients', 'idx_clients_search',     '`search_term`');

echo "\n-- customer_addresses (multi site address book: bill-to / ship-to / remittance) --\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `customer_addresses` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `client_id`      INT UNSIGNED NOT NULL,
        `address_type`   ENUM('billing','shipping','remittance','other') NOT NULL DEFAULT 'shipping',
        `label`          VARCHAR(120) NOT NULL,
        `contact_name`   VARCHAR(190) NULL,
        `phone`          VARCHAR(40) NULL,
        `email`          VARCHAR(190) NULL,
        `address_line1`  VARCHAR(255) NULL,
        `address_line2`  VARCHAR(255) NULL,
        `city`           VARCHAR(120) NULL,
        `state`          VARCHAR(120) NULL,
        `postal_code`    VARCHAR(20) NULL,
        `country`        VARCHAR(120) NULL,
        `tax_number`     VARCHAR(80) NULL,
        `is_default`     TINYINT(1) NOT NULL DEFAULT 0,
        `valid_from`     DATE NULL,
        `valid_upto`     DATE NULL,
        `notes`          TEXT NULL,
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at`     TIMESTAMP NULL,
        PRIMARY KEY (`id`),
        KEY `idx_addr_client` (`client_id`),
        KEY `idx_addr_type` (`address_type`),
        CONSTRAINT `fk_addr_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  = customer_addresses ready\n";

echo "\n-- link People Management to the customer master --\n";
addColumn($db, 'people', 'customer_id', "INT UNSIGNED NULL", 'employee_id');
$s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = 'people' AND constraint_name = 'fk_people_customer'");
$s->execute([$DB]);
if (!$s->fetchColumn()) {
    $db->exec("ALTER TABLE `people` ADD CONSTRAINT `fk_people_customer` FOREIGN KEY (`customer_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL");
    echo "  -> people.customer_id FK\n";
}

echo "\n-- permissions --\n";
$permIds = [];
foreach (['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 1, 'manage_credit' => 1] as $pname => $_) {
    $s = $db->prepare("SELECT id FROM permissions WHERE module = 'customers' AND name = ? LIMIT 1");
    $s->execute([$pname]);
    $id = $s->fetchColumn();
    if ($id) { echo "  = customers.$pname exists (#$id)\n"; $permIds[$pname] = (int)$id; continue; }
    $ins = $db->prepare("INSERT INTO permissions (module, name, created_at) VALUES ('customers', ?, NOW())");
    $ins->execute([$pname]);
    $permIds[$pname] = (int)$db->lastInsertId();
    echo "  + customers.$pname (#{$permIds[$pname]})\n";
}

// Grant the full set to Super Admin, and the operational set to Admin.
$grants = [];
$sa = $db->prepare("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1"); $sa->execute();
if ($saId = $sa->fetchColumn()) {
    foreach ($permIds as $pid) $grants[(int)$saId][] = $pid;
}
$ad = $db->prepare("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1"); $ad->execute();
if ($adId = $ad->fetchColumn()) {
    foreach (['view', 'create', 'edit'] as $p) {
        if (isset($permIds[$p])) $grants[(int)$adId][] = $permIds[$p];
    }
}
foreach ($grants as $roleId => $pids) {
    foreach (array_unique($pids) as $pid) {
        $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $chk->execute([$roleId, $pid]);
        if ($chk->fetchColumn()) continue;
        $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$roleId, $pid]);
        echo "  -> role #$roleId granted permission #$pid\n";
    }
}

echo "\n== Done: $added columns added, $skipped already present ==\n";
