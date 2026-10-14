<?php
$contractorId = (int) ($_GET['contractor_id'] ?? $_POST['contractor_id'] ?? 0);
$contractId   = (int) ($_GET['contract_id'] ?? $_POST['contract_id'] ?? 0);
$isEdit = $contractId > 0;
$pageTitle = $isEdit ? 'Edit Contract' : 'New Contractor Contract';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';
require_once __DIR__ . '/form_helpers.php';
require_once __DIR__ . '/engagement_fields.php';

if (!hasPermission('contractors.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$contractor = $db->prepare("SELECT * FROM contractors WHERE id = ? AND deleted_at IS NULL");
$contractor->execute([$contractorId]);
$person = $contractor->fetch();
if (!$person) {
    flash('nf', 'Contractor not found.', 'error');
    to('modules/contractors/index.php');
}

$existing = null;
if ($isEdit) {
    $existing = contractorLoadContract($db, $contractId);
    if (!$existing || (int) $existing['contractor_id'] !== $contractorId) {
        flash('nf', 'Contract not found for this contractor.', 'error');
        to('modules/contractors/view.php?id=' . $contractorId);
    }
    if (!contractorContractEditable($existing)) {
        flash('locked', 'This contract has been approved or activated, so its terms are read-only. Use Extend, Complete or Terminate instead.', 'warning');
        to('modules/contractors/view.php?id=' . $contractorId . '&contract=' . $contractId);
    }
}

/* ------------------------------------------------------------------ */
/* Reference data                                                      */
/* ------------------------------------------------------------------ */
$opts = contractorEngagementOptions($db);
$typeLabels = $opts['type_labels'];
$arrangements = $opts['arrangements'];
$billing = $opts['billing'];
$rateBases = $opts['rate_bases'];
$paymentTerms = $opts['payment_terms'];

$activeCompany = null;
if (function_exists('activeCompany')) { $activeCompany = activeCompany(); }

/* ------------------------------------------------------------------ */
/* Next contract number preview — never consumed by a page view        */
/* ------------------------------------------------------------------ */
$nextNumber = '';
$numberError = '';
if (!$isEdit) {
    try {
        $nextNumber = peekDocumentNumber($db, 'contractor_contract');
    } catch (RuntimeException $e) {
        $numberError = $e->getMessage();
    }
}

$record = [
    'title' => '', 'contract_number' => $nextNumber, 'contract_type' => '', 'scope_of_work' => '',
    'supply_source' => 'direct', 'agency_vendor_id' => '', 'department_id' => '', 'designation_id' => '',
    'contractual_job_title' => '', 'manager_employee_id' => '', 'project_id' => '', 'purchase_order_id' => '',
    'po_reference' => '', 'start_date' => date('Y-m-d'), 'end_date' => '', 'work_arrangement' => '',
    'fte_allocation' => '', 'rate_basis' => '', 'rate_amount' => '',
    'currency_code' => function_exists('getActiveCurrency') ? getActiveCurrency() : 'AED',
    'estimated_value' => '', 'not_to_exceed_amount' => '', 'billing_frequency' => 'monthly',
    'payment_terms' => '', 'timesheet_required' => '', 'security_clearance' => '', 'badge_number' => '',
    'extension_limit' => '', 'extension_period_days' => '30', 'notes' => '',
];
if ($existing) {
    $record = array_merge($record, $existing);
    $record['timesheet_required'] = (int) $existing['timesheet_required'] === 1 ? '1' : '';
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('csrf', 'Your session expired. Please submit the form again.', 'error');
        to('modules/contractors/contract_form.php?contractor_id=' . $contractorId . ($isEdit ? '&contract_id=' . $contractId : ''));
    }

    $existing = $isEdit ? contractorLoadContract($db, $contractId) : null;
    if ($isEdit && (!contractorLoadContract($db, $contractId) || !contractorContractEditable($existing))) {
        flash('locked', 'This contract is approved or activated and can no longer be edited.', 'error');
        to('modules/contractors/view.php?id=' . $contractorId . '&contract=' . $contractId);
    }

    $data = contractorContractInput($_POST);
    $errors = contractorContractErrors($data, $_POST, $db);

    if ($data['contract_type'] !== '' && !array_key_exists($data['contract_type'], $typeLabels)) {
        $errors['contract_type'] = 'Choose a contract type from the list';
    }
    if ($data['rate_basis'] !== '' && !array_key_exists($data['rate_basis'], $rateBases)) {
        $errors['rate_basis'] = 'Choose a rate basis from the list';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/contractors/contract_form.php?contractor_id=' . $contractorId . ($isEdit ? '&contract_id=' . $contractId : ''));
    }

    $result = contractorSaveContract($db, $data, $contractorId, $isEdit ? $contractId : 0, $_SESSION['user_id']);
    if ($result['error'] !== null) {
        flash('error', $result['error'], 'error');
        to('modules/contractors/contract_form.php?contractor_id=' . $contractorId . ($isEdit ? '&contract_id=' . $contractId : ''));
    }
    $savedId = $result['id'];
    logAudit($_SESSION['user_id'], ($isEdit ? 'Updated contract ' . $existing['contract_number']
        : 'Created contract ' . $result['number'] . ' for contractor #' . $contractorId), 'contractors');


    flash('saved', $isEdit ? 'Contract updated.' : 'Contract created as a draft. Approve it, then activate.', 'success');
    to('modules/contractors/view.php?id=' . $contractorId . '&contract=' . $savedId . '#contracts');
}

/* ------------------------------------------------------------------ */
/* Form definition                                                     */
/* ------------------------------------------------------------------ */
$sections = contractorEngagementSections($opts, true);
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $isEdit ? 'Edit Contract' : 'New Contract' ?></h1>
        <p class="text-gray-500 text-sm mt-1">
            <a href="<?= url('modules/contractors/view.php?id=' . $contractorId) ?>" class="text-indigo-600 hover:underline">
                <?= htmlspecialchars(trim($person['first_name'] . ' ' . $person['last_name'])) ?>
            </a>
            <span class="font-mono ml-2 text-slate-400"><?= htmlspecialchars($person['contractor_code']) ?></span>
            <?php if ($activeCompany): ?>
            <span class="ml-2 text-slate-400">&middot; engaged by <?= htmlspecialchars($activeCompany['company_code'] . ' — ' . $activeCompany['company_name']) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= url('modules/contractors/view.php?id=' . $contractorId . ($isEdit ? '&contract=' . $contractId : '')) ?>"
       class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Back to contractor</a>
</div>

<?php if ($isEdit): ?>
<div class="bg-amber-50 border border-amber-100 rounded-xl p-4 mb-5 text-sm text-amber-800">
    <i class="fas fa-pen-to-square mr-2"></i>Editing a draft contract. Once approved, its terms become read-only.
</div>
<?php endif; ?>

<form method="post" id="contractForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="contractor_id" value="<?= $contractorId ?>">
    <?php if ($isEdit): ?><input type="hidden" name="contract_id" value="<?= $contractId ?>"><?php endif; ?>

    <?php foreach ($sections as $sec): ?>
        <?php contractorRenderSection($sec, $record, $errors); ?>
    <?php endforeach; ?>

    <div class="sticky bottom-0 bg-white border border-slate-100 rounded-xl shadow-lg p-4 flex items-center gap-3 mb-8">
        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-save mr-2"></i><?= $isEdit ? 'Update Contract' : 'Create Draft Contract' ?>
        </button>
        <a href="<?= url('modules/contractors/view.php?id=' . $contractorId . ($isEdit ? '&contract=' . $contractId : '')) ?>"
           class="px-5 py-2.5 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">Cancel</a>
        <span class="ml-auto text-xs text-slate-400"><span class="text-red-500">*</span> required</span>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // The agency field only matters for agency-supplied engagements.
    var source = document.querySelector('#supply_source');
    var agency = document.querySelector('#agency_vendor_id');
    function syncAgency() {
        if (!source || !agency) return;
        var isAgency = source.value === 'agency';
        agency.closest('div').classList.toggle('opacity-50', !isAgency);
        agency.disabled = !isAgency;
    }
    if (source) { source.addEventListener('change', syncAgency); syncAgency(); }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
