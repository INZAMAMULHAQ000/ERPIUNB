<?php
$pageTitle = 'Currencies & FX Rates';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('settings.view')) { die('Access Denied'); }

$db = getDB();
$canManage = hasPermission('settings.manage_currencies');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid request'); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_currency') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            if (!preg_match('/^[A-Z]{3}$/', $code)) { throw new RuntimeException('Currency code must be 3 letters (ISO 4217), e.g. USD'); }
            $name = trim($_POST['name'] ?? '');
            if ($name === '') { throw new RuntimeException('Name is required'); }
            $dec  = max(0, min(4, (int) ($_POST['decimal_places'] ?? 2)));
            $db->prepare("INSERT INTO currencies (code, name, symbol, decimal_places, sort_order) VALUES (?,?,?,?,?)")
               ->execute([$code, $name, trim($_POST['symbol'] ?? '') ?: ($code . ' '), $dec, 999]);
            logAudit($_SESSION['user_id'], 'Added currency ' . $code, 'settings');
            flash('ok', "Currency $code added.", 'success');
        } elseif ($action === 'add_rate') {
            $code = strtoupper(trim($_POST['rate_code'] ?? ''));
            $rate = (float) ($_POST['rate'] ?? 0);
            $date = trim($_POST['effective_date'] ?? '') ?: date('Y-m-d');
            if (!isset(getCurrencies()[$code])) { throw new RuntimeException('Unknown currency'); }
            if ($rate <= 0) { throw new RuntimeException('Rate must be greater than zero'); }
            $db->prepare("
                INSERT INTO currency_rates (currency_code, rate_to_base, effective_date, source, created_by)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE rate_to_base = VALUES(rate_to_base), source = VALUES(source)
            ")->execute([$code, $rate, $date, trim($_POST['source'] ?? '') ?: 'manual', $_SESSION['user_id']]);
            logAudit($_SESSION['user_id'], "Set $code rate $rate effective $date", 'settings');
            flash('ok', "Rate for $code saved.", 'success');
        } elseif ($action === 'toggle') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $db->prepare("UPDATE currencies SET is_active = 1 - is_active WHERE code = ? AND code <> ?")
               ->execute([$code, getBaseCurrency()]);
            flash('ok', 'Currency status updated.', 'success');
        }
    } catch (Throwable $e) {
        flash('err', $e->getMessage(), 'error');
    }
    to('modules/settings/currencies.php');
}

$rows = $db->query("
    SELECT c.code, c.name, c.symbol, c.decimal_places, c.is_active, c.is_base,
           (SELECT cr.rate_to_base   FROM currency_rates cr WHERE cr.currency_code = c.code ORDER BY cr.effective_date DESC, cr.id DESC LIMIT 1) AS rate,
           (SELECT cr.effective_date FROM currency_rates cr WHERE cr.currency_code = c.code ORDER BY cr.effective_date DESC, cr.id DESC LIMIT 1) AS rate_date,
           (SELECT COUNT(*)          FROM currency_rates cr WHERE cr.currency_code = c.code) AS rate_count
    FROM currencies c
    ORDER BY c.is_active DESC, c.sort_order, c.code
")->fetchAll();

$base = getBaseCurrency();
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Currencies &amp; FX Rates</h1>
        <p class="text-gray-500 text-sm mt-1">
            Base currency is <span class="font-semibold"><?= htmlspecialchars($base) ?></span>
            <?php if ($active = activeCompany()): ?>
            (from <?= htmlspecialchars($active['company_name']) ?>)
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= url('modules/settings/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Configuration</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Code</th>
                        <th class="text-left px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Currency</th>
                        <th class="text-right px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Rate to <?= htmlspecialchars($base) ?></th>
                        <th class="text-left px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Effective</th>
                        <th class="text-center px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <?php if ($canManage): ?><th class="text-right px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($rows as $r): $isBase = $r['code'] === $base; ?>
                    <tr class="hover:bg-slate-50 <?= $r['is_active'] ? '' : 'opacity-50' ?>">
                        <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-700"><?= htmlspecialchars($r['code']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-slate-800"><?= htmlspecialchars($r['name']) ?></span>
                            <span class="text-slate-400 text-xs ml-1.5"><?= $r['symbol'] ?> · <?= (int) $r['decimal_places'] ?>dp</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-xs">
                            <?php if ($isBase): ?>
                                <span class="text-slate-400">1.0000 (base)</span>
                            <?php elseif ($r['rate'] !== null): ?>
                                <span class="font-medium text-slate-800"><?= number_format((float) $r['rate'], 4) ?></span>
                            <?php else: ?>
                                <span class="text-red-500 text-xs">no rate</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <?= $r['rate_date'] ? date('d M Y', strtotime($r['rate_date'])) : '—' ?>
                            <span class="text-slate-300">(<?= (int) $r['rate_count'] ?>)</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($isBase): ?><span class="px-2 py-0.5 text-[11px] rounded-full bg-indigo-100 text-indigo-700">BASE</span>
                            <?php elseif ((int) $r['is_active'] === 1): ?><span class="px-2 py-0.5 text-[11px] rounded-full bg-green-100 text-green-800">Active</span>
                            <?php else: ?><span class="px-2 py-0.5 text-[11px] rounded-full bg-gray-100 text-gray-600">Inactive</span><?php endif; ?>
                        </td>
                        <?php if ($canManage): ?>
                        <td class="px-4 py-3 text-right">
                            <?php if (!$isBase): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="code" value="<?= htmlspecialchars($r['code']) ?>">
                                <button class="text-xs text-slate-400 hover:text-indigo-600" title="<?= (int) $r['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fas <?= (int) $r['is_active'] === 1 ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canManage): ?>
    <div class="space-y-5">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4"><i class="fas fa-chart-line text-emerald-500 mr-1.5 text-xs"></i>Add / Update Rate</h3>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add_rate">
                <select name="rate_code" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                    <?php foreach ($rows as $r): if ($r['code'] === $base) continue; ?>
                    <option value="<?= htmlspecialchars($r['code']) ?>"><?= htmlspecialchars($r['code']) ?> — <?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="rate" type="number" step="0.00000001" min="0.00000001" required placeholder="Rate to <?= htmlspecialchars($base) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <input name="effective_date" type="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <input name="source" placeholder="source" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <p class="text-[11px] text-slate-400">Rates are dated, so history is kept and a rate change never re-values past postings.</p>
                <button class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Save Rate</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4"><i class="fas fa-plus text-emerald-500 mr-1.5 text-xs"></i>Add Currency</h3>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add_currency">
                <input name="code" required maxlength="3" placeholder="Code (e.g. CHF)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm uppercase">
                <input name="name" required placeholder="Name" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <input name="symbol" placeholder="Symbol" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <input name="decimal_places" type="number" min="0" max="4" value="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <button class="w-full px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900 transition">Add Currency</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
