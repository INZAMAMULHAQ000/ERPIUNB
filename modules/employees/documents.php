<?php
$pageTitle = 'Employee Documents';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('employees.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT employee_code, first_name, last_name FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash('error', 'Employee not found');
    to('modules/employees/index.php');
}

$docTypes = ['resume' => 'Resume', 'id_proof' => 'ID Proof', 'passport' => 'Passport', 'certificate' => 'Certificate', 'contract' => 'Contract', 'other' => 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['document']['name'])) {
    $document_type = $_POST['document_type'] ?? 'other';
    $document_name = $_POST['document_name'] ?? $_FILES['document']['name'];
    
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['document']['type'], $allowedTypes)) {
        flash('error', 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX');
    } elseif ($_FILES['document']['size'] > $maxSize) {
        flash('error', 'File size exceeds 5MB limit');
    } else {
        $uploadDir = 'uploads/employees/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['document']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            $stmt = $db->prepare("INSERT INTO employee_documents (employee_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id, $document_type, $document_name, $targetPath,
                $_FILES['document']['size'], $_FILES['document']['type'],
                $_SESSION['user_id']
            ]);
            
            logAudit($_SESSION['user_id'], 'upload_document', 'employees');
            flash('success', 'Document uploaded successfully');
            to("modules/employees/documents.php?id=$id");
        } else {
            flash('error', 'Failed to upload file');
        }
    }
}

if (isset($_GET['delete_doc'])) {
    $docId = (int) $_GET['delete_doc'];
    $docStmt = $db->prepare("SELECT file_path FROM employee_documents WHERE id = ? AND employee_id = ?");
    $docStmt->execute([$docId, $id]);
    $doc = $docStmt->fetch();
    
    if ($doc && file_exists($doc['file_path'])) {
        unlink($doc['file_path']);
    }
    
    $delStmt = $db->prepare("DELETE FROM employee_documents WHERE id = ? AND employee_id = ?");
    $delStmt->execute([$docId, $id]);
    
    flash('success', 'Document deleted');
    to("modules/employees/documents.php?id=$id");
}

$documents = $db->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC");
$documents->execute([$id]);
$docs = $documents->fetchAll();
?>

<div class="mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Profile
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Upload Document</h2>
        <p class="text-gray-600 mb-4">Employee: <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= sanitize($emp['employee_code']) ?>)</p>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                <select name="document_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($docTypes as $key => $label): ?>
                    <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Document Name</label>
                <input type="text" name="document_name" placeholder="Optional custom name"
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
        <h2 class="text-xl font-semibold mb-4">Uploaded Documents</h2>
        
        <?php if ($docs): ?>
        <ul class="space-y-3">
            <?php foreach ($docs as $doc): ?>
            <li class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium"><?= sanitize($doc['document_name']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= $docTypes[$doc['document_type']] ?? $doc['document_type'] ?> | 
                            <?= number_format($doc['file_size'] / 1024, 1) ?> KB | 
                            <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="../../<?= sanitize($doc['file_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="View/Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <a href="?id=<?= $id ?>&delete_doc=<?= $doc['id'] ?>" class="text-red-600 hover:text-red-900" title="Delete"
                       onclick="return confirm('Delete this document?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-gray-500">No documents uploaded yet</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>