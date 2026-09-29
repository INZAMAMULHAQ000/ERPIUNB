<?php

$pageTitle = 'Edit Budget';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.edit')) {
    die('Access Denied');
}

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

$budget = budget_find($db, $id);

if (!$budget) {
    flash('error', 'Budget not found.', 'error');
    redirect('index.php');
}

if (!in_array($budget['status'], ['draft', 'rejected'])) {

    flash(
        'error',
        'Budget is locked (' . str_replace('_', ' ', $budget['status']) . '). Create a new version or a revision to change amounts.',
        'error'
    );

    redirect('view.php?id=' . $id);
}

$version = budget_active_version($db, $id);

if (!$version) {
    flash('error', 'Budget has no version.', 'error');
    redirect('view.php?id=' . $id);
}

$departments = $db->query("
    SELECT *
    FROM departments
    WHERE status = 'active'
    ORDER BY department_name
")->fetchAll();

$projects = $db->query("
    SELECT *
    FROM projects
    WHERE status IN ('planning','active','on_hold')
    ORDER BY project_name
")->fetchAll();

$accounts = $db->query("
    SELECT
        coa.id,
        coa.account_code,
        coa.account_name
    FROM chart_of_accounts coa
    JOIN account_types at
        ON at.id = coa.type_id
    JOIN account_groups ag
        ON ag.id = at.group_id
    WHERE ag.nature = 'expense'
      AND coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $budget_name = trim($_POST['budget_name'] ?? '');

    $budget_type = $_POST['budget_type'] ?? '';

    $description = trim($_POST['description'] ?? '');

    $items = $_POST['items'] ?? [];


    /*
    |--------------------------------------------------------------------------
    | Main Budget Validation
    |--------------------------------------------------------------------------
    */

    if ($budget_name === '') {
        $errors[] = 'Budget name is required.';
    }

    if (!in_array(
        $budget_type,
        [
            'annual',
            'quarterly',
            'monthly',
            'project',
            'department'
        ]
    )) {
        $errors[] = 'Select a valid budget type.';
    }


    /*
    |--------------------------------------------------------------------------
    | Budget Lines
    |--------------------------------------------------------------------------
    */

    $lines = [];

    $seen = [];

    foreach ($items as $it) {

        $acct = (int)($it['account_id'] ?? 0);

        $amt = round(
            (float)($it['amount'] ?? 0),
            2
        );

        $dept = (
            (int)($it['department_id'] ?? 0)
        ) ?: null;

        $proj = (
            (int)($it['project_id'] ?? 0)
        ) ?: null;

        $month = (
            $it['month'] === ''
            || $it['month'] === null
        )
            ? null
            : (int)$it['month'];


        /*
        |--------------------------------------------------------------------------
        | Empty Line
        |--------------------------------------------------------------------------
        */

        if (!$acct && $amt == 0) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Account
        |--------------------------------------------------------------------------
        */

        if (!$acct) {

            $errors[] =
                'Every budget line must have an account.';

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        if ($amt <= 0) {

            $errors[] =
                'Every budget line amount must be greater than zero.';

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Check
        |--------------------------------------------------------------------------
        */

        $key =
            ($dept ?: 0)
            . '-'
            . ($proj ?: 0)
            . '-'
            . $acct
            . '-'
            . ($month ?: 0);


        if (isset($seen[$key])) {

            $errors[] =
                'Duplicate line (dept/project/account/month combination).';

            continue;
        }

        $seen[$key] = true;


        /*
        |--------------------------------------------------------------------------
        | Store Line
        |--------------------------------------------------------------------------
        */

        $lines[] = [

            'department_id' => $dept,

            'project_id' => $proj,

            'chart_of_accounts_id' => $acct,

            'month' => $month,

            'budget_amount' => $amt,

            'notes' => trim(
                $it['notes'] ?? ''
            )

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | At Least One Line
    |--------------------------------------------------------------------------
    */

    if (empty($lines)) {

        $errors[] =
            'Add at least one budget line with a valid amount.';
    }


    /*
    |--------------------------------------------------------------------------
    | Save Validation Errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'edit.php?id=' . $id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Budget
    |--------------------------------------------------------------------------
    */

    try {

        $db->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Update Budget Master
        |--------------------------------------------------------------------------
        */

        $db->prepare("
            UPDATE budgets
            SET
                budget_name = ?,
                budget_type = ?,
                description = ?
            WHERE id = ?
        ")->execute([
            $budget_name,
            $budget_type,
            $description,
            $id
        ]);


        /*
        |--------------------------------------------------------------------------
        | Delete Existing Lines
        |--------------------------------------------------------------------------
        */

        $db->prepare("
            DELETE FROM budget_lines
            WHERE budget_version_id = ?
        ")->execute([
            $version['id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Insert New Lines
        |--------------------------------------------------------------------------
        */

        $st = $db->prepare("
            INSERT INTO budget_lines
            (
                budget_id,
                budget_version_id,
                department_id,
                project_id,
                chart_of_accounts_id,
                month,
                budget_amount,
                notes
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


        foreach ($lines as $l) {

            $st->execute([
                $id,
                $version['id'],
                $l['department_id'],
                $l['project_id'],
                $l['chart_of_accounts_id'],
                $l['month'],
                $l['budget_amount'],
                $l['notes']
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Recompute Total
        |--------------------------------------------------------------------------
        */

        recompute_version_total(
            $db,
            $version['id']
        );


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $db->commit();


        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        logAudit(
            $_SESSION['user_id'],
            'Edited budget: ' . $budget['budget_code'],
            'budget'
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Budget updated.',
            'success'
        );


        redirect(
            'view.php?id=' . $id
        );
    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log(
            'Budget edit error: ' . $e->getMessage()
        );

        flash(
            'error',
            'Could not update budget: ' . $e->getMessage(),
            'error'
        );

        redirect(
            'edit.php?id=' . $id
        );
    }
}


/*
|--------------------------------------------------------------------------
| Load Existing Budget Lines
|--------------------------------------------------------------------------
*/

$st = $db->prepare("
    SELECT
        bl.*,
        acct.account_code,
        acct.account_name,
        d.department_name,
        p.project_name
    FROM budget_lines bl
    JOIN chart_of_accounts acct
        ON acct.id = bl.chart_of_accounts_id
    LEFT JOIN departments d
        ON d.id = bl.department_id
    LEFT JOIN projects p
        ON p.id = bl.project_id
    WHERE bl.budget_version_id = ?
    ORDER BY
        bl.chart_of_accounts_id,
        bl.month
");

$st->execute([
    $version['id']
]);

$lines = $st->fetchAll();

?>

<!-- ========================================================= -->
<!-- Page Header -->
<!-- ========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Edit Budget
        </h1>

        <p class="text-gray-600">

            <?= sanitize($budget['budget_code']) ?>

            —

            <?= sanitize($budget['budget_name']) ?>

        </p>

    </div>


    <div class="flex gap-2">

        <a
            href="view.php?id=<?= $id ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-eye mr-2"></i>
            View
        </a>


        <a
            href="matrix.php?id=<?= $id ?>"
            class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition">
            <i class="fas fa-th mr-2"></i>
            Allocation Matrix
        </a>

    </div>

</div>


<!-- ========================================================= -->
<!-- Server Errors -->
<!-- ========================================================= -->

<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p>
                <?= sanitize($e) ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!-- ========================================================= -->
<!-- Form -->
<!-- ========================================================= -->

<form
    method="POST"
    action="edit.php?id=<?= $id ?>"
    id="budgetForm">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!-- ===================================================== -->
    <!-- Budget Details -->
    <!-- ===================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Budget Details
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Budget Name -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Budget Name

                    <span class="text-red-500">*</span>

                </label>


                <input
                    type="text"
                    name="budget_name"
                    value="<?= sanitize(
                                old(
                                    'budget_name',
                                    $budget['budget_name']
                                )
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Financial Year -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Financial Year
                </label>


                <input
                    type="text"
                    value="<?= sanitize($budget['year_name']) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50"
                    disabled>

            </div>


            <!-- Budget Type -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Budget Type

                    <span class="text-red-500">*</span>

                </label>


                <select
                    name="budget_type"
                    id="budget_type"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <?php
                    foreach (
                        [
                            'annual' => 'Annual',
                            'quarterly' => 'Quarterly',
                            'monthly' => 'Monthly',
                            'project' => 'Project',
                            'department' => 'Department'
                        ] as $k => $v
                    ):
                    ?>

                        <option
                            value="<?= $k ?>"
                            <?= old(
                                'budget_type',
                                $budget['budget_type']
                            ) === $k
                                ? 'selected'
                                : '' ?>>
                            <?= $v ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- Description -->
        <div class="mt-4">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>


            <textarea
                name="description"
                rows="2"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(
                                                                                                old(
                                                                                                    'description',
                                                                                                    $budget['description'] ?? ''
                                                                                                )
                                                                                            ) ?></textarea>

        </div>

    </div>


    <!-- ===================================================== -->
    <!-- Budget Lines -->
    <!-- ===================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">


        <div class="flex justify-between items-center mb-4">

            <h3 class="text-lg font-semibold text-gray-800">
                Budget Lines
            </h3>


            <button
                type="button"
                onclick="addItem()"
                class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition">

                <i class="fas fa-plus mr-1"></i>

                Add Line

            </button>

        </div>


        <div class="overflow-x-auto">

            <table
                class="w-full"
                id="linesTable">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Department
                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Project
                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">

                            Account

                            <span class="text-red-500">*</span>

                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Month
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">

                            Amount

                            <span class="text-red-500">*</span>

                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Notes
                        </th>

                        <th class="px-3 py-2">
                        </th>

                    </tr>

                </thead>


                <tbody id="linesBody">

                    <?php if (empty($lines)): ?>

                        <!-- ================================================= -->
                        <!-- Empty Initial Row -->
                        <!-- ================================================= -->

                        <tr class="item-row">

                            <!-- Department -->
                            <td class="px-3 py-2">

                                <select
                                    name="items[0][department_id]"
                                    class="dept w-full px-2 py-2 border rounded text-sm">

                                    <option value="">
                                        —
                                    </option>

                                    <?php foreach ($departments as $d): ?>

                                        <option
                                            value="<?= (int)$d['id'] ?>">
                                            <?= sanitize(
                                                $d['department_name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- Project -->
                            <td class="px-3 py-2">

                                <select
                                    name="items[0][project_id]"
                                    class="proj w-full px-2 py-2 border rounded text-sm">

                                    <option value="">
                                        —
                                    </option>

                                    <?php foreach ($projects as $p): ?>

                                        <option
                                            value="<?= (int)$p['id'] ?>">
                                            <?= sanitize(
                                                $p['project_name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- Account -->
                            <td class="px-3 py-2">

                                <select
                                    name="items[0][account_id]"
                                    class="acct w-full px-2 py-2 border rounded text-sm">

                                    <option value="">
                                        Select account
                                    </option>

                                    <?php foreach ($accounts as $a): ?>

                                        <option
                                            value="<?= (int)$a['id'] ?>">
                                            <?= sanitize(
                                                $a['account_code']
                                            ) ?>

                                            —

                                            <?= sanitize(
                                                $a['account_name']
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- Month -->
                            <td class="px-3 py-2">

                                <select
                                    name="items[0][month]"
                                    class="mon w-full px-2 py-2 border rounded text-sm">

                                    <option value="">
                                        Annual
                                    </option>

                                    <?php for ($m = 1; $m <= 12; $m++): ?>

                                        <option value="<?= $m ?>">
                                            <?= budget_month_name($m) ?>
                                        </option>

                                    <?php endfor; ?>

                                </select>

                            </td>


                            <!-- Amount -->
                            <td class="px-3 py-2">

                                <input
                                    type="number"
                                    name="items[0][amount]"
                                    step="0.01"
                                    min="0.01"
                                    value=""
                                    placeholder="0.00"
                                    class="amt w-full px-2 py-2 border rounded text-right text-sm font-mono">

                            </td>


                            <!-- Notes -->
                            <td class="px-3 py-2">

                                <input
                                    type="text"
                                    name="items[0][notes]"
                                    class="w-full px-2 py-2 border rounded text-sm"
                                    placeholder="Optional">

                            </td>


                            <!-- Remove -->
                            <td class="px-3 py-2 text-center">

                                <button
                                    type="button"
                                    onclick="removeItem(this)"
                                    class="text-red-500 hover:text-red-700">

                                    <i class="fas fa-trash-alt"></i>

                                </button>

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($lines as $i => $l): ?>

                            <tr class="item-row">


                                <!-- Department -->
                                <td class="px-3 py-2">

                                    <select
                                        name="items[<?= $i ?>][department_id]"
                                        class="dept w-full px-2 py-2 border rounded text-sm">

                                        <option value="">
                                            —
                                        </option>

                                        <?php foreach ($departments as $d): ?>

                                            <option
                                                value="<?= (int)$d['id'] ?>"
                                                <?= (int)$l['department_id'] === (int)$d['id']
                                                    ? 'selected'
                                                    : '' ?>>
                                                <?= sanitize(
                                                    $d['department_name']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>


                                <!-- Project -->
                                <td class="px-3 py-2">

                                    <select
                                        name="items[<?= $i ?>][project_id]"
                                        class="proj w-full px-2 py-2 border rounded text-sm">

                                        <option value="">
                                            —
                                        </option>

                                        <?php foreach ($projects as $p): ?>

                                            <option
                                                value="<?= (int)$p['id'] ?>"
                                                <?= (int)$l['project_id'] === (int)$p['id']
                                                    ? 'selected'
                                                    : '' ?>>
                                                <?= sanitize(
                                                    $p['project_name']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>


                                <!-- Account -->
                                <td class="px-3 py-2">

                                    <select
                                        name="items[<?= $i ?>][account_id]"
                                        class="acct w-full px-2 py-2 border rounded text-sm">

                                        <option value="">
                                            Select account
                                        </option>

                                        <?php foreach ($accounts as $a): ?>

                                            <option
                                                value="<?= (int)$a['id'] ?>"
                                                <?= (int)$l['chart_of_accounts_id'] === (int)$a['id']
                                                    ? 'selected'
                                                    : '' ?>>
                                                <?= sanitize(
                                                    $a['account_code']
                                                ) ?>

                                                —

                                                <?= sanitize(
                                                    $a['account_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>


                                <!-- Month -->
                                <td class="px-3 py-2">

                                    <select
                                        name="items[<?= $i ?>][month]"
                                        class="mon w-full px-2 py-2 border rounded text-sm">

                                        <option value="">
                                            Annual
                                        </option>

                                        <?php for ($m = 1; $m <= 12; $m++): ?>

                                            <option
                                                value="<?= $m ?>"
                                                <?= (int)$l['month'] === $m
                                                    ? 'selected'
                                                    : '' ?>>
                                                <?= budget_month_name($m) ?>
                                            </option>

                                        <?php endfor; ?>

                                    </select>

                                </td>


                                <!-- Amount -->
                                <td class="px-3 py-2">

                                    <input
                                        type="number"
                                        name="items[<?= $i ?>][amount]"
                                        step="0.01"
                                        min="0.01"
                                        value="<?= htmlspecialchars(
                                                    (string)(float)$l['budget_amount'],
                                                    ENT_QUOTES
                                                ) ?>"
                                        class="amt w-full px-2 py-2 border rounded text-right text-sm font-mono">

                                </td>


                                <!-- Notes -->
                                <td class="px-3 py-2">

                                    <input
                                        type="text"
                                        name="items[<?= $i ?>][notes]"
                                        value="<?= sanitize(
                                                    $l['notes'] ?? ''
                                                ) ?>"
                                        class="w-full px-2 py-2 border rounded text-sm">

                                </td>


                                <!-- Remove -->
                                <td class="px-3 py-2 text-center">

                                    <button
                                        type="button"
                                        onclick="removeItem(this)"
                                        class="text-red-500 hover:text-red-700">

                                        <i class="fas fa-trash-alt"></i>

                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>

                </tbody>


                <!-- Total -->
                <tfoot class="bg-gray-100 font-semibold">

                    <tr>

                        <td
                            colspan="4"
                            class="px-3 py-2 text-sm text-gray-600 text-right">
                            Total
                        </td>

                        <td
                            class="px-3 py-2 text-right font-mono text-gray-900"
                            id="grandTotal">
                            0.00
                        </td>

                        <td colspan="2">
                        </td>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>


    <!-- ===================================================== -->
    <!-- Buttons -->
    <!-- ===================================================== -->

    <div class="flex justify-end gap-4 mb-8">

        <a
            href="view.php?id=<?= $id ?>"
            class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            Cancel
        </a>


        <button
            type="submit"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-save mr-2"></i>

            Save Changes

        </button>

    </div>

</form>


<!-- ========================================================= -->
<!-- jQuery Validation -->
<!-- ========================================================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<style>
    /*
|--------------------------------------------------------------------------
| Validation Error
|--------------------------------------------------------------------------
*/

    #budgetForm p.error {

        display: block !important;

        width: 100%;

        margin-top: 4px !important;

        margin-bottom: 0 !important;

        clear: both;

        color: #dc2626;

        font-size: 0.875rem;

        line-height: 1.25rem;
    }


    /*
|--------------------------------------------------------------------------
| Invalid Fields
|--------------------------------------------------------------------------
*/

    #budgetForm input.error,
    #budgetForm select.error,
    #budgetForm textarea.error {

        border-color: #ef4444 !important;
    }
</style>


<script>
    /*
|--------------------------------------------------------------------------
| Start Line Index
|--------------------------------------------------------------------------
*/

    let lineIndex = <?= count($lines) ?>;


    /*
    |--------------------------------------------------------------------------
    | Add Budget Line
    |--------------------------------------------------------------------------
    */

    function addItem() {

        const rows = document.querySelectorAll(
            '#linesBody .item-row'
        );

        if (!rows.length) {
            return;
        }

        const idx = lineIndex++;

        const proto = rows[0].cloneNode(true);


        /*
        |--------------------------------------------------------------------------
        | Update Input Names
        |--------------------------------------------------------------------------
        */

        proto.querySelectorAll(
            'input, select, textarea'
        ).forEach(function(el) {

            el.name = el.name.replace(
                /\[\d+\]/g,
                '[' + idx + ']'
            );


            /*
            |--------------------------------------------------------------------------
            | Reset Values
            |--------------------------------------------------------------------------
            */

            if (el.tagName === 'SELECT') {

                el.selectedIndex = 0;

            } else {

                el.value = '';

            }


            /*
            |--------------------------------------------------------------------------
            | Remove Validation Classes
            |--------------------------------------------------------------------------
            */

            el.classList.remove('error');

            el.classList.remove('valid');

        });


        /*
        |--------------------------------------------------------------------------
        | Remove Existing Error Messages
        |--------------------------------------------------------------------------
        */

        proto.querySelectorAll(
            'p.error'
        ).forEach(function(error) {

            error.remove();

        });


        /*
        |--------------------------------------------------------------------------
        | Add New Row
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('linesBody')
            .appendChild(proto);


        /*
        |--------------------------------------------------------------------------
        | Apply Validation
        |--------------------------------------------------------------------------
        */

        applyLineValidation();


        /*
        |--------------------------------------------------------------------------
        | Recalculate Total
        |--------------------------------------------------------------------------
        */

        calcTotal();
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Budget Line
    |--------------------------------------------------------------------------
    */

    function removeItem(button) {

        const rows = document.querySelectorAll(
            '#linesBody .item-row'
        );


        /*
        |--------------------------------------------------------------------------
        | Keep One Row
        |--------------------------------------------------------------------------
        */

        if (rows.length <= 1) {

            const row = button.closest('tr');


            row.querySelectorAll(
                'input, select, textarea'
            ).forEach(function(el) {

                el.value = '';

                el.classList.remove('error');

                el.classList.remove('valid');

            });


            row.querySelectorAll(
                'p.error'
            ).forEach(function(error) {

                error.remove();

            });


            calcTotal();

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Remove Row
        |--------------------------------------------------------------------------
        */

        button
            .closest('tr')
            .remove();


        /*
        |--------------------------------------------------------------------------
        | Recalculate
        |--------------------------------------------------------------------------
        */

        calcTotal();
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Total
    |--------------------------------------------------------------------------
    */

    function calcTotal() {

        let tot = 0;


        document
            .querySelectorAll(
                '#linesBody .item-row .amt'
            )
            .forEach(function(a) {

                tot += parseFloat(a.value) || 0;

            });


        document.getElementById(
            'grandTotal'
        ).textContent = tot.toLocaleString(
            'en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Apply Validation Rules To Budget Lines
    |--------------------------------------------------------------------------
    */

    function applyLineValidation() {

        $('#linesBody .item-row').each(function() {

            const row = $(this);

            const account = row.find('.acct');

            const amount = row.find('.amt');

            const department = row.find('.dept');

            const project = row.find('.proj');

            const month = row.find('.mon');

            const notes = row.find(
                'input[name*="[notes]"]'
            );


            /*
            |--------------------------------------------------------------------------
            | Account
            |--------------------------------------------------------------------------
            */

            if (account.length) {

                account.rules('add', {

                    required: true,

                    messages: {

                        required: 'Please select an account.'

                    }

                });

            }


            /*
            |--------------------------------------------------------------------------
            | Amount
            |--------------------------------------------------------------------------
            */

            if (amount.length) {

                amount.rules('add', {

                    required: true,

                    number: true,

                    min: 0.01,

                    messages: {

                        required: 'Amount is required.',

                        number: 'Please enter a valid amount.',

                        min: 'Amount must be greater than zero.'

                    }

                });

            }


            /*
            |--------------------------------------------------------------------------
            | Department
            |--------------------------------------------------------------------------
            */

            if (department.length) {

                department.rules('add', {

                    required: function() {

                        return $('#budget_type').val() ===
                            'department';

                    },

                    messages: {

                        required: 'Please select a department.'

                    }

                });

            }


            /*
            |--------------------------------------------------------------------------
            | Project
            |--------------------------------------------------------------------------
            */

            if (project.length) {

                project.rules('add', {

                    required: function() {

                        return $('#budget_type').val() ===
                            'project';

                    },

                    messages: {

                        required: 'Please select a project.'

                    }

                });

            }


            /*
            |--------------------------------------------------------------------------
            | Month
            |--------------------------------------------------------------------------
            */

            if (month.length) {

                month.rules('add', {

                    required: function() {

                        return $('#budget_type').val() ===
                            'monthly';

                    },

                    messages: {

                        required: 'Please select a month.'

                    }

                });

            }


            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            if (notes.length) {

                notes.rules('add', {

                    maxlength: 500,

                    messages: {

                        maxlength: 'Notes cannot exceed 500 characters.'

                    }

                });

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Document Ready
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Main Form Validation
        |--------------------------------------------------------------------------
        */

        $('#budgetForm').validate({

            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */

            errorElement: 'p',

            errorClass: 'error text-red-600 text-sm mt-1 block',


            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            |
            | Select error appears BELOW dropdown.
            |
            */

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    element.parent().append(error);

                } else {

                    element.after(error);

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Highlight Invalid Field
            |--------------------------------------------------------------------------
            */

            highlight: function(element) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass('border-gray-300');

            },


            /*
            |--------------------------------------------------------------------------
            | Remove Error Style
            |--------------------------------------------------------------------------
            */

            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500')
                    .addClass('border-gray-300');

            },


            /*
            |--------------------------------------------------------------------------
            | Main Form Rules
            |--------------------------------------------------------------------------
            */

            rules: {

                budget_name: {

                    required: true,

                    maxlength: 150

                },


                budget_type: {

                    required: true

                },


                description: {

                    maxlength: 500

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Main Form Messages
            |--------------------------------------------------------------------------
            */

            messages: {

                budget_name: {

                    required: 'Budget name is required.',

                    maxlength: 'Budget name cannot exceed 150 characters.'

                },


                budget_type: {

                    required: 'Please select a budget type.'

                },


                description: {

                    maxlength: 'Description cannot exceed 500 characters.'

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Validate All Fields
            |--------------------------------------------------------------------------
            */

            ignore: [],


            /*
            |--------------------------------------------------------------------------
            | Submit Handler
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                let valid = true;


                /*
                |--------------------------------------------------------------------------
                | Validate All Budget Lines
                |--------------------------------------------------------------------------
                */

                $('#linesBody .item-row').each(
                    function() {

                        const row = $(this);


                        /*
                        | Account
                        */

                        if (!row.find('.acct').valid()) {

                            valid = false;

                        }


                        /*
                        | Amount
                        */

                        if (!row.find('.amt').valid()) {

                            valid = false;

                        }


                        /*
                        | Department
                        */

                        if (!row.find('.dept').valid()) {

                            valid = false;

                        }


                        /*
                        | Project
                        */

                        if (!row.find('.proj').valid()) {

                            valid = false;

                        }


                        /*
                        | Month
                        */

                        if (!row.find('.mon').valid()) {

                            valid = false;

                        }


                        /*
                        | Notes
                        */

                        if (
                            !row
                            .find(
                                'input[name*="[notes]"]'
                            )
                            .valid()
                        ) {

                            valid = false;

                        }

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Stop If Validation Failed
                |--------------------------------------------------------------------------
                */

                if (!valid) {

                    return false;

                }


                /*
                |--------------------------------------------------------------------------
                | Submit
                |--------------------------------------------------------------------------
                */

                form.submit();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Apply Validation To Existing Lines
        |--------------------------------------------------------------------------
        */

        applyLineValidation();


        /*
        |--------------------------------------------------------------------------
        | Budget Type Change
        |--------------------------------------------------------------------------
        */

        $('#budget_type').on(
            'change',
            function() {


                /*
                | Department
                */

                $('#linesBody .dept').each(
                    function() {

                        $(this).valid();

                    }
                );


                /*
                | Project
                */

                $('#linesBody .proj').each(
                    function() {

                        $(this).valid();

                    }
                );


                /*
                | Month
                */

                $('#linesBody .mon').each(
                    function() {

                        $(this).valid();

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Initial Total
        |--------------------------------------------------------------------------
        */

        calcTotal();


        /*
        |--------------------------------------------------------------------------
        | Amount Change
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input',
            '.amt',
            function() {

                calcTotal();

            }
        );

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>