<?php
$pageTitle = 'Create Journal Entry';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('finance.journal.create')) {
    die('Access Denied');
}

$db = getDB();

// Journals are created against the active company; PostingEngine refuses to guess one.
$activeCompanyRow = activeCompany();
$activeCompanyId  = $activeCompanyRow ? (int) $activeCompanyRow['id'] : 0;
if ($activeCompanyId <= 0) {
    flash('nocorp', 'No active company is configured. Create a company and mark it default before raising journals.', 'error');
    to('modules/companies/index.php');
}

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$voucherTypes = $db->query("
    SELECT *
    FROM voucher_types
    WHERE status = 'active'
    ORDER BY voucher_name
")->fetchAll();

$accounts = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE status = 'active'
      AND (company_id IS NULL OR company_id = " . (int) $activeCompanyId . ")
    ORDER BY account_code
")->fetchAll();


// ---------------------------------------------------------
// POST Handler
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token. Please try again.';
    }

    $voucher_type_id = (int)($_POST['voucher_type_id'] ?? 0);
    $voucher_date    = trim($_POST['voucher_date'] ?? '');
    $reference_no    = trim($_POST['reference_no'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $lines           = $_POST['lines'] ?? [];


    // -----------------------------------------------------
    // Validate header fields
    // -----------------------------------------------------

    if ($voucher_type_id <= 0) {
        $errors[] = 'Please select a voucher type.';
    }

    if (empty($voucher_date)) {
        $errors[] = 'Voucher date is required.';
    }


    // -----------------------------------------------------
    // Validate journal lines
    // -----------------------------------------------------

    if (empty($lines) || !is_array($lines)) {

        $errors[] = 'At least one journal line is required.';
    } else {

        $total_debit  = 0.0;
        $total_credit = 0.0;
        $account_ids  = [];

        foreach ($lines as $index => $line) {

            $line_no = $index + 1;

            $acc_id = (int)($line['account_id'] ?? 0);

            $debit = (float)str_replace(
                ',',
                '',
                $line['debit'] ?? 0
            );

            $credit = (float)str_replace(
                ',',
                '',
                $line['credit'] ?? 0
            );


            if ($acc_id <= 0) {

                $errors[] =
                    "Line {$line_no}: Please select an account.";

                continue;
            }


            if (
                isset($line['debit']) &&
                $line['debit'] !== '' &&
                isset($line['credit']) &&
                $line['credit'] !== ''
            ) {

                if ($debit > 0 && $credit > 0) {

                    $errors[] =
                        "Line {$line_no}: A line cannot contain both debit and credit amounts.";
                }
            }


            if ($debit <= 0 && $credit <= 0) {

                $errors[] =
                    "Line {$line_no}: Either debit or credit amount must be greater than zero.";
            }


            if ($debit < 0 || $credit < 0) {

                $errors[] =
                    "Line {$line_no}: Amount cannot be negative.";
            }


            $total_debit += $debit;
            $total_credit += $credit;

            $account_ids[] = $acc_id;
        }


        // -------------------------------------------------
        // Debit / Credit balance
        // -------------------------------------------------

        if (abs($total_debit - $total_credit) >= 0.001) {

            $errors[] =
                'Total debit (' .
                formatNumber($total_debit) .
                ') must equal total credit (' .
                formatNumber($total_credit) .
                ').';
        }


        // -------------------------------------------------
        // Verify selected accounts
        // -------------------------------------------------

        if (!empty($account_ids)) {

            $unique_ids = array_values(
                array_unique($account_ids)
            );

            $placeholders = implode(
                ',',
                array_fill(0, count($unique_ids), '?')
            );

            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt
                FROM chart_of_accounts
                WHERE id IN ({$placeholders})
                AND status = 'active'
            ");

            $stmt->execute($unique_ids);

            if (
                (int)$stmt->fetch()['cnt']
                !== count($unique_ids)
            ) {

                $errors[] =
                    'One or more selected accounts are invalid or inactive.';
            }


            // Duplicate account check
            if (
                count($account_ids)
                !== count($unique_ids)
            ) {

                $errors[] =
                    'Duplicate accounts are not allowed in a single journal entry.';
            }
        }
    }


    // -----------------------------------------------------
    // Save if no errors
    // -----------------------------------------------------

    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/finance/journal_entries/create.php');
    }


    try {

        $db->beginTransaction();

        $voucher_no = generateVoucherNo(
            $voucher_type_id,
            $db
        );


        $stmt = $db->prepare("
            INSERT INTO journal_entries
            (
                company_id,
                voucher_no,
                voucher_type_id,
                voucher_date,
                reference_no,
                description,
                status,
                created_by,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, NOW(), NOW())
        ");

        $stmt->execute([
            $activeCompanyId,
            $voucher_no,
            $voucher_type_id,
            $voucher_date,
            $reference_no,
            $description,
            $_SESSION['user_id']
        ]);


        $journal_entry_id =
            (int)$db->lastInsertId();


        foreach ($lines as $line) {

            if (
                (int)($line['account_id'] ?? 0)
                <= 0
            ) {
                continue;
            }


            $debit = (float)str_replace(
                ',',
                '',
                $line['debit'] ?? 0
            );

            $credit = (float)str_replace(
                ',',
                '',
                $line['credit'] ?? 0
            );


            $stmt = $db->prepare("
                INSERT INTO journal_entry_details
                (
                    journal_entry_id,
                    account_id,
                    debit_amount,
                    credit_amount,
                    description
                )
                VALUES (?, ?, ?, ?, ?)
            ");


            $stmt->execute([
                $journal_entry_id,
                (int)$line['account_id'],
                $debit,
                $credit,
                $line['description'] ?? ''
            ]);
        }


        $db->commit();


        logAudit(
            'journal.create',
            'journal_entries',
            "Created journal entry voucher: {$voucher_no}"
        );

        flash(
            'success',
            'Journal entry created successfully as draft.'
        );

        redirect('index.php');
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $errors[] =
            'Database error: ' . $e->getMessage();
    }
}
?>

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold">
            Create Journal Entry
        </h1>

        <p class="text-gray-600">
            Enter balanced debit and credit entries
        </p>

    </div>

    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Journal Entries
    </a>

</div>


<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

        <ul class="list-disc pl-5">

            <?php foreach ($errors as $err): ?>

                <li><?= sanitize($err) ?></li>

            <?php endforeach; ?>

        </ul>

    </div>

<?php endif; ?>


<div class="bg-white rounded-lg shadow p-6">

    <form
        method="POST"
        id="journalForm"
        class="space-y-4"
        novalidate>

         <input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <input
            type="hidden"
            name="voucher_no"
            id="voucherNo">


        <!-- Header Row -->

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <!-- Voucher Type -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Voucher Type
                    <span class="text-red-500">*</span>
                </label>

                <select
                    name="voucher_type_id"
                    id="voucherType"
                    class="w-full px-3 py-2 border rounded"
                    onchange="updateVoucherNo()">

                    <option value="">
                        Select Type
                    </option>

                    <?php foreach ($voucherTypes as $vt): ?>

                        <option
                            value="<?= $vt['id'] ?>"
                            <?= old('voucher_type_id') == $vt['id']
                                ? 'selected'
                                : '' ?>>
                            <?= sanitize($vt['voucher_name']) ?>
                            (<?= sanitize($vt['voucher_prefix']) ?>)
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Voucher No -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Voucher No
                </label>

                <input
                    type="text"
                    id="voucherNoDisplay"
                    readonly
                    class="w-full px-3 py-2 border rounded bg-gray-100"
                    placeholder="Auto-generated">

            </div>


            <!-- Voucher Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Voucher Date
                    <span class="text-red-500">*</span>
                </label>

                <input
                    type="date"
                    name="voucher_date"
                    value="<?= old(
                                'voucher_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded">

            </div>


            <!-- Reference No -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference No
                </label>

                <input
                    type="text"
                    name="reference_no"
                    value="<?= old('reference_no') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="Optional">

            </div>

        </div>


        <!-- Description -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>

            <textarea
                name="description"
                rows="2"
                maxlength="500"
                class="w-full px-3 py-2 border rounded"
                placeholder="Journal description"><?= old('description') ?></textarea>

        </div>


        <!-- Journal Lines -->

        <div class="mt-6">

            <h3 class="text-lg font-semibold mb-2">
                Journal Lines
            </h3>

            <div class="overflow-x-auto">

                <table
                    class="w-full"
                    id="journalLinesTable">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Account
                                <span class="text-red-500">*</span>
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Debit (AED)
                            </th>

                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                Credit (AED)
                            </th>

                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                Description
                            </th>

                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody
                        id="journalLinesBody"
                        class="divide-y divide-gray-200">

                        <tr class="journal-line">

                            <!-- Account -->

                            <td class="px-4 py-2">

                                <select
                                    name="lines[0][account_id]"
                                    class="w-full px-2 py-2 border rounded account-select">

                                    <option value="">
                                        Select Account
                                    </option>

                                    <?php foreach ($accounts as $a): ?>

                                        <option value="<?= $a['id'] ?>">
                                            <?= sanitize(
                                                $a['account_code']
                                                    . ' - '
                                                    . $a['account_name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>

                            <!-- Debit -->

                            <td class="px-4 py-2">

                                <input
                                    type="number"
                                    name="lines[0][debit]"
                                    step="0.01"
                                    min="0"
                                    value="0.00"
                                    class="w-full px-2 py-2 border rounded text-right debit-input">

                            </td>


                            <!-- Credit -->

                            <td class="px-4 py-2">

                                <input
                                    type="number"
                                    name="lines[0][credit]"
                                    step="0.01"
                                    min="0"
                                    value="0.00"
                                    class="w-full px-2 py-2 border rounded text-right credit-input">

                            </td>


                            <!-- Line Description -->

                            <td class="px-4 py-2">

                                <input
                                    type="text"
                                    name="lines[0][description]"
                                    maxlength="250"
                                    class="w-full px-2 py-2 border rounded line-description"
                                    placeholder="Line description">

                            </td>


                            <!-- Actions -->

                            <td class="px-4 py-2 text-center">

                                <button
                                    type="button"
                                    onclick="removeLine(this)"
                                    class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>

                            </td>

                        </tr>

                    </tbody>


                    <tfoot class="bg-gray-50 font-semibold">

                        <tr>

                            <td class="px-4 py-2 text-right">
                                Totals
                            </td>

                            <td
                                class="px-4 py-2 text-right"
                                id="totalDebit">
                                0.00
                            </td>

                            <td
                                class="px-4 py-2 text-right"
                                id="totalCredit">
                                0.00
                            </td>

                            <td class="px-4 py-2"></td>

                            <td
                                class="px-4 py-2 text-center"
                                id="balanceIndicator">
                                <span class="text-green-600">
                                    <i class="fas fa-check-circle"></i>
                                    Balanced
                                </span>
                            </td>

                        </tr>

                    </tfoot>

                </table>

            </div>


            <button
                type="button"
                onclick="addLine()"
                class="mt-2 text-blue-600 hover:text-blue-800">
                <i class="fas fa-plus mr-1"></i>
                Add Line
            </button>

        </div>


        <!-- Submit -->

        <div class="pt-4 border-t">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i>
                Save as Draft
            </button>

            <a
                href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    let lineIndex = 1;


    /* ---------------------------------------------------------
       Calculate totals
    --------------------------------------------------------- */

    function calculateTotals() {

        let totalDebit = 0;
        let totalCredit = 0;

        document
            .querySelectorAll('.journal-line')
            .forEach(function(row) {

                const debitInput =
                    row.querySelector('.debit-input');

                const creditInput =
                    row.querySelector('.credit-input');

                const debit =
                    parseFloat(debitInput?.value) || 0;

                const credit =
                    parseFloat(creditInput?.value) || 0;

                totalDebit += debit;
                totalCredit += credit;
            });


        document.getElementById('totalDebit').textContent =
            totalDebit.toFixed(2);

        document.getElementById('totalCredit').textContent =
            totalCredit.toFixed(2);


        const indicator =
            document.getElementById('balanceIndicator');


        if (Math.abs(totalDebit - totalCredit) > 0.01) {

            indicator.innerHTML =
                '<span class="text-red-600">' +
                '<i class="fas fa-times-circle"></i> ' +
                'Unbalanced' +
                '</span>';

        } else {

            indicator.innerHTML =
                '<span class="text-green-600">' +
                '<i class="fas fa-check-circle"></i> ' +
                'Balanced' +
                '</span>';
        }
    }


    /* ---------------------------------------------------------
       Add journal line
    --------------------------------------------------------- */

    function addLine() {

        const tbody =
            document.getElementById('journalLinesBody');

        const rows =
            tbody.querySelectorAll('.journal-line');

        const lastRow =
            rows[rows.length - 1];

        if (!lastRow) {
            return;
        }


        const newRow =
            lastRow.cloneNode(true);


        const inputs =
            newRow.querySelectorAll('input, select');


        inputs.forEach(function(input) {

            const name =
                input.getAttribute('name');


            if (name) {

                input.setAttribute(
                    'name',
                    name.replace(
                        /\[\d+\]/g,
                        '[' + lineIndex + ']'
                    )
                );
            }


            if (input.tagName === 'INPUT') {

                if (input.type === 'number') {

                    input.value = '0.00';

                } else if (input.type === 'text') {

                    input.value = '';
                }
            }


            if (input.tagName === 'SELECT') {

                input.selectedIndex = 0;
            }


            input.classList.remove('border-red-500');

            const existingError =
                input.parentElement.querySelector(
                    '.line-validation-error'
                );

            if (existingError) {
                existingError.remove();
            }
        });


        tbody.appendChild(newRow);

        lineIndex++;

        calculateTotals();

        initializeLineValidation(newRow);
    }


    /* ---------------------------------------------------------
       Remove journal line
    --------------------------------------------------------- */

    function removeLine(button) {

        const rows =
            document.querySelectorAll('.journal-line');


        if (rows.length <= 1) {

            const row =
                button.closest('.journal-line');

            row.querySelector('.account-select').value = '';

            row.querySelector('.debit-input').value =
                '0.00';

            row.querySelector('.credit-input').value =
                '0.00';

            row.querySelector('.line-description').value =
                '';

        } else {

            button.closest('.journal-line').remove();
        }


        calculateTotals();
    }


    /* ---------------------------------------------------------
       Voucher number preview
    --------------------------------------------------------- */

    function updateVoucherNo() {

        const select =
            document.getElementById('voucherType');

        const display =
            document.getElementById('voucherNoDisplay');


        if (select.value) {

            const text =
                select.options[select.selectedIndex].text;

            const match =
                text.match(/\(([^)]+)\)/);

            const prefix =
                match ? match[1] : '';

            const fy =
                new Date()
                .getFullYear()
                .toString()
                .slice(-2);

            display.value =
                prefix + '-' + fy + '-****';

        } else {

            display.value = '';
        }
    }


    /* ---------------------------------------------------------
       Input filtering
    --------------------------------------------------------- */

    $(document).on(
        'input',
        'input[name="reference_no"]',
        function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z0-9 .,_\/#@()\-]/g,
                ''
            );

            $(this).val(value);
        }
    );


    $(document).on(
        'input',
        'textarea[name="description"], .line-description',
        function() {

            let value = $(this).val();

            value = value.replace(
                /[^\x20-\x7E\r\n]/g,
                ''
            );

            $(this).val(value);
        }
    );


    /* ---------------------------------------------------------
       Amount input validation while typing
    --------------------------------------------------------- */

    $(document).on(
        'input',
        '.debit-input, .credit-input',
        function() {

            let value = $(this).val();

            value = value.replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            if (parts.length > 2) {

                value =
                    parts[0] + '.' +
                    parts.slice(1).join('');
            }


            if (parts.length === 2) {

                value =
                    parts[0] + '.' +
                    parts[1].substring(0, 2);
            }


            if (parseFloat(value) < 0) {
                value = '0';
            }


            $(this).val(value);

            calculateTotals();
        }
    );


    /* ---------------------------------------------------------
       Prevent Debit + Credit in same line
    --------------------------------------------------------- */

    $(document).on(
        'input',
        '.debit-input, .credit-input',
        function() {

            const row =
                $(this).closest('.journal-line');

            const debit =
                parseFloat(
                    row.find('.debit-input').val()
                ) || 0;

            const credit =
                parseFloat(
                    row.find('.credit-input').val()
                ) || 0;


            if (
                $(this).hasClass('debit-input') &&
                debit > 0
            ) {

                row.find('.credit-input').val('0.00');

            }


            if (
                $(this).hasClass('credit-input') &&
                credit > 0
            ) {

                row.find('.debit-input').val('0.00');
            }


            calculateTotals();
        }
    );


    /* ---------------------------------------------------------
       Initialize dynamic line validation
    --------------------------------------------------------- */

    function initializeLineValidation(row) {

        const account =
            row.querySelector('.account-select');

        const debit =
            row.querySelector('.debit-input');

        const credit =
            row.querySelector('.credit-input');


        if (account) {

            $(account).on('change', function() {

                $(this).valid();
            });
        }


        if (debit) {

            $(debit).on('input', function() {

                $(this).valid();
            });
        }


        if (credit) {

            $(credit).on('input', function() {

                $(this).valid();
            });
        }
    }


    /* ---------------------------------------------------------
       jQuery Validation
    --------------------------------------------------------- */

    $(document).ready(function() {


        $.validator.addMethod(
            "validVoucherDate",
            function(value, element) {

                if (this.optional(element)) {
                    return true;
                }

                return /^\d{4}-\d{2}-\d{2}$/.test(value);

            },
            "Please enter a valid voucher date"
        );


        $.validator.addMethod(
            "validAmount",
            function(value, element) {

                if (value === '') {
                    return true;
                }

                return /^\d+(?:\.\d{1,2})?$/.test(value);

            },
            "Enter a valid amount with maximum 2 decimal places"
        );


        $("#journalForm").validate({

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block line-validation-error",

            rules: {

                voucher_type_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                voucher_date: {
                    required: true,
                    validVoucherDate: true
                },

                reference_no: {
                    maxlength: 100
                },

                description: {
                    maxlength: 500
                }
            },


            messages: {

                voucher_type_id: {
                    required: "Please select a voucher type",
                    digits: "Please select a valid voucher type",
                    min: "Please select a valid voucher type"
                },

                voucher_date: {
                    required: "Voucher date is required",
                    validVoucherDate: "Please enter a valid voucher date"
                },

                reference_no: {
                    maxlength: "Reference number cannot exceed 100 characters"
                },

                description: {
                    maxlength: "Description cannot exceed 500 characters"
                }
            },


            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    element.parent().append(error);

                } else {

                    error.insertAfter(element);
                }
            },


            highlight: function(element) {

                $(element).addClass(
                    "border-red-500"
                );
            },


            unhighlight: function(element) {

                $(element).removeClass(
                    "border-red-500"
                );
            },


            submitHandler: function(form) {

                let valid = true;

                let totalDebit = 0;
                let totalCredit = 0;

                const usedAccounts = {};


                document
                    .querySelectorAll('.journal-line')
                    .forEach(function(row, index) {

                        const lineNo = index + 1;

                        const account =
                            row.querySelector(
                                '.account-select'
                            );

                        const debitInput =
                            row.querySelector(
                                '.debit-input'
                            );

                        const creditInput =
                            row.querySelector(
                                '.credit-input'
                            );


                        const accountValue =
                            account?.value || '';

                        const debit =
                            parseFloat(
                                debitInput?.value
                            ) || 0;

                        const credit =
                            parseFloat(
                                creditInput?.value
                            ) || 0;


                        // Account required

                        if (!accountValue) {

                            valid = false;

                            $(account).addClass(
                                'border-red-500'
                            );

                            if (
                                !row.querySelector(
                                    '.account-error'
                                )
                            ) {

                                $(account)
                                    .parent()
                                    .append(
                                        '<span class="text-red-500 text-sm mt-1 block account-error">' +
                                        'Please select an account' +
                                        '</span>'
                                    );
                            }

                        } else {

                            $(account).removeClass(
                                'border-red-500'
                            );

                            $(account)
                                .parent()
                                .find('.account-error')
                                .remove();


                            // Duplicate account

                            if (usedAccounts[accountValue]) {

                                valid = false;

                                $(account).addClass(
                                    'border-red-500'
                                );

                                if (
                                    !row.querySelector(
                                        '.duplicate-account-error'
                                    )
                                ) {

                                    $(account)
                                        .parent()
                                        .append(
                                            '<span class="text-red-500 text-sm mt-1 block duplicate-account-error">' +
                                            'Duplicate accounts are not allowed' +
                                            '</span>'
                                        );
                                }

                            } else {

                                usedAccounts[accountValue] = true;

                                $(account)
                                    .parent()
                                    .find(
                                        '.duplicate-account-error'
                                    )
                                    .remove();
                            }
                        }


                        // Both debit and credit

                        if (
                            debit > 0 &&
                            credit > 0
                        ) {

                            valid = false;

                            $(debitInput).addClass(
                                'border-red-500'
                            );

                            $(creditInput).addClass(
                                'border-red-500'
                            );

                            if (
                                !row.querySelector(
                                    '.amount-error'
                                )
                            ) {

                                $(creditInput)
                                    .parent()
                                    .append(
                                        '<span class="text-red-500 text-sm mt-1 block amount-error">' +
                                        'A line cannot contain both debit and credit' +
                                        '</span>'
                                    );
                            }

                        } else {

                            $(debitInput).removeClass(
                                'border-red-500'
                            );

                            $(creditInput).removeClass(
                                'border-red-500'
                            );

                            $(row)
                                .find('.amount-error')
                                .remove();
                        }


                        // At least one amount

                        if (
                            debit <= 0 &&
                            credit <= 0
                        ) {

                            valid = false;

                            $(debitInput).addClass(
                                'border-red-500'
                            );

                            $(creditInput).addClass(
                                'border-red-500'
                            );

                            if (
                                !row.querySelector(
                                    '.zero-amount-error'
                                )
                            ) {

                                $(creditInput)
                                    .parent()
                                    .append(
                                        '<span class="text-red-500 text-sm mt-1 block zero-amount-error">' +
                                        'Enter either debit or credit amount' +
                                        '</span>'
                                    );
                            }

                        } else {

                            $(row)
                                .find('.zero-amount-error')
                                .remove();
                        }


                        totalDebit += debit;
                        totalCredit += credit;
                    });


                // Balance validation

                if (
                    Math.abs(
                        totalDebit - totalCredit
                    ) > 0.001
                ) {

                    valid = false;

                    const balanceMessage =
                        'Total debit (' +
                        totalDebit.toFixed(2) +
                        ') must equal total credit (' +
                        totalCredit.toFixed(2) +
                        ').';


                    $('#balanceIndicator')
                        .html(
                            '<span class="text-red-600">' +
                            '<i class="fas fa-times-circle"></i> ' +
                            'Unbalanced' +
                            '</span>'
                        );


                    if (
                        !$('#journalBalanceError').length
                    ) {

                        $('#journalLinesTable')
                            .after(
                                '<span id="journalBalanceError" ' +
                                'class="text-red-500 text-sm mt-2 block">' +
                                balanceMessage +
                                '</span>'
                            );

                    } else {

                        $('#journalBalanceError')
                            .text(balanceMessage);
                    }

                } else {

                    $('#journalBalanceError')
                        .remove();

                    $('#balanceIndicator')
                        .html(
                            '<span class="text-green-600">' +
                            '<i class="fas fa-check-circle"></i> ' +
                            'Balanced' +
                            '</span>'
                        );
                }


                if (!valid) {

                    return false;
                }


                form.submit();
            }

        });


        /*
         * Initialize first row
         */
        initializeLineValidation(
            document.querySelector('.journal-line')
        );


        /*
         * Calculate initial totals
         */
        calculateTotals();


        /*
         * Update voucher number on page load
         */
        updateVoucherNo();

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>