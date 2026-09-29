<?php
/**
 * F12 — Closing Reports hub — STEP 19
 */
$pageTitle = 'Closing Reports';
require_once '../../../../includes/header.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$reports = [
    ['period.php',             'fa-lock',                'Period Closing Report',      'Full validation trail for any closed period'],
    ['year_end.php',           'fa-flag-checkered',      'Year-End Closing Report',    'Closing journal, net P/L, retained earnings transfer'],
    ['checklist.php',          'fa-list-check',          'Closing Checklist Report',   'Checklist completion status and sign-offs'],
    ['opening_balances.php',   'fa-forward',             'Opening Balance Report',     'Carry-forward balances and verification result'],
    ['closing_journal.php',    'fa-book',                'Closing Journal Report',     'Year-end closing journals with all lines'],
    ['unreconciled_accounts.php','fa-triangle-exclamation','Unreconciled Accounts',     'Exception items and suspense balances'],
    ['yoy_comparison.php',     'fa-chart-column',        'Year-over-Year Comparison',  'Multi-year financials with variance'],
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Closing Reports</h1>
        <p class="text-gray-600">Period-close and year-end reporting suite</p>
    </div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($reports as $r): ?>
    <a href="<?= $r[0] ?>" class="bg-white rounded-lg shadow p-5 hover:shadow-md transition block">
        <span class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mb-3"><i class="fas <?= $r[1] ?>"></i></span>
        <h3 class="font-semibold text-gray-800"><?= $r[2] ?></h3>
        <p class="text-sm text-gray-500 mt-1"><?= $r[3] ?></p>
    </a>
<?php endforeach; ?>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
