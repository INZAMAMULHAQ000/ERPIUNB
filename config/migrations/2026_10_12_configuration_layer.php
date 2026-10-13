<?php
/**
 * Configuration layer migration.
 *
 * Creates the lookup tables that replace the hardcoded option arrays, seeds
 * them from the values currently in code (so behaviour is identical on cutover),
 * aligns document_sequences.next_number with existing rows so the sequence-based
 * generator can never reuse or collide with a live number, and adds the
 * settings.* permissions.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_12_configuration_layer.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;

echo "== Configuration layer migration ==\n\n-- lookup_types + lookup_values --\n";

$db->exec("
    CREATE TABLE IF NOT EXISTS `lookup_types` (
        `type_key`    VARCHAR(60) NOT NULL,
        `label`       VARCHAR(160) NOT NULL,
        `description` VARCHAR(500) NULL,
        `group_name`  VARCHAR(60) NOT NULL DEFAULT 'general',
        `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`type_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$db->exec("
    CREATE TABLE IF NOT EXISTS `lookup_values` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `type_key`    VARCHAR(60) NOT NULL,
        `value_key`   VARCHAR(80) NOT NULL,
        `value_label` VARCHAR(200) NOT NULL,
        `extra`       JSON NULL,
        `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 100,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_lookup` (`type_key`, `value_key`),
        KEY `idx_lookup_active` (`type_key`, `is_active`, `sort_order`),
        CONSTRAINT `fk_lookup_type` FOREIGN KEY (`type_key`) REFERENCES `lookup_types`(`type_key`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  = lookup_types + lookup_values ready\n";

/** Seeded from the exact arrays in modules/companies/form.php and customers/form.php. */
$seed = [
    'company.entity_type' => ['Company master', 'Legal / registration form of a company', ['limited_liability' => 'Limited Liability (LLC/Ltd)', 'sole_proprietorship' => 'Sole Proprietorship', 'partnership' => 'Partnership', 'free_zone_establishment' => 'Free Zone Establishment', 'branch' => 'Branch of Foreign Company', 'subsidiary' => 'Subsidiary', 'holding' => 'Holding Company', 'ngo' => 'NGO / Non-Profit', 'government' => 'Government Body', 'trust' => 'Trust']],
    'company.category' => ['Company master', 'Role the entity plays in the group', ['operating' => 'Operating', 'holding' => 'Holding', 'service_provider' => 'Service Provider', 'special_purpose' => 'Special Purpose Vehicle', 'dormant' => 'Dormant']],
    'company.consolidation_method' => ['Company master', 'How the entity is consolidated (F13)', ['full' => 'Full consolidation (100% lines)', 'proportionate' => 'Proportionate / line-by-line', 'equity' => 'Equity method', 'none' => 'Not consolidated']],
    'company.framework' => ['Company master', 'Accounting reporting framework', ['IFRS' => 'IFRS', 'IFRS_for_SMEs' => 'IFRS for SMEs', 'Local_GAAP' => 'Local GAAP', 'US_GAAP' => 'US GAAP', 'ISFR' => 'ISFR (UAE)', 'None' => 'None / Not reported']],
    'company.tax_registration_type' => ['Company master', 'Tax registration posture', ['standard' => 'Standard', 'vat_registered' => 'VAT / GST registered', 'non_registered' => 'Not registered', 'exempt' => 'Exempt', 'reverse_charge' => 'Reverse charge only']],
    'company.vat_frequency' => ['Company master', 'VAT return cadence', ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'semi_annual' => 'Semi-annual', 'annual' => 'Annual', 'none' => 'Not applicable']],
    'company.e_invoicing_system' => ['Company master', 'Statutory e-invoicing regime', ['none' => 'No e-invoicing', 'zatca_fatoora' => 'ZATCA Fatoora (KSA)', 'gst_e_invoice' => 'GST e-Invoice (India)', 'fbr_iris' => 'FBR Iris (Pakistan)', 'peppol' => 'Peppol', 'other' => 'Other']],
    'company.bank_account_type' => ['Company master', 'Type of company bank account', ['current' => 'Current', 'savings' => 'Savings', 'margin' => 'Margin', 'escrow' => 'Escrow', 'fx' => 'FX', 'fixed_deposit' => 'Fixed Deposit']],
    'party.customer_type' => ['Customer master', 'Customer entity type', ['company' => 'Company', 'individual' => 'Individual', 'partnership' => 'Partnership', 'sole_proprietorship' => 'Sole Proprietorship', 'government' => 'Government Body', 'trust' => 'Trust / NGO']],
    'party.account_group' => ['Customer master', 'AR account group', ['trade' => 'Trade Customer', 'retail' => 'Retail / End Consumer', 'intercompany' => 'Inter-company', 'government' => 'Government', 'one_time' => 'One-Time Customer']],
    'party.lead_source' => ['Customer master', 'How the customer was acquired', ['referral' => 'Referral', 'website' => 'Website Enquiry', 'exhibition' => 'Exhibition / Trade Show', 'outbound' => 'Outbound Sales', 'partner' => 'Partner / Reseller', 'marketing_campaign' => 'Marketing Campaign', 'existing' => 'Existing Account']],
    'party.tax_category' => ['Customer master', 'Customer tax category', ['regular' => 'Regular', 'composition' => 'Composition Scheme', 'unregistered' => 'Unregistered', 'sez' => 'SEZ', 'export' => 'Export (Zero Rated)', 'deemed_export' => 'Deemed Export', 'overseas' => 'Overseas']],
    'party.risk_level' => ['Customer master', 'Credit risk grading', ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']],
    'party.kyc_status' => ['Customer master', 'KYC review state', ['not_started' => 'Not Started', 'pending' => 'Pending', 'submitted' => 'Submitted', 'verified' => 'Verified', 'rejected' => 'Rejected']],
    'party.company_size' => ['Customer master', 'Size band', ['micro' => 'Micro (< 10 staff)', 'small' => 'Small (10-49)', 'medium' => 'Medium (50-249)', 'large' => 'Large (250-999)', 'enterprise' => 'Enterprise (1000+)']],
    'party.credit_hold' => ['Customer master', 'Credit hold state', ['no' => 'No Hold', 'manual' => 'Manual Hold', 'automatic' => 'Automatic (over limit)']],
    'trade.payment_terms' => ['Trading terms', 'Shared by customers, companies and invoices. `days` drives due-date calculation.', ['Due on Receipt' => 'Due on Receipt', 'Net 7' => 'Net 7', 'Net 15' => 'Net 15', 'Net 30' => 'Net 30', 'Net 45' => 'Net 45', 'Net 60' => 'Net 60', 'Net 90' => 'Net 90', '2/10 Net 30' => '2/10 Net 30', 'End of Month Net 30' => 'End of Month Net 30', 'End of Month Net 60' => 'End of Month Net 60']],
    'trade.payment_method' => ['Trading terms', 'Settlement method', ['bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque', 'cash' => 'Cash', 'credit_card' => 'Credit Card', 'upi' => 'UPI / Wallet', 'dd' => 'Demand Draft', 'lc' => 'Letter of Credit']],
    'trade.billing_cycle' => ['Trading terms', 'Invoicing cadence', ['per_transaction' => 'Per Transaction', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual']],
    'trade.invoice_delivery' => ['Trading terms', 'How invoices are issued', ['email' => 'Email', 'print' => 'Printed Copy', 'portal' => 'Customer Portal', 'courier' => 'Courier']],
    'trade.price_list' => ['Trading terms', 'Pricing tier', ['standard' => 'Standard', 'preferred' => 'Preferred Partner', 'volume' => 'Volume Tier', 'contract' => 'Contract Priced', 'internal' => 'Internal / Cost']],
    'trade.shipping_condition' => ['Trading terms', 'Delivery speed', ['standard' => 'Standard', 'express' => 'Express', 'economy' => 'Economy', 'pickup' => 'Customer Pickup', 'freight' => 'Freight Collect']],
    'trade.incoterms' => ['Trading terms', 'Incoterms 2020 (ISO standard names)', ['EXW' => 'EXW — Ex Works', 'FCA' => 'FCA — Free Carrier', 'CPT' => 'CPT — Carriage Paid To', 'CIP' => 'CIP — Carriage & Insurance Paid', 'DAP' => 'DAP — Delivered at Place', 'DPU' => 'DPU — Delivered at Place Unloaded', 'DDP' => 'DDP — Delivered Duty Paid', 'FOB' => 'FOB — Free on Board', 'CIF' => 'CIF — Cost, Insurance & Freight']],
    'trade.contact_method' => ['Trading terms', 'Preferred contact channel', ['email' => 'Email', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'mail' => 'Postal Mail']],
    'party.credit_rating' => ['Customer master', 'Internal credit band', ['AAA' => 'AAA', 'AA' => 'AA', 'A' => 'A', 'BBB' => 'BBB', 'BB' => 'BB', 'B' => 'B', 'CCC' => 'CCC', 'Watch' => 'Watch', 'Critical' => 'Critical']],
];

$paymentDays = ['Due on Receipt' => 0, 'Net 7' => 7, 'Net 15' => 15, 'Net 30' => 30, 'Net 45' => 45, 'Net 60' => 60, 'Net 90' => 90, '2/10 Net 30' => 30, 'End of Month Net 30' => 30, 'End of Month Net 60' => 60];

$typeIns = $db->prepare("INSERT IGNORE INTO lookup_types (type_key, label, description, group_name) VALUES (?,?,?,?)");
$valIns  = $db->prepare("INSERT IGNORE INTO lookup_values (type_key, value_key, value_label, extra, sort_order) VALUES (?,?,?,?,?)");
$nTypes = 0; $nVals = 0;
foreach ($seed as $key => [$label, $desc, $opts]) {
    $group = explode('.', $key)[0];
    $typeIns->execute([$key, $label, $desc, $group]);
    $nTypes++;
    $i = 0;
    foreach ($opts as $vk => $vl) {
        $extra = ($key === 'trade.payment_terms' && isset($paymentDays[$vk]))
               ? json_encode(['days' => $paymentDays[$vk]]) : null;
        $valIns->execute([$key, $vk, $vl, $extra, (++$i) * 10]);
        $nVals++;
    }
}
echo "  ~ seeded $nTypes lookup types / $nVals values (INSERT IGNORE, existing edits preserved)\n";

echo "\n-- align document_sequences with live data --\n";
// Map each doc type to the table.column whose current numbers we must not collide with.
$map = [
    'customer_invoice' => ['customer_invoices', 'invoice_number'],
    'receipt'          => ['customer_receipts', 'receipt_number'],
    'purchase_order'   => ['purchase_orders', 'po_number'],
    'vendor_bill'      => ['vendor_bills', 'bill_number'],
    'credit_note'      => ['credit_notes', 'credit_note_number'],
];
$aligned = 0;
foreach ($map as $type => [$table, $col]) {
    $exists = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='$table' AND column_name='$col'")->fetchColumn();
    if (!$exists) { echo "  - skipped $type ($table.$col absent)\n"; continue; }

    // Prefer the true trailing digit run; MariaDB >= 10.0.5 has REGEXP_SUBSTR.
    $next = null;
    try {
        $next = (int) $db->query("SELECT COALESCE(MAX(CAST(REGEXP_SUBSTR(`$col`, '[0-9]+\$') AS UNSIGNED)), 0) FROM `$table`")->fetchColumn();
    } catch (Throwable $e) {
        $next = 0;
    }
    // A row count is a sound independent floor in case the regex path found nothing
    // (e.g. numbers that do not end in digits).
    $rows = (int) $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $next = max($next, $rows) + 1;

    $db->prepare("UPDATE document_sequences SET next_number = GREATEST(next_number, ?) WHERE doc_type = ?")->execute([$next, $type]);
    echo "  ~ $type: max-suffix/rows floor applied, next_number >= $next\n";
    $aligned++;
}
echo "  $aligned sequence(s) aligned to live data\n";

echo "\n-- settings permissions --\n";
$permIds = [];
foreach (['view', 'edit', 'manage_currencies', 'manage_sequences', 'manage_lookups'] as $p) {
    $s = $db->prepare("SELECT id FROM permissions WHERE module='settings' AND name=? LIMIT 1");
    $s->execute([$p]);
    if ($id = $s->fetchColumn()) { $permIds[$p] = (int) $id; echo "  = settings.$p (#$id)\n"; continue; }
    $db->prepare("INSERT INTO permissions (module, name, created_at) VALUES ('settings',?,NOW())")->execute([$p]);
    $permIds[$p] = (int) $db->lastInsertId();
    echo "  + settings.$p (#{$permIds[$p]})\n";
}
$sa = (int) $db->query("SELECT id FROM roles WHERE name='Super Admin' LIMIT 1")->fetchColumn();
foreach ($permIds as $pid) {
    $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id=? AND permission_id=?");
    $chk->execute([$sa, $pid]);
    if ($chk->fetchColumn()) continue;
    $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)")->execute([$sa, $pid]);
    echo "  -> Super Admin granted permission #$pid\n";
}
// Admins get read + edit on settings so the console is usable without Super Admin.
$ad = (int) $db->query("SELECT id FROM roles WHERE name='Admin' LIMIT 1")->fetchColumn();
if ($ad) {
    foreach (['view', 'edit'] as $p) {
        $pid = $permIds[$p] ?? 0;
        if (!$pid) continue;
        $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id=? AND permission_id=?");
        $chk->execute([$ad, $pid]);
        if ($chk->fetchColumn()) continue;
        $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)")->execute([$ad, $pid]);
        echo "  -> Admin granted settings.$p\n";
    }
}

echo "\n== Done: " . $db->query("SELECT COUNT(*) FROM lookup_values WHERE is_active=1")->fetchColumn() . " active lookup values available ==\n";
