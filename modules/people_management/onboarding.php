<?php
$pageTitle = 'Person Onboarding';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/people_onboarding.php';

if (!hasPermission('people_management.view')) {
    die('Access Denied');
}

$db = getDB();
$person = peopleLoad($db, (int) ($_GET['person_id'] ?? 0));

if (!$person) {
    peopleOnboardingForgetInput();
    peopleOnboardingClearReturn();
    flash('error', 'Person not found.');
    to('modules/people_management/index.php');
}

$personId = (int) $person['id'];
$roleDefs = peopleRoleDefinitions();

/*
| -------------------------------------------------------------------------
| Actions
| -------------------------------------------------------------------------
| Every mutation here goes through the shared helper, so the rules about who
| may be linked, unlinked or declined are the same rules the module forms use.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        to(peopleOnboardingUrl($personId));
    }

    $action = $_POST['action'] ?? '';
    $role = $_POST['role'] ?? '';
    $def = peopleRoleDefinition($role);

    if ($def === null && $action !== 'add_roles') {
        flash('error', 'Unknown person type.');
        to(peopleOnboardingUrl($personId));
    }

    switch ($action) {

        case 'attach':
            if (!hasPermission($def['create_perm']) && !hasPermission('people_management.edit')) {
                flash('error', 'You do not have permission to link ' . strtolower($def['label']) . ' records.');
                break;
            }
            $result = peopleOnboardingAttachExisting($db, $person, $role, (int) ($_POST['record_id'] ?? 0));
            flash($result['ok'] ? 'wiz_ok' : 'wiz_err', $result['message'], $result['ok'] ? 'success' : 'error');
            if (!$result['ok']) {
                peopleOnboardingClearReturn();
            }
            break;

        case 'decline':
            if (!hasPermission('people_management.edit')) {
                flash('error', 'You do not have permission to decline onboarding steps.');
                break;
            }
            if (peopleOnboardingDecline($db, $person, $role, trim((string) ($_POST['reason'] ?? '')))) {
                flash('wiz_info', $def['label'] . ' step declined. Completed steps and the person record are untouched — reopen this step any time.', 'info');
            } else {
                flash('wiz_err', 'That step could not be declined because it is no longer pending.', 'error');
            }
            break;

        case 'reopen':
            if (!hasPermission('people_management.edit')) {
                flash('error', 'You do not have permission to reopen onboarding steps.');
                break;
            }
            if (peopleOnboardingReopen($db, $person, $role)) {
                flash('wiz_info', $def['label'] . ' step reopened.', 'info');
            }
            break;

        case 'add_roles':
            if (!hasPermission('people_management.edit')) {
                flash('error', 'You do not have permission to add person types.');
                break;
            }
            $added = peopleNormaliseRoles($_POST['roles'] ?? []);
            if (!$added) {
                flash('wiz_err', 'Choose at least one person type to add.', 'error');
                break;
            }
            $count = 0;
            foreach ($added as $newRole) {
                if (peopleRoleAdd($db, $person, $newRole)) {
                    $count++;
                }
            }
            flash($count ? 'wiz_ok' : 'wiz_err',
                $count ? $count . ' person type added to this same person — no new record was created.'
                       : 'Those person types are already recorded for this person.',
                $count ? 'success' : 'info');
            break;
    }

    to(peopleOnboardingUrl($personId));
}

/*
| -------------------------------------------------------------------------
| State
| -------------------------------------------------------------------------
*/
$pending  = peoplePendingRoles($person);
$declined = peopleDeclinedRoles($person);
$total    = count($person['roles']);
$done     = 0;
foreach ($person['roles'] as $row) {
    if ($row['status'] === 'complete') {
        $done++;
    }
}

// A step has just saved. Once nothing is pending the whole onboarding is
// finished, so the user goes back to the directory instead of being left on a
// module screen — and never before the last selected role is done.
$justLinked = peopleOnboardingReturnPending($personId);
peopleOnboardingClearReturn();
if ($justLinked && !$pending && !$declined) {
    peopleOnboardingForgetInput();
    flash('success', $person['first_name'] . ' ' . (string) $person['last_name'] . ' is fully onboarded with '
        . $done . ' person type(s) on a single record.');
    to('modules/people_management/index.php?highlight=' . $personId);
}

// Codes of the records each completed role points at, for the "open" links.
$records = [];
foreach ($roleDefs as $role => $def) {
    if (($person['roles'][$role] ?? ['status' => null])['status'] === 'complete' && !empty($person[$def['link_column']])) {
        $stmt = $db->prepare("SELECT `{$def['code_column']}` AS code FROM `{$def['table']}` WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $person[$def['link_column']]]);
        $records[$role] = $stmt->fetch();
    }
}

$suggested = peopleNormaliseRoles(explode(',', (string) ($_GET['suggest'] ?? '')));
$csrf = csrfToken();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="index.php" class="text-blue-600 hover:text-blue-900 inline-block mb-1">
            <i class="fas fa-arrow-left mr-1"></i>Back to People
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Onboarding — <?= sanitize(trim($person['first_name'] . ' ' . $person['last_name'])) ?></h1>
        <p class="text-gray-600 text-sm">
            <span class="font-mono"><?= sanitize($person['person_code']) ?></span>
            &middot; <?= sanitize($person['email'] ?: 'no email') ?>
            <?php if ($person['phone']): ?>&middot; <?= sanitize($person['phone']) ?><?php endif; ?>
        </p>
    </div>
    <a href="view.php?id=<?= $personId ?>" class="bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200">
        <i class="fas fa-id-card mr-1"></i>Person Profile
    </a>
</div>

<?php if ($suggested): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 mb-6 text-sm">
    <i class="fas fa-circle-info mr-1"></i>
    Requested person types: <?= htmlspecialchars(implode(', ', array_map(fn($r) => $roleDefs[$r]['label'], $suggested)), ENT_QUOTES) ?>.
    Add any that are missing below — they attach to <strong>this</strong> person, never a second record.
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex justify-between items-end mb-2">
        <h3 class="text-lg font-semibold text-gray-800">Progress</h3>
        <span class="text-sm text-gray-600"><?= $done ?> of <?= $total ?> person type<?= $total === 1 ? '' : 's' ?> completed</span>
    </div>
    <div class="w-full bg-gray-200 rounded h-2 mb-4">
        <div class="bg-green-600 h-2 rounded" style="width: <?= $total ? round($done / $total * 100) : 0 ?>%"></div>
    </div>

    <div class="space-y-3">
        <?php foreach ($roleDefs as $role => $def): ?>
        <?php
        $state = $person['roles'][$role] ?? null;
        if ($state === null) {
            continue;
        }
        $canCreate = hasPermission($def['create_perm']);
        $existing = $state['status'] === 'pending' ? peopleFindExistingRecord($db, $person, $role) : null;
        $border = $state['status'] === 'complete' ? 'border-green-300' : ($state['status'] === 'declined' ? 'border-gray-200' : 'border-amber-300');
        ?>
        <div class="border rounded-lg p-4 <?= $border ?>">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start">
                    <div class="mt-1 mr-3">
                        <?php if ($state['status'] === 'complete'): ?>
                            <i class="fas fa-circle-check text-green-600 text-xl"></i>
                        <?php elseif ($state['status'] === 'declined'): ?>
                            <i class="fas fa-circle-minus text-gray-400 text-xl"></i>
                        <?php else: ?>
                            <i class="fas fa-clock text-amber-500 text-xl"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="font-medium text-gray-800">
                            <?= $def['label'] ?>
                            <span class="text-xs font-normal text-gray-500">in <?= $def['module'] ?></span>
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5"><?= $def['hint'] ?></div>

                        <?php if ($state['status'] === 'complete'): ?>
                            <div class="text-sm mt-1">
                                <?php if (!empty($records[$role])): ?>
                                    <span class="font-mono"><?= sanitize($records[$role]['code']) ?></span>
                                    <a class="text-blue-600 hover:text-blue-900 ml-2"
                                       href="<?= url($def['view_url'] . (int) $person[$def['link_column']]) ?>">
                                        Open record <i class="fas fa-external-link-alt text-xs"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">Linked record #<?= (int) $person[$def['link_column']] ?> no longer exists.</span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($state['status'] === 'declined'): ?>
                            <div class="text-xs text-gray-500 mt-1">Declined — this person type is on hold, the rest of the workflow is unaffected.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($state['status'] === 'pending'): ?>
                        <?php if ($canCreate): ?>
                            <a href="<?= url($def['create_url'] . '?person_id=' . $personId) ?>"
                               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm whitespace-nowrap">
                                <i class="fas fa-arrow-right mr-1"></i>Complete <?= $def['label'] ?> details
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-500 border border-gray-200 rounded px-3 py-2"
                                  title="Your account lacks the <?= $def['create_perm'] ?> permission.">
                                <i class="fas fa-lock mr-1"></i>Needs <?= $def['create_perm'] ?>
                            </span>
                        <?php endif; ?>
                        <?php if (hasPermission('people_management.edit')): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="decline">
                            <input type="hidden" name="role" value="<?= $role ?>">
                            <button type="submit" class="text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded px-3 py-2">
                                Skip for now
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php elseif ($state['status'] === 'declined' && hasPermission('people_management.edit')): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="reopen">
                            <input type="hidden" name="role" value="<?= $role ?>">
                            <button type="submit" class="text-xs text-blue-600 hover:text-blue-900 border border-blue-200 rounded px-3 py-2">
                                Reopen step
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($existing && empty($existing['claimed_by'])): ?>
            <div class="mt-3 pt-3 border-t border-dashed">
                <div class="text-xs text-amber-800 bg-amber-50 rounded px-3 py-2 flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        A <?= strtolower($def['label']) ?> record already exists for this email
                        (<span class="font-mono"><?= sanitize($existing['code']) ?></span>).
                        Link it to avoid a duplicate — creating another one will be rejected.
                    </span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="attach">
                        <input type="hidden" name="role" value="<?= $role ?>">
                        <input type="hidden" name="record_id" value="<?= (int) $existing['id'] ?>">
                        <button type="submit" class="bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700 ml-2">
                            Link existing
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif ($existing): ?>
            <div class="mt-3 pt-3 border-t border-dashed text-xs text-gray-500">
                <i class="fas fa-link mr-1"></i>The <?= strtolower($def['label']) ?> record for this email
                (<span class="font-mono"><?= sanitize($existing['code']) ?></span>) already belongs to person
                <?= sanitize($existing['claimed_by']) ?>.
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (!$total): ?>
        <div class="text-sm text-gray-500 border rounded-lg p-4">
            No person types are recorded for this person. Add one below to start onboarding.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$missing = [];
foreach ($roleDefs as $role => $def) {
    if (!isset($person['roles'][$role])) {
        $missing[$role] = $def;
    }
}
?>
<?php if ($missing && hasPermission('people_management.edit')): ?>
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-1">Add a person type</h3>
    <p class="text-xs text-gray-500 mb-3">Adds a role to <?= sanitize($person['person_code']) ?> and opens its module. Never creates a second person.</p>
    <form method="POST" class="flex flex-wrap items-center gap-4">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="add_roles">
        <?php foreach ($missing as $role => $def): ?>
        <label class="inline-flex items-center text-sm">
            <input type="checkbox" name="roles[]" value="<?= $role ?>" class="rounded text-blue-600">
            <span class="ml-2"><?= $def['label'] ?></span>
        </label>
        <?php endforeach; ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            Add and continue
        </button>
    </form>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
