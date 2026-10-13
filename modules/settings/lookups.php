<?php
$pageTitle = 'Lookup Values';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('settings.manage_lookups')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid request'); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $type = trim($_POST['type_key'] ?? '');
            $key  = trim($_POST['value_key'] ?? '');
            $lbl  = trim($_POST['value_label'] ?? '');
            if ($type === '' || $key === '' || $lbl === '') { throw new RuntimeException('Type, key and label are all required'); }
            $days = isset($_POST['days']) && $_POST['days'] !== '' ? (int) $_POST['days'] : null;
            $extra = $days !== null ? json_encode(['days' => $days]) : null;
            $max = (int) $db->query("SELECT COALESCE(MAX(sort_order),0) FROM lookup_values WHERE type_key = " . $db->quote($type))->fetchColumn();
            $db->prepare("INSERT INTO lookup_values (type_key, value_key, value_label, extra, sort_order) VALUES (?,?,?,?,?)")
               ->execute([$type, $key, $lbl, $extra, $max + 10]);
            flash('ok', 'Value added.', 'success');
            logAudit($_SESSION['user_id'], "Added lookup $type.$key", 'settings');
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            // Deactivating rather than deleting keeps historical documents readable.
            $db->prepare("UPDATE lookup_values SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            flash('ok', 'Value status updated.', 'success');
        } elseif ($action === 'rename') {
            $id = (int) ($_POST['id'] ?? 0);
            $lbl = trim($_POST['value_label'] ?? '');
            if ($lbl === '') { throw new RuntimeException('Label cannot be empty'); }
            $db->prepare("UPDATE lookup_values SET value_label = ? WHERE id = ?")->execute([$lbl, $id]);
            flash('ok', 'Label updated.', 'success');
        }
    } catch (Throwable $e) {
        flash('err', $e->getMessage(), 'error');
    }
    to('modules/settings/lookups.php' . (isset($_POST['type_key']) ? '?type=' . urlencode($_POST['type_key']) : ''));
}

$type = $_GET['type'] ?? '';
$types = $db->query("
    SELECT t.type_key, t.label, t.group_name, t.description,
           (SELECT COUNT(*) FROM lookup_values v WHERE v.type_key = t.type_key AND v.is_active = 1) AS active_count,
           (SELECT COUNT(*) FROM lookup_values v WHERE v.type_key = t.type_key) AS total_count
    FROM lookup_types t WHERE t.is_active = 1
    ORDER BY t.group_name, t.label
")->fetchAll();

if ($type === '' && $types) { $type = $types[0]['type_key']; }

$values = [];
$current = null;
if ($type !== '') {
    $cs = $db->prepare("SELECT * FROM lookup_types WHERE type_key = ?");
    $cs->execute([$type]);
    $current = $cs->fetch();
    $vs = $db->prepare("SELECT * FROM lookup_values WHERE type_key = ? ORDER BY sort_order, value_label");
    $vs->execute([$type]);
    $values = $vs->fetchAll();
}

$groups = [];
foreach ($types as $t) { $groups[$t['group_name']][] = $t; }
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Lookup Values</h1>
        <p class="text-gray-500 text-sm mt-1">The option lists behind the customer, company and trading-term forms</p>
    </div>
    <a href="<?= url('modules/settings/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Configuration</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
    <!-- Type navigator -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 self-start">
        <?php foreach ($groups as $g => $items): ?>
            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mt-3 mb-1.5 first:mt-0"><?= htmlspecialchars($g) ?></p>
            <?php foreach ($items as $t): ?>
            <a href="?type=<?= urlencode($t['type_key']) ?>"
               class="flex items-center justify-between px-3 py-2 rounded-lg text-sm mb-0.5 transition <?= $type === $t['type_key'] ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50' ?>">
                <span class="truncate"><?= htmlspecialchars($t['label']) ?></span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 ml-2"><?= (int) $t['active_count'] ?></span>
            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- Values -->
    <div class="lg:col-span-3 space-y-5">
        <?php if (!$current): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-8 text-center text-slate-400">No lookup types found.</div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h2 class="text-sm font-bold text-slate-800 mb-1"><?= htmlspecialchars($current['label']) ?></h2>
            <p class="text-xs text-slate-400 mb-4"><span class="font-mono"><?= htmlspecialchars($current['type_key']) ?></span> — <?= htmlspecialchars($current['description'] ?: 'No description') ?></p>

            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100">
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Key (stored in records)</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Label (shown in forms)</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Extra</th>
                    <th class="text-center text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Status</th>
                    <th class="text-right text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($values as $v): $extra = $v['extra'] ? (json_decode($v['extra'], true) ?: []) : []; ?>
                    <tr class="<?= $v['is_active'] ? '' : 'opacity-45' ?>">
                        <td class="py-2.5 font-mono text-xs text-slate-500"><?= htmlspecialchars($v['value_key']) ?></td>
                        <td class="py-2.5 pr-3">
                            <form method="post" class="flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                <input type="hidden" name="type_key" value="<?= htmlspecialchars($type) ?>">
                                <input name="value_label" value="<?= htmlspecialchars($v['value_label']) ?>" class="flex-1 px-2 py-1.5 border border-slate-200 rounded text-sm">
                                <button class="px-2.5 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded">Save</button>
                            </form>
                        </td>
                        <td class="py-2.5 text-xs text-slate-500 font-mono"><?= $extra ? htmlspecialchars(json_encode($extra)) : '—' ?></td>
                        <td class="py-2.5 text-center">
                            <span class="px-2 py-0.5 text-[11px] rounded-full <?= $v['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>"><?= $v['is_active'] ? 'Active' : 'Inactive' ?></span>
                        </td>
                        <td class="py-2.5 text-right">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                <input type="hidden" name="type_key" value="<?= htmlspecialchars($type) ?>">
                                <button class="text-slate-400 hover:text-indigo-600 text-xs" title="Toggle active">
                                    <i class="fas <?= $v['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-[11px] text-slate-400 mt-3"><i class="fas fa-circle-info mr-1"></i>Values are deactivated, never deleted — records already storing a key must stay readable.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4"><i class="fas fa-plus text-indigo-500 mr-1.5 text-xs"></i>Add Value to <?= htmlspecialchars($current['label']) ?></h3>
            <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type_key" value="<?= htmlspecialchars($type) ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Key</label>
                    <input name="value_key" required placeholder="net_120" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm font-mono">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Label</label>
                    <input name="value_label" required placeholder="Net 120" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Days</label>
                    <input name="days" type="number" min="0" placeholder="120" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Add</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
