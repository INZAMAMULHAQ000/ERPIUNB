<?php

$pageTitle = 'New Budget Revision';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.revise')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

$budgets = $db->query("
    SELECT *
    FROM budgets
    WHERE status IN ('active')
    ORDER BY budget_code
")->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
    }

    $budgetId     = (int)($_POST['budget_id'] ?? 0);
    $versionId    = (int)($_POST['version_id'] ?? 0);
    $lineId       = (int)($_POST['line_id'] ?? 0);
    $revisionType = $_POST['revision_type'] ?? '';
    $newAmount    = (float)($_POST['new_amount'] ?? 0);
    $reason       = trim($_POST['reason'] ?? '');


    if (!$budgetId) {
        $errors['budget_id'] = 'Select a budget.';
    }

    if (!$versionId) {
        $errors['version_id'] = 'Select a version.';
    }

    if (!$lineId) {
        $errors['line_id'] = 'Select a line.';
    }

    if (!in_array($revisionType, ['increase', 'decrease', 'adjustment'])) {
        $errors['revision_type'] = 'Select a revision type.';
    }

    if ($newAmount < 0) {
        $errors['new_amount'] = 'Amount cannot be negative.';
    }

    if ($reason === '') {
        $errors['reason'] = 'Reason is required.';
    }


    $version = $line = null;


    if ($budgetId && $versionId && !$errors) {

        $v = $db->prepare("
            SELECT *
            FROM budget_versions
            WHERE id = ?
              AND budget_id = ?
        ");

        $v->execute([
            $versionId,
            $budgetId
        ]);

        $version = $v->fetch();


        if (!$version) {

            $errors['version_id'] =
                'Version not found for this budget.';
        } else if ($version['status'] !== 'draft') {

            $errors['version_id'] =
                'Only draft versions can be revised.';
        }
    }


    if ($lineId && !$errors) {

        $l = $db->prepare("
            SELECT *
            FROM budget_lines
            WHERE id = ?
              AND budget_version_id = ?
        ");

        $l->execute([
            $lineId,
            $versionId
        ]);

        $line = $l->fetch();


        if (!$line) {

            $errors['line_id'] =
                'Line not found in this version.';
        }
    }


    if (!$errors) {

        $oldAmount = (float)$line['budget_amount'];

        $change = $newAmount - $oldAmount;


        if (
            $revisionType === 'increase' &&
            $change <= 0
        ) {
            $errors['new_amount'] =
                'Increase requires a higher amount.';
        }


        if (
            $revisionType === 'decrease' &&
            $change >= 0
        ) {
            $errors['new_amount'] =
                'Decrease requires a lower amount.';
        }
    }


    if (!$errors) {

        try {

            $db->beginTransaction();


            $db->prepare("
                UPDATE budget_lines
                SET budget_amount = ?
                WHERE id = ?
            ")->execute([
                $newAmount,
                $lineId
            ]);


            recompute_version_total(
                $db,
                $versionId
            );


            $change = $newAmount - $oldAmount;


            $db->prepare("
                INSERT INTO budget_revisions
                (
                    budget_id,
                    budget_version_id,
                    line_id,
                    revision_type,
                    old_amount,
                    new_amount,
                    change_amount,
                    reason,
                    created_by,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $budgetId,
                $versionId,
                $lineId,
                $revisionType,
                $oldAmount,
                $newAmount,
                $change,
                $reason,
                $_SESSION['user_id']
            ]);


            $db->commit();


            logAudit(
                $_SESSION['user_id'],
                "Revision {$revisionType} on line $lineId: "
                    . formatMoney($oldAmount)
                    . " → "
                    . formatMoney($newAmount),
                'budget'
            );


            flash(
                'success',
                'Revision applied.',
                'success'
            );


            redirect(
                'view.php?id=' . $db->lastInsertId()
            );
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }


            error_log(
                'Revision create error: '
                    . $e->getMessage()
            );


            $errors['general'] =
                'Could not apply revision: '
                . $e->getMessage();
        }
    }


    if ($errors) {
        saveFormState($errors);
        redirect('create.php');
    }
}

?>

<style>
    #revisionForm p.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        clear: both;
    }

    #revisionForm input.error,
    #revisionForm select.error,
    #revisionForm textarea.error {
        border-color: #ef4444 !important;
    }

    #revisionForm .server-error {
        display: block;
    }
</style>


<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            New Budget Revision
        </h1>

        <p class="text-gray-600">
            Adjust a budget line — increase, decrease, or adjust
        </p>

    </div>


    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
    </a>

</div>


<?php if (isset($errors['general'])): ?>

    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
        <?= sanitize($errors['general']) ?>
    </div>

<?php endif; ?>


<div class="bg-white rounded-lg shadow w-full">

    <form
        method="POST"
        class="p-6 space-y-5"
        id="revisionForm"
        novalidate>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <!-- Budget -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Budget

                <span class="text-red-600">*</span>

            </label>


            <select
                name="budget_id"
                id="budget_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['budget_id']) ? 'border-red-500' : '' ?>">

                <option value="">
                    Select budget...
                </option>


                <?php foreach ($budgets as $b): ?>

                    <option
                        value="<?= (int)$b['id'] ?>"
                        <?= old('budget_id') == $b['id'] ? 'selected' : '' ?>>
                        <?= sanitize($b['budget_code']) ?>
                        —
                        <?= sanitize($b['budget_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <?php if (isset($errors['budget_id'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['budget_id']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Version -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Version

                <span class="text-red-600">*</span>

            </label>


            <select
                name="version_id"
                id="version_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['version_id']) ? 'border-red-500' : '' ?>">

                <option value="">
                    Select version...
                </option>

            </select>


            <?php if (isset($errors['version_id'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['version_id']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Line -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Line

                <span class="text-red-600">*</span>

            </label>


            <select
                name="line_id"
                id="line_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['line_id']) ? 'border-red-500' : '' ?>">

                <option value="">
                    Select line...
                </option>

            </select>


            <?php if (isset($errors['line_id'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['line_id']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Current Amount -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Current Budget

            </label>


            <input
                type="text"
                id="current_amount"
                readonly
                class="w-full px-3 py-2 border rounded bg-gray-50 font-mono text-gray-700">

        </div>


        <!-- Revision Type -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Revision Type

                <span class="text-red-600">*</span>

            </label>


            <div
                class="grid grid-cols-3 gap-3"
                id="revisionTypeContainer">

                <?php foreach (
                    [

                        'increase' => [
                            'Increase',
                            'Raise the budget amount'
                        ],

                        'decrease' => [
                            'Decrease',
                            'Lower the budget amount'
                        ],

                        'adjustment' => [
                            'Adjustment',
                            'Any change (increase or decrease)'
                        ],

                    ] as $k => [$label, $desc]
                ): ?>


                    <label
                        class="border rounded-lg p-3 cursor-pointer hover:border-blue-500 <?= old('revision_type') === $k ? 'border-blue-500 bg-blue-50' : '' ?>">

                        <input
                            type="radio"
                            name="revision_type"
                            value="<?= $k ?>"
                            class="mr-2"
                            <?= old('revision_type') === $k ? 'checked' : '' ?>>


                        <span class="font-medium text-gray-800">

                            <?= $label ?>

                        </span>


                        <div class="text-xs text-gray-500 mt-1">

                            <?= $desc ?>

                        </div>

                    </label>


                <?php endforeach; ?>

            </div>


            <?php if (isset($errors['revision_type'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['revision_type']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- New Amount -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                New Amount

                <span class="text-red-600">*</span>

            </label>


            <input
                type="number"
                step="0.01"
                min="0"
                name="new_amount"
                id="new_amount"
                value="<?= sanitize(old('new_amount', '')) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['new_amount']) ? 'border-red-500' : '' ?>">


            <?php if (isset($errors['new_amount'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['new_amount']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Reason -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Reason

                <span class="text-red-600">*</span>

                <span class="text-xs text-gray-500">
                    (mandatory)
                </span>

            </label>


            <textarea
                name="reason"
                id="reason"
                rows="3"
                maxlength="500"
                placeholder="Why is this revision needed?"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['reason']) ? 'border-red-500' : '' ?>"><?= sanitize(old('reason')) ?></textarea>


            <?php if (isset($errors['reason'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['reason']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Buttons -->

        <div class="flex justify-end gap-2 pt-2">

            <a
                href="index.php"
                class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50">
                Cancel
            </a>


            <button
                type="submit"
                class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-save mr-2"></i>
                Apply Revision
            </button>

        </div>

    </form>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    // =========================================================
    // Budget -> Versions
    // =========================================================

    document
        .getElementById('budget_id')
        .addEventListener('change', function() {

            const bid = this.value;

            const vSel =
                document.getElementById('version_id');

            const lSel =
                document.getElementById('line_id');


            vSel.innerHTML =
                '<option value="">Select version...</option>';

            lSel.innerHTML =
                '<option value="">Select line...</option>';

            document.getElementById('current_amount').value = '';


            if (!bid) {

                $('#version_id').valid();
                $('#line_id').valid();

                return;
            }


            fetch(
                    'versions_ajax.php?budget_id=' +
                    encodeURIComponent(bid)
                )
                .then(r => {

                    if (!r.ok) {
                        throw new Error('Failed to load versions.');
                    }

                    return r.json();
                })
                .then(data => {

                    data.forEach(v => {

                        if (v.status === 'draft') {

                            const opt =
                                document.createElement('option');

                            opt.value = v.id;

                            opt.textContent =
                                v.version_label +
                                ' (v' +
                                v.version_no +
                                ')';

                            vSel.appendChild(opt);
                        }

                    });


                    // Existing behavior:
                    // default revision type = adjustment

                    const adjustment =
                        document.querySelector(
                            'input[name="revision_type"][value="adjustment"]'
                        );

                    if (adjustment) {
                        adjustment.checked = true;
                    }


                    $('#version_id').valid();

                })
                .catch(() => {

                    vSel.innerHTML =
                        '<option value="">Unable to load versions</option>';

                    $('#version_id').valid();

                });

        });


    // =========================================================
    // Version -> Lines
    // =========================================================

    document
        .getElementById('version_id')
        .addEventListener('change', function() {

            const vid = this.value;

            const lSel =
                document.getElementById('line_id');


            lSel.innerHTML =
                '<option value="">Select line...</option>';

            document.getElementById('current_amount').value = '';


            if (!vid) {

                $('#line_id').valid();

                return;
            }


            fetch(
                    'lines_ajax.php?version_id=' +
                    encodeURIComponent(vid)
                )
                .then(r => {

                    if (!r.ok) {
                        throw new Error('Failed to load lines.');
                    }

                    return r.json();
                })
                .then(data => {

                    data.forEach(l => {

                        const opt =
                            document.createElement('option');

                        opt.value = l.id;

                        opt.textContent =
                            l.display;

                        opt.dataset.amount =
                            l.amount;

                        lSel.appendChild(opt);

                    });


                    $('#line_id').valid();

                })
                .catch(() => {

                    lSel.innerHTML =
                        '<option value="">Unable to load lines</option>';

                    $('#line_id').valid();

                });

        });


    // =========================================================
    // Line -> Current Amount
    // =========================================================

    document
        .getElementById('line_id')
        .addEventListener('change', function() {

            const opt =
                this.options[this.selectedIndex];


            if (
                opt &&
                opt.dataset.amount !== undefined
            ) {

                const amount =
                    parseFloat(opt.dataset.amount);


                document.getElementById(
                        'current_amount'
                    ).value =

                    new Intl.NumberFormat(
                        'en-US', {
                            style: 'currency',
                            currency: 'USD',
                            minimumFractionDigits: 2
                        }
                    ).format(amount);


            } else {

                document.getElementById(
                    'current_amount'
                ).value = '';
            }


            // Revalidate amount when line changes.
            $('#new_amount').valid();

            $('#line_id').valid();

        });


    // =========================================================
    // Revision Type - UI
    // =========================================================

    document
        .querySelectorAll('input[name="revision_type"]')
        .forEach(function(radio) {

            radio.addEventListener('change', function() {

                document
                    .querySelectorAll(
                        '#revisionTypeContainer label'
                    )
                    .forEach(function(label) {

                        label.classList.remove(
                            'border-blue-500',
                            'bg-blue-50'
                        );

                    });


                const parent =
                    this.closest('label');


                if (parent) {

                    parent.classList.add(
                        'border-blue-500',
                        'bg-blue-50'
                    );
                }


                $('#revisionForm').validate().element(
                    'input[name="revision_type"]'
                );


                // Revision type changes the amount rule.
                $('#new_amount').valid();

            });

        });


    // =========================================================
    // jQuery Validation
    // =========================================================

    $(document).ready(function() {


        // Remove server-side error message when
        // user starts changing the field.

        $('#revisionForm').on(
            'input change',
            'input, select, textarea',
            function() {

                $(this)
                    .closest('div')
                    .find('.server-error')
                    .first()
                    .remove();

            }
        );


        // =====================================================
        // Custom validation:
        // Revision amount must match revision type
        // =====================================================

        $.validator.addMethod(
            'validRevisionAmount',
            function(value, element) {

                if (value === '') {
                    return true;
                }


                const newAmount =
                    parseFloat(value);


                if (isNaN(newAmount)) {
                    return false;
                }


                const selectedType =
                    $('input[name="revision_type"]:checked').val();


                const lineOption =
                    $('#line_id option:selected');


                const oldAmount =
                    parseFloat(
                        lineOption.attr('data-amount')
                    );


                // If no line is selected yet,
                // let the line validation handle it.

                if (
                    !selectedType ||
                    !lineOption.val() ||
                    isNaN(oldAmount)
                ) {
                    return true;
                }


                const epsilon = 0.000001;


                if (selectedType === 'increase') {

                    return newAmount > oldAmount + epsilon;

                }


                if (selectedType === 'decrease') {

                    return newAmount < oldAmount - epsilon;

                }


                if (selectedType === 'adjustment') {

                    return Math.abs(newAmount - oldAmount) > epsilon;

                }


                return true;

            },
            function() {

                const selectedType =
                    $('input[name="revision_type"]:checked').val();


                if (selectedType === 'increase') {

                    return 'Increase requires a higher amount than the current budget.';

                }


                if (selectedType === 'decrease') {

                    return 'Decrease requires a lower amount than the current budget.';

                }


                if (selectedType === 'adjustment') {

                    return 'Adjustment requires a different amount from the current budget.';

                }


                return 'Please enter a valid revision amount.';

            }
        );


        // =====================================================
        // Main Validation
        // =====================================================

        $('#revisionForm').validate({

            errorElement: 'p',

            errorClass: 'error text-red-500 text-sm mt-1 block',


            // We are using jQuery validation.
            ignore: ':hidden:not(select)',


            // =================================================
            // Error Placement
            // =================================================

            errorPlacement: function(
                error,
                element
            ) {

                if (element.is(':radio')) {

                    // Put radio group error below all options.

                    error.insertAfter(
                        '#revisionTypeContainer'
                    );

                } else if (element.is('select')) {

                    // IMPORTANT:
                    // Dropdown error appears below dropdown.

                    error.appendTo(
                        element.parent()
                    );

                } else {

                    error.insertAfter(element);
                }

            },


            // =================================================
            // Highlight
            // =================================================

            highlight: function(element) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass('border-gray-300');

            },


            // =================================================
            // Unhighlight
            // =================================================

            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500');

            },


            // =================================================
            // Rules
            // =================================================

            rules: {

                budget_id: {

                    required: true

                },


                version_id: {

                    required: true

                },


                line_id: {

                    required: true

                },


                revision_type: {

                    required: true

                },


                new_amount: {

                    required: true,

                    number: true,

                    min: 0,

                    validRevisionAmount: true

                },


                reason: {

                    required: true,

                    minlength: 3,

                    maxlength: 500

                }

            },


            // =================================================
            // Messages
            // =================================================

            messages: {

                budget_id: {

                    required: 'Please select a budget.'

                },


                version_id: {

                    required: 'Please select a version.'

                },


                line_id: {

                    required: 'Please select a budget line.'

                },


                revision_type: {

                    required: 'Please select a revision type.'

                },


                new_amount: {

                    required: 'Please enter the new amount.',

                    number: 'Please enter a valid amount.',

                    min: 'Amount cannot be negative.'

                },


                reason: {

                    required: 'Please enter the reason for this revision.',

                    minlength: 'Reason must contain at least 3 characters.',

                    maxlength: 'Reason cannot exceed 500 characters.'

                }

            },


            // =================================================
            // Submit
            // =================================================

            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>