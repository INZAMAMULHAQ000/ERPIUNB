<?php
/**
 * F13 — Intercompany Service
 *
 * IC transaction lifecycle: source company posts through the Posting Engine;
 * the service ALSO generates the linked counterparty entry in the target
 * company's books (STEP 9). Both rows share root_id; counterpart_id cross-links.
 * Idempotent: one counterpart per source row.
 */

require_once __DIR__ . '/../posting_engine/PostingEngine.php';
require_once __DIR__ . '/../helpers/finance_helpers.php';

class IntercompanyService
{
    // ---------------------------------------------------------
    // Audit
    // ---------------------------------------------------------
    public static function log($db, $action, $companyId = null, $refId = null, $details = null, $reason = null, $userId = null)
    {
        $db->prepare("INSERT INTO finance_group_audit (action, company_id, reference_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$action, $companyId, $refId, $details, $reason,
                       $_SERVER['REMOTE_ADDR'] ?? null, $userId ?? ($_SESSION['user_id'] ?? null)]);
    }

    // ---------------------------------------------------------
    // IC account mapping (configurable — never hard-coded)
    // ---------------------------------------------------------
    public static function getIcAccount($db, $companyId, $icType)
    {
        $s = $db->prepare("SELECT account_id FROM intercompany_accounts WHERE company_id = ? AND ic_type = ?");
        $s->execute([(int)$companyId, $icType]);
        return (int)$s->fetchColumn() ?: null;
    }

    public static function setIcAccount($db, $companyId, $icType, $accountId, $userId)
    {
        $db->prepare("INSERT INTO intercompany_accounts (company_id, ic_type, account_id, updated_by)
                      VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), updated_by = VALUES(updated_by)")
            ->execute([(int)$companyId, $icType, (int)$accountId, $userId]);
        self::log($db, 'ic_account_configured', $companyId, $accountId, "$icType → #$accountId", null, $userId);
    }

    /** Which IC account types does a transaction type hit on each side? */
    public static function typeMap($transactionType)
    {
        switch ($transactionType) {
            case 'sale': case 'service':
                return ['src_debit' => 'receivable', 'src_credit' => 'revenue',
                        'tgt_debit' => 'expense',   'tgt_credit' => 'payable'];
            case 'purchase': case 'expense':
                return ['src_debit' => 'payable',    'src_credit' => 'receivable',
                        'tgt_debit' => 'payable',    'tgt_credit' => 'receivable'];
            default:
                return ['src_debit' => 'receivable', 'src_credit' => 'other',
                        'tgt_debit' => 'other',      'tgt_credit' => 'payable'];
        }
    }

    // ---------------------------------------------------------
    // Create IC transaction + AUTOMATIC COUNTERPART (STEP 8/9)
    // ---------------------------------------------------------
    public static function createTransaction($db, array $d)
    {
        $userId = $d['user_id'];
        if ((int)$d['source_company_id'] === (int)$d['target_company_id'])
            return ['success' => false, 'message' => 'Source and target company must differ.'];
        if ((float)$d['amount'] <= 0) return ['success' => false, 'message' => 'Amount must be positive.'];

        try {
            $db->beginTransaction();

            $txnNo = 'ICT-' . date('Ymd') . '-' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 5);
            $map = self::typeMap($d['transaction_type']);

            // --- Source company entry (through Posting Engine) ---
            $jeSrc = self::createJournal($db, [
                'company_id'  => $d['source_company_id'],
                'date'        => $d['transaction_date'],
                'debit_acct'  => self::getIcAccount($db, $d['source_company_id'], $map['src_debit']),
                'credit_acct' => self::getIcAccount($db, $d['source_company_id'], $map['src_credit']),
                'amount'      => $d['amount'],
                'reference'   => $d['reference'] ?? '',
                'description' => "IC {$d['transaction_type']} to company #{$d['target_company_id']}: " . ($d['description'] ?? ''),
            ], $userId);
            if (!$jeSrc) throw new Exception('Source journal could not be created (check IC accounts / open period for that company).');

            $ins = $db->prepare("
                INSERT INTO intercompany_transactions
                    (transaction_number, source_company_id, target_company_id, transaction_date, transaction_type,
                     reference, description, currency, amount, tax_amount, match_status, source_journal_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unmatched', ?, ?)
            ");
            $ins->execute([$txnNo, $d['source_company_id'], $d['target_company_id'], $d['transaction_date'],
                           $d['transaction_type'], $d['reference'] ?? null, $d['description'] ?? null,
                           $d['currency'] ?? 'AED', $d['amount'], $d['tax_amount'] ?? 0, $jeSrc, $userId]);
            $srcId = (int)$db->lastInsertId();

            // --- Automatic counterparty in target books (STEP 9, idempotent) ---
            list($jeTgt, $cpId) = self::generateCounterpart($db, $srcId, $map, $userId);

            self::log($db, 'ic_transaction_created', $d['source_company_id'], $srcId,
                "{$d['transaction_type']} {$d['currency']} {$d['amount']} → company #{$d['target_company_id']} (auto-counterpart #$cpId)", null, $userId);

            $db->commit();
            return ['success' => true, 'message' => "IC transaction $txnNo created with automatic counterpart.",
                    'id' => $srcId, 'counterpart_id' => $cpId];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => 'IC transaction failed: ' . $e->getMessage()];
        }
    }

    /**
     * STEP 9 — Idempotent counterpart generator.
     * Creates the mirrored IC row + posted journal in the target books.
     * Returns [journalId|null, counterpartRowId].
     */
    public static function generateCounterpart($db, $sourceTxnId, $map = null, $userId = null)
    {
        $stmt = $db->prepare("SELECT * FROM intercompany_transactions WHERE id = ?");
        $stmt->execute([(int)$sourceTxnId]);
        $t = $stmt->fetch();
        if (!$t) return [null, null];

        // IDEMPOTENCY: if a counterpart already links to us, return it untouched
        $ex = $db->prepare("SELECT id, target_journal_id FROM intercompany_transactions WHERE counterpart_id = ?");
        $ex->execute([(int)$sourceTxnId]);
        if ($existing = $ex->fetch()) {
            return [$existing['target_journal_id'] ?: null, (int)$existing['id']];
        }

        $map = $map ?: self::typeMap($t['transaction_type']);
        $rootId = $t['root_id'] ?: (int)$t['id'];

        $tgtDebitAcct  = self::getIcAccount($db, $t['target_company_id'], $map['tgt_debit']);
        $tgtCreditAcct = self::getIcAccount($db, $t['target_company_id'], $map['tgt_credit']);
        if (!$tgtDebitAcct || !$tgtCreditAcct) return [null, null];

        require_once __DIR__ . '/../helpers/finance_helpers.php';
        $vtId = (int)$db->query("SELECT id FROM voucher_types WHERE voucher_name='Journal Voucher' LIMIT 1")->fetchColumn();
        $voucherNo = generateVoucherNo($vtId, $db);
        $desc = "[IC counterpart of {$t['transaction_number']}] " . ($t['description'] ?? '');
        $insJ = $db->prepare("
            INSERT INTO journal_entries (company_id, voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        $insJ->execute([$t['target_company_id'], $voucherNo, $vtId, $t['transaction_date'], $t['reference'], $desc, $userId ?? 1]);
        $jeTgt = (int)$db->lastInsertId();
        $li = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
        $li->execute([$jeTgt, $tgtDebitAcct, $t['amount'], 0, $desc]);
        $li->execute([$jeTgt, $tgtCreditAcct, 0, $t['amount'], $desc]);
        $res = PostingEngine::post($jeTgt, $userId ?? 1);
        if (empty($res['success'])) throw new Exception('Counterpart posting failed: ' . $res['message']);

        $insCp = $db->prepare("
            INSERT INTO intercompany_transactions
                (transaction_number, source_company_id, target_company_id, transaction_date, transaction_type,
                 reference, description, currency, amount, tax_amount, match_status, counterpart_id, root_id, target_journal_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'matched', ?, ?, ?, ?)
        ");
        $insCp->execute(['CP-' . $t['transaction_number'], $t['target_company_id'], $t['source_company_id'],
                         $t['transaction_date'], $t['transaction_type'], $t['reference'], '[counterpart] ' . ($t['description'] ?? ''),
                         $t['currency'], $t['amount'], $t['tax_amount'], (int)$t['id'], $rootId, $jeTgt, $userId ?? 1]);
        $cpId = (int)$db->lastInsertId();

        // cross-link the ORIGINAL to its counterpart
        $db->prepare("UPDATE intercompany_transactions SET match_status='matched', counterpart_id=?, root_id=?, target_journal_id=? WHERE id=?")
           ->execute([$cpId, $rootId, $jeTgt, (int)$t['id']]);

        return [$jeTgt, $cpId];
    }

    private static function createJournal($db, array $j, $userId)
    {
        if (!$j['debit_acct'] || !$j['credit_acct']) return null;
        require_once __DIR__ . '/../helpers/finance_helpers.php';
        $vtId = (int)$db->query("SELECT id FROM voucher_types WHERE voucher_name='Journal Voucher' LIMIT 1")->fetchColumn();
        $voucherNo = generateVoucherNo($vtId, $db);
        $ins = $db->prepare("
            INSERT INTO journal_entries (company_id, voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        $ins->execute([$j['company_id'], $voucherNo, $vtId, $j['date'], $j['reference'], $j['description'], $userId]);
        $jid = (int)$db->lastInsertId();
        $li = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
        $li->execute([$jid, $j['debit_acct'], $j['amount'], 0, $j['description']]);
        $li->execute([$jid, $j['credit_acct'], 0, $j['amount'], $j['description']]);
        $res = PostingEngine::post($jid, $userId);
        return !empty($res['success']) ? $jid : null;
    }

    // ---------------------------------------------------------
    // STEP 10 — Matching engine
    // ---------------------------------------------------------

    /** Auto-match one-sided entries on partner pair + amount + currency + date window. */
    public static function autoMatch($db, $tolerance = 0.001, $dateWindowDays = 5)
    {
        $candidates = $db->query("
            SELECT * FROM intercompany_transactions
            WHERE match_status IN ('unmatched','exception') AND amount > 0
            ORDER BY transaction_date
        ")->fetchAll();
        $matched = 0;
        $used = [];
        foreach ($candidates as $a) {
            if (isset($used[$a['id']])) continue;
            foreach ($candidates as $b) {
                if ($a['id'] === $b['id'] || isset($used[$b['id']])) continue;
                if (!((int)$a['source_company_id'] === (int)$b['target_company_id']
                   && (int)$a['target_company_id'] === (int)$b['source_company_id'])) continue;
                if ($a['currency'] !== $b['currency']) continue;
                if (abs((float)$a['amount'] - (float)$b['amount']) > $tolerance) continue;
                $days = abs((strtotime($a['transaction_date']) - strtotime($b['transaction_date'])) / 86400);
                if ($days > $dateWindowDays) continue;

                $db->prepare("UPDATE intercompany_transactions SET match_status='matched', counterpart_id=? WHERE id=?")
                   ->execute([$b['id'], $a['id']]);
                $db->prepare("UPDATE intercompany_transactions SET match_status='matched', counterpart_id=? WHERE id=?")
                   ->execute([$a['id'], $b['id']]);
                $used[$a['id']] = $used[$b['id']] = true;
                self::log($db, 'ic_matched', $a['source_company_id'], $a['id'],
                    "Auto-matched with #{$b['id']} ({$b['transaction_number']})", null, $_SESSION['user_id'] ?? null);
                $matched++;
                break;
            }
        }
        return $matched;
    }

    public static function setMatchStatus($db, $txnId, $status, $userId, $reason = null)
    {
        if (!in_array($status, ['unmatched','partially_matched','matched','exception','resolved'], true))
            return ['success' => false, 'message' => 'Invalid status.'];
        $db->prepare("UPDATE intercompany_transactions SET match_status=? WHERE id=?")->execute([$status, $txnId]);
        self::log($db, $status === 'resolved' ? 'ic_exception_resolved' : 'ic_status_changed', null, $txnId, "→ $status", $reason, $userId);
        return ['success' => true];
    }

    // ---------------------------------------------------------
    // STEP 11 — Reconciliation matrix (GL-truth based)
    // ---------------------------------------------------------

    public static function reconciliationMatrix($db)
    {
        $pairs = $db->query("
            SELECT DISTINCT LEAST(source_company_id, target_company_id) a,
                            GREATEST(source_company_id, target_company_id) b
            FROM intercompany_transactions
        ")->fetchAll();
        $out = [];
        foreach ($pairs as $p) {
            $recvA = self::pairBalance($db, $p['a'], $p['b'], 'receivable');
            $payB  = self::pairBalance($db, $p['b'], $p['a'], 'payable');
            $diff  = round($recvA - $payB, 2);
            $base  = max(abs($recvA), abs($payB), 0.01);
            $pct   = round(abs($diff) / $base * 100, 2);
            $out[] = [
                'company_a' => (int)$p['a'], 'company_b' => (int)$p['b'],
                'receivable_a' => $recvA, 'payable_b' => $payB,
                'difference' => abs($diff), 'difference_pct' => $pct,
                'status' => ($recvA == 0 && $payB == 0) ? 'NO BALANCE'
                          : (abs($diff) < 0.005 ? 'MATCHED' : 'EXCEPTION'),
            ];
        }
        return $out;
    }

    private static function pairBalance($db, $companyId, $partnerId, $icType)
    {
        $acct = self::getIcAccount($db, $companyId, $icType);
        if (!$acct) return 0.0;
        $s = $db->prepare("SELECT COALESCE(SUM(debit-credit),0) FROM general_ledger WHERE company_id=? AND account_id=?");
        $s->execute([(int)$companyId, $acct]);
        $raw = (float)$s->fetchColumn();
        // present balances in NATURAL sign: receivable debit-positive, payable credit-positive
        return round($icType === 'payable' ? -$raw : $raw, 2);
    }
}
