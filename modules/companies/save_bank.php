<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('company.edit')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Invalid request.', 'error'); to('modules/companies/index.php');
}

$db = getDB();
$companyId = (int) ($_POST['company_id'] ?? 0);
$label = trim($_POST['account_label'] ?? '');
$bank  = trim($_POST['bank_name'] ?? '');
if ($companyId <= 0 || $label === '' || $bank === '') {
    flash('bank', 'A bank account needs at least a label and a bank name.', 'error');
    to('modules/companies/view.php?id=' . $companyId);
}

$currency = strtoupper(trim($_POST['currency_code'] ?? 'USD'));
if (!isset(getCurrencies()[$currency])) { $currency = 'USD'; }
$type = $_POST['account_type'] ?? 'current';
if (!in_array($type, ['current','savings','margin','escrow','fx','fixed_deposit'], true)) { $type = 'current'; }

$isPrimary = !empty($_POST['is_primary']) ? 1 : 0;
if ($isPrimary) {
    $db->prepare("UPDATE company_bank_accounts SET is_primary = 0 WHERE company_id = ?")->execute([$companyId]);
}

$db->prepare("
    INSERT INTO company_bank_accounts
        (company_id, account_label, bank_name, branch, account_name, account_number,
         iban, swift_bic, currency_code, account_type, is_primary, opening_balance, gl_account_id, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
")->execute([
    $companyId, $label, $bank,
    trim($_POST['branch'] ?? '') ?: null, trim($_POST['account_name'] ?? '') ?: null,
    trim($_POST['account_number'] ?? '') ?: null, trim($_POST['iban'] ?? '') ?: null,
    trim($_POST['swift_bic'] ?? '') ?: null, $currency, $type, $isPrimary,
    (float) ($_POST['opening_balance'] ?? 0), null,
]);

logAudit($_SESSION['user_id'], 'Added bank account to company #' . $companyId, 'company');
flash('bank', 'Bank account added.', 'success');
to('modules/companies/view.php?id=' . $companyId . '#financial');
