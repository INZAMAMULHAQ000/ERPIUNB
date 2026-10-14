<?php
$pageTitle = 'Contractor Profile';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';

if (!hasPermission('contractors.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM contractors WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash('nf', 'Contractor not found.', 'error');
    to('modules/contractors/index.php');
}

$canEdit = hasPermission('contractors.edit');
$canManage = hasPermission('contractors.manage_contracts');

$categories = lookupOptions('contractor.category', []);
$typeLabels = lookupOptions('contractor.contract_type', []);
$rateBases  = contractorRateBases();
$arrangements = lookupOptions('contractor.work_arrangement', []);
$billing    = lookupOptions('contractor.billing_frequency', []);
$endReasons = lookupOptions('contractor.end_reason', []);
$paymentTerms = lookupOptions('trade.payment_terms', []);

$slotDocs = contractorSlotDocuments($db, $id);
$attachments = $db->prepare("
    SELECT d.*, u.name AS uploader_name
      FROM contractor_documents d
      LEFT JOIN users u ON u.id = d.uploaded_by
     WHERE d.contractor_id = ? ORDER BY d.created_at DESC
");
$attachments->execute([$id]);
$docs = $attachments->fetchAll();
$attachmentRows = array_values(array_filter($docs, fn($d) => !isset(contractorDocSlots()[$d['document_type']])));

$contracts = $db->prepare("
    SELECT cc.*, v.vendor_name AS agency_name, dep.department_name, des.designation_name,
           em.first_name AS manager_first, em.last_name AS manager_last,
           pj.project_name, po.po_number, ap.name AS approved_by_name,
           com.company_code, com.company_name
      FROM contractor_contracts cc
      LEFT JOIN vendors v          ON v.id = cc.agency_vendor_id
      LEFT JOIN departments dep    ON dep.id = cc.department_id
      LEFT JOIN designations des   ON des.id = cc.designation_id
      LEFT JOIN employees em       ON em.id = cc.manager_employee_id
      LEFT JOIN projects pj        ON pj.id = cc.project_id
      LEFT JOIN purchase_orders po ON po.id = cc.purchase_order_id
      LEFT JOIN users ap           ON ap.id = cc.approved_by
      LEFT JOIN companies com      ON com.id = cc.company_id
     WHERE cc.contractor_id = ? AND cc.deleted_at IS NULL
     ORDER BY cc.is_current DESC, cc.start_date DESC, cc.id DESC
");
$contracts->execute([$id]);
$contractRows = $contracts->fetchAll();

// A profile can focus any contract (deep link), defaulting to the current one.
$focusId = (int) ($_GET['contract'] ?? 0);
$focus = null;
foreach ($contractRows as $row) {
    if ($focusId && (int) $row['id'] === $focusId) { $focus = $row; break; }
}
if (!$focus && $contractRows) { $focus = $contractRows[0]; }

$rates = $focus ? contractorRateSchedule($db, (int) $focus['id']) : [];
$contractDocs = $focus ? contractorContractDocuments($db, (int) $focus['id']) : [];
$contractDocTypes = contractorContractDocTypes();

$totals = [
    'contracts'  => count($contractRows),
    'extensions' => 0,
    'value'      => 0.0,
];
foreach ($contractRows as $row) {
    $totals['extensions'] += (int) $row['extension_count'];
    $totals['value']      += (float) ($row['estimated_value'] ?? 0);
}

$statusColors = ['active' => 'bg-emerald-100 text-emerald-700', 'suspended' => 'bg-amber-100 text-amber-700', 'terminated' => 'bg-red-100 text-red-700'];
$name = trim($c['first_name'] . ' ' . $c['last_name']);
$photoPath = $c['photo_path'] ?? '';

/** Compact definition list row. */
$dl = function ($label, $value, $class = 'text-slate-800') {
    echo '<div><p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">' . $label . '</p>'
        . '<p class="text-sm ' . $class . ' mt-0.5">' . ($value === '' || $value === null ? '<span class="text-slate-300">—</span>' : $value) . '</p></div>';
};
$esc = fn($v) => htmlspecialchars((string) $v);
$money = function ($amount, $code = '') {
    if ($amount === null || $amount === '') return '';
    return ($code ? $code . ' ' : '') . number_format((float) $amount, 2);
};
$actionForm = function ($action, $contractId, $fields = '', $confirm = '', $buttonClass = '', $label = '') use ($esc) {
    ?>
    <form method="post" action="<?= url('modules/contractors/contract_action.php') ?>" class="inline-flex items-center gap-2"
          <?= $confirm ? 'onsubmit="return confirm(\'' . $confirm . '\')"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="<?= $action ?>">
        <input type="hidden" name="contract_id" value="<?= (int) $contractId ?>">
        <?= $fields ?>
        <button class="text-xs px-3 py-1.5 rounded-lg border transition <?= $buttonClass ?>"><?= $label ?></button>
    </form>
    <?php
};
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-center gap-4 min-w-0">
        <?php if ($photoPath): ?>
        <button type="button" onclick="attachmentViewerOpen(this)"
                data-url="<?= attachmentFileUrl($photoPath) ?>" data-mime="<?= attachmentMimeFromPath($photoPath) ?>"
                data-name="Profile Photo — <?= $esc($name) ?>" title="View photo">
            <img src="<?= attachmentFileUrl($photoPath) ?>" alt="<?= $esc($name) ?>"
                 class="h-16 w-16 rounded-full object-cover border-2 border-white shadow">
        </button>
        <?php else: ?>
        <div class="h-16 w-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
            <?= strtoupper(substr($c['first_name'], 0, 1)) ?>
        </div>
        <?php endif; ?>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-800 leading-tight"><?= $esc($name) ?></h1>
            <p class="text-sm text-slate-500 mt-0.5">
                <span class="font-mono"><?= $esc($c['contractor_code']) ?></span>
                &middot; <?= $esc($categories[$c['contractor_category']] ?? 'Uncategorised') ?>
                &middot; <span class="text-[11px] px-2 py-0.5 rounded-full <?= $statusColors[$c['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($c['status']) ?></span>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('modules/contractors/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-800 mr-1">
            <i class="fas fa-arrow-left mr-1.5"></i>Register
        </a>
        <?php if ($canEdit): ?>
        <a href="<?= url('modules/contractors/contract_form.php?contractor_id=' . $id) ?>"
           class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
            <i class="fas fa-file-circle-plus mr-2"></i>New Contract
        </a>
        <a href="<?= url('modules/contractors/documents.php?id=' . $id) ?>"
           class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
            <i class="fas fa-paperclip mr-2"></i>Attachments
        </a>
        <a href="<?= url('modules/contractors/form.php?id=' . $id) ?>"
           class="px-4 py-2 text-sm font-medium bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition">
            <i class="fas fa-pen-to-square mr-2"></i>Edit
        </a>
        <?php endif; ?>
        <?php if (hasPermission('contractors.delete')): ?>
        <a href="<?= url('modules/contractors/delete.php?id=' . $id) ?>"
           class="px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition">
            <i class="fas fa-trash"></i>
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                <i class="fas fa-user text-indigo-500"></i> Identity
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $dl('Gender', $esc(ucfirst($c['gender'] ?? '')));
                $dl('Date of Birth', $c['date_of_birth'] ? date('M d, Y', strtotime($c['date_of_birth'])) : '');
                $dl('Nationality', $esc($c['nationality']));
                $dl('Specialisation', $esc($c['specialisation']));
                $dl('Email', $c['email'] ? '<a class="text-indigo-600 hover:underline" href="mailto:' . $esc($c['email']) . '">' . $esc($c['email']) . '</a>' : '');
                $dl('Phone', $esc($c['phone']));
                $dl('Work Location', $esc($c['work_location']));
                $dl('Bank / IBAN', $c['bank_account_number'] ? $esc($c['bank_account_number']) . '<span class="block text-[11px] text-slate-400">' . $esc($c['bank_name']) . '</span>' : '');
                ?>
                <div class="col-span-2 md:col-span-3">
                    <?php $dl('Address', nl2br($esc($c['address']))); ?>
                </div>
                <?php $dl('VAT TRN', $esc($c['trn_number'])); ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5" id="contracts">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                    <i class="fas fa-file-signature text-indigo-500"></i>
                    <?= $focus ? 'Contract ' . $esc($focus['contract_number']) : 'Contracts' ?>
                </h2>
                <span class="text-[11px] text-slate-400"><?= $totals['contracts'] ?> on record &middot; <?= $totals['extensions'] ?> extension(s)</span>
            </div>

            <?php if (!$contractRows): ?>
            <p class="text-sm text-slate-500 py-6 text-center">
                No contract yet — this contractor is registered but not engaged.
                <?php if ($canEdit): ?>
                <a href="<?= url('modules/contractors/contract_form.php?contractor_id=' . $id) ?>" class="block mt-2 text-indigo-600 hover:underline">
                    <i class="fas fa-file-circle-plus mr-1"></i>Create the first contract
                </a>
                <?php endif; ?>
            </p>
            <?php else: ?>

            <?php if ($focus): ?>
            <?php $focusState = contractorContractState($focus); $editable = contractorContractEditable($focus); ?>
            <div class="mb-5">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="text-[11px] px-2.5 py-1 rounded-full font-medium <?= $focusState['class'] ?>"><?= $focusState['label'] ?></span>
                    <span class="text-sm font-semibold text-slate-800"><?= $esc($focus['title']) ?></span>
                    <?php if ((int) $focus['is_current'] === 1): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 uppercase tracking-wide">Current</span>
                    <?php endif; ?>
                    <?php if (!empty($focus['approved_at'])): ?>
                    <span class="text-[11px] text-slate-500"><i class="fas fa-check-double text-emerald-500 mr-1"></i>Approved <?= date('M d, Y', strtotime($focus['approved_at'])) ?><?= $focus['approved_by_name'] ? ' by ' . $esc($focus['approved_by_name']) : '' ?></span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <?php
                    $dl('Type', $esc($typeLabels[$focus['contract_type']] ?? $focus['contract_type']));
                    $dl('Engaged By', $focus['supply_source'] === 'agency' ? $esc($focus['agency_name'] ?: 'Agency') : 'Direct');
                    $dl('Company', $esc($focus['company_name'] ? ($focus['company_code'] . ' — ' . $focus['company_name']) : ''));
                    $dl('Department', $esc($focus['department_name']));
                    $dl('Contractual Job Title', $esc($focus['contractual_job_title']));
                    $dl('Reporting Manager', $focus['manager_first'] ? $esc($focus['manager_first'] . ' ' . $focus['manager_last']) : '');
                    $dl('Project', $esc($focus['project_name']));
                    $dl('Purchase Order', $focus['po_number'] ? $esc($focus['po_number']) : $esc($focus['po_reference']));
                    $dl('Start', $focus['start_date'] ? date('M d, Y', strtotime($focus['start_date'])) : '');
                    $dl('End', $focus['end_date'] ? date('M d, Y', strtotime($focus['end_date'])) : 'Open-ended');
                    $dl('Work Arrangement', $esc($arrangements[$focus['work_arrangement']] ?? $focus['work_arrangement']));
                    $dl('Allocation', $focus['fte_allocation'] !== null ? number_format((float) $focus['fte_allocation'], 1) . '%' : '');
                    $dl('Rate', $focus['rate_amount'] !== null ? $esc($money($focus['rate_amount'], $focus['currency_code'])) . '<span class="text-[11px] text-slate-400">/' . $esc($rateBases[$focus['rate_basis']] ?? $focus['rate_basis']) . '</span>' : '');
                    $dl('Monthly Equivalent', $focus['rate_amount'] !== null ? $esc($money(contractorMonthlyEquivalent($focus['rate_amount'], $focus['rate_basis']), $focus['currency_code'])) : '');
                    $dl('Estimated Value', $esc($money($focus['estimated_value'], $focus['currency_code'])));
                    $dl('Not To Exceed', $esc($money($focus['not_to_exceed_amount'], $focus['currency_code'])));
                    $dl('Billing', $esc($billing[$focus['billing_frequency']] ?? $focus['billing_frequency']));
                    $dl('Payment Terms', $esc($paymentTerms[$focus['payment_terms']] ?? $focus['payment_terms']));
                    $dl('Timesheets', (int) $focus['timesheet_required'] === 1 ? 'Required' : 'Not required');
                    $dl('Clearance / Badge', trim(($focus['security_clearance'] ?? '') . ' / ' . ($focus['badge_number'] ?? ''), ' /'));
                    ?>
                    <div>
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Duration</p>
                        <p class="text-sm text-slate-800 mt-0.5">
                            <?php
                            if ($focus['start_date'] && $focus['end_date']) {
                                $days = intdiv(strtotime($focus['end_date']) - strtotime($focus['start_date']), 86400);
                                echo $days . ' days (' . round($days / 30.44, 1) . ' months)';
                            } elseif ($focus['start_date']) {
                                echo 'Ongoing since ' . date('M d, Y', strtotime($focus['start_date']));
                            }
                            ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Extensions</p>
                        <p class="text-sm text-slate-800 mt-0.5">
                            <?= (int) $focus['extension_count'] ?><?= $focus['extension_limit'] !== null ? ' of ' . (int) $focus['extension_limit'] : '' ?>
                            <?php if ($focus['extension_period_days']): ?>
                            <span class="block text-[11px] text-slate-400"><?= (int) $focus['extension_period_days'] ?> days each</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ($focus['scope_of_work']): ?>
                <div class="mb-4 p-3 bg-slate-50 rounded-lg">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Scope of Work</p>
                    <p class="text-sm text-slate-700 whitespace-pre-line"><?= $esc($focus['scope_of_work']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($focus['end_reason'] || $focus['notes']): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 text-sm">
                    <?php if ($focus['end_reason']): ?>
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Ended</p>
                        <p class="text-slate-700"><?= $focus['ended_on'] ? date('M d, Y', strtotime($focus['ended_on'])) . ' — ' : '' ?><?= $esc($focus['end_reason']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($focus['notes']): ?>
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Notes</p>
                        <p class="text-slate-700 whitespace-pre-line"><?= $esc($focus['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($canEdit || $contractDocs): ?>
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Contract documents</h3>
                <?php if ($contractDocs): ?>
                <ul class="divide-y divide-slate-100 border border-slate-100 rounded-lg mb-3">
                    <?php foreach ($contractDocs as $cdoc):
                        $note = contractorExpiryNote($cdoc['expires_on'] ?? ''); ?>
                    <li class="px-3 py-2 flex items-center justify-between gap-3 hover:bg-slate-50">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="fas <?= strpos($cdoc['mime_type'] ?? '', 'pdf') !== false ? 'fa-file-pdf text-red-400' : 'fa-file-image text-indigo-400' ?>"></i>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800 truncate max-w-[280px]"><?= $esc($cdoc['document_name']) ?></p>
                                <p class="text-[11px] text-slate-400">
                                    <?= $esc($contractDocTypes[$cdoc['document_type']] ?? $cdoc['document_type']) ?>
                                    &middot; <?= attachmentSizeLabel($cdoc['file_size']) ?>
                                    &middot; <?= date('M d, Y', strtotime($cdoc['created_at'])) ?>
                                    <?php if ($cdoc['uploader_name']): ?>&middot; <?= $esc($cdoc['uploader_name']) ?><?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <?php if (!empty($cdoc['expires_on'])): ?>
                            <span class="text-[10px] px-2 py-0.5 rounded-full <?= contractorExpiryNote($cdoc['expires_on'])['class'] ?>"><?= $note['label'] ?></span>
                            <?php endif; ?>
                            <?= attachmentViewButton('', $cdoc['file_path'], $cdoc['mime_type'] ?? '', $cdoc['document_name'], 'text-slate-500 hover:text-indigo-600') ?>
                            <a href="<?= attachmentFileUrl($cdoc['file_path']) ?>" class="text-slate-500 hover:text-indigo-600" title="Download"><i class="fas fa-download"></i></a>
                            <?php if ($canEdit): ?>
                            <form method="post" action="<?= url('modules/contractors/contract_action.php') ?>" onsubmit="return confirm('Delete this contract document?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="delete_contract_doc">
                                <input type="hidden" name="contract_id" value="<?= (int) $focus['id'] ?>">
                                <input type="hidden" name="document_id" value="<?= (int) $cdoc['id'] ?>">
                                <button class="text-slate-400 hover:text-red-600" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-xs text-slate-400 mb-3">No signed contract, amendment, renewal or termination letter attached yet.</p>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                <form method="post" action="<?= url('modules/contractors/contract_action.php') ?>" enctype="multipart/form-data"
                      class="grid grid-cols-2 md:grid-cols-5 gap-2 items-end p-3 bg-slate-50 rounded-lg mb-4">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add_contract_doc">
                    <input type="hidden" name="contract_id" value="<?= (int) $focus['id'] ?>">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Document</label>
                        <select name="document_type" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                            <?php foreach ($contractDocTypes as $tk => $tl): ?>
                            <option value="<?= $tk ?>"><?= $esc($tl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Label</label>
                        <input type="text" name="document_name" placeholder="defaults to file name"
                               class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Valid until</label>
                        <input type="date" name="expires_on" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">File</label>
                        <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx"
                               class="block w-full text-xs text-slate-600 file:mr-2 file:px-2 file:py-1 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700">
                    </div>
                    <button class="px-3 py-1.5 bg-slate-800 text-white text-xs rounded-lg hover:bg-slate-900 col-span-2 md:col-span-1">
                        <i class="fas fa-upload mr-1"></i>Attach
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($canManage || $canEdit): ?>
                <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100">
                    <?php if ($canManage && $focus['status'] === 'draft' && empty($focus['approved_at'])): ?>
                        <?php $actionForm('approve', $focus['id'], '', '', 'border-slate-200 text-slate-700 hover:bg-slate-50', 'Approve'); ?>
                    <?php endif; ?>
                    <?php if ($canManage && $focus['status'] === 'draft'): ?>
                        <?php $actionForm('activate', $focus['id'], '', 'Activate this contract and make it current?', 'border-emerald-200 text-emerald-700 hover:bg-emerald-50', 'Activate'); ?>
                    <?php endif; ?>
                    <?php if ($canManage && $focus['status'] === 'active'): ?>
                        <?php $actionForm('extend', $focus['id'],
                            '<input type="number" name="extension_days" min="1" max="3650" placeholder="days" class="w-20 px-2 py-1 border border-slate-200 rounded-lg text-xs" />'
                            . '<input type="text" name="reason" placeholder="reason" class="w-32 px-2 py-1 border border-slate-200 rounded-lg text-xs" />',
                            '', 'border-amber-200 text-amber-700 hover:bg-amber-50', 'Extend'); ?>
                        <?php $actionForm('complete', $focus['id'], '', 'Mark this contract completed?', 'border-slate-200 text-slate-700 hover:bg-slate-50', 'Complete'); ?>
                        <?php
                        $reasonSelect = '<select name="end_reason" class="px-2 py-1 border border-slate-200 rounded-lg text-xs">';
                        foreach ($endReasons as $k => $l) { $reasonSelect .= '<option value="' . $esc($k) . '">' . $esc($l) . '</option>'; }
                        $reasonSelect .= '</select>';
                        $actionForm('terminate', $focus['id'], $reasonSelect, 'Terminate this contract?', 'border-red-200 text-red-700 hover:bg-red-50', 'Terminate'); ?>
                    <?php endif; ?>
                    <?php if ($canManage && (int) $focus['is_current'] !== 1 && in_array($focus['status'], ['draft', 'active'], true)): ?>
                        <?php $actionForm('set_current', $focus['id'], '', '', 'border-indigo-200 text-indigo-700 hover:bg-indigo-50', 'Make current'); ?>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <a href="<?= url('modules/contractors/contract_form.php?contractor_id=' . $id . '&contract_id=' . (int) $focus['id']) ?>"
                       class="text-xs px-3 py-1.5 rounded-lg border <?= $editable ? 'border-slate-200 text-slate-700 hover:bg-slate-50' : 'border-slate-100 text-slate-300 pointer-events-none' ?>">
                        Edit
                    </a>
                    <?php endif; ?>
                    <?php if ($canManage && $editable): ?>
                        <?php $actionForm('delete_contract', $focus['id'], '', 'Delete this draft contract?', 'border-red-200 text-red-700 hover:bg-red-50', 'Delete'); ?>
                    <?php endif; ?>
                    <?php if (!$editable && $canManage): ?>
                    <span class="text-[11px] text-slate-400 ml-1"><i class="fas fa-lock mr-1"></i>Approved contracts are read-only</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mt-6 mb-3">All contracts</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <?php foreach (['Number', 'Title', 'Period', 'Source', 'Rate', 'State', 'Extensions', ''] as $h): ?>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-slate-500 uppercase tracking-wide"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($contractRows as $row):
                        $st = contractorContractState($row); ?>
                        <tr class="<?= (int) $focus['id'] === (int) $row['id'] ? 'bg-indigo-50/40' : '' ?>">
                            <td class="px-3 py-2 font-mono text-xs text-slate-500 whitespace-nowrap"><?= $esc($row['contract_number']) ?></td>
                            <td class="px-3 py-2 max-w-[220px]">
                                <a href="<?= url('modules/contractors/view.php?id=' . $id . '&contract=' . (int) $row['id']) ?>"
                                   class="text-slate-800 hover:text-indigo-700 font-medium block truncate"><?= $esc($row['title']) ?></a>
                                <span class="text-[11px] text-slate-400"><?= $esc($typeLabels[$row['contract_type']] ?? '') ?></span>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                                <?= $row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : '' ?>
                                → <?= $row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : 'open' ?>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600">
                                <?= $row['supply_source'] === 'agency' ? 'Agency' : 'Direct' ?>
                                <?php if ($row['agency_name']): ?><span class="block text-[11px] text-slate-400 truncate max-w-[140px]"><?= $esc($row['agency_name']) ?></span><?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                                <?= $row['rate_amount'] !== null ? $esc($money($row['rate_amount'], $row['currency_code'])) . '<span class="text-slate-400">/' . $esc($row['rate_basis']) . '</span>' : '—' ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="text-[11px] px-2 py-0.5 rounded-full <?= $st['class'] ?>"><?= $st['label'] ?></span>
                                <?php if ((int) $row['is_current'] === 1): ?><span class="text-[10px] text-indigo-600 ml-1">current</span><?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600"><?= (int) $row['extension_count'] ?><?= $row['extension_limit'] !== null ? '/' . (int) $row['extension_limit'] : '' ?></td>
                            <td class="px-3 py-2 text-right">
                                <a href="<?= url('modules/contractors/view.php?id=' . $id . '&contract=' . (int) $row['id']) ?>" class="text-slate-400 hover:text-indigo-600"><i class="fas fa-angles-right"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($focus): ?>
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mt-6 mb-3">Rate schedule</h3>
            <?php if ($rates): ?>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <?php foreach (['Valid From', 'Valid To', 'Basis', 'Rate', 'Reason', ''] as $h): ?>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-slate-500 uppercase tracking-wide"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($rates as $rate): ?>
                    <tr>
                        <td class="px-3 py-2 text-xs text-slate-700"><?= date('M d, Y', strtotime($rate['valid_from'])) ?></td>
                        <td class="px-3 py-2 text-xs text-slate-600"><?= $rate['valid_to'] ? date('M d, Y', strtotime($rate['valid_to'])) : '—' ?></td>
                        <td class="px-3 py-2 text-xs text-slate-600"><?= $esc($rateBases[$rate['rate_basis']] ?? $rate['rate_basis']) ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-slate-800"><?= $esc($money($rate['rate_amount'], $rate['currency_code'])) ?></td>
                        <td class="px-3 py-2 text-xs text-slate-500"><?= $esc($rate['reason']) ?></td>
                        <td class="px-3 py-2 text-right">
                            <?php if ($canEdit): ?>
                            <form method="post" action="<?= url('modules/contractors/contract_action.php') ?>" class="inline"
                                  onsubmit="return confirm('Delete this rate entry?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="delete_rate">
                                <input type="hidden" name="rate_id" value="<?= (int) $rate['id'] ?>">
                                <button class="text-slate-400 hover:text-red-600" title="Delete rate"><i class="fas fa-xmark"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-xs text-slate-400 mb-3">No dated rate entries — the contract rate above applies.</p>
            <?php endif; ?>

            <?php if ($canEdit): ?>
            <form method="post" action="<?= url('modules/contractors/contract_action.php') ?>" class="grid grid-cols-2 md:grid-cols-6 gap-2 items-end mt-3 p-3 bg-slate-50 rounded-lg">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add_rate">
                <input type="hidden" name="contract_id" value="<?= (int) $focus['id'] ?>">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Valid from</label>
                    <input type="date" name="valid_from" required class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Valid to</label>
                    <input type="date" name="valid_to" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Basis</label>
                    <select name="rate_basis" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs bg-white">
                        <?php foreach ($rateBases as $k => $l): ?><option value="<?= $esc($k) ?>"><?= $esc($l) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Rate</label>
                    <input type="number" name="rate_amount" step="0.01" min="0" required class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Reason</label>
                    <input type="text" name="reason" placeholder="annual uplift" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                </div>
                <button class="px-3 py-1.5 bg-slate-800 text-white text-xs rounded-lg hover:bg-slate-900">
                    <i class="fas fa-plus mr-1"></i>Add rate
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>

            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                    <i class="fas fa-file-shield text-indigo-500"></i> Compliance Scans
                </h2>
                <?php if ($canEdit): ?>
                <a href="<?= url('modules/contractors/form.php?id=' . $id) ?>" class="text-xs text-indigo-600 hover:text-indigo-800">
                    <i class="fas fa-upload mr-1"></i>Upload / Replace
                </a>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach (contractorDocSlots() as $slot => $label): ?>
                <?php
                $doc = $slotDocs[$slot] ?? null;
                $expiryColumn = contractorSlotExpiryColumn($slot);
                $note = contractorExpiryNote($expiryColumn ? ($c[$expiryColumn] ?? '') : '');
                ?>
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <p class="text-xs font-semibold text-slate-600"><?= $label ?></p>
                        <span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap <?= $note['class'] ?>"><?= $note['label'] ?></span>
                    </div>
                    <?php if ($doc): ?>
                        <?php if (strpos($doc['mime_type'] ?? '', 'image/') === 0): ?>
                        <img src="<?= attachmentFileUrl($doc['file_path']) ?>" alt="<?= $esc($label) ?>"
                             class="w-full h-28 object-cover rounded border border-slate-200 mb-2">
                        <?php else: ?>
                        <div class="w-full h-28 rounded border border-slate-200 bg-slate-50 flex items-center justify-center text-slate-400 mb-2">
                            <i class="fas <?= strpos($doc['mime_type'] ?? '', 'pdf') !== false ? 'fa-file-pdf text-red-400 text-3xl' : 'fa-file-word text-blue-500 text-3xl' ?>"></i>
                        </div>
                        <?php endif; ?>
                        <?= attachmentViewButton('View', $doc['file_path'], $doc['mime_type'] ?? '', $label . ' — ' . $doc['document_name']) ?>
                        <p class="text-[11px] text-slate-400 mt-2">
                            <?= $esc($doc['document_name']) ?> &middot; <?= attachmentSizeLabel($doc['file_size']) ?>
                            &middot; <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                        </p>
                    <?php else: ?>
                        <div class="w-full h-28 rounded border border-dashed border-slate-200 flex items-center justify-center text-slate-300 mb-2">
                            <i class="fas fa-file-upload text-2xl"></i>
                        </div>
                        <p class="text-[11px] text-slate-400">Not on record</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($attachmentRows): ?>
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mt-6 mb-3">Attachments</h3>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($attachmentRows as $doc): $note = contractorExpiryNote($doc['expires_on'] ?? ''); ?>
                <li class="py-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fas <?= strpos($doc['mime_type'] ?? '', 'pdf') !== false ? 'fa-file-pdf text-red-400' : (strpos($doc['mime_type'] ?? '', 'image/') === 0 ? 'fa-file-image text-indigo-400' : 'fa-file-word text-blue-500') ?>"></i>
                        <div class="min-w-0">
                            <p class="text-sm text-slate-700 truncate"><?= $esc($doc['document_name']) ?></p>
                            <p class="text-[11px] text-slate-400">
                                <?= $esc(contractorAttachmentTypes()[$doc['document_type']] ?? $doc['document_type']) ?>
                                &middot; <?= attachmentSizeLabel($doc['file_size']) ?>
                                &middot; <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                                <?php if (!empty($doc['uploader_name'])): ?> &middot; <?= $esc($doc['uploader_name']) ?><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <?php if (!empty($doc['expires_on'])): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded-full <?= $note['class'] ?>"><?= $note['label'] ?></span>
                        <?php endif; ?>
                        <?= attachmentViewButton('', $doc['file_path'], $doc['mime_type'] ?? '', $doc['document_name'], 'text-slate-500 hover:text-indigo-600') ?>
                        <a href="<?= attachmentFileUrl($doc['file_path']) ?>" class="text-slate-500 hover:text-indigo-600" title="Download"><i class="fas fa-download"></i></a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-4">Engagement Summary</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Contracts</span><span class="font-semibold text-slate-800"><?= $totals['contracts'] ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Extensions used</span><span class="font-semibold text-slate-800"><?= $totals['extensions'] ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Lifetime estimated</span><span class="font-semibold text-slate-800"><?= $esc($money($totals['value'], $focus['currency_code'] ?? '')) ?></span></div>
                <?php if ($focus && $focus['rate_amount'] !== null): ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Monthly equivalent</span>
                    <span class="font-semibold text-indigo-600"><?= $esc($money(contractorMonthlyEquivalent($focus['rate_amount'], $focus['rate_basis']), $focus['currency_code'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($focus && $focus['not_to_exceed_amount']): ?>
                <div class="flex justify-between"><span class="text-slate-500">Not to exceed</span><span class="font-semibold text-slate-800"><?= $esc($money($focus['not_to_exceed_amount'], $focus['currency_code'])) ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Supply</span>
                    <span class="font-semibold text-slate-800"><?= ($focus['supply_source'] ?? '') === 'agency' ? 'Agency' : 'Direct' ?></span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-4">Right to Work</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                <?php
                $docRows = [
                    'Emirates ID'  => ['emirates_id_number', 'emirates_id_expiry'],
                    'Passport'     => ['passport_number', 'passport_expiry'],
                    'Visa'         => ['visa_number', 'visa_expiry'],
                    'Work Permit'  => ['work_permit_number', 'work_permit_expiry'],
                    'Labour Card'  => ['labor_card_number', 'labor_card_expiry'],
                ];
                foreach ($docRows as $label => [$numCol, $expCol]):
                    $number = $c[$numCol] ?? '';
                    if ($label === 'Emirates ID' && $number) { $number = contractorFormatEmiratesId($number); }
                    $note = contractorExpiryNote($c[$expCol] ?? '');
                    ?>
                    <tr>
                        <td class="py-2 pr-2 text-xs text-slate-500 whitespace-nowrap"><?= $label ?></td>
                        <td class="py-2 pr-2 font-mono text-xs text-slate-800"><?= $number ? $esc($number) : '<span class="text-slate-300">—</span>' ?></td>
                        <td class="py-2 text-right"><span class="text-[10px] px-2 py-0.5 rounded-full <?= $note['class'] ?>"><?= $note['label'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($c['visa_type']): ?>
            <p class="text-[11px] text-slate-400 mt-3">Visa type: <?= $esc(contractorVisaTypes()[$c['visa_type']] ?? $c['visa_type']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($c['termination_date']) || !empty($c['termination_reason'])): ?>
        <div class="bg-white rounded-xl shadow-sm border border-red-100 p-5">
            <h3 class="text-xs font-bold text-red-700 uppercase tracking-wide mb-3">Termination</h3>
            <p class="text-sm text-slate-700">
                <?= $c['termination_date'] ? date('M d, Y', strtotime($c['termination_date'])) : '' ?>
                <?= $c['termination_reason'] ? ' — ' . $esc($c['termination_reason']) : '' ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($c['notes'])): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-3">Notes</h3>
            <p class="text-sm text-slate-700 whitespace-pre-line"><?= $esc($c['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../templates/document_viewer.php'; ?>
<?php require_once '../../includes/footer.php'; ?>
