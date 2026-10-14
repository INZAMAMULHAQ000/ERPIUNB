<?php
/**
 * Sole POST endpoint for contractor contract lifecycle and rate entries.
 *
 *   approve | activate | extend | complete | terminate | set_current |
 *   delete_contract | add_rate | delete_rate
 *
 * Lifecycle changes need contractors.manage_contracts; rate entries are part of
 * negotiating a draft, so they need contractors.edit. Each branch returns a
 * message on failure rather than mutating partially.
 */
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access Denied');
}

$action = $_POST['action'] ?? '';
$allowed = ['approve', 'activate', 'extend', 'complete', 'terminate', 'set_current', 'delete_contract',
            'add_rate', 'delete_rate', 'add_contract_doc', 'delete_contract_doc'];
if (!in_array($action, $allowed, true)) {
    die('Access Denied');
}

$needsManage = in_array($action, ['approve', 'activate', 'extend', 'complete', 'terminate', 'set_current', 'delete_contract'], true);
if (!hasPermission($needsManage ? 'contractors.manage_contracts' : 'contractors.edit')) {
    die('Access Denied');
}

$db = getDB();
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Your session expired. Please try again.', 'error');
    to('modules/contractors/index.php');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$contractId = (int) ($_POST['contract_id'] ?? 0);
$redirectBack = function ($contractorId, $focusId = 0) {
    $url = 'modules/contractors/view.php?id=' . (int) $contractorId;
    if ($focusId) { $url .= '&contract=' . (int) $focusId . '#contracts'; }
    to($url);
};

/* Rate entries address the schedule row directly, then find their contract. */
if ($action === 'delete_rate') {
    $rateId = (int) ($_POST['rate_id'] ?? 0);
    $stmt = $db->prepare("SELECT r.*, cc.contractor_id, cc.contract_number FROM contractor_rate_schedules r
                            JOIN contractor_contracts cc ON cc.id = r.contract_id
                           WHERE r.id = ?");
    $stmt->execute([$rateId]);
    $rate = $stmt->fetch();
    if (!$rate) {
        flash('rate', 'Rate entry not found.', 'error');
        to('modules/contractors/index.php');
    }
    $db->prepare("DELETE FROM contractor_rate_schedules WHERE id = ?")->execute([$rateId]);
    logAudit($userId, 'Deleted rate entry from contract ' . $rate['contract_number'], 'contractors');
    flash('rate', 'Rate entry deleted.', 'success');
    $redirectBack($rate['contractor_id'], (int) $rate['contract_id']);
}

/* Contract paperwork: same table, tagged with the contract it belongs to. */
if ($action === 'add_contract_doc' || $action === 'delete_contract_doc') {
    $stmt = $db->prepare("SELECT * FROM contractor_contracts WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int) ($_POST['contract_id'] ?? 0)]);
    $docContract = $stmt->fetch();
    if (!$docContract) {
        flash('doc', 'Contract not found.', 'error');
        to('modules/contractors/index.php');
    }
    $docContractorId = (int) $docContract['contractor_id'];
    $docContractId = (int) $docContract['id'];
    $backTo = 'modules/contractors/view.php?id=' . $docContractorId . '&contract=' . $docContractId . '#contracts';

    if ($action === 'delete_contract_doc') {
        $docStmt = $db->prepare("SELECT * FROM contractor_documents WHERE id = ? AND contract_id = ?");
        $docStmt->execute([(int) ($_POST['document_id'] ?? 0), $docContractId]);
        $doc = $docStmt->fetch();
        if (!$doc) {
            flash('doc', 'Document not found on this contract.', 'error');
        } elseif (!array_key_exists($doc['document_type'], contractorContractDocTypes())) {
            // Compliance scans and personal attachments are managed elsewhere;
            // this screen may only touch contract paperwork.
            flash('doc', 'Only contract paperwork can be removed from the contract card.', 'error');
        } else {
            attachmentDeleteFile('contractor', $doc['file_path']);
            $db->prepare("DELETE FROM contractor_documents WHERE id = ?")->execute([(int) $doc['id']]);
            logAudit($userId, 'Deleted ' . $doc['document_type'] . ' from contract ' . $docContract['contract_number'], 'contractors');
            flash('doc', 'Contract document deleted.', 'success');
        }
        to($backTo);
    }

    if (empty($_FILES['document']['name'])) {
        flash('doc', 'Choose a file to attach.', 'error');
        to($backTo);
    }

    $docTypes = contractorContractDocTypes();
    $type = trim($_POST['document_type'] ?? 'contract_copy');
    if (!array_key_exists($type, $docTypes)) {
        $type = 'contract_copy';
    }
    $label = substr(trim($_POST['document_name'] ?? ''), 0, 255);
    $expiresOn = contractorNormaliseDate(trim($_POST['expires_on'] ?? ''));
    $docNotes = substr(trim($_POST['notes'] ?? ''), 0, 255);

    try {
        $stored = attachmentStoreFile('contractor', $docContractorId, $type, $_FILES['document'], contractorDocMaxBytes());
        $db->prepare("INSERT INTO contractor_documents
            (contractor_id, contract_id, document_type, document_name, file_path, file_size, mime_type, expires_on, notes, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([
               $docContractorId, $docContractId, $type,
               $label !== '' ? $label : $stored['original_name'],
               $stored['file_path'], $stored['file_size'], $stored['mime_type'],
               $expiresOn ?: null, $docNotes ?: null, $userId,
           ]);
        logAudit($userId, 'Attached ' . $type . ' to contract ' . $docContract['contract_number'], 'contractors');
        flash('doc', $docTypes[$type] . ' attached to the contract.', 'success');
    } catch (RuntimeException $e) {
        flash('doc', $e->getMessage(), 'error');
    }
    to($backTo);
}

$contract = contractorLoadContract($db, $contractId);
if (!$contract) {
    flash('contract', 'Contract not found.', 'error');
    to('modules/contractors/index.php');
}
$contractorId = (int) $contract['contractor_id'];
$number = $contract['contract_number'];

if ($action === 'add_rate') {
    $validFrom = contractorNormaliseDate(trim($_POST['valid_from'] ?? ''));
    $validTo   = contractorNormaliseDate(trim($_POST['valid_to'] ?? ''));
    $rateBases = contractorRateBases();
    $amount    = ($_POST['rate_amount'] ?? '') === '' ? null : (float) $_POST['rate_amount'];
    $basis     = trim($_POST['rate_basis'] ?? '');
    $currency  = strtoupper(trim($_POST['currency_code'] ?? ($contract['currency_code'] ?? '')));
    $reason    = substr(trim($_POST['reason'] ?? ''), 0, 255);

    if ($validFrom === '') {
        flash('rate', 'A rate entry needs a valid "from" date.', 'error');
        $redirectBack($contractorId, $contractId);
    }
    if ($amount === null || $amount < 0) {
        flash('rate', 'Enter a rate of zero or more.', 'error');
        $redirectBack($contractorId, $contractId);
    }
    if ($basis !== '' && !array_key_exists($basis, $rateBases)) {
        flash('rate', 'Unknown rate basis.', 'error');
        $redirectBack($contractorId, $contractId);
    }
    if ($validTo !== '' && strtotime($validTo) < strtotime($validFrom)) {
        flash('rate', 'The "valid to" date must be on or after the "valid from" date.', 'error');
        $redirectBack($contractorId, $contractId);
    }

    try {
        $stmt = $db->prepare("INSERT INTO contractor_rate_schedules
            (contract_id, valid_from, valid_to, rate_basis, rate_amount, currency_code, reason, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $contractId, $validFrom, $validTo ?: null, $basis ?: null, $amount,
            $currency !== '' ? $currency : null, $reason ?: null, $userId,
        ]);
        logAudit($userId, "Added rate $amount for contract $number", 'contractors');
        flash('rate', 'Rate entry added.', 'success');
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            flash('rate', 'A rate entry already starts on ' . date('M d, Y', strtotime($validFrom)) . '.', 'error');
        } else {
            flash('rate', 'Could not save the rate: ' . $e->getMessage(), 'error');
        }
    }
    $redirectBack($contractorId, $contractId);
}

/** Lifecycle: each handler returns null on success or a message. */
switch ($action) {
    case 'approve':
        $message = contractorApproveContract($db, $contractId, $userId);
        $success = 'Contract approved. It can now be activated.';
        break;
    case 'activate':
        $message = contractorActivateContract($db, $contractId);
        $success = 'Contract activated and set as current.';
        break;
    case 'extend':
        $days = ($_POST['extension_days'] ?? '') === '' ? null : (int) $_POST['extension_days'];
        $message = contractorExtendContract($db, $contractId, $userId, $days, trim($_POST['reason'] ?? ''));
        $success = 'Contract extended.';
        break;
    case 'complete':
        $message = contractorCompleteContract($db, $contractId, $userId, 'Scope completed');
        $success = 'Contract marked completed.';
        break;
    case 'terminate':
        $endReasons = lookupOptions('contractor.end_reason', []);
        $key = trim($_POST['end_reason'] ?? '');
        $label = $endReasons[$key] ?? ($key !== '' ? $key : '');
        $message = contractorTerminateContract($db, $contractId, $userId, $label);
        $success = 'Contract terminated.';
        break;
    case 'set_current':
        $previous = $db->prepare("SELECT contract_number FROM contractor_contracts
                                   WHERE contractor_id = ? AND is_current = 1 AND id <> ? AND deleted_at IS NULL");
        $previous->execute([$contractorId, $contractId]);
        $replaced = $previous->fetchColumn();
        contractorSetCurrent($db, $contractorId, $contractId);
        $message = null;
        $success = $replaced
            ? 'Current contract switched from ' . $replaced . ' to ' . $number . '.'
            : 'This contract is now the current engagement.';
        break;
    case 'delete_contract':
        $message = contractorDeleteContract($db, $contractId);
        $success = 'Draft contract deleted.';
        break;
    default:
        $message = 'Unknown action.';
        $success = '';
}

if ($message !== null) {
    flash('contract', $message, 'error');
    $redirectBack($contractorId, $contractId);
}

logAudit($userId, ucfirst(str_replace('_', ' ', $action)) . ' contract ' . $number, 'contractors');
flash('contract', $success, 'success');

// A deleted draft cannot be the focus any more.
if ($action === 'delete_contract') {
    to('modules/contractors/view.php?id=' . $contractorId . '#contracts');
}
$redirectBack($contractorId, $contractId);
