<?php
$pageTitle = 'Tax Registrations';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Toggle status ----
if (isset($_GET['toggle']) && hasPermission('tax.edit')) {
    $stmt = $db->prepare("SELECT status FROM tax_registrations WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    if ($stmt->fetch()) {
        $db->prepare("UPDATE tax_registrations SET status = IF(status='active','inactive','active'), updated_at = NOW() WHERE id = ?")->execute([(int)$_GET['toggle']]);
        logAudit($_SESSION['user_id'], 'Toggled tax registration #' . (int)$_GET['toggle'], 'tax');
        flash('success', 'Tax registration status updated.');
    }
    redirect('index.php');
}

// ---- Delete ----
if (isset($_GET['delete']) && hasPermission('tax.edit')) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tax_registrations WHERE id = ?")->execute([$id]);
    logAudit($_SESSION['user_id'], 'Deleted tax registration #' . $id, 'tax');
    flash('success', 'Tax registration deleted.');
    redirect('index.php');
}

$registrations = $db->query("
    SELECT r.*, t.name AS type_name, t.code AS type_code
    FROM tax_registrations r
    JOIN tax_types t ON t.id = r.tax_type_id
    ORDER BY r.status='inactive', r.company_name
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Registrations</h1>
        <p class="text-gray-600">Tax IDs your company holds across jurisdictions — VAT, GST, sales tax and more</p>
    </div>
    <?php if (hasPermission('tax.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Registration
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('registrations') ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Country</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Registration Date</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Effective</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($registrations)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No tax registrations yet. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($registrations as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($r['company_name']) ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($r['tax_number']) ?></span>
                </td>
                <td class="px-6 py-4">
                    <span class="text-gray-900"><?= sanitize($r['type_name']) ?></span>
                    <span class="ml-1 px-2 py-1 text-xs font-mono bg-blue-100 text-blue-700 rounded"><?= sanitize($r['type_code']) ?></span>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($r['country'] ?: '—') ?></td>
                <td class="px-6 py-4 text-center text-gray-600 whitespace-nowrap"><?= sanitize($r['registration_date'] ?: '—') ?></td>
                <td class="px-6 py-4 text-center text-gray-600 whitespace-nowrap">
                    <?php
                    $from = $r['effective_from'] ?? '';
                    $to   = $r['effective_to'] ?? '';
                    if ($from !== '' && $to !== '') {
                        echo sanitize($from) . ' &rarr; ' . sanitize($to);
                    } elseif ($from !== '') {
                        echo 'From ' . sanitize($from);
                    } elseif ($to !== '') {
                        echo 'Until ' . sanitize($to);
                    } else {
                        echo '—';
                    }
                    ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $r['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <?php if (hasPermission('tax.edit')): ?>
                    <a href="edit.php?id=<?= $r['id'] ?>" class="text-gray-500 hover:text-blue-600 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="index.php?toggle=<?= $r['id'] ?>" class="text-gray-500 hover:text-amber-600 mr-2" title="<?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-toggle-<?= $r['status'] === 'active' ? 'on' : 'off' ?>"></i>
                    </a>
                    <a href="index.php?delete=<?= $r['id'] ?>" class="text-gray-500 hover:text-red-600" title="Delete" onclick="return confirm('Delete this tax registration?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>