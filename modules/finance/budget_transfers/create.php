<?php

$pageTitle = 'New Budget Transfer';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.transfer')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

$activeFy = getActiveFinancialYear($db);

$budgets = $db->query("
    SELECT b.*, fy.year_name, fy.start_date, fy.end_date
    FROM budgets b
    JOIN financial_years fy ON fy.id = b.financial_year_id
    WHERE b.status IN ('active')
    ORDER BY b.budget_code
")->fetchAll();

$preBudgetId = (int)($_GET['budget_id'] ?? 0);

if (
    $preBudgetId &&
    !$db->query("SELECT 1 FROM budgets WHERE id = $preBudgetId")->fetchColumn()
) {
    $preBudgetId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
    }

    $budgetId     = (int)($_POST['budget_id'] ?? 0);
    $transferType = $_POST['transfer_type'] ?? '';
    $fromLineId   = (int)($_POST['from_line_id'] ?? 0);
    $toLineId     = (int)($_POST['to_line_id'] ?? 0);
    $amount       = (float)($_POST['amount'] ?? 0);
    $reason       = trim($_POST['reason'] ?? '');

    if (!$budgetId) {
        $errors['budget_id'] = 'Select a budget.';
    }

    if (!in_array($transferType, ['department', 'project', 'account'])) {
        $errors['transfer_type'] = 'Select a transfer type.';
    }

    if (!$fromLineId) {
        $errors['from_line_id'] = 'Select a source line.';
    }

    if (!$toLineId) {
        $errors['to_line_id'] = 'Select a destination line.';
    }

    if ($fromLineId === $toLineId) {
        $errors['general'] = 'From and To lines must be different.';
    }

    if ($amount <= 0) {
        $errors['amount'] = 'Amount must be greater than zero.';
    }

    if ($reason === '') {
        $errors['reason'] = 'Reason is required.';
    }

    if (!$errors) {

        $budget = budget_find($db, $budgetId);

        if (!$budget) {
            $errors['budget_id'] = 'Selected budget was not found.';
        } else {

            $version = $db->prepare("SELECT * FROM budget_versions WHERE id = ?");
            $version->execute([$budget['current_version_id']]);
            $version = $version->fetch();

            if (!$version) {
                $errors['general'] = 'No active version for this budget.';
            }
        }
    }

    if (!$errors) {

        $fy = [
            'id' => $budget['financial_year_id'],
            'start_date' => $budget['fy_start'],
            'end_date' => $budget['fy_end']
        ];

        $fromRow = $db->prepare("
            SELECT bl.*, 
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
            WHERE bl.id = ?
              AND bl.budget_version_id = ?
        ");

        $fromRow->execute([$fromLineId, $version['id']]);
        $fromLine = $fromRow->fetch();

        $toRow = $db->prepare("
            SELECT bl.*, 
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
            WHERE bl.id = ?
              AND bl.budget_version_id = ?
        ");

        $toRow->execute([$toLineId, $version['id']]);
        $toLine = $toRow->fetch();

        if (!$fromLine || !$toLine) {
            $errors['general'] = 'Selected lines not found.';
        }
    }

    if (!$errors) {

        // Dimension validation per transfer_type

        if (
            $transferType === 'department' &&
            ($fromLine['project_id'] !== null || $toLine['project_id'] !== null)
        ) {
            $errors['general'] = 'Department transfer lines must not have a project set.';
        }

        if (
            $transferType === 'project' &&
            ($fromLine['department_id'] !== null || $toLine['department_id'] !== null)
        ) {
            $errors['general'] = 'Project transfer lines must not have a department set.';
        }

        if (
            $transferType === 'account' &&
            ($fromLine['chart_of_accounts_id'] === $toLine['chart_of_accounts_id'])
        ) {
            $errors['general'] = 'Account transfer requires different accounts.';
        }
    }

    if (!$errors) {

        // Available check on FROM line

        $available = line_available($db, $fromLine, $fy);

        if ($amount > $available + 0.005) {
            $errors['amount'] =
                'Transfer amount exceeds available budget on the source line (available: '
                . formatMoney($available)
                . ').';
        }
    }

    if (!$errors) {

        try {

            $db->beginTransaction();

            $transferNo = generateTransferNo($db);

            $db->prepare("
                INSERT INTO budget_transfers
                (
                    transfer_no,
                    budget_id,
                    budget_version_id,
                    from_line_id,
                    to_line_id,
                    transfer_type,
                    amount,
                    reason,
                    status,
                    requested_by,
                    requested_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ")->execute([
                $transferNo,
                $budgetId,
                $version['id'],
                $fromLineId,
                $toLineId,
                $transferType,
                $amount,
                $reason,
                $_SESSION['user_id']
            ]);

            $transferId = (int)$db->lastInsertId();

            $db->commit();

            logAudit(
                $_SESSION['user_id'],
                "Created budget transfer $transferNo: "
                    . formatMoney($amount)
                    . " from line $fromLineId to $toLineId",
                'budget'
            );

            flash(
                'success',
                'Transfer created and pending approval.',
                'success'
            );

            redirect('view.php?id=' . $transferId);
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Transfer create error: ' . $e->getMessage());

            $errors['general'] =
                'Could not create transfer: ' . $e->getMessage();
        }
    }

    if ($errors) {
        saveFormState($errors);
        redirect('create.php');
    }
}


// Build line lists per budget for JS
// We'll fetch lines for the pre-selected budget if any, else empty

$fromLines = $toLines = [];

if ($preBudgetId) {

    $b = budget_find($db, $preBudgetId);

    if ($b) {

        $v = $db->prepare("SELECT * FROM budget_versions WHERE id = ?");
        $v->execute([$b['current_version_id']]);
        $v = $v->fetch();

        if ($v) {

            $fromLines = $toLines = $db->prepare("
                SELECT bl.*,
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
                ORDER BY acct.account_code, bl.month
            ");

            $fromLines->execute([$v['id']]);

            $fromLines = $fromLines->fetchAll();

            $toLines = $fromLines;
        }
    }
}

$encodedFromLines = json_encode(
    array_map(
        function ($l) {

            return [
                'id' => (int)$l['id'],

                'display' => ($l['department_name'] ?? '—')
                    . ' / '
                    . ($l['project_name'] ?? '—')
                    . ' / '
                    . $l['account_code']
                    . ' - '
                    . $l['account_name']
                    . ' / '
                    . budget_month_name($l['month']),

                'dept' => $l['department_id'],
                'proj' => $l['project_id'],
                'acct' => $l['chart_of_accounts_id'],
                'month' => $l['month'],
                'amount' => (float)$l['budget_amount'],
            ];
        },
        $fromLines
    )
);

?>

<style>
    #transferForm p.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        clear: both;
    }

    #transferForm input.error,
    #transferForm select.error,
    #transferForm textarea.error {
        border-color: #ef4444 !important;
    }

    #transferForm .field-error {
        display: block;
        color: #ef4444;
        font-size: 0.75rem;
        margin-top: 4px;
    }
</style>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            New Budget Transfer
        </h1>

        <p class="text-gray-600">
            Move budget between departments, projects or accounts
        </p>
    </div>

    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Transfers
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
        id="transferForm"
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
                        <?= old('budget_id') == $b['id'] || $preBudgetId == $b['id'] ? 'selected' : '' ?>>
                        <?= sanitize($b['budget_code']) ?>
                        —
                        <?= sanitize($b['budget_name']) ?>
                        (<?= sanitize($b['year_name']) ?>)
                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (isset($errors['budget_id'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['budget_id']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Transfer Type -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Transfer Type
                <span class="text-red-600">*</span>
            </label>

            <div
                class="grid grid-cols-3 gap-3"
                id="typeRadios">

                <?php foreach (
                    [
                        'department' => [
                            'Department',
                            'Move budget between departments (same account, same project)'
                        ],
                        'project' => [
                            'Project',
                            'Move budget between projects (same account, same department)'
                        ],
                        'account' => [
                            'Account',
                            'Move budget between accounts (same department/project)'
                        ],
                    ] as $k => [$label, $desc]
                ): ?>

                    <label
                        class="border rounded-lg p-3 cursor-pointer hover:border-blue-500
                    <?= old('transfer_type') === $k ||
                        ($errors && !old('transfer_type') && $k === 'department')
                        ? 'border-blue-500 bg-blue-50'
                        : '' ?>">

                        <input
                            type="radio"
                            name="transfer_type"
                            value="<?= $k ?>"
                            class="mr-2"
                            <?= old('transfer_type') === $k ? 'checked' : '' ?>>

                        <span class="font-medium text-gray-800">
                            <?= $label ?>
                        </span>

                        <div class="text-xs text-gray-500 mt-1">
                            <?= $desc ?>
                        </div>

                    </label>

                <?php endforeach; ?>

            </div>

            <?php if (isset($errors['transfer_type'])): ?>

                <p class="text-red-500 text-xs mt-1 server-error">
                    <?= sanitize($errors['transfer_type']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- From / To -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    From Line
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="from_line_id"
                    id="from_line_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select source line...
                    </option>

                </select>

                <?php if (isset($errors['from_line_id'])): ?>

                    <p class="text-red-500 text-xs mt-1 server-error">
                        <?= sanitize($errors['from_line_id']) ?>
                    </p>

                <?php endif; ?>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    To Line
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="to_line_id"
                    id="to_line_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select destination line...
                    </option>

                </select>

                <?php if (isset($errors['to_line_id'])): ?>

                    <p class="text-red-500 text-xs mt-1 server-error">
                        <?= sanitize($errors['to_line_id']) ?>
                    </p>

                <?php endif; ?>

            </div>

        </div>


        <!-- Amount / Reason -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Amount
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="amount"
                    id="amount"
                    value="<?= sanitize(old('amount', '')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['amount']) ? 'border-red-500' : '' ?>">

                <?php if (isset($errors['amount'])): ?>

                    <p class="text-red-500 text-xs mt-1 server-error">
                        <?= sanitize($errors['amount']) ?>
                    </p>

                <?php endif; ?>

                <p
                    class="text-xs text-gray-500 mt-1"
                    id="availableHint"></p>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason
                    <span class="text-red-600">*</span>
                </label>

                <textarea
                    name="reason"
                    id="reason"
                    rows="3"
                    maxlength="500"
                    placeholder="Why is this transfer needed?"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= isset($errors['reason']) ? 'border-red-500' : '' ?>"><?= sanitize(old('reason')) ?></textarea>

                <?php if (isset($errors['reason'])): ?>

                    <p class="text-red-500 text-xs mt-1 server-error">
                        <?= sanitize($errors['reason']) ?>
                    </p>

                <?php endif; ?>

            </div>

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
                Create Transfer
            </button>

        </div>

    </form>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    // =========================================================
    // Line data from PHP
    // =========================================================

    let allLines = <?= $encodedFromLines ?>;


    // =========================================================
    // Populate From / To Lines
    // =========================================================

    function populateLines() {

        const type =
            document.querySelector('input[name="transfer_type"]:checked')?.value ||
            'department';

        const fromSel = document.getElementById('from_line_id');
        const toSel = document.getElementById('to_line_id');

        const fromVal = fromSel.value;
        const toVal = toSel.value;

        fromSel.innerHTML =
            '<option value="">Select source line...</option>';

        toSel.innerHTML =
            '<option value="">Select destination line...</option>';


        allLines.forEach(l => {

            let ok = false;

            if (type === 'department') {

                // Department transfer:
                // must have department and no project
                ok = !!l.dept && !l.proj;

            } else if (type === 'project') {

                // Project transfer:
                // must have project and no department
                ok = !!l.proj && !l.dept;

            } else if (type === 'account') {

                // Account transfer:
                // must have department and project
                ok = !!l.dept && !!l.proj;
            }


            if (ok) {

                const opt1 = document.createElement('option');

                opt1.value = l.id;
                opt1.textContent = l.display;
                opt1.dataset.amount = l.amount;

                fromSel.appendChild(opt1);


                const opt2 = document.createElement('option');

                opt2.value = l.id;
                opt2.textContent = l.display;
                opt2.dataset.amount = l.amount;

                toSel.appendChild(opt2);
            }

        });


        if (fromVal) {
            fromSel.value = fromVal;
        }

        if (toVal) {
            toSel.value = toVal;
        }


        updateAvailableHint();

        // Re-validate dynamically populated selects
        if ($('#transferForm').data('validator')) {
            $('#from_line_id').valid();
            $('#to_line_id').valid();
        }
    }


    // =========================================================
    // Available Amount Hint
    // =========================================================

    function updateAvailableHint() {

        const fromId =
            parseInt(
                document.getElementById('from_line_id').value || 0
            );

        const hint =
            document.getElementById('availableHint');


        if (fromId) {

            const line =
                allLines.find(l => l.id === fromId);

            if (line) {

                const formattedAmount =
                    new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD',
                        minimumFractionDigits: 2
                    }).format(line.amount);


                hint.innerHTML =
                    '<i class="fas fa-info-circle mr-1"></i>' +
                    'Line budget: ' +
                    formattedAmount;
            }

        } else {

            hint.textContent = '';
        }
    }


    // =========================================================
    // Budget Change - Fetch Lines
    // =========================================================

    document
        .getElementById('budget_id')
        .addEventListener('change', function() {

            const bid = this.value;

            if (!bid) {

                allLines = [];

                populateLines();

                return;
            }


            fetch(
                    'lines_ajax.php?budget_id=' +
                    encodeURIComponent(bid)
                )
                .then(r => {

                    if (!r.ok) {
                        throw new Error('Failed to load budget lines.');
                    }

                    return r.json();
                })
                .then(data => {

                    allLines = Array.isArray(data) ?
                        data :
                        [];

                    populateLines();
                })
                .catch(() => {

                    allLines = [];

                    populateLines();
                });

        });


    // =========================================================
    // Transfer Type Change
    // =========================================================

    document
        .querySelectorAll('input[name="transfer_type"]')
        .forEach(r => {

            r.addEventListener('change', function() {

                populateLines();

                $('#transferForm').validate().element(
                    'input[name="transfer_type"]'
                );
            });

        });


    // =========================================================
    // From Line Change
    // =========================================================

    document
        .getElementById('from_line_id')
        .addEventListener('change', function() {

            updateAvailableHint();

            $('#from_line_id').valid();

            // To line must be different
            if ($('#to_line_id').val() === this.value) {
                $('#to_line_id').valid();
            }
        });


    // =========================================================
    // To Line Change
    // =========================================================

    document
        .getElementById('to_line_id')
        .addEventListener('change', function() {

            $('#to_line_id').valid();

            // From line must be different
            if ($('#from_line_id').val() === this.value) {
                $('#from_line_id').valid();
            }
        });


    // =========================================================
    // jQuery Validation
    // =========================================================

    $(document).ready(function() {

        // Remove server-side validation messages when
        // user starts using client-side validation.
        $('#transferForm').on('input change', 'input, select, textarea', function() {

            $(this)
                .closest('div')
                .find('.server-error')
                .first()
                .remove();

        });


        // Custom validation:
        // From and To lines must be different.
        $.validator.addMethod(
            'differentLines',
            function(value, element) {

                const from =
                    $('#from_line_id').val();

                const to =
                    $('#to_line_id').val();

                if (!from || !to) {
                    return true;
                }

                return from !== to;
            },
            'From and To lines must be different.'
        );


        $('#transferForm').validate({

            errorElement: 'p',

            errorClass: 'error text-red-500 text-sm mt-1 block',

            // Do not validate hidden fields.
            ignore: ':hidden:not(select)',


            // Error placement
            errorPlacement: function(error, element) {

                if (element.is(':radio')) {

                    error.insertAfter('#typeRadios');

                } else if (element.is('select')) {

                    // Important:
                    // Dropdown error appears BELOW dropdown.
                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);
                }
            },


            highlight: function(element) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass('border-gray-300');
            },


            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500');
            },


            rules: {

                budget_id: {
                    required: true
                },

                transfer_type: {
                    required: true
                },

                from_line_id: {
                    required: true,
                    differentLines: true
                },

                to_line_id: {
                    required: true,
                    differentLines: true
                },

                amount: {
                    required: true,
                    number: true,
                    min: 0.01
                },

                reason: {
                    required: true,
                    minlength: 3,
                    maxlength: 500
                }

            },


            messages: {

                budget_id: {
                    required: 'Please select a budget.'
                },

                transfer_type: {
                    required: 'Please select a transfer type.'
                },

                from_line_id: {
                    required: 'Please select the source line.',
                    differentLines: 'From and To lines must be different.'
                },

                to_line_id: {
                    required: 'Please select the destination line.',
                    differentLines: 'From and To lines must be different.'
                },

                amount: {
                    required: 'Please enter the transfer amount.',
                    number: 'Please enter a valid amount.',
                    min: 'Amount must be greater than zero.'
                },

                reason: {
                    required: 'Please enter the reason for this transfer.',
                    minlength: 'Reason must contain at least 3 characters.',
                    maxlength: 'Reason cannot exceed 500 characters.'
                }

            },


            // Validate radio group correctly.
            groups: {
                transferType: 'transfer_type'
            },


            // Submit only after jQuery validation succeeds.
            submitHandler: function(form) {

                form.submit();
            }

        });

    });


    // =========================================================
    // Initial Load
    // =========================================================

    populateLines();
</script>


<?php require_once '../../../includes/footer.php'; ?>