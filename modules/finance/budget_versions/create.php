<?php
$pageTitle = 'New Budget Version';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.edit')) { die('Access Denied'); }

$db = getDB();
$errors   = restoreFormErrors();
$activeFy = getActiveFinancialYear($db);
$budgets  = $db->query("SELECT b.*, fy.year_name FROM budgets b JOIN financial_years fy ON fy.id = b.financial_year_id WHERE b.status NOT IN ('closed','rejected') ORDER BY b.budget_code")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('create.php'); }
    $budgetId   = (int)($_POST['budget_id'] ?? 0);
    $versionType = $_POST['version_type'] ?? '';
    $note        = trim($_POST['notes'] ?? '');

    if (!in_array($versionType, ['revision','forecast','final'])) {
        $errors['version_type'] = 'Please choose a version type.';
    }

    $budget = $budgetId ? budget_find($db, $budgetId) : null;
    if (!$budget) { $errors['budget_id'] = 'Select a valid budget.'; }
    if ($budget && $budget['status'] === 'closed') { $errors['budget_id'] = 'Closed budgets cannot have new versions.'; }

    if (!$errors) {
        try {
            $db->beginTransaction();
            $versionNo = budget_next_version_no($db, $budgetId);
            $label = budget_version_label($db, $budgetId, $versionType);

            $db->prepare("INSERT INTO budget_versions
                (budget_id, version_no, version_type, version_label, status, notes, created_by, approved_by, created_at)
                VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, NOW())")
                ->execute([$budgetId, $versionNo, $versionType, $label, $note, $_SESSION['user_id'], $_SESSION['user_id']]);
            $newVersionId = (int)$db->lastInsertId();

            $lastVersion = $db->prepare("SELECT id FROM budget_versions WHERE budget_id = ? AND id <> ? ORDER BY id DESC LIMIT 1");
            $lastVersion->execute([$budgetId, $newVersionId]);
            $fromVersionId = (int)$lastVersion->fetchColumn();

            if ($fromVersionId) {
                copy_version_lines($db, $fromVersionId, $budgetId, $newVersionId);
                recompute_version_total($db, $newVersionId);
            }

            $db->commit();
            logAudit($_SESSION['user_id'], 'Created ' . $versionType . ' version ' . $versionNo . ' for budget: ' . $budget['budget_code'], 'budget');
            flash('success', 'New version created' . ($fromVersionId ? ' from previous version lines.' : '.'), 'success');
            redirect('../budget_versions/view.php?id=' . $newVersionId);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Version create error: ' . $e->getMessage());
            $errors['general'] = 'Could not create version: ' . $e->getMessage();
        }
    }

    if ($errors) { saveFormState($errors); redirect('create.php'); }
}

$budgetFyMap = [];
foreach ($budgets as $b) { $budgetFyMap[(int)$b['id']] = $b['year_name']; }
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">New Budget Version</h1>
        <p class="text-gray-600">Create a new version by cloning the latest version's lines</p>
    </div>
    <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Versions</a>
</div>

<?php if (isset($errors['general'])): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4"><?= sanitize($errors['general']) ?></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow w-full">
    <form method="POST" class="p-6 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Budget <span class="text-red-600">*</span></label>
            <select name="budget_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['budget_id']) ? 'border-red-500' : '' ?>">
                <option value="">Select budget...</option>
                <?php foreach ($budgets as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= old('budget_id') == $b['id'] ? 'selected' : '' ?>>
                    <?= sanitize($b['budget_code']) ?> — <?= sanitize($b['budget_name']) ?> (<?= sanitize($b['year_name']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['budget_id'])): ?><p class="text-red-500 text-xs mt-1"><?= sanitize($errors['budget_id']) ?></p><?php endif; ?>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Version Type <span class="text-red-600">*</span></label>
            <div class="grid grid-cols-3 gap-3">
                <?php foreach ([
                    'revision' => ['Revision', 'Adjust the current budget'],
                    'forecast' => ['Forecast', 'Projected outlook'],
                    'final'    => ['Final', 'Year-end finalization'],
                ] as $k => [$label, $desc]): ?>
                <label class="border rounded-lg p-3 cursor-pointer hover:border-blue-500 <?= old('version_type') === $k ? 'border-blue-500 bg-blue-50' : '' ?>">
                    <input type="radio" name="version_type" value="<?= $k ?>" class="mr-2" <?= old('version_type') === $k ? 'checked' : '' ?>>
                    <span class="font-medium text-gray-800"><?= $label ?></span>
                    <div class="text-xs text-gray-500 mt-1"><?= $desc ?></div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['version_type'])): ?><p class="text-red-500 text-xs mt-1"><?= sanitize($errors['version_type']) ?></p><?php endif; ?>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="3" placeholder="What changed in this version?" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('notes')) ?></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="index.php" class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-save mr-2"></i>Create Version</button>
        </div>
    </form>
</div>
<?php require_once '../../../includes/footer.php'; ?>