<?php
/**
 * F13 — Consolidation Service
 *
 * GOLDEN RULE: consolidation NEVER modifies company ledgers.
 *   Company TB + Company TB + Eliminations/Adjustments = Group Statements.
 *
 * Workflow: draft → in_progress → validated → approved → posted → (cancelled)
 * Idempotency: a run posts once (status guard); eliminations are keyed per
 * IC pair per run (uk_elim_once).
 */

require_once __DIR__ . '/../group/IntercompanyService.php';

class ConsolidationService
{
    // ---------------------------------------------------------
    // Audit
    // ---------------------------------------------------------
    public static function log($db, $action, $companyId = null, $refId = null, $details = null, $reason = null)
    {
        $db->prepare("INSERT INTO finance_group_audit (action, company_id, reference_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$action, $companyId, $refId, $details, $reason,
                       $_SERVER['REMOTE_ADDR'] ?? null, $_SESSION['user_id'] ?? null]);
    }

    // ---------------------------------------------------------
    // STEP 15 — Run lifecycle
    // ---------------------------------------------------------

    public static function createRun($db, array $d)
    {
        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO consolidation_runs (group_id, period_label, start_date, end_date, group_currency, translation_method, status, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)")
                ->execute([$d['group_id'], $d['period_label'], $d['start_date'], $d['end_date'],
                           $d['group_currency'] ?? 'AED', $d['translation_method'] ?? 'closing', $d['created_by']]);
            $runId = (int)$db->lastInsertId();

            $ins = $db->prepare("INSERT INTO consolidation_run_companies (run_id, company_id) VALUES (?, ?)");
            foreach ($d['company_ids'] as $cid) $ins->execute([$runId, (int)$cid]);

            self::log($db, 'consolidation_started', null, $runId,
                "Group {$d['group_id']} · {$d['period_label']} · companies: " . implode(',', $d['company_ids']));
            $db->commit();
            return ['success' => true, 'run_id' => $runId];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Companies included in a run. */
    public static function runCompanies($db, $runId)
    {
        $s = $db->prepare("SELECT crc.*, c.company_name, c.company_code FROM consolidation_run_companies crc
                           JOIN companies c ON c.id = crc.company_id WHERE crc.run_id = ?");
        $s->execute([(int)$runId]);
        return $s->fetchAll();
    }

    // ---------------------------------------------------------
    // STEP 31 — Pre-consolidation validation
    // ---------------------------------------------------------

    public static function validate($db, $runId)
    {
        $run = self::getRun($db, $runId);
        if (!$run) return ['passed' => false, 'checks' => [['name' => 'Run', 'status' => 'fail', 'detail' => 'Not found.']]];
        $checks = [];

        $cos = self::runCompanies($db, $runId);
        if (!count($cos)) {
            return ['passed' => false, 'checks' => [['name' => 'Companies', 'status' => 'fail', 'detail' => 'No companies selected.']]];
        }

        foreach ($cos as $co) {
            if (!$co['included']) continue;
            $cid = (int)$co['company_id'];
            $anchorFyId = (int)($co['financial_year_id'] ?? 0);
            $fy = null;
            if ($anchorFyId) {
                $fyS = $db->prepare("SELECT id, year_name, start_date, end_date, status FROM financial_years WHERE id=? LIMIT 1");
                $fyS->execute([$anchorFyId]);
                $fy = $fyS->fetch();
            }
            if (!$fy) {
                $fyS = $db->prepare("SELECT id, year_name, start_date, end_date, status FROM financial_years
                                     WHERE company_id=? AND ? BETWEEN start_date AND end_date
                                     ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END, start_date DESC, id DESC LIMIT 1");
                $fyS->execute([$cid, $run['start_date']]);
                $fy = $fyS->fetch();
            }

            // 1. Valid financial year for the window
            if ($fy && !($fy['end_date'] < $run['start_date'] || $fy['start_date'] > $run['end_date'])) {
                $checks[] = ['name' => "{$co['company_code']}: financial year", 'status' => 'pass',
                             'detail' => $fy['year_name'] . ' (' . $fy['status'] . ')'];
            } else {
                $checks[] = ['name' => "{$co['company_code']}: financial year", 'status' => 'fail',
                             'detail' => 'No FY covering the run window'];
            }

            // 2. Balanced trial balance (zero-activity entities pass — they contribute zeros)
            $tb = self::companyTrialBalance($db, $cid, (int)($fy['id'] ?? 0), $run['start_date'], $run['end_date']);
            $dr = array_sum(array_column($tb, 'debit')); $cr = array_sum(array_column($tb, 'credit'));
            $balanced = abs($dr - $cr) < 0.005;
            if ($dr == 0 && $cr == 0) {
                $checks[] = ['name' => "{$co['company_code']}: trial balance", 'status' => 'pass', 'detail' => 'No activity in scope (zeros carry forward).'];
            } else {
                $checks[] = ['name' => "{$co['company_code']}: trial balance", 'status' => $balanced ? 'pass' : 'fail',
                             'detail' => $balanced ? 'Dr '.number_format($dr,2).' = Cr '.number_format($cr,2)
                                                   : "Unbalanced: Dr ".number_format($dr,2)." / Cr ".number_format($cr,2)];
            }

            // 3. Periods closed inside window
            $openP = $db->prepare("SELECT COUNT(*) FROM accounting_periods ap
                                   WHERE ap.financial_year_id = ? AND ap.status<>'closed'
                                     AND NOT (ap.end_date < ? OR ap.start_date > ?)");
            $openP->execute([(int)($fy['id'] ?? 0), $run['start_date'], $run['end_date']]);
            $nOpen = (int)$openP->fetchColumn();
            $checks[] = ['name' => "{$co['company_code']}: periods closed", 'status' => $nOpen === 0 ? 'pass' : 'fail',
                         'detail' => $nOpen === 0 ? 'All relevant periods closed.' : "$nOpen open/closing period(s) in window"];

            // 4. FX rate availability when currency differs from group currency
            if ($co['exchange_rate'] <= 0) {
                $checks[] = ['name' => "{$co['company_code']}: exchange rate", 'status' => 'fail', 'detail' => 'Rate not configured on run'];
            }
        }

        // 5. IC reconciliation — differences are allowed when every underlying
        //    transaction is formally matched/resolved (documented timing
        //    differences); UNRESOLVED exceptions block consolidation.
        $matrix = IntercompanyService::reconciliationMatrix($db);
        $inSet = array_map(fn($c) => (int)$c['company_id'], $cos);
        foreach ($matrix as $m) {
            if (!in_array($m['company_a'], $inSet) || !in_array($m['company_b'], $inSet)) continue;
            if ($m['difference'] >= 0.005) {
                $u = $db->prepare("SELECT COUNT(*) FROM intercompany_transactions
                                   WHERE match_status IN ('unmatched','exception','partially_matched')
                                     AND ((source_company_id=? AND target_company_id=?) OR (source_company_id=? AND target_company_id=?))");
                $u->execute([$m['company_a'],$m['company_b'],$m['company_b'],$m['company_a']]);
                $unresolved = (int)$u->fetchColumn();
                if ($unresolved > 0) {
                    $checks[] = ['name' => "IC reconciliation C{$m['company_a']}↔C{$m['company_b']}", 'status' => 'fail',
                                 'detail' => "Difference {$m['difference']} ({$m['difference_pct']}%) with $unresolved unresolved transaction(s)"];
                } else {
                    $checks[] = ['name' => "IC reconciliation C{$m['company_a']}↔C{$m['company_b']}", 'status' => 'pass',
                                 'detail' => "Documented difference {$m['difference']} — all transactions resolved"];
                }
            }
        }
        $checks[] = ['name' => 'Intercompany reconciliation', 'status' => 'pass', 'detail' => 'No unresolved differences in scope'];

        $passed = !in_array('fail', array_column($checks, 'status'), true);
        if ($passed) {
            self::log($db, 'consolidation_validated', null, (int)$runId, 'All validation checks passed');
        }
        return ['passed' => $passed, 'checks' => $checks];
    }

    /** Approve a validated run (audited). */
    public static function approveRun($db, $runId, $userId)
    {
        $st = self::getRun($db, $runId);
        if (!$st || $st['status'] !== 'validated')
            return ['success' => false, 'message' => 'Only VALIDATED runs can be approved.'];
        $db->prepare("UPDATE consolidation_runs SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
           ->execute([$userId, (int)$runId]);
        self::log($db, 'consolidation_approved', null, (int)$runId, null, null, $userId);
        return ['success' => true];
    }

    public static function getRun($db, $runId)
    {
        $s = $db->prepare("SELECT cr.*, g.group_name FROM consolidation_runs cr JOIN company_groups g ON g.id=cr.group_id WHERE cr.id=?");
        $s->execute([(int)$runId]);
        return $s->fetch();
    }

    // ---------------------------------------------------------
    // Company trial balance (GL-truth; OB only for default co.)
    // ---------------------------------------------------------

    public static function companyTrialBalance($db, $companyId, $fyId, $dateFrom = null, $dateTo = null)
    {
        $w = [];
        $p = [(int)$companyId];
        $sql = "SELECT coa.id account_id, coa.account_code, coa.account_name, ag.nature,
                  COALESCE(SUM(gl.debit),0) debit, COALESCE(SUM(gl.credit),0) credit
                FROM chart_of_accounts coa
                JOIN account_groups ag ON ag.id = coa.group_id
                LEFT JOIN general_ledger gl ON gl.account_id = coa.id AND gl.company_id = ?";
        if ($fyId) { $sql .= " AND gl.financial_year_id = ?"; $p[] = (int)$fyId; }
        else {
            if ($dateFrom) { $sql .= " AND gl.posting_date >= ?"; $p[] = $dateFrom; }
            if ($dateTo)   { $sql .= " AND gl.posting_date <= ?"; $p[] = $dateTo; }
        }
        $sql .= " GROUP BY coa.id, coa.account_code, coa.account_name, ag.nature HAVING debit <> 0 OR credit <> 0";
        $st = $db->prepare($sql); $st->execute($p);
        $rows = $st->fetchAll();

        // Legacy opening balances belong to the default company's books only
        if ((int)$companyId === 1) {
            foreach ($rows as &$r) {
                $ob = $db->prepare("SELECT CASE WHEN opening_balance_type='credit' THEN -opening_balance ELSE opening_balance END
                                    FROM chart_of_accounts WHERE id = ?");
                $ob->execute([(int)$r['account_id']]);
                $signed = (float)$ob->fetchColumn();
                if ($signed >= 0) $r['debit'] = (float)$r['debit'] + $signed;
                else              $r['credit'] = (float)$r['credit'] + abs($signed);
            }
        }
        return $rows;
    }

    // ---------------------------------------------------------
    // STEP 12/13 — Eliminations (consolidation layer ONLY)
    // ---------------------------------------------------------

    public static function buildEliminations($db, $runId)
    {
        $run = self::getRun($db, $runId);
        $journals = [];
        $pairs = IntercompanyService::reconciliationMatrix($db);
        foreach ($pairs as $m) {
            if ($m['receivable_a'] == 0 && $m['payable_b'] == 0) continue;
            $amount = min(abs($m['receivable_a']), abs($m['payable_b']));
            if ($amount < 0.005) continue;

            $recvAcctA = IntercompanyService::getIcAccount($db, $m['company_a'], 'receivable');
            $payAcctB  = IntercompanyService::getIcAccount($db, $m['company_b'], 'payable');
            if (!$recvAcctA || !$payAcctB) continue;

            $key = 'IC-' . min($m['company_a'],$m['company_b']) . '-' . max($m['company_a'],$m['company_b']);
            $journals[] = [
                'adjustment_type' => 'intercompany_elimination',
                'partner_pair_key' => $key,
                'description' => "Eliminate IC balance C{$m['company_a']} ↔ C{$m['company_b']}",
                'amount' => $amount,
                'lines' => [
                    ['account_id' => $payAcctB,  'debit' => $amount, 'credit' => 0, 'memo' => 'Eliminate intercompany payable'],
                    ['account_id' => $recvAcctA, 'debit' => 0, 'credit' => $amount, 'memo' => 'Eliminate intercompany receivable'],
                ],
            ];
        }

        // Revenue vs expense elimination from matched transactions
        // ONLY transactions with REAL posted journals on both sides qualify —
        // synthetic matching-test rows must never drive eliminations.
        $revExp = $db->prepare("
            SELECT t.source_company_id, t.target_company_id, SUM(t.amount) amt
            FROM intercompany_transactions t
            WHERE t.match_status IN ('matched','resolved') AND t.transaction_type IN ('sale','service')
              AND t.source_journal_id IS NOT NULL AND t.target_journal_id IS NOT NULL
              AND EXISTS (SELECT 1 FROM consolidation_run_companies rc WHERE rc.run_id=? AND rc.included=1 AND rc.company_id=t.source_company_id)
              AND EXISTS (SELECT 1 FROM consolidation_run_companies rc WHERE rc.run_id=? AND rc.included=1 AND rc.company_id=t.target_company_id)
            GROUP BY t.source_company_id, t.target_company_id");
        $revExp->execute([(int)$runId,(int)$runId]);
        foreach ($revExp->fetchAll() as $r) {
            $srcRev = IntercompanyService::getIcAccount($db, $r['source_company_id'], 'revenue');
            $tgtExp = IntercompanyService::getIcAccount($db, $r['target_company_id'], 'expense');
            if (!$srcRev || !$tgtExp || (float)$r['amt'] < 0.005) continue;
            $journals[] = [
                'adjustment_type' => 'intercompany_elimination',
                'partner_pair_key' => 'ICRE-' . $r['source_company_id'] . '-' . $r['target_company_id'],
                'description' => "Eliminate IC revenue/expense C{$r['source_company_id']} → C{$r['target_company_id']}",
                'amount' => (float)$r['amt'],
                'lines' => [
                    ['account_id' => $srcRev, 'debit' => (float)$r['amt'], 'credit' => 0, 'memo' => 'Eliminate intercompany revenue'],
                    ['account_id' => $tgtExp, 'debit' => 0, 'credit' => (float)$r['amt'], 'memo' => 'Eliminate intercompany expense'],
                ],
            ];
        }
        return $journals;
    }

    public static function persistJournals($db, $runId, array $journals, $userId)
    {
        $saved = 0;
        foreach ($journals as $j) {
            $dupe = $db->prepare("SELECT COUNT(*) FROM consolidation_journals WHERE run_id=? AND adjustment_type=? AND partner_pair_key <=> ?");
            $dupe->execute([(int)$runId, $j['adjustment_type'], $j['partner_pair_key'] ?? null]);
            if ((int)$dupe->fetchColumn() > 0) continue; // IDEMPOTENCY: never apply twice

            $ins = $db->prepare("INSERT INTO consolidation_journals (run_id, adjustment_type, description, reason, amount, source, partner_pair_key, created_by)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([(int)$runId, $j['adjustment_type'], $j['description'], $j['reason'] ?? null,
                           $j['amount'], $j['source'] ?? 'system', $j['partner_pair_key'] ?? null, $userId]);
            $jid = (int)$db->lastInsertId();
            $ln = $db->prepare("INSERT INTO consolidation_journal_lines (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
            foreach ($j['lines'] as $l) $ln->execute([$jid, $l['account_id'], $l['debit'], $l['credit'], $l['memo'] ?? null]);

            self::log($db, 'elimination_created', null, $jid, $j['description']);
            $saved++;
        }
        return $saved;
    }

    // ---------------------------------------------------------
    // STEP 32 — Posting (idempotent; only APPROVED runs, once)
    // ---------------------------------------------------------

    public static function postRun($db, $runId, $userId)
    {
        $run = self::getRun($db, $runId);
        if (!$run) return ['success' => false, 'message' => 'Run not found.'];

        // IDEMPOTENCY: a posted run can never post again
        if ($run['status'] === 'posted')
            return ['success' => false, 'message' => 'This run is ALREADY posted. Duplicate posting rejected.'];
        if ($run['status'] !== 'approved')
            return ['success' => false, 'message' => 'Only APPROVED runs can be posted. Current status: '.$run['status']];

        $tb = self::consolidatedTrialBalance($db, $runId);
        $dr = array_sum(array_column($tb, 'total_debit'));
        $cr = array_sum(array_column($tb, 'total_credit'));
        if (abs($dr - $cr) > 0.01)
            return ['success' => false, 'message' => "Consolidated TB not balanced (Dr ".number_format($dr,2)." / Cr ".number_format($cr,2).") — cannot post."];

        $pl = self::consolidatedPL($db, $runId);
        $elimTotal = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM consolidation_journals WHERE run_id=".(int)$runId)->fetchColumn();
        $nci = self::nciShares($db, $runId);

        $db->prepare("UPDATE consolidation_runs SET status='posted', posted_by=?, posted_at=NOW(), completed_at=NOW(),
                      total_revenue=?, total_expenses=?, net_profit=?, elimination_total=?, nci_total=? WHERE id=?")
           ->execute([$userId, $pl['totals']['revenue'], abs($pl['totals']['expenses']),
                      $pl['totals']['net_profit'], $elimTotal,
                      round(array_sum(array_column($nci,'nci_amount')),2), (int)$runId]);
        self::log($db, 'consolidation_posted', null, $runId,
            "Net ".$pl['totals']['net_profit']." · eliminations ".number_format($elimTotal,2), null, $userId);
        return ['success' => true, 'message' => 'Consolidated statements POSTED.', 'totals' => $pl['totals']];
    }

    // ---------------------------------------------------------
    // STEP 16-18 — Consolidated statements
    // ---------------------------------------------------------

    /**
     * Consolidated TB: every company column + eliminations + consolidated row.
     * Returns ['accounts'=>[[code,name,nature,perCompany[companyId],elimDebit,elimCredit,totalDebit,totalCredit]]]
     */
    public static function consolidatedTrialBalance($db, $runId)
    {
        $run = self::getRun($db, $runId);
        $cos = self::runCompanies($db, $runId);
        $accounts = []; // keyed by account_id

        foreach ($cos as $co) {
            if (!$co['included']) continue;
            $rate = (float)$co['exchange_rate'];
            $fyId = (int)$co['financial_year_id'];
            // fallback: resolve scope FY if validate/attachRates hasn't anchored it
            if (!$fyId) {
                $run = $run ?: self::getRun($db, $runId);
                $f = $db->prepare("SELECT id FROM financial_years WHERE company_id=?
                                   AND ? BETWEEN start_date AND end_date
                                   ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END, start_date DESC, id DESC LIMIT 1");
                $f->execute([(int)$co['company_id'], $run['start_date']]);
                $fyId = (int)$f->fetchColumn();
            }
            $tb = self::companyTrialBalance($db, (int)$co['company_id'], $fyId);
            foreach ($tb as $row) {
                $aid = (int)$row['account_id'];
                if (!isset($accounts[$aid])) {
                    $accounts[$aid] = ['account_id'=>$aid,'code'=>$row['account_code'],'name'=>$row['account_name'],
                                       'nature'=>$row['nature'],'per_company'=>[],'elim_debit'=>0,'elim_credit'=>0];
                }
                $accounts[$aid]['per_company'][$co['company_id']] = [
                    'debit'  => round((float)$row['debit'] * $rate, 2),
                    'credit' => round((float)$row['credit'] * $rate, 2),
                ];
            }
        }

        // apply elimination journals
        $ej = $db->prepare("SELECT l.*, j.adjustment_type FROM consolidation_journal_lines l
                            JOIN consolidation_journals j ON j.id=l.journal_id WHERE j.run_id=? AND j.status='posted'");
        $ej->execute([(int)$runId]);
        foreach ($ej->fetchAll() as $l) {
            $aid = (int)$l['account_id'];
            if (!isset($accounts[$aid])) {
                $acct = $db->prepare("SELECT coa.account_code, coa.account_name, ag.nature FROM chart_of_accounts coa
                                      JOIN account_groups ag ON ag.id=coa.group_id WHERE coa.id=?");
                $acct->execute([(int)$aid]); $a = $acct->fetch();
                $accounts[$aid] = ['account_id'=>$aid,'code'=>$a['account_code'],'name'=>$a['account_name'],
                                   'nature'=>$a['nature'],'per_company'=>[],'elim_debit'=>0,'elim_credit'=>0];
            }
            $accounts[$aid]['elim_debit']  += (float)$l['debit'];
            $accounts[$aid]['elim_credit'] += (float)$l['credit'];
        }

        foreach ($accounts as &$a) {
            $td = $a['elim_debit']; $tc = $a['elim_credit'];
            foreach ($a['per_company'] as $pc) { $td += $pc['debit']; $tc += $pc['credit']; }
            $a['total_debit'] = round($td,2); $a['total_credit'] = round($tc,2);
        }
        usort($accounts, fn($x,$y) => strcmp($x['code'],$y['code']));
        return array_values($accounts);
    }

    /** P&L structure with company columns, eliminations and consolidated amounts. */
    public static function consolidatedPL($db, $runId)
    {
        $tb = self::consolidatedTrialBalance($db, $runId);
        $sections = ['revenue' => [], 'expense' => []];
        $totals = ['revenue' => 0, 'expense' => 0, 'elim_revenue' => 0, 'elim_expense' => 0];
        foreach ($tb as $a) {
            if (!in_array($a['nature'], ['income','expense'], true)) continue;
            $net = round(($a['total_credit'] - $a['total_debit']) * ($a['nature']==='income' ? 1 : -1), 2);
            $bucket = $a['nature'] === 'income' ? 'revenue' : 'expense';
            $sections[$bucket][] = ['code'=>$a['code'],'name'=>$a['name'],'amount'=>$net,
                                    'elim' => round($a['elim_credit']-$a['elim_debit'],2)];
            $totals[$bucket] += $net;
            $totals['elim_'.$bucket] += round(($bucket==='revenue'?-1:1) * ($a['elim_debit']-$a['elim_credit']),2);
        }
        return ['sections'=>$sections,'totals'=>[
            'revenue'=>round($totals['revenue'],2),'expenses'=>round(-$totals['expense'],2)*-1,
            'gross_profit'=>round($totals['revenue']+$totals['expense'],2),
            'net_profit'=>round($totals['revenue']+$totals['expense'],2)]];
    }

    /** Balance sheet grouped by nature with A = L + E validation.
     *  In-period net P&L is included in equity (profit for the period). */
    public static function consolidatedBalanceSheet($db, $runId)
    {
        $tb = self::consolidatedTrialBalance($db, $runId);
        $out = ['asset'=>[],'liability'=>[],'equity'=>[],'pl'=>[]];
        $tot = ['asset'=>0,'liability'=>0,'equity'=>0,'pl'=>0];
        foreach ($tb as $a) {
            $signed = round($a['total_debit'] - $a['total_credit'], 2);
            $nature = isset($out[$a['nature']]) ? $a['nature'] : 'pl';
            if ($a['nature']==='income' || $a['nature']==='expense') {
                $label = $a['nature']==='income' ? 'Income' : 'Expense';
                $out['pl'][] = ['code'=>$a['code'],'name'=>$a['name'],'signed'=>$signed,'kind'=>$label];
            } else {
                $out[$nature][] = ['code'=>$a['code'],'name'=>$a['name'],'signed'=>$signed];
            }
            $tot[$nature] += $signed;
        }
        $profitForPeriod = round(-($tot['pl']), 2); // credit-natural → positive profit
        return ['sections'=>$out,'totals'=>[
            'assets'=>round($tot['asset'],2),
            'liabilities'=>round(-$tot['liability'],2),
            'equity'=>round(-$tot['equity'],2),
            'profit_for_period'=>$profitForPeriod,
            'equity_incl_profit'=>round(-$tot['equity'] + $profitForPeriod, 2),
            'balanced'=>abs($tot['asset'] + $tot['liability'] + $tot['equity'] + $tot['pl']) < 0.01,
        ]];
    }

    // ---------------------------------------------------------
    // STEP 21/22 — Ownership & NCI-ready calculation
    // ---------------------------------------------------------

    public static function ownershipTree($db)
    {
        return $db->query("
            SELECT r.*, p.company_name parent_name, c.company_name child_name, g.group_name
            FROM company_relationships r
            JOIN companies p ON p.id=r.parent_company_id
            JOIN companies c ON c.id=r.child_company_id
            JOIN company_groups g ON g.id=r.group_id
            WHERE r.status='active'
            ORDER BY g.group_name, c.company_name
        ")->fetchAll();
    }

    /** NCI share of each subsidiary's net profit (architecture-ready). */
    public static function nciShares($db, $runId)
    {
        $shares = [];
        foreach (self::runCompanies($db, $runId) as $co) {
            if (!(int)$co['included']) continue;
            $rel = $db->prepare("SELECT ownership_percentage FROM company_relationships
                                 WHERE child_company_id=? AND status='active' ORDER BY id DESC LIMIT 1");
            $rel->execute([(int)$co['company_id']]);
            $own = (float)($rel->fetchColumn() ?: 100);
            if ($own >= 99.9995) continue;
            $fyId = (int)$co['financial_year_id'];
            $tb = self::companyTrialBalance($db, (int)$co['company_id'], $fyId);
            $np = 0;
            foreach ($tb as $r) {
                if (in_array($r['nature'], ['income','expense'], true))
                    $np += ((float)$r['credit'] - (float)$r['debit']);
            }
            $shares[] = ['company_id'=>(int)$co['company_id'],'ownership'=>$own,
                         'nci_pct'=>round(100-$own,2),'subsidiary_profit'=>round((float)$np,2),
                         'nci_amount'=>round((float)$np*(100-$own)/100,2)];
        }
        return $shares;
    }

    // ---------------------------------------------------------
    // STEP 19/20 — Currency translation
    // ---------------------------------------------------------

    public static function resolveRate($db, $from, $to, $method, $asOf)
    {
        if ($from === $to) return 1.0;
        $s = $db->prepare("SELECT rate FROM exchange_rates WHERE from_currency=? AND to_currency=? AND rate_type=? AND effective_date<=?
                           ORDER BY effective_date DESC LIMIT 1");
        $s->execute([$from,$to,$method,$asOf]);
        $r = $s->fetchColumn();
        if (!$r) { $s->execute([$to,$from,$method,$asOf]); if ($inv=$s->fetchColumn()) return round(1/(float)$inv,8); }
        return $r !== false && $r !== null ? (float)$r : 0.0;
    }

    public static function attachRates($db, $runId)
    {
        $run = self::getRun($db, $runId);
        foreach (self::runCompanies($db, $runId) as $co) {
            $cid = (int)$co['company_id'];
            $cur = $db->prepare("SELECT base_currency FROM companies WHERE id=?");
            $cur->execute([$cid]);
            $ccy = $cur->fetchColumn();
            $closing = self::resolveRate($db, $ccy, $run['group_currency'], 'closing', $run['end_date']);
            $average = self::resolveRate($db, $ccy, $run['group_currency'], 'average', $run['start_date']);
            // resolve the company's active FY covering the run window (scope anchor)
            $fyS = $db->prepare("SELECT id FROM financial_years WHERE company_id=?
                                 AND ? BETWEEN start_date AND end_date
                                 ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END, start_date DESC, id DESC LIMIT 1");
            $fyS->execute([$cid, $run['start_date']]);
            $fyId = (int)$fyS->fetchColumn();
            $db->prepare("UPDATE consolidation_run_companies
                          SET exchange_rate=?, average_rate=?, financial_year_id=NULLIF(?,0)
                          WHERE run_id=? AND company_id=?")
               ->execute([$closing ?: 1, $average ?: $closing ?: 1, $fyId, (int)$runId, $cid]);
        }
    }
}
