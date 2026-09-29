<?php
/**
 * F12 Report 5 — Closing Journal Report
 */
$pageTitle = 'Closing Journal Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();
$closings = $db->query("
    SELECT y.*, f.year_name, je.voucher_no, je.voucher_date, je.description, je.status je_status,
           cu.name AS closed_by_name, rev.voucher_no AS reversal_voucher_no
    FROM year_end_closings y
    JOIN financial_years f ON f.id = y.financial_year_id
    LEFT JOIN journal_entries je ON je.id = y.closing_journal_id
    LEFT JOIN users cu ON cu.id = y.closed_by
    LEFT JOIN journal_entries rev ON rev.reference_no = je.voucher_no AND rev.status='posted'
    ORDER BY f.start_date DESC
")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Closing Journal Report</h1>
    <p class="text-gray-600 text-sm">All year-end closing journals — history is immutable; corrections use reversals.</p></div>
    <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
</div>

<div class="space-y-6">
<?php foreach ($closings as $c):
    if (!$c['closing_journal_id']) continue;
    $l = $db->prepare("
        SELECT jed.*, coa.account_code, coa.account_name FROM journal_entry_details jed
        JOIN chart_of_accounts coa ON coa.id = jed.account_id WHERE jed.journal_entry_id = ?
        ORDER BY jed.debit_amount DESC, jed.credit_amount DESC
    ");
    $l->execute([(int)$c['closing_journal_id']]);
    $lines = $l->fetchAll();
?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 bg-blue-50 border-b flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="font-semibold"><?= sanitize($c['year_name']) ?></span>
                <span class="ml-2 font-mono text-sm"><?= sanitize($c['voucher_no'] ?? '') ?></span>
                <?php if ($c['je_status']==='reversed'): ?><span class="ml-2 text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded">REVERSED → <?= sanitize($c['reversal_voucher_no'] ?? '') ?></span><?php endif; ?>
            </div>
            <div class="text-xs text-gray-500">
                Dated <?= date('d M Y', strtotime($c['voucher_date'])) ?> · Closed by <?= sanitize($c['closed_by_name'] ?? '') ?> on <?= date('d M Y', strtotime($c['closed_at'])) ?>
            </div>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y">
            <?php foreach ($lines as $ln): ?>
                <tr>
                    <td class="px-4 py-2 font-mono text-xs w-24"><?= sanitize($ln['account_code']) ?></td>
                    <td class="px-4 py-2"><?= sanitize($ln['account_name']) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= $ln['debit_amount']>0?number_format((float)$ln['debit_amount'],2):'' ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= $ln['credit_amount']>0?number_format((float)$ln['credit_amount'],2):'' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold"><tr>
                <td colspan="2" class="px-4 py-2">Total</td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format(array_sum(array_column($lines,'debit_amount')),2) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format(array_sum(array_column($lines,'credit_amount')),2) ?></td>
            </tr></tfoot>
        </table>
    </div>
<?php endforeach; ?>
<?php if (empty($closings)): ?><p class="text-center text-gray-400 py-10">No closing journals recorded.</p><?php endif; ?>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
