<?php
/**
 * Shared attachment storage engine for master-data document slots.
 *
 * Both the employee master and the contractor master keep one current scan per
 * document type plus free attachments, and the risky part — deciding what a
 * uploaded file really is, naming it, placing it and deleting it — must not be
 * maintained twice. This file owns that. Each module keeps its own slot list
 * and its own persistence layer, and passes an entity name here.
 *
 * Guarantees:
 *  - File type comes from the leading bytes, never from the client-supplied
 *    MIME or filename. The extension and MIME we store are derived from what
 *    the bytes actually are.
 *  - Generated names only (`<slot>_<id>_<random hex>.<ext>`), so nothing
 *    uploaded can escape its folder, collide, or be served as executable code.
 *  - Deletion is confined to the entity's own upload root.
 */

/** Storage coordinates per entity. */
function attachmentEntities()
{
    return [
        'employee' => [
            'root'  => 'uploads/employees',
            'table' => 'employee_documents',
            'fk'    => 'employee_id',
        ],
        'contractor' => [
            'root'  => 'uploads/contractors',
            'table' => 'contractor_documents',
            'fk'    => 'contractor_id',
        ],
    ];
}

function attachmentEntity($entity)
{
    $entities = attachmentEntities();
    if (!isset($entities[$entity])) {
        throw new RuntimeException('Unknown attachment entity: ' . $entity);
    }
    return $entities[$entity];
}

/** Byte signatures we accept, mapped to the extension and MIME we store. */
function attachmentFileSignatures()
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

/** Human labels for the MIME types, used in rejection messages. */
function attachmentMimeLabels()
{
    return [
        'image/jpeg' => 'JPG',
        'image/png'  => 'PNG',
        'image/gif'  => 'GIF',
        'application/pdf' => 'PDF',
        'application/msword' => 'DOC',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
    ];
}

/**
 * Absolute path to an entity's upload folder, created on demand.
 * The id is cast to int, so the caller-supplied value cannot inject a path.
 */
function attachmentUploadDir($entity, $entityId)
{
    $root = attachmentEntity($entity)['root'];
    $dir = __DIR__ . '/../' . $root . '/' . (int) $entityId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the upload folder. Check write permissions on ' . $root . '/.');
    }
    return realpath($dir);
}

/**
 * Validate a $_FILES entry and move it into the entity's folder.
 * $allowedMimes restricts which sniffed types this slot accepts.
 * Throws RuntimeException with text that is safe to show the user.
 */
function attachmentStoreFile($entity, $entityId, $slot, $file, $maxBytes, array $allowedMimes = [])
{
    $root = attachmentEntity($entity)['root'];

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

    $labels = attachmentMimeLabels();
    $head = file_get_contents($file['tmp_name'], false, null, 0, 8);
    $matched = null;
    foreach (attachmentFileSignatures() as $signature => $derived) {
        if (strncmp($head, $signature, strlen($signature)) === 0) {
            $matched = $derived;
            break;
        }
    }
    if (!$matched || ($allowedMimes && !in_array($matched[1], $allowedMimes, true))) {
        $message = 'Unrecognised file type. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX.';
        if ($allowedMimes) {
            $message = 'That file type is not allowed here. Allowed: '
                . implode(', ', array_map(fn($m) => $labels[$m] ?? $m, $allowedMimes)) . '.';
        }
        throw new RuntimeException($message);
    }

    [$ext, $mime] = $matched;
    $storedName = $slot . '_' . (int) $entityId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], attachmentUploadDir($entity, $entityId) . DIRECTORY_SEPARATOR . $storedName)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return [
        'file_path'     => $root . '/' . (int) $entityId . '/' . $storedName,
        'stored_name'   => $storedName,
        'original_name' => trim(substr($file['name'], 0, 255)),
        'file_size'     => (int) $file['size'],
        'mime_type'     => $mime,
    ];
}

/**
 * Delete a stored upload. Only paths inside the entity's own upload root are
 * ever removed, so a hand-edited or corrupted file_path cannot reach
 * application files or another tenant's uploads.
 */
function attachmentDeleteFile($entity, $filePath)
{
    $root = attachmentEntity($entity)['root'];
    $relative = ltrim(preg_replace('#^(\.\./)+#', '', (string) $filePath), '/');
    if (strpos($relative, '..') !== false || !str_starts_with($relative, $root . '/')) {
        return;
    }

    $absolute = __DIR__ . '/../' . $relative;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

/** Public URL for a stored path, tolerating legacy './' or '../' prefixes. */
function attachmentFileUrl($filePath)
{
    $path = ltrim(preg_replace('#^(\.\./)+#', '', (string) $filePath), '/');
    return url($path);
}

/** Best-effort MIME where no attachment row exists (e.g. a profile photo column). */
function attachmentMimeFromPath($filePath)
{
    $ext = strtolower(pathinfo((string) $filePath, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'pdf' => 'application/pdf', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

/** Renders "2.4 MB" / "310 KB" style metadata for a stored file. */
function attachmentSizeLabel($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '';
    }
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 0) . ' KB';
}

/**
 * Button that opens templates/document_viewer.php in place. Pass an empty
 * $label for icon-only toolbars.
 */
function attachmentViewButton($label, $filePath, $mime, $name, $class = 'text-blue-600 hover:text-blue-900 text-sm')
{
    return '<button type="button" onclick="attachmentViewerOpen(this)"'
        . ' data-url="' . sanitize(attachmentFileUrl($filePath)) . '"'
        . ' data-mime="' . sanitize($mime) . '"'
        . ' data-name="' . sanitize($name) . '"'
        . ' title="' . sanitize($name) . '"'
        . ' class="' . $class . '">'
        . '<i class="fas fa-eye' . ($label !== '' ? ' mr-1' : '') . '"></i>' . sanitize($label)
        . '</button>';
}
