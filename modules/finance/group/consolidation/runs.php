<?php

/** F13 — Consolidation Runs */
$pageTitle = 'Consolidation Runs';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../ConsolidationService.php';

if (!hasPermission('consolidation.create') && !hasPermission('company.view')) {
    die('Access Denied');
}
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('consolidation.create')) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        to('modules/finance/group/consolidation/runs.php');
    }
    $ids = array_map('intval', $_POST['company_ids'] ?? []);
    if (!$ids) {
        flash('error', 'Select at least one company.');
        to('modules/finance/group/consolidation/runs.php');
    }
    $res = ConsolidationService::createRun($db, [
        'group_id' => (int)($_POST['group_id'] ?? 0),
        'period_label' => trim($_POST['period_label'] ?? '') ?: date('Y-m'),
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'group_currency' => strtoupper($_POST['group_currency'] ?? 'AED'),
        'translation_method' => in_array($_POST['translation_method'] ?? '', ['closing', 'average', 'historical']) ? $_POST['translation_method'] : 'closing',
        'company_ids' => $ids,
        'created_by' => $_SESSION['user_id'],
    ]);
    flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Run created — open the workspace.' : $res['message']);
    if (!empty($res['run_id'])) to('modules/finance/group/consolidation/workspace.php?id=' . (int)$res['run_id']);
    to('modules/finance/group/consolidation/runs.php');
}

$groups = $db->query("SELECT * FROM company_groups WHERE status='active'")->fetchAll();
$companies = $db->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll();
$runs = $db->query("
    SELECT cr.*, g.group_name, u.name created_by_name
    FROM consolidation_runs cr JOIN company_groups g ON g.id=cr.group_id LEFT JOIN users u ON u.id=cr.created_by
    ORDER BY cr.id DESC LIMIT 50
")->fetchAll();
$statusStyles = [
    'draft' => 'bg-gray-100 text-gray-600',
    'in_progress' => 'bg-blue-100 text-blue-700',
    'validated' => 'bg-cyan-100 text-cyan-700',
    'approved' => 'bg-green-100 text-green-700',
    'posted' => 'bg-emerald-100 text-emerald-700',
    'cancelled' => 'bg-red-100 text-red-700'
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Consolidation Runs</h1>
        <p class="text-gray-600">Group statements built from company ledgers + elimination layer</p>
    </div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<?php if (hasPermission('consolidation.create')): ?>

    <form
        method="post"
        id="consolidationForm"
        novalidate
        class="bg-white rounded-lg shadow p-5 mb-6">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <!-- Main Form Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">

            <!-- Group -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Group <span class="text-red-600">*</span>
                </label>

                <select
                    name="group_id"
                    id="group_id"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       bg-white focus:outline-none focus:ring-2
                       focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($groups as $g): ?>

                        <option value="<?= (int)$g['id'] ?>">
                            <?= sanitize($g['group_name']) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>


            <!-- Period Label -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Period Label <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="period_label"
                    id="period_label"
                    placeholder="e.g. FY2030"
                    maxlength="20"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- From Date -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    From <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="start_date"
                    id="start_date"
                    required
                    value="<?= date('Y-01-01') ?>"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- To Date -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    To <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="end_date"
                    id="end_date"
                    required
                    value="<?= date('Y-m-t') ?>"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">
            </div>


            <!-- Translation -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Translation <span class="text-red-600">*</span>
                </label>

                <select
                    name="translation_method"
                    id="translation_method"
                    class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm
                       bg-white focus:outline-none focus:ring-2
                       focus:ring-blue-500 focus:border-blue-500">
                    <option value="closing">Closing</option>
                    <option value="average">Average</option>
                    <option value="historical">Historical</option>
                </select>
            </div>


            <!-- Create Button -->
            <div class="flex items-end">

                <button
                    type="submit"
                    class="w-full h-10 bg-blue-600 text-white px-4 rounded-md
                       hover:bg-blue-700 transition text-sm font-medium
                       whitespace-nowrap">
                    <i class="fas fa-plus mr-1.5"></i>
                    Create Run
                </button>

            </div>

        </div>


        <!-- Companies in Scope -->
        <div class="mt-5 pt-4 border-t border-gray-200">

            <label class="block text-xs font-medium text-gray-600 mb-2">
                Companies in Scope <span class="text-red-600">*</span>
            </label>

            <div class="flex flex-wrap gap-2">

                <?php foreach ($companies as $c): ?>

                    <label
                        class="inline-flex items-center gap-2
                           bg-gray-50 border border-gray-200
                           rounded-md px-3 py-2
                           text-sm text-gray-700
                           cursor-pointer hover:bg-gray-100
                           transition">

                        <input
                            type="checkbox"
                            name="company_ids[]"
                            value="<?= (int)$c['id'] ?>"
                            checked
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded
                               focus:ring-blue-500">

                        <span>
                            <?= sanitize($c['company_name']) ?>
                            <span class="text-xs text-gray-400">
                                (<?= sanitize($c['base_currency']) ?>)
                            </span>
                        </span>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>

    </form>

<?php endif; ?>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3">#</th>
                <th class="px-4 py-3 text-left">Period</th>
                <th class="px-4 py-3 text-left">Group</th>
                <th class="px-4 py-3 text-left">Window</th>
                <th class="px-4 py-3 text-left">Method</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Net Profit</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($runs as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono"><?= (int)$r['id'] ?></td>
                    <td class="px-4 py-3 font-medium"><?= sanitize($r['period_label']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($r['group_name']) ?></td>
                    <td class="px-4 py-3 text-xs"><?= date('d M Y', strtotime($r['start_date'])) ?> – <?= date('d M Y', strtotime($r['end_date'])) ?></td>
                    <td class="px-4 py-3 text-xs uppercase"><?= sanitize($r['translation_method']) ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$r['status']] ?>"><?= strtoupper($r['status']) ?></span></td>
                    <td class="px-4 py-3 text-right font-mono <?= ($r['net_profit'] ?? 0) >= 0 ? '' : 'text-red-600' ?>"><?= $r['net_profit'] !== null ? number_format((float)$r['net_profit'], 2) : '—' ?></td>
                    <td class="px-4 py-3 text-right"><a href="workspace.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">Workspace →</a></td>
                </tr>
            <?php endforeach;
            if (empty($runs)): ?><tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No runs yet.</td>
                </tr><?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    #consolidationForm p.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
    }

    #consolidationForm input.error,
    #consolidationForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #consolidationForm input.valid,
    #consolidationForm select.valid {
        border-color: #d1d5db !important;
    }

    #consolidationForm .company-error {
        margin-top: 6px !important;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
         * To date must be greater than or equal to From date
         */
        $.validator.addMethod(
            "validDateRange",
            function(value, element) {

                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();

                if (!startDate || !endDate) {
                    return true;
                }

                return endDate >= startDate;
            },
            "To date must be on or after From date."
        );


        /*
         * At least one company must be selected
         */
        $.validator.addMethod(
            "companySelected",
            function() {

                return $('input[name="company_ids[]"]:checked').length > 0;

            },
            "Please select at least one company."
        );


        /*
         * Consolidation Form Validation
         */
        $('#consolidationForm').validate({

            rules: {

                group_id: {
                    required: true
                },

                period_label: {
                    required: true,
                    minlength: 3,
                    maxlength: 20
                },

                start_date: {
                    required: true
                },

                end_date: {
                    required: true,
                    validDateRange: true
                },

                translation_method: {
                    required: true
                },

                'company_ids[]': {
                    companySelected: true
                }
            },


            messages: {

                group_id: {
                    required: "Please select group."
                },

                period_label: {
                    required: "Please enter period label.",
                    minlength: "Period label must be at least 3 characters.",
                    maxlength: "Period label cannot exceed 20 characters."
                },

                start_date: {
                    required: "Please select from date."
                },

                end_date: {
                    required: "Please select to date.",
                    validDateRange: "To date must be on or after From date."
                },

                translation_method: {
                    required: "Please select translation method."
                },

                'company_ids[]': {
                    companySelected: "Please select at least one company."
                }
            },


            errorElement: 'p',
            errorClass: 'error',


            /*
             * Error always appears below the field
             */
            errorPlacement: function(error, element) {

                if (element.is(':checkbox')) {

                    error.addClass('company-error');

                    error.insertAfter(
                        $('#consolidationForm .flex.flex-wrap').first()
                    );

                } else {

                    error.insertAfter(element);

                }
            },


            /*
             * Highlight invalid field
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
         * Revalidate To date when From date changes
         */
        $('#start_date').on('change', function() {

            $('#end_date').valid();

        });


        /*
         * Revalidate To date when To date changes
         */
        $('#end_date').on('change', function() {

            $('#end_date').valid();

        });


        /*
         * Revalidate companies when checkbox changes
         */
        $('input[name="company_ids[]"]').on('change', function() {

            $('input[name="company_ids[]"]').first().valid();

        });

    });
</script>

<?php require_once '../../../../includes/footer.php'; ?>