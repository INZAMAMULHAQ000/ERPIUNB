<?php
$pageTitle = 'Document Numbering';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('settings.manage_sequences')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();

$docLabels = [
    'customer_invoice' => 'Customer Invoice', 'credit_note' => 'Credit Note', 'receipt' => 'Customer Receipt',
    'journal_entry' => 'Journal Voucher', 'purchase_order' => 'Purchase Order', 'purchase_request' => 'Purchase Requisition',
    'goods_receipt' => 'Goods Receipt Note', 'vendor_bill' => 'Vendor Bill', 'customer' => 'Customer Code', 'vendor' => 'Vendor Code',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid request'); }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['prefix'] ?? '')));
        $padding = max(1, min(10, (int) ($_POST['padding'] ?? 4)));
        $next = max(1, (int) ($_POST['next_number'] ?? 1));
        if ($prefix === '') { throw new RuntimeException('Prefix cannot be empty'); }

        $stmt = $db->prepare("SELECT company_id, doc_type, next_number FROM document_sequences WHERE id = ?");
        $stmt->execute([$id]);
        $seq = $stmt->fetch();
        if (!$seq) { throw new RuntimeException('Sequence not found'); }

        // Warn loudly if the operator tries to move the counter backwards over live numbers.
        if ($next < (int) $seq['next_number']) {
            $db->prepare("UPDATE document_sequences SET prefix = ?, padding = ?, next_number = ? WHERE id = ?")
               ->execute([$prefix, $padding, $next, $id]);
            logAudit($_SESSION['user_id'], "Sequence #$id rewound to $next (by #{$_SESSION['user_id']})", 'settings');
            flash('rewound', "Sequence rewound to $next. Verify no document already uses a higher number for this type.", 'warning');
        } else {
            $db->prepare("UPDATE document_sequences SET prefix = ?, padding = ?, next_number = ? WHERE id = ?")
               ->execute([$prefix, $padding, $next, $id]);
            flash('ok', 'Sequence updated.', 'success');
        }
        logAudit($_SESSION['user_id'], "Updated sequence #$id to $prefix/$padding/$next", 'settings');
    } catch (Throwable $e) {
        flash('err', $e->getMessage(), 'error');
    }
    to('modules/settings/sequences.php');
}

$rows = $db->query("
    SELECT ds.*, c.company_code, c.company_name
    FROM document_sequences ds
    JOIN companies c ON c.id = ds.company_id
    WHERE c.deleted_at IS NULL
    ORDER BY c.is_default DESC, c.company_name, ds.doc_type
")->fetchAll();

$byCompany = [];
foreach ($rows as $r) { $byCompany[$r['company_name'] . '|' . $r['company_code'] . '|' . $r['company_id']][] = $r; }
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Document Numbering</h1>
        <p class="text-gray-500 text-sm mt-1">One counter per company per document type — allocation-safe, no <span class="font-mono">COUNT(*)+1</span> races or gaps</p>
    </div>
    <a href="<?= url('modules/settings/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-800"><i class="fas fa-arrow-left mr-1.5"></i>Configuration</a>
</div>

<?php foreach ($byCompany as $label => $seqs): [$cname, $ccode, $cid] = explode('|', $label); ?>
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
    <h2 class="text-sm font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
        <i class="fas fa-building text-indigo-500 text-xs"></i>
        <?= htmlspecialchars($cname) ?>
        <span class="font-mono text-[11px] text-slate-400 font-normal"><?= htmlspecialchars($ccode) ?></span>
    </h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Document</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Prefix</th>
                    <th class="text-center text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Padding</th>
                    <th class="text-right text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Next</th>
                    <th class="text-left text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2 pl-4">Preview</th>
                    <th class="text-right text-[10px] font-semibold text-slate-400 uppercase tracking-wide pb-2">Save</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($seqs as $s): ?>
                <tr>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <td class="py-2.5 text-slate-800 font-medium">
                            <?= htmlspecialchars($docLabels[$s['doc_type']] ?? $s['doc_type']) ?>
                            <span class="block text-[10px] text-slate-400 font-mono font-normal"><?= htmlspecialchars($s['doc_type']) ?></span>
                        </td>
                        <td class="py-2.5 pr-2"><input name="prefix" value="<?= htmlspecialchars($s['prefix']) ?>" class="w-24 px-2 py-1.5 border border-slate-200 rounded text-sm font-mono uppercase"></td>
                        <td class="py-2.5 text-center"><input name="padding" type="number" min="1" max="10" value="<?= (int) $s['padding'] ?>" class="w-16 px-2 py-1.5 border border-slate-200 rounded text-sm text-center"></td>
                        <td class="py-2.5 text-right"><input name="next_number" type="number" min="1" value="<?= (int) $s['next_number'] ?>" class="w-24 px-2 py-1.5 border border-slate-200 rounded text-sm text-right font-mono"></td>
                        <td class="py-2.5 pl-4 font-mono text-xs text-indigo-600"><?= htmlspecialchars($s['prefix'] . str_pad((string) $s['next_number'], (int) $s['padding'], '0', STR_PAD_LEFT)) ?></td>
                        <td class="py-2.5 text-right"><button class="px-3 py-1.5 text-xs bg-slate-800 text-white rounded hover:bg-slate-900 transition">Save</button></td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-8">
    <i class="fas fa-triangle-exclamation mr-1.5"></i>
    Setting <strong>Next</strong> below the current value is recorded in the audit log — a number already issued to a document must never be handed out twice.
</div>

<?php require_once '../../includes/footer.php'; ?>
