<?php
$pageTitle = 'Delete Person';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/people_onboarding.php';

if (!hasPermission('people_management.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM people WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$person = $stmt->fetch();

if (!$person) {
    flash('error', 'Person not found');
    to('modules/people_management/index.php');
}

/*
 * Deleting used to happen on GET, so any stray link, prefetch or embedded
 * request could remove a directory entry. The row is now removed only by a
 * POST carrying the session CSRF token; a GET shows the confirmation screen.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to('modules/people_management/index.php');
    }

    try {
        $db->beginTransaction();

        // Only the directory entry is removed. person_roles cascades with it;
        // the employee, customer and contractor records stay in their own
        // modules, where payroll, invoices and contracts still reference them.
        $db->prepare("DELETE FROM people WHERE id = ?")->execute([$id]);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', $e->getCode() === '23000'
            ? 'This person cannot be deleted because another record still points at them.'
            : 'Failed to delete person: ' . $e->getMessage());
        to('modules/people_management/index.php');
    }

    logAudit($_SESSION['user_id'], 'Deleted person: ' . $person['first_name'] . ' ' . (string) $person['last_name']
        . ' (' . $person['person_code'] . ')', 'people_management');
    peopleOnboardingForgetInput();
    flash('success', 'Person removed from the directory. Their module records were left untouched.');
    to('modules/people_management/index.php');
}

$roles = peopleLoad($db, $id);
$pending = $roles ? count(peoplePendingRoles($roles)) : 0;
?>
<div class="mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Person
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h2 class="text-xl font-semibold text-red-700 mb-4">Remove <?= sanitize(trim($person['first_name'] . ' ' . $person['last_name'])) ?> from the directory?</h2>

    <p class="text-sm text-gray-600 mb-4">
        This deletes the directory entry
        <span class="font-mono"><?= sanitize($person['person_code']) ?></span> and its person-type records.
        <?php if ($pending): ?>
            <strong><?= $pending ?> onboarding step(s) are still open</strong> — deleting now abandons them.
        <?php endif; ?>
    </p>

    <ul class="text-sm text-gray-600 list-disc list-inside space-y-1 mb-6">
        <li>Employee, customer and contractor records stay in their own modules and keep their history.</li>
        <li>Any login account attached to this person is not deleted.</li>
        <li>This cannot be undone from the application.</li>
    </ul>

    <form method="POST" class="flex items-center gap-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="bg-red-600 text-white px-5 py-2 rounded hover:bg-red-700 transition">
            <i class="fas fa-trash mr-2"></i>Remove from directory
        </button>
        <a href="view.php?id=<?= $id ?>" class="text-gray-600 px-5 py-2 border border-gray-200 rounded hover:text-gray-900">Cancel</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
