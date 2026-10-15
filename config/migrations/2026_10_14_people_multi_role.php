<?php
/**
 * People multi-role migration — lets one person carry more than one business
 * role (Employee / Customer / Contractor) without duplicating the person.
 *
 * `people` already holds four independent nullable links (user_id,
 * employee_id, customer_id, contractor_id), so the table could always point at
 * several records for one human. What assumed a single role was the
 * `people.category` enum plus the create/edit screens. This migration adds the
 * missing set semantics without touching either:
 *
 *  - `person_roles` is the authoritative set of selected roles AND the
 *    onboarding progress record (status pending/complete per role), so the
 *    multi-step workflow survives a refresh, a cancel or a failed step without
 *    any temporary frontend state.
 *  - `people.category` is kept and reinterpreted as the PRIMARY role — the
 *    first selected one. Every existing read of it (list filter, statistics,
 *    badges, the edit screen's mirror conditions) keeps working unchanged.
 *  - `record_id` has no foreign key because its parent depends on `role`
 *    (employees / clients / contractors). The invariant "status = complete
 *    implies people.<link column> = record_id" is enforced transactionally in
 *    helpers/people_onboarding.php instead.
 *  - Backfill gives every existing person exactly one row, derived from
 *    `category` and the populated link columns, so historical single-role
 *    records keep their meaning and appear complete rather than pending.
 *
 * `people.email` is only indexed today, so the directory's "one person per
 * email" rule lives entirely in application code and two concurrent creations
 * can fork a person. A UNIQUE index closes that, but only if no duplicates
 * already exist — the guard below aborts with a message rather than silently
 * deleting or merging anyone's data. email stays nullable, so several NULL
 * rows (people recorded without an address) remain legal.
 *
 * Additive and idempotent — safe to re-run.
 *   php config/migrations/2026_10_14_people_multi_role.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;

function pr_hasTable($db, $table)
{
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $s->execute([$DB, $table]);
    return (bool) $s->fetchColumn();
}

function pr_hasIndex($db, $table, $name)
{
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}

function pr_hasConstraint($db, $name)
{
    global $DB;
    // InnoDB constraint names are unique per schema, not per table.
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND constraint_name = ?");
    $s->execute([$DB, $name]);
    return (bool) $s->fetchColumn();
}

echo "== People multi-role migration ==\n";

/* ------------------------------------------------------------------ */
echo "\n-- person_roles: the selected-role set and onboarding progress --\n";

if (!pr_hasTable($db, 'person_roles')) {
    $db->exec("
    CREATE TABLE `person_roles` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `person_id` int(10) unsigned NOT NULL,
      `role` enum('employee','customer','contractor') NOT NULL,
      `status` enum('pending','complete','declined') NOT NULL DEFAULT 'pending',
      `record_id` int(10) unsigned DEFAULT NULL,
      `completed_at` timestamp NULL DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_person_role` (`person_id`,`role`),
      KEY `idx_person_roles_progress` (`person_id`,`status`),
      KEY `idx_person_roles_role` (`role`),
      KEY `fk_pr_person` (`person_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  + person_roles\n";
} else {
    echo "  = person_roles exists\n";
}

// 'declined' is how a step is given up on without deleting the person or
// pretending the workflow finished. Tables created before that state existed
// are widened in place; existing rows are untouched.
$statusDefinition = $db->query("SELECT COLUMN_TYPE FROM information_schema.columns
                                WHERE table_schema = '" . DB_NAME . "' AND table_name = 'person_roles' AND column_name = 'status'")
                       ->fetchColumn();
if ($statusDefinition !== false && strpos((string) $statusDefinition, 'declined') === false) {
    $db->exec("ALTER TABLE `person_roles` MODIFY `status` enum('pending','complete','declined') NOT NULL DEFAULT 'pending'");
    echo "  ~ person_roles.status widened to include 'declined'\n";
}

if (!pr_hasConstraint($db, 'fk_person_roles_person')) {
    $db->exec("ALTER TABLE `person_roles` ADD CONSTRAINT `fk_person_roles_person`
               FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE CASCADE");
    echo "  fk person_roles.person_id -> people.id (CASCADE)\n";
}

/* ------------------------------------------------------------------ */
echo "\n-- backfill existing people --\n";

// Link column per role, in the canonical onboarding order.
$roleLinks = [
    'employee'   => 'employee_id',
    'customer'   => 'customer_id',
    'contractor' => 'contractor_id',
];

$people = $db->query("SELECT id, category, employee_id, customer_id, contractor_id FROM people")->fetchAll();
$insert = $db->prepare("INSERT IGNORE INTO person_roles (person_id, role, status, record_id, completed_at)
                        VALUES (?, ?, ?, ?, ?)");
$created = 0;

foreach ($people as $person) {
    foreach ($roleLinks as $role => $column) {
        $linkedId = $person[$column] !== null ? (int) $person[$column] : null;

        // A person is only ever asked to complete a role they were classified
        // as, or one that already has a record behind it. Anything else has no
        // row at all — historical people are not given roles they never had.
        if ($linkedId === null && $person['category'] !== $role) {
            continue;
        }

        $status = $linkedId !== null ? 'complete' : 'pending';
        $insert->execute([$person['id'], $role, $status, $linkedId, $linkedId !== null ? date('Y-m-d H:i:s') : null]);
        $created += $insert->rowCount();
    }
}
echo "  $created role row(s) backfilled across " . count($people) . " person(s)\n";

/* ------------------------------------------------------------------ */
echo "\n-- people.email uniqueness --\n";

if (pr_hasIndex($db, 'people', 'uq_people_email')) {
    echo "  = uq_people_email exists\n";
} else {
    $dupes = $db->query("SELECT email, COUNT(*) c FROM people
                         WHERE email IS NOT NULL AND email <> ''
                         GROUP BY email HAVING c > 1")->fetchAll();
    if ($dupes) {
        echo "  ! SKIPPED — the live data already breaks this rule. Resolve these by hand, then re-run:\n";
        foreach ($dupes as $d) {
            echo "      " . $d['c'] . " people share " . $d['email'] . "\n";
        }
    } else {
        $db->exec("ALTER TABLE `people` ADD UNIQUE KEY `uq_people_email` (`email`)");
        echo "  + uq_people_email\n";
    }
}

echo "\nDone.\n";
