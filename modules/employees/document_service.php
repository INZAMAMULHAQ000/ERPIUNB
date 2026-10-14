<?php
/**
 * Employee master identification block: the passport / visa / Emirates ID
 * fields, their validation, and the storage of their scanned copies.
 *
 * The three identification scans are one "current file" slot each, persisted
 * in employee_documents against a fixed document_type. Re-uploading a slot
 * replaces the previous file; the profile photo is a plain column on employees
 * because it is master data, not a document.
 *
 * Byte-level file handling, naming, URL building and deletion live in
 * helpers/attachment_storage.php under the 'employee' entity, so the same
 * guarantees apply to every module that stores uploads.
 */

require_once __DIR__ . '/../../helpers/attachment_storage.php';

/** Text inputs of the identification + contract block, in display order. */
function employeeIdFields()
{
    return [
        'nationality',
        'passport_number',
        'passport_expiry',
        'visa_type',
        'visa_number',
        'visa_expiry',
        'emirates_id_number',
        'emirates_id_expiry',
        'contract_number',
        'contract_start_date',
        'contract_end_date',
    ];
}

function employeeVisaTypes()
{
    return [
        'employment'        => 'Employment Visa',
        'entry_permit'      => 'Entry Permit',
        'visit'             => 'Visit Visa',
        'tourist'           => 'Tourist Visa',
        'student'           => 'Student Visa',
        'investor'          => 'Investor Visa',
        'golden'            => 'Golden Visa',
        'family_sponsorship'=> 'Family Sponsorship',
        'other'             => 'Other',
    ];
}

/**
 * Trim and canonicalise the identification inputs: document numbers upper-case
 * with no separators, Emirates ID digits only, dates as Y-m-d or empty.
 */
function employeeIdInput($post)
{
    $data = [];
    foreach (employeeIdFields() as $field) {
        $data[$field] = trim((string) ($post[$field] ?? ''));
    }

    $data['passport_number']    = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['passport_number']));
    $data['visa_number']        = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['visa_number']));
    $data['emirates_id_number'] = preg_replace('/[^0-9]/', '', $data['emirates_id_number']);
    $data['contract_number']    = strtoupper(preg_replace('/[^A-Za-z0-9\-\/]/', '', $data['contract_number']));

    foreach (['passport_expiry', 'visa_expiry', 'emirates_id_expiry', 'contract_start_date', 'contract_end_date'] as $field) {
        $data[$field] = employeeNormaliseDate($data[$field]);
    }

    if ($data['visa_type'] !== '' && !array_key_exists($data['visa_type'], employeeVisaTypes())) {
        $data['visa_type'] = '';
    }

    return $data;
}

/** 'Y-m-d' in, normalised 'Y-m-d' or '' out. Anything unparseable becomes ''. */
function employeeNormaliseDate($value)
{
    if ($value === '') {
        return '';
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
}

function employeeIdErrors($data, $rawInput)
{
    $errors = [];

    if ($data['nationality'] !== '' && !preg_match('/^[A-Za-z ]+$/', $data['nationality'])) {
        $errors['nationality'] = 'Nationality may contain letters and spaces only';
    }
    foreach (['passport_number' => 'Passport Number', 'visa_number' => 'Visa Number'] as $field => $label) {
        if (strlen($data[$field]) > 50) {
            $errors[$field] = $label . ' must not exceed 50 characters';
        }
    }
    if (strlen($data['emirates_id_number']) > 20) {
        $errors['emirates_id_number'] = 'Emirates ID Number must not exceed 20 digits';
    }
    // A date the canonicaliser could not parse was not a real calendar date.
    foreach ([
        'passport_expiry'     => 'Passport Expiry',
        'visa_expiry'         => 'Visa Expiry',
        'emirates_id_expiry'  => 'Emirates ID Expiry',
        'contract_start_date' => 'Contract Start Date',
        'contract_end_date'   => 'Contract End Date',
    ] as $field => $label) {
        if (trim((string) ($rawInput[$field] ?? '')) !== '' && $data[$field] === '') {
            $errors[$field] = $label . ' must be a valid date';
        }
    }

    if (strlen($data['contract_number']) > 50) {
        $errors['contract_number'] = 'Contract Number must not exceed 50 characters';
    }
    if ($data['contract_start_date'] !== '' && $data['contract_end_date'] !== ''
        && strtotime($data['contract_end_date']) < strtotime($data['contract_start_date'])) {
        $errors['contract_end_date'] = 'Contract End Date must be on or after the Contract Start Date';
    }
    // joining_date is posted on the employee form; blank on any other caller.
    $joining = employeeNormaliseDate(trim((string) ($rawInput['joining_date'] ?? '')));
    if ($joining !== '' && $data['contract_start_date'] !== '' && $data['contract_start_date'] < $joining) {
        $errors['contract_start_date'] = 'Contract cannot start before the joining date';
    }

    return $errors;
}

/** Emirates ID is 15 digits shown as 784-1234-5678901-2; stored digits only. */
function employeeFormatEmiratesId($digits)
{
    $digits = (string) $digits;
    if (strlen($digits) !== 15) {
        return $digits;
    }
    return substr($digits, 0, 3) . '-' . substr($digits, 3, 4) . '-'
        . substr($digits, 7, 7) . '-' . substr($digits, 14, 1);
}

/** Tailwind classes for an expiry date: red once past, amber within 60 days. */
function employeeExpiryClass($date)
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

function employeeDocSlots()
{
    return [
        'passport_copy'            => 'Passport Copy',
        'visa_copy'                => 'Visa Copy',
        'emirates_id_copy'         => 'Emirates ID Copy',
        'employment_contract_copy' => 'Employment Contract Copy',
    ];
}

/** Chip styling for an expiry date, matching employeeExpiryClass thresholds. */
function employeeExpiryChipClass($date)
{
    if (empty($date)) {
        return 'bg-gray-100 text-gray-600';
    }
    $days = intdiv(strtotime($date) - time(), 86400);
    if ($days < 0) {
        return 'bg-red-100 text-red-700';
    }
    if ($days <= 60) {
        return 'bg-amber-100 text-amber-700';
    }
    return 'bg-green-100 text-green-700';
}

/** Which master column holds each slot's expiry, so a scan can be colour-coded. */
function employeeSlotExpiryColumn($slot)
{
    return [
        'passport_copy'            => 'passport_expiry',
        'visa_copy'                => 'visa_expiry',
        'emirates_id_copy'         => 'emirates_id_expiry',
        'employment_contract_copy' => 'contract_end_date',
    ][$slot] ?? null;
}

function employeeDocMaxBytes()
{
    return 5 * 1024 * 1024;
}

function employeePhotoMaxBytes()
{
    return 2 * 1024 * 1024;
}

/** The current file for a slot, or null. Newest row wins if duplicates exist. */
function employeeSlotDocument($db, $employeeId, $slot)
{
    $stmt = $db->prepare("SELECT * FROM employee_documents WHERE employee_id = ? AND document_type = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$employeeId, $slot]);
    return $stmt->fetch() ?: null;
}

/** All current slot rows keyed by document_type, for form rendering. */
function employeeSlotDocuments($db, $employeeId)
{
    $docs = [];
    foreach (array_keys(employeeDocSlots()) as $slot) {
        $doc = employeeSlotDocument($db, $employeeId, $slot);
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
function employeeSaveSlot($db, $employeeId, $slot, $file, $remove, $userId)
{
    if (!array_key_exists($slot, employeeDocSlots())) {
        return 'Unknown document slot.';
    }

    try {
        if ($remove) {
            $existing = employeeSlotDocument($db, $employeeId, $slot);
            if ($existing) {
                attachmentDeleteFile('employee', $existing['file_path']);
                $stmt = $db->prepare("DELETE FROM employee_documents WHERE id = ? AND employee_id = ?");
                $stmt->execute([$existing['id'], $employeeId]);
            }
            return null;
        }

        if (empty($file['name'])) {
            return null;
        }

        $stored = attachmentStoreFile('employee', $employeeId, $slot, $file, employeeDocMaxBytes());
        $existing = employeeSlotDocument($db, $employeeId, $slot);

        if ($existing) {
            attachmentDeleteFile('employee', $existing['file_path']);
            $stmt = $db->prepare("UPDATE employee_documents
                SET document_name = ?, file_path = ?, file_size = ?, mime_type = ?, uploaded_by = ?, created_at = NOW()
                WHERE id = ? AND employee_id = ?");
            $stmt->execute([
                $stored['original_name'], $stored['file_path'], $stored['file_size'],
                $stored['mime_type'], $userId, $existing['id'], $employeeId,
            ]);
            return null;
        }

        $stmt = $db->prepare("INSERT INTO employee_documents
            (employee_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $employeeId, $slot, $stored['original_name'], $stored['file_path'],
            $stored['file_size'], $stored['mime_type'], $userId,
        ]);
        return null;
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

/**
 * Replace or clear the profile photo (employees.photo_path). Images only,
 * because this is rendered in an <img> tag. Returns an error message or null.
 */
function employeeSavePhoto($db, $employeeId, $file, $remove, $currentPath)
{
    try {
        if ($remove) {
            if ($currentPath) {
                attachmentDeleteFile('employee', $currentPath);
            }
            $db->prepare("UPDATE employees SET photo_path = NULL WHERE id = ?")->execute([$employeeId]);
            return null;
        }

        if (empty($file['name'])) {
            return null;
        }

        $stored = attachmentStoreFile(
            'employee',
            $employeeId,
            'photo',
            $file,
            employeePhotoMaxBytes(),
            ['image/jpeg', 'image/png', 'image/gif']
        );

        if ($currentPath) {
            attachmentDeleteFile('employee', $currentPath);
        }
        $db->prepare("UPDATE employees SET photo_path = ? WHERE id = ?")->execute([$stored['file_path'], $employeeId]);
        return null;
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}
