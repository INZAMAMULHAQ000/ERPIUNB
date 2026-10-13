<?php
$isEdit = isset($_GET['id']) && (int) $_GET['id'] > 0;
$pageTitle = $isEdit ? 'Edit Company' : 'New Company';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission($isEdit ? 'company.edit' : 'company.create')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();

// ————— Reference data —————
$accounts  = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' ORDER BY account_code")->fetchAll();
$accountOpts = [];
foreach ($accounts as $a) { $accountOpts[$a['id']] = $a['account_code'] . ' — ' . $a['account_name']; }

$groups = $db->query("SELECT id, group_code, group_name FROM company_groups WHERE status = 'active' ORDER BY group_name")->fetchAll();
$groupOpts = array_column($groups, 'group_name', 'id');

$whs = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status = 'active' ORDER BY warehouse_name")->fetchAll();
$whOpts = [];
foreach ($whs as $w) { $whOpts[$w['id']] = $w['warehouse_code'] . ' — ' . $w['warehouse_name']; }

$ccs = $db->query("SELECT id, cost_center_code, cost_center_name FROM cost_centers WHERE status = 'active' ORDER BY cost_center_name")->fetchAll();
$ccOpts = [];
foreach ($ccs as $x) { $ccOpts[$x['id']] = $x['cost_center_code'] . ' — ' . $x['cost_center_name']; }

$tpl = $db->query("SELECT id, template_name FROM templates WHERE status = 'active' ORDER BY template_name")->fetchAll();
$tplOpts = array_column($tpl, 'template_name', 'id');

$selfStmt = $db->prepare("SELECT id, company_code, company_name FROM companies WHERE deleted_at IS NULL AND id <> ? ORDER BY company_name");
$selfStmt->execute([$isEdit ? (int) $_GET['id'] : 0]);
$parentOpts = array_column($selfStmt->fetchAll(), 'company_name', 'id');

$company = [
    'status' => 'active', 'base_currency' => 'USD', 'country' => 'United Arab Emirates',
    'entity_type' => 'limited_liability', 'company_category' => 'operating',
    'financial_framework' => 'IFRS', 'consolidation_method' => 'full',
    'tax_registration_type' => 'standard', 'vat_return_frequency' => 'monthly', 'vat_return_day' => 15,
    'e_invoicing_system' => 'none', 'periods_per_year' => 12, 'posting_tolerance' => '0.01',
    'invoice_prefix' => 'INV', 'invoice_padding' => 4, 'quote_prefix' => 'QTN', 'po_prefix' => 'PO',
    'pr_prefix' => 'PR', 'gr_prefix' => 'GRN', 'je_prefix' => 'JV', 'receipt_prefix' => 'RCP',
    'vendor_bill_prefix' => 'VB', 'quote_validity_days' => 30, 'is_legal_entity' => 1,
    'is_operating_unit' => 1, 'is_business_unit' => 0, 'bu_sales' => 1, 'bu_purchasing' => 1,
    'bu_inventory' => 1, 'bu_payables' => 1, 'bu_receivables' => 1, 'enable_multicurrency' => 1,
    'enable_inventory' => 1, 'enable_projects' => 1, 'enable_hr' => 1, 'enable_pos' => 0,
    'enable_manufacturing' => 0, 'withholding_agent' => 1, 'is_reporting_entity' => 1,
    'working_days' => '1,2,3,4,5', 'weekend_days' => '5,6', 'working_hours' => '09:00-18:00',
    'language' => 'English', 'default_payment_terms' => 'Net 30',
];

if ($isEdit) {
    $s = $db->prepare("SELECT * FROM companies WHERE id = ? AND deleted_at IS NULL");
    $s->execute([(int) $_GET['id']]);
    $found = $s->fetch();
    if (!$found) { flash('nf', 'Company not found.', 'error'); to('modules/companies/index.php'); }
    $company = array_merge($company, $found);
}

// ————— Option sets —————
$optEntity = ['limited_liability' => 'Limited Liability (LLC/Ltd)', 'sole_proprietorship' => 'Sole Proprietorship', 'partnership' => 'Partnership', 'free_zone_establishment' => 'Free Zone Establishment', 'branch' => 'Branch of Foreign Company', 'subsidiary' => 'Subsidiary', 'holding' => 'Holding Company', 'ngo' => 'NGO / Non-Profit', 'government' => 'Government Body', 'trust' => 'Trust'];
$optCategory = ['operating' => 'Operating', 'holding' => 'Holding', 'service_provider' => 'Service Provider', 'special_purpose' => 'Special Purpose Vehicle', 'dormant' => 'Dormant'];
$optStatus = ['active' => 'Active', 'inactive' => 'Inactive', 'dormant' => 'Dormant', 'archived' => 'Archived'];
$optFramework = ['IFRS' => 'IFRS', 'IFRS_for_SMEs' => 'IFRS for SMEs', 'Local_GAAP' => 'Local GAAP', 'US_GAAP' => 'US GAAP', 'ISFR' => 'ISFR (UAE)', 'None' => 'None / Not reported'];
$optConsol = ['full' => 'Full consolidation (100% lines)', 'proportionate' => 'Proportionate / line-by-line', 'equity' => 'Equity method', 'none' => 'Not consolidated'];
$optTaxReg = ['standard' => 'Standard', 'vat_registered' => 'VAT / GST registered', 'non_registered' => 'Not registered', 'exempt' => 'Exempt', 'reverse_charge' => 'Reverse charge only'];
$optVatFreq = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'semi_annual' => 'Semi-annual', 'annual' => 'Annual', 'none' => 'Not applicable'];
$optEInv = ['none' => 'No e-invoicing', 'zatca_fatoora' => 'ZATCA Fatoora (KSA)', 'gst_e_invoice' => 'GST e-Invoice (India)', 'fbr_iris' => 'FBR Iris (Pakistan)', 'peppol' => 'Peppol', 'other' => 'Other'];
$optEInvStage = ['phase_1_generation' => 'Phase 1 — generation & QR', 'phase_2_integration' => 'Phase 2 — clearance integration', 'cleared_only' => 'Cleared invoices only'];
$optPayTerms = ['Due on Receipt', 'Net 7', 'Net 15', 'Net 30', 'Net 45', 'Net 60', 'Net 90', 'End of Month Net 30', 'End of Month Net 60'];
$optInco = ['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FOB', 'CIF'];
$optShipCond = ['standard' => 'Standard', 'express' => 'Express', 'economy' => 'Economy', 'pickup' => 'Customer Pickup', 'freight' => 'Freight Collect'];
$optPriceList = ['standard' => 'Standard', 'preferred' => 'Preferred', 'volume' => 'Volume Tier', 'contract' => 'Contract Priced', 'internal' => 'Internal'];
$optLang = ['English' => 'English', 'Arabic' => 'Arabic', 'French' => 'French', 'Hindi' => 'Hindi', 'Urdu' => 'Urdu', 'German' => 'German', 'Spanish' => 'Spanish'];
$optTz = ['Asia/Dubai' => 'UAE (UTC+4)', 'Asia/Riyadh' => 'KSA (UTC+3)', 'Asia/Karachi' => 'Pakistan (UTC+5)', 'Asia/Kolkata' => 'India (UTC+5:30)', 'Europe/London' => 'UK (GMT/BST)', 'America/New_York' => 'US East (UTC-5)', 'Asia/Singapore' => 'Singapore (UTC+8)'];
$optAccountType = ['current' => 'Current', 'savings' => 'Savings', 'margin' => 'Margin', 'escrow' => 'Escrow', 'fx' => 'FX', 'fixed_deposit' => 'Fixed Deposit'];

$payTermsOpts = array_combine($optPayTerms, $optPayTerms);
$incoOpts = array_combine($optInco, $optInco);
$currencies = getCurrencies();

// ————— Field schema: drives rendering AND the save whitelist —————
$sections = [
    ['title' => 'Identity & Classification', 'icon' => 'fa-building', 'fields' => [
        ['n' => 'company_name',   'l' => 'Company Name', 't' => 'text', 'span' => 2, 'req' => true],
        ['n' => 'legal_name',     'l' => 'Registered Legal Name', 't' => 'text', 'span' => 2],
        ['n' => 'trade_name',     'l' => 'Trading Name', 't' => 'text', 'span' => 2],
        ['n' => 'short_name',     'l' => 'Short Name', 't' => 'text', 'span' => 1, 'hint' => 'Used in headers and reports'],
        ['n' => 'search_term',    'l' => 'Search Term', 't' => 'text', 'span' => 1],
        ['n' => 'entity_type',    'l' => 'Entity Type', 't' => 'select', 'opts' => $optEntity, 'span' => 1],
        ['n' => 'company_category','l' => 'Company Category', 't' => 'select', 'opts' => $optCategory, 'span' => 1],
        ['n' => 'status',         'l' => 'Status', 't' => 'select', 'opts' => $optStatus, 'span' => 1],
        ['n' => 'is_default',     'l' => 'Default company for new postings', 't' => 'checkbox', 'span' => 1],
        ['n' => 'industry',       'l' => 'Industry', 't' => 'text', 'span' => 1, 'list' => 'indlist'],
        ['n' => 'sub_industry',   'l' => 'Sub Industry', 't' => 'text', 'span' => 1],
        ['n' => 'established_date','l' => 'Established / Incorporated', 't' => 'date', 'span' => 1],
        ['n' => 'duns_number',    'l' => 'D-U-N-S Number', 't' => 'text', 'span' => 1],
        ['n' => 'lei_number',     'l' => 'Legal Entity Identifier (LEI)', 't' => 'text', 'span' => 1],
    ]],
    ['title' => 'Group Structure & Unit Roles', 'icon' => 'fa-sitemap', 'fields' => [
        ['n' => 'group_id',          'l' => 'Company Group', 't' => 'select', 'opts' => $groupOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Ungrouped —'],
        ['n' => 'parent_company_id', 'l' => 'Parent Company', 't' => 'select', 'opts' => $parentOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Top of hierarchy —'],
        ['n' => 'is_legal_entity',   'l' => 'Legal entity', 't' => 'checkbox', 'span' => 1],
        ['n' => 'is_operating_unit', 'l' => 'Operating unit', 't' => 'checkbox', 'span' => 1],
        ['n' => 'is_business_unit',  'l' => 'Business unit', 't' => 'checkbox', 'span' => 1],
        ['n' => 'is_branch',         'l' => 'Is a branch', 't' => 'checkbox', 'span' => 1],
        ['n' => 'branch_code',       'l' => 'Branch Code', 't' => 'text', 'span' => 1],
        ['n' => 'consolidation_method','l' => 'Consolidation Method', 't' => 'select', 'opts' => $optConsol, 'span' => 1],
        ['n' => 'is_reporting_entity','l' => 'Included in statutory reporting', 't' => 'checkbox', 'span' => 1],
        ['n' => 'bu_sales',          'l' => 'Sales', 't' => 'checkbox', 'span' => 1],
        ['n' => 'bu_purchasing',     'l' => 'Purchasing', 't' => 'checkbox', 'span' => 1],
        ['n' => 'bu_inventory',      'l' => 'Inventory', 't' => 'checkbox', 'span' => 1],
        ['n' => 'bu_payables',       'l' => 'Payables', 't' => 'checkbox', 'span' => 1],
        ['n' => 'bu_receivables',    'l' => 'Receivables', 't' => 'checkbox', 'span' => 1],
    ]],
    ['title' => 'Registration, Identifiers & Statutory', 'icon' => 'fa-certificate', 'fields' => [
        ['n' => 'registration_number', 'l' => 'Company Registration No.', 't' => 'text', 'span' => 1],
        ['n' => 'tax_number',          'l' => 'Tax Number', 't' => 'text', 'span' => 1],
        ['n' => 'vat_number',          'l' => 'VAT Number', 't' => 'text', 'span' => 1, 'hint' => 'Printed on invoices as the issuer VAT'],
        ['n' => 'trn_number',          'l' => 'TRN (UAE)', 't' => 'text', 'span' => 1],
        ['n' => 'tin_number',          'l' => 'TIN', 't' => 'text', 'span' => 1],
        ['n' => 'ein_number',          'l' => 'EIN (US)', 't' => 'text', 'span' => 1],
        ['n' => 'gst_state_code',      'l' => 'GST State Code', 't' => 'text', 'span' => 1],
        ['n' => 'free_zone_name',      'l' => 'Free Zone', 't' => 'text', 'span' => 1],
        ['n' => 'free_zone_license',   'l' => 'Free Zone Licence No.', 't' => 'text', 'span' => 1],
        ['n' => 'free_zone_expiry',    'l' => 'Free Zone Licence Expires', 't' => 'date', 'span' => 1],
        ['n' => 'license_number',      'l' => 'Trade Licence No.', 't' => 'text', 'span' => 1],
        ['n' => 'license_expiry',      'l' => 'Trade Licence Expires', 't' => 'date', 'span' => 1],
        ['n' => 'regulatory_authority','l' => 'Regulatory Authority', 't' => 'text', 'span' => 1],
        ['n' => 'share_capital',       'l' => 'Authorised Share Capital', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'audit_firm',          'l' => 'External Audit Firm', 't' => 'text', 'span' => 1],
        ['n' => 'statutory_auditor',   'l' => 'Statutory Auditor', 't' => 'text', 'span' => 1],
        ['n' => 'financial_framework', 'l' => 'Reporting Framework', 't' => 'select', 'opts' => $optFramework, 'span' => 1],
    ]],
    ['title' => 'Registered Address & Contact', 'icon' => 'fa-location-dot', 'fields' => [
        ['n' => 'address_line1', 'l' => 'Address Line 1', 't' => 'text', 'span' => 2],
        ['n' => 'address_line2', 'l' => 'Address Line 2', 't' => 'text', 'span' => 2],
        ['n' => 'city',          'l' => 'City', 't' => 'text', 'span' => 1],
        ['n' => 'state',         'l' => 'State / Emirate / Province', 't' => 'text', 'span' => 1],
        ['n' => 'region',        'l' => 'Region', 't' => 'text', 'span' => 1],
        ['n' => 'postal_code',   'l' => 'Postal Code', 't' => 'text', 'span' => 1],
        ['n' => 'country',       'l' => 'Country', 't' => 'text', 'span' => 1, 'list' => 'ctrylist'],
        ['n' => 'timezone',      'l' => 'Timezone', 't' => 'select', 'opts' => $optTz, 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'language',      'l' => 'Document Language', 't' => 'select', 'opts' => $optLang, 'span' => 1],
        ['n' => 'phone',         'l' => 'Phone', 't' => 'tel', 'span' => 1],
        ['n' => 'alternate_phone','l' => 'Alternate Phone', 't' => 'tel', 'span' => 1],
        ['n' => 'fax',           'l' => 'Fax', 't' => 'tel', 'span' => 1],
        ['n' => 'email',         'l' => 'General Email', 't' => 'email', 'span' => 1],
        ['n' => 'support_email', 'l' => 'Support Email', 't' => 'email', 'span' => 1],
        ['n' => 'website',       'l' => 'Website', 't' => 'text', 'span' => 1, 'ph' => 'https://'],
        ['n' => 'contact_person','l' => 'Primary Contact', 't' => 'text', 'span' => 1],
        ['n' => 'contact_designation', 'l' => 'Contact Designation', 't' => 'text', 'span' => 1],
    ]],
    ['title' => 'Financial & Ledger Controls', 'icon' => 'fa-coins', 'fields' => [
        ['n' => 'base_currency',  'l' => 'Base Currency', 't' => 'select', 'opts' => array_map(fn($c) => $c['name'] . ' (' . $c['symbol'] . ')', $currencies), 'keys' => array_keys($currencies), 'span' => 1],
        ['n' => 'financial_year_start', 'l' => 'Fiscal Year Start', 't' => 'date', 'span' => 1, 'hint' => 'Required for period close & consolidation'],
        ['n' => 'periods_per_year', 'l' => 'Periods Per Year', 't' => 'number', 'span' => 1, 'cast' => 'int', 'min' => '1', 'max' => '52'],
        ['n' => 'posting_tolerance', 'l' => 'Rounding Tolerance', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.0001'],
        ['n' => 'default_receivable_account_id', 'l' => 'Accounts Receivable', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_payable_account_id',    'l' => 'Accounts Payable', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_bank_account_id',       'l' => 'Bank Clearing', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_cash_account_id',       'l' => 'Cash / Petty Cash', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_inventory_account_id',  'l' => 'Inventory', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_cogs_account_id',       'l' => 'Cost of Goods Sold', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_sales_account_id',      'l' => 'Revenue / Sales', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_purchase_account_id',   'l' => 'Purchases', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_tax_account_id',        'l' => 'Tax Payable', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_intercompany_account_id','l' => 'Inter-company', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_retained_earnings_account_id', 'l' => 'Retained Earnings', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —', 'hint' => 'Year-end closing target'],
        ['n' => 'default_fx_difference_account_id',     'l' => 'FX Gain / Loss', 't' => 'select', 'opts' => $accountOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'allow_posting_to_closed', 'l' => 'Allow posting to closed periods', 't' => 'checkbox', 'span' => 1],
        ['n' => 'require_cost_center',     'l' => 'Cost centre mandatory on postings', 't' => 'checkbox', 'span' => 1],
        ['n' => 'require_project',         'l' => 'Project mandatory on postings', 't' => 'checkbox', 'span' => 1],
        ['n' => 'enable_multicurrency',    'l' => 'Multi-currency enabled', 't' => 'checkbox', 'span' => 1],
        ['n' => 'cash_basis_accounting',   'l' => 'Cash-basis accounting', 't' => 'checkbox', 'span' => 1],
        ['n' => 'allow_negative_stock',    'l' => 'Allow negative stock', 't' => 'checkbox', 'span' => 1],
    ]],
    ['title' => 'Tax Reporting & E-Invoicing', 'icon' => 'fa-file-invoice', 'fields' => [
        ['n' => 'tax_registration_type', 'l' => 'Tax Registration Type', 't' => 'select', 'opts' => $optTaxReg, 'span' => 1],
        ['n' => 'tax_authority',         'l' => 'Tax Authority', 't' => 'text', 'span' => 1],
        ['n' => 'tax_office_code',       'l' => 'Tax Office Code', 't' => 'text', 'span' => 1],
        ['n' => 'vat_return_frequency',  'l' => 'VAT Return Frequency', 't' => 'select', 'opts' => $optVatFreq, 'span' => 1],
        ['n' => 'vat_return_day',        'l' => 'Return Due Day (of month)', 't' => 'number', 'span' => 1, 'cast' => 'int', 'min' => '1', 'max' => '31'],
        ['n' => 'withholding_agent',     'l' => 'Acts as withholding agent', 't' => 'checkbox', 'span' => 1],
        ['n' => 'default_withholding_rate', 'l' => 'Default WHT Rate %', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'e_invoicing_enabled',   'l' => 'E-invoicing enabled', 't' => 'checkbox', 'span' => 1],
        ['n' => 'e_invoicing_system',    'l' => 'E-Invoicing Regime', 't' => 'select', 'opts' => $optEInv, 'span' => 1],
        ['n' => 'e_invoicing_stage',     'l' => 'E-Invoicing Stage', 't' => 'select', 'opts' => $optEInvStage, 'span' => 1, 'blank' => '— Not applicable —'],
        ['n' => 'direct_debit_prefix',   'l' => 'Direct Debit Mandate Prefix', 't' => 'text', 'span' => 1],
    ]],
    ['title' => 'Document Numbering & Trading Terms', 'icon' => 'fa-hashtag', 'fields' => [
        ['n' => 'invoice_prefix',     'l' => 'Invoice Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'invoice_padding',    'l' => 'Number Padding', 't' => 'number', 'span' => 1, 'cast' => 'int', 'min' => '1', 'max' => '10'],
        ['n' => 'quote_prefix',       'l' => 'Quote Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'receipt_prefix',     'l' => 'Receipt Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'je_prefix',          'l' => 'Journal Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'vendor_bill_prefix', 'l' => 'Vendor Bill Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'pr_prefix',          'l' => 'Requisition Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'po_prefix',          'l' => 'Purchase Order Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'gr_prefix',          'l' => 'Goods Receipt Prefix', 't' => 'text', 'span' => 1],
        ['n' => 'payment_reference_format', 'l' => 'Payment Reference Format', 't' => 'text', 'span' => 1, 'hint' => 'e.g. {invoice_number}, {company_code}-{id}'],
        ['n' => 'default_payment_terms', 'l' => 'Default Payment Terms', 't' => 'select', 'opts' => $payTermsOpts, 'span' => 1, 'blank' => '— None —'],
        ['n' => 'default_shipping_condition', 'l' => 'Default Shipping Condition', 't' => 'select', 'opts' => $optShipCond, 'span' => 1, 'blank' => '— Standard —'],
        ['n' => 'default_incoterms',  'l' => 'Default Incoterms', 't' => 'select', 'opts' => $incoOpts, 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'default_price_list', 'l' => 'Default Price List', 't' => 'select', 'opts' => $optPriceList, 'span' => 1, 'blank' => '— Standard —'],
        ['n' => 'quote_validity_days','l' => 'Quote Validity (days)', 't' => 'number', 'span' => 1, 'cast' => 'int'],
        ['n' => 'invoice_terms',      'l' => 'Standard Terms & Conditions', 't' => 'textarea', 'span' => 4],
    ]],
    ['title' => 'Branding & Documents', 'icon' => 'fa-palette', 'fields' => [
        ['n' => 'logo_path', 'l' => 'Logo Path or URL', 't' => 'text', 'span' => 2, 'ph' => 'uploads/company_logos/logo.png or https://…', 'hint' => 'Rendered on invoices, quotes and statements'],
        ['n' => 'theme_color', 'l' => 'Brand Colour', 't' => 'text', 'span' => 1, 'ph' => '#1e40af'],
        ['n' => 'letterhead_template_id', 'l' => 'Letterhead Template', 't' => 'select', 'opts' => $tplOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Default —'],
        ['n' => 'signature_text', 'l' => 'Authorised Signatory Text', 't' => 'textarea', 'span' => 2],
        ['n' => 'document_footer_note', 'l' => 'Document Footer Note', 't' => 'textarea', 'span' => 2],
    ]],
    ['title' => 'Operations & Modules', 'icon' => 'fa-gears', 'fields' => [
        ['n' => 'default_warehouse_id',   'l' => 'Default Warehouse', 't' => 'select', 'opts' => $whOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'default_cost_center_id', 'l' => 'Default Cost Centre', 't' => 'select', 'opts' => $ccOpts, 'span' => 1, 'cast' => 'int', 'blank' => '— Not set —'],
        ['n' => 'working_days',  'l' => 'Working Days', 't' => 'text', 'span' => 1, 'hint' => '0=Sun … 6=Sat, comma separated'],
        ['n' => 'weekend_days',  'l' => 'Weekend Days', 't' => 'text', 'span' => 1],
        ['n' => 'working_hours', 'l' => 'Working Hours', 't' => 'text', 'span' => 1, 'ph' => '09:00-18:00'],
        ['n' => 'employee_count','l' => 'Employee Count', 't' => 'number', 'span' => 1, 'cast' => 'int'],
        ['n' => 'annual_revenue','l' => 'Annual Revenue', 't' => 'number', 'span' => 1, 'cast' => 'float', 'step' => '0.01'],
        ['n' => 'enable_inventory',     'l' => 'Inventory', 't' => 'checkbox', 'span' => 1],
        ['n' => 'enable_projects',      'l' => 'Projects', 't' => 'checkbox', 'span' => 1],
        ['n' => 'enable_hr',            'l' => 'Human Resources', 't' => 'checkbox', 'span' => 1],
        ['n' => 'enable_pos',           'l' => 'Point of Sale', 't' => 'checkbox', 'span' => 1],
        ['n' => 'enable_manufacturing', 'l' => 'Manufacturing', 't' => 'checkbox', 'span' => 1],
    ]],
];

$fields = [];
foreach ($sections as $sec) {
    foreach ($sec['fields'] as $f) { $fields[$f['n']] = $f['cast'] ?? 'str'; }
}

// ————— Save —————
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('csrf', 'Session expired or invalid request.', 'error');
        to('modules/companies/index.php');
    }

    $in = [];
    foreach ($fields as $name => $cast) {
        $raw = $_POST[$name] ?? null;
        $in[$name] = is_string($raw) ? trim($raw) : $raw;
    }

    $errors = validate($_POST, ['company_name' => 'required|min:2|max:255']);
    if (!empty($in['email']) && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Invalid email format'; }
    foreach (['share_capital' => 'Share capital', 'annual_revenue' => 'Annual revenue', 'posting_tolerance' => 'Tolerance', 'default_withholding_rate' => 'Withholding rate'] as $f => $lbl) {
        if (isset($in[$f]) && $in[$f] !== '' && (float) $in[$f] < 0) { $errors[$f] = "$lbl cannot be negative"; }
    }
    if (!empty($in['parent_company_id']) && $isEdit && (int) $in['parent_company_id'] === (int) $_GET['id']) {
        $errors['parent_company_id'] = 'A company cannot be its own parent';
    }
    // Reject ancestor loops: walking up from the chosen parent must not reach this company.
    if (!empty($in['parent_company_id']) && $isEdit) {
        $walk = (int) $in['parent_company_id'];
        for ($i = 0; $i < 20 && $walk; $i++) {
            if ($walk === (int) $_GET['id']) { $errors['parent_company_id'] = 'That parent would create a circular hierarchy'; break; }
            $ws = $db->prepare("SELECT parent_company_id FROM companies WHERE id = ?");
            $ws->execute([$walk]);
            $walk = (int) ($ws->fetchColumn() ?: 0);
        }
    }
    $dup = $db->prepare("SELECT company_code FROM companies WHERE company_name = ? AND id <> ? AND deleted_at IS NULL LIMIT 1");
    $dup->execute([$in['company_name'], $isEdit ? (int) $_GET['id'] : 0]);
    if ($dup->fetch()) { $errors['company_name'] = 'A company with this name already exists'; }

    if ($errors) {
        saveFormState($errors);
        flash('validation', 'Please correct the highlighted fields.', 'error');
        to('modules/companies/' . ($isEdit ? 'form.php?id=' . (int) $_GET['id'] : 'form.php'));
    }

    $numericDefaults = [
        'periods_per_year' => 12, 'posting_tolerance' => 0.01, 'invoice_padding' => 4,
        'quote_validity_days' => 30, 'vat_return_day' => 15, 'default_withholding_rate' => 0,
        'share_capital' => null, 'annual_revenue' => null, 'employee_count' => null,
    ];
    $data = [];
    foreach ($fields as $name => $cast) {
        if ($cast === 'bool') { continue; }
        $val = $in[$name] ?? null;
        if ($val === '' || $val === null) {
            $data[$name] = array_key_exists($name, $numericDefaults) ? $numericDefaults[$name] : null;
            continue;
        }
        $data[$name] = $cast === 'int' ? (int) $val : ($cast === 'float' ? (float) $val : $val);
    }
    foreach ($sections as $sec) {
        foreach ($sec['fields'] as $f) {
            if ($f['t'] === 'checkbox') { $data[$f['n']] = isset($_POST[$f['n']]) ? 1 : 0; }
        }
    }
    // Prefixes must survive string interpolation into document numbers.
    foreach (['invoice_prefix','quote_prefix','po_prefix','pr_prefix','gr_prefix','je_prefix','receipt_prefix','vendor_bill_prefix'] as $p) {
        $data[$p] = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $data[$p])) ?: 'X';
    }

    clearOldInput();
    $db->beginTransaction();
    try {
        if ($isEdit) {
            $id = (int) $_GET['id'];
            $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
            $db->prepare("UPDATE companies SET $sets, updated_at = NOW(), updated_by = ? WHERE id = ?")
               ->execute(array_merge(array_values($data), [$_SESSION['user_id'], $id]));
            $savedId = $id;
            logAudit($_SESSION['user_id'], 'Updated company: ' . $data['company_name'], 'company');
            flash('ok', 'Company updated successfully.', 'success');
        } else {
            $code = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $m = $db->query("SELECT MAX(id) FROM companies")->fetchColumn();
                $code = 'CMP' . str_pad(((int) $m) + 1 + $attempt, 4, '0', STR_PAD_LEFT);
                try {
                    $cols = array_merge(['company_code', 'created_at', 'created_by'], array_keys($data));
                    $vals = array_merge([$code, date('Y-m-d H:i:s'), $_SESSION['user_id']], array_values($data));
                    $ph = implode(', ', array_fill(0, count($cols), '?'));
                    $db->prepare("INSERT INTO companies (`" . implode('`, `', $cols) . "`) VALUES ($ph)")->execute($vals);
                    $savedId = (int) $db->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000' || $attempt === 9) throw $e;
                    $db->rollBack();
                    $db->beginTransaction();
                }
            }
            logAudit($_SESSION['user_id'], 'Created company: ' . $data['company_name'] . " ($code)", 'company');
            flash('ok', "Company created with code $code.", 'success');
        }

        // Only one company may be the default.
        if (!empty($data['is_default'])) {
            $keep = $savedId ?? (int) $_GET['id'];
            $db->prepare("UPDATE companies SET is_default = 0 WHERE id <> ?")->execute([$keep]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('db', 'Could not save company: ' . $e->getMessage(), 'error');
        to('modules/companies/index.php');
    }
    to('modules/companies/view.php?id=' . ($savedId ?? (int) $_GET['id']));
}

$v = function ($name) use ($company) { return old($name, $company[$name] ?? ''); };
$err = function ($name) use ($errors) { return isset($errors[$name]) ? '<p class="text-xs text-red-600 mt-1">' . htmlspecialchars($errors[$name]) . '</p>' : ''; };
$cls = function ($name) use ($errors) { return 'w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none ' . (isset($errors[$name]) ? 'border-red-400' : 'border-slate-200'); };
$spanCls = [1 => 'lg:col-span-1', 2 => 'lg:col-span-2', 3 => 'lg:col-span-3', 4 => 'lg:col-span-4'];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $isEdit ? 'Edit Company' : 'New Company' ?></h1>
        <p class="text-gray-500 text-sm mt-1">
            <?php if ($isEdit): ?><span class="font-mono"><?= htmlspecialchars($company['company_code']) ?></span> &middot; <?= htmlspecialchars($company['company_name']) ?>
            <?php else: ?>Register a legal entity, operating unit or branch<?php endif; ?>
        </p>
    </div>
    <a href="<?= url('modules/companies/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Back to list</a>
</div>

<form method="post" id="companyForm">
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
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $f['l'] ?></label>
                    <select name="<?= $name ?>" class="<?= $cls($name) ?>">
                        <?php if (isset($f['blank'])): ?><option value=""><?= $f['blank'] ?></option><?php endif; ?>
                        <?php foreach ($keys as $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= (string) $val === (string) $k ? 'selected' : '' ?>><?= htmlspecialchars($opts[$k] ?? $k) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($f['hint'])): ?><p class="text-[11px] text-slate-400 mt-1"><?= $f['hint'] ?></p><?php endif; ?>
                    <?= $err($name) ?>
                </div>

                <?php else: ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $f['l'] ?><?= !empty($f['req']) ? ' <span class="text-red-500">*</span>' : '' ?></label>
                    <input type="<?= $f['t'] ?>" name="<?= $name ?>" value="<?= htmlspecialchars($val) ?>"
                           <?= isset($f['step']) ? 'step="' . $f['step'] . '"' : '' ?>
                           <?= isset($f['min']) ? 'min="' . $f['min'] . '"' : '' ?>
                           <?= isset($f['max']) ? 'max="' . $f['max'] . '"' : '' ?>
                           <?= isset($f['ph']) ? 'placeholder="' . htmlspecialchars($f['ph']) . '"' : '' ?>
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

    <datalist id="indlist"><?php foreach (['Information Technology','Financial Services','Healthcare','Retail','Manufacturing','Construction','Logistics','Hospitality','Energy','Professional Services','Telecommunications','Real Estate','Trading','Consulting'] as $i): ?><option value="<?= $i ?>"><?php endforeach; ?></datalist>
    <datalist id="ctrylist"><?php foreach (['United Arab Emirates','Saudi Arabia','Qatar','Kuwait','Bahrain','Oman','India','Pakistan','Egypt','United Kingdom','United States','Germany','France','Singapore','Malaysia','Australia','Canada','China','South Africa','Turkey'] as $ct): ?><option value="<?= $ct ?>"><?php endforeach; ?></datalist>

    <div class="sticky bottom-0 bg-white border border-slate-100 rounded-xl shadow-lg p-4 flex items-center gap-3 mb-8">
        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-save mr-2"></i><?= $isEdit ? 'Update Company' : 'Create Company' ?>
        </button>
        <a href="<?= url($isEdit ? 'modules/companies/view.php?id=' . (int) $_GET['id'] : 'modules/companies/index.php') ?>"
           class="px-5 py-2.5 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">Cancel</a>
        <span class="ml-auto text-xs text-slate-400"><span class="text-red-500">*</span> required</span>
    </div>
</form>

<?php require_once '../../includes/footer.php'; ?>
