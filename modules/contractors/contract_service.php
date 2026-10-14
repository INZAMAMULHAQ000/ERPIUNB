<?php
/**
 * Contractor contract lifecycle.
 *
 * States follow Oracle's separation of the contract record from its approval:
 * draft -> (approve) -> draft approved -> (activate) -> active -> completed, or
 * active -> terminated. An active contract can be extended within its limit.
 *
 * A contract can only be activated once it has been approved by someone holding
 * contractors.manage_contracts, only one contract per contractor may be current
 * (enforced by UNIQUE(contractor_id, is_current)), and a contract that has ever
 * been approved can no longer be edited or deleted — the numbers on it are
 * committed.
 *
 * Extensions are bounded by extension_limit so an open-ended engagement cannot
 * quietly become permanent employment, which is the classification risk ERP
 * suites build this guard for.
 */

/** Display status, derived: an active contract past its end date reads as expired. */
function contractorContractState($contract)
{
    $status = $contract['status'] ?? 'draft';

    if ($status === 'active' && !empty($contract['end_date'])) {
        if (strtotime($contract['end_date']) < strtotime(date('Y-m-d'))) {
            return ['label' => 'Expired', 'class' => 'bg-red-100 text-red-700', 'key' => 'expired'];
        }
        $days = intdiv(strtotime($contract['end_date']) - time(), 86400);
        if ($days <= 30) {
            return ['label' => 'Ending in ' . $days . 'd', 'class' => 'bg-amber-100 text-amber-700', 'key' => 'ending'];
        }
    }

    return [
        'draft'       => ['label' => 'Draft',      'class' => 'bg-slate-100 text-slate-600',  'key' => 'draft'],
        'active'      => ['label' => 'Active',     'class' => 'bg-emerald-100 text-emerald-700', 'key' => 'active'],
        'completed'   => ['label' => 'Completed',  'class' => 'bg-slate-200 text-slate-700',  'key' => 'completed'],
        'terminated'  => ['label' => 'Terminated', 'class' => 'bg-red-100 text-red-700',      'key' => 'terminated'],
    ][$status] ?? ['label' => ucfirst($status), 'class' => 'bg-slate-100 text-slate-600', 'key' => $status];
}

/** Is this contract frozen (approved or spent)? Approved terms are not editable. */
function contractorContractEditable($contract)
{
    return ($contract['status'] ?? '') === 'draft' && empty($contract['approved_at']);
}

/** Rate options, driven by the lookup list with a code-side fallback. */
function contractorRateBases()
{
    $fromLookup = lookupOptions('contractor.rate_basis', []);
    return $fromLookup ?: [
        'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly',
        'monthly' => 'Monthly', 'fixed_deliverable' => 'Fixed / Deliverable', 'milestone' => 'Milestone',
    ];
}

/**
 * Approximate monthly cost, used for the run-rate KPI. Fixed and milestone
 * engagements have no monthly equivalent and are excluded rather than guessed.
 */
function contractorMonthlyEquivalent($amount, $basis)
{
    $amount = (float) $amount;
    if ($amount <= 0) {
        return 0.0;
    }
    $factors = ['hourly' => 173.33, 'daily' => 21.67, 'weekly' => 4.348, 'monthly' => 1];
    return isset($factors[$basis]) ? $amount * $factors[$basis] : 0.0;
}

/**
 * Allocate a document number that is not already taken. Master codes are global
 * while `document_sequences` counters are per company, so in a multi-entity
 * setup two companies can produce the same number; consuming the next value on
 * collision keeps both counters honest and the unique key unbroken.
 */
function contractorAllocateNumber($db, $docType, $table, $column)
{
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $number = nextDocumentNumber($db, $docType);
        $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
        $stmt->execute([$number]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $number;
        }
    }
    throw new RuntimeException('Could not allocate a unique number for ' . $docType . '. Check the Document Numbering counter.');
}

/** The contract flagged current for a contractor, newest first as a safety net. */
function contractorCurrentContract($db, $contractorId)
{
    $stmt = $db->prepare("SELECT * FROM contractor_contracts WHERE contractor_id = ? AND deleted_at IS NULL ORDER BY is_current DESC, id DESC LIMIT 1");
    $stmt->execute([$contractorId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Make one contract the current one; NULL elsewhere keeps the unique index satisfiable. */
function contractorSetCurrent($db, $contractorId, $contractId)
{
    $db->prepare("UPDATE contractor_contracts SET is_current = NULL WHERE contractor_id = ? AND id <> ?")
       ->execute([$contractorId, $contractId]);
    $db->prepare("UPDATE contractor_contracts SET is_current = 1 WHERE id = ? AND contractor_id = ?")
       ->execute([$contractId, $contractorId]);
}

/**
 * Normalise a contract form post. Dates become Y-m-d or '', numbers float or
 * null, and enumerations are validated against their allowed sets.
 */
function contractorContractInput($post)
{
    $data = [
        'title'                => trim((string) ($post['title'] ?? '')),
        'contract_type'        => trim((string) ($post['contract_type'] ?? '')),
        'scope_of_work'        => trim((string) ($post['scope_of_work'] ?? '')),
        'supply_source'        => in_array($post['supply_source'] ?? '', ['direct', 'agency'], true) ? $post['supply_source'] : 'direct',
        'agency_vendor_id'     => (int) ($post['agency_vendor_id'] ?? 0) ?: null,
        'department_id'        => (int) ($post['department_id'] ?? 0) ?: null,
        'designation_id'       => (int) ($post['designation_id'] ?? 0) ?: null,
        'contractual_job_title'=> trim((string) ($post['contractual_job_title'] ?? '')),
        'project_id'           => (int) ($post['project_id'] ?? 0) ?: null,
        'manager_employee_id'  => (int) ($post['manager_employee_id'] ?? 0) ?: null,
        'purchase_order_id'    => (int) ($post['purchase_order_id'] ?? 0) ?: null,
        'po_reference'         => strtoupper(trim((string) ($post['po_reference'] ?? ''))),
        'start_date'           => contractorNormaliseDate(trim((string) ($post['start_date'] ?? ''))),
        'end_date'             => contractorNormaliseDate(trim((string) ($post['end_date'] ?? ''))),
        'work_arrangement'     => trim((string) ($post['work_arrangement'] ?? '')),
        'fte_allocation'       => ($post['fte_allocation'] ?? '') === '' ? null : (float) $post['fte_allocation'],
        'rate_basis'           => trim((string) ($post['rate_basis'] ?? '')),
        'rate_amount'          => ($post['rate_amount'] ?? '') === '' ? null : (float) $post['rate_amount'],
        'currency_code'        => strtoupper(trim((string) ($post['currency_code'] ?? ''))),
        'estimated_value'      => ($post['estimated_value'] ?? '') === '' ? null : (float) $post['estimated_value'],
        'not_to_exceed_amount' => ($post['not_to_exceed_amount'] ?? '') === '' ? null : (float) $post['not_to_exceed_amount'],
        'billing_frequency'    => trim((string) ($post['billing_frequency'] ?? '')),
        'payment_terms'        => trim((string) ($post['payment_terms'] ?? '')),
        'timesheet_required'   => !empty($post['timesheet_required']) ? 1 : 0,
        'security_clearance'   => trim((string) ($post['security_clearance'] ?? '')),
        'badge_number'         => strtoupper(trim((string) ($post['badge_number'] ?? ''))),
        'extension_limit'      => ($post['extension_limit'] ?? '') === '' ? null : max(0, (int) $post['extension_limit']),
        'extension_period_days'=> ($post['extension_period_days'] ?? '') === '' ? null : max(1, (int) $post['extension_period_days']),
        'notes'                => trim((string) ($post['notes'] ?? '')),
    ];

    if ($data['supply_source'] !== 'agency') {
        $data['agency_vendor_id'] = null;
    }

    return $data;
}

function contractorContractErrors($data, $rawInput, $db = null)
{
    $errors = [];

    if ($data['title'] === '') {
        $errors['title'] = 'Contract title is required';
    } elseif (strlen($data['title']) > 200) {
        $errors['title'] = 'Contract title must not exceed 200 characters';
    }

    // A raw date that did not survive normalisation was never a real date.
    foreach (['start_date' => 'Start Date', 'end_date' => 'End Date'] as $field => $label) {
        if (trim((string) ($rawInput[$field] ?? '')) !== '' && $data[$field] === '') {
            $errors[$field] = $label . ' must be a valid date';
        }
    }
    if ($data['start_date'] === '' && !isset($errors['start_date'])) {
        $errors['start_date'] = 'Start Date is required';
    }
    if ($data['start_date'] !== '' && $data['end_date'] !== '' && strtotime($data['end_date']) < strtotime($data['start_date'])) {
        $errors['end_date'] = 'End Date must be on or after the Start Date';
    }

    if ($data['supply_source'] === 'agency' && !$data['agency_vendor_id']) {
        $errors['agency_vendor_id'] = 'An agency-supplied contractor must name the supplying vendor';
    }

    if ($data['contract_type'] === 'purchase_order' && !$data['purchase_order_id'] && $data['po_reference'] === '') {
        $errors['purchase_order_id'] = 'A purchase-order contract needs a linked PO or its reference number';
    }

    if ($data['rate_amount'] !== null && $data['rate_amount'] < 0) {
        $errors['rate_amount'] = 'Rate cannot be negative';
    }
    if ($data['rate_amount'] !== null && $data['rate_amount'] > 0 && $data['rate_basis'] === '') {
        $errors['rate_basis'] = 'Choose the unit the rate is charged in';
    }
    foreach (['estimated_value', 'not_to_exceed_amount'] as $field) {
        if ($data[$field] !== null && $data[$field] < 0) {
            $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' cannot be negative';
        }
    }
    if ($data['not_to_exceed_amount'] !== null && $data['estimated_value'] !== null
        && $data['not_to_exceed_amount'] < $data['estimated_value']) {
        $errors['not_to_exceed_amount'] = 'Not-to-exceed cannot be lower than the estimated value';
    }

    if ($data['fte_allocation'] !== null && ($data['fte_allocation'] < 0 || $data['fte_allocation'] > 100)) {
        $errors['fte_allocation'] = 'Allocation must be between 0 and 100 percent';
    }
    if ($data['extension_limit'] !== null && $data['extension_limit'] > 999) {
        $errors['extension_limit'] = 'Extension limit must not exceed 999';
    }

    if ($db instanceof PDO && $data['currency_code'] !== '') {
        $chk = $db->prepare("SELECT COUNT(*) FROM currencies WHERE code = ?");
        $chk->execute([$data['currency_code']]);
        if (!(int) $chk->fetchColumn()) {
            $errors['currency_code'] = 'Unknown currency code';
        }
    }

    return $errors;
}

/* ----------------------------------------------------------------------
 | Lifecycle actions. Each returns null on success or a message to show.
 * -------------------------------------------------------------------- */

function contractorApproveContract($db, $contractId, $userId)
{
    $contract = contractorLoadContract($db, $contractId);
    if (!$contract) return 'Contract not found.';
    if ($contract['approved_at']) return 'This contract is already approved.';
    if ($contract['status'] !== 'draft') return 'Only a draft contract can be approved.';

    $db->prepare("UPDATE contractor_contracts SET approved_by = ?, approved_at = NOW(), updated_by = ? WHERE id = ?")
       ->execute([$userId, $userId, $contractId]);
    return null;
}

function contractorActivateContract($db, $contractId)
{
    $contract = contractorLoadContract($db, $contractId);
    if (!$contract) return 'Contract not found.';
    if ($contract['status'] === 'active') return 'This contract is already active.';
    if (in_array($contract['status'], ['completed', 'terminated'], true)) return 'A closed contract cannot be activated.';
    if (empty($contract['approved_at'])) return 'Approve the contract before activating it.';
    if (empty($contract['start_date'])) return 'A start date is required before activation.';

    $db->prepare("UPDATE contractor_contracts SET status = 'active' WHERE id = ?")->execute([$contractId]);
    contractorSetCurrent($db, (int) $contract['contractor_id'], (int) $contractId);
    return null;
}

/**
 * Push the end date out by the configured period, counting the extension and
 * refusing once the agreed limit is reached.
 */
function contractorExtendContract($db, $contractId, $userId, $overrideDays = null, $reason = null)
{
    $contract = contractorLoadContract($db, $contractId);
    if (!$contract) return 'Contract not found.';
    if ($contract['status'] !== 'active') return 'Only an active contract can be extended.';
    if (empty($contract['end_date'])) return 'This contract has no end date to extend — edit the contract and set one.';

    $limit = $contract['extension_limit'] === null ? null : (int) $contract['extension_limit'];
    if ($limit !== null && (int) $contract['extension_count'] >= $limit) {
        return 'Extension limit reached (' . $limit . ' of ' . $limit . '). Renegotiate the contract instead of extending.';
    }

    $days = $overrideDays !== null ? (int) $overrideDays : (int) ($contract['extension_period_days'] ?: 30);
    if ($days <= 0) return 'Extension period must be at least one day.';

    $from = $contract['end_date'];
    $newEnd = date('Y-m-d', strtotime($from . ' +' . $days . ' days'));

    $db->prepare("
        UPDATE contractor_contracts
           SET end_date = ?,
               extension_count = extension_count + 1,
               last_extended_on = CURDATE(),
               last_extension_reason = ?,
               updated_by = ?
         WHERE id = ?
    ")->execute([
        $newEnd,
        $reason !== null && $reason !== '' ? substr($reason, 0, 255) : 'Extended by ' . $days . ' days',
        $userId,
        $contractId,
    ]);
    return null;
}

function contractorCompleteContract($db, $contractId, $userId, $reason = null)
{
    $contract = contractorLoadContract($db, $contractId);
    if (!$contract) return 'Contract not found.';
    if ($contract['status'] !== 'active') return 'Only an active contract can be completed.';

    $db->prepare("UPDATE contractor_contracts SET status = 'completed', ended_on = CURDATE(), end_reason = ?, is_current = NULL, updated_by = ? WHERE id = ?")
       ->execute([$reason ?: 'Scope completed', $userId, $contractId]);
    return null;
}

function contractorTerminateContract($db, $contractId, $userId, $reason)
{
    $contract = contractorLoadContract($db, $contractId);
    if (!$contract) return 'Contract not found.';
    if (in_array($contract['status'], ['completed', 'terminated'], true)) return 'This contract is already closed.';
    if ($reason === '') return 'A termination reason is required.';

    $db->prepare("UPDATE contractor_contracts SET status = 'terminated', ended_on = CURDATE(), end_reason = ?, is_current = NULL, updated_by = ? WHERE id = ?")
       ->execute([substr($reason, 0, 255), $userId, $contractId]);
    return null;
}

/** Drafts only — an approved or spent number must stay in the register. */
function contractorDeleteContract($db, $contractId)
{
    $contract = contractorLoadContract($db, $contractId);
    if (!$contract) return 'Contract not found.';
    if (!contractorContractEditable($contract)) {
        return 'This contract has been approved or activated and cannot be deleted. Terminate or complete it instead.';
    }
    $db->prepare("UPDATE contractor_contracts SET deleted_at = NOW(), is_current = NULL, updated_by = ? WHERE id = ?")
       ->execute([$_SESSION['user_id'], $contractId]);
    return null;
}

/**
 * Insert or update a contract from normalised input. Both contract_form.php and
 * the inline block on the contractor master save through here, so the column
 * list lives in one place. Returns ['id' =>, 'error' => null|string].
 */
function contractorSaveContract($db, array $data, $contractorId, $contractId = 0, $userId = null)
{
    $columns = [
        'title', 'contract_type', 'scope_of_work', 'supply_source', 'agency_vendor_id',
        'department_id', 'designation_id', 'contractual_job_title', 'project_id', 'manager_employee_id',
        'purchase_order_id', 'po_reference', 'start_date', 'end_date', 'work_arrangement', 'fte_allocation',
        'rate_basis', 'rate_amount', 'currency_code', 'estimated_value', 'not_to_exceed_amount',
        'billing_frequency', 'payment_terms', 'timesheet_required', 'security_clearance', 'badge_number',
        'extension_limit', 'extension_period_days', 'notes',
    ];

    $bind = [];
    foreach ($columns as $col) {
        $value = $data[$col] ?? '';
        $bind[$col] = $value === '' ? null : $value;
    }
    if (empty($data['rate_amount'])) {
        $bind['rate_amount'] = null;
    }

    $company = null;
    if (function_exists('activeCompanyId')) {
        $company = activeCompanyId() ?: null;
    }

    try {
        if ($contractId > 0) {
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", $columns));
            $args = array_values($bind);
            $args[] = $userId;
            $args[] = $contractId;
            $db->prepare("UPDATE contractor_contracts SET $set, updated_by = ? WHERE id = ?")->execute($args);
            return ['id' => (int) $contractId, 'error' => null];
        }

        $number = contractorAllocateNumber($db, 'contractor_contract', 'contractor_contracts', 'contract_number');
        $cols = array_merge(['contractor_id', 'contract_number', 'company_id', 'status', 'created_at', 'created_by'], $columns);
        $vals = array_merge([$contractorId, $number, $company, 'draft', date('Y-m-d H:i:s'), $userId], array_values($bind));
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        $db->prepare("INSERT INTO contractor_contracts (`" . implode('`, `', $cols) . "`) VALUES ($ph)")->execute($vals);
        return ['id' => (int) $db->lastInsertId(), 'number' => $number, 'error' => null];
    } catch (PDOException $e) {
        return ['id' => (int) $contractId, 'error' => 'Could not save the contract: ' . $e->getMessage()];
    } catch (RuntimeException $e) {
        return ['id' => (int) $contractId, 'error' => $e->getMessage()];
    }
}

function contractorLoadContract($db, $contractId)
{
    $stmt = $db->prepare("SELECT * FROM contractor_contracts WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$contractId]);
    return $stmt->fetch() ?: null;
}

/** Rate history for a contract, most recent first, with the live row flagged. */
function contractorRateSchedule($db, $contractId)
{
    $stmt = $db->prepare("SELECT * FROM contractor_rate_schedules WHERE contract_id = ? ORDER BY valid_from DESC");
    $stmt->execute([$contractId]);
    return $stmt->fetchAll();
}

/** The rate that applies on a given day, falling back to the latest before it. */
function contractorEffectiveRate($db, $contractId, $onDate = null)
{
    $onDate = $onDate ?: date('Y-m-d');
    $stmt = $db->prepare("
        SELECT * FROM contractor_rate_schedules
         WHERE contract_id = ? AND valid_from <= ?
           AND (valid_to IS NULL OR valid_to >= ?)
         ORDER BY valid_from DESC LIMIT 1
    ");
    $stmt->execute([$contractId, $onDate, $onDate]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $fallback = $db->prepare("SELECT * FROM contractor_rate_schedules WHERE contract_id = ? ORDER BY valid_from DESC LIMIT 1");
    $fallback->execute([$contractId]);
    return $fallback->fetch() ?: null;
}
