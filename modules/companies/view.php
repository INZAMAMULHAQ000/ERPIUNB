<?php
$pageTitle = 'Company Profile';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('company.view')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { to('modules/companies/index.php'); }

$s = $db->prepare("SELECT * FROM companies WHERE id = ? AND deleted_at IS NULL");
$s->execute([$id]);
$c = $s->fetch();
if (!$c) { flash('nf', 'Company not found.', 'error'); to('modules/companies/index.php'); }

$canEdit = hasPermission('company.edit');

// ————— Related records —————
$parent = null;
if ($c['parent_company_id']) {
    $ps = $db->prepare("SELECT id, company_code, company_name FROM companies WHERE id = ? AND deleted_at IS NULL");
    $ps->execute([$c['parent_company_id']]); $parent = $ps->fetch();
}
$subs = $db->prepare("SELECT id, company_code, company_name, entity_type, status FROM companies WHERE parent_company_id = ? AND deleted_at IS NULL ORDER BY company_name");
$subs->execute([$id]); $subs = $subs->fetchAll();

$rels = $db->prepare("
    SELECT cr.*, p.company_name AS parent_name, ch.company_name AS child_name
    FROM company_relationships cr
    LEFT JOIN companies p  ON p.id  = cr.parent_company_id
    LEFT JOIN companies ch ON ch.id = cr.child_company_id
    WHERE (cr.parent_company_id = ? OR cr.child_company_id = ?) AND cr.status = 'active'
    ORDER BY cr.effective_from DESC
");
$rels->execute([$id, $id]); $rels = $rels->fetchAll();

$users = $db->prepare("
    SELECT u.id, u.name, u.email, u.status, r.name AS role
    FROM user_companies uc
    JOIN users u ON u.id = uc.user_id AND u.deleted_at IS NULL
    LEFT JOIN roles r ON r.id = (SELECT role_id FROM user_roles WHERE user_id = u.id LIMIT 1)
    WHERE uc.company_id = ?
    ORDER BY u.name
");
$users->execute([$id]); $users = $users->fetchAll();

$fy = $db->prepare("SELECT * FROM financial_years WHERE company_id = ? ORDER BY start_date DESC");
$fy->execute([$id]); $fy = $fy->fetchAll();

$banks = $db->prepare("SELECT * FROM company_bank_accounts WHERE company_id = ? AND deleted_at IS NULL ORDER BY is_primary DESC, account_label");
$banks->execute([$id]); $banks = $banks->fetchAll();

$warehouses = $db->prepare("SELECT id, warehouse_code, warehouse_name, address, status FROM warehouses WHERE company_id = ? ORDER BY warehouse_name");
$warehouses->execute([$id]); $warehouses = $warehouses->fetchAll();

$costCenters = $db->prepare("SELECT id, cost_center_code, cost_center_name, status FROM cost_centers WHERE company_id = ? ORDER BY cost_center_name");
$costCenters->execute([$id]); $costCenters = $costCenters->fetchAll();

$group = null;
if ($c['group_id']) {
    $gs = $db->prepare("SELECT id, group_code, group_name FROM company_groups WHERE id = ?");
    $gs->execute([$c['group_id']]); $group = $gs->fetch();
}

// ————— Ledger activity —————
$gl = $db->prepare("
    SELECT COUNT(*) AS line_count, COALESCE(SUM(debit),0) AS total_debit, COALESCE(SUM(credit),0) AS total_credit,
           MIN(posting_date) AS first_post, MAX(posting_date) AS last_post
    FROM general_ledger WHERE company_id = ?");
$gl->execute([$id]); $gl = $gl->fetch();

$jeByStatus = $db->prepare("
    SELECT je.status, COUNT(DISTINCT je.id) AS cnt, COALESCE(SUM(d.debit_amount), 0) AS amount
    FROM journal_entries je
    LEFT JOIN journal_entry_details d ON d.journal_entry_id = je.id
    WHERE je.company_id = ?
    GROUP BY je.status
    ORDER BY cnt DESC");
$jeByStatus->execute([$id]); $jeByStatus = $jeByStatus->fetchAll();

$docCounts = [
    'Purchase orders'   => (int) $db->query("SELECT COUNT(*) FROM purchase_orders WHERE company_id = $id")->fetchColumn(),
    'Requisitions'      => (int) $db->query("SELECT COUNT(*) FROM purchase_requisitions WHERE company_id = $id")->fetchColumn(),
    'RFQs'              => (int) $db->query("SELECT COUNT(*) FROM request_for_quotations WHERE company_id = $id")->fetchColumn(),
    'Goods receipts'    => (int) $db->query("SELECT COUNT(*) FROM goods_receipts WHERE company_id = $id")->fetchColumn(),
    'Vendor bills'      => (int) $db->query("SELECT COUNT(*) FROM vendor_bills WHERE company_id = $id")->fetchColumn(),
    'Inventory moves'   => (int) $db->query("SELECT COUNT(*) FROM inventory_stock_ledger WHERE company_id = $id")->fetchColumn(),
];
$icAccounts = (int) $db->query("SELECT COUNT(*) FROM intercompany_accounts WHERE company_id = $id")->fetchColumn();

$acctName = function ($aid) use ($db) {
    if (!$aid) return null;
    $q = $db->prepare("SELECT CONCAT(account_code,' — ',account_name) FROM chart_of_accounts WHERE id = ?");
    $q->execute([$aid]); return $q->fetchColumn() ?: null;
};

$statusBadges = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-700', 'dormant' => 'bg-amber-100 text-amber-800', 'archived' => 'bg-slate-100 text-slate-600'];
$typeLabels = ['limited_liability' => 'Limited Liability', 'sole_proprietorship' => 'Sole Proprietorship', 'partnership' => 'Partnership', 'free_zone_establishment' => 'Free Zone Establishment', 'branch' => 'Branch', 'subsidiary' => 'Subsidiary', 'holding' => 'Holding', 'ngo' => 'NGO', 'government' => 'Government', 'trust' => 'Trust'];
$consolLabels = ['full' => 'Full consolidation', 'proportionate' => 'Proportionate', 'equity' => 'Equity method', 'none' => 'Not consolidated'];
$buFlags = ['bu_sales' => 'Sales', 'bu_purchasing' => 'Purchasing', 'bu_inventory' => 'Inventory', 'bu_payables' => 'Payables', 'bu_receivables' => 'Receivables'];
$activeBus = [];
foreach ($buFlags as $col => $lbl) { if ((int) $c[$col] === 1) $activeBus[] = $lbl; }

$profileGroups = [
    ['Identity', 'fa-building', [
        ['Entity Type', $typeLabels[$c['entity_type']] ?? $c['entity_type']],
        ['Category', ucfirst(str_replace('_', ' ', $c['company_category']))],
        ['Legal Name', $c['legal_name']], ['Trading Name', $c['trade_name']], ['Short Name', $c['short_name']],
        ['Industry', $c['industry']], ['Sub Industry', $c['sub_industry']],
        ['Established', $c['established_date'] ? date('d M Y', strtotime($c['established_date'])) : null],
        ['D-U-N-S', $c['duns_number']], ['LEI', $c['lei_number']],
        ['Reporting Framework', str_replace('_', ' ', $c['financial_framework'])],
        ['Consolidation', $consolLabels[$c['consolidation_method']] ?? null],
        ['Legal Entity', $c['is_legal_entity'] ? 'Yes' : 'No'],
        ['Operating Unit', $c['is_operating_unit'] ? 'Yes' : 'No'],
        ['Business Unit', $c['is_business_unit'] ? 'Yes' : 'No'],
        ['Statutory Reporting', $c['is_reporting_entity'] ? 'Included' : 'Excluded'],
    ]],
    ['Registration & Statutory', 'fa-certificate', [
        ['Registration No.', $c['registration_number']], ['Tax Number', $c['tax_number']],
        ['VAT Number', $c['vat_number']], ['TRN', $c['trn_number']], ['TIN', $c['tin_number']],
        ['EIN', $c['ein_number']], ['GST State Code', $c['gst_state_code']],
        ['Free Zone', $c['free_zone_name'] ? $c['free_zone_name'] . ($c['free_zone_license'] ? ' (' . $c['free_zone_license'] . ')' : '') : null],
        ['FZ Licence Expires', $c['free_zone_expiry'] ? date('d M Y', strtotime($c['free_zone_expiry'])) : null],
        ['Trade Licence', $c['license_number']], ['Licence Expires', $c['license_expiry'] ? date('d M Y', strtotime($c['license_expiry'])) : null],
        ['Regulator', $c['regulatory_authority']],
        ['Share Capital', $c['share_capital'] !== null ? number_format((float) $c['share_capital'], 2) : null],
        ['Audit Firm', $c['audit_firm']], ['Statutory Auditor', $c['statutory_auditor']],
    ]],
    ['Address & Contact', 'fa-location-dot', [
        ['Address', $c['address_line1']], ['Address 2', $c['address_line2']],
        ['City', $c['city']], ['State', $c['state']], ['Region', $c['region']],
        ['Postal Code', $c['postal_code']], ['Country', $c['country']],
        ['Timezone', $c['timezone']], ['Language', $c['language']],
        ['Phone', $c['phone']], ['Alternate Phone', $c['alternate_phone']], ['Fax', $c['fax']],
        ['Email', $c['email']], ['Support Email', $c['support_email']], ['Website', $c['website']],
        ['Contact', $c['contact_person'] ? $c['contact_person'] . ($c['contact_designation'] ? ' — ' . $c['contact_designation'] : '') : null],
    ]],
    ['Ledger & Accounting Controls', 'fa-coins', [
        ['Base Currency', $c['base_currency']],
        ['Fiscal Year Start', $c['financial_year_start'] && $c['financial_year_start'] !== '0000-00-00' ? date('d M Y', strtotime($c['financial_year_start'])) : null],
        ['Periods / Year', $c['periods_per_year']], ['Rounding Tolerance', $c['posting_tolerance']],
        ['Post to Closed', $c['allow_posting_to_closed'] ? 'Allowed' : 'Blocked'],
        ['Cost Centre Required', $c['require_cost_center'] ? 'Yes' : 'No'],
        ['Project Required', $c['require_project'] ? 'Yes' : 'No'],
        ['Multi-Currency', $c['enable_multicurrency'] ? 'Enabled' : 'Disabled'],
        ['Cash Basis', $c['cash_basis_accounting'] ? 'Yes' : 'No'],
        ['Negative Stock', $c['allow_negative_stock'] ? 'Allowed' : 'Blocked'],
        ['Receivable A/C', $acctName($c['default_receivable_account_id'])],
        ['Payable A/C', $acctName($c['default_payable_account_id'])],
        ['Bank Clearing', $acctName($c['default_bank_account_id'])],
        ['Cash A/C', $acctName($c['default_cash_account_id'])],
        ['Inventory A/C', $acctName($c['default_inventory_account_id'])],
        ['COGS A/C', $acctName($c['default_cogs_account_id'])],
        ['Revenue A/C', $acctName($c['default_sales_account_id'])],
        ['Purchases A/C', $acctName($c['default_purchase_account_id'])],
        ['Tax Payable', $acctName($c['default_tax_account_id'])],
        ['Inter-company', $acctName($c['default_intercompany_account_id'])],
        ['Retained Earnings', $acctName($c['default_retained_earnings_account_id'])],
        ['FX Difference', $acctName($c['default_fx_difference_account_id'])],
    ]],
    ['Tax & E-Invoicing', 'fa-file-invoice', [
        ['Registration Type', str_replace('_', ' ', $c['tax_registration_type'])],
        ['Tax Authority', $c['tax_authority']], ['Tax Office Code', $c['tax_office_code']],
        ['VAT Return', ucfirst($c['vat_return_frequency']) . ($c['vat_return_frequency'] !== 'none' ? ' (day ' . $c['vat_return_day'] . ')' : '')],
        ['Withholding Agent', $c['withholding_agent'] ? 'Yes' : 'No'],
        ['Default WHT Rate', (float) $c['default_withholding_rate'] > 0 ? $c['default_withholding_rate'] . '%' : null],
        ['E-Invoicing', $c['e_invoicing_enabled'] ? 'Enabled' : 'Disabled'],
        ['Regime', $c['e_invoicing_system'] !== 'none' ? str_replace('_', ' ', strtoupper($c['e_invoicing_system'])) : null],
        ['Stage', $c['e_invoicing_stage'] ? str_replace('_', ' ', $c['e_invoicing_stage']) : null],
        ['DD Mandate Prefix', $c['direct_debit_prefix']],
    ]],
    ['Document Numbering & Terms', 'fa-hashtag', [
        ['Invoice', $c['invoice_prefix'] . str_repeat('0', max(0, (int) $c['invoice_padding']))],
        ['Quote', $c['quote_prefix'] . '…'], ['Receipt', $c['receipt_prefix'] . '…'],
        ['Journal', $c['je_prefix'] . '…'], ['Vendor Bill', $c['vendor_bill_prefix'] . '…'],
        ['Requisition', $c['pr_prefix'] . '…'], ['Purchase Order', $c['po_prefix'] . '…'],
        ['Goods Receipt', $c['gr_prefix'] . '…'],
        ['Payment Reference', $c['payment_reference_format']],
        ['Default Terms', $c['default_payment_terms']], ['Shipping', $c['default_shipping_condition']],
        ['Incoterms', $c['default_incoterms']], ['Price List', $c['default_price_list']],
        ['Quote Validity', $c['quote_validity_days'] . ' days'],
    ]],
    ['Branding & Operations', 'fa-palette', [
        ['Logo', $c['logo_path']], ['Brand Colour', $c['theme_color']],
        ['Letterhead', $c['letterhead_template_id'] ? (function () use ($db, $c) { $q = $db->prepare("SELECT template_name FROM templates WHERE id=?"); $q->execute([$c['letterhead_template_id']]); return $q->fetchColumn() ?: null; })() : null],
        ['Signatory Text', $c['signature_text']], ['Footer Note', $c['document_footer_note']],
        ['Default Warehouse', $c['default_warehouse_id'] ? (function () use ($db, $c) { $q = $db->prepare("SELECT warehouse_name FROM warehouses WHERE id=?"); $q->execute([$c['default_warehouse_id']]); return $q->fetchColumn() ?: null; })() : null],
        ['Default Cost Centre', $c['default_cost_center_id'] ? (function () use ($db, $c) { $q = $db->prepare("SELECT cost_center_name FROM cost_centers WHERE id=?"); $q->execute([$c['default_cost_center_id']]); return $q->fetchColumn() ?: null; })() : null],
        ['Working Days', $c['working_days']], ['Weekend', $c['weekend_days']], ['Hours', $c['working_hours']],
        ['Employees', $c['employee_count']], ['Annual Revenue', $c['annual_revenue'] !== null ? number_format((float) $c['annual_revenue'], 2) : null],
        ['Branch Code', $c['branch_code']],
    ]],
];
?>

<!-- ===== Header ===== -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <?php if ($c['logo_path']): ?>
            <img src="<?= htmlspecialchars($c['logo_path']) ?>" alt="" class="w-14 h-14 rounded-xl object-contain bg-white border border-slate-200 p-1">
        <?php else: ?>
            <div class="w-14 h-14 rounded-xl bg-indigo-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                <i class="fas fa-building text-white text-lg"></i>
            </div>
        <?php endif; ?>
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($c['company_name']) ?></h1>
                <span class="px-2 py-0.5 text-xs rounded-full <?= $statusBadges[$c['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($c['status']) ?></span>
                <?php if ((int) $c['is_default'] === 1): ?><span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700 font-semibold">DEFAULT</span><?php endif; ?>
                <?php if ((int) $c['is_legal_entity'] === 1): ?><span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600">Legal Entity</span><?php endif; ?>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                <span class="font-mono"><?= htmlspecialchars($c['company_code']) ?></span>
                &middot; <?= htmlspecialchars($typeLabels[$c['entity_type']] ?? $c['entity_type']) ?>
                &middot; <?= htmlspecialchars($c['base_currency']) ?>
                <?php if ($c['country']): ?>&middot; <?= htmlspecialchars($c['country']) ?><?php endif; ?>
                <?php if ($c['vat_number']): ?>&middot; VAT <?= htmlspecialchars($c['vat_number']) ?><?php endif; ?>
            </p>
            <?php if ($activeBus): ?>
            <p class="text-xs text-slate-400 mt-1"><i class="fas fa-diagram-project mr-1"></i>BU functions: <?= htmlspecialchars(implode(', ', $activeBus)) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($canEdit): ?>
        <a href="<?= url('modules/companies/form.php?id=' . $id) ?>" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-pen mr-1.5"></i>Edit
        </a>
        <?php endif; ?>
        <a href="<?= url('modules/companies/index.php') ?>" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left"></i></a>
    </div>
</div>

<!-- ===== KPIs ===== -->
<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
    <?php
    $kpis = [
        ['Users', count($users), 'fa-users', 'text-indigo-500', 'with access'],
        ['Subsidiaries', count($subs), 'fa-sitemap', 'text-violet-500', 'direct children'],
        ['Financial Years', count($fy), 'fa-calendar', 'text-blue-500', 'configured'],
        ['Journal Lines', (int) $gl['line_count'], 'fa-book', 'text-emerald-500', 'posted to GL'],
        ['Bank Accounts', count($banks), 'fa-vault', 'text-amber-500', 'registered'],
        ['Warehouses', count($warehouses), 'fa-warehouse', 'text-teal-500', 'locations'],
    ];
    foreach ($kpis as [$lbl, $val, $icon, $colour, $sub]): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide"><?= $lbl ?></span>
            <i class="fas <?= $icon ?> <?= $colour ?> text-xs"></i>
        </div>
        <p class="text-xl font-bold text-slate-800"><?= number_format($val) ?></p>
        <p class="text-[11px] text-slate-400"><?= $sub ?></p>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!$c['financial_year_start'] || $c['financial_year_start'] === '0000-00-00'): ?>
<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <i class="fas fa-triangle-exclamation mr-1.5"></i>
    <strong>No fiscal year start.</strong> Period closing and group consolidation resolve financial years per company, so this entity cannot post.
    <?php if ($canEdit): ?><a href="<?= url('modules/companies/form.php?id=' . $id) ?>" class="underline font-medium">Set it now</a>.<?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Tabs ===== -->
<?php $tabs = [
    'profile' => ['Profile', 'fa-address-card'],
    'structure' => ['Group Structure', count($subs) + count($rels)],
    'access' => ['Users & Access', count($users)],
    'financial' => ['Ledger & Banks', count($fy) + count($banks)],
    'operations' => ['Locations & Docs', count($warehouses) + count($costCenters)],
]; ?>
<div class="border-b border-slate-200 mb-6 overflow-x-auto">
    <div class="flex gap-1 min-w-max">
        <?php foreach ($tabs as $key => $t): $badge = is_int($t[1]) ? $t[1] : null; ?>
        <button data-tab="<?= $key ?>" class="co-tab px-4 py-2.5 text-sm font-medium border-b-2 <?= $key === 'profile' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fas <?= is_string($t[1]) ? $t[1] : 'fa-layer-group' ?> mr-1.5 text-xs"></i><?= $t[0] ?>
            <?php if ($badge !== null && $badge > 0): ?><span class="ml-1.5 px-1.5 py-0.5 text-[10px] bg-slate-100 text-slate-600 rounded-full"><?= $badge ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== Profile ===== -->
<div class="co-panel" data-panel="profile">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <?php foreach ($profileGroups as [$gtitle, $gicon, $items]):
            $items = array_values(array_filter($items, fn($i) => $i[1] !== null && $i[1] !== '')); ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                <i class="fas <?= $gicon ?> text-indigo-500 text-xs"></i> <?= $gtitle ?>
            </h3>
            <?php if (empty($items)): ?>
            <p class="text-sm text-slate-400">Not captured yet.<?php if ($canEdit): ?> <a href="<?= url('modules/companies/form.php?id=' . $id) ?>" class="text-indigo-600 hover:underline">Complete the record</a><?php endif; ?></p>
            <?php else: ?>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                <?php foreach ($items as [$lbl, $val]): ?>
                <div class="<?= in_array($lbl, ['Signatory Text','Footer Note','Terms & Conditions']) ? 'sm:col-span-2' : '' ?>">
                    <dt class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide"><?= $lbl ?></dt>
                    <dd class="text-sm text-slate-800 mt-0.5 break-words"><?= htmlspecialchars($val) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== Structure ===== -->
<div class="co-panel hidden" data-panel="structure">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Position in Hierarchy</h3>
            <?php if ($group): ?>
            <div class="mb-3 p-3 bg-slate-50 rounded-lg">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Company Group</p>
                <a href="<?= url('modules/finance/group/relationships/index.php') ?>" class="text-sm font-medium text-slate-800 hover:text-indigo-600"><?= htmlspecialchars($group['group_name']) ?></a>
                <span class="text-xs text-slate-400 font-mono ml-1"><?= htmlspecialchars($group['group_code']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($parent): ?>
            <div class="p-3 bg-indigo-50 rounded-lg">
                <p class="text-[10px] font-semibold text-indigo-400 uppercase tracking-wide">Parent Company</p>
                <a href="<?= url('modules/companies/view.php?id=' . $parent['id']) ?>" class="text-sm font-medium text-indigo-800 hover:underline"><?= htmlspecialchars($parent['company_name']) ?></a>
                <span class="text-xs text-indigo-400 font-mono ml-1"><?= htmlspecialchars($parent['company_code']) ?></span>
            </div>
            <?php else: ?>
            <p class="text-sm text-slate-400">Top of the hierarchy — no parent.</p>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Direct Subsidiaries</h3>
            <?php if (empty($subs)): ?><p class="text-sm text-slate-400">None.</p><?php else: ?>
            <div class="space-y-2">
                <?php foreach ($subs as $sb): ?>
                <a href="<?= url('modules/companies/view.php?id=' . $sb['id']) ?>" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg hover:bg-indigo-50 transition">
                    <div><p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($sb['company_name']) ?></p>
                    <p class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($sb['company_code']) ?> · <?= htmlspecialchars($typeLabels[$sb['entity_type']] ?? $sb['entity_type']) ?></p></div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full <?= $statusBadges[$sb['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($sb['status']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Ownership Relationships</h3>
        <?php if (empty($rels)): ?><p class="text-sm text-slate-400">No ownership records. Manage them under Finance → Group → Relationships.</p><?php else: ?>
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100">
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Parent</th>
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Child</th>
                <th class="text-right text-[10px] font-semibold text-slate-400 uppercase pb-2">Ownership</th>
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2 pl-3">Type</th>
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Effective</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($rels as $r): ?>
                <tr class="<?= $r['parent_company_id'] == $id ? '' : 'bg-slate-50/50' ?>">
                    <td class="py-2 text-slate-700"><?= htmlspecialchars($r['parent_name'] ?? '—') ?></td>
                    <td class="py-2 text-slate-700"><?= htmlspecialchars($r['child_name'] ?? '—') ?></td>
                    <td class="py-2 text-right font-medium"><?= rtrim(rtrim(number_format((float) $r['ownership_percentage'], 2), '0'), '.') ?>%</td>
                    <td class="py-2 pl-3 text-xs text-slate-500"><?= htmlspecialchars($r['relationship_type'] ?? '—') ?></td>
                    <td class="py-2 text-xs text-slate-500"><?= date('d M Y', strtotime($r['effective_from'])) ?><?= $r['effective_to'] ? ' → ' . date('d M Y', strtotime($r['effective_to'])) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Access ===== -->
<div class="co-panel hidden" data-panel="access">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Users With Access to This Company</h3>
        <?php if (empty($users)): ?>
        <p class="text-sm text-slate-400">No users are assigned. Procurement and inventory restrict records to company members, so nobody can transact against this entity.</p>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100">
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">User</th>
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Role</th>
                <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full bg-indigo-50 flex items-center justify-center"><span class="text-[11px] font-bold text-indigo-600"><?= strtoupper(substr($u['name'], 0, 1)) ?></span></div>
                            <div><p class="font-medium text-slate-800"><?= htmlspecialchars($u['name']) ?></p><p class="text-[11px] text-slate-400"><?= htmlspecialchars($u['email']) ?></p></div>
                        </div>
                    </td>
                    <td class="py-2.5 text-slate-600"><?= htmlspecialchars($u['role'] ?? '—') ?></td>
                    <td class="py-2.5"><span class="px-2 py-0.5 text-[11px] rounded-full <?= $u['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>"><?= ucfirst($u['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Ledger & banks ===== -->
<div class="co-panel hidden" data-panel="financial">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">General Ledger Position</h3>
            <dl class="space-y-3">
                <div class="flex justify-between"><dt class="text-xs text-slate-400 uppercase font-semibold">Posted lines</dt><dd class="text-sm font-medium"><?= number_format((int) $gl['line_count']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-xs text-slate-400 uppercase font-semibold">Total debit</dt><dd class="text-sm font-medium"><?= number_format((float) $gl['total_debit'], 2) ?></dd></div>
                <div class="flex justify-between"><dt class="text-xs text-slate-400 uppercase font-semibold">Total credit</dt><dd class="text-sm font-medium"><?= number_format((float) $gl['total_credit'], 2) ?></dd></div>
                <?php $diff = (float) $gl['total_debit'] - (float) $gl['total_credit']; ?>
                <div class="flex justify-between border-t border-slate-100 pt-3">
                    <dt class="text-xs text-slate-400 uppercase font-semibold">Balance</dt>
                    <dd class="text-sm font-bold <?= abs($diff) > 0.01 ? 'text-red-600' : 'text-green-600' ?>"><?= number_format($diff, 2) ?><?= abs($diff) > 0.01 ? ' ⚠ out of balance' : ' ✓ balanced' ?></dd>
                </div>
                <div class="flex justify-between"><dt class="text-xs text-slate-400 uppercase font-semibold">First posting</dt><dd class="text-sm"><?= $gl['first_post'] ? date('d M Y', strtotime($gl['first_post'])) : '—' ?></dd></div>
                <div class="flex justify-between"><dt class="text-xs text-slate-400 uppercase font-semibold">Last posting</dt><dd class="text-sm"><?= $gl['last_post'] ? date('d M Y', strtotime($gl['last_post'])) : '—' ?></dd></div>
                <div class="flex justify-between"><dt class="text-xs text-slate-400 uppercase font-semibold">Intercompany accounts</dt><dd class="text-sm"><?= $icAccounts ?></dd></div>
            </dl>
            <?php if ($jeByStatus): ?>
            <div class="mt-4 pt-4 border-t border-slate-100">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-2">Journals by status</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($jeByStatus as $j): ?>
                    <span class="px-2.5 py-1 bg-slate-50 rounded-lg text-xs"><span class="font-medium capitalize"><?= htmlspecialchars($j['status']) ?></span> <span class="text-slate-400"><?= (int) $j['cnt'] ?> · <?= number_format((float) $j['amount'], 0) ?></span></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Financial Years</h3>
            <?php if (empty($fy)): ?>
            <p class="text-sm text-slate-400">No financial years configured for this company. Create them under Finance → Chart of Accounts / Financial Year.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100">
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Year</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Period</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($fy as $f): ?>
                    <tr>
                        <td class="py-2 font-medium text-slate-800"><?= htmlspecialchars($f['year_name']) ?><span class="text-[11px] text-slate-400 font-mono ml-1.5"><?= htmlspecialchars($f['year_code']) ?></span></td>
                        <td class="py-2 text-xs text-slate-500"><?= date('d M Y', strtotime($f['start_date'])) ?> → <?= date('d M Y', strtotime($f['end_date'])) ?></td>
                        <td class="py-2"><span class="px-2 py-0.5 text-[11px] rounded-full <?= $f['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($f['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Bank Accounts</h3>
            <?php if (empty($banks)): ?>
            <p class="text-sm text-slate-400">No bank accounts registered for this company.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($banks as $b): ?>
                <div class="border border-slate-100 rounded-lg p-4 relative">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-vault text-amber-500 text-xs"></i>
                        <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($b['account_label']) ?></span>
                        <?php if ($b['is_primary']): ?><span class="text-[10px] text-indigo-600 font-semibold">PRIMARY</span><?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-600"><?= htmlspecialchars($b['bank_name']) ?><?= $b['branch'] ? ' · ' . htmlspecialchars($b['branch']) : '' ?></p>
                    <p class="text-xs text-slate-500 mt-1 font-mono"><?= $b['iban'] ? htmlspecialchars($b['iban']) : ($b['account_number'] ? '••••' . substr($b['account_number'], -4) : '—') ?></p>
                    <p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars(str_replace('_', ' ', $b['account_type'])) ?> · <?= htmlspecialchars($b['currency_code']) ?> · <?= number_format((float) $b['opening_balance'], 2) ?></p>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('modules/companies/delete_bank.php') ?>" class="absolute top-3 right-3" onsubmit="return confirm('Remove this bank account?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="company_id" value="<?= $id ?>">
                        <button class="text-slate-300 hover:text-red-500 text-xs"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($canEdit): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Add Bank Account</h3>
            <form method="post" action="<?= url('modules/companies/save_bank.php') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="company_id" value="<?= $id ?>">
                <input name="account_label" required placeholder="Label (e.g. Main Operating)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="bank_name" required placeholder="Bank name" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="branch" placeholder="Branch" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="account_name" placeholder="Account holder" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="account_number" placeholder="Account number" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <input name="iban" placeholder="IBAN" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <input name="swift_bic" placeholder="SWIFT/BIC" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select name="currency_code" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                        <?php foreach (array_keys(getCurrencies()) as $cc): ?><option value="<?= $cc ?>" <?= $cc === $c['base_currency'] ? 'selected' : '' ?>><?= $cc ?></option><?php endforeach; ?>
                    </select>
                    <select name="account_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                        <?php foreach (['current','savings','margin','escrow','fx','fixed_deposit'] as $at): ?><option value="<?= $at ?>"><?= ucfirst(str_replace('_',' ',$at)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <input name="opening_balance" type="number" step="0.01" placeholder="Opening balance" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300 text-indigo-600">Primary account</label>
                <button class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Save Account</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Locations & documents ===== -->
<div class="co-panel hidden" data-panel="operations">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Warehouses & Locations</h3>
            <?php if (empty($warehouses)): ?><p class="text-sm text-slate-400">No warehouses registered to this company.</p><?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($warehouses as $w): ?>
                <div class="py-2.5 flex items-center justify-between">
                    <div><p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($w['warehouse_name']) ?>
                        <?php if ((int) $c['default_warehouse_id'] === (int) $w['id']): ?><span class="ml-1.5 text-[10px] px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded-full">DEFAULT</span><?php endif; ?></p>
                    <p class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($w['warehouse_code']) ?><?= $w['address'] ? ' · ' . htmlspecialchars(substr($w['address'], 0, 60)) : '' ?></p></div>
                    <span class="text-[11px] px-2 py-0.5 rounded-full <?= $w['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($w['status']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Cost Centres</h3>
            <?php if (empty($costCenters)): ?><p class="text-sm text-slate-400">No cost centres registered to this company.</p><?php else: ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($costCenters as $x): ?>
                <span class="px-3 py-1.5 bg-slate-50 rounded-lg text-sm <?= (int) $c['default_cost_center_id'] === (int) $x['id'] ? 'ring-1 ring-indigo-300' : '' ?>">
                    <span class="font-mono text-[11px] text-slate-400"><?= htmlspecialchars($x['cost_center_code']) ?></span> <?= htmlspecialchars($x['cost_center_name']) ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Transaction Volume by Module</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($docCounts as $lbl => $n): ?>
            <div class="p-4 bg-slate-50 rounded-lg text-center">
                <p class="text-2xl font-bold <?= $n > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= number_format($n) ?></p>
                <p class="text-[11px] text-slate-500 mt-1"><?= $lbl ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('.co-tab'), panels = document.querySelectorAll('.co-panel');
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.className = 'co-tab px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-800'; });
            t.className = 'co-tab px-4 py-2.5 text-sm font-medium border-b-2 border-indigo-600 text-indigo-700';
            panels.forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== t.dataset.tab); });
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
