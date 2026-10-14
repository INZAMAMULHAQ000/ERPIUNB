<?php
/**
 * Contractor master identification: the right-to-work document numbers, their
 * validation, and the storage of their scanned copies.
 *
 * A contractor's file is only as good as its expiry dates — an expired work
 * permit ends the engagement regardless of what the contract says — so every
 * identity document carries a number AND an expiry, and each has a one-current
 * scan slot. Free-form attachments (certificates, insurance, NDA, trade licence)
 * share the same table with an optional expiry of their own.
 *
 * File handling is delegated to helpers/attachment_storage.php under the
 * 'contractor' entity, so uploads land in uploads/contractors/<id>/ with the
 * same sniffing and deletion guarantees as every other module.
 */

require_once __DIR__ . '/../../helpers/attachment_storage.php';

/** Number + expiry pairs held on the contractor master, in display order. */
function contractorIdFields()
{
    return [
        'nationality',
        'passport_number',      'passport_expiry',
        'visa_type',            'visa_number',    'visa_expiry',
        'emirates_id_number',   'emirates_id_expiry',
        'work_permit_number',   'work_permit_expiry',
        'labor_card_number',    'labor_card_expiry',
        'trn_number',
    ];
}

function contractorVisaTypes()
{
    return [
        'employment'        => 'Employment Visa',
        'entry_permit'      => 'Entry Permit',
        'visit'             => 'Visit Visa',
        'tourist'           => 'Tourist Visa',
        'student'           => 'Student Visa',
        'investor'          => 'Investor Visa',
        'golden'            => 'Golden Visa',
        'freelance_permit'  => 'Freelance Permit',
        'family_sponsorship'=> 'Family Sponsorship',
        'other'             => 'Other',
    ];
}

/** One current scan per document; keys double as contractor_documents.document_type. */
function contractorDocSlots()
{
    return [
        'passport_copy'       => 'Passport Copy',
        'visa_copy'           => 'Visa Copy',
        'emirates_id_copy'    => 'Emirates ID Copy',
        'work_permit_copy'    => 'Work Permit Copy',
        'labor_card_copy'     => 'Labour Card Copy',
    ];
}

/** Attachment types that may have more than one file on record. */
function contractorAttachmentTypes()
{
    return [
        'certificate'   => 'Professional Certificate',
        'insurance'     => 'Insurance Certificate',
        'nda'           => 'Non-Disclosure Agreement',
        'trade_license' => 'Trade Licence / Establishment Card',
        'timesheet_proof' => 'Timesheet Evidence',
        'invoice_copy'  => 'Invoice Copy',
        'other'         => 'Other',
    ];
}

/**
 * Paperwork that belongs to a contract rather than to the person. Kept in the
 * same table with contract_id set, so one query answers "what signed this
 * engagement, amended it, renewed it and ended it".
 */
function contractorContractDocTypes()
{
    return [
        'contract_copy'      => 'Signed Contract',
        'sow'                => 'Statement of Work (SOW)',
        'amendment'          => 'Contract Amendment',
        'renewal_letter'     => 'Renewal Letter',
        'termination_letter' => 'Termination Letter',
    ];
}

/** Every document type a contractor row can carry. */
function contractorAllDocTypes()
{
    return contractorContractDocTypes() + contractorAttachmentTypes();
}

/** Documents filed against one contract, newest first. */
function contractorContractDocuments($db, $contractId)
{
    $stmt = $db->prepare("
        SELECT d.*, u.name AS uploader_name
          FROM contractor_documents d
          LEFT JOIN users u ON u.id = d.uploaded_by
         WHERE d.contract_id = ? ORDER BY d.created_at DESC
    ");
    $stmt->execute([$contractId]);
    return $stmt->fetchAll();
}

/** Column each slot's expiry lives in, so a slot can be colour-coded. */
function contractorSlotExpiryColumn($slot)
{
    return [
        'passport_copy'    => 'passport_expiry',
        'visa_copy'        => 'visa_expiry',
        'emirates_id_copy' => 'emirates_id_expiry',
        'work_permit_copy' => 'work_permit_expiry',
        'labor_card_copy'  => 'labor_card_expiry',
    ][$slot] ?? null;
}

function contractorDocMaxBytes()
{
    return 5 * 1024 * 1024;
}

function contractorPhotoMaxBytes()
{
    return 2 * 1024 * 1024;
}

/**
 * Trim and canonicalise the identification inputs: document numbers upper-case
 * without separators, Emirates ID digits only, TRN accepts digits and hyphens,
 * dates normalised to Y-m-d or blank.
 */
function contractorIdInput($post)
{
    $data = [];
    foreach (contractorIdFields() as $field) {
        $data[$field] = trim((string) ($post[$field] ?? ''));
    }

    foreach (['passport_number', 'visa_number', 'work_permit_number', 'labor_card_number'] as $field) {
        $data[$field] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data[$field]));
    }
    $data['emirates_id_number'] = preg_replace('/[^0-9]/', '', $data['emirates_id_number']);
    $data['trn_number'] = strtoupper(preg_replace('/[^0-9\-]/', '', $data['trn_number']));

    foreach (['passport_expiry', 'visa_expiry', 'emirates_id_expiry', 'work_permit_expiry', 'labor_card_expiry'] as $field) {
        $data[$field] = contractorNormaliseDate($data[$field]);
    }

    if ($data['visa_type'] !== '' && !array_key_exists($data['visa_type'], contractorVisaTypes())) {
        $data['visa_type'] = '';
    }

    return $data;
}

function contractorNormaliseDate($value)
{
    if ($value === '') {
        return '';
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
}

function contractorIdErrors($data, $rawInput)
{
    $errors = [];

    if ($data['nationality'] !== '' && !preg_match('/^[A-Za-z ]+$/', $data['nationality'])) {
        $errors['nationality'] = 'Nationality may contain letters and spaces only';
    }

    $numberLabels = [
        'passport_number'   => 'Passport Number',
        'visa_number'       => 'Visa Number',
        'work_permit_number'=> 'Work Permit Number',
        'labor_card_number' => 'Labour Card Number',
    ];
    foreach ($numberLabels as $field => $label) {
        if (strlen($data[$field]) > 50) {
            $errors[$field] = $label . ' must not exceed 50 characters';
        }
    }

    if (strlen($data['emirates_id_number']) > 20) {
        $errors['emirates_id_number'] = 'Emirates ID Number must not exceed 20 digits';
    }
    if (strlen($data['trn_number']) > 40) {
        $errors['trn_number'] = 'TRN must not exceed 40 characters';
    }

    // A value the canonicaliser could not parse was not a real calendar date.
    $dateLabels = [
        'passport_expiry'     => 'Passport Expiry',
        'visa_expiry'         => 'Visa Expiry',
        'emirates_id_expiry'  => 'Emirates ID Expiry',
        'work_permit_expiry'  => 'Work Permit Expiry',
        'labor_card_expiry'   => 'Labour Card Expiry',
    ];
    foreach ($dateLabels as $field => $label) {
        if (trim((string) ($rawInput[$field] ?? '')) !== '' && $data[$field] === '') {
            $errors[$field] = $label . ' must be a valid date';
        }
    }

    return $errors;
}

/** Emirates ID is 15 digits shown as 784-1234-5678901-2; stored digits only. */
function contractorFormatEmiratesId($digits)
{
    $digits = (string) $digits;
    if (strlen($digits) !== 15) {
        return $digits;
    }
    return substr($digits, 0, 3) . '-' . substr($digits, 3, 4) . '-'
        . substr($digits, 7, 7) . '-' . substr($digits, 14, 1);
}

/** Tailwind classes for an expiry date: red past due, amber within 60 days. */
function contractorExpiryClass($date)
{
    if (empty($date)) {
        return 'text-gray-900';
    }
    $days = intdiv(strtotime($date) - time(), 86400);
    if ($days < 0) {
        return 'text-red-600 font-medium';
    }
    if ($days <= 60) {
        return 'text-amber-600 font-medium';
    }
    return 'text-gray-900';
}

/** Plain-language status of a date, used for compliance chips. */
function contractorExpiryNote($date)
{
    if (empty($date)) {
        return ['label' => 'Not recorded', 'class' => 'bg-slate-100 text-slate-600'];
    }
    $days = intdiv(strtotime($date) - time(), 86400);
    if ($days < 0) {
        return ['label' => 'Expired ' . date('M d, Y', strtotime($date)), 'class' => 'bg-red-100 text-red-700'];
    }
    if ($days <= 60) {
        return ['label' => $days . ' day' . ($days === 1 ? '' : 's') . ' left', 'class' => 'bg-amber-100 text-amber-700'];
    }
    return ['label' => 'Valid till ' . date('M d, Y', strtotime($date)), 'class' => 'bg-emerald-100 text-emerald-700'];
}

/** The current file for a slot, or null. Newest row wins if duplicates exist. */
function contractorSlotDocument($db, $contractorId, $slot)
{
    $stmt = $db->prepare("SELECT * FROM contractor_documents WHERE contractor_id = ? AND document_type = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$contractorId, $slot]);
    return $stmt->fetch() ?: null;
}

/** All current slot rows keyed by document_type, for form rendering. */
function contractorSlotDocuments($db, $contractorId)
{
    $docs = [];
    foreach (array_keys(contractorDocSlots()) as $slot) {
        $doc = contractorSlotDocument($db, $contractorId, $slot);
        if ($doc) {
            $docs[$slot] = $doc;
        }
    }
    return $docs;
}

/**
 * Replace a document slot. Passing $remove clears it; passing a $_FILES entry
 * stores it and discards the previous file. Returns an error message or null.
 */
function contractorSaveSlot($db, $contractorId, $slot, $file, $remove, $userId)
{
    if (!array_key_exists($slot, contractorDocSlots())) {
        return 'Unknown document slot.';
    }

    try {
        if ($remove) {
            $existing = contractorSlotDocument($db, $contractorId, $slot);
            if ($existing) {
                attachmentDeleteFile('contractor', $existing['file_path']);
                $db->prepare("DELETE FROM contractor_documents WHERE id = ? AND contractor_id = ?")
                   ->execute([$existing['id'], $contractorId]);
            }
            return null;
        }

        if (empty($file['name'])) {
            return null;
        }

        $stored = attachmentStoreFile('contractor', $contractorId, $slot, $file, contractorDocMaxBytes());
        $existing = contractorSlotDocument($db, $contractorId, $slot);

        if ($existing) {
            attachmentDeleteFile('contractor', $existing['file_path']);
            $stmt = $db->prepare("UPDATE contractor_documents
                SET document_name = ?, file_path = ?, file_size = ?, mime_type = ?, uploaded_by = ?, created_at = NOW()
                WHERE id = ? AND contractor_id = ?");
            $stmt->execute([
                $stored['original_name'], $stored['file_path'], $stored['file_size'],
                $stored['mime_type'], $userId, $existing['id'], $contractorId,
            ]);
            return null;
        }

        $stmt = $db->prepare("INSERT INTO contractor_documents
            (contractor_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $contractorId, $slot, $stored['original_name'], $stored['file_path'],
            $stored['file_size'], $stored['mime_type'], $userId,
        ]);
        return null;
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

/**
 * Replace or clear the profile photo (contractors.photo_path). Images only,
 * because this is rendered in an <img> tag. Returns an error message or null.
 */
function contractorSavePhoto($db, $contractorId, $file, $remove, $currentPath)
{
    try {
        if ($remove) {
            if ($currentPath) {
                attachmentDeleteFile('contractor', $currentPath);
            }
            $db->prepare("UPDATE contractors SET photo_path = NULL WHERE id = ?")->execute([$contractorId]);
            return null;
        }

        if (empty($file['name'])) {
            return null;
        }

        $stored = attachmentStoreFile(
            'contractor',
            $contractorId,
            'photo',
            $file,
            contractorPhotoMaxBytes(),
            ['image/jpeg', 'image/png', 'image/gif']
        );

        if ($currentPath) {
            attachmentDeleteFile('contractor', $currentPath);
        }
        $db->prepare("UPDATE contractors SET photo_path = ? WHERE id = ?")->execute([$stored['file_path'], $contractorId]);
        return null;
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}
