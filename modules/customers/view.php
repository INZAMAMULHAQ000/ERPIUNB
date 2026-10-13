<?php
$pageTitle = 'Customer Profile';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.view')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { to('modules/customers/index.php'); }

$s = $db->prepare("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL");
$s->execute([$id]);
$c = $s->fetch();
if (!$c) { flash('nf', 'Customer not found.', 'error'); to('modules/customers/index.php'); }

$canEdit = hasPermission('customers.edit');

// ————— Receivables exposure —————
$st = $db->prepare("
    SELECT COUNT(*) AS invoices,
           COALESCE(SUM(total_amount), 0) AS billed,
           COALESCE(SUM(paid_amount), 0)  AS collected,
           COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft','void') THEN balance_amount ELSE 0 END), 0) AS outstanding,
           COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft','void') AND due_date < CURDATE() THEN balance_amount ELSE 0 END), 0) AS overdue
    FROM customer_invoices WHERE client_id = ?");
$st->execute([$id]);
$fin = $st->fetch();

$available = (float) $c['credit_limit'] - (float) $fin['outstanding'];
$util      = (float) $c['credit_limit'] > 0 ? min(100, round(((float) $fin['outstanding'] / (float) $c['credit_limit']) * 100)) : null;

// ————— Sub-records —————
$contacts = $db->prepare("SELECT * FROM client_contacts WHERE client_id = ? ORDER BY is_primary DESC, name");
$contacts->execute([$id]); $contacts = $contacts->fetchAll();

$addresses = $db->prepare("SELECT * FROM customer_addresses WHERE client_id = ? AND deleted_at IS NULL ORDER BY is_default DESC, address_type, label");
$addresses->execute([$id]); $addresses = $addresses->fetchAll();

$notes = $db->prepare("SELECT n.*, u.name AS author FROM client_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.client_id = ? ORDER BY n.created_at DESC LIMIT 30");
$notes->execute([$id]); $notes = $notes->fetchAll();

$docs = $db->prepare("SELECT d.*, u.name AS uploader FROM client_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.client_id = ? ORDER BY d.created_at DESC");
$docs->execute([$id]); $docs = $docs->fetchAll();

$invoices = $db->prepare("SELECT id, invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_amount, status FROM customer_invoices WHERE client_id = ? ORDER BY invoice_date DESC LIMIT 10");
$invoices->execute([$id]); $invoices = $invoices->fetchAll();

$receipts = $db->prepare("SELECT id, receipt_number, receipt_date, amount, payment_method, reference_number FROM customer_receipts WHERE client_id = ? ORDER BY receipt_date DESC LIMIT 10");
$receipts->execute([$id]); $receipts = $receipts->fetchAll();

$deals = $db->prepare("SELECT id, deal_title, stage, deal_value, expected_closing_date FROM deals WHERE client_id = ? ORDER BY id DESC LIMIT 8");
$deals->execute([$id]); $deals = $deals->fetchAll();

$tickets = $db->prepare("SELECT id, subject, priority, status, created_at FROM tickets WHERE client_id = ? ORDER BY id DESC LIMIT 8");
$tickets->execute([$id]); $tickets = $tickets->fetchAll();

$openTickets = $db->prepare("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status NOT IN ('closed','resolved')");
$openTickets->execute([$id]); $openTickets = (int) $openTickets->fetchColumn();

$people = $db->prepare("SELECT id, first_name, last_name, category FROM people WHERE customer_id = ? ORDER BY first_name");
$people->execute([$id]); $people = $people->fetchAll();

$parent = null;
if ($c['parent_client_id']) {
    $ps = $db->prepare("SELECT id, client_code, company_name FROM clients WHERE id = ? AND deleted_at IS NULL");
    $ps->execute([$c['parent_client_id']]); $parent = $ps->fetch();
}
$children = $db->prepare("SELECT id, client_code, company_name, status FROM clients WHERE parent_client_id = ? AND deleted_at IS NULL ORDER BY company_name");
$children->execute([$id]); $children = $children->fetchAll();

// 12-month billing trend
$trend = $db->prepare("
    SELECT DATE_FORMAT(invoice_date, '%b %Y') AS label,
           COALESCE(SUM(total_amount), 0) AS billed,
           COALESCE(SUM(paid_amount), 0)  AS collected
    FROM customer_invoices
    WHERE client_id = ? AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY MIN(invoice_date)");
$trend->execute([$id]); $trend = $trend->fetchAll();

$empName = function ($eid) use ($db) {
    if (!$eid) return null;
    $q = $db->prepare("SELECT CONCAT(first_name,' ',last_name) FROM employees WHERE id = ?");
    $q->execute([$eid]); $r = $q->fetchColumn(); return $r ?: null;
};
$statusBadges = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-700', 'prospect' => 'bg-blue-100 text-blue-800', 'blocked' => 'bg-red-100 text-red-800', 'archived' => 'bg-slate-100 text-slate-600'];
$invBadges    = ['draft' => 'bg-slate-100 text-slate-600', 'approved' => 'bg-blue-100 text-blue-800', 'sent' => 'bg-indigo-100 text-indigo-800', 'partial' => 'bg-amber-100 text-amber-800', 'paid' => 'bg-green-100 text-green-800', 'overdue' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-gray-100 text-gray-500'];

$cur = $c['currency_code'];
$money = function ($v) use ($cur) { return $cur . ' ' . number_format((float) $v, 2); };

// Read-only profile display groups.
$profileGroups = [
    ['Classification', 'fa-id-badge', [
        ['Type', $c['customer_type']], ['Account Group', $c['account_group']], ['Segment', $c['segment']],
        ['Industry', $c['industry']], ['Lead Source', $c['lead_source']], ['One-Time', $c['is_one_time'] ? 'Yes' : 'No'],
        ['Customer Since', $c['customer_since'] ? date('d M Y', strtotime($c['customer_since'])) : null],
        ['Legal Name', $c['legal_name']], ['Trade Name', $c['trade_name']], ['Search Term', $c['search_term']],
    ]],
    ['Contact', 'fa-address-card', [
        ['Contact Person', $c['contact_person']], ['Email', $c['email']], ['Phone', $c['phone']],
        ['Alternate Phone', $c['alternate_phone']], ['WhatsApp', $c['whatsapp']], ['Website', $c['website']],
        ['LinkedIn', $c['linkedin_url']], ['Preferred Method', $c['preferred_contact_method']],
        ['Language', $c['preferred_language']], ['Timezone', $c['timezone']],
    ]],
    ['Billing Address', 'fa-location-dot', [
        ['Line 1', $c['address_line1']], ['Line 2', $c['address_line2']], ['City', $c['city']],
        ['State', $c['state']], ['Postal Code', $c['postal_code']], ['Country', $c['country']],
    ]],
    ['Registration & Tax', 'fa-file-invoice', [
        ['Registration No.', $c['registration_number']], ['Tax ID', $c['tax_id']], ['Secondary Tax ID', $c['tax_id_secondary']],
        ['GSTIN / VAT', $c['gst_vat_number']], ['Tax Category', $c['tax_category']], ['Jurisdiction', $c['tax_jurisdiction']],
        ['State Code', $c['state_code']], ['Tax Reg. Date', $c['tax_registration_date'] ? date('d M Y', strtotime($c['tax_registration_date'])) : null],
        ['Exemption Ref.', $c['tax_exemption_ref']], ['Exemption Valid Until', $c['tax_exemption_upto'] ? date('d M Y', strtotime($c['tax_exemption_upto'])) : null],
        ['Default WHT / TDS', (float) $c['withholding_tax_rate'] > 0 ? $c['withholding_tax_rate'] . '%' : null],
        ['Import / Export Code', $c['export_code']],
    ]],
    ['Financial Terms', 'fa-coins', [
        ['Currency', $cur], ['Payment Terms', $c['payment_terms']], ['Terms (days)', $c['payment_term_days']],
        ['Payment Method', $c['payment_method']], ['Price List', $c['price_list']], ['Billing Cycle', str_replace('_', ' ', $c['billing_cycle'])],
        ['Tax Inclusive', $c['tax_inclusive'] ? 'Yes' : 'No'], ['Consolidated Billing', $c['consolidated_billing'] ? 'Yes' : 'No'],
        ['Partial Payment', $c['allow_partial_payment'] ? 'Allowed' : 'Not allowed'],
        ['Cash Discount', (float) $c['cash_discount_percent'] > 0 ? $c['cash_discount_percent'] . '%' : null],
        ['Discount Days', (int) $c['discount_days'] > 0 ? $c['discount_days'] : null],
        ['Late Interest', (float) $c['interest_rate'] > 0 ? $c['interest_rate'] . '%' : null],
        ['Collection Priority', $c['collection_priority']],
        ['Receivable Account', $c['default_receivable_account_id'] ? (function () use ($db, $c) { $q = $db->prepare("SELECT CONCAT(account_code,' — ',account_name) FROM chart_of_accounts WHERE id=?"); $q->execute([$c['default_receivable_account_id']]); return $q->fetchColumn() ?: null; })() : null],
    ]],
    ['Invoicing & Remittance', 'fa-file-invoice-dollar', [
        ['Delivery', ucfirst(str_replace('_', ' ', $c['invoice_delivery']))], ['Invoice Email', $c['invoice_email']],
        ['CC Emails', $c['invoice_cc_emails']], ['Bank', $c['bank_name']], ['Account Holder', $c['bank_account_name']],
        ['Account Number', $c['bank_account_number'] ? '••••' . substr($c['bank_account_number'], -4) : null],
        ['Branch', $c['bank_branch']], ['SWIFT / IBAN / IFSC', $c['bank_identifier']],
    ]],
    ['Sales & Commercial', 'fa-handshake', [
        ['Account Manager', $empName($c['assigned_manager'])], ['Sales Rep', $empName($c['sales_representative_id'])],
        ['Support Rep', $empName($c['support_representative_id'])], ['Region', $c['sales_region']],
        ['Channel', $c['distribution_channel']], ['Shipping Condition', $c['shipping_condition']],
        ['Incoterms', $c['incoterms'] ? $c['incoterms'] . ($c['incoterms_location'] ? ' — ' . $c['incoterms_location'] : '') : null],
        ['Lead Time (days)', (int) $c['delivery_lead_time_days'] > 0 ? $c['delivery_lead_time_days'] : null],
        ['Default Discount', (float) $c['default_discount_percent'] > 0 ? $c['default_discount_percent'] . '%' : null],
        ['Commission', (float) $c['commission_rate'] > 0 ? $c['commission_rate'] . '%' : null],
        ['Contract Start', $c['contract_start_date'] ? date('d M Y', strtotime($c['contract_start_date'])) : null],
        ['Contract End', $c['contract_end_date'] ? date('d M Y', strtotime($c['contract_end_date'])) : null],
    ]],
    ['Company Profile', 'fa-building', [
        ['Size', $c['company_size']], ['Employees', $c['number_of_employees']],
        ['Annual Revenue', $c['annual_revenue'] !== null ? number_format((float) $c['annual_revenue'], 2) : null],
        ['Established', $c['year_established']],
    ]],
    ['Compliance', 'fa-clipboard-check', [
        ['KYC Status', str_replace('_', ' ', $c['kyc_status'])],
        ['KYC Verified At', $c['kyc_verified_at'] ? date('d M Y H:i', strtotime($c['kyc_verified_at'])) : null],
        ['Risk Level', $c['risk_level']], ['Marketing Consent', $c['marketing_consent'] ? 'Granted' : 'No'],
        ['Data Processing Consent', $c['data_processing_consent'] ? 'Granted' : 'No'],
        ['Internal Notes', $c['internal_notes']],
    ]],
];
?>

<!-- ===== Header ===== -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-14 h-14 rounded-xl bg-indigo-600 flex items-center justify-center flex-shrink-0 shadow-sm">
            <span class="text-xl font-bold text-white"><?= strtoupper(substr($c['company_name'], 0, 1)) ?></span>
        </div>
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($c['company_name']) ?></h1>
                <span class="px-2 py-0.5 text-xs rounded-full <?= $statusBadges[$c['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($c['status']) ?></span>
                <?php if ($c['credit_hold'] !== 'no'): ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-semibold"><i class="fas fa-lock text-[9px] mr-1"></i><?= ucfirst($c['credit_hold']) ?> credit hold</span>
                <?php endif; ?>
                <?php if ($c['is_one_time']): ?><span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600">One-time</span><?php endif; ?>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                <span class="font-mono"><?= htmlspecialchars($c['client_code']) ?></span>
                &middot; <?= htmlspecialchars(ucfirst($c['customer_type'])) ?>
                <?php if ($c['segment']): ?>&middot; <?= htmlspecialchars($c['segment']) ?><?php endif; ?>
                <?php if ($c['city'] || $c['country']): ?>&middot; <?= htmlspecialchars(trim(($c['city'] ?? '') . ', ' . ($c['country'] ?? ''), ', ')) ?><?php endif; ?>
            </p>
            <?php if ($parent): ?>
            <p class="text-xs text-slate-400 mt-1">
                <i class="fas fa-sitemap mr-1"></i>Child of
                <a href="<?= url('modules/customers/view.php?id=' . $parent['id']) ?>" class="text-indigo-600 hover:underline"><?= htmlspecialchars($parent['company_name']) ?></a>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($canEdit): ?>
        <a href="<?= url('modules/customers/form.php?id=' . $id) ?>" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-pen mr-1.5"></i>Edit
        </a>
        <?php endif; ?>
        <?php if (hasPermission('finance.invoice.create') || hasPermission('customer_invoices.create')): ?>
        <a href="<?= url('modules/finance/customer_invoices/create.php?client_id=' . $id) ?>" class="px-4 py-2 text-sm font-medium text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition">
            <i class="fas fa-file-invoice mr-1.5"></i>New Invoice
        </a>
        <?php endif; ?>
        <a href="<?= url('modules/customers/index.php') ?>" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left"></i></a>
    </div>
</div>

<!-- ===== Financial KPIs ===== -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Credit Limit</p>
        <p class="text-lg font-bold text-slate-800 mt-1"><?= $money($c['credit_limit']) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5"><?= $c['credit_rating'] ? 'Rating ' . htmlspecialchars($c['credit_rating']) : 'No rating' ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Outstanding</p>
        <p class="text-lg font-bold text-indigo-600 mt-1"><?= $money($fin['outstanding']) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5"><?= (int) $fin['invoices'] ?> invoice<?= $fin['invoices'] == 1 ? '' : 's' ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Overdue</p>
        <p class="text-lg font-bold <?= (float) $fin['overdue'] > 0 ? 'text-red-600' : 'text-slate-800' ?> mt-1"><?= $money($fin['overdue']) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5">past due date</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Available Credit</p>
        <p class="text-lg font-bold <?= $available < 0 ? 'text-red-600' : 'text-green-600' ?> mt-1"><?= $money($available) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5"><?= $util === null ? 'no limit configured' : $util . '% utilised' ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Lifetime</p>
        <p class="text-lg font-bold text-slate-800 mt-1"><?= $money($fin['billed']) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5">billed &middot; <?= $money($fin['collected']) ?> collected</p>
    </div>
</div>

<?php if ($util !== null): ?>
<div class="bg-white rounded-xl shadow-sm border border-slate-100 px-5 py-3 mb-6">
    <div class="flex items-center gap-4">
        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Credit Utilisation</span>
        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full <?= $util >= 100 ? 'bg-red-500' : ($util >= 80 ? 'bg-amber-500' : 'bg-green-500') ?>" style="width: <?= $util ?>%"></div>
        </div>
        <span class="text-sm font-bold text-slate-700 w-12 text-right"><?= $util ?>%</span>
    </div>
</div>
<?php endif; ?>

<!-- ===== Tabs ===== -->
<?php $tabs = [
    'profile' => ['Profile', 'fa-address-card'], 'contacts' => ['Contacts', count($contacts)],
    'addresses' => ['Addresses', count($addresses)], 'financial' => ['Financial', count($invoices) + count($receipts)],
    'pipeline' => ['Sales & Support', count($deals) + count($tickets)], 'documents' => ['Documents', count($docs)],
    'notes' => ['Notes', count($notes)],
]; ?>
<div class="border-b border-slate-200 mb-6 overflow-x-auto">
    <div class="flex gap-1 min-w-max">
        <?php foreach ($tabs as $key => $t): $badge = is_int($t[1]) ? $t[1] : null; ?>
        <button data-tab="<?= $key ?>" class="cust-tab px-4 py-2.5 text-sm font-medium border-b-2 <?= $key === 'profile' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fas <?= is_string($t[1]) ? $t[1] : 'fa-layer-group' ?> mr-1.5 text-xs"></i><?= $t[0] ?>
            <?php if ($badge !== null && $badge > 0): ?><span class="ml-1.5 px-1.5 py-0.5 text-[10px] bg-slate-100 text-slate-600 rounded-full"><?= $badge ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== Tab: Profile ===== -->
<div class="cust-panel" data-panel="profile">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <?php foreach ($profileGroups as [$gtitle, $gicon, $items]): $items = array_values(array_filter($items, fn($i) => $i[1] !== null && $i[1] !== '')); ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 <?= $gtitle === 'Compliance' ? 'lg:col-span-2' : '' ?>">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                <i class="fas <?= $gicon ?> text-indigo-500 text-xs"></i> <?= $gtitle ?>
            </h3>
            <?php if (empty($items)): ?>
            <p class="text-sm text-slate-400">Not captured yet. <a href="<?= url('modules/customers/form.php?id=' . $id) ?>" class="text-indigo-600 hover:underline">Complete the record</a></p>
            <?php else: ?>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                <?php foreach ($items as [$lbl, $val]): ?>
                <div class="<?= $lbl === 'Internal Notes' ? 'sm:col-span-2' : '' ?>">
                    <dt class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide"><?= $lbl ?></dt>
                    <dd class="text-sm text-slate-800 mt-0.5 break-words"><?= htmlspecialchars($val) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($children): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 lg:col-span-2">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                <i class="fas fa-sitemap text-indigo-500 text-xs"></i> Group Members
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($children as $ch): ?>
                <a href="<?= url('modules/customers/view.php?id=' . $ch['id']) ?>" class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg hover:bg-indigo-50 transition">
                    <i class="fas fa-building text-slate-400 text-xs"></i>
                    <div class="min-w-0"><p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($ch['company_name']) ?></p>
                    <p class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($ch['client_code']) ?></p></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Tab: Contacts ===== -->
<div class="cust-panel hidden" data-panel="contacts">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Contact People</h3>
            <?php if (empty($contacts)): ?>
            <p class="text-sm text-slate-400 py-4">No contacts recorded.</p>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($contacts as $p): ?>
                <div class="py-3 flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-slate-500"><?= strtoupper(substr($p['name'], 0, 1)) ?></span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if ($p['is_primary']): ?><span class="ml-1.5 px-1.5 py-0.5 text-[10px] bg-indigo-100 text-indigo-700 rounded-full">Primary</span><?php endif; ?>
                            </p>
                            <?php if ($p['designation']): ?><p class="text-xs text-slate-500"><?= htmlspecialchars($p['designation']) ?></p><?php endif; ?>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <?php if ($p['email']): ?><a href="mailto:<?= htmlspecialchars($p['email']) ?>" class="hover:text-indigo-600"><?= htmlspecialchars($p['email']) ?></a><?php endif; ?>
                                <?php if ($p['phone']): ?> &middot; <?= htmlspecialchars($p['phone']) ?><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('modules/customers/delete_child.php') ?>" onsubmit="return confirm('Remove this contact?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="kind" value="contact"><input type="hidden" name="cid" value="<?= $p['id'] ?>">
                        <input type="hidden" name="client_id" value="<?= $id ?>">
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
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Add Contact</h3>
            <form method="post" action="<?= url('modules/customers/save_contact.php') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="client_id" value="<?= $id ?>">
                <input name="name" required placeholder="Full name" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="designation" placeholder="Designation" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="email" type="email" placeholder="Email" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="phone" placeholder="Phone" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300 text-indigo-600">Primary contact</label>
                <button class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Save Contact</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Tab: Addresses ===== -->
<div class="cust-panel hidden" data-panel="addresses">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Ship-to / Bill-to Locations</h3>
            <?php if (empty($addresses)): ?>
            <p class="text-sm text-slate-400 py-4">No additional locations. The billing address on the profile is used by default.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($addresses as $a): ?>
                <div class="border border-slate-100 rounded-lg p-4 relative">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide rounded-full <?= $a['address_type'] === 'shipping' ? 'bg-blue-100 text-blue-700' : ($a['address_type'] === 'billing' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600') ?>"><?= $a['address_type'] ?></span>
                        <?php if ($a['is_default']): ?><span class="text-[10px] text-indigo-600 font-semibold">DEFAULT</span><?php endif; ?>
                    </div>
                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($a['label']) ?></p>
                    <?php if ($a['contact_name']): ?><p class="text-xs text-slate-500"><?= htmlspecialchars($a['contact_name']) ?></p><?php endif; ?>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        <?= htmlspecialchars(implode(', ', array_filter([$a['address_line1'], $a['address_line2'], $a['city'], $a['state'], $a['postal_code'], $a['country']]))) ?>
                    </p>
                    <?php if ($a['phone'] || $a['email']): ?><p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars(trim($a['phone'] . ' · ' . $a['email'], ' ·')) ?></p><?php endif; ?>
                    <?php if ($a['tax_number']): ?><p class="text-[11px] text-slate-400">Tax: <?= htmlspecialchars($a['tax_number']) ?></p><?php endif; ?>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('modules/customers/delete_child.php') ?>" class="absolute top-3 right-3" onsubmit="return confirm('Remove this location?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="kind" value="address"><input type="hidden" name="cid" value="<?= $a['id'] ?>">
                        <input type="hidden" name="client_id" value="<?= $id ?>">
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
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Add Location</h3>
            <form method="post" action="<?= url('modules/customers/save_address.php') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="client_id" value="<?= $id ?>">
                <select name="address_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                    <option value="shipping">Ship-to</option><option value="billing">Bill-to</option>
                    <option value="remittance">Remittance</option><option value="other">Other</option>
                </select>
                <input name="label" required placeholder="Label (e.g. Warehouse North)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="contact_name" placeholder="Site contact" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="phone" placeholder="Phone" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="email" type="email" placeholder="Email" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="address_line1" placeholder="Address line 1" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <input name="address_line2" placeholder="Address line 2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <input name="city" placeholder="City" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <input name="state" placeholder="State" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <input name="postal_code" placeholder="Postal code" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <input name="country" placeholder="Country" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <input name="tax_number" placeholder="Site tax / GST number" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-indigo-600">Set as default</label>
                <button class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Save Location</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Tab: Financial ===== -->
<div class="cust-panel hidden" data-panel="financial">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Billing Trend — Last 12 Months</h3>
        <div id="custBillingTrend" style="min-height: 260px"></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Recent Invoices</h3>
            <?php if (empty($invoices)): ?><p class="text-sm text-slate-400">No invoices yet.</p><?php else: ?>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100">
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Invoice</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Date</th>
                    <th class="text-right text-[10px] font-semibold text-slate-400 uppercase pb-2">Total</th>
                    <th class="text-right text-[10px] font-semibold text-slate-400 uppercase pb-2">Balance</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2 pl-3">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td class="py-2 font-mono text-xs"><a href="<?= url('modules/finance/customer_invoices/view.php?id=' . $i['id']) ?>" class="text-indigo-600 hover:underline"><?= htmlspecialchars($i['invoice_number']) ?></a></td>
                        <td class="py-2 text-xs text-slate-500 whitespace-nowrap"><?= date('d M Y', strtotime($i['invoice_date'])) ?></td>
                        <td class="py-2 text-right text-xs"><?= number_format((float) $i['total_amount'], 2) ?></td>
                        <td class="py-2 text-right text-xs font-medium <?= (float) $i['balance_amount'] > 0 ? 'text-red-600' : 'text-slate-400' ?>"><?= number_format((float) $i['balance_amount'], 2) ?></td>
                        <td class="py-2 pl-3"><span class="px-2 py-0.5 text-[10px] rounded-full <?= $invBadges[$i['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($i['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Recent Receipts</h3>
            <?php if (empty($receipts)): ?><p class="text-sm text-slate-400">No receipts recorded.</p><?php else: ?>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100">
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Receipt</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Date</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase pb-2">Method</th>
                    <th class="text-right text-[10px] font-semibold text-slate-400 uppercase pb-2">Amount</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td class="py-2 font-mono text-xs text-slate-600"><?= htmlspecialchars($r['receipt_number']) ?></td>
                        <td class="py-2 text-xs text-slate-500"><?= date('d M Y', strtotime($r['receipt_date'])) ?></td>
                        <td class="py-2 text-xs text-slate-500"><?= htmlspecialchars(str_replace('_', ' ', $r['payment_method'])) ?></td>
                        <td class="py-2 text-right text-xs font-medium text-green-600"><?= number_format((float) $r['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== Tab: Pipeline & support ===== -->
<div class="cust-panel hidden" data-panel="pipeline">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Open Deals</h3>
            <?php if (empty($deals)): ?><p class="text-sm text-slate-400">No deals linked to this customer.</p><?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($deals as $d): ?>
                <a href="<?= url('modules/deals/view.php?id=' . $d['id']) ?>" class="py-2.5 flex items-center justify-between hover:bg-slate-50 -mx-2 px-2 rounded">
                    <div><p class="text-sm text-slate-800"><?= htmlspecialchars($d['deal_title']) ?></p>
                    <p class="text-[11px] text-slate-400 uppercase"><?= htmlspecialchars(str_replace('_', ' ', $d['stage'])) ?><?= $d['expected_closing_date'] ? ' · close ' . date('d M Y', strtotime($d['expected_closing_date'])) : '' ?></p></div>
                    <span class="text-sm font-medium"><?= number_format((float) $d['deal_value'], 2) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center justify-between">
                <span>Tickets</span>
                <span class="text-[11px] font-normal text-slate-400"><?= $openTickets ?> open</span>
            </h3>
            <?php if (empty($tickets)): ?><p class="text-sm text-slate-400">No support tickets.</p><?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($tickets as $t): ?>
                <a href="<?= url('modules/tickets/view.php?id=' . $t['id']) ?>" class="py-2.5 flex items-center justify-between hover:bg-slate-50 -mx-2 px-2 rounded">
                    <div class="min-w-0"><p class="text-sm text-slate-800 truncate"><?= htmlspecialchars($t['subject']) ?></p>
                    <p class="text-[11px] text-slate-400"><?= htmlspecialchars($t['priority']) ?> · <?= date('d M Y', strtotime($t['created_at'])) ?></p></div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 ml-2"><?= htmlspecialchars($t['status']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($people): ?>
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Linked People Records</h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($people as $p): ?>
                <a href="<?= url('modules/people_management/view.php?id=' . $p['id']) ?>" class="px-3 py-1.5 bg-slate-50 rounded-lg text-sm hover:bg-indigo-50 transition">
                    <i class="fas fa-user text-slate-400 text-xs mr-1.5"></i><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Tab: Documents ===== -->
<div class="cust-panel hidden" data-panel="documents">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Documents</h3>
        <?php if (empty($docs)): ?>
        <p class="text-sm text-slate-400">No documents uploaded. Contracts, tax certificates and KYC evidence belong here.</p>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($docs as $d): ?>
            <div class="py-2.5 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <i class="fas fa-file-alt text-slate-300"></i>
                    <div class="min-w-0"><p class="text-sm text-slate-800 truncate"><?= htmlspecialchars($d['document_name']) ?></p>
                    <p class="text-[11px] text-slate-400"><?= htmlspecialchars($d['document_type'] ?? 'document') ?> · <?= round($d['file_size'] / 1024) ?> KB · <?= htmlspecialchars($d['uploader'] ?? 'unknown') ?></p></div>
                </div>
                <span class="text-[11px] text-slate-400"><?= date('d M Y', strtotime($d['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Tab: Notes ===== -->
<div class="cust-panel hidden" data-panel="notes">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Interaction History</h3>
            <?php if (empty($notes)): ?><p class="text-sm text-slate-400">No notes yet.</p><?php else: ?>
            <div class="space-y-4">
                <?php foreach ($notes as $n): ?>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center flex-shrink-0">
                        <span class="text-[11px] font-bold text-indigo-600"><?= strtoupper(substr($n['author'] ?? 'U', 0, 1)) ?></span>
                    </div>
                    <div class="min-w-0 flex-1 bg-slate-50 rounded-lg p-3">
                        <p class="text-sm text-slate-700 whitespace-pre-line"><?= htmlspecialchars($n['note_text']) ?></p>
                        <p class="text-[11px] text-slate-400 mt-1.5"><?= htmlspecialchars($n['author'] ?? 'System') ?> · <?= date('d M Y, H:i', strtotime($n['created_at'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Add Note</h3>
            <form method="post" action="<?= url('modules/customers/save_note.php') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="client_id" value="<?= $id ?>">
                <textarea name="note_text" rows="6" required placeholder="Call summary, commitment, issue…" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm"></textarea>
                <button class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Save Note</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('.cust-tab'), panels = document.querySelectorAll('.cust-panel');
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.className = 'cust-tab px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-800'; });
            t.className = 'cust-tab px-4 py-2.5 text-sm font-medium border-b-2 border-indigo-600 text-indigo-700';
            panels.forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== t.dataset.tab); });
            if (t.dataset.tab === 'financial' && !window.__custTrend) window.__custTrend = renderTrend();
        });
    });
    function renderTrend() {
        var el = document.getElementById('custBillingTrend');
        if (!el || !window.renderCarbonChart) return;
        var labels = <?= json_encode(array_column($trend, 'label')) ?>;
        render(el, 'bar', {
            labels: labels,
            datasets: [
                { label: 'Billed',    data: <?= json_encode(array_map('floatval', array_column($trend, 'billed'))) ?>,    backgroundColor: '#6366f1' },
                { label: 'Collected', data: <?= json_encode(array_map('floatval', array_column($trend, 'collected'))) ?>, backgroundColor: '#22c55e' }
            ]
        }, { scales: { y: { beginAtZero: true } }, legend: { position: 'bottom' } });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
