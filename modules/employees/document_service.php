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
 * Files land in <repo>/uploads/employees/<employee_id>/ and the DB stores the
 * root-relative web path ("uploads/employees/7/passport_copy_7_ab12cd.pdf").
 * Names are generated from the sniffed file type, never from the client
 * filename, so nothing uploaded can escape the folder or be executed.
 */

/** Text inputs of the identification block, in display order. */
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

    foreach (['passport_expiry', 'visa_expiry', 'emirates_id_expiry'] as $field) {
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
    foreach (['passport_expiry' => 'Passport Expiry', 'visa_expiry' => 'Visa Expiry', 'emirates_id_expiry' => 'Emirates ID Expiry'] as $field => $label) {
        if (trim((string) ($rawInput[$field] ?? '')) !== '' && $data[$field] === '') {
            $errors[$field] = $label . ' must be a valid date';
        }
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
        'passport_copy'    => 'Passport Copy',
        'visa_copy'        => 'Visa Copy',
        'emirates_id_copy' => 'Emirates ID Copy',
    ];
}

/** Byte signatures we accept, mapped to the extension and MIME we store. */
function employeeFileSignatures()
{
    return [
        "\xFF\xD8\xFF"      => ['jpg',  'image/jpeg'],
        "\x89PNG"           => ['png',  'image/png'],
        'GIF8'              => ['gif',  'image/gif'],
        '%PDF'              => ['pdf',  'application/pdf'],
        "\xD0\xCF\x11\xE0"  => ['doc',  'application/msword'],
        "PK\x03\x04"        => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];
}

function employeeDocMaxBytes()
{
    return 5 * 1024 * 1024;
}

function employeePhotoMaxBytes()
{
    return 2 * 1024 * 1024;
}

/**
 * Absolute path to an employee's upload folder, created on demand.
 * Caller must treat the return value as trusted — the id is cast to int.
 */
function employeeUploadDir($employeeId)
{
    $dir = __DIR__ . '/../../uploads/employees/' . (int) $employeeId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the upload folder. Check write permissions on uploads/.');
    }
    return realpath($dir);
}

/**
 * Validate an entry from $_FILES and move it into the employee's folder.
 * $allowedMimes restricts which sniffed types the slot accepts. Returns
 * ['file_path' => root-relative, 'stored_name' =>, 'original_name' =>,
 * 'file_size' =>, 'mime_type' =>]. Throws RuntimeException with a message that
 * is safe to show the user.
 */
function employeeStoreFile($employeeId, $slot, $file, $maxBytes, array $allowedMimes = [])
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file was received. Choose a file and submit again.');
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That file is larger than the server upload limit.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The upload failed. Please try again.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File is larger than ' . round($maxBytes / 1048576, 1) . ' MB.');
    }

    $head = file_get_contents($file['tmp_name'], false, null, 0, 8);
    $matched = null;
    foreach (employeeFileSignatures() as $signature => $derived) {
        if (strncmp($head, $signature, strlen($signature)) === 0) {
            $matched = $derived;
            break;
        }
    }
    if (!$matched || ($allowedMimes && !in_array($matched[1], $allowedMimes, true))) {
        $labels = [
            'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/gif' => 'GIF',
            'application/pdf' => 'PDF', 'application/msword' => 'DOC',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
        ];
        $message = 'Unrecognised file type. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX.';
        if ($allowedMimes) {
            $message = 'That file type is not allowed here. Allowed: '
                . implode(', ', array_map(fn($m) => $labels[$m] ?? $m, $allowedMimes)) . '.';
        }
        throw new RuntimeException($message);
    }

    [$ext, $mime] = $matched;
    $storedName = $slot . '_' . (int) $employeeId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], employeeUploadDir($employeeId) . DIRECTORY_SEPARATOR . $storedName)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return [
        'file_path'     => 'uploads/employees/' . (int) $employeeId . '/' . $storedName,
        'stored_name'   => $storedName,
        'original_name' => trim(substr($file['name'], 0, 255)),
        'file_size'     => (int) $file['size'],
        'mime_type'     => $mime,
    ];
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
 * Delete a stored upload. Only paths inside the employee upload area are ever
 * removed, so a hand-edited or corrupted file_path cannot reach other files.
 */
function employeeDeleteFile($filePath)
{
    $filePath = ltrim(preg_replace('#^(\.\./)+#', '', (string) $filePath), '/');
    if (strpos($filePath, '..') !== false || !str_starts_with($filePath, 'uploads/employees/')) {
        return;
    }

    $absolute = __DIR__ . '/../../' . $filePath;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
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
                employeeDeleteFile($existing['file_path']);
                $stmt = $db->prepare("DELETE FROM employee_documents WHERE id = ? AND employee_id = ?");
                $stmt->execute([$existing['id'], $employeeId]);
            }
            return null;
        }

        if (empty($file['name'])) {
            return null;
        }

        $stored = employeeStoreFile($employeeId, $slot, $file, employeeDocMaxBytes());
        $existing = employeeSlotDocument($db, $employeeId, $slot);

        if ($existing) {
            employeeDeleteFile($existing['file_path']);
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
                employeeDeleteFile($currentPath);
            }
            $db->prepare("UPDATE employees SET photo_path = NULL WHERE id = ?")->execute([$employeeId]);
            return null;
        }

        if (empty($file['name'])) {
            return null;
        }

        $stored = employeeStoreFile(
            $employeeId,
            'photo',
            $file,
            employeePhotoMaxBytes(),
            ['image/jpeg', 'image/png', 'image/gif']
        );

        if ($currentPath) {
            employeeDeleteFile($currentPath);
        }
        $db->prepare("UPDATE employees SET photo_path = ? WHERE id = ?")->execute([$stored['file_path'], $employeeId]);
        return null;
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

/** Public URL for a stored path, tolerating both new and legacy DB values. */
function employeeFileUrl($filePath)
{
    $path = ltrim(preg_replace('#^(\.\./)+#', '', (string) $filePath), '/');
    return url($path);
}

/** Best-effort MIME for a stored file; used where no employee_documents row exists. */
function employeeMimeFromPath($filePath)
{
    $ext = strtolower(pathinfo((string) $filePath, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

/** Renders "2.4 MB" style metadata for a stored file. */
function employeeFileSizeLabel($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '';
    }
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 0) . ' KB';
}

/**
 * Button that opens document_viewer.php in place. Pair it with an empty $label
 * for icon-only toolbars.
 */
function employeeDocViewButton($label, $filePath, $mime, $name, $class = 'text-blue-600 hover:text-blue-900 text-sm')
{
    return '<button type="button" onclick="employeeDocViewerOpen(this)"'
        . ' data-url="' . sanitize(employeeFileUrl($filePath)) . '"'
        . ' data-mime="' . sanitize($mime) . '"'
        . ' data-name="' . sanitize($name) . '"'
        . ' title="' . sanitize($name) . '"'
        . ' class="' . $class . '">'
        . '<i class="fas fa-eye' . ($label !== '' ? ' mr-1' : '') . '"></i>' . sanitize($label)
        . '</button>';
}
