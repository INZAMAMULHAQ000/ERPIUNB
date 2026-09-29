<?php
/**
 * P2 — RFQ & Vendor Quotation Service
 *
 * Traceability (STEP 33): PR -> pr_item_id -> RFQ item -> quotation item
 * -> evaluation -> selection/award. No re-entry anywhere.
 * ALL monetary math server-side. Browser totals never trusted.
 */

require_once __DIR__ . '/../finance/group/CompanyService.php';

class RfqService
{
    const STATUSES = ['draft','open','sent','partially_responded','fully_responded','evaluation','awarded','cancelled','closed'];
    const QSTATUSES = ['draft','received','under_evaluation','shortlisted','rejected','selected','expired','cancelled'];

    // ---------------------------------------------------------
    public static function log($db,$action,$refId=null,$details=null,$reason=null,$userId=null)
    {
        $db->prepare("INSERT INTO procurement_audit (action,pr_id,details,reason,ip_address,user_id)
                      VALUES (?,?,?,?,?,?)")
            ->execute([$action,null,$details !== null ? ('RFQ#'.$refId.' '.$details) : null,
                       $reason,$_SERVER['REMOTE_ADDR'] ?? null,$userId ?? ($_SESSION['user_id'] ?? null)]);
        // also write into dedicated chain table via procurement_audit is enough for P2
    }

    // ---------------------------------------------------------
    // STEP 2 — concurrency-safe numbering (reuses P1 sequence infra)
    // ---------------------------------------------------------
    public static function nextNumber($db)
    {
        $year = (int)date('Y');
        $db->prepare("INSERT INTO procurement_sequences (seq_type,seq_year,last_number) VALUES ('rfq',?,0)
                      ON DUPLICATE KEY UPDATE last_number=last_number")->execute([$year]);
        $db->prepare("UPDATE procurement_sequences SET last_number=LAST_INSERT_ID(last_number+1)
                      WHERE seq_type='rfq' AND seq_year=?")->execute([$year]);
        return sprintf('RFQ-%d-%06d', $year, (int)$db->lastInsertId());
    }

    public static function getRFQ($db,$id)
    {
        $s=$db->prepare("SELECT r.*, u.name requester_name, c.company_name c_name, d.department_name, p.project_name, cc.cost_center_name
                         FROM request_for_quotations r
                         LEFT JOIN users u ON u.id=r.requester_id
                         LEFT JOIN companies c ON c.id=r.company_id
                         LEFT JOIN departments d ON d.id=r.department_id
                         LEFT JOIN projects p ON p.id=r.project_id
                         LEFT JOIN cost_centers cc ON cc.id=r.cost_center_id
                         WHERE r.id=?");
        $s->execute([(int)$id]);
        return $s->fetch();
    }
    public static function getItems($db,$rfqId){ $s=$db->prepare("SELECT * FROM rfq_items WHERE rfq_id=? ORDER BY id"); $s->execute([(int)$rfqId]); return $s->fetchAll(); }
    public static function getItem($db,$itemId){ $s=$db->prepare("SELECT * FROM rfq_items WHERE id=?"); $s->execute([(int)$itemId]); return $s->fetch(); }
    public static function getVendors($db,$rfqId){
        $s=$db->prepare("SELECT rv.*, v.vendor_name, v.email vendor_email FROM rfq_vendors rv JOIN vendors v ON v.id=rv.vendor_id
                         WHERE rv.rfq_id=? ORDER BY rv.id"); $s->execute([(int)$rfqId]); return $s->fetchAll();
    }
    public static function getQuotations($db,$rfqId){
        $s=$db->prepare("SELECT q.*, v.vendor_name FROM vendor_quotations q JOIN vendors v ON v.id=q.vendor_id
                         WHERE q.rfq_id=? ORDER BY q.grand_total"); $s->execute([(int)$rfqId]); return $s->fetchAll();
    }
    public static function getQuotation($db,$id){
        $s=$db->prepare("SELECT q.*, v.vendor_name, r.rfq_number FROM vendor_quotations q
                         JOIN vendors v ON v.id=q.vendor_id JOIN request_for_quotations r ON r.id=q.rfq_id WHERE q.id=?");
        $s->execute([(int)$id]); return $s->fetch();
    }
    public static function getQuoteItems($db,$quoteId){
        $s=$db->prepare("SELECT qi.*, ri.item_name rfq_item_name FROM vendor_quotation_items qi
                         LEFT JOIN rfq_items ri ON ri.id=qi.rfq_item_id WHERE qi.quotation_id=? ORDER BY qi.is_alternative, qi.id");
        $s->execute([(int)$quoteId]); return $s->fetchAll();
    }
    public static function getOtherCharges($db,$quoteId){
        $s=$db->prepare("SELECT * FROM quotation_other_charges WHERE quotation_id=? ORDER BY id");
        $s->execute([(int)$quoteId]); return $s->fetchAll();
    }

    // ---------------------------------------------------------
    // STEP 3 — Create RFQ from an APPROVED PR (traceability kept)
    // ---------------------------------------------------------
    public static function createFromPR($db,$prId,array $overrides,$userId)
    {
        require_once __DIR__ . '/../procurement/ProcurementService.php';
        $pr = ProcurementService::getPR($db,$prId);
        if (!$pr) return ['success'=>false,'message'=>'Requisition not found.'];
        if ($pr['status']!=='approved') return ['success'=>false,'message'=>'Only APPROVED requisitions can become an RFQ (current: '.$pr['status'].').'];
        $items = ProcurementService::getRfqReadyItems($db,$prId);
        if (!count($items)) return ['success'=>false,'message'=>'The requisition has no items to quote.'];

        try {
            $db->beginTransaction();
            $number = self::nextNumber($db);
            $ins = $db->prepare("INSERT INTO request_for_quotations
                (rfq_number,pr_id,company_id,department_id,project_id,cost_center_id,requester_id,
                 rfq_date,required_date,response_due,currency,delivery_location,payment_terms,delivery_terms,notes,status,
                 estimated_pr_value,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $est = (float)$pr['grand_total'];
            $ins->execute([
                $number,(int)$prId,(int)$pr['company_id'],$pr['department_id'],$pr['project_id'],$pr['cost_center_id'],
                (int)$pr['requester_id'],
                $overrides['rfq_date'] ?? date('Y-m-d'),
                $overrides['required_date'] ?? $pr['required_date'],
                $overrides['response_due'] ?? date('Y-m-d', strtotime('+7 days')),
                $overrides['currency'] ?? $pr['currency'],
                $overrides['delivery_location'] ?? null,
                $overrides['payment_terms'] ?? 'Net 30',
                $overrides['delivery_terms'] ?? 'Delivered to site',
                trim($overrides['notes'] ?? '') ?: null,
                'draft', $est, $userId,
            ]);
            $rfqId=(int)$db->lastInsertId();

            $li=$db->prepare("INSERT INTO rfq_items
                (rfq_id,pr_item_id,item_name,description,specification,quantity,unit,required_date,delivery_location,tax_percent,est_unit_price,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($items as $it) {
                $li->execute([
                    $rfqId,(int)$it['id'],$it['item_name'],$it['description'] ?? null,null,
                    (float)$it['quantity'],$it['unit'],
                    !empty($it['required_date'])?$it['required_date']:null,
                    $overrides['delivery_location'] ?? null, 0, (float)$it['est_unit_price'], null,
                ]);
            }
            self::log($db,'rfq_created',$rfqId,"from PR ".$pr['pr_number']." (".count($items)." items)",null,$userId);
            $db->commit();
            return ['success'=>true,'id'=>$rfqId,'number'=>$number];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    /** Update editable header + items while status in draft/open. */
    public static function updateRFQ($db,$rfqId,array $hdr,array $lines,$userId)
    {
        $rfq=self::getRFQ($db,$rfqId);
        if (!$rfq) return ['success'=>false,'message'=>'Not found.'];
        if (!in_array($rfq['status'],['draft','open'],true))
            return ['success'=>false,'message'=>'Status "'.$rfq['status'].'" cannot be edited.'];
        try{
            $db->beginTransaction();
            $db->prepare("UPDATE request_for_quotations SET required_date=?, response_due=?, currency=?, delivery_location=?,
                          payment_terms=?, delivery_terms=?, notes=? WHERE id=?")->execute([
                $hdr['required_date'],$hdr['response_due'],$hdr['currency'],$hdr['delivery_location'] ?? null,
                $hdr['payment_terms'] ?? null,$hdr['delivery_terms'] ?? null,$hdr['notes'] ?? null,(int)$rfqId]);
            // items: update existing rows only when they still map to a PR item; allow adding manual rows
            foreach ($lines as $l) {
                if (empty($l['item_name'])) continue;
                if (!empty($l['id'])) {
                    $db->prepare("UPDATE rfq_items SET specification=?, quantity=?, unit=?, tax_percent=?, est_unit_price=? WHERE id=? AND rfq_id=?")
                       ->execute([$l['specification'] ?? null, round((float)$l['quantity'],3), $l['unit'] ?? 'pcs',
                                  round((float)($l['tax_percent'] ?? 0),3), round((float)($l['est_unit_price'] ?? 0),2),
                                  (int)$l['id'],(int)$rfqId]);
                } else {
                    $db->prepare("INSERT INTO rfq_items (rfq_id,item_name,specification,quantity,unit,tax_percent,est_unit_price)
                                  VALUES (?,?,?,?,?,?,?)")
                       ->execute([(int)$rfqId,trim($l['item_name']),$l['specification'] ?? null,
                                  round((float)$l['quantity'],3),$l['unit'] ?? 'pcs',
                                  round((float)($l['tax_percent'] ?? 0),3),round((float)($l['est_unit_price'] ?? 0),2)]);
                }
            }
            self::log($db,'rfq_edited',$rfqId,null,null,$userId);
            $db->commit();
            return ['success'=>true];
        }catch(Exception $e){ if($db->inTransaction())$db->rollBack(); return ['success'=>false,'message'=>$e->getMessage()]; }
    }

    // ---------------------------------------------------------
    // STEP 5/6 — vendor management on the RFQ
    // ---------------------------------------------------------
    public static function addVendors($db,$rfqId,array $vendorIds,$userId)
    {
        $rfq=self::getRFQ($db,$rfqId);
        if (!$rfq || in_array($rfq['status'],['cancelled','closed','awarded'],true))
            return ['success'=>false,'message'=>'Vendors cannot be added in current status.'];
        $added=0; $skipped=[];
        $st=$db->prepare("INSERT IGNORE INTO rfq_vendors (rfq_id,vendor_id,status,sent_by) VALUES (?,?,'selected',?)");
        foreach ($vendorIds as $vid) {
            $vid=(int)$vid;
            $v=$db->query("SELECT status, email FROM vendors WHERE id=$vid")->fetch();
            if (!$v) { $skipped[]="#$vid missing"; continue; }
            if ($v['status']!=='active'){ $skipped[]=$v['vendor_name'].' inactive'; continue; }   // STEP 5 validation
            $st->execute([(int)$rfqId,$vid,$userId]);
            if ($st->rowCount()) $added++;
        }
        if ($added) self::log($db,'rfq_vendor_added',$rfqId,"+$added vendor(s)",null,$userId);
        // keep draft vs open semantics
        if ($added && $rfq['status']==='draft') $db->prepare("UPDATE request_for_quotations SET status='open' WHERE id=?")->execute([(int)$rfqId]);
        return ['success'=>true,'added'=>$added,'skipped'=>$skipped];
    }

    // ---------------------------------------------------------
    // STEP 7 — send (records communication; no external mailer assumed)
    // ---------------------------------------------------------
    public static function send($db,$rfqId,$userId,?array $vendorIds=null)
    {
        $rfq=self::getRFQ($db,$rfqId);
        if (!$rfq) return ['success'=>false,'message'=>'Not found.'];
        if (!in_array($rfq['status'],['draft','open','sent'],true))
            return ['success'=>false,'message'=>'Cannot send in status '.$rfq['status'].'.'];
        try {
            $db->beginTransaction();
            $sql = "SELECT rv.*, v.email vendor_email, v.vendor_name FROM rfq_vendors rv JOIN vendors v ON v.id=rv.vendor_id
                    WHERE rv.rfq_id=?";
            $p=[(int)$rfqId];
            if ($vendorIds) { $in=implode(',',array_map('intval',$vendorIds)); $sql.=" AND rv.id IN ($in)"; }
            $sth=$db->prepare($sql); $sth->execute($p);
            $sent=0;
            foreach ($sth->fetchAll() as $rv) {
                $recipient = $rv['vendor_email'] ?: 'manual';
                $db->prepare("UPDATE rfq_vendors SET status='sent', sent_at=NOW(), sent_by=? WHERE id=?")->execute([$userId,(int)$rv['id']]);
                $db->prepare("INSERT INTO rfq_vendor_communications (rfq_vendor_id,method,recipient,status,sent_by)
                              VALUES (?, 'email', ?, 'sent', ?)")->execute([(int)$rv['id'],$recipient,$userId]);
                self::log($db,'rfq_sent',$rfqId,"to ".$rv['vendor_name'],null,$userId);
                $sent++;
            }
            if ($sent) {
                $newStatus = $rfq['status']==='draft' ? 'sent' : ($rfq['status']==='open'?'sent':'sent');
                $db->prepare("UPDATE request_for_quotations SET status='sent' WHERE id=?")->execute([(int)$rfqId]);
            }
            $db->commit();
            return ['success'=>true,'sent'=>$sent];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    // ---------------------------------------------------------
    // STEP 9/10/12/13 — create/receive quotation (partial + alternatives)
    // ---------------------------------------------------------
    private static function calcLine(array $l): array
    {
        $qty=max(0.0,(float)($l['quantity']??0)); $price=max(0.0,(float)($l['unit_price']??0));
        $disc=min(max(0.0,(float)($l['discount_amount']??0)), $qty*$price);
        $taxP=max(0.0,(float)($l['tax_percent']??0));
        $sub=round($qty*$price,2); $tax=round(($sub-$disc)*$taxP/100,2);
        return [$sub,$tax,$disc,round($sub-$disc+$tax,2)];
    }

    public static function saveQuotation($db,$rfqId,$quotationId,array $header,array $lines,array $charges,$userId)
    {
        $rfq=self::getRFQ($db,$rfqId);
        if (!$rfq) return ['success'=>false,'errors'=>['RFQ not found.']];
        if (in_array($rfq['status'],['cancelled','closed','awarded'],true))
            return ['success'=>false,'errors'=>['RFQ is '.$rfq['status'].' — quotations locked.']];

        $errs=[]; $clean=[];
        $row=0;
        foreach ($lines as $l) {
            $row++;
            if (trim((string)($l['item_name'] ?? ''))==='' ) { if(empty($l['rfq_item_id'])) continue; }
            if ((float)($l['quantity'] ?? 0)<=0){ $errs[]="Line $row: quantity > 0 required."; continue; }
            $clean[]=[
                'rfq_item_id'=>!empty($l['rfq_item_id'])?(int)$l['rfq_item_id']:null,
                'is_alternative'=>!empty($l['is_alternative'])?1:0,
                'item_name'=>trim($l['item_name'] ?? '') ?: '(quoted)',
                'description'=>$l['description'] ?? null,
                'quantity'=>(float)$l['quantity'],'unit'=>$l['unit'] ?? 'pcs',
                'unit_price'=>(float)($l['unit_price'] ?? 0),'discount_amount'=>(float)($l['discount_amount'] ?? 0),
                'tax_percent'=>(float)($l['tax_percent'] ?? 0),
                'delivery_days'=>($l['delivery_days'] ?? '')!==''?(int)$l['delivery_days']:null,
                'warranty_months'=>($l['warranty_months'] ?? '')!==''?(int)$l['warranty_months']:null,
                'notes'=>$l['notes'] ?? null,
            ];
        }
        if (!count($clean)) $errs[]='At least one quoted line is required.';
        if ($errs) return ['success'=>false,'errors'=>$errs];

        try {
            $db->beginTransaction();
            if ($quotationId) {
                $q=$db->prepare("SELECT * FROM vendor_quotations WHERE id=?"); $q->execute([(int)$quotationId]);
                $existing=$q->fetch();
                if (!$existing) throw new Exception('Quotation not found.');
                $qid=(int)$quotationId;
                $db->exec("DELETE FROM vendor_quotation_items WHERE quotation_id=$qid");
                $db->exec("DELETE FROM quotation_other_charges WHERE quotation_id=$qid");
            } else {
                $settings=$db->query("SELECT setting_value FROM procurement_settings WHERE setting_key='quote_prefix'")->fetchColumn();
                $year=(int)date('Y');
                $db->prepare("INSERT INTO procurement_sequences (seq_type,seq_year,last_number) VALUES ('quote',?,0)
                              ON DUPLICATE KEY UPDATE last_number=last_number")->execute([$year]);
                $db->prepare("UPDATE procurement_sequences SET last_number=LAST_INSERT_ID(last_number+1) WHERE seq_type='quote' AND seq_year=?")->execute([$year]);
                $num=sprintf('%s-%d-%06d',($settings?:'QUO'),$year,(int)$db->lastInsertId());
                $ins=$db->prepare("INSERT INTO vendor_quotations (quotation_number,rfq_id,vendor_id,created_by) VALUES (?,?,?,?)");
                $ins->execute([$num,(int)$rfqId,(int)$header['vendor_id'],$userId]);
                $qid=(int)$db->lastInsertId();
            }
            // header
            $db->prepare("UPDATE vendor_quotations SET quotation_date=?, valid_until=?, currency=?, payment_terms=?, delivery_terms=?,
                          incoterms=?, warranty_terms=?, vendor_reference=?, lead_time_days=?, notes=?, other_charges_total=?,
                          subtotal=?, discount_total=?, tax_total=?, grand_total=?, status=IF(status='draft','received',status),
                          received_at=COALESCE(received_at,NOW()) WHERE id=?")->execute([
                $header['quotation_date'] ?? date('Y-m-d'), $header['valid_until'] ?? null,
                $header['currency'] ?? 'AED', $header['payment_terms'] ?? null, $header['delivery_terms'] ?? null,
                $header['incoterms'] ?? null, $header['warranty_terms'] ?? null, $header['vendor_reference'] ?? null,
                ($header['lead_time_days'] ?? '')!==''?(int)$header['lead_time_days']:null,
                $header['notes'] ?? null, 0,0,0,0,0,$qid]);

            $sub=0;$tx=0;$dc=0;
            $li=$db->prepare("INSERT INTO vendor_quotation_items
                (quotation_id,rfq_item_id,is_alternative,item_name,description,quantity,unit,unit_price,discount_amount,tax_percent,
                 line_subtotal,line_tax,line_total,delivery_days,warranty_months,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($clean as $l) {
                list($ls,$lt,$ld,$ltot)=self::calcLine($l);
                $sub+=$ls; $tx+=$lt; $dc+=$ld;
                $li->execute([$qid,$l['rfq_item_id'],$l['is_alternative'],$l['item_name'],$l['description'],
                              $l['quantity'],$l['unit'],$l['unit_price'],$l['discount_amount'],$l['tax_percent'],
                              $ls,$lt,$ltot,$l['delivery_days'],$l['warranty_months'],$l['notes']]);
            }
            $chargesTotal=0;
            $ch=$db->prepare("INSERT INTO quotation_other_charges (quotation_id,charge_name,amount) VALUES (?,?,?)");
            foreach ($charges as $c) {
                $name=trim($c['charge_name'] ?? ''); $amt=(float)($c['amount'] ?? 0);
                if ($name===''||$amt==0) continue;
                $ch->execute([$qid,$name,round($amt,2)]); $chargesTotal+=round($amt,2);
            }
            $grand=round($sub-$dc+$tx+$chargesTotal,2);
            $db->prepare("UPDATE vendor_quotations SET subtotal=?, discount_total=?, tax_total=?, grand_total=?, other_charges_total=? WHERE id=?")
               ->execute([round($sub,2),round($dc,2),round($tx,2),$grand,round($chargesTotal,2),$qid]);

            // mark vendor responded + RFQ partially/fully responded
            $rfqV=$db->prepare("SELECT id FROM rfq_vendors WHERE rfq_id=? AND vendor_id=?");
            $rfqV->execute([(int)$rfqId,(int)$header['vendor_id']]);
            $rvId=(int)$rfqV->fetchColumn();
            if ($rvId) {
                $db->prepare("UPDATE rfq_vendors SET status='responded', responded_at=NOW() WHERE id=?")->execute([$rvId]);
            }
            $tot=(int)$db->query("SELECT COUNT(*) FROM rfq_vendors WHERE rfq_id=$rfqId")->fetchColumn();
            $res=(int)$db->query("SELECT COUNT(*) FROM rfq_vendors WHERE rfq_id=$rfqId AND status IN ('responded','declined')")->fetchColumn();
            $rStatus = $res>=$tot ? 'fully_responded' : 'partially_responded';
            if (!in_array($rfq['status'],['evaluation','awarded'],true))
                $db->prepare("UPDATE request_for_quotations SET status=? WHERE id=?")->execute([$rStatus,(int)$rfqId]);

            self::log($db,$quotationId?'quotation_edited':'quotation_created',$rfqId,"quote #$qid",null,$userId);
            if (!$quotationId) self::log($db,'vendor_response_received',$rfqId,"vendor #".$header['vendor_id'],null,$userId);
            $db->commit();
            return ['success'=>true,'id'=>$qid];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'errors'=>['Save failed: '.$e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // STEP 16-19 — criteria, weighted scoring, ranking
    // ---------------------------------------------------------
    public static function saveCriteria($db,$rfqId,array $criteria,$userId)
    {
        $db->exec("DELETE FROM rfq_evaluation_criteria WHERE rfq_id=".(int)$rfqId);
        $ins=$db->prepare("INSERT INTO rfq_evaluation_criteria (rfq_id,criterion,weight,sort_order) VALUES (?,?,?,?)");
        $n=0; $total=0;
        foreach ($criteria as $i=>$c) {
            $name=trim($c['criterion'] ?? ''); $w=(float)($c['weight'] ?? 0);
            if ($name==='' || $w<=0) continue;
            $ins->execute([(int)$rfqId,$name,round($w,2),$i+1]); $total+=$w; $n++;
        }
        if ($n && abs($total-100)>0.01) {
            $db->exec("DELETE FROM rfq_evaluation_criteria WHERE rfq_id=".(int)$rfqId);
            return ['success'=>false,'message'=>"Weights must total exactly 100% (you entered ".round($total,2)."%). Nothing saved."];
        }
        if ($n) self::log($db,'criteria_saved',$rfqId,"$n criteria / $total%",null,$userId);
        return ['success'=>true,'count'=>$n,'total'=>$total];
    }

    public static function saveEvaluations($db,$quotationId,array $scores,$userId)
    {
        $db->exec("DELETE FROM quotation_evaluations WHERE quotation_id=".(int)$quotationId);
        $ins=$db->prepare("INSERT INTO quotation_evaluations (quotation_id,criterion,weight,score,weighted_score,comments,evaluated_by)
                           VALUES (?,?,?,?,?,?,?)");
        $total=0;
        foreach ($scores as $s) {
            $w=(float)($s['weight'] ?? 0); $sc=min(10,max(0,(float)($s['score'] ?? 0)));
            $ws=round($sc*$w/10,3); $total+=$ws;
            $ins->execute([(int)$quotationId,trim($s['criterion'] ?? ''),$w,$sc,$ws,trim($s['comments'] ?? '') ?: null,$userId]);
        }
        $q=$db->prepare("SELECT rfq_id FROM vendor_quotations WHERE id=?"); $q->execute([(int)$quotationId]);
        $rfqId=(int)$q->fetchColumn();
        $db->prepare("UPDATE vendor_quotations SET status='under_evaluation' WHERE id=? AND status IN ('received','draft')")->execute([$quotationId]);
        $db->prepare("UPDATE request_for_quotations SET status='evaluation' WHERE id=? AND status NOT IN ('awarded')")->execute([$rfqId]);
        self::log($db,'quotation_evaluated',$rfqId,"quote #$quotationId total weighted ".number_format($total,2),null,$userId);
        return ['success'=>true,'total_weighted'=>$total];
    }

    /** STEP 19 — rank all quotations of an RFQ by weighted score (desc), then price (asc). */
    public static function rankings($db,$rfqId): array
    {
        $quotes=$db->query("
            SELECT q.*, v.vendor_name,
              (SELECT COALESCE(SUM(weighted_score),0) FROM quotation_evaluations e WHERE e.quotation_id=q.id) wscore,
              (SELECT MIN(delivery_days) FROM vendor_quotation_items i WHERE i.quotation_id=q.id AND i.is_alternative=0 AND i.delivery_days IS NOT NULL) min_delivery,
              (SELECT MAX(warranty_months) FROM vendor_quotation_items i WHERE i.quotation_id=q.id AND i.is_alternative=0 AND i.warranty_months IS NOT NULL) max_warranty
            FROM vendor_quotations q JOIN vendors v ON v.id=q.vendor_id
            WHERE q.rfq_id=".(int)$rfqId." AND q.status NOT IN ('cancelled')
            ORDER BY wscore DESC, q.grand_total ASC")->fetchAll();
        foreach ($quotes as $i=>&$q) $q['rank']=$i+1;
        return $quotes;
    }

    // ---------------------------------------------------------
    // STEP 20/21/22 — selection, override, split award
    // ---------------------------------------------------------
    public static function select($db,$rfqId,$quotationId,$reason,$userId,$overrideReason=null,$level='rfq',$rfqItemId=null)
    {
        if (trim((string)$reason)==='') return ['success'=>false,'message'=>'Selection reason is REQUIRED.'];
        $rankings=self::rankings($db,$rfqId);
        $rankOf=null; foreach ($rankings as $r) if ((int)$r['id']===(int)$quotationId) $rankOf=(int)$r['rank'];
        if ($rankOf===null) return ['success'=>false,'message'=>'Quotation does not belong to this RFQ ranking.'];
        $isOverride=($rankOf>1);
        if ($isOverride && trim((string)$overrideReason)==='')
            return ['success'=>false,'message'=>'Manual override: a REASON why a non-#1 vendor was selected is REQUIRED.'];

        try {
            $db->beginTransaction();
            $q=$db->prepare("SELECT vendor_id FROM vendor_quotations WHERE id=?"); $q->execute([(int)$quotationId]);
            $vendorId=(int)$q->fetchColumn();

            if ($level==='rfq') {
                // whole-RFQ award: selected wins, others rejected
                $db->prepare("UPDATE vendor_quotations SET status='selected' WHERE id=?")->execute([(int)$quotationId]);
                $db->prepare("UPDATE vendor_quotations SET status='rejected'
                              WHERE rfq_id=? AND id<>? AND status NOT IN ('cancelled','expired')")
                   ->execute([(int)$rfqId,(int)$quotationId]);
                $db->prepare("DELETE FROM quotation_selection WHERE rfq_id=? AND award_level='item'")->execute([(int)$rfqId]);
            }
            $ins=$db->prepare("INSERT INTO quotation_selection
                (rfq_id,quotation_id,vendor_id,rfq_item_id,award_level,reason,is_override,override_reason,selected_by)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->execute([(int)$rfqId,$level==='rfq'?(int)$quotationId:null,$vendorId,
                           $level==='item'?$rfqItemId:null,$level,$reason,$isOverride?1:0,
                           $isOverride?trim($overrideReason):null,$userId]);
            if ($isOverride)
                self::log($db,'selection_overridden',$rfqId,"quote #$quotationId ranked #$rankOf chosen",$overrideReason,$userId);
            self::log($db,'vendor_selected',$rfqId,"quote #$quotationId (".$level.')',null,$userId);

            // close the RFQ once fully awarded (whole-RFQ or every item has an award)
            if ($level==='rfq') {
                $db->prepare("UPDATE request_for_quotations SET status='awarded' WHERE id=?")->execute([(int)$rfqId]);
            } else {
                $itemsTotal=(int)$db->query("SELECT COUNT(*) FROM rfq_items WHERE rfq_id=$rfqId")->fetchColumn();
                $awarded=(int)$db->query("SELECT COUNT(DISTINCT rfq_item_id) FROM quotation_selection WHERE rfq_id=$rfqId AND award_level='item'")->fetchColumn();
                if ($itemsTotal>0 && $awarded>=$itemsTotal)
                    $db->prepare("UPDATE request_for_quotations SET status='awarded' WHERE id=?")->execute([(int)$rfqId]);
                else
                    $db->prepare("UPDATE request_for_quotations SET status='evaluation' WHERE id=? AND status<>'awarded'")->execute([(int)$rfqId]);
            }
            $db->commit();
            return ['success'=>true,'override'=>$isOverride];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    // ---------------------------------------------------------
    // STEP 23/24/25 — cancellation, deadline, validity helpers
    // ---------------------------------------------------------
    public static function cancel($db,$rfqId,$userId,$reason)
    {
        if (trim((string)$reason)==='') return ['success'=>false,'message'=>'Cancellation reason REQUIRED.'];
        $rfq=self::getRFQ($db,$rfqId);
        if (!$rfq || in_array($rfq['status'],['cancelled','closed'])) return ['success'=>false,'message'=>'Already closed/cancelled.'];
        try{
            $db->beginTransaction();
            $db->prepare("UPDATE request_for_quotations SET status='cancelled', cancelled_by=?, cancelled_at=NOW(), cancel_reason=? WHERE id=?")
               ->execute([$userId,trim($reason),(int)$rfqId]);
            $db->exec("UPDATE vendor_quotations SET status='cancelled' WHERE rfq_id=".((int)$rfqId)." AND status NOT IN ('selected')");
            self::log($db,'rfq_cancelled',$rfqId,null,$reason,$userId);
            $db->commit();
            return ['success'=>true];
        }catch(Exception $e){ if($db->inTransaction())$db->rollBack(); return ['success'=>false,'message'=>$e->getMessage()]; }
    }

    /** STEP 24/25 — flag expired: RFQ past due with <full responses; quotations past valid_until. */
    public static function applyDeadlineFlags($db)
    {
        $today=date('Y-m-d');
        $db->exec("UPDATE vendor_quotations SET status='expired'
                   WHERE valid_until<'$today' AND status IN ('received','draft')");
        $rows=$db->query("SELECT r.id,
                            (SELECT COUNT(*) FROM rfq_vendors rv WHERE rv.rfq_id=r.id) tot,
                            (SELECT COUNT(*) FROM rfq_vendors rv WHERE rv.rfq_id=r.id AND status IN ('responded','declined')) res
                          FROM request_for_quotations r
                          WHERE r.response_due<'$today' AND r.status IN ('sent','partially_responded','open')")->fetchAll();
        foreach ($rows as $r) {
            if ((int)$r['res']>=(int)$r['tot']) continue;
            $db->prepare("UPDATE rfq_vendors SET status='expired' WHERE rfq_id=? AND status IN ('sent','selected')")->execute([(int)$r['id']]);
        }
        $db->exec("UPDATE request_for_quotations r
                   SET r.status='closed'
                   WHERE r.response_due<DATE_SUB('$today', INTERVAL 30 DAY) AND r.status IN ('sent','open')");
    }

    /** STEP 27 — savings estimate per RFQ (informational only). */
    public static function savingsSummary($db,$rfqId): array
    {
        $rfq=self::getRFQ($db,$rfqId);
        $best=$db->query("SELECT MIN(grand_total) m FROM vendor_quotations WHERE rfq_id=".(int)$rfqId." AND status IN ('selected','rejected','shortlisted')")->fetchColumn();
        $sel=$db->query("SELECT grand_total FROM vendor_quotations WHERE rfq_id=".(int)$rfqId." AND status='selected'")->fetchColumn();
        $est=(float)($rfq['estimated_pr_value'] ?? 0);
        $out=['estimated_pr_value'=>$est,'best_quote'=>$best!==null?(float)$best:null,'selected_value'=>$sel!==null?(float)$sel:null,
              'savings_vs_estimate'=>null,'savings_pct'=>null];
        if ($sel!==null && $est>0) {
            $out['savings_vs_estimate']=round($est-(float)$sel,2);
            $out['savings_pct']=$est>0?round(($est-(float)$sel)/$est*100,2):null;
        }
        return $out;
    }
}
