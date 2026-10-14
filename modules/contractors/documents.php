<?php
$pageTitle = 'Contractor Attachments';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';

if (!hasPermission('contractors.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM contractors WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash('nf', 'Contractor not found.', 'error');
    to('modules/contractors/index.php');
}

// Contract paperwork is selectable here too, so a signed agreement can be filed
// against its contract without leaving the attachments screen.
$attachmentTypes = contractorAllDocTypes();
$slotKeys = array_keys(contractorDocSlots());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('csrf', 'Your session expired. Please try again.', 'error');
        to('modules/contractors/documents.php?id=' . $id);
    }

    if (isset($_POST['delete_doc'])) {
        $docId = (int) $_POST['delete_doc'];
        $docStmt = $db->prepare("SELECT file_path, document_type FROM contractor_documents WHERE id = ? AND contractor_id = ?");
        $docStmt->execute([$docId, $id]);
        $doc = $docStmt->fetch();

        if (!$doc) {
            flash('doc', 'Document not found on this contractor.', 'error');
        } elseif (in_array($doc['document_type'], $slotKeys, true)) {
            flash('doc', 'Compliance scans are replaced or removed on the contractor form, not here.', 'error');
        } else {
            attachmentDeleteFile('contractor', $doc['file_path']);
            $db->prepare("DELETE FROM contractor_documents WHERE id = ? AND contractor_id = ?")->execute([$docId, $id]);
            logAudit($_SESSION['user_id'], 'Deleted contractor attachment #' . $docId, 'contractors');
            flash('doc', 'Attachment deleted.', 'success');
        }
        to('modules/contractors/documents.php?id=' . $id);
    }

    if (!empty($_FILES['document']['name'])) {
        $type = trim($_POST['document_type'] ?? 'other');
        if (!array_key_exists($type, $attachmentTypes)) {
            $type = 'other';
        }
        $label = trim($_POST['document_name'] ?? '') ?: $_FILES['document']['name'];
        $expiresOn = contractorNormaliseDate(trim($_POST['expires_on'] ?? ''));
        $contractId = (int) ($_POST['contract_id'] ?? 0) ?: null;
        $notes = substr(trim($_POST['notes'] ?? ''), 0, 255);

        if ($contractId !== null) {
            $ownsContract = $db->prepare("SELECT id FROM contractor_contracts WHERE id = ? AND contractor_id = ? AND deleted_at IS NULL");
            $ownsContract->execute([$contractId, $id]);
            if (!$ownsContract->fetch()) {
                $contractId = null;
            }
        }

        try {
            $stored = attachmentStoreFile('contractor', $id, $type, $_FILES['document'], contractorDocMaxBytes());
            $db->prepare("INSERT INTO contractor_documents
                (contractor_id, contract_id, document_type, document_name, file_path, file_size, mime_type, expires_on, notes, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([
                   $id, $contractId, $type, $stored['original_name'] ?: $label, $stored['file_path'],
                   $stored['file_size'], $stored['mime_type'], $expiresOn ?: null, $notes ?: null, $_SESSION['user_id'],
               ]);
            logAudit($_SESSION['user_id'], 'Uploaded contractor attachment: ' . $label, 'contractors');
            flash('doc', 'Attachment uploaded.', 'success');
        } catch (RuntimeException $e) {
            flash('doc', $e->getMessage(), 'error');
        }
        to('modules/contractors/documents.php?id=' . $id);
    }

    flash('doc', 'Nothing to upload — choose a file first.', 'error');
    to('modules/contractors/documents.php?id=' . $id);
}

$docs = $db->prepare("
    SELECT d.*, u.name AS uploader_name, cc.contract_number
      FROM contractor_documents d
      LEFT JOIN users u ON u.id = d.uploaded_by
      LEFT JOIN contractor_contracts cc ON cc.id = d.contract_id
     WHERE d.contractor_id = ? ORDER BY d.created_at DESC
");
$docs->execute([$id]);
$rows = $docs->fetchAll();

$contractOpts = [];
$cs = $db->prepare("SELECT id, contract_number, title FROM contractor_contracts WHERE contractor_id = ? AND deleted_at IS NULL ORDER BY id DESC");
$cs->execute([$id]);
foreach ($cs->fetchAll() as $row) {
    $contractOpts[$row['id']] = $row['contract_number'] . ' — ' . $row['title'];
}
$typeLabels = contractorAllDocTypes() + contractorDocSlots();
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Attachments</h1>
        <p class="text-gray-500 text-sm mt-1">
            <a href="<?= url('modules/contractors/view.php?id=' . $id) ?>" class="text-indigo-600 hover:underline">
                <?= htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name'])) ?>
            </a>
            <span class="font-mono ml-2 text-slate-400"><?= htmlspecialchars($c['contractor_code']) ?></span>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('modules/contractors/view.php?id=' . $id) ?>" class="text-sm text-slate-500 hover:text-slate-800">
            <i class="fas fa-arrow-left mr-1.5"></i>Profile
        </a>
        <a href="<?= url('modules/contractors/form.php?id=' . $id) ?>"
           class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
            <i class="fas fa-file-shield mr-2"></i>Compliance Scans
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                <i class="fas fa-upload text-indigo-500"></i> Upload Attachment
            </h2>
            <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="document_type">Type</label>
                    <select name="document_type" id="document_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                        <?php foreach ($attachmentTypes as $k => $l): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="document_name">Label</label>
                    <input type="text" name="document_name" id="document_name" placeholder="Defaults to the file name"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="expires_on">Expires On</label>
                    <input type="date" name="expires_on" id="expires_on" min="1990-01-01"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <p class="text-[11px] text-slate-400 mt-1">For certificates and insurance — drives the expiry chip</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="contract_id">Related Contract</label>
                    <select name="contract_id" id="contract_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                        <option value="">— None —</option>
                        <?php foreach ($contractOpts as $k => $l): ?>
                        <option value="<?= (int) $k ?>"><?= htmlspecialchars($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="notes">Note</label>
                    <input type="text" name="notes" id="notes" maxlength="255" placeholder="e.g. attested by MOFA"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="document">File</label>
                    <input type="file" name="document" id="document" required accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-[11px] text-slate-400 mt-1">PDF, JPG, PNG, GIF, DOC or DOCX &middot; max 5 MB</p>
                </div>
                <div class="md:col-span-2">
                    <button class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-upload mr-2"></i>Upload
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i class="fas fa-paperclip text-indigo-500"></i> All Documents
                <span class="ml-auto text-[11px] font-normal text-slate-400 normal-case"><?= count($rows) ?> file(s)</span>
            </h2>
            <?php if (!$rows): ?>
            <p class="text-sm text-slate-500 px-5 py-10 text-center">Nothing uploaded yet.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <?php foreach (['Document', 'Type', 'Size', 'Uploaded', 'Expiry', ''] as $h): ?>
                            <th class="px-4 py-2 text-left text-[10px] font-semibold text-slate-500 uppercase tracking-wide"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $doc):
                        $isSlot = in_array($doc['document_type'], $slotKeys, true);
                        $note = contractorExpiryNote($doc['expires_on'] ?? ''); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i class="fas <?= strpos($doc['mime_type'] ?? '', 'pdf') !== false ? 'fa-file-pdf text-red-400' : (strpos($doc['mime_type'] ?? '', 'image/') === 0 ? 'fa-file-image text-indigo-400' : 'fa-file-word text-blue-500') ?>"></i>
                                    <div class="min-w-0">
                                        <p class="text-slate-800 truncate max-w-[240px]"><?= htmlspecialchars($doc['document_name']) ?></p>
                                        <?php if ($doc['contract_number']): ?>
                                        <p class="text-[11px] text-slate-400">
                                            <a href="<?= url('modules/contractors/view.php?id=' . $id . '&contract=' . (int) $doc['contract_id']) ?>" class="hover:text-indigo-600">
                                                <?= htmlspecialchars($doc['contract_number']) ?>
                                            </a>
                                            <?php if ($doc['notes']): ?> &middot; <?= htmlspecialchars($doc['notes']) ?><?php endif; ?>
                                        </p>
                                        <?php elseif ($doc['notes']): ?>
                                        <p class="text-[11px] text-slate-400"><?= htmlspecialchars($doc['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <?= htmlspecialchars($typeLabels[$doc['document_type']] ?? $doc['document_type']) ?>
                                <?php if ($isSlot): ?><span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 ml-1">scan slot</span><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600"><?= attachmentSizeLabel($doc['file_size']) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                                <?php if ($doc['uploader_name']): ?><span class="block text-[11px] text-slate-400">by <?= htmlspecialchars($doc['uploader_name']) ?></span><?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if (!empty($doc['expires_on'])): ?>
                                <span class="text-[10px] px-2 py-0.5 rounded-full <?= $note['class'] ?>"><?= $note['label'] ?></span>
                                <?php else: ?>
                                <span class="text-[11px] text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <?= attachmentViewButton('', $doc['file_path'], $doc['mime_type'] ?? '', $doc['document_name'], 'text-slate-500 hover:text-indigo-600') ?>
                                    <a href="<?= attachmentFileUrl($doc['file_path']) ?>" class="text-slate-500 hover:text-indigo-600" title="Download"><i class="fas fa-download"></i></a>
                                    <?php if (!$isSlot): ?>
                                    <form method="post" onsubmit="return confirm('Delete this attachment?')">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="delete_doc" value="<?= (int) $doc['id'] ?>">
                                        <button class="text-slate-500 hover:text-red-600" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-3">Compliance Scan Slots</h3>
            <p class="text-[11px] text-slate-500 mb-4">
                One current file each, paired with the document number on the master. Managed on the contractor form.
            </p>
            <ul class="space-y-2">
                <?php foreach (contractorDocSlots() as $slot => $label):
                    $doc = null;
                    foreach ($rows as $r) { if ($r['document_type'] === $slot) { $doc = $r; break; } }
                    $expiryColumn = contractorSlotExpiryColumn($slot);
                    $note = contractorExpiryNote($expiryColumn ? ($c[$expiryColumn] ?? '') : ''); ?>
                    <li class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-50 last:border-0">
                        <span class="text-sm text-slate-700"><?= htmlspecialchars($label) ?></span>
                        <?php if ($doc): ?>
                        <span class="flex items-center gap-2">
                            <span class="text-[10px] px-2 py-0.5 rounded-full <?= $note['class'] ?>"><?= $note['label'] ?></span>
                            <?= attachmentViewButton('', $doc['file_path'], $doc['mime_type'] ?? '', $label . ' — ' . $doc['document_name'], 'text-slate-500 hover:text-indigo-600') ?>
                        </span>
                        <?php else: ?>
                        <span class="text-[11px] text-slate-400">not on record</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-3">Housekeeping</h3>
            <ul class="text-[11px] text-slate-500 space-y-2">
                <li><i class="fas fa-circle-check text-emerald-500 mr-1.5"></i>File type is read from the bytes, not the name or browser claim.</li>
                <li><i class="fas fa-circle-check text-emerald-500 mr-1.5"></i>Stored names are generated; the original name is kept as the label.</li>
                <li><i class="fas fa-circle-check text-emerald-500 mr-1.5"></i>Deleting a row also deletes the file, inside <span class="font-mono">uploads/contractors/</span> only.</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../templates/document_viewer.php'; ?>
<?php require_once '../../includes/footer.php'; ?>
