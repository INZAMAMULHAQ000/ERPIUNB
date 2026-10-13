<?php
$pageTitle = 'Configuration';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('settings.view')) { die('Access Denied'); }

$db = getDB();
$canEdit = hasPermission('settings.edit');

$errors = restoreFormErrors();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('csrf', 'Invalid request.', 'error'); to('modules/settings/index.php');
    }
    $saved = 0;
    $db->beginTransaction();
    try {
        foreach (($_POST['settings'] ?? []) as $key => $value) {
            $stmt = $db->prepare("SELECT value_type FROM app_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $type = $stmt->fetchColumn();
            if (!$type) { continue; }   // only keys that exist may be written
            if ($type === 'boolean') { $value = empty($value) ? '0' : '1'; }
            if ($type === 'json' && is_string($value) && $value !== '' && json_decode($value, true) === null) {
                throw new RuntimeException("'$key' must contain valid JSON");
            }
            $db->prepare("UPDATE app_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?")
               ->execute([$value === '' ? null : $value, $_SESSION['user_id'], $key]);
            $saved++;
        }
        $db->commit();
        logAudit($_SESSION['user_id'], "Updated $saved setting(s)", 'settings');
        flash('ok', "$saved setting(s) saved.", 'success');
        to('modules/settings/index.php');
    } catch (Throwable $e) {
        $db->rollBack();
        flash('db', 'Could not save: ' . $e->getMessage(), 'error');
        to('modules/settings/index.php');
    }
}

$settings = $db->query("SELECT * FROM app_settings ORDER BY setting_group, setting_key")->fetchAll();
$groups = [];
foreach ($settings as $s) { $groups[$s['setting_group']][] = $s; }

$stats = [
    'Currencies'      => [(int) $db->query("SELECT COUNT(*) FROM currencies WHERE is_active=1")->fetchColumn(), 'fa-money-bill-wave', 'text-emerald-500', 'active'],
    'FX rates'        => [(int) $db->query("SELECT COUNT(*) FROM currency_rates")->fetchColumn(), 'fa-chart-line', 'text-blue-500', 'dated entries'],
    'Doc sequences'   => [(int) $db->query("SELECT COUNT(*) FROM document_sequences")->fetchColumn(), 'fa-hashtag', 'text-amber-500', 'per company/type'],
    'Lookup types'    => [(int) $db->query("SELECT COUNT(*) FROM lookup_types WHERE is_active=1")->fetchColumn(), 'fa-list', 'text-violet-500', 'enumerations'],
    'Lookup values'   => [(int) $db->query("SELECT COUNT(*) FROM lookup_values WHERE is_active=1")->fetchColumn(), 'fa-database', 'text-indigo-500', 'configurable'],
    'Settings'        => [count($settings), 'fa-sliders', 'text-teal-500', 'stored keys'],
];

$cards = [];
$cards[] = ['href' => 'modules/settings/currencies.php', 'title' => 'Currencies & FX Rates', 'desc' => 'Active currencies, symbols, decimal places and dated conversion rates', 'icon' => 'fa-money-bill-wave', 'bg' => 'bg-emerald-500', 'count' => $stats['Currencies'][0], 'unit' => 'currencies'];
if (hasPermission('settings.manage_sequences')) {
    $cards[] = ['href' => 'modules/settings/sequences.php', 'title' => 'Document Numbering', 'desc' => 'Per-company prefixes, padding and next counter for every document type', 'icon' => 'fa-hashtag', 'bg' => 'bg-amber-500', 'count' => $stats['Doc sequences'][0], 'unit' => 'sequences'];
}
if (hasPermission('settings.manage_lookups')) {
    $cards[] = ['href' => 'modules/settings/lookups.php', 'title' => 'Lookup Values', 'desc' => 'Entity types, payment terms, tax categories and other form enumerations', 'icon' => 'fa-list', 'bg' => 'bg-violet-500', 'count' => $stats['Lookup values'][0], 'unit' => 'values'];
}
$active = activeCompany();
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Configuration</h1>
        <p class="text-gray-500 text-sm mt-1">Business values that used to live in code — now editable here</p>
    </div>
    <div class="text-xs text-slate-500">
        <i class="fas fa-building mr-1"></i>Active company:
        <span class="font-medium text-slate-700"><?= htmlspecialchars($active['company_name'] ?? 'none') ?></span>
    </div>
</div>

<!-- ===== Cards ===== -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
    <?php foreach ($cards as $c): ?>
    <a href="<?= url($c['href']) ?>" class="group bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 block">
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl <?= $c['bg'] ?> flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                <i class="fas <?= $c['icon'] ?> text-white"></i>
            </div>
            <i class="fas fa-arrow-right text-slate-300 group-hover:text-slate-500 group-hover:translate-x-1 transition-all text-sm mt-1"></i>
        </div>
        <h3 class="font-semibold text-slate-800 mb-0.5"><?= $c['title'] ?></h3>
        <p class="text-xs text-slate-400 leading-relaxed mb-4"><?= $c['desc'] ?></p>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($c['count']) ?></span>
            <span class="text-xs text-slate-400"><?= $c['unit'] ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ===== Settings form ===== -->
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <?php foreach ($groups as $groupName => $items): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i class="fas <?= $groupName === 'finance' ? 'fa-coins text-amber-500' : ($groupName === 'branding' ? 'fa-palette text-indigo-500' : ($groupName === 'inventory' ? 'fa-boxes text-teal-500' : 'fa-sliders text-slate-400')) ?> text-xs"></i>
            <?= htmlspecialchars(ucfirst($groupName)) ?>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($items as $s): $val = (string) ($s['setting_value'] ?? ''); ?>
            <div class="<?= $s['value_type'] === 'boolean' ? '' : '' ?>">
                <?php if ($s['value_type'] === 'boolean'): ?>
                    <label class="flex items-start gap-2.5 p-3 bg-slate-50 rounded-lg <?= $canEdit ? 'cursor-pointer hover:bg-slate-100' : 'opacity-80' ?>">
                        <input type="checkbox" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" value="1"
                               <?= $val === '1' || strtolower($val) === 'true' ? 'checked' : '' ?>
                               <?= $canEdit ? '' : 'disabled' ?> class="mt-0.5 rounded border-slate-300 text-indigo-600">
                        <span class="text-sm text-slate-700 leading-snug">
                            <?= htmlspecialchars($s['label'] ?: $s['setting_key']) ?>
                            <?php if ($s['description']): ?><span class="block text-[11px] text-slate-400"><?= htmlspecialchars($s['description']) ?></span><?php endif; ?>
                        </span>
                    </label>
                <?php else: ?>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">
                        <?= htmlspecialchars($s['label'] ?: $s['setting_key']) ?>
                        <span class="font-mono text-[10px] text-slate-300 ml-1"><?= htmlspecialchars($s['setting_key']) ?></span>
                    </label>
                    <input type="<?= $s['value_type'] === 'integer' ? 'number' : ($s['value_type'] === 'decimal' ? 'number' : 'text') ?>"
                           <?= $s['value_type'] === 'decimal' ? 'step="0.0001"' : '' ?>
                           name="settings[<?= htmlspecialchars($s['setting_key']) ?>]"
                           value="<?= htmlspecialchars($val) ?>"
                           <?= $canEdit ? '' : 'disabled' ?>
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none <?= $canEdit ? '' : 'opacity-60' ?>">
                    <?php if ($s['description']): ?><p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars($s['description']) ?></p><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($canEdit): ?>
    <div class="sticky bottom-0 bg-white border border-slate-100 rounded-xl shadow-lg p-4 flex items-center gap-3 mb-8">
        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-save mr-2"></i>Save Settings
        </button>
        <span class="text-xs text-slate-400">Changes apply immediately across every page.</span>
    </div>
    <?php else: ?>
    <p class="text-xs text-slate-400 mb-8"><i class="fas fa-lock mr-1"></i>Read-only — you have view access but cannot edit settings.</p>
    <?php endif; ?>
</form>

<!-- ===== Stats strip ===== -->
<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
    <?php foreach ($stats as $lbl => [$n, $icon, $colour, $sub]): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide"><?= $lbl ?></span>
            <i class="fas <?= $icon ?> <?= $colour ?> text-xs"></i>
        </div>
        <p class="text-xl font-bold text-slate-800"><?= number_format($n) ?></p>
        <p class="text-[11px] text-slate-400"><?= $sub ?></p>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
