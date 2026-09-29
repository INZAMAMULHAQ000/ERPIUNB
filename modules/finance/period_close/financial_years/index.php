<?php

/**
 * F12 — Financial Year Management (list / create / edit / close / reopen)
 */
$pageTitle = 'Financial Years — Period Close';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';
require_once __DIR__ . '/../YearEndClosingService.php';

if (!hasPermission('period.view')) {
    die('Access Denied');
}

$db = getDB();

// —— Handle POST actions (create / update / start-closing / reopen-year) ——
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        to('modules/finance/period_close/financial_years/index.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' && hasPermission('period.create')) {
        $name = trim($_POST['year_name'] ?? '');
        $code = trim($_POST['year_code'] ?? '');
        $from = $_POST['start_date'] ?? '';
        $toD  = $_POST['end_date'] ?? '';
        if (!$name || !$from || !$toD || $from >= $toD) {
            flash('error', 'Valid name and date range required.');
        } else {
            try {
                $s = $db->prepare("INSERT INTO financial_years (year_name, year_code, start_date, end_date, status) VALUES (?, ?, ?, ?, 'closed')");
                $s->execute([$name, $code ?: null, $from, $toD]);
                PeriodCloseService::ensurePeriodsExist($db, (int)$db->lastInsertId());
                logAudit($_SESSION['user_id'], "Created financial year $name", 'finance');
                PeriodCloseService::log($db, 'fy_created', (int)$db->lastInsertId(), null, null, $name, null, $_SESSION['user_id']);
                flash('success', 'Financial year created (inactive). Activate it from the list.');
            } catch (Exception $e) {
                flash('error', 'Create failed: ' . $e->getMessage());
            }
        }
    }

    if ($action === 'activate') {
        $fyId = (int)$_POST['fy_id'];
        try {
            $db->beginTransaction();
            $db->exec("UPDATE financial_years SET status='closed' WHERE status='active'");
            $u = $db->prepare("UPDATE financial_years SET status='active' WHERE id = ?");
            $u->execute([$fyId]);
            PeriodCloseService::log($db, 'fy_activated', $fyId, null, null, null, null, $_SESSION['user_id']);
            logAudit($_SESSION['user_id'], 'Activated financial year ID ' . $fyId, 'finance');
            $db->commit();
            flash('success', 'Financial year activated.');
        } catch (Exception $e) {
            $db->rollBack();
            flash('error', $e->getMessage());
        }
    }

    if ($action === 'start_closing' && hasPermission('year.close')) {
        $fyId = (int)$_POST['fy_id'];
        $u = $db->prepare("UPDATE financial_years SET status='closing' WHERE id=? AND status='active'");
        $u->execute([$fyId]);
        PeriodCloseService::log($db, 'fy_closing_started', $fyId, null, null, null, null, $_SESSION['user_id']);
        flash('success', 'Year moved to CLOSING state. Close all periods, complete the checklist, then run Year-End Close.');
    }

    if ($action === 'reopen_year' && hasPermission('year.reopen')) {
        $res = YearEndClosingService::reopenYear($db, (int)$_POST['fy_id'], $_SESSION['user_id'], trim($_POST['reason'] ?? ''));
        flash($res['success'] ? 'success' : 'error', $res['message']);
    }

    to('modules/finance/period_close/financial_years/index.php');
}

// —— List ——
$fys = $db->query("
    SELECT f.*, u.name AS closed_by_name,
      (SELECT COUNT(*) FROM accounting_periods p WHERE p.financial_year_id = f.id) period_count,
      (SELECT COUNT(*) FROM accounting_periods p WHERE p.financial_year_id = f.id AND p.status='closed') periods_closed,
      yec.status yec_status, yec.net_profit
    FROM financial_years f
    LEFT JOIN users u ON u.id = f.closed_by
    LEFT JOIN year_end_closings yec ON yec.financial_year_id = f.id
    ORDER BY f.start_date DESC
")->fetchAll();

$statusColors = [
    'active' => 'bg-green-100 text-green-800',
    'closing' => 'bg-yellow-100 text-yellow-800',
    'closed' => 'bg-gray-200 text-gray-700',
    'archived' => 'bg-red-100 text-red-700',
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Financial Years</h1>
        <p class="text-gray-600">Fiscal year lifecycle: Open → Closing → Closed → Archived</p>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('period.create')): ?>
            <button data-modal-open="createModal" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-plus mr-2"></i>New Financial Year</button>
        <?php endif; ?>
        <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Year</th>
                <th class="px-4 py-3 text-left">Code</th>
                <th class="px-4 py-3 text-left">Period</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Periods</th>
                <th class="px-4 py-3 text-right">Net P/L</th>
                <th class="px-4 py-3 text-left">Closed</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($fys as $fy): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= sanitize($fy['year_name']) ?></td>
                    <td class="px-4 py-3 text-gray-500"><?= sanitize($fy['year_code'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= date('d M Y', strtotime($fy['start_date'])) ?> – <?= date('d M Y', strtotime($fy['end_date'])) ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold <?= $statusColors[$fy['status']] ?? '' ?>"><?= strtoupper($fy['status']) ?></span></td>
                    <td class="px-4 py-3"><?= (int)$fy['periods_closed'] ?>/<?= (int)$fy['period_count'] ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $fy['yec_status'] === 'completed' ? (($fy['net_profit'] >= 0) ? 'text-green-600' : 'text-red-600') : 'text-gray-300' ?>">
                        <?= $fy['yec_status'] === 'completed' ? formatMoney($fy['net_profit']) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= $fy['closed_at'] ? date('d M Y H:i', strtotime($fy['closed_at'])) . ($fy['closed_by_name'] ? '<br>' . sanitize($fy['closed_by_name']) : '') : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <?php if ($fy['status'] === 'closed' && $fy['yec_status'] !== 'completed'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Activate this financial year? Only one can be active.')">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="action" value="activate"><input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                <button class="text-green-600 hover:underline text-xs font-medium">Activate</button>
                            </form>
                        <?php elseif ($fy['status'] === 'active'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Move this year into CLOSING state?')">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="action" value="start_closing"><input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                <button class="text-blue-600 hover:underline text-xs font-medium" <?= hasPermission('year.close') ? '' : 'disabled title="No permission"' ?>>Start Closing</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($fy['status'] === 'closed' && $fy['yec_status'] === 'completed' && hasPermission('year.reopen')): ?>
                            <button data-modal-open="reopen<?= $fy['id'] ?>" class="text-orange-600 hover:underline text-xs font-medium ml-2">Reopen</button>
                        <?php endif; ?>

                        <?php if (!empty($fy['yec_status'])): ?>
                            <a href="../history/index.php?fy_id=<?= $fy['id'] ?>" class="text-gray-400 hover:text-blue-600 ml-2" title="Closing history"><i class="fas fa-clock-rotate-left"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if ($fy['status'] === 'closed' && $fy['yec_status'] === 'completed'): ?>
                    <!-- reopen modal -->
                    <div id="reopen<?= $fy['id'] ?>" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-start justify-center pt-24">
                        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                            <div class="px-4 py-3 border-b font-semibold">Reopen <?= sanitize($fy['year_name']) ?></div>
                            <form method="post" class="p-4 space-y-3">
                                <?= csrfToken() ?>
                                <input type="hidden" name="action" value="reopen_year"><input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                <p class="text-sm text-gray-600">The year will return to CLOSING state. Closing history is preserved; use reversal actions in Closing History for corrections.</p>
                                <textarea name="reason" required rows="3" placeholder="Reason (required, audited)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                                <div class="flex gap-2 justify-end">
                                    <button type="button" data-modal-close class="px-3 py-2 border rounded text-sm">Cancel</button>
                                    <button class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 text-sm">Reopen Year</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- create modal -->
<?php if (hasPermission('period.create')): ?>
    <div id="createModal" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-start justify-center pt-24">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-4 py-3 border-b font-semibold">New Financial Year</div>
            <form method="post" class="p-4 space-y-3">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken()) ?>"> <input type="hidden" name="action" value="create">
                <input name="year_name" required placeholder="Name e.g. FY 2028-2029" class="w-full border rounded px-3 py-2 text-sm">
                <input name="year_code" placeholder="Code e.g. 2829 (optional)" class="w-full border rounded px-3 py-2 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <input type="date" name="start_date" required class="border rounded px-3 py-2 text-sm">
                    <input type="date" name="end_date" required class="border rounded px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" data-modal-close class="px-3 py-2 border rounded text-sm">Cancel</button>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">Create</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

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