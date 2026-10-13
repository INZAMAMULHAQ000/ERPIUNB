<?php
$isEdit = isset($_GET['id']) && (int) $_GET['id'] > 0;
$pageTitle = $isEdit ? 'Edit Customer' : 'New Customer';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission($isEdit ? 'customers.edit' : 'customers.create')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();

// ————— Reference data —————
$currencies = getCurrencies();
$employees  = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$accounts   = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' ORDER BY account_code")->fetchAll();
$users      = $db->query("SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$parentStmt = $db->prepare("SELECT id, client_code, company_name FROM clients WHERE deleted_at IS NULL AND id <> ? ORDER BY company_name");
$parentStmt->execute([$isEdit ? (int) $_GET['id'] : 0]);
$parents = $parentStmt->fetchAll();

$exposure = ['outstanding' => 0.0, 'overdue' => 0.0, 'invoices' => 0];
if ($isEdit) {
    $e = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft','void') THEN balance_amount ELSE 0 END), 0) AS outstanding,
               COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft','void') AND due_date < CURDATE() THEN balance_amount ELSE 0 END), 0) AS overdue,
               COUNT(*) AS invoices
        FROM customer_invoices WHERE client_id = ?");
    $e->execute([(int) $_GET['id']]);
    $exposure = $e->fetch() ?: $exposure;
}

$customer = [
    'customer_type' => 'company', 'account_group' => 'trade', 'status' => 'active',
    'currency_code' => getActiveCurrency(), 'payment_terms' => 'Net 30', 'payment_term_days' => 30,
    'billing_cycle' => 'per_transaction', 'invoice_delivery' => 'email', 'credit_hold' => 'no',
    'collection_priority' => 3, 'allow_partial_payment' => 1, 'tax_category' => 'regular',
    'kyc_status' => 'not_started', 'risk_level' => 'low', 'preferred_contact_method' => 'email',
    'is_one_time' => 0, 'consolidated_billing' => 0, 'tax_inclusive' => 0,
    'marketing_consent' => 0, 'data_processing_consent' => 0,
    'credit_limit' => 0, 'interest_rate' => 0, 'cash_discount_percent' => 0, 'discount_days' => 0,
    'withholding_tax_rate' => 0, 'default_discount_percent' => 0, 'commission_rate' => 0,
    'delivery_lead_time_days' => 0, 'country' => 'India',
];

if ($isEdit) {
    $s = $db->prepare("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL");
    $s->execute([(int) $_GET['id']]);
    $found = $s->fetch();
    if (!$found) { flash('notfound', 'Customer not found.', 'error'); to('modules/customers/index.php'); }
    $customer = array_merge($customer, $found);
}

// ————— Option sets —————
$optType       = ['company' => 'Company', 'individual' => 'Individual', 'partnership' => 'Partnership', 'sole_proprietorship' => 'Sole Proprietorship', 'government' => 'Government Body', 'trust' => 'Trust / NGO'];
$optGroup      = ['trade' => 'Trade Customer', 'retail' => 'Retail / End Consumer', 'intercompany' => 'Inter-company', 'government' => 'Government', 'one_time' => 'One-Time Customer'];
$optStatus     = ['active' => 'Active', 'prospect' => 'Prospect', 'inactive' => 'Inactive', 'blocked' => 'Blocked', 'archived' => 'Archived'];
$optLead       = ['referral' => 'Referral', 'website' => 'Website Enquiry', 'exhibition' => 'Exhibition / Trade Show', 'outbound' => 'Outbound Sales', 'partner' => 'Partner / Reseller', 'marketing_campaign' => 'Marketing Campaign', 'existing' => 'Existing Account'];
$optTaxCat     = ['regular' => 'Regular', 'composition' => 'Composition Scheme', 'unregistered' => 'Unregistered', 'sez' => 'SEZ', 'export' => 'Export (Zero Rated)', 'deemed_export' => 'Deemed Export', 'overseas' => 'Overseas'];
$optPayTerms   = ['Due on Receipt' => 0, 'Net 7' => 7, 'Net 14' => 14, 'Net 15' => 15, 'Net 30' => 30, 'Net 45' => 45, 'Net 60' => 60, 'Net 90' => 90, '2/10 Net 30' => 30, 'End of Month Net 30' => 30, 'End of Month Net 60' => 60];
$optPayMethod  = ['bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque', 'cash' => 'Cash', 'credit_card' => 'Credit Card', 'upi' => 'UPI / Wallet', 'dd' => 'Demand Draft', 'lc' => 'Letter of Credit'];
$optBilling    = ['per_transaction' => 'Per Transaction', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
$optDelivery   = ['email' => 'Email', 'print' => 'Printed Copy', 'portal' => 'Customer Portal', 'courier' => 'Courier'];
$optCreditHold = ['no' => 'No Hold', 'manual' => 'Manual Hold', 'automatic' => 'Automatic (over limit)'];
$optRisk       = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
$optKyc        = ['not_started' => 'Not Started', 'pending' => 'Pending', 'submitted' => 'Submitted', 'verified' => 'Verified', 'rejected' => 'Rejected'];
$optSize       = ['micro' => 'Micro (< 10 staff)', 'small' => 'Small (10–49)', 'medium' => 'Medium (50–249)', 'large' => 'Large (250–999)', 'enterprise' => 'Enterprise (1000+)'];
$optChannel    = ['direct' => 'Direct', 'reseller' => 'Reseller', 'distributor' => 'Distributor', 'online' => 'Online', 'oem' => 'OEM'];
$optShipCond   = ['standard' => 'Standard', 'express' => 'Express', 'economy' => 'Economy', 'pickup' => 'Customer Pickup', 'freight' => 'Freight Collect'];
$optInco       = ['EXW' => 'EXW — Ex Works', 'FCA' => 'FCA — Free Carrier', 'CPT' => 'CPT — Carriage Paid To', 'CIP' => 'CIP — Carriage & Insurance Paid', 'DAP' => 'DAP — Delivered at Place', 'DPU' => 'DPU — Delivered at Place Unloaded', 'DDP' => 'DDP — Delivered Duty Paid', 'FOB' => 'FOB — Free on Board', 'CIF' => 'CIF — Cost, Insurance & Freight'];
$optContact    = ['email' => 'Email', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'mail' => 'Postal Mail'];
$optLang       = ['English' => 'English', 'Arabic' => 'Arabic', 'French' => 'French', 'Hindi' => 'Hindi', 'Urdu' => 'Urdu', 'German' => 'German', 'Spanish' => 'Spanish', 'Chinese' => 'Chinese'];
$optTz         = ['Asia/Kolkata' => 'IST (UTC+5:30)', 'Asia/Dubai' => 'GST (UTC+4)', 'Europe/London' => 'GMT/BST', 'America/New_York' => 'ET (UTC-5)', 'America/Los_Angeles' => 'PT (UTC-8)', 'Asia/Singapore' => 'SGT (UTC+8)', 'Australia/Sydney' => 'AEST (UTC+10)'];
$optPriceList  = ['standard' => 'Standard', 'preferred' => 'Preferred Partner', 'volume' => 'Volume Tier', 'contract' => 'Contract Priced', 'internal' => 'Internal / Cost'];

// ————— Field schema: drives both rendering and the save whitelist —————
$sections = [
    ['title' => 'Identity & Classification', 'icon' => 'fa-id-badge', 'fields' => [
        ['n' => 'company_name',   'l' => 'Customer Name', 't' => 'text', 'span' => 2, 'req' => true],
        ['n' => 'legal_name',     'l' => 'Legal / Registered Name', 't' => 'text', 'span' => 2],
        ['n' => 'trade_name',     'l' => 'Trade / Brand Name', 't' => 'text', 'span' => 2],
        ['n' => 'search_term',    'l' => 'Search Term', 't' => 'text', 'span' => 1, 'hint' => 'Short alias used when searching'],
        ['n' => 'customer_type',  'l' => 'Customer Type', 't' => 'select', 'opts' => $optType, 'span' => 1],
        ['n' => 'account_group',  'l' => 'Account Group', 't' => 'select', 'opts' => $optGroup, 'span' => 1],
        ['n' => 'segment',        'l' => 'Segment', 't' => 'text', 'span' => 1, 'list' => 'seglist', 'hint' => 'e.g. Enterprise, SMB, Startup'],
        ['n' => 'status',         'l' => 'Status', 't' => 'select', 'opts' => $optStatus, 'span' => 1],
        ['n' => 'parent_client_id','l' => 'Parent / Group Account', 't' => 'select', 'opts' => array_column($parents, 'company_name', 'id'), 'span' => 1, 'cast' => 'int', 'blank' => '— Independent —'],
        ['n' => 'customer_since', 'l' => 'Customer Since', 't' => 'date', 'span' => 1],
        ['n' => 'industry',       'l' => 'Industry', 't' => 'text', 'span' => 1, 'list' => 'indlist'],
        ['n' => 'lead_source',    'l' => 'Lead Source', 't' => 'select', 'opts' => $optLead, 'span' => 1, 'blank' => '— Not recorded —'],
        ['n' => 'is_one_time',    'l' => 'One-time customer', 't' => 'checkbox', 'span' => 1],
    ]],
    ['title' => 'Primary Contact', 'icon' => 'fa-address-card', 'fields' => [
        ['n' => 'contact_person', 'l' => 'Contact Person', 't' => 'text', 'span' => 1],
        ['n' => 'email',          'l' => 'Email', 't' => 'email', 'span' => 1],
        ['n' => 'phone',          'l' => 'Phone', 't' => 'tel', 'span' => 1],
        ['n' => 'alternate_phone','l' => 'Alternate Phone', 't' => 'tel', 'span' => 1],
        ['n' => 'whatsapp',       'l' => 'WhatsApp', 't' => 'tel', 'span' => 1],
        ['n' => 'website',        'l' => 'Website', 't' => 'text', 'span' => 1, 'ph' => 'https://'],
        ['n' => 'linkedin_url',   'l' => 'LinkedIn', 't' => 'text', 'span' => 1, 'ph' => 'https://'],
        ['n' => 'preferred_contact_method', 'l' => 'Preferred Contact Method', 't' => 'select', 'opts' => $optContact, 'span' => 1],
        ['n' => 'preferred_language', 'l' => 'Language', 't' => 'select', 'opts' => $optLang, 'span' => 1, 'blank' => '— Default —'],
        ['n' => 'timezone',       'l' => 'Timezone', 't' => 'select', 'opts' => $optTz, 'span' => 1, 'blank' => '— Not set —'],
    ]],
    ['title' => 'Billing Address', 'icon' => 'fa-location-dot', 'fields' => [
        ['n' => 'address_line1', 'l' => 'Address Line 1', 't' => 'text', 'span' => 2],
        ['n' => 'address_line2', 'l' => 'Address Line 2', 't' => 'text', 'span' => 2],
        ['n' => 'city',          'l' => 'City', 't' => 'text', 'span' => 1],
        ['n' => 'state',         'l' => 'State / Province', 't' => 'text', 'span' => 1],
        ['n' => 'postal_code',   'l' => 'Postal Code', 't' => 'text', 'span' => 1],
        ['n' => 'country',       'l' => 'Country', 't' => 'text', 'span' => 1, 'list' => 'ctrylist'],
    ]],
    ['title' => 'Registration & Tax', 'icon' => 'fa-file-invoice', 'fields' => [
        ['n' => 'registration_number', 'l' => 'Company / CIN Registration', 't' => 'text', 'span' => 1],
        ['n' => 'tax_id',              'l' => 'Tax Identification No.', 't' => 'text', 'span' => 1],
        ['n' => 'tax_id_secondary',    'l' => 'Secondary Tax ID (PAN)', 't' => 'text', 'span' => 1],
        ['n' => 'gst_vat_number',      'l' => 'GSTIN / VAT No.', 't' => 'text', 'span' => 1, 'hint' => 'Printed on tax invoices'],
        ['n' => 'tax_category',        'l' => 'Tax Category', 't' => 'select', 'opts' => $optTaxCat, 'span' => 1],
        ['n' => 'tax_jurisdiction',    'l' => 'Tax Jurisdiction', 't' => 'text', 'span' => 1],
        ['n' => 'state_code',          'l' => 'Tax State Code', 't' => 'text', 'span' => 1],
        ['n' => 'tax_registration_date','l' => 'Tax Registration Date', 't' => 'date', 'span' => 1],
        ['n' => 'tax_exemption_ref',   'l' => 'Exemption Certificate Ref.', 't' => 'text', 'span' => 1],
        ['n' => 'tax_exemption_upto',  'l' => 'Exemption Valid Until', 't' => 'date', 'span' => 1],
        ['n' => 'withholding_tax_rate','l' => 'Default WHT / TDS %', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'export_code',         'l' => 'Import / Export Code (IEC)', 't' => 'text', 'span' => 1],
    ]],
    ['title' => 'Financial & Receivables', 'icon' => 'fa-coins', 'fields' => [
        ['n' => 'currency_code',  'l' => 'Billing Currency', 't' => 'select', 'opts' => array_map(fn($c) => $c['name'] . ' (' . $c['symbol'] . ')', $currencies), 'keys' => array_keys($currencies), 'span' => 1],
        ['n' => 'payment_terms',  'l' => 'Payment Terms', 't' => 'select', 'opts' => $optPayTerms, 'span' => 1],
        ['n' => 'payment_term_days', 'l' => 'Terms (days)', 't' => 'number', 'span' => 1, 'cast' => 'int'],
        ['n' => 'payment_method', 'l' => 'Default Payment Method', 't' => 'select', 'opts' => $optPayMethod, 'span' => 1, 'blank' => '— Not specified —'],
        ['n' => 'default_receivable_account_id', 'l' => 'Receivable GL Account', 't' => 'select', 'opts' => array_column($accounts, 'account_name', 'id'), 'span' => 1, 'cast' => 'int', 'blank' => '— Use default —'],
        ['n' => 'price_list',     'l' => 'Price List', 't' => 'select', 'opts' => $optPriceList, 'span' => 1, 'blank' => '— Standard —'],
        ['n' => 'billing_cycle',  'l' => 'Billing Cycle', 't' => 'select', 'opts' => ['per_transaction' => 'Per Transaction', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'], 'span' => 1],
        ['n' => 'tax_inclusive',  'l' => 'Prices include tax', 't' => 'checkbox', 'span' => 1],
        ['n' => 'consolidated_billing', 'l' => 'Consolidated invoice', 't' => 'checkbox', 'span' => 1],
        ['n' => 'allow_partial_payment', 'l' => 'Allow partial payment', 't' => 'checkbox', 'span' => 1],
        ['n' => 'cash_discount_percent', 'l' => 'Cash Discount %', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'discount_days',  'l' => 'Discount Valid (days)', 't' => 'number', 'span' => 1, 'cast' => 'int'],
        ['n' => 'interest_rate',  'l' => 'Late Payment Interest %', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'collection_priority', 'l' => 'Dunning / Collection Priority', 't' => 'number', 'span' => 1, 'cast' => 'int', 'hint' => '1 = most aggressive'],
    ]],
    ['title' => 'Credit Control', 'icon' => 'fa-shield-halved', 'fields' => [
        ['n' => 'credit_limit',  'l' => 'Credit Limit', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'credit_rating', 'l' => 'Credit Rating', 't' => 'text', 'span' => 1, 'list' => 'ratlist', 'hint' => 'e.g. AAA, B+, Watch'],
        ['n' => 'credit_hold',   'l' => 'Credit Hold', 't' => 'select', 'opts' => $optCreditHold, 'span' => 1],
        ['n' => 'risk_level',    'l' => 'Risk Level', 't' => 'select', 'opts' => $optRisk, 'span' => 1],
    ]],
    ['title' => 'Invoicing & Remittance', 'icon' => 'fa-file-invoice-dollar', 'fields' => [
        ['n' => 'invoice_delivery', 'l' => 'Invoice Delivery', 't' => 'select', 'opts' => $optDelivery, 'span' => 1],
        ['n' => 'invoice_email',    'l' => 'Invoice Email', 't' => 'email', 'span' => 1],
        ['n' => 'invoice_cc_emails','l' => 'Invoice CC (comma separated)', 't' => 'text', 'span' => 2],
        ['n' => 'bank_name',        'l' => 'Bank Name', 't' => 'text', 'span' => 1],
        ['n' => 'bank_account_name','l' => 'Account Holder Name', 't' => 'text', 'span' => 1],
        ['n' => 'bank_account_number','l' => 'Account Number', 't' => 'text', 'span' => 1],
        ['n' => 'bank_branch',      'l' => 'Branch', 't' => 'text', 'span' => 1],
        ['n' => 'bank_identifier',  'l' => 'SWIFT / IBAN / IFSC', 't' => 'text', 'span' => 1],
    ]],
    ['title' => 'Sales & Commercial Terms', 'icon' => 'fa-handshake', 'fields' => [
        ['n' => 'assigned_manager', 'l' => 'Account Manager', 't' => 'select', 'opts' => array_column($employees, 'first_name', 'id'), 'span' => 1, 'cast' => 'int', 'blank' => '— Unassigned —'],
        ['n' => 'sales_representative_id', 'l' => 'Sales Representative', 't' => 'select', 'opts' => array_column($employees, 'first_name', 'id'), 'span' => 1, 'cast' => 'int', 'blank' => '— Unassigned —'],
        ['n' => 'support_representative_id','l' => 'Support Representative', 't' => 'select', 'opts' => array_column($employees, 'first_name', 'id'), 'span' => 1, 'cast' => 'int', 'blank' => '— Unassigned —'],
        ['n' => 'sales_region',     'l' => 'Sales Region', 't' => 'text', 'span' => 1],
        ['n' => 'distribution_channel', 'l' => 'Distribution Channel', 't' => 'select', 'opts' => $optChannel, 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'shipping_condition','l' => 'Shipping Condition', 't' => 'select', 'opts' => $optShipCond, 'span' => 1, 'blank' => '— Standard —'],
        ['n' => 'incoterms',        'l' => 'Incoterms', 't' => 'select', 'opts' => $optInco, 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'incoterms_location','l' => 'Incoterms Location', 't' => 'text', 'span' => 1],
        ['n' => 'delivery_lead_time_days', 'l' => 'Delivery Lead Time (days)', 't' => 'number', 'span' => 1, 'cast' => 'int'],
        ['n' => 'default_discount_percent', 'l' => 'Default Discount %', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'commission_rate',  'l' => 'Commission %', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'contract_start_date','l' => 'Contract Start', 't' => 'date', 'span' => 1],
        ['n' => 'contract_end_date','l' => 'Contract End', 't' => 'date', 'span' => 1],
    ]],
    ['title' => 'Company Profile', 'icon' => 'fa-building', 'fields' => [
        ['n' => 'company_size',      'l' => 'Company Size', 't' => 'select', 'opts' => $optSize, 'span' => 1, 'blank' => '— Unknown —'],
        ['n' => 'number_of_employees','l' => 'Number of Employees', 't' => 'number', 'span' => 1, 'cast' => 'int'],
        ['n' => 'annual_revenue',    'l' => 'Annual Revenue', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'year_established',  'l' => 'Year Established', 't' => 'number', 'span' => 1, 'cast' => 'int', 'min' => '1800', 'max' => date('Y')],
    ]],
    ['title' => 'Compliance, Consent & Notes', 'icon' => 'fa-clipboard-check', 'fields' => [
        ['n' => 'kyc_status',     'l' => 'KYC Status', 't' => 'select', 'opts' => $optKyc, 'span' => 1],
        ['n' => 'kyc_verified_at','l' => 'KYC Verified At', 't' => 'datetime', 'span' => 1],
        ['n' => 'kyc_verified_by','l' => 'KYC Verified By', 't' => 'select', 'opts' => array_column($users, 'name', 'id'), 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'marketing_consent',     'l' => 'Marketing consent', 't' => 'checkbox', 'span' => 1],
        ['n' => 'data_processing_consent','l' => 'Data processing consent', 't' => 'checkbox', 'span' => 1],
        ['n' => 'internal_notes', 'l' => 'Internal Notes', 't' => 'textarea', 'span' => 4],
    ]],
];

// Flat whitelist of every savable column, with its cast.
$fields = [];
foreach ($sections as $sec) {
    foreach ($sec['fields'] as $f) { $fields[$f['n']] = $f['cast'] ?? 'str'; }
}

// ————— Save —————
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('csrf', 'Session expired or invalid request. Please try again.', 'error');
        to('modules/customers/index.php');
    }

    $in = [];
    foreach ($fields as $name => $cast) {
        $raw = $_POST[$name] ?? null;
        if ($raw === null) { $in[$name] = null; continue; }
        $in[$name] = is_string($raw) ? trim($raw) : $raw;
    }

    $errors = validate($_POST, ['company_name' => 'required|min:2|max:255']);
    if (!empty($in['email']) && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Invalid email format'; }
    if (!empty($in['invoice_email']) && !filter_var($in['invoice_email'], FILTER_VALIDATE_EMAIL)) { $errors['invoice_email'] = 'Invalid invoice email'; }
    foreach (['credit_limit' => 'Credit limit', 'withholding_tax_rate' => 'Withholding tax rate', 'interest_rate' => 'Interest rate', 'default_discount_percent' => 'Discount', 'commission_rate' => 'Commission'] as $f => $lbl) {
        if ($in[$f] !== null && $in[$f] !== '' && (float) $in[$f] < 0) { $errors[$f] = "$lbl cannot be negative"; }
    }
    if ($in['contract_start_date'] && $in['contract_end_date'] && $in['contract_start_date'] > $in['contract_end_date']) {
        $errors['contract_end_date'] = 'Contract end must fall after its start';
    }
    if ($in['parent_client_id'] && $isEdit && (int) $in['parent_client_id'] === (int) $_GET['id']) {
        $errors['parent_client_id'] = 'A customer cannot be its own parent';
    }
    // Duplicate guard on the trading name.
    $dup = $db->prepare("SELECT client_code FROM clients WHERE company_name = ? AND id <> ? AND deleted_at IS NULL LIMIT 1");
    $dup->execute([$in['company_name'], $isEdit ? (int) $_GET['id'] : 0]);
    if ($dup->fetch()) {
        $errors['company_name'] = 'A customer with this name already exists';
    }

    if ($errors) {
        saveFormState($errors);
        flash('validation', 'Please correct the highlighted fields.', 'error');
        to('modules/customers/' . ($isEdit ? 'form.php?id=' . (int) $_GET['id'] : 'form.php'));
    }

    // Build the column set with correct typing. Numeric columns that are NOT NULL
    // in the schema fall back to their declared default when left blank; the rest
    // become NULL so an empty field does not silently read as zero.
    $numericDefaults = [
        'payment_term_days' => 30, 'credit_limit' => 0, 'collection_priority' => 3,
        'interest_rate' => 0, 'cash_discount_percent' => 0, 'discount_days' => 0,
        'delivery_lead_time_days' => 0, 'default_discount_percent' => 0,
        'commission_rate' => 0, 'withholding_tax_rate' => 0,
    ];
    $data = [];
    foreach ($fields as $name => $cast) {
        $v2 = $in[$name];
        if ($cast === 'bool') { continue; } // handled below from the raw POST
        if ($v2 === '' || $v2 === null) {
            $data[$name] = $numericDefaults[$name] ?? null;
            continue;
        }
        $data[$name] = $cast === 'int' ? (int) $v2 : ($cast === 'float' ? (float) $v2 : $v2);
    }
    // Checkbox fields are absent from POST when unchecked.
    foreach ($sections as $sec) {
        foreach ($sec['fields'] as $f) {
            if ($f['t'] === 'checkbox') { $data[$f['n']] = isset($_POST[$f['n']]) ? 1 : 0; }
        }
    }
    // Keep the legacy free-text `address` in sync — customer_statements and invoice
    // letterheads still read it.
    $data['address'] = trim(implode(', ', array_filter([
        $data['address_line1'], $data['address_line2'], $data['city'], $data['state'],
        $data['postal_code'], $data['country'],
    ]))) ?: null;

    clearOldInput();
    $db->beginTransaction();
    try {
        if ($isEdit) {
            $id = (int) $_GET['id'];
            $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
            $db->prepare("UPDATE clients SET $sets, updated_at = NOW(), updated_by = ? WHERE id = ?")
               ->execute(array_merge(array_values($data), [$_SESSION['user_id'], $id]));
            $savedId = $id;
            logAudit($_SESSION['user_id'], 'Updated customer: ' . $data['company_name'], 'customers');
            flash('ok', 'Customer updated successfully.', 'success');
        } else {
            // Collision-safe code generation: MAX, not COUNT, retried on duplicate key.
            $code = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $prefix = 'CL' . date('Y');
                $m = $db->query("SELECT MAX(CAST(SUBSTRING(client_code, 7) AS UNSIGNED)) FROM clients WHERE client_code LIKE '$prefix%'")->fetchColumn();
                $code = $prefix . str_pad(((int) $m) + 1, 4, '0', STR_PAD_LEFT);
                try {
                    $cols = array_merge(['client_code', 'created_at', 'created_by'], array_keys($data));
                    $vals = array_merge([$code, date('Y-m-d H:i:s'), $_SESSION['user_id']], array_values($data));
                    $ph = implode(', ', array_fill(0, count($cols), '?'));
                    $db->prepare("INSERT INTO clients (`" . implode('`, `', $cols) . "`) VALUES ($ph)")->execute($vals);
                    $savedId = (int) $db->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000' || $attempt === 9) throw $e;
                    $db->rollBack();
                    $db->beginTransaction();
                    continue;
                }
            }
            logAudit($_SESSION['user_id'], 'Created customer: ' . $data['company_name'] . " ($code)", 'customers');
            flash('ok', "Customer created with code $code.", 'success');
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('db', 'Could not save customer: ' . $e->getMessage(), 'error');
        to('modules/customers/index.php');
    }
    to('modules/customers/view.php?id=' . $savedId);
}

$v = function ($name) use ($customer) { return old($name, $customer[$name] ?? ''); };
$err = function ($name) use ($errors) { return isset($errors[$name]) ? '<p class="text-xs text-red-600 mt-1">' . htmlspecialchars($errors[$name]) . '</p>' : ''; };
$cls = function ($name) use ($errors) { return 'w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none ' . (isset($errors[$name]) ? 'border-red-400' : 'border-slate-200'); };
$spanCls = [1 => 'lg:col-span-1', 2 => 'lg:col-span-2', 3 => 'lg:col-span-3', 4 => 'lg:col-span-4'];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $isEdit ? 'Edit Customer' : 'New Customer' ?></h1>
        <p class="text-gray-500 text-sm mt-1">
            <?php if ($isEdit): ?>
                <span class="font-mono"><?= htmlspecialchars($customer['client_code']) ?></span> &middot; <?= htmlspecialchars($customer['company_name']) ?>
            <?php else: ?>
                Create a customer record for AR, sales and service
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= url('modules/customers/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Back to list</a>
</div>

<?php if ($isEdit && hasPermission('customers.view')): ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Credit Limit</p>
        <p class="text-lg font-bold text-slate-800 mt-1"><?= htmlspecialchars($customer['currency_code']) ?> <?= number_format((float) $customer['credit_limit'], 2) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Outstanding</p>
        <p class="text-lg font-bold text-indigo-600 mt-1"><?= number_format((float) $exposure['outstanding'], 2) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Overdue</p>
        <p class="text-lg font-bold <?= (float) $exposure['overdue'] > 0 ? 'text-red-600' : 'text-slate-800' ?> mt-1"><?= number_format((float) $exposure['overdue'], 2) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Available Credit</p>
        <?php $avail = (float) $customer['credit_limit'] - (float) $exposure['outstanding']; ?>
        <p class="text-lg font-bold <?= $avail < 0 ? 'text-red-600' : 'text-green-600' ?> mt-1"><?= number_format($avail, 2) ?></p>
    </div>
</div>
<?php endif; ?>

<form method="post" id="customerForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <?php foreach ($sections as $sec): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i class="fas <?= $sec['icon'] ?> text-indigo-500"></i> <?= $sec['title'] ?>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($sec['fields'] as $f):
                $name = $f['n']; $val = $v($name); $span = $spanCls[$f['span'] ?? 1]; ?>

                <?php if ($f['t'] === 'checkbox'): ?>
                <label class="<?= $span ?> flex items-start gap-2.5 p-3 bg-slate-50 rounded-lg cursor-pointer hover:bg-slate-100 transition">
                    <input type="checkbox" name="<?= $name ?>" value="1" <?= !empty($val) ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-slate-700 leading-snug"><?= $f['l'] ?>
                        <?php if (!empty($f['hint'])): ?><span class="block text-[11px] text-slate-400"><?= $f['hint'] ?></span><?php endif; ?>
                    </span>
                </label>

                <?php elseif ($f['t'] === 'textarea'): ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $f['l'] ?></label>
                    <textarea name="<?= $name ?>" rows="3" class="<?= $cls($name) ?>"><?= htmlspecialchars($val) ?></textarea>
                    <?= $err($name) ?>
                </div>

                <?php elseif ($f['t'] === 'select'): ?>
                <?php $opts = $f['opts']; $keys = $f['keys'] ?? array_keys($opts); ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $f['l'] ?><?= !empty($f['req']) ? ' *' : '' ?></label>
                    <select name="<?= $name ?>" class="<?= $cls($name) ?>" data-terms="<?= $name === 'payment_terms' ? htmlspecialchars(json_encode($optPayTerms)) : '' ?>">
                        <?php if (isset($f['blank'])): ?><option value=""><?= $f['blank'] ?></option><?php endif; ?>
                        <?php foreach ($keys as $i => $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= (string) $val === (string) $k ? 'selected' : '' ?>><?= htmlspecialchars(is_array($opts) ? ($opts[$k] ?? $k) : $k) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($f['hint'])): ?><p class="text-[11px] text-slate-400 mt-1"><?= $f['hint'] ?></p><?php endif; ?>
                    <?= $err($name) ?>
                </div>

                <?php else: ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $f['l'] ?><?= !empty($f['req']) ? ' <span class="text-red-500">*</span>' : '' ?></label>
                    <input type="<?= $f['t'] ?>" name="<?= $name ?>"
                           value="<?= htmlspecialchars($f['t'] === 'datetime' && $val ? date('Y-m-d\TH:i', strtotime($val)) : $val) ?>"
                           <?= isset($f['step']) ? 'step="' . $f['step'] . '"' : '' ?>
                           <?= isset($f['min']) ? 'min="' . $f['min'] . '"' : '' ?>
                           <?= isset($f['max']) ? 'max="' . $f['max'] . '"' : '' ?>
                           <?= isset($f['ph']) ? 'placeholder="' . $f['ph'] . '"' : '' ?>
                           <?= !empty($f['list']) ? 'list="' . $f['list'] . '"' : '' ?>
                           class="<?= $cls($name) ?>">
                    <?php if (!empty($f['hint'])): ?><p class="text-[11px] text-slate-400 mt-1"><?= $f['hint'] ?></p><?php endif; ?>
                    <?= $err($name) ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Datalists -->
    <datalist id="seglist"><?php foreach ($db->query("SELECT DISTINCT segment FROM clients WHERE segment IS NOT NULL AND segment<>'' ORDER BY segment") as $d): ?><option value="<?= htmlspecialchars($d['segment']) ?>"><?php endforeach; ?></datalist>
    <datalist id="indlist"><?php foreach (['Information Technology','Financial Services','Healthcare','Retail','Manufacturing','Education','Construction','Logistics','Hospitality','Energy','Professional Services','Telecommunications','Real Estate','Government','Other'] as $i): ?><option value="<?= $i ?>"><?php endforeach; ?></datalist>
    <datalist id="ctrylist"><?php foreach (['India','United Arab Emirates','Saudi Arabia','Qatar','United Kingdom','United States','Germany','France','Singapore','Malaysia','Australia','Canada','China','Oman','Bahrain','Kuwait','Egypt','South Africa'] as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist>
    <datalist id="ratlist"><?php foreach (['AAA','AA','A','BBB','BB','B','CCC','Watch','Critical'] as $r): ?><option value="<?= $r ?>"><?php endforeach; ?></datalist>

    <div class="sticky bottom-0 bg-white border border-slate-100 rounded-xl shadow-lg p-4 flex items-center gap-3 mb-8">
        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-save mr-2"></i><?= $isEdit ? 'Update Customer' : 'Create Customer' ?>
        </button>
        <a href="<?= url($isEdit ? 'modules/customers/view.php?id=' . (int) $_GET['id'] : 'modules/customers/index.php') ?>"
           class="px-5 py-2.5 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">Cancel</a>
        <span class="ml-auto text-xs text-slate-400"><span class="text-red-500">*</span> required</span>
    </div>
</form>

<script>
// Payment terms drive the day count unless the user has overridden it.
document.addEventListener('DOMContentLoaded', function () {
    var terms = document.querySelector('select[name="payment_terms"]');
    var days  = document.querySelector('input[name="payment_term_days"]');
    if (!terms || !days) return;
    var map = JSON.parse(terms.dataset.terms || '{}');
    var touched = days.value !== '';
    days.addEventListener('input', function () { touched = true; });
    terms.addEventListener('change', function () { if (!touched && map[terms.value] !== undefined) days.value = map[terms.value]; });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
