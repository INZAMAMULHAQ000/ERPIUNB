<?php

/** F13 — Intercompany Transactions (create + list) */
$pageTitle = 'Intercompany Transactions';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../IntercompanyService.php';

if (!hasPermission('intercompany.view')) {
    die('Access Denied');
}
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('intercompany.create')) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        to('modules/finance/group/intercompany/transactions.php');
    }
    $res = IntercompanyService::createTransaction($db, [
        'source_company_id' => (int)($_POST['source_company_id'] ?? 0),
        'target_company_id' => (int)($_POST['target_company_id'] ?? 0),
        'transaction_date'  => $_POST['transaction_date'] ?? date('Y-m-d'),
        'transaction_type'  => in_array($_POST['transaction_type'] ?? '', ['sale', 'purchase', 'service', 'expense', 'loan', 'interest', 'transfer', 'other']) ? $_POST['transaction_type'] : 'other',
        'reference'         => trim($_POST['reference'] ?? ''),
        'description'       => trim($_POST['description'] ?? ''),
        'currency'          => strtoupper($_POST['currency'] ?? 'AED'),
        'amount'            => (float)($_POST['amount'] ?? 0),
        'tax_amount'        => (float)($_POST['tax_amount'] ?? 0),
        'user_id'           => $_SESSION['user_id'],
    ]);
    flash($res['success'] ? 'success' : 'error', $res['message']);
    to('modules/finance/group/intercompany/transactions.php');
}

$companies = $db->query("SELECT id, company_code, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$txns = $db->query("
    SELECT t.*, s.company_name src_name, t2.company_name tgt_name
    FROM intercompany_transactions t
    JOIN companies s ON s.id = t.source_company_id
    JOIN companies t2 ON t2.id = t.target_company_id
    ORDER BY t.id DESC LIMIT 100
")->fetchAll();
$statusStyles = [
    'unmatched' => 'bg-gray-100 text-gray-600',
    'partially_matched' => 'bg-yellow-100 text-yellow-700',
    'matched' => 'bg-green-100 text-green-700',
    'exception' => 'bg-red-100 text-red-700',
    'resolved' => 'bg-blue-100 text-blue-700'
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Intercompany Transactions</h1>
        <p class="text-gray-600">Counterparty entry auto-generated in the target company's books — always matched</p>
    </div>
    <div class="flex gap-2">
        <a href="matching.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-link mr-1"></i>Matching</a>
        <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<?php if (hasPermission('intercompany.create')): ?>

    <form
        method="post"
        id="intercompanyForm"
        novalidate
        class="bg-white rounded-lg shadow p-5 mb-6">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <!-- Form Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Date -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="transaction_date"
                    id="transaction_date"
                    value="<?= date('Y-m-d') ?>"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- Source Company -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    From (Source) <span class="text-red-600">*</span>
                </label>

                <select
                    name="source_company_id"
                    id="source_company_id"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm bg-white
               focus:outline-none focus:ring-2 focus:ring-blue-500
               focus:border-blue-500">

                    <option value="">Select source...</option>

                    <?php foreach ($companies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= sanitize($c['company_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>


            <!-- Target Company -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    To (Target) <span class="text-red-600">*</span>
                </label>

                <select
                    name="target_company_id"
                    id="target_company_id"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       bg-white focus:outline-none focus:ring-2
                       focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select target...</option>

                    <?php foreach ($companies as $c): ?>

                        <option value="<?= (int)$c['id'] ?>">
                            <?= sanitize($c['company_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>


            <!-- Transaction Type -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Transaction Type <span class="text-red-600">*</span>
                </label>

                <select
                    name="transaction_type"
                    id="transaction_type"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       bg-white focus:outline-none focus:ring-2
                       focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select type...</option>

                    <?php foreach (
                        ['sale', 'service', 'purchase', 'expense', 'loan', 'interest', 'transfer', 'other']
                        as $t
                    ): ?>

                        <option value="<?= $t ?>">
                            <?= sanitize(ucfirst($t)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>


            <!-- Amount -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Amount <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="amount"
                    id="amount"
                    placeholder="0.00"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- Reference -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Reference
                </label>

                <input
                    type="text"
                    name="reference"
                    id="reference"
                    maxlength="100"
                    placeholder="Transaction reference"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- Description -->
            <div class="lg:col-span-1">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Description
                </label>

                <input
                    type="text"
                    name="description"
                    id="description"
                    maxlength="500"
                    placeholder="Transaction description"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- Submit -->
            <div class="flex items-end">

                <button
                    type="submit"
                    class="w-full h-10 bg-blue-600 text-white px-4 rounded-md
                       hover:bg-blue-700 transition text-sm font-medium
                       whitespace-nowrap">
                    <i class="fas fa-plus mr-1.5"></i>
                    Counterpart
                </button>

            </div>

        </div>

    </form>

<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Number</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Flow</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-right">Amount</th>
                <th class="px-4 py-3 text-left">Match</th>
                <th class="px-4 py-3 text-left">Linked JE</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($txns as $t): ?>
                <tr class="hover:bg-gray-50 <?= strpos($t['transaction_number'], 'CP-') === 0 ? 'bg-orange-50/40' : '' ?>">
                    <td class="px-4 py-2 font-mono text-xs"><?= sanitize($t['transaction_number']) ?></td>
                    <td class="px-4 py-2"><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                    <td class="px-4 py-2 text-xs"><?= sanitize($t['src_name']) ?> → <?= sanitize($t['tgt_name']) ?></td>
                    <td class="px-4 py-2"><span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded uppercase"><?= sanitize($t['transaction_type']) ?></span></td>
                    <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$t['amount'], 2) ?> <?= sanitize($t['currency']) ?></td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$t['match_status']] ?>"><?= strtoupper(str_replace('_', ' ', $t['match_status'])) ?></span></td>
                    <td class="px-4 py-2 text-xs text-gray-400">#<?= (int)$t['source_journal_id'] ?> / #<?= (int)$t['target_journal_id'] ?></td>
                </tr>
            <?php endforeach;
            if (empty($txns)): ?><tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">No intercompany transactions yet.</td>
                </tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- jQuery Validation Plugin -->

<style>
    #intercompanyForm p.error {
        display: block !important;
        width: 100%;
        margin-top: 5px !important;
        margin-bottom: 0 !important;
        clear: both;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
    }

    #intercompanyForm input.error,
    #intercompanyForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #intercompanyForm input.valid,
    #intercompanyForm select.valid {
        border-color: #d1d5db !important;
    }
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
         * Source and Target company
         * must be different.
         */
        $.validator.addMethod(
            "differentCompany",
            function(value, element) {

                const sourceCompany = $('#source_company_id').val();
                const targetCompany = $('#target_company_id').val();

                if (!sourceCompany || !targetCompany) {
                    return true;
                }

                return sourceCompany !== targetCompany;
            },
            "Source and target companies must be different."
        );


        /*
         * Intercompany Transaction Form Validation
         */
        $('#intercompanyForm').validate({

            rules: {

                transaction_date: {
                    required: true
                },

                source_company_id: {
                    required: true
                },

                target_company_id: {
                    required: true,
                    differentCompany: true
                },

                transaction_type: {
                    required: true
                },

                amount: {
                    required: true,
                    number: true,
                    min: 0.01
                },

                reference: {
                    maxlength: 100
                },

                description: {
                    maxlength: 500
                }
            },


            messages: {

                transaction_date: {
                    required: "Please select transaction date."
                },

                source_company_id: {
                    required: "Please select source company."
                },

                target_company_id: {
                    required: "Please select target company."
                },

                transaction_type: {
                    required: "Please select transaction type."
                },

                amount: {
                    required: "Please enter amount.",
                    number: "Please enter a valid amount.",
                    min: "Amount must be greater than 0."
                },

                reference: {
                    maxlength: "Reference cannot exceed 100 characters."
                },

                description: {
                    maxlength: "Description cannot exceed 500 characters."
                }
            },


            errorElement: 'p',
            errorClass: 'error',

            errorPlacement: function(error, element) {

                if (
                    element.is('#source_company_id') ||
                    element.is('#target_company_id') ||
                    element.is('#transaction_type')
                ) {
                    error.insertAfter(element);
                } else {
                    error.insertAfter(element);
                }

            },


            /*
             * Highlight invalid fields
             */
            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },


            /*
             * Remove error when valid
             */
            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },


            /*
             * Submit only after validation
             */
            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
         * When source company changes,
         * validate target again.
         */
        $('#source_company_id').on('change', function() {

            $('#target_company_id').valid();

        });


        /*
         * When target company changes,
         * validate target again.
         */
        $('#target_company_id').on('change', function() {

            $('#target_company_id').valid();

        });

    });
</script>

<?php require_once '../../../../includes/footer.php'; ?>