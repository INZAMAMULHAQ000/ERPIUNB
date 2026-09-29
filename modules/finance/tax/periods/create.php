<?php
$pageTitle = 'New Tax Period';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.period.manage')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
    }

    $taxTypeId  = (int)($_POST['tax_type_id'] ?? 0);
    $periodName = trim($_POST['period_name'] ?? '');
    $startDate  = trim($_POST['start_date'] ?? '');
    $endDate    = trim($_POST['end_date'] ?? '');
    if (!in_array($_POST['status'] ?? 'open', ['open', 'submitted', 'closed'], true)) {
        $status = 'open';
    } else {
        $status = $_POST['status'];
    }

    $errors = [];
    if ($taxTypeId <= 0) $errors[] = 'Tax type is required.';
    if ($periodName === '') $errors[] = 'Period name is required.';
    if ($startDate === '') $errors[] = 'Start date is required.';
    if ($endDate === '') $errors[] = 'End date is required.';

    $d1 = DateTime::createFromFormat('Y-m-d', $startDate);
    $d2 = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$errors && (!$d1 || !$d2 || $d1->format('Y-m-d') !== $startDate || $d2->format('Y-m-d') !== $endDate)) {
        $errors[] = 'Dates must be valid YYYY-MM-DD values.';
    }
    if (!$errors && $startDate > $endDate) {
        $errors[] = 'Start date cannot be after end date.';
    }

    if (!$errors) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM tax_periods WHERE tax_type_id = ? AND NOT (end_date < ? OR start_date > ?)");
        $stmt->execute([$taxTypeId, $startDate, $endDate]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'A period already exists overlapping these dates.';
        }
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('create.php');
    }

    $db->prepare("INSERT INTO tax_periods (tax_type_id, period_name, start_date, end_date, status)
                  VALUES (?, ?, ?, ?, ?)")
       ->execute([$taxTypeId, $periodName, $startDate, $endDate, $status]);
    $id = (int)$db->lastInsertId();
    logAudit($_SESSION['user_id'], 'Created tax period #' . $id . ' (' . $periodName . ')', 'tax');
    flash('success', 'Tax period "' . $periodName . '" created.');
    redirect('index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Periods</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">New Tax Period</h1>
    <p class="text-gray-600">Define a filing period for a tax type — open, submitted, or closed</p>
</div>

<?= taxui_tabs('periods') ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="create.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type <span class="text-red-500">*</span></label>
            <?= taxui_types_select(0, 'tax_type_id', false) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Period Name <span class="text-red-500">*</span></label>
            <input type="text" name="period_name" required maxlength="100" placeholder="e.g. Jan 2026"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                <input type="date" name="start_date" required class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
                <input type="date" name="end_date" required class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="open" selected>Open</option>
                <option value="submitted">Submitted</option>
                <option value="closed">Closed</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Lifecycle is open &rarr; submitted &rarr; closed. Closed periods block further tax transactions.</p>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Period</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>