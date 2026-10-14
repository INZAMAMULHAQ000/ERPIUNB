<?php
$isEdit = isset($_GET['id']) && (int) $_GET['id'] > 0;
$pageTitle = $isEdit ? 'Edit Contractor' : 'New Contractor';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';
require_once __DIR__ . '/form_helpers.php';
require_once __DIR__ . '/engagement_fields.php';

if (!hasPermission($isEdit ? 'contractors.edit' : 'contractors.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$categories = lookupOptions('contractor.category', [
    'consultant' => 'Consultant', 'technical' => 'Technical / Engineering', 'it_services' => 'IT Services',
    'creative' => 'Creative / Media', 'administrative' => 'Administrative Support', 'skilled_labour' => 'Skilled Labour',
    'driver' => 'Driver / Logistics', 'training' => 'Training', 'other' => 'Other',
]);

$vendorStmt = $db->query("SELECT id, vendor_code, vendor_name FROM vendors WHERE status = 'active' ORDER BY vendor_name");
$vendorOpts = [];
foreach ($vendorStmt->fetchAll() as $v) {
    $vendorOpts[$v['id']] = ($v['vendor_code'] ? $v['vendor_code'] . ' — ' : '') . $v['vendor_name'];
}

$contractor = [
    'first_name' => '', 'last_name' => '', 'gender' => '', 'date_of_birth' => '', 'nationality' => '',
    'email' => '', 'phone' => '', 'address' => '', 'work_location' => '', 'contractor_category' => '',
    'specialisation' => '', 'status' => 'active',
    'bank_name' => '', 'bank_account_number' => '', 'account_holder_name' => '', 'trn_number' => '',
    'termination_date' => '', 'termination_reason' => '', 'notes' => '',
];
$slotDocs = [];
$photoPath = '';
$contractorId = 0;

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM contractors WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('nf', 'Contractor not found.', 'error');
        to('modules/contractors/index.php');
    }
    $contractor = array_merge($contractor, $found);
    $contractorId = (int) $found['id'];
    $slotDocs = contractorSlotDocuments($db, $contractorId);
    $photoPath = $found['photo_path'] ?? '';
}

/*
| Inline engagement block
|-------------------------
| Registering a contractor and opening their first contract is one action. The
| block edits the current contract when that contract is still an unapproved
| draft, and is read-only once it has been approved or activated — the lifecycle
| locks are enforced by contract_service.php, not duplicated here.
*/
$currentContract = $contractorId ? contractorCurrentContract($db, $contractorId) : null;
$engagementLocked = $currentContract !== null && !contractorContractEditable($currentContract);
$engOptions = contractorEngagementOptions($db);
$engSections = contractorEngagementSections($engOptions, false);
$engagementRecord = $currentContract ?: ['title' => '', 'contract_type' => '', 'scope_of_work' => '',
    'supply_source' => 'direct', 'agency_vendor_id' => '', 'department_id' => '', 'designation_id' => '',
    'contractual_job_title' => '', 'project_id' => '', 'manager_employee_id' => '', 'purchase_order_id' => '',
    'po_reference' => '', 'start_date' => date('Y-m-d'), 'end_date' => '', 'work_arrangement' => '',
    'fte_allocation' => '', 'rate_basis' => '', 'rate_amount' => '',
    'currency_code' => function_exists('getActiveCurrency') ? getActiveCurrency() : 'AED',
    'estimated_value' => '', 'not_to_exceed_amount' => '', 'billing_frequency' => 'monthly',
    'payment_terms' => '', 'timesheet_required' => '', 'security_clearance' => '', 'badge_number' => '',
    'extension_limit' => '', 'extension_period_days' => '30'];
$engagementRecord['timesheet_required'] = (int) ($engagementRecord['timesheet_required'] ?? 0) === 1 ? '1' : '';
$engProvided = $_SERVER['REQUEST_METHOD'] === 'POST' && contractorEngagementPosted($_POST);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('csrf', 'Your session expired. Please submit the form again.', 'error');
        to($isEdit ? 'modules/contractors/form.php?id=' . $contractorId : 'modules/contractors/form.php');
    }

    $core = [
        'first_name'   => trim($_POST['first_name'] ?? ''),
        'last_name'    => trim($_POST['last_name'] ?? ''),
        'gender'       => in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : '',
        'date_of_birth'=> trim($_POST['date_of_birth'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'phone'        => trim($_POST['phone'] ?? ''),
        'address'      => trim($_POST['address'] ?? ''),
        'work_location'=> trim($_POST['work_location'] ?? ''),
        'contractor_category' => trim($_POST['contractor_category'] ?? ''),
        'specialisation'      => trim($_POST['specialisation'] ?? ''),
        'bank_name'           => trim($_POST['bank_name'] ?? ''),
        'bank_account_number' => trim($_POST['bank_account_number'] ?? ''),
        'account_holder_name' => trim($_POST['account_holder_name'] ?? ''),
        'status'       => in_array($_POST['status'] ?? '', ['active', 'suspended', 'terminated'], true) ? $_POST['status'] : 'active',
        'termination_date'   => trim($_POST['termination_date'] ?? ''),
        'termination_reason' => trim($_POST['termination_reason'] ?? ''),
        'notes'        => trim($_POST['notes'] ?? ''),
    ];

    $data = array_merge($core, contractorIdInput($_POST));
    $dob = $data['date_of_birth'] !== '' ? contractorNormaliseDate($data['date_of_birth']) : '';
    $termDate = $data['termination_date'] !== '' ? contractorNormaliseDate($data['termination_date']) : '';

    $errors = validate($data, [
        'first_name' => 'required|min:2|max:100',
        'last_name'  => 'max:100',
        'address'    => 'max:500',
        'notes'      => 'max:65000',
    ]);
    $errors += contractorIdErrors($data, $_POST);

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address';
    }
    if (strlen($data['specialisation']) > 200) {
        $errors['specialisation'] = 'Specialisation must not exceed 200 characters';
    }
    if ($data['date_of_birth'] !== '' && $dob === '') {
        $errors['date_of_birth'] = 'Date of Birth must be a valid date';
    }
    $data['date_of_birth'] = $dob;

    if ($data['status'] === 'terminated') {
        if ($termDate === '') {
            $errors['termination_date'] = 'A termination date is required when the contractor is terminated';
        }
        if ($data['termination_reason'] === '') {
            $errors['termination_reason'] = 'A termination reason is required';
        }
    }
    $data['termination_date'] = $termDate;

    if (!isset($errors['contractor_category']) && $data['contractor_category'] !== ''
        && !array_key_exists($data['contractor_category'], $categories)) {
        $errors['contractor_category'] = 'Choose a category from the list';
    }

    if (empty($errors) && $data['email'] !== '') {
        $chk = $db->prepare("SELECT id FROM contractors WHERE email = ? AND id <> ? AND deleted_at IS NULL LIMIT 1");
        $chk->execute([$data['email'], $contractorId]);
        if ($chk->fetch()) {
            $errors['email'] = 'Another contractor already uses this email';
        }
    }

    $engInput = null;
    if (!$engagementLocked) {
        $engSaving = $engProvided && ($currentContract === null || contractorContractEditable($currentContract));
        if ($engSaving) {
            $engInput = contractorContractInput($_POST);
            $errors += contractorContractErrors($engInput, $_POST, $db);
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to($isEdit ? 'modules/contractors/form.php?id=' . $contractorId : 'modules/contractors/form.php');
    }

    $columns = [
        'first_name', 'last_name', 'gender', 'date_of_birth', 'nationality', 'email', 'phone', 'address',
        'work_location', 'contractor_category', 'specialisation',
        'emirates_id_number', 'emirates_id_expiry', 'passport_number', 'passport_expiry',
        'visa_type', 'visa_number', 'visa_expiry', 'work_permit_number', 'work_permit_expiry',
        'labor_card_number', 'labor_card_expiry', 'trn_number',
        'bank_name', 'bank_account_number', 'account_holder_name',
        'status', 'termination_date', 'termination_reason', 'notes',
    ];

    $bind = [];
    foreach ($columns as $c) {
        $bind[$c] = $data[$c] !== '' ? $data[$c] : null;
    }

    try {
        if ($isEdit) {
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", $columns));
            $stmt = $db->prepare("UPDATE contractors SET $set, updated_by = ? WHERE id = ?");
            $args = array_values($bind);
            $args[] = $_SESSION['user_id'];
            $args[] = $contractorId;
            $stmt->execute($args);
            logAudit($_SESSION['user_id'], 'Updated contractor: ' . $data['first_name'] . ' ' . $data['last_name'] . " (#$contractorId)", 'contractors');
        } else {
            $code = contractorAllocateNumber($db, 'contractor', 'contractors', 'contractor_code');
            $cols = array_merge(['contractor_code', 'created_at', 'created_by'], $columns);
            $vals = array_merge([$code, date('Y-m-d H:i:s'), $_SESSION['user_id']], array_values($bind));
            $ph = implode(', ', array_fill(0, count($cols), '?'));
            $db->prepare("INSERT INTO contractors (`" . implode('`, `', $cols) . "`) VALUES ($ph)")->execute($vals);
            $contractorId = (int) $db->lastInsertId();
            logAudit($_SESSION['user_id'], 'Created contractor: ' . $data['first_name'] . ' ' . $data['last_name'] . " ($code)", 'contractors');
        }
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $errors['email'] = 'That email is already used by a contractor.';
            saveFormState($errors);
            to('modules/contractors/form.php');
        }
        flash('error', 'Could not save the contractor: ' . $e->getMessage(), 'error');
        to($isEdit ? 'modules/contractors/form.php?id=' . $contractorId : 'modules/contractors/form.php');
    } catch (RuntimeException $e) {
        // Numbering not configured for the active company.
        flash('error', $e->getMessage(), 'error');
        to('modules/contractors/form.php');
    }

    if ($engInput !== null) {
        $result = contractorSaveContract($db, $engInput, $contractorId,
            $currentContract ? (int) $currentContract['id'] : 0, $_SESSION['user_id']);
        if ($result['error'] !== null) {
            flash('contract', 'Contractor saved, but the engagement was not: ' . $result['error'], 'error');
        } else {
            logAudit($_SESSION['user_id'], ($currentContract ? 'Updated' : 'Created')
                . ' inline contract #' . $result['id'] . ' for contractor #' . $contractorId, 'contractors');
        }
    }

    $uploadErrors = [];
    foreach (array_keys(contractorDocSlots()) as $slot) {
        $message = contractorSaveSlot($db, $contractorId, $slot, $_FILES[$slot] ?? null, !empty($_POST['remove_' . $slot]), $_SESSION['user_id']);
        if ($message !== null) {
            $uploadErrors[$slot] = $message;
        }
    }
    $photoMessage = contractorSavePhoto(
        $db,
        $contractorId,
        $_FILES['photo'] ?? null,
        !empty($_POST['remove_photo']),
        $photoPath
    );
    if ($photoMessage !== null) {
        $uploadErrors['photo'] = $photoMessage;
    }

    if ($uploadErrors) {
        flash('uploads', 'Contractor saved, but these files were not accepted: ' . implode(' ', $uploadErrors), 'warning');
    } else {
        flash('saved', $isEdit ? 'Contractor updated.' : 'Contractor created.', 'success');
    }
    to('modules/contractors/view.php?id=' . $contractorId);
}

/* ------------------------------------------------------------------ */
/* Form definition — same descriptor shape as the customer master.     */
/* ------------------------------------------------------------------ */
$sections = [
    ['title' => 'Contractor Identity', 'icon' => 'fa-user-pen', 'fields' => [
        ['n' => 'first_name',  'l' => 'First Name',  't' => 'text', 'span' => 1, 'req' => true],
        ['n' => 'last_name',   'l' => 'Last Name',   't' => 'text', 'span' => 1],
        ['n' => 'gender',      'l' => 'Gender',      't' => 'select', 'opts' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'], 'span' => 1, 'blank' => '— Select —'],
        ['n' => 'date_of_birth', 'l' => 'Date of Birth', 't' => 'date', 'span' => 1],
        ['n' => 'email',       'l' => 'Email',       't' => 'email', 'span' => 1],
        ['n' => 'phone',       'l' => 'Phone',       't' => 'tel', 'span' => 1, 'ph' => '05xxxxxxxx'],
        ['n' => 'work_location', 'l' => 'Work Location', 't' => 'text', 'span' => 1, 'ph' => 'Dubai'],
        ['n' => 'contractor_category', 'l' => 'Contractor Category', 't' => 'select', 'opts' => $categories, 'span' => 1, 'blank' => '— Select —'],
        ['n' => 'specialisation', 'l' => 'Specialisation', 't' => 'text', 'span' => 2, 'ph' => 'e.g. Fire-rated electrical, SAP FICO'],
        ['n' => 'address',     'l' => 'Address',     't' => 'textarea', 'span' => 2],
    ]],
    ['title' => 'Payment & Tax', 'icon' => 'fa-building-columns', 'fields' => [
        ['n' => 'bank_name',           'l' => 'Bank Name',            't' => 'text', 'span' => 1],
        ['n' => 'bank_account_number', 'l' => 'Account / IBAN',       't' => 'text', 'span' => 1],
        ['n' => 'account_holder_name', 'l' => 'Account Holder',       't' => 'text', 'span' => 1],
        ['n' => 'trn_number',          'l' => 'VAT TRN (if registered)', 't' => 'text', 'span' => 1, 'hint' => 'Freelancers with a tax registration'],
    ]],
    ['title' => 'Status', 'icon' => 'fa-circle-half-stroke', 'fields' => [
        ['n' => 'status',   'l' => 'Register Status', 't' => 'select', 'opts' => [
            'active' => 'Active', 'suspended' => 'Suspended', 'terminated' => 'Terminated',
        ], 'span' => 1],
        ['n' => 'termination_date',   'l' => 'Termination Date',   't' => 'date', 'span' => 1],
        ['n' => 'termination_reason', 'l' => 'Termination Reason', 't' => 'text', 'span' => 2],
        ['n' => 'notes', 'l' => 'Notes', 't' => 'textarea', 'span' => 4],
    ]],
];

?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= $isEdit ? 'Edit Contractor' : 'New Contractor' ?></h1>
        <p class="text-gray-500 text-sm mt-1">
            <?php if ($isEdit): ?>
                <span class="font-mono"><?= htmlspecialchars($contractor['contractor_code']) ?></span>
                &middot; <?= htmlspecialchars(trim($contractor['first_name'] . ' ' . $contractor['last_name'])) ?>
            <?php else: ?>
                Register a contingent worker and their right-to-work documents
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= url($isEdit ? 'modules/contractors/view.php?id=' . $contractorId : 'modules/contractors/index.php') ?>"
       class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Back</a>
</div>

<form method="post" enctype="multipart/form-data" id="contractorForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <?php contractorRenderSection($sections[0], $contractor, $errors); ?>

    <?php require __DIR__ . '/compliance_form.php'; ?>

    <?php foreach (array_slice($sections, 1) as $sec): ?>
        <?php contractorRenderSection($sec, $contractor, $errors); ?>
    <?php endforeach; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-2 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                <i class="fas fa-file-signature text-indigo-500"></i> Engagement &amp; Contract Terms
            </h2>
            <?php if ($currentContract): ?>
            <span class="text-xs text-slate-500">
                editing <span class="font-mono"><?= htmlspecialchars($currentContract['contract_number']) ?></span>
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] <?= $engagementLocked ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' ?>">
                    <?= $engagementLocked ? 'approved — read-only' : 'draft' ?>
                </span>
                <a href="<?= url('modules/contractors/view.php?id=' . $contractorId . '&contract=' . (int) $currentContract['id'] . '#contracts') ?>"
                   class="ml-2 text-indigo-600 hover:text-indigo-800">lifecycle &amp; documents</a>
            </span>
            <?php else: ?>
            <span class="text-xs text-slate-500">optional — filling this in opens a draft contract alongside the register entry</span>
            <?php endif; ?>
        </div>
        <?php if ($engagementLocked): ?>
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3">
            <i class="fas fa-lock mr-1.5"></i>
            This contract has been approved, so its terms are read-only here. Use
            <a href="<?= url('modules/contractors/view.php?id=' . $contractorId . '&contract=' . (int) $currentContract['id'] . '#contracts') ?>" class="underline font-medium">Extend, Complete or Terminate</a>
            on the profile, or raise a new contract for changes.
        </p>
        <?php endif; ?>
        <?php foreach ($engSections as $sec): ?>
            <?php contractorRenderSection($sec, $engagementRecord, $errors, $engagementLocked); ?>
        <?php endforeach; ?>
    </div>

    <div class="sticky bottom-0 bg-white border border-slate-100 rounded-xl shadow-lg p-4 flex items-center gap-3 mb-8">
        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-save mr-2"></i><?= $isEdit ? 'Update Contractor' : 'Create Contractor' ?>
        </button>
        <a href="<?= url($isEdit ? 'modules/contractors/view.php?id=' . $contractorId : 'modules/contractors/index.php') ?>"
           class="px-5 py-2.5 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">Cancel</a>
        <span class="ml-auto text-xs text-slate-400"><span class="text-red-500">*</span> required</span>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Document numbers are stored canonicalised; mirror that while typing so what
    // the user sees is what the register holds.
    document.querySelectorAll('#contractorForm input.doc-number').forEach(function (el) {
        var digitsOnly = el.name === 'emirates_id_number';
        el.addEventListener('input', function () {
            el.value = digitsOnly
                ? el.value.replace(/\D/g, '')
                : el.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        });
    });

    ['passport_copy', 'visa_copy', 'emirates_id_copy', 'work_permit_copy', 'labor_card_copy'].forEach(function (slot) {
        var el = document.querySelector('#' + slot);
        if (!el) return;
        el.addEventListener('change', function () {
            if (el.files.length && el.files[0].size > 5 * 1024 * 1024) {
                alert(el.files[0].name + ' is larger than 5 MB.');
                el.value = '';
            }
        });
    });

    // Agency field only matters when the engagement is agency-supplied.
    var src = document.querySelector('#supply_source');
    var agency = document.querySelector('#agency_vendor_id');
    function syncAgency() {
        if (!src || !agency) return;
        var on = src.value === 'agency';
        agency.closest('div').classList.toggle('opacity-50', !on);
        agency.disabled = !on;
    }
    if (src) { src.addEventListener('change', syncAgency); syncAgency(); }

    var photo = document.querySelector('#photo');
    if (photo) {
        photo.addEventListener('change', function () {
            if (photo.files.length && photo.files[0].size > 2 * 1024 * 1024) {
                alert(photo.files[0].name + ' is larger than 2 MB.');
                photo.value = '';
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../../templates/document_viewer.php'; ?>
<?php require_once '../../includes/footer.php'; ?>
