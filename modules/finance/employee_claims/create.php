<?php
$pageTitle = 'Submit Expense Claim';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('employee_claims.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$categories = $db->query("
    SELECT id, name
    FROM expense_categories
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();

$employees = $db->query("
    SELECT id, first_name, last_name, employee_code
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$projects = $db->query("
    SELECT id, project_name
    FROM projects
    WHERE status = 'active'
    ORDER BY project_name
")->fetchAll();


// Generate claim number
$prefix = 'CLM' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM employee_expense_claims
    WHERE claim_number LIKE ?
");

$stmt->execute([
    $prefix . '%'
]);

$claim_number =
    $prefix . '-' .
    str_pad(
        (int)$stmt->fetchColumn() + 1,
        3,
        '0',
        STR_PAD_LEFT
    );


// Max upload size
$maxUploadSize = 5 * 1024 * 1024; // 5MB

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/zip',
    'application/x-zip-compressed'
];


// Map validated MIME type to safe extension
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip',
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }


    $employee_id = (int)($_POST['employee_id'] ?? 0);

    $category_id = (int)($_POST['category_id'] ?? 0);

    $project_id = !empty($_POST['project_id'])
        ? (int)$_POST['project_id']
        : null;

    $expense_date = trim(
        $_POST['expense_date'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $amount = (float)str_replace(
        ',',
        '',
        $_POST['amount'] ?? 0
    );

    $tax_amount = (float)str_replace(
        ',',
        '',
        $_POST['tax_amount'] ?? 0
    );

    $total_amount = $amount + $tax_amount;


    /*
    |--------------------------------------------------------------------------
    | Server-side validation
    |--------------------------------------------------------------------------
    */

    if ($employee_id <= 0) {
        $errors[] = 'Please select an employee.';
    }

    if ($category_id <= 0) {
        $errors[] = 'Please select a category.';
    }

    if (empty($expense_date)) {
        $errors[] = 'Expense date is required.';
    }

    if ($amount < 0) {
        $errors[] = 'Amount cannot be negative.';
    }

    if ($tax_amount < 0) {
        $errors[] = 'Tax amount cannot be negative.';
    }

    if ($total_amount <= 0) {
        $errors[] = 'Total amount must be greater than zero.';
    }


    /*
    |--------------------------------------------------------------------------
    | Active Financial Year Guard
    |--------------------------------------------------------------------------
    */

    $fy = getActiveFinancialYear($db);

    if (
        $fy
        && !empty($expense_date)
        && (
            $expense_date < $fy['start_date']
            || $expense_date > $fy['end_date']
        )
    ) {

        $errors[] =
            'Expense date must be within the active financial year ('
            . date(
                'M d, Y',
                strtotime($fy['start_date'])
            )
            . ' - '
            . date(
                'M d, Y',
                strtotime($fy['end_date'])
            )
            . ').';
    }


    /*
    |--------------------------------------------------------------------------
    | File Upload
    |--------------------------------------------------------------------------
    */

    $uploadedFiles = [];

    $finfo = class_exists('finfo')
        ? new finfo(FILEINFO_MIME_TYPE)
        : null;


    if (!empty($_FILES['receipts']['name'][0])) {

        $files = $_FILES['receipts'];

        $fileCount = count($files['name']);


        for ($i = 0; $i < $fileCount; $i++) {

            if ($files['error'][$i] === UPLOAD_ERR_OK) {

                $fileSize = $files['size'][$i];

                $originalName = $files['name'][$i];


                if ($fileSize > $maxUploadSize) {

                    $errors[] =
                        'File "'
                        . sanitize($originalName)
                        . '" exceeds maximum upload size of 5MB.';

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Detect real MIME type
                |--------------------------------------------------------------------------
                */

                $detectedType = $finfo
                    ? ($finfo->file($files['tmp_name'][$i]) ?: '')
                    : '';


                if (
                    !in_array(
                        $detectedType,
                        $allowedMimeTypes,
                        true
                    )
                ) {

                    $errors[] =
                        'File type not allowed for "'
                        . sanitize($originalName)
                        . '". Allowed: JPG, PNG, GIF, PDF, ZIP.';

                    continue;
                }


                $uploadedFiles[] = [

                    'tmp_name' =>
                    $files['tmp_name'][$i],

                    'name' =>
                    $originalName,

                    'type' =>
                    $detectedType,

                    'size' =>
                    $fileSize,

                    'ext' =>
                    $mimeToExt[$detectedType],
                ];
            } elseif (
                $files['error'][$i]
                !== UPLOAD_ERR_NO_FILE
            ) {

                $errors[] =
                    'Upload error for file "'
                    . sanitize($files['name'][$i])
                    . '".';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Stop if errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/employee_claims/create.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Retry on concurrent claim-number collision
    |--------------------------------------------------------------------------
    */

    for ($attempt = 1; $attempt <= 5; $attempt++) {

        $db->beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Insert Claim
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                INSERT INTO employee_expense_claims
                (
                    claim_number,
                    employee_id,
                    expense_date,
                    category_id,
                    project_id,
                    description,
                    amount,
                    tax_amount,
                    total_amount,
                    status,
                    created_by,
                    created_at,
                    updated_at
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
                    ?,
                    ?,
                    'draft',
                    ?,
                    NOW(),
                    NOW()
                )
            ");


            $stmt->execute([

                $claim_number,

                $employee_id,

                $expense_date,

                $category_id,

                $project_id,

                $description ?: null,

                $amount,

                $tax_amount,

                $total_amount,

                $_SESSION['user_id']

            ]);


            $claim_id =
                (int)$db->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Process Uploaded Receipts
            |--------------------------------------------------------------------------
            */

            $uploadDir =
                '../../../uploads/expenses/';


            if (!empty($uploadedFiles)) {

                $attStmt = $db->prepare("
                    INSERT INTO expense_attachments
                    (
                        expense_type,
                        expense_id,
                        file_name,
                        original_name,
                        file_path,
                        file_size,
                        mime_type,
                        uploaded_by
                    )
                    VALUES
                    (
                        'employee_claim',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


                foreach ($uploadedFiles as $file) {

                    $fileName =
                        'claim_'
                        . $claim_id
                        . '_'
                        . time()
                        . '_'
                        . bin2hex(random_bytes(4))
                        . '.'
                        . $file['ext'];


                    $destPath =
                        $uploadDir . $fileName;


                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $destPath
                        )
                    ) {

                        throw new Exception(
                            'Failed to save uploaded file "'
                                . sanitize($file['name'])
                                . '".'
                        );
                    }


                    $attStmt->execute([

                        'employee_claim',

                        $claim_id,

                        $fileName,

                        $file['name'],

                        'uploads/expenses/' . $fileName,

                        $file['size'],

                        $file['type'],

                        $_SESSION['user_id']

                    ]);
                }
            }


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
                'Created expense claim: ' . $claim_number,
                'employee_claims'
            );


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            flash(
                'success',
                'Expense claim '
                    . $claim_number
                    . ' created successfully.',
                'success'
            );


            redirect(
                'view.php?id=' . $claim_id
            );
        } catch (Exception $e) {

            $db->rollBack();


            $isDupNumber =
                $e instanceof PDOException
                && (int)$e->getCode() === 23000
                && stripos(
                    $e->getMessage(),
                    'claim_number'
                ) !== false;


            if (
                $isDupNumber
                && $attempt < 5
            ) {

                /*
                |--------------------------------------------------------------------------
                | Regenerate claim number
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM employee_expense_claims
                    WHERE claim_number LIKE ?
                ");

                $stmt->execute([
                    $prefix . '%'
                ]);


                $claim_number =
                    $prefix
                    . '-'
                    . str_pad(
                        (int)$stmt->fetchColumn() + 1,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );


                continue;
            }


            flash(
                'error',
                'Error: ' . $e->getMessage()
            );


            to(
                'modules/finance/employee_claims/create.php'
            );
        }
    }
}
?>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Submit Expense Claim
        </h1>

        <p class="text-gray-600">
            Submit an employee expense reimbursement claim
        </p>

    </div>


    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
    </a>

</div>


<!-- =========================================================
     SERVER SIDE ERRORS
========================================================= -->

<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p><?= sanitize($e) ?></p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     FORM
========================================================= -->

<form
    method="POST"
    enctype="multipart/form-data"
    id="expenseClaimForm">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!-- =====================================================
         CLAIM DETAILS
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Claim Details
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Claim Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Claim Number

                </label>

                <input
                    type="text"
                    value="<?= sanitize($claim_number) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500"
                    readonly>

            </div>


            <!-- Employee -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Employee

                    <span class="text-red-500">*</span>

                </label>


                <select
                    name="employee_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

                    <option value="">
                        -- Select Employee --
                    </option>


                    <?php foreach ($employees as $e): ?>

                        <option
                            value="<?= $e['id'] ?>"
                            <?= old('employee_id') == $e['id'] ? 'selected' : '' ?>>
                            <?= sanitize(
                                $e['first_name']
                                    . ' '
                                    . $e['last_name']
                                    . ' ('
                                    . $e['employee_code']
                                    . ')'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Expense Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Expense Date

                    <span class="text-red-500">*</span>

                </label>


                <input
                    type="date"
                    name="expense_date"
                    value="<?= sanitize(
                                old(
                                    'expense_date',
                                    date('Y-m-d')
                                )
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

            </div>


            <!-- Category -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Category

                    <span class="text-red-500">*</span>

                </label>


                <select
                    name="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

                    <option value="">
                        -- Select Category --
                    </option>


                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?= $cat['id'] ?>"
                            <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Project -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Project

                </label>


                <select
                    name="project_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>


                    <?php foreach ($projects as $p): ?>

                        <option
                            value="<?= $p['id'] ?>"
                            <?= old('project_id') == $p['id'] ? 'selected' : '' ?>>
                            <?= sanitize($p['project_name']) ?>
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
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                placeholder="Describe the expense"><?= sanitize(old('description')) ?></textarea>

        </div>

    </div>


    <!-- =====================================================
         AMOUNT
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Amount
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">


            <!-- Amount -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Amount

                    <span class="text-red-500">*</span>

                </label>


                <input
                    type="number"
                    name="amount"
                    step="0.01"
                    min="0.01"
                    value="<?= sanitize(
                                old('amount', '0')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

            </div>


            <!-- Tax Amount -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Tax Amount

                </label>


                <input
                    type="number"
                    name="tax_amount"
                    step="0.01"
                    min="0"
                    value="<?= sanitize(
                                old('tax_amount', '0')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


        </div>

    </div>


    <!-- =====================================================
         RECEIPTS / ATTACHMENTS
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">

            Receipts / Attachments

        </h3>


        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">

            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>


            <p class="text-sm text-gray-600 mb-2">

                Drag & drop files here or click to browse

            </p>


            <p class="text-xs text-gray-500 mb-4">

                Allowed: JPG, PNG, GIF, PDF, ZIP
                (max 5MB each)

            </p>


            <input
                type="file"
                name="receipts[]"
                multiple
                accept=".jpg,.jpeg,.png,.gif,.pdf,.zip"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">

        </div>

    </div>


    <!-- =====================================================
         BUTTONS
    ====================================================== -->

    <div class="flex justify-end gap-4 mb-8">

        <a
            href="index.php"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
            Cancel
        </a>


        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-save mr-2"></i>

            Create Claim

        </button>

    </div>

</form>


<!-- =========================================================
     JQUERY VALIDATION
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<style>
    /*
    |--------------------------------------------------------------------------
    | Validation Error
    |--------------------------------------------------------------------------
    */

    #expenseClaimForm p.error {

        display: block !important;

        width: 100%;

        margin-top: 4px !important;

        margin-bottom: 0 !important;

        clear: both;

    }


    /*
    |--------------------------------------------------------------------------
    | Invalid Input
    |--------------------------------------------------------------------------
    */

    #expenseClaimForm input.error,
    #expenseClaimForm select.error,
    #expenseClaimForm textarea.error {

        border-color: #ef4444 !important;

    }
</style>


<script>
    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Expense Claim Validation
        |--------------------------------------------------------------------------
        */

        $("#expenseClaimForm").validate({

            ignore: [],


            /*
            |--------------------------------------------------------------------------
            | Rules
            |--------------------------------------------------------------------------
            */

            rules: {

                employee_id: {

                    required: true

                },


                expense_date: {

                    required: true

                },


                category_id: {

                    required: true

                },


                amount: {

                    required: true,

                    number: true,

                    min: 0.01

                },


                tax_amount: {

                    number: true,

                    min: 0

                },


                description: {

                    maxlength: 500

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Messages
            |--------------------------------------------------------------------------
            */

            messages: {

                employee_id: {

                    required: "Please select an employee."

                },


                expense_date: {

                    required: "Expense date is required."

                },


                category_id: {

                    required: "Please select a category."

                },


                amount: {

                    required: "Amount is required.",

                    number: "Please enter a valid amount.",

                    min: "Amount must be greater than zero."

                },


                tax_amount: {

                    number: "Please enter a valid tax amount.",

                    min: "Tax amount cannot be negative."

                },


                description: {

                    maxlength: "Description cannot exceed 500 characters."

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */

            errorElement: "p",


            errorClass: "error text-red-500 text-sm mt-1 block",


            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | Select dropdown validation message is appended
            | inside the parent DIV.
            |
            | Therefore message appears BELOW dropdown.
            |
            |--------------------------------------------------------------------------
            */

            errorPlacement: function(error, element) {


                if (element.is("select")) {

                    /*
                    |--------------------------------------------------------------------------
                    | Select
                    |--------------------------------------------------------------------------
                    */

                    element.parent().append(error);


                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Input / Textarea
                    |--------------------------------------------------------------------------
                    */

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
                    .addClass("border-red-500")
                    .removeClass("border-gray-300");

            },


            /*
            |--------------------------------------------------------------------------
            | Remove Highlight
            |--------------------------------------------------------------------------
            */

            unhighlight: function(element) {

                $(element)
                    .removeClass("border-red-500")
                    .addClass("border-gray-300");

            },


            /*
            |--------------------------------------------------------------------------
            | Submit
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Receipt Client-side Validation
        |--------------------------------------------------------------------------
        |
        | Server-side validation remains the final validation.
        |
        */

        $('input[name="receipts[]"]').on('change', function() {

            const files = this.files;

            const maxSize = 5 * 1024 * 1024;

            const allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'gif',
                'pdf',
                'zip'
            ];


            /*
            |--------------------------------------------------------------------------
            | Remove previous custom file error
            |--------------------------------------------------------------------------
            */

            $(this)
                .siblings('.receipt-error')
                .remove();


            for (let i = 0; i < files.length; i++) {

                const file = files[i];

                const fileName = file.name;

                const extension =
                    fileName
                    .split('.')
                    .pop()
                    .toLowerCase();


                /*
                |--------------------------------------------------------------------------
                | File Size
                |--------------------------------------------------------------------------
                */

                if (file.size > maxSize) {

                    showReceiptError(
                        "File \"" +
                        fileName +
                        "\" exceeds maximum upload size of 5MB."
                    );

                    this.value = '';

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | File Extension
                |--------------------------------------------------------------------------
                */

                if (
                    !allowedExtensions.includes(
                        extension
                    )
                ) {

                    showReceiptError(
                        "File type not allowed for \"" +
                        fileName +
                        "\". Allowed: JPG, PNG, GIF, PDF, ZIP."
                    );

                    this.value = '';

                    return false;
                }

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Receipt Error
        |--------------------------------------------------------------------------
        */

        function showReceiptError(message) {

            $('input[name="receipts[]"]')
                .parent()
                .append(
                    '<p class="receipt-error text-red-500 text-sm mt-2">' +
                    escapeHtml(message) +
                    '</p>'
                );

        }


        /*
        |--------------------------------------------------------------------------
        | Escape HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(text) {

            return $('<div>')
                .text(text)
                .html();

        }


    });
</script>


<?php require_once '../../../includes/footer.php'; ?>