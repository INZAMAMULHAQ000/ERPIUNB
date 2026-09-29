<?php
$pageTitle = 'Approve Tax Adjustment';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('tax.adjust')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token.', 'error');
    redirect('view.php?id=' . (int)($_POST['id'] ?? 0));
}

$db = getDB();
$id = (int)($_POST['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_adjustments WHERE id = ?");
$stmt->execute([$id]);
$adj = $stmt->fetch();
if (!$adj) { flash('error', 'Tax adjustment not found.', 'error'); redirect('index.php'); }
if ($adj['status'] !== 'draft') {
    flash('error', 'Only draft adjustments can be approved.', 'error');
    redirect('view.php?id=' . $id);
}

try {
    $db->beginTransaction();

    // 1. Closed-period guard
    $period = TaxService::getPeriodForDate($adj['tax_type_id'], $adj['adjustment_date']);
    if ($period && TaxService::periodIsClosed($period['id'])) {
        throw new RuntimeException('The adjustment date falls inside a closed tax period.');
    }

    // 2. Tax GL accounts must be configured
    $adjAcct = (int)TaxService::config('adjustment_account_id');
    $dirAcct = (int)TaxService::getTaxAccountId($adj['direction']);
    if (!$adjAcct || !$dirAcct) {
        throw new Exception('Tax GL accounts are not configured. Go to Tax Settings.');
    }

    // 3. Rate effective on the adjustment date (for the tax register)
    $rate = TaxService::getApplicableTaxRate($adj['tax_type_id'], $adj['adjustment_date']);
    $ratePct = $rate['rate_percentage'] ?? 0;

    // 4. Voucher type 'Tax Adjustment' + voucher number
    $vtStmt = $db->prepare("SELECT id FROM voucher_types WHERE voucher_name = 'Tax Adjustment' LIMIT 1");
    $vtStmt->execute();
    $vtId = $vtStmt->fetchColumn();
    if (!$vtId) {
        throw new Exception('Tax Adjustment voucher type not found.');
    }
    $voucherNo = generateVoucherNo($vtId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    // 5. Journal entry (draft, ready for PostingEngine)
    $db->prepare("INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
                  VALUES (?, ?, ?, ?, ?, 'draft', ?)")
       ->execute([
           $voucherNo,
           (int)$vtId,
           $adj['adjustment_date'],
           $adj['adjustment_no'],
           'Tax Adjustment ' . $adj['adjustment_no'],
           $_SESSION['user_id'],
       ]);
    $journalId = (int)$db->lastInsertId();

    // 6. Journal lines
    $taxAmount = (float)$adj['tax_amount'];
    $lineStmt = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
    if ($adj['direction'] === 'input') {
        // Input tax: DR Input Tax account, CR Adjustment clearing
        $lineStmt->execute([$journalId, $dirAcct, $taxAmount, 0, 'Tax adjustment ' . $adj['adjustment_no']]);
        $lineStmt->execute([$journalId, $adjAcct, 0, $taxAmount, 'Tax adjustment ' . $adj['adjustment_no']]);
    } else {
        // Output tax: DR Adjustment clearing, CR Output Tax account
        $lineStmt->execute([$journalId, $adjAcct, $taxAmount, 0, 'Tax adjustment ' . $adj['adjustment_no']]);
        $lineStmt->execute([$journalId, $dirAcct, 0, $taxAmount, 'Tax adjustment ' . $adj['adjustment_no']]);
    }

    // 7. Post via the Posting Engine
    require_once __DIR__ . '/../../posting_engine/PostingEngine.php';
    $res = PostingEngine::post($journalId, $_SESSION['user_id']);
    if (!($res['success'] ?? false)) {
        throw new Exception($res['message'] ?? 'Posting failed.');
    }

    // 8. Mark the adjustment approved
    $upd = $db->prepare("UPDATE tax_adjustments SET status = 'approved', journal_entry_id = ?, approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'draft'");
    $upd->execute([$journalId, $_SESSION['user_id'], $id]);
    if ($upd->rowCount() === 0) {
        throw new Exception('Adjustment was already processed.');
    }

    // 9. Register the adjustment in the tax register
    try {
        TaxService::recordDocumentTax([
            'source_module'       => TaxService::SOURCE_ADJUSTMENT,
            'source_record_id'    => $id,
            'source_document_no'  => $adj['adjustment_no'],
            'transaction_date'    => $adj['adjustment_date'],
            'tax_type_id'         => $adj['tax_type_id'],
            'direction'           => $adj['direction'],
            'created_by'          => $_SESSION['user_id'],
            'period_id'           => $period['id'] ?? null,
            'lines' => [
                [
                    'taxable_amount' => $adj['taxable_amount'],
                    'tax_amount'     => $adj['tax_amount'],
                    'tax_rate'       => $ratePct,
                    'tax_status'     => 'taxable',
                ],
            ],
        ]);
    } catch (RuntimeException $e) {
        throw new Exception($e->getMessage());
    }

    $db->commit();
    logAudit($_SESSION['user_id'], 'Approved tax adjustment ' . $adj['adjustment_no'] . ' (Voucher ' . $voucherNo . ')', 'tax');
    flash('success', 'Adjustment approved and posted. Voucher #' . $voucherNo);
    redirect('view.php?id=' . $id);

} catch (Throwable $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    flash('error', 'Failed to approve adjustment: ' . $e->getMessage());
    redirect('view.php?id=' . $id);
}