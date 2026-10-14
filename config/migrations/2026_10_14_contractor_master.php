<?php
/**
 * Contractor Master migration — adds the contingent-workforce register that
 * `people.category = 'contractor'` has always pointed at but never had.
 *
 * Modelled on Oracle Fusion HCM's Worker Employment Contract business object
 * (contract number, type, status, effective dates, extension count / period /
 * reason, end reason, document status, contractual job title, approval and
 * audit columns) plus the placement, rate and not-to-exceed dimensions that
 * contingent-workforce platforms (Fieldglass / Coupa, Ariba Staffing, Workday
 * Contingent Labor) treat as mandatory.
 *
 * Structural decisions, and why:
 *
 *  - `contractors` is a GLOBAL master, like `clients` and `vendors`. A person
 *    exists once for the group; the entity relationship lives on the contract.
 *  - `contractor_contracts.company_id` is stamped on create, mirroring
 *    `purchase_orders`, because the commitment belongs to one legal entity.
 *  - Placement is modelled with `agency_vendor_id` -> `vendors`. Agency-supplied
 *    contractors are paid through the existing procurement chain (PO -> vendor
 *    bill), so no parallel supplier master is introduced.
 *  - Exactly one current contract per contractor is enforced by the database
 *    with UNIQUE(contractor_id, is_current): non-current rows store NULL, and
 *    MySQL allows unlimited NULLs in a unique index.
 *  - Rate changes are a `contractor_rate_schedules` child rather than an edit
 *    of one number, so a raised rate never silently rewrites history.
 *  - Compliance documents keep `expires_on` (which `employee_documents` lacks)
 *    because a contractor's right-to-work is what actually expires.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_14_contractor_master.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;
$added = 0;
$skipped = 0;

function hasTable($db, $table) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $s->execute([$DB, $table]);
    return (bool) $s->fetchColumn();
}

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

function hasConstraint($db, $table, $name) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}

echo "== Contractor Master migration ==\n";

/* ------------------------------------------------------------------ */
echo "\n-- contractors: the contingent-worker register --\n";

if (!hasTable($db, 'contractors')) {
    $db->exec("
    CREATE TABLE `contractors` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `contractor_code` varchar(50) NOT NULL,
      `first_name` varchar(100) NOT NULL,
      `last_name` varchar(100) DEFAULT NULL,
      `gender` enum('male','female','other') DEFAULT NULL,
      `date_of_birth` date DEFAULT NULL,
      `nationality` varchar(100) DEFAULT NULL,
      `email` varchar(255) DEFAULT NULL,
      `phone` varchar(20) DEFAULT NULL,
      `address` varchar(500) DEFAULT NULL,
      `work_location` varchar(100) DEFAULT NULL,
      `photo_path` varchar(500) DEFAULT NULL,
      `contractor_category` varchar(60) DEFAULT NULL,
      `specialisation` varchar(200) DEFAULT NULL,
      `emirates_id_number` varchar(20) DEFAULT NULL,
      `emirates_id_expiry` date DEFAULT NULL,
      `passport_number` varchar(50) DEFAULT NULL,
      `passport_expiry` date DEFAULT NULL,
      `visa_number` varchar(50) DEFAULT NULL,
      `visa_type` varchar(30) DEFAULT NULL,
      `visa_expiry` date DEFAULT NULL,
      `work_permit_number` varchar(50) DEFAULT NULL,
      `work_permit_expiry` date DEFAULT NULL,
      `labor_card_number` varchar(50) DEFAULT NULL,
      `labor_card_expiry` date DEFAULT NULL,
      `trn_number` varchar(40) DEFAULT NULL,
      `bank_name` varchar(150) DEFAULT NULL,
      `bank_account_number` varchar(60) DEFAULT NULL,
      `account_holder_name` varchar(150) DEFAULT NULL,
      `status` enum('active','suspended','terminated') NOT NULL DEFAULT 'active',
      `termination_date` date DEFAULT NULL,
      `termination_reason` varchar(255) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `created_by` int(10) unsigned DEFAULT NULL,
      `updated_by` int(10) unsigned DEFAULT NULL,
      `deleted_at` timestamp NULL DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_contractor_code` (`contractor_code`),
      UNIQUE KEY `uq_contractor_email` (`email`),
      KEY `idx_contractor_name` (`first_name`, `last_name`),
      KEY `idx_contractor_status` (`status`),
      KEY `idx_contractor_category` (`contractor_category`),
      KEY `idx_contractor_emirates_id` (`emirates_id_number`),
      KEY `idx_contractor_passport` (`passport_number`),
      KEY `idx_contractor_deleted` (`deleted_at`),
      KEY `fk_contractor_created_by` (`created_by`),
      KEY `fk_contractor_updated_by` (`updated_by`),
      CONSTRAINT `fk_contractor_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contractor_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  + table contractors\n";
} else {
    echo "  = table contractors exists\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- contractor_contracts: work contract / engagement --\n";

if (!hasTable($db, 'contractor_contracts')) {
    $db->exec("
    CREATE TABLE `contractor_contracts` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `contractor_id` int(10) unsigned NOT NULL,
      `contract_number` varchar(50) NOT NULL,
      `company_id` int(10) unsigned DEFAULT NULL,
      `title` varchar(200) NOT NULL,
      `contract_type` varchar(60) DEFAULT NULL,
      `scope_of_work` text DEFAULT NULL,
      `status` enum('draft','active','completed','terminated') NOT NULL DEFAULT 'draft',
      `is_current` tinyint(1) unsigned DEFAULT NULL,
      `supply_source` enum('direct','agency') NOT NULL DEFAULT 'direct',
      `agency_vendor_id` int(10) unsigned DEFAULT NULL,
      `department_id` int(10) unsigned DEFAULT NULL,
      `designation_id` int(10) unsigned DEFAULT NULL,
      `contractual_job_title` varchar(150) DEFAULT NULL,
      `project_id` int(10) unsigned DEFAULT NULL,
      `manager_employee_id` int(10) unsigned DEFAULT NULL,
      `purchase_order_id` int(10) unsigned DEFAULT NULL,
      `po_reference` varchar(60) DEFAULT NULL,
      `start_date` date NOT NULL,
      `end_date` date DEFAULT NULL,
      `work_arrangement` varchar(40) DEFAULT NULL,
      `fte_allocation` decimal(5,2) DEFAULT NULL,
      `rate_basis` varchar(40) DEFAULT NULL,
      `rate_amount` decimal(15,2) DEFAULT NULL,
      `currency_code` char(3) DEFAULT NULL,
      `estimated_value` decimal(15,2) DEFAULT NULL,
      `not_to_exceed_amount` decimal(15,2) DEFAULT NULL,
      `billing_frequency` varchar(40) DEFAULT NULL,
      `payment_terms` varchar(60) DEFAULT NULL,
      `timesheet_required` tinyint(1) NOT NULL DEFAULT 0,
      `security_clearance` varchar(60) DEFAULT NULL,
      `badge_number` varchar(60) DEFAULT NULL,
      `extension_count` smallint(5) unsigned NOT NULL DEFAULT 0,
      `extension_limit` smallint(5) unsigned DEFAULT NULL,
      `extension_period_days` smallint(5) unsigned DEFAULT NULL,
      `last_extended_on` date DEFAULT NULL,
      `last_extension_reason` varchar(255) DEFAULT NULL,
      `approved_by` int(10) unsigned DEFAULT NULL,
      `approved_at` datetime DEFAULT NULL,
      `ended_on` date DEFAULT NULL,
      `end_reason` varchar(255) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `created_by` int(10) unsigned DEFAULT NULL,
      `updated_by` int(10) unsigned DEFAULT NULL,
      `deleted_at` timestamp NULL DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_contract_number` (`contract_number`),
      UNIQUE KEY `uq_one_current_contract` (`contractor_id`, `is_current`),
      KEY `idx_contract_status_dates` (`status`, `start_date`, `end_date`),
      KEY `idx_contract_agency` (`agency_vendor_id`),
      KEY `idx_contract_company` (`company_id`),
      KEY `idx_contract_project` (`project_id`),
      KEY `idx_contract_po` (`purchase_order_id`),
      KEY `fk_contract_department` (`department_id`),
      KEY `fk_contract_designation` (`designation_id`),
      KEY `fk_contract_manager` (`manager_employee_id`),
      KEY `fk_contract_approved_by` (`approved_by`),
      KEY `fk_contract_created_by` (`created_by`),
      KEY `fk_contract_updated_by` (`updated_by`),
      KEY `fk_contract_currency` (`currency_code`),
      CONSTRAINT `fk_contract_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_contract_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
      CONSTRAINT `fk_contract_agency` FOREIGN KEY (`agency_vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_designation` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_manager` FOREIGN KEY (`manager_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_purchase_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contract_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  + table contractor_contracts\n";
} else {
    echo "  = table contractor_contracts exists\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- contractor_rate_schedules: dated rate history --\n";

// Constraint names are unique per *database* in InnoDB, not per table, so these
// are prefixed fk_ctr_* — plain fk_rate_* collides with currency_rates.fk_rate_currency.

if (!hasTable($db, 'contractor_rate_schedules')) {
    $db->exec("
    CREATE TABLE `contractor_rate_schedules` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `contract_id` int(10) unsigned NOT NULL,
      `valid_from` date NOT NULL,
      `valid_to` date DEFAULT NULL,
      `rate_basis` varchar(40) DEFAULT NULL,
      `rate_amount` decimal(15,2) NOT NULL,
      `currency_code` char(3) DEFAULT NULL,
      `reason` varchar(255) DEFAULT NULL,
      `created_by` int(10) unsigned DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_rate_valid_from` (`contract_id`, `valid_from`),
      KEY `idx_rate_contract` (`contract_id`, `valid_from`),
      KEY `fk_ctr_rate_created_by` (`created_by`),
      KEY `fk_ctr_rate_currency` (`currency_code`),
      CONSTRAINT `fk_ctr_rate_contract` FOREIGN KEY (`contract_id`) REFERENCES `contractor_contracts` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_ctr_rate_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON DELETE SET NULL,
      CONSTRAINT `fk_ctr_rate_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  + table contractor_rate_schedules\n";
} else {
    echo "  = table contractor_rate_schedules exists\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- contractor_documents: compliance scans with expiry --\n";

if (!hasTable($db, 'contractor_documents')) {
    $db->exec("
    CREATE TABLE `contractor_documents` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `contractor_id` int(10) unsigned NOT NULL,
      `contract_id` int(10) unsigned DEFAULT NULL,
      `document_type` varchar(50) NOT NULL,
      `document_name` varchar(255) NOT NULL,
      `file_path` varchar(500) NOT NULL,
      `file_size` int(11) DEFAULT NULL,
      `mime_type` varchar(100) DEFAULT NULL,
      `expires_on` date DEFAULT NULL,
      `notes` varchar(255) DEFAULT NULL,
      `uploaded_by` int(10) unsigned DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_contractor_doc_type` (`contractor_id`, `document_type`),
      KEY `idx_contractor_doc_expiry` (`expires_on`),
      KEY `fk_contractor_doc_contract` (`contract_id`),
      KEY `fk_contractor_doc_user` (`uploaded_by`),
      CONSTRAINT `fk_contractor_doc_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_contractor_doc_contract` FOREIGN KEY (`contract_id`) REFERENCES `contractor_contracts` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_contractor_doc_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  + table contractor_documents\n";
} else {
    echo "  = table contractor_documents exists\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- people: give the contractor category somewhere to point --\n";

addColumn($db, 'people', 'contractor_id', "INT UNSIGNED NULL", 'customer_id');
addIndex($db, 'people', 'idx_people_contractor', '`contractor_id`');
if (!hasConstraint($db, 'people', 'fk_people_contractor')) {
    $db->exec("ALTER TABLE `people` ADD CONSTRAINT `fk_people_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL");
    echo "  fk people.contractor_id -> contractors.id\n";
}

// A contractor created from the People directory may carry a login, exactly as
// employees do, so the register keeps its own link back to users.
addColumn($db, 'contractors', 'user_id', "INT UNSIGNED NULL", 'status');
addIndex($db, 'contractors', 'idx_contractor_user', '`user_id`');
if (!hasConstraint($db, 'contractors', 'fk_contractor_user')) {
    $db->exec("ALTER TABLE `contractors` ADD CONSTRAINT `fk_contractor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
    echo "  fk contractors.user_id -> users.id\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- lookups: contractor classification lists --\n";

$seed = [
    'contractor.category' => ['Contractor Master', 'Trade or skill classification of a contingent worker.', [
        'consultant'    => 'Consultant',
        'technical'     => 'Technical / Engineering',
        'it_services'   => 'IT Services',
        'creative'      => 'Creative / Media',
        'administrative'=> 'Administrative Support',
        'skilled_labour'=> 'Skilled Labour',
        'driver'        => 'Driver / Logistics',
        'training'      => 'Training',
        'other'         => 'Other',
    ]],
    'contractor.contract_type' => ['Contractor Master', 'Commercial form the engagement is contracted under.', [
        'statement_of_work'      => 'Statement of Work (SOW)',
        'purchase_order'         => 'Purchase Order',
        'direct_engagement'      => 'Direct Engagement',
        'retainer'               => 'Retainer',
        'framework_agreement'    => 'Framework Agreement',
        'master_service_agreement'=> 'Master Service Agreement (MSA)',
        'staffing_placement'     => 'Agency Staffing Placement',
    ]],
    'contractor.rate_basis' => ['Contractor Master', 'Unit the rate is charged in.', [
        'hourly'            => 'Hourly',
        'daily'             => 'Daily',
        'weekly'            => 'Weekly',
        'monthly'           => 'Monthly',
        'fixed_deliverable' => 'Fixed / Deliverable',
        'milestone'         => 'Milestone',
    ]],
    'contractor.work_arrangement' => ['Contractor Master', 'Where the work is performed.', [
        'on_site'  => 'On-site',
        'off_site' => 'Off-site',
        'hybrid'   => 'Hybrid',
        'remote'   => 'Remote',
    ]],
    'contractor.billing_frequency' => ['Contractor Master', 'How often the contractor or agency invoices.', [
        'weekly'          => 'Weekly',
        'bi_weekly'       => 'Every two weeks',
        'monthly'         => 'Monthly',
        'quarterly'       => 'Quarterly',
        'upon_milestone'  => 'Upon milestone',
        'upon_delivery'   => 'Upon delivery',
    ]],
    'contractor.end_reason' => ['Contractor Master', 'Why a contract was ended.', [
        'completed'            => 'Scope completed',
        'expiry'               => 'Contract expired',
        'budget_withdrawn'     => 'Budget withdrawn',
        'performance'          => 'Performance',
        'project_cancelled'    => 'Project cancelled',
        'converted_to_employee'=> 'Converted to employee',
        'compliance'           => 'Compliance / documentation',
        'other'                => 'Other',
    ]],
    'contractor.document_type' => ['Contractor Master', 'Attachments that are not one-per-contractor compliance scans.', [
        'certificate'      => 'Professional Certificate',
        'insurance'        => 'Insurance Certificate',
        'nda'              => 'Non-Disclosure Agreement',
        'timesheet_proof'  => 'Timesheet Evidence',
        'invoice_copy'     => 'Invoice Copy',
        'trade_license'    => 'Trade Licence / Establishment Card',
        'other'            => 'Other',
    ]],
];

$typeIns = $db->prepare("
    INSERT IGNORE INTO lookup_types (type_key, label, description, group_name, is_active, created_at)
    VALUES (?, ?, ?, ?, 1, NOW())");
$valIns = $db->prepare("
    INSERT IGNORE INTO lookup_values (type_key, value_key, value_label, is_active, sort_order, created_at)
    VALUES (?, ?, ?, 1, ?, NOW())");

foreach ($seed as $typeKey => [$label, $description, $values]) {
    $typeIns->execute([$typeKey, $label, $description, 'contractor']);
    foreach (array_keys($values) as $i => $vKey) {
        $valIns->execute([$typeKey, $vKey, $values[$vKey], ($i + 1) * 10]);
    }
    echo "  " . str_pad($typeKey, 30) . count($values) . " values\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- document_sequences: CTR codes and CTRT contract numbers --\n";

$specs = [
    'contractor'          => ['CTR',  4, 'Y', '{prefix}{period}{seq}'],        // CTR20260001
    'contractor_contract' => ['CTRT', 4, 'Y', '{prefix}-{period}-{seq}'],       // CTRT-2026-0001
];

$companyIds = $db->query("SELECT id FROM companies WHERE deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
if (!$companyIds) {
    $companyIds = [$db->query("SELECT id FROM companies ORDER BY id LIMIT 1")->fetchColumn()];
}

$seqIns = $db->prepare("
    INSERT INTO document_sequences (company_id, doc_type, period_key, prefix, padding, date_format, format, next_number)
    VALUES (?,?,?,?,?,?,?,1)
    ON DUPLICATE KEY UPDATE prefix = VALUES(prefix), padding = VALUES(padding),
        date_format = VALUES(date_format), format = VALUES(format)
");
foreach ($companyIds as $cid) {
    foreach ($specs as $type => [$prefix, $padding, $df, $fmt]) {
        $seqIns->execute([$cid, $type, '', $prefix, $padding, $df, $fmt]);
    }
    echo "  + sequences for company #$cid\n";
}

// Raise each counter above numbers already present so a re-run never collides
// with codes that were inserted by hand or by an earlier counter value.
$floorTargets = [
    'contractor'          => ['contractors', 'contractor_code', 'CTR%'],
    'contractor_contract' => ['contractor_contracts', 'contract_number', 'CTRT%'],
];
foreach ($floorTargets as $type => [$table, $column, $like]) {
    foreach ($companyIds as $cid) {
        $stmt = $db->prepare("
            SELECT MAX(CAST(SUBSTRING(`$column`, -4) AS UNSIGNED))
              FROM `$table`
             WHERE `$column` LIKE ?
        ");
        $stmt->execute([$like]);
        $highest = (int) $stmt->fetchColumn();
        if ($highest > 0) {
            $db->prepare("UPDATE document_sequences SET next_number = GREATEST(next_number, ?) WHERE company_id = ? AND doc_type = ?")
               ->execute([$highest + 1, $cid, $type]);
            echo "  ~ $type counter floored at " . ($highest + 1) . " (company #$cid)\n";
        }
    }
}

/* ------------------------------------------------------------------ */
echo "\n-- permissions --\n";

$permIds = [];
foreach (['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 1, 'manage_contracts' => 1] as $pname => $_) {
    $s = $db->prepare("SELECT id FROM permissions WHERE module = 'contractors' AND name = ? LIMIT 1");
    $s->execute([$pname]);
    $id = $s->fetchColumn();
    if ($id) { echo "  = contractors.$pname exists (#$id)\n"; $permIds[$pname] = (int)$id; continue; }
    $ins = $db->prepare("INSERT INTO permissions (module, name, created_at) VALUES ('contractors', ?, NOW())");
    $ins->execute([$pname]);
    $permIds[$pname] = (int)$db->lastInsertId();
    echo "  + contractors.$pname (#{$permIds[$pname]})\n";
}

$grants = [];
$sa = $db->prepare("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1"); $sa->execute();
if ($saId = $sa->fetchColumn()) { foreach ($permIds as $pid) $grants[(int)$saId][] = $pid; }
$ad = $db->prepare("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1"); $ad->execute();
if ($adId = $ad->fetchColumn()) {
    foreach (['view', 'create', 'edit', 'manage_contracts'] as $p) if (isset($permIds[$p])) $grants[(int)$adId][] = $permIds[$p];
}
foreach ($grants as $roleId => $pids) {
    foreach (array_unique($pids) as $pid) {
        $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $chk->execute([$roleId, $pid]);
        if ($chk->fetchColumn()) continue;
        $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$roleId, $pid]);
        echo "  grant role #$roleId <- permission #$pid\n";
    }
}

echo "\n== Done: $added columns added, $skipped already present ==\n";
