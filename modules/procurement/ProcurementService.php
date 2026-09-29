<?php
/**
 * P1 — Procurement Service
 * Purchase Requisition lifecycle, server-side calculations,
 * concurrency-safe numbering, configurable approval routing.
 *
 * GOLDEN RULES:
 *  - ALL monetary math is computed SERVER-SIDE from stored line inputs.
 *    Browser totals are never trusted (Test P1 #2).
 *  - Status transitions are guarded; edit rules per STEP 11 enforced here.
 */

require_once __DIR__ . '/../finance/group/CompanyService.php';

class ProcurementService
{
    const STATUSES = ['draft','submitted','pending_approval','approved','rejected','cancelled','closed'];
    const ITEM_TYPES = ['stock','non_stock','service','asset','expense'];
    const PRIORITIES = ['low','normal','high','urgent'];

    // ---------------------------------------------------------
    // Audit
    // ---------------------------------------------------------
    public static function log($db, $action, $prId = null, $details = null, $reason = null, $userId = null)
    {
        $db->prepare("INSERT INTO procurement_audit (action, pr_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$action, $prId, $details, $reason, $_SERVER['REMOTE_ADDR'] ?? null,
                       $userId ?? ($_SESSION['user_id'] ?? null)]);
    }

    // ---------------------------------------------------------
    // Settings
    // ---------------------------------------------------------
    public static function getSettings($db): array
    {
        $rows = $db->query("SELECT setting_key, setting_value FROM procurement_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge([
            'pr_prefix'=>'PR','rfq_prefix'=>'RFQ','quote_prefix'=>'QUO','po_prefix'=>'PO','grn_prefix'=>'GRN',
            'default_currency'=>'AED',
        ], $rows ?: []);
    }

    public static function saveSettings($db, array $data, $userId)
    {
        $st = $db->prepare("UPDATE procurement_settings SET setting_value=? WHERE setting_key=?");
        foreach ($data as $k => $v) $st->execute([(string)$v, $k]);
        self::log($db, 'settings_updated', null, json_encode($data), null, $userId);
    }

    /**
     * STEP 4 — concurrency-safe numbering: PR-2026-000001
     * Uses a single atomic UPSERT + read-back inside the caller's transaction.
     */
    public static function nextNumber($db, $type, $prefix)
    {
        $year = (int)date('Y');
        $db->prepare("INSERT INTO procurement_sequences (seq_type, seq_year, last_number) VALUES (?, ?, 0)
                      ON DUPLICATE KEY UPDATE last_number = last_number")
           ->execute([$type, $year]);
        $db->prepare("UPDATE procurement_sequences SET last_number = LAST_INSERT_ID(last_number + 1)
                      WHERE seq_type = ? AND seq_year = ?")->execute([$type, $year]);
        $n = (int)$db->lastInsertId();
        return sprintf('%s-%d-%06d', $prefix, $year, $n);
    }

    // ---------------------------------------------------------
    // STEP 5 — server-side calculation (never trust browser math)
    // ---------------------------------------------------------

    /** Recalculate one line from its raw inputs. Returns [subtotal, tax, discount, total]. */
    public static function calcLine(array $line): array
    {
        $qty   = max(0.0, (float)($line['quantity'] ?? 0));
        $price = max(0.0, (float)($line['est_unit_price'] ?? 0));
        $disc  = min(max(0.0, (float)($line['discount_amount'] ?? 0)), $qty * $price); // never negative
        $taxPct = max(0.0, (float)($line['tax_percent'] ?? 0));
        $sub   = round($qty * $price, 2);
        $tax   = round(($sub - $disc) * $taxPct / 100, 2);
        $total = round($sub - $disc + $tax, 2);
        return [$sub, $tax, $disc, $total];
    }

    /** Recompute header totals from DB lines. */
    public static function recalcHeader($db, $prId): array
    {
        $lines = $db->query("SELECT line_subtotal, line_tax, discount_amount FROM purchase_requisition_items WHERE pr_id=".(int)$prId)->fetchAll();
        $sub = round(array_sum(array_column($lines,'line_subtotal')),2);
        $tx  = round(array_sum(array_column($lines,'line_tax')),2);
        $dc  = round(array_sum(array_column($lines,'discount_amount')),2);
        $gr  = round($sub - $dc + $tx, 2);
        $db->prepare("UPDATE purchase_requisitions SET subtotal=?, tax_total=?, discount_total=?, grand_total=? WHERE id=?")
           ->execute([$sub,$tx,$dc,$gr,(int)$prId]);
        return ['subtotal'=>$sub,'tax_total'=>$tx,'discount_total'=>$dc,'grand_total'=>$gr];
    }

    // ---------------------------------------------------------
    // Validation (STEP 10)
    // ---------------------------------------------------------

    private static function validateHeader($db, array $h): array
    {
        $errs = [];
        if (empty($h['requester_id']))          $errs[] = 'Requester is required.';
        if (empty($h['company_id']))            $errs[] = 'Company is required.';
        if (empty($h['department_id']))         $errs[] = 'Department is required.';
        if (empty($h['required_date']) || empty($h['request_date'])) $errs[] = 'Request & required dates are required.';
        if (!empty($h['required_date']) && !empty($h['request_date']) && $h['required_date'] < $h['request_date'])
                                                $errs[] = 'Required date cannot precede request date.';
        foreach ([['company_id','companies'],['department_id','departments'],['project_id','projects'],['cost_center_id','cost_centers']] as $x) {
            if (!empty($h[$x[0]])) {
                $s = $db->prepare("SELECT COUNT(*) FROM {$x[1]} WHERE id=?"); $s->execute([(int)$h[$x[0]]]);
                if (!(int)$s->fetchColumn()) $errs[] = ucfirst(str_replace('_id','',$x[0])) . ' does not exist.';
            }
        }
        if (!empty($h['priority']) && !in_array($h['priority'], self::PRIORITIES, true)) $errs[] = 'Invalid priority.';
        return $errs;
    }

    private static function validateLines($db, array $lines): array
    {
        $errs = [];
        if (!count($lines)) $errs[] = 'At least one item is required.';
        $row = 0;
        foreach ($lines as $l) {
            $row++;
            if (trim((string)($l['item_name'] ?? '')) === '') { $errs[] = "Line $row: item name required."; continue; }
            if (!in_array(($l['item_type'] ?? ''), self::ITEM_TYPES, true)) { $errs[] = "Line $row: invalid item type."; }
            if ((float)($l['quantity'] ?? 0) <= 0)      $errs[] = "Line $row: quantity must be > 0.";
            if ((float)($l['est_unit_price'] ?? 0) < 0) $errs[] = "Line $row: unit price cannot be negative.";
            if (!empty($l['preferred_vendor_id'])) {
                $s = $db->prepare("SELECT status FROM vendors WHERE id=?"); $s->execute([(int)$l['preferred_vendor_id']]);
                $v = $s->fetchColumn();
                if ($v === false)       $errs[] = "Line $row: preferred vendor does not exist.";
                elseif ($v !== 'active')$errs[] = "Line $row: preferred vendor is inactive.";
            }
            if (!empty($l['project_id']) || !empty($l['cost_center_id'])) {
                foreach ([['project_id','projects'],['cost_center_id','cost_centers']] as $x) {
                    if (!empty($l[$x[0]])) {
                        $s = $db->prepare("SELECT COUNT(*) FROM {$x[1]} WHERE id=?"); $s->execute([(int)$l[$x[0]]]);
                        if (!(int)$s->fetchColumn()) $errs[] = "Line $row: invalid ".str_replace('_id','',$x[0]).".";
                    }
                }
            }
        }
        return $errs;
    }

    // ---------------------------------------------------------
    // Create / update
    // ---------------------------------------------------------

    public static function createPR($db, array $header, array $lines, $userId)
    {
        $errs = array_merge(self::validateHeader($db,$header), self::validateLines($db,$lines));
        if (!empty($header['company_id']) && !CompanyService::canAccessCompany($db, $userId, (int)$header['company_id'])) {
            $errs[] = 'Access denied for the selected company.';
        }
        if ($errs) return ['success'=>false, 'errors'=>$errs];

        try {
            $db->beginTransaction();
            $settings = self::getSettings($db);
            $number = self::nextNumber($db, 'pr', $settings['pr_prefix']);

            $ins = $db->prepare("INSERT INTO purchase_requisitions
                (pr_number, company_id, department_id, project_id, cost_center_id, branch, requester_id,
                 request_date, required_date, priority, purpose, description, currency, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?)");
            $ins->execute([
                $number, (int)$header['company_id'], $header['department_id'] ?? null, $header['project_id'] ?? null,
                $header['cost_center_id'] ?? null, trim($header['branch'] ?? '') ?: null, (int)$header['requester_id'],
                $header['request_date'], $header['required_date'], $header['priority'] ?? 'normal',
                trim($header['purpose'] ?? '') ?: null, trim($header['description'] ?? '') ?: null,
                $header['currency'] ?? 'AED', $userId,
            ]);
            $prId = (int)$db->lastInsertId();
            self::saveLines($db, $prId, $lines);
            self::recalcHeader($db, $prId);

            self::log($db,'pr_created',$prId,"{$number} created",null,$userId);
            logAudit($userId,'Created purchase requisition '.$number,'procurement');
            $db->commit();
            return ['success'=>true,'id'=>$prId,'number'=>$number];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'errors'=>['Create failed: '.$e->getMessage()]];
        }
    }

    public static function updatePR($db, $prId, array $header, array $lines, $userId)
    {
        $pr = self::getPR($db, $prId);
        if (!$pr) return ['success'=>false,'errors'=>['Requisition not found.']];
        if (!CompanyService::canAccessCompany($db, $userId, (int)$pr['company_id'])) {
            return ['success'=>false,'errors'=>['Access denied for this requisition company.']];
        }
        if (!self::canEdit($pr)) return ['success'=>false,'errors'=>['Status "'.$pr['status'].'" cannot be edited (STEP 11).']];

        $errs = array_merge(self::validateHeader($db,$header), self::validateLines($db,$lines));
        if (!empty($header['company_id']) && !CompanyService::canAccessCompany($db, $userId, (int)$header['company_id'])) {
            $errs[] = 'Access denied for the selected company.';
        }
        if ($errs) return ['success'=>false,'errors'=>$errs];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_requisitions SET company_id=?, department_id=?, project_id=?, cost_center_id=?,
                          branch=?, requester_id=?, request_date=?, required_date=?, priority=?, purpose=?, description=?, currency=?
                          WHERE id=?")->execute([
                (int)$header['company_id'], $header['department_id'] ?: null, $header['project_id'] ?: null,
                $header['cost_center_id'] ?: null, trim($header['branch'] ?? '') ?: null, (int)$header['requester_id'],
                $header['request_date'], $header['required_date'], $header['priority'] ?? 'normal',
                trim($header['purpose'] ?? '') ?: null, trim($header['description'] ?? '') ?: null,
                $header['currency'] ?? 'AED', (int)$prId]);
            // replace lines wholesale while editable
            $db->prepare("DELETE FROM purchase_requisition_items WHERE pr_id=?")->execute([(int)$prId]);
            self::saveLines($db, $prId, $lines);
            self::recalcHeader($db, $prId);
            self::log($db,'pr_edited',$prId,null,null,$userId);
            $db->commit();
            return ['success'=>true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'errors'=>['Update failed: '.$e->getMessage()]];
        }
    }

    private static function saveLines($db, $prId, array $lines)
    {
        $ins = $db->prepare("INSERT INTO purchase_requisition_items
            (pr_id,item_type,item_name,description,quantity,unit,est_unit_price,discount_amount,tax_percent,
             line_subtotal,line_tax,line_total,required_date,preferred_vendor_id,department_id,project_id,cost_center_id,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($lines as $l) {
            list($sub,$tax,$disc,$total) = self::calcLine($l);
            $ins->execute([
                (int)$prId, in_array($l['item_type'],self::ITEM_TYPES,true)?$l['item_type']:'non_stock',
                trim($l['item_name']), trim($l['description'] ?? '') ?: null,
                round((float)$l['quantity'],3), trim($l['unit'] ?? 'pcs') ?: 'pcs',
                round((float)$l['est_unit_price'],2), $disc, round((float)($l['tax_percent'] ?? 0),3),
                $sub, $tax, $total,
                !empty($l['required_date']) ? $l['required_date'] : null,
                !empty($l['preferred_vendor_id']) ? (int)$l['preferred_vendor_id'] : null,
                !empty($l['department_id']) ? (int)$l['department_id'] : null,
                !empty($l['project_id']) ? (int)$l['project_id'] : null,
                !empty($l['cost_center_id']) ? (int)$l['cost_center_id'] : null,
                trim($l['notes'] ?? '') ?: null,
            ]);
        }
    }

    // ---------------------------------------------------------
    // Workflow (STEP 6/7/11/12)
    // ---------------------------------------------------------

    /** Defense-in-depth: verify a user holds module.name permission (page layer also checks). */
    public static function userHasPermission($db, $userId, $perm)
    {
        if (CompanyService::isSuperAdmin($db, $userId)) return true;
        list($module, $name) = explode('.', $perm, 2);
        $s = $db->prepare("SELECT COUNT(*) FROM permissions p
                           JOIN role_permissions rp ON rp.permission_id=p.id
                           JOIN user_roles ur ON ur.role_id=rp.role_id
                           WHERE ur.user_id=? AND p.module=? AND p.name=?");
        $s->execute([(int)$userId, $module, $name]);
        return (int)$s->fetchColumn() > 0;
    }

    public static function canEdit(array $pr): bool
    {
        return in_array($pr['status'], ['draft','rejected'], true); // STEP 11
    }

    /** STEP 8 — resolve required approver roles from configurable rules. */
    public static function approvalRoute($db, array $pr): array
    {
        $rules = $db->query("
            SELECT * FROM procurement_approval_rules
            WHERE is_active=1 ORDER BY rule_sequence, id
        ")->fetchAll();
        $roles = [];
        foreach ($rules as $r) {
            if ($r['company_id'] !== null && (int)$r['company_id'] !== (int)$pr['company_id']) continue;
            if ($r['department_id'] !== null && (int)$r['department_id'] !== (int)$pr['department_id']) continue;
            if ($r['priority'] !== null && $r['priority'] !== $pr['priority']) continue;
            if ($r['max_amount'] !== null && (float)$pr['grand_total'] > (float)$r['max_amount']) continue;
            $roles[(int)$r['approver_role_id']] = true; // dedupe across rules
        }
        if (!$roles) {
            // fallback route when no rules configured: any holder of procurement.pr.approve
            return [0]; // 0 = permission-based (any user with procurement.pr.approve)
        }
        return array_keys($roles);
    }

    public static function submit($db, $prId, $userId)
    {
        if (!self::userHasPermission($db,$userId,'procurement.pr.submit'))
            return ['success'=>false,'message'=>'Missing permission: procurement.pr.submit'];
        $pr = self::getPR($db,$prId);
        if (!$pr) return ['success'=>false,'message'=>'Not found.'];
        if (!in_array($pr['status'],['draft','rejected'],true))
            return ['success'=>false,'message'=>'Only Draft or Rejected requisitions can be submitted.'];
        $prev = $pr['status'];

        $errs = [];
        $lines = $db->query("SELECT * FROM purchase_requisition_items WHERE pr_id=".(int)$prId)->fetchAll();
        $hdr = ['requester_id'=>$pr['requester_id'],'company_id'=>$pr['company_id'],'department_id'=>$pr['department_id'],
                'required_date'=>$pr['required_date'],'request_date'=>$pr['request_date'],'priority'=>$pr['priority']];
        $errs = array_merge(self::validateHeader($db,$hdr), self::validateLines($db,$lines));
        if ($errs) return ['success'=>false,'message'=>'Cannot submit: '.implode(' ',$errs)];

        try {
            $db->beginTransaction();
            self::recalcHeader($db,$prId); // final authoritative numbers
            $newStatus = 'pending_approval';
            $db->prepare("UPDATE purchase_requisitions SET status=?, submitted_at=NOW(), rejection_reason=NULL WHERE id=?")
               ->execute([$newStatus,(int)$prId]);
            $db->prepare("INSERT INTO purchase_requisition_approvals (pr_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$prId,$userId,$prev==='rejected'?'resubmitted':'submitted',
                          $prev==='rejected'?'Resubmitted after revision':null,$prev,$newStatus]);
            self::log($db,$prev==='rejected'?'pr_resubmitted':'pr_submitted',$prId,null,null,$userId);
            logAudit($userId,($prev==='rejected'?'Resubmitted':'Submitted').' PR '.$pr['pr_number'],'procurement');
            $db->commit();
            return ['success'=>true,'route_roles'=>self::approvalRoute($db,self::getPR($db,$prId))];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function approve($db, $prId, $userId, $comments = null)
    {
        if (!self::userHasPermission($db,$userId,'procurement.pr.approve'))
            return ['success'=>false,'message'=>'Missing permission: procurement.pr.approve'];
        $pr = self::getPR($db,$prId);
        if (!$pr) return ['success'=>false,'message'=>'Not found.'];
        if (!in_array($pr['status'],['submitted','pending_approval'],true))
            return ['success'=>false,'message'=>'Only pending requisitions can be approved.'];
        if ((int)$pr['requester_id']===(int)$userId && !CompanyService::isSuperAdmin($db,$userId))
            return ['success'=>false,'message'=>'Separation of duties: the requester cannot approve their own requisition.'];

        $prev = $pr['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_requisitions SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
               ->execute([$userId,(int)$prId]);
            $db->prepare("INSERT INTO purchase_requisition_approvals (pr_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$prId,$userId,'approved',trim((string)$comments) ?: null,$prev,'approved']);
            self::log($db,'pr_approved',$prId,null,null,$userId);
            logAudit($userId,'Approved PR '.$pr['pr_number'],'procurement');
            $db->commit();
            return ['success'=>true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function reject($db, $prId, $userId, $reason)
    {
        if (!self::userHasPermission($db,$userId,'procurement.pr.reject'))
            return ['success'=>false,'message'=>'Missing permission: procurement.pr.reject'];
        $pr = self::getPR($db,$prId);
        if (!$pr) return ['success'=>false,'message'=>'Not found.'];
        if (!in_array($pr['status'],['submitted','pending_approval'],true))
            return ['success'=>false,'message'=>'Only pending requisitions can be rejected.'];
        if (trim((string)$reason)==='') return ['success'=>false,'message'=>'A rejection reason is REQUIRED.'];

        $prev = $pr['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_requisitions SET status='rejected', rejection_reason=? WHERE id=?")->execute([trim($reason),(int)$prId]);
            $db->prepare("INSERT INTO purchase_requisition_approvals (pr_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$prId,$userId,'rejected',trim($reason),$prev,'rejected']);
            self::log($db,'pr_rejected',$prId,null,$reason,$userId);
            logAudit($userId,'Rejected PR '.$pr['pr_number'],'procurement');
            $db->commit();
            return ['success'=>true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function cancel($db, $prId, $userId, $reason)
    {
        if (!self::userHasPermission($db,$userId,'procurement.pr.cancel'))
            return ['success'=>false,'message'=>'Missing permission: procurement.pr.cancel'];
        $pr = self::getPR($db,$prId);
        if (!$pr) return ['success'=>false,'message'=>'Not found.'];
        if (in_array($pr['status'], ['cancelled','closed'], true))
            return ['success'=>false,'message'=>'Already '.$pr['status'].'.'];
        if (trim((string)$reason)==='') return ['success'=>false,'message'=>'A cancellation reason is REQUIRED.'];

        $prev = $pr['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_requisitions SET status='cancelled', cancel_reason=?, cancelled_by=?, cancelled_at=NOW() WHERE id=?")
               ->execute([trim($reason),(int)$userId,(int)$prId]);
            $db->prepare("INSERT INTO purchase_requisition_approvals (pr_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$prId,$userId,'cancelled',trim($reason),$prev,'cancelled']);
            self::log($db,'pr_cancelled',$prId,null,$reason,$userId);
            logAudit($userId,'Cancelled PR '.$pr['pr_number'],'procurement');
            $db->commit();
            return ['success'=>true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    // ---------------------------------------------------------
    // Reads
    // ---------------------------------------------------------

    public static function getPR($db, $prId)
    {
        $s = $db->prepare("
            SELECT pr.*, u.name requester_name, d.department_name, c.company_name, cc.cost_center_name, p.project_name
            FROM purchase_requisitions pr
            LEFT JOIN users u ON u.id=pr.requester_id
            LEFT JOIN departments d ON d.id=pr.department_id
            LEFT JOIN companies c ON c.id=pr.company_id
            LEFT JOIN cost_centers cc ON cc.id=pr.cost_center_id
            LEFT JOIN projects p ON p.id=pr.project_id
            WHERE pr.id=?");
        $s->execute([(int)$prId]);
        return $s->fetch();
    }

    public static function getItems($db, $prId)
    {
        $s = $db->prepare("
            SELECT i.*, v.vendor_name FROM purchase_requisition_items i
            LEFT JOIN vendors v ON v.id=i.preferred_vendor_id
            WHERE i.pr_id=? ORDER BY i.id");
        $s->execute([(int)$prId]);
        return $s->fetchAll();
    }

    public static function getApprovals($db, $prId)
    {
        $s = $db->prepare("
            SELECT a.*, u.name approver_name FROM purchase_requisition_approvals a
            JOIN users u ON u.id=a.approver_id WHERE a.pr_id=? ORDER BY a.created_at, a.id");
        $s->execute([(int)$prId]);
        return $s->fetchAll();
    }

    public static function getAudit($db, $prId)
    {
        $s = $db->prepare("SELECT a.*, u.name user_name FROM procurement_audit a
                           LEFT JOIN users u ON u.id=a.user_id WHERE a.pr_id=? ORDER BY a.created_at, a.id");
        $s->execute([(int)$prId]);
        return $s->fetchAll();
    }

    /** STEP 20 — items exposed for the future RFQ phase without re-entry. */
    public static function getRfqReadyItems($db, $prId)
    {
        return $db->query("
            SELECT id, item_type, item_name, description, quantity, unit, est_unit_price, required_date, preferred_vendor_id
            FROM purchase_requisition_items WHERE pr_id=".(int)$prId." ORDER BY id")->fetchAll();
    }
}
