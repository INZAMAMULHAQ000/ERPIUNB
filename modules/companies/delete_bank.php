<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('company.edit')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Invalid request.', 'error'); to('modules/companies/index.php');
}

$db = getDB();
$id = (int) ($_POST['id'] ?? 0);
$companyId = (int) ($_POST['company_id'] ?? 0);
if ($id <= 0) { die('Invalid request'); }

$db->prepare("UPDATE company_bank_accounts SET deleted_at = NOW() WHERE id = ? AND company_id = ?")->execute([$id, $companyId]);

logAudit($_SESSION['user_id'], "Removed bank account #$id from company #$companyId", 'company');
flash('bank', 'Bank account removed.', 'success');
to('modules/companies/view.php?id=' . $companyId . '#financial');
