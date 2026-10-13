<?php
/**
 * Company-scoped posting + dynamic configuration migration.
 *
 * Closes the gap where every journal and GL line resolved to company 1 via a
 * silent `?? 1` fallback, and removes the remaining hardcoded business values
 * (FX rates, document number sequences, system branding) in favour of tables.
 *
 *  1. app_settings          — branding + fiscal defaults, admin-editable
 *  2. currencies / currency_rates — replaces the static array in helpers/currency.php
 *  3. document_sequences    — per-company, per-type counters (replaces COUNT(*)+1)
 *  4. chart_of_accounts.company_id — NULL = shared group chart, else company-specific
 *  5. financial_years + accounting_periods provisioned for every active company
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_12_company_scoped_posting.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;

function hasColumn($db, $table, $col) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?");
    $s->execute([$DB, $table, $col]);
    return (bool) $s->fetchColumn();
}
function addColumn($db, $table, $col, $def, $after = null) {
    if (hasColumn($db, $table, $col)) return;
    $pos = $after ? " AFTER `$after`" : '';
    $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def$pos");
    echo "  + $table.$col\n";
}
function hasConstraint($db, $table, $name) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=? AND table_name=? AND constraint_name=?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}
function hasIndex($db, $table, $name) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=? AND table_name=? AND index_name=?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}
function addFk($db, $table, $name, $col, $ref, $refCol = 'id', $onDel = 'SET NULL') {
    if (hasConstraint($db, $table, $name)) return;
    $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$col`) REFERENCES `$ref`(`$refCol`) ON DELETE $onDel");
    echo "  -> $table.$col FK $ref\n";
}

echo "== Company-scoped posting migration ==\n\n-- app_settings (branding & fiscal defaults) --\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `app_settings` (
        `setting_key`   VARCHAR(80) NOT NULL,
        `setting_value` TEXT NULL,
        `setting_group` VARCHAR(40) NOT NULL DEFAULT 'general',
        `value_type`    ENUM('string','integer','decimal','boolean','json','date') NOT NULL DEFAULT 'string',
        `label`         VARCHAR(160) NULL,
        `description`   VARCHAR(500) NULL,
        `is_public`     TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by`    INT UNSIGNED NULL,
        PRIMARY KEY (`setting_key`),
        KEY `idx_settings_group` (`setting_group`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  = app_settings ready\n";

// Seed from what the code currently hardcodes, so behaviour does not change under foot.
$appName = 'ERP System';
$defFy   = $db->query("SELECT MONTH(start_date) FROM financial_years WHERE company_id = 1 ORDER BY start_date LIMIT 1")->fetchColumn();
$fyMonth = $defFy ? (int) $defFy : 1;

$seeds = [
    ['app_name',                 $appName, 'branding',  'string',  'Application name',        'Shown in page titles, the navbar and printed documents'],
    ['base_currency',            'USD',    'finance',   'string',  'Fallback currency',       'Used only when no company context is resolvable'],
    ['fiscal_year_start_month',  (string) $fyMonth, 'finance', 'integer', 'Fiscal year start month', '1-12; used when a company has no explicit fiscal year start'],
    ['fiscal_year_pattern',      'FY {start}-{end}', 'finance', 'string', 'Financial year naming', '{start} and {end} are replaced with the year numbers'],
    ['period_naming',            '{Month} {Year}', 'finance', 'string', 'Accounting period naming', '{Month} is the month name, {Year} the four-digit year'],
    ['negative_stock_allowed',   '0',      'inventory', 'boolean', 'Allow negative stock',    'Global default; companies can override'],
    ['date_format',              'd M Y',  'general',   'string',  'Display date format',     'PHP date() format used across the UI'],
    ['datetime_format',          'd M Y, H:i', 'general','string',  'Display datetime format', 'PHP date() format used across the UI'],
];
foreach ($seeds as [$k, $v, $g, $t, $lbl, $desc]) {
    $chk = $db->prepare("SELECT COUNT(*) FROM app_settings WHERE setting_key = ?");
    $chk->execute([$k]);
    if ($chk->fetchColumn()) { echo "  = $k exists\n"; continue; }
    $db->prepare("INSERT INTO app_settings (setting_key, setting_value, setting_group, value_type, label, description) VALUES (?,?,?,?,?,?)")
       ->execute([$k, $v, $g, $t, $lbl, $desc]);
    echo "  + $k = " . var_export($v, true) . "\n";
}

echo "\n-- currencies + rates (replaces the hardcoded table in helpers/currency.php) --\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `currencies` (
        `code`        CHAR(3) NOT NULL,
        `name`        VARCHAR(120) NOT NULL,
        `symbol`      VARCHAR(12) NOT NULL,
        `decimal_places` TINYINT UNSIGNED NOT NULL DEFAULT 2,
        `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
        `is_base`     TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 100,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`code`),
        KEY `idx_cur_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$db->exec("
    CREATE TABLE IF NOT EXISTS `currency_rates` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `currency_code`  CHAR(3) NOT NULL,
        `rate_to_base`   DECIMAL(18,8) NOT NULL,
        `effective_date` DATE NOT NULL,
        `source`         VARCHAR(60) NOT NULL DEFAULT 'manual',
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_by`     INT UNSIGNED NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_cur_effective` (`currency_code`, `effective_date`),
        CONSTRAINT `fk_rate_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies`(`code`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  = currencies + currency_rates ready\n";

// Carry the previously-static helper values across so nothing changes visually.
$legacy = [
    ['USD','US Dollar','$',1.0000], ['INR','Indian Rupee','₹',83.5000], ['AED','UAE Dirham','د.إ',3.6700],
    ['EUR','Euro','€',0.9200], ['GBP','British Pound','£',0.7900], ['SAR','Saudi Riyal','﷼',3.7500],
    ['CAD','Canadian Dollar','CA$',1.3600], ['AUD','Australian Dollar','AU$',1.5400],
    ['JPY','Japanese Yen','¥',149.5000], ['CNY','Chinese Yuan','元',7.2400],
    ['MYR','Malaysian Ringgit','RM',4.7200], ['QAR','Qatari Riyal','﷼',3.6400],
];
$today = date('Y-m-d');
foreach ($legacy as $i => [$code, $name, $symbol, $rate]) {
    $db->prepare("INSERT IGNORE INTO currencies (code, name, symbol, decimal_places, is_base, sort_order) VALUES (?,?,?,?,?,?)")
       ->execute([$code, $name, $symbol, $code === 'JPY' ? 0 : 2, $code === 'USD' ? 1 : 0, ($i + 1) * 10]);
    $db->prepare("INSERT IGNORE INTO currency_rates (currency_code, rate_to_base, effective_date, source) VALUES (?,?,?,'seeded')")
       ->execute([$code, $rate, $today]);
}
echo "  ~ seeded " . count($legacy) . " currencies with today's rates\n";

echo "\n-- document_sequences (per company, per type) --\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `document_sequences` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id`   INT UNSIGNED NOT NULL,
        `doc_type`     VARCHAR(40) NOT NULL,
        `prefix`       VARCHAR(20) NOT NULL,
        `padding`      TINYINT UNSIGNED NOT NULL DEFAULT 4,
        `date_format`  VARCHAR(20) NOT NULL DEFAULT '',
        `next_number`  BIGINT UNSIGNED NOT NULL DEFAULT 1,
        `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_docseq` (`company_id`, `doc_type`),
        CONSTRAINT `fk_docseq_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  = document_sequences ready\n";

$docCols = 'id, company_code, invoice_prefix, invoice_padding, receipt_prefix, je_prefix, po_prefix, pr_prefix, gr_prefix, vendor_bill_prefix';
$companies = $db->query("SELECT $docCols FROM companies WHERE deleted_at IS NULL")->fetchAll();
$docTypes = [
    'customer_invoice'  => ['invoice_prefix', 'INV'],
    'credit_note'       => [null, 'CN'],
    'receipt'           => ['receipt_prefix', 'RCP'],
    'journal_entry'     => ['je_prefix', 'JV'],
    'purchase_order'    => ['po_prefix', 'PO'],
    'purchase_request'  => ['pr_prefix', 'PR'],
    'goods_receipt'     => ['gr_prefix', 'GRN'],
    'vendor_bill'       => ['vendor_bill_prefix', 'VB'],
    'customer'          => [null, 'CUS'],
    'vendor'            => [null, 'VND'],
];
$seeded = 0;
foreach ($companies as $co) {
    foreach ($docTypes as $type => [$prefCol, $fallback]) {
        $prefix = trim((string) ($prefCol ? ($co[$prefCol] ?? '') : ''));
        if ($prefix === '') { $prefix = $fallback; }
        $padding = ($type === 'customer_invoice') ? (int) ($co['invoice_padding'] ?: 4) : 4;
        // Upsert so re-running corrects rows seeded by an earlier, narrower column list.
        $db->prepare("
            INSERT INTO document_sequences (company_id, doc_type, prefix, padding, next_number)
            VALUES (?,?,?,?,1)
            ON DUPLICATE KEY UPDATE prefix = VALUES(prefix), padding = VALUES(padding)
        ")->execute([$co['id'], $type, strtoupper($prefix), $padding]);
        $seeded++;
    }
}
echo "  ~ upserted $seeded sequence rows for " . count($companies) . " companies\n";

echo "\n-- chart_of_accounts company dimension --\n";
addColumn($db, 'chart_of_accounts', 'company_id', 'INT UNSIGNED NULL', 'id');
addFk($db, 'chart_of_accounts', 'fk_coa_company', 'company_id', 'companies');
if (!hasIndex($db, 'chart_of_accounts', 'idx_coa_company')) {
    $db->exec("ALTER TABLE `chart_of_accounts` ADD INDEX `idx_coa_company` (`company_id`)");
    echo "  i chart_of_accounts.idx_coa_company\n";
}
addColumn($db, 'chart_of_accounts', 'deleted_at', 'TIMESTAMP NULL');

echo "\n-- provision financial years + periods for every active company --\n";

// `year_name` was globally unique, which makes it impossible for two companies to
// run a year with the same label. Scope uniqueness to the company instead.
$globalUnique = $db->query("
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = '$DB' AND table_name = 'financial_years'
      AND index_name = 'year_name' AND NON_UNIQUE = 0
")->fetchColumn();
if ($globalUnique) {
    $db->exec("ALTER TABLE `financial_years` DROP INDEX `year_name`");
    echo "  - dropped global UNIQUE on financial_years.year_name\n";
}
$composite = $db->query("
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = '$DB' AND table_name = 'financial_years' AND index_name = 'uq_fy_company_name'
")->fetchColumn();
if (!$composite) {
    $db->exec("ALTER TABLE `financial_years` ADD UNIQUE KEY `uq_fy_company_name` (`company_id`, `year_name`)");
    echo "  + UNIQUE (company_id, year_name)\n";
}
// One open year per company per date range.
$rangeUq = $db->query("
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = '$DB' AND table_name = 'financial_years' AND index_name = 'uq_fy_company_range'
")->fetchColumn();
if (!$rangeUq) {
    try {
        $db->exec("ALTER TABLE `financial_years` ADD UNIQUE KEY `uq_fy_company_range` (`company_id`, `start_date`, `end_date`)");
        echo "  + UNIQUE (company_id, start_date, end_date)\n";
    } catch (PDOException $e) {
        echo "  ! could not add range uniqueness (duplicate rows already present): " . $e->getMessage() . "\n";
    }
}

// Resolution order: the company's own fiscal start, else the app_settings month.
$fyMonthSetting = (int) $db->query("SELECT setting_value FROM app_settings WHERE setting_key='fiscal_year_start_month'")->fetchColumn();
$pattern = (string) $db->query("SELECT setting_value FROM app_settings WHERE setting_key='fiscal_year_pattern'")->fetchColumn();
$periodPattern = (string) $db->query("SELECT setting_value FROM app_settings WHERE setting_key='period_naming'")->fetchColumn();

$targets = $db->query("
    SELECT id, company_code, company_name, financial_year_start, periods_per_year
    FROM companies WHERE deleted_at IS NULL AND status = 'active'
    ORDER BY is_default DESC, id ASC
")->fetchAll();

$created = 0;
foreach ($targets as $co) {
    $startMonth = null;
    $startYear   = null;
    if ($co['financial_year_start'] && $co['financial_year_start'] !== '0000-00-00') {
        $startMonth = (int) date('n', strtotime($co['financial_year_start']));
        // Align to the most recent occurrence of that month, so the year covers today.
        $anchorYear = (int) date('Y');
        $candidate  = mktime(0, 0, 0, $startMonth, 1, $anchorYear);
        if ($candidate > time()) { $candidate = mktime(0, 0, 0, $startMonth, 1, $anchorYear - 1); }
        $startYear = (int) date('Y', $candidate);
    } else {
        $startMonth = $fyMonthSetting ?: 1;
        $candidate  = mktime(0, 0, 0, $startMonth, 1, (int) date('Y'));
        if ($candidate > time()) { $candidate = mktime(0, 0, 0, $startMonth, 1, (int) date('Y') - 1); }
        $startYear = (int) date('Y', $candidate);
    }

    $startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
    $endStamp  = strtotime('+' . 12 . ' months', strtotime($startDate));
    $endDate   = date('Y-m-d', strtotime('-1 day', $endStamp));
    $label     = str_replace(['{start}', '{end}'], [(string) $startYear, (string) ($startYear + 1)], $pattern ?: 'FY {start}-{end}');

    $exists = $db->prepare("SELECT id FROM financial_years WHERE company_id = ? AND start_date <= ? AND end_date >= ? LIMIT 1");
    $exists->execute([$co['id'], $startDate, $startDate]);
    $fyId = (int) $exists->fetchColumn();

    if (!$fyId) {
        $isDefault = (int) $db->query("SELECT COUNT(*) FROM financial_years WHERE company_id = {$co['id']}")->fetchColumn() === 0 ? 1 : 0;
        $ins = $db->prepare("INSERT INTO financial_years (company_id, year_code, year_name, start_date, end_date, status, is_default, created_at, updated_at) VALUES (?,?,?,?,?,'active',?,NOW(),NOW())");
        $ins->execute([$co['id'], 'FY' . $startYear . '-' . ($startYear + 1), $label, $startDate, $endDate, $isDefault]);
        $fyId = (int) $db->lastInsertId();
        $created++;
        echo "  + {$co['company_code']}: $label ($startDate → $endDate)" . ($isDefault ? ' [default]' : '') . "\n";
    } else {
        echo "  = {$co['company_code']}: covered by existing FY #$fyId\n";
    }

    // Monthly periods across the whole year.
    $have = (int) $db->query("SELECT COUNT(*) FROM accounting_periods WHERE financial_year_id = $fyId")->fetchColumn();
    if ($have > 0) { continue; }
    for ($i = 0; $i < 12; $i++) {
        $pStart = strtotime("+$i month", strtotime($startDate));
        $pEnd   = strtotime('-1 day', strtotime('+' . ($i + 1) . ' month', strtotime($startDate)));
        $pName  = str_replace(['{Month}', '{Year}'], [date('M', $pStart), date('Y', $pStart)], $periodPattern ?: '{Month} {Year}');
        $dup = $db->prepare("SELECT COUNT(*) FROM accounting_periods WHERE financial_year_id=? AND start_date=?");
        $dup->execute([$fyId, date('Y-m-d', $pStart)]);
        if ($dup->fetchColumn()) { continue; }
        $db->prepare("INSERT INTO accounting_periods (financial_year_id, period_name, start_date, end_date, status, reopen_count, created_at, updated_at) VALUES (?,?,?,?,'open',0,NOW(),NOW())")
           ->execute([$fyId, $pName, date('Y-m-d', $pStart), date('Y-m-d', $pEnd)]);
    }
    echo "    ~ periods provisioned for FY #$fyId\n";
}
echo "  $created financial year(s) created\n";

echo "\n-- posting permissions --\n";
$perm = $db->prepare("SELECT id FROM permissions WHERE module='company' AND name='switch' LIMIT 1");
$perm->execute();
if (!$perm->fetchColumn()) {
    $db->prepare("INSERT INTO permissions (module, name, created_at) VALUES ('company','switch',NOW())")->execute();
    echo "  + company.switch\n";
} else { echo "  = company.switch exists\n"; }
$pid = (int) $db->query("SELECT id FROM permissions WHERE module='company' AND name='switch'")->fetchColumn();
$sa  = (int) $db->query("SELECT id FROM roles WHERE name='Super Admin' LIMIT 1")->fetchColumn();
if ($sa && $pid) {
    $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id=? AND permission_id=?");
    $chk->execute([$sa, $pid]);
    if (!$chk->fetchColumn()) {
        $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)")->execute([$sa, $pid]);
        echo "  -> Super Admin granted company.switch\n";
    }
}

echo "\n== Done ==\n";
echo "  companies with an active FY now: " . $db->query("
    SELECT COUNT(DISTINCT c.id) FROM companies c
    JOIN financial_years fy ON fy.company_id = c.id AND fy.status='active'
    WHERE c.deleted_at IS NULL AND c.status='active'")->fetchColumn()
   . " of " . $db->query("SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL AND status='active'")->fetchColumn() . "\n";
