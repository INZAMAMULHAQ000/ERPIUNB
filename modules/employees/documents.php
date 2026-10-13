<?php
$pageTitle = 'Employee Documents';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once __DIR__ . '/document_service.php';

if (!hasPermission('employees.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT employee_code, first_name, last_name, photo_path FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash('error', 'Employee not found');
    to('modules/employees/index.php');
}

// The passport / visa / Emirates ID scans are managed on the employee form, so
// this screen only handles the remaining attachment types.
$slotKeys = array_keys(employeeDocSlots());
$otherDocTypes = [
    'resume'      => 'Resume',
    'id_proof'    => 'ID Proof',
    'certificate' => 'Certificate',
    'contract'    => 'Contract',
    'other'       => 'Other',
];
$docTypeLabels = $otherDocTypes + employeeDocSlots();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
        to("modules/employees/documents.php?id=$id");
    }

    if (isset($_POST['delete_doc'])) {
        $docId = (int) $_POST['delete_doc'];
        $docStmt = $db->prepare("SELECT file_path, document_type FROM employee_documents WHERE id = ? AND employee_id = ?");
        $docStmt->execute([$docId, $id]);
        $doc = $docStmt->fetch();

        if ($doc && !in_array($doc['document_type'], $slotKeys, true)) {
            employeeDeleteFile($doc['file_path']);
            $db->prepare("DELETE FROM employee_documents WHERE id = ? AND employee_id = ?")->execute([$docId, $id]);
            logAudit($_SESSION['user_id'], 'delete_document', 'employees');
            flash('success', 'Document deleted');
        } else {
            flash('error', 'Document not found on this employee');
        }

        to("modules/employees/documents.php?id=$id");
    }

    if (!empty($_FILES['document']['name'])) {
        $documentType = $_POST['document_type'] ?? 'other';
        if (!array_key_exists($documentType, $otherDocTypes)) {
            $documentType = 'other';
        }
        $documentName = trim($_POST['document_name'] ?? '') ?: $_FILES['document']['name'];

        try {
            $stored = employeeStoreFile($id, $documentType, $_FILES['document'], employeeDocMaxBytes());
            $insert = $db->prepare("INSERT INTO employee_documents
                (employee_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $id, $documentType, $stored['original_name'] ?: $documentName, $stored['file_path'],
                $stored['file_size'], $stored['mime_type'], $_SESSION['user_id'],
            ]);

            logAudit($_SESSION['user_id'], 'upload_document', 'employees');
            flash('success', 'Document uploaded successfully');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        to("modules/employees/documents.php?id=$id");
    }

    flash('error', 'Nothing to upload — choose a file first.');
    to("modules/employees/documents.php?id=$id");
}

$documents = $db->prepare("SELECT d.*, u.name AS uploader_name
    FROM employee_documents d
    LEFT JOIN users u ON u.id = d.uploaded_by
    WHERE d.employee_id = ? ORDER BY d.created_at DESC");
$documents->execute([$id]);
$docs = $documents->fetchAll();

$slotDocs = employeeSlotDocuments($db, $id);
?>

<div class="mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Profile
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Upload Document</h2>
            <p class="text-gray-600 mb-4">
                Employee: <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                (<?= sanitize($emp['employee_code']) ?>)
            </p>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                    <select name="document_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($otherDocTypes as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Name</label>
                    <input type="text" name="document_name" placeholder="Defaults to the file name"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                    <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Max 5MB. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX</p>
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Attachments</h2>

            <?php if ($docs): ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Document</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($docs as $doc): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <i class="fas <?= strpos($doc['mime_type'] ?? '', 'pdf') !== false ? 'fa-file-pdf text-red-500' : (strpos($doc['mime_type'] ?? '', 'image/') === 0 ? 'fa-file-image text-blue-500' : 'fa-file-word text-blue-700') ?> mr-2"></i>
                                <span class="text-sm font-medium text-gray-900 truncate max-w-xs"><?= sanitize($doc['document_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= sanitize($docTypeLabels[$doc['document_type']] ?? $doc['document_type']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= employeeFileSizeLabel($doc['file_size']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                            <?php if (!empty($doc['uploader_name'])): ?>
                            <span class="block text-xs text-gray-500">by <?= sanitize($doc['uploader_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <?= employeeDocViewButton('', $doc['file_path'], $doc['mime_type'] ?? '', $doc['document_name'], 'text-blue-600 hover:text-blue-900') ?>
                                <a href="<?= employeeFileUrl($doc['file_path']) ?>" class="text-blue-600 hover:text-blue-900" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php if (!in_array($doc['document_type'], $slotKeys, true)): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this document?')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="delete_doc" value="<?= (int) $doc['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-gray-500">No documents uploaded yet</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Identification Scans</h3>
            <p class="text-xs text-gray-500 mb-4">
                Managed on the employee form so each stays paired with its document number.
                <a href="edit.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900 block mt-1">
                    <i class="fas fa-edit mr-1"></i>Update on employee form
                </a>
            </p>

            <ul class="space-y-3">
                <?php foreach (employeeDocSlots() as $slot => $label): ?>
                <?php $doc = $slotDocs[$slot] ?? null; ?>
                <li class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <span class="text-sm text-gray-700"><?= $label ?></span>
                    <?php if ($doc): ?>
                    <?= employeeDocViewButton('View', $doc['file_path'], $doc['mime_type'] ?? '', $label . ' — ' . $doc['document_name']) ?>
                    <?php else: ?>
                    <span class="text-xs text-gray-400">Not on record</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>

                <li class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <span class="text-sm text-gray-700">Profile Photo</span>
                    <?php if (!empty($emp['photo_path'])): ?>
                    <?= employeeDocViewButton('View', $emp['photo_path'], employeeMimeFromPath($emp['photo_path']), 'Profile Photo — ' . $emp['first_name'] . ' ' . $emp['last_name']) ?>
                    <?php else: ?>
                    <span class="text-xs text-gray-400">Not on record</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/document_viewer.php'; ?>

<?php require_once '../../includes/footer.php'; ?>

