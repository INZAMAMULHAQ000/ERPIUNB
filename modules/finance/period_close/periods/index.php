<?php

/**
 * F12 — Accounting Period Management (close / reopen with validation)
 */
$pageTitle = 'Accounting Periods';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('period.view')) {
    die('Access Denied');
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        to('modules/finance/period_close/periods/index.php');
    }
    $fyId = (int)($_POST['fy_id'] ?? 0);
    $periodId = (int)($_POST['period_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'close' && hasPermission('period.close')) {
        $res = PeriodCloseService::closePeriod($db, $fyId, $periodId, $_SESSION['user_id']);
        if (!$res['success']) {
            flash('error', $res['message']);
            if (!empty($res['validation']['checks'])) {
                foreach ($res['validation']['checks'] as $c) {
                    if ($c['status'] === 'fail') flash('error', '✗ ' . $c['name'] . ': ' . $c['detail']);
                }
            }
        } else {
            flash('success', $res['message']);
        }
    } elseif ($action === 'reopen' && hasPermission('period.reopen')) {
        $res = PeriodCloseService::reopenPeriod($db, $fyId, $periodId, $_SESSION['user_id'], trim($_POST['reason'] ?? ''));
        flash($res['success'] ? 'success' : 'error', $res['message']);
    } elseif ($action === 'generate') {
        $r = PeriodCloseService::ensurePeriodsExist($db, $fyId);
        flash($r['created'] ? 'success' : 'info', $r['created'] ? "{$r['created']} period(s) generated." : 'Periods already exist.');
    }
    to('modules/finance/period_close/periods/index.php?fy_id=' . $fyId);
}

// FY selector
$fys = $db->query("SELECT id, year_name, status FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) {
    $a = getActiveFinancialYear($db);
    $fyId = $a ? (int)$a['id'] : (int)($fys[0]['id'] ?? 0);
}
$currentFY = null;
foreach ($fys as $f) if ($f['id'] == $fyId) $currentFY = $f;

$periods = [];
if ($fyId) {
    PeriodCloseService::ensureChecklistItems($db, $fyId); // year-level templates ready
    $s = $db->prepare("
        SELECT p.*,
          (SELECT COUNT(*) FROM journal_entries j WHERE j.voucher_date BETWEEN p.start_date AND p.end_date AND j.status='draft') draft_journals,
          u.name AS closed_by_name
        FROM accounting_periods p LEFT JOIN users u ON u.id = p.closed_by
        WHERE p.financial_year_id = ? ORDER BY p.start_date
    ");
    $s->execute([$fyId]);
    $periods = $s->fetchAll();
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Accounting Periods</h1>
        <p class="text-gray-600">Open → Closing → Closed. Closed periods block all posting.</p>
    </div>
    <div class="flex gap-2">
        <?php if ($fyId && hasPermission('period.create')): ?>
            <form method="post"><input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken()) ?>"><input type="hidden" name="action" value="generate"><input type="hidden" name="fy_id" value="<?= $fyId ?>">
                <button class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-wand-magic-sparkles mr-1"></i>Generate Missing Periods</button>
            </form>
        <?php endif; ?>
        <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<form method="get" class="mb-4">
    <div class="flex items-center gap-3">

        
        <div class="relative w-64">

            <select
                id="fy_id"
                name="fy_id"
                onchange="this.form.submit()"
                class="w-full appearance-none
                       border border-gray-300
                       bg-white
                       px-3 py-2 pr-9
                       text-sm text-gray-700
                       cursor-pointer
                       focus:outline-none
                       focus:border-blue-500
                       focus:ring-1 focus:ring-blue-500">

                <?php foreach ($fys as $f): ?>
                    <option
                        value="<?= (int)$f['id'] ?>"
                        <?= (int)$f['id'] === $fyId ? 'selected' : '' ?>>
                        <?= sanitize($f['year_name']) ?>
                        (<?= strtoupper(sanitize($f['status'])) ?>)
                    </option>
                <?php endforeach; ?>

            </select>

            <!-- Single Down Arrow -->
            <!-- <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                <i class="fas fa-chevron-down text-xs text-gray-500"></i>
            </span> -->

        </div>

    </div>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Period</th>
                <th class="px-4 py-3 text-left">Dates</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-center">Draft Journals</th>
                <th class="px-4 py-3 text-left">Closed By / At</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($periods as $p):
                $color = $p['status'] === 'open' ? 'bg-green-100 text-green-800' : ($p['status'] === 'closing' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-700');
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= sanitize($p['period_name']) ?></td>
                    <td class="px-4 py-3"><?= date('d M', strtotime($p['start_date'])) ?> – <?= date('d M Y', strtotime($p['end_date'])) ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold <?= $color ?>"><?= strtoupper($p['status']) ?></span></td>
                    <td class="px-4 py-3 text-center <?= $p['draft_journals'] > 0 ? 'text-orange-600 font-semibold' : 'text-gray-400' ?>"><?= (int)$p['draft_journals'] ?></td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= $p['closed_at'] ? date('d M Y H:i', strtotime($p['closed_at'])) . ($p['closed_by_name'] ? ' · ' . sanitize($p['closed_by_name']) : '') : '—' ?>
                        <?= $p['reopen_count'] > 0 ? '<br><span class="text-orange-500">reopened ×' . $p['reopen_count'] . '</span>' : '' ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <?php if ($p['status'] !== 'closed' && in_array($currentFY['status'] ?? '', ['active', 'closing']) && hasPermission('period.close')): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Validate and close period <?= sanitize($p['period_name']) ?>?');">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrfToken()) ?>"> <input type="hidden" name="action" value="close"><input type="hidden" name="fy_id" value="<?= $fyId ?>"><input type="hidden" name="period_id" value="<?= $p['id'] ?>">
                                <button class="text-blue-600 hover:underline text-xs font-medium"><i class="fas fa-lock mr-1"></i>Close</button>
                            </form>
                        <?php elseif ($p['status'] === 'closed' && !in_array($currentFY['status'] ?? '', ['closed', 'archived']) && hasPermission('period.reopen')): ?>
                            <button data-modal-open="reopen<?= $p['id'] ?>" class="text-orange-600 hover:underline text-xs font-medium"><i class="fas fa-lock-open mr-1"></i>Reopen</button>
                        <?php else: ?>
                            <span class="text-gray-300 text-xs">—</span>
                        <?php endif; ?>
                        <a href="../../journal_entries/index.php" class="text-gray-400 hover:text-blue-600 ml-2" title="Journals in this period" target="_blank"><i class="fas fa-book"></i></a>
                    </td>
                </tr>

                <?php if ($p['status'] === 'closed'): ?>
                    <div id="reopen<?= $p['id'] ?>" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-start justify-center pt-24">
                        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                            <div class="px-4 py-3 border-b font-semibold">Reopen <?= sanitize($p['period_name']) ?></div>
                            <form method="post" class="p-4 space-y-3">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="action" value="reopen"><input type="hidden" name="fy_id" value="<?= $fyId ?>"><input type="hidden" name="period_id" value="<?= $p['id'] ?>">
                                <p class="text-sm text-gray-600">Reopening is audited with your user, timestamp and reason. It is never silent.</p>
                                <textarea name="reason" required rows="3" placeholder="Reason (required)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                                <div class="flex gap-2 justify-end">
                                    <button type="button" data-modal-close class="px-3 py-2 border rounded text-sm">Cancel</button>
                                    <button class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 text-sm">Reopen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($periods)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No periods for this financial year. Use “Generate Missing Periods”.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.querySelectorAll('[data-modal-open]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById(el.getAttribute('data-modal-open')).classList.remove('hidden');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function(el) {
        el.addEventListener('click', function() {
            el.closest('.fixed').classList.add('hidden');
        });
    });
</script>

<?php require_once '../../../../includes/footer.php'; ?>