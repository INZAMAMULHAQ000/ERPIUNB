<?php

/** F13 — Intercompany Matching & Reconciliation */
$pageTitle = 'IC Matching & Reconciliation';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../IntercompanyService.php';

if (!hasPermission('intercompany.view')) {
    die('Access Denied');
}
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        to('modules/finance/group/intercompany/matching.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'auto_match' && hasPermission('intercompany.match')) {
        $n = IntercompanyService::autoMatch($db);
        flash($n ? 'success' : 'info', "$n transaction pair(s) auto-matched.");
    } elseif ($action === 'resolve' && hasPermission('intercompany.reconcile')) {
        $res = IntercompanyService::setMatchStatus($db, (int)$_POST['id'], $_POST['status'] ?? 'resolved', $_SESSION['user_id'], trim($_POST['reason'] ?? '') ?: null);
        flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Status updated.' : $res['message']);
    }
    to('modules/finance/group/intercompany/matching.php');
}

$txns = $db->query("
    SELECT t.*, s.company_name src_name, t2.company_name tgt_name
    FROM intercompany_transactions t
    JOIN companies s ON s.id=t.source_company_id JOIN companies t2 ON t2.id=t.target_company_id
    ORDER BY FIELD(t.match_status,'exception','unmatched','partially_matched','resolved','matched'), t.transaction_date DESC LIMIT 100
")->fetchAll();
$matrix = IntercompanyService::reconciliationMatrix($db);
$statusStyles = [
    'unmatched' => 'bg-gray-100 text-gray-600',
    'partially_matched' => 'bg-yellow-100 text-yellow-700',
    'matched' => 'bg-green-100 text-green-700',
    'exception' => 'bg-red-100 text-red-700',
    'resolved' => 'bg-blue-100 text-blue-700'
];
$coName = function ($id) use ($db) {
    static $c = null;
    if ($c === null) {
        $c = [];
        foreach ($db->query("SELECT id, company_name FROM companies") as $r) $c[$r['id']] = $r['company_name'];
    }
    return $c[$id] ?? "#$id";
};
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Intercompany Matching &amp; Reconciliation</h1>
        <p class="text-gray-600">Match one-sided entries; reconcile receivable vs payable per partner pair</p>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('intercompany.match')): ?>
            <form method="post"><input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken()) ?>" ><input type="hidden" name="action" value="auto_match">
                <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-wand-magic-sparkles mr-1"></i>Run Auto-Match</button>
            </form>
        <?php endif; ?>
        <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<!-- Reconciliation matrix -->
<div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
    <div class="px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800">Partner-Pair Reconciliation (GL truth)</h3>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Pair</th>
                <th class="px-4 py-3 text-right">Receivable (A books)</th>
                <th class="px-4 py-3 text-right">Payable (B books)</th>
                <th class="px-4 py-3 text-right">Difference</th>
                <th class="px-4 py-3 text-right">Diff %</th>
                <th class="px-4 py-3 text-left">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($matrix as $m): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><?= sanitize($coName($m['company_a'])) ?> ↔ <?= sanitize($coName($m['company_b'])) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= number_format($m['receivable_a'], 2) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= number_format($m['payable_b'], 2) ?></td>
                    <td class="px-4 py-2 text-right font-mono <?= $m['difference'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= number_format($m['difference'], 2) ?></td>
                    <td class="px-4 py-2 text-right <?= $m['difference_pct'] > 1 ? 'text-red-600' : 'text-gray-400' ?>"><?= $m['difference_pct'] ?>%</td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $m['status'] === 'MATCHED' ? 'bg-green-100 text-green-700' : ($m['status'] === 'EXCEPTION' ? 'bg-red-100 text-red-700' : 'bg-gray-100') ?>"><?= $m['status'] ?></span></td>
                </tr>
            <?php endforeach;
            if (empty($matrix)): ?><tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No IC pairs yet.</td>
                </tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Transactions needing attention -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-4 py-3 border-b">
        <h3 class="font-semibold text-gray-800">Transactions (exceptions &amp; unmatched first)</h3>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Number</th>
                <th class="px-4 py-3 text-left">Flow</th>
                <th class="px-4 py-3 text-right">Amount</th>
                <th class="px-4 py-3 text-left">Match</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($txns as $t): ?>
                <tr class="hover:bg-gray-50 <?= in_array($t['match_status'], ['exception', 'unmatched']) ? 'bg-red-50/40' : '' ?>">
                    <td class="px-4 py-2 font-mono text-xs"><?= sanitize($t['transaction_number']) ?></td>
                    <td class="px-4 py-2 text-xs"><?= sanitize($coName((int)$t['source_company_id'])) ?> → <?= sanitize($coName((int)$t['target_company_id'])) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$t['amount'], 2) ?> <?= sanitize($t['currency']) ?></td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$t['match_status']] ?>"><?= strtoupper(str_replace('_', ' ', $t['match_status'])) ?></span></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <?php if (hasPermission('intercompany.reconcile') && in_array($t['match_status'], ['exception', 'unmatched'])): ?>
                            <form method="post" class="inline" onsubmit="this.reason.value=prompt('Resolution reason?')||'';return this.reason.value.trim()!=='';">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="status" value="resolved"><input type="hidden" name="reason" value="">
                                <button class="text-blue-600 hover:underline text-xs">Resolve</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach;
            if (empty($txns)): ?><tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">Nothing to match.</td>
                </tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>