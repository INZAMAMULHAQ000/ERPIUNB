<?php
/**
 * P3 — Purchase Order Service
 *
 * Traceability: PR → RFQ → Quotation → Award → PO
 * GOLDEN RULES:
 *  - ALL monetary math computed SERVER-SIDE (never trust browser totals).
 *  - Status transitions guarded; edit rules enforced.
 *  - Over-ordering protection (awarded qty ≥ ordered qty unless override).
 *  - No GL entries — PO is informational until GRN/Bill phase.
 */

require_once __DIR__ . '/../finance/group/CompanyService.php';

class PoService
{
    const STATUSES = ['draft','pending_approval','approved','rejected','sent','acknowledged',
                      'partially_received','fully_received','partially_billed','fully_billed',
                      'closed','cancelled'];
    const EDITABLE_STATUSES = ['draft','rejected'];
    const ITEM_TYPES = ['stock','non_stock','service','asset','expense'];

    // ─── Audit ────────────────────────────────────────────────
    public static function log($db, $action, $poId = null, $details = null, $reason = null, $userId = null)
    {
        $db->prepare("INSERT INTO procurement_audit (action, pr_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$action, null,
                       $details !== null ? ('PO#' . $poId . ' ' . $details) : null,
                       $reason, $_SERVER['REMOTE_ADDR'] ?? null,
                       $userId ?? ($_SESSION['user_id'] ?? null)]);
    }

    // ─── Numbering (STEP 2) ──────────────────────────────────
    public static function nextNumber($db)
    {
        $year = (int)date('Y');
        $db->prepare("INSERT INTO procurement_sequences (seq_type,seq_year,last_number) VALUES ('po',?,0)
                      ON DUPLICATE KEY UPDATE last_number=last_number")->execute([$year]);
        $db->prepare("UPDATE procurement_sequences SET last_number=LAST_INSERT_ID(last_number+1)
                      WHERE seq_type='po' AND seq_year=?")->execute([$year]);
        return sprintf('PO-%d-%06d', $year, (int)$db->lastInsertId());
    }

    // ─── Server-side calculation (STEP 5) ────────────────────
    public static function calcLine(array $l): array
    {
        $qty    = max(0.0, (float)($l['quantity'] ?? 0));
        $price  = max(0.0, (float)($l['unit_price'] ?? 0));
        $disc   = min(max(0.0, (float)($l['discount_amount'] ?? 0)), $qty * $price);
        $taxPct = max(0.0, (float)($l['tax_percent'] ?? 0));
        $sub    = round($qty * $price, 2);
        $tax    = round(($sub - $disc) * $taxPct / 100, 2);
        $total  = round($sub - $disc + $tax, 2);
        return [$sub, $tax, $disc, $total];
    }

    public static function recalcHeader($db, $poId): array
    {
        $lines = $db->query("SELECT line_subtotal, line_tax, discount_amount FROM purchase_order_items WHERE po_id=" . (int)$poId)->fetchAll();
        $charges = $db->query("SELECT amount FROM purchase_order_charges WHERE po_id=" . (int)$poId)->fetchAll();
        $sub  = round(array_sum(array_column($lines, 'line_subtotal')), 2);
        $tx   = round(array_sum(array_column($lines, 'line_tax')), 2);
        $dc   = round(array_sum(array_column($lines, 'discount_amount')), 2);
        $chg  = round(array_sum(array_column($charges, 'amount')), 2);
        $gr   = round($sub - $dc + $tx + $chg, 2);
        $db->prepare("UPDATE purchase_orders SET subtotal=?, discount_total=?, tax_total=?, other_charges_total=?, grand_total=? WHERE id=?")
           ->execute([$sub, $tx, $dc, $chg, $gr, (int)$poId]);
        return ['subtotal' => $sub, 'tax_total' => $tx, 'discount_total' => $dc, 'other_charges' => $chg, 'grand_total' => $gr];
    }

    // ─── Reads ────────────────────────────────────────────────
    public static function getPO($db, $poId)
    {
        $s = $db->prepare("SELECT po.*, v.vendor_name, u.name requester_name, b.name buyer_name,
                           d.department_name, c.company_name, cc.cost_center_name, p.project_name,
                           rfq.rfq_number, q.quotation_number, pr.pr_number
                           FROM purchase_orders po
                           LEFT JOIN vendors v ON v.id=po.vendor_id
                           LEFT JOIN users u ON u.id=po.requester_id
                           LEFT JOIN users b ON b.id=po.buyer_id
                           LEFT JOIN departments d ON d.id=po.department_id
                           LEFT JOIN companies c ON c.id=po.company_id
                           LEFT JOIN cost_centers cc ON cc.id=po.cost_center_id
                           LEFT JOIN projects p ON p.id=po.project_id
                           LEFT JOIN request_for_quotations rfq ON rfq.id=po.rfq_id
                           LEFT JOIN vendor_quotations q ON q.id=po.quotation_id
                           LEFT JOIN purchase_requisitions pr ON pr.id=po.pr_id
                           WHERE po.id=?");
        $s->execute([(int)$poId]);
        return $s->fetch();
    }

    public static function getItems($db, $poId)
    {
        $s = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=? ORDER BY po_item_number, id");
        $s->execute([(int)$poId]);
        return $s->fetchAll();
    }

    public static function getCharges($db, $poId)
    {
        $s = $db->prepare("SELECT * FROM purchase_order_charges WHERE po_id=? ORDER BY id");
        $s->execute([(int)$poId]);
        return $s->fetchAll();
    }

    public static function getApprovals($db, $poId)
    {
        $s = $db->prepare("SELECT a.*, u.name approver_name FROM purchase_order_approvals a
                           JOIN users u ON u.id=a.approver_id WHERE a.po_id=? ORDER BY a.created_at, a.id");
        $s->execute([(int)$poId]);
        return $s->fetchAll();
    }

    public static function getRevisions($db, $poId)
    {
        $s = $db->prepare("SELECT r.*, u.name changed_by_name FROM purchase_order_revisions r
                           JOIN users u ON u.id=r.changed_by WHERE r.po_id=? ORDER BY r.revision_number");
        $s->execute([(int)$poId]);
        return $s->fetchAll();
    }

    public static function getCommunications($db, $poId)
    {
        $s = $db->prepare("SELECT cm.*, u.name sent_by_name FROM purchase_order_communications cm
                           JOIN users u ON u.id=cm.sent_by WHERE cm.po_id=? ORDER BY cm.sent_at DESC");
        $s->execute([(int)$poId]);
        return $s->fetchAll();
    }

    public static function canEdit(array $po): bool
    {
        return in_array($po['status'], self::EDITABLE_STATUSES, true);
    }

    // ─── Validation (STEP 25) ────────────────────────────────
    private static function validateHeader($db, array $h): array
    {
        $errs = [];
        if (empty($h['vendor_id']))     $errs[] = 'Vendor is required.';
        if (empty($h['company_id']))    $errs[] = 'Company is required.';
        if (empty($h['po_date']))       $errs[] = 'PO date is required.';
        if (!empty($h['vendor_id'])) {
            $v = $db->prepare("SELECT status FROM vendors WHERE id=?");
            $v->execute([(int)$h['vendor_id']]);
            $vs = $v->fetchColumn();
            if ($vs === false) $errs[] = 'Vendor does not exist.';
            elseif ($vs !== 'active') $errs[] = 'Vendor is inactive.';
        }
        if (!empty($h['company_id'])) {
            if (!CompanyService::canAccessCompany($db, $_SESSION['user_id'] ?? 0, (int)$h['company_id']))
                $errs[] = 'Access denied for the selected company.';
        }
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
            if ((float)($l['quantity'] ?? 0) <= 0)      $errs[] = "Line $row: quantity must be > 0.";
            if ((float)($l['unit_price'] ?? 0) < 0)     $errs[] = "Line $row: unit price cannot be negative.";
            if (!empty($l['item_type']) && !in_array($l['item_type'], self::ITEM_TYPES, true))
                $errs[] = "Line $row: invalid item type.";
        }
        return $errs;
    }

    // ─── Over-ordering protection (STEP 26) ──────────────────
    private static function checkOverOrdering(array $lines): array
    {
        $errs = [];
        foreach ($lines as $i => $l) {
            $awarded = (float)($l['awarded_quantity'] ?? 0);
            $ordered = (float)($l['quantity'] ?? 0);
            if ($awarded > 0 && $ordered > $awarded) {
                $errs[] = "Line " . ($i + 1) . ": ordered qty ($ordered) exceeds awarded qty ($awarded).";
            }
        }
        return $errs;
    }

    // ─── Create PO from Award (STEP 3) ──────────────────────
    public static function createFromAward($db, $awardId, array $overrides, $userId)
    {
        // Load the award (quotation_selection)
        $aw = $db->prepare("SELECT qs.*, q.rfq_id, q.vendor_id, q.quotation_number,
                            r.pr_id, r.company_id, r.department_id, r.project_id, r.cost_center_id,
                            r.requester_id, r.currency, r.payment_terms, r.delivery_terms,
                            v.vendor_name
                            FROM quotation_selection qs
                            JOIN vendor_quotations q ON q.id=qs.quotation_id
                            JOIN request_for_quotations r ON r.id=qs.rfq_id
                            JOIN vendors v ON v.id=q.vendor_id
                            WHERE qs.id=?");
        $aw->execute([(int)$awardId]);
        $award = $aw->fetch();
        if (!$award) return ['success' => false, 'message' => 'Award not found.'];

        // Load awarded items — get unique rfq_items from this vendor's quotation
        $items = $db->query("SELECT DISTINCT qi.*, ri.pr_item_id
                             FROM vendor_quotation_items qi
                             LEFT JOIN rfq_items ri ON ri.id=qi.rfq_item_id
                             WHERE qi.quotation_id=" . (int)$award['quotation_id'] . "
                             AND qi.is_alternative=0
                             ORDER BY qi.id")->fetchAll();

        if (!count($items)) return ['success' => false, 'message' => 'No items in the awarded quotation.'];

        try {
            $db->beginTransaction();
            $number = self::nextNumber($db);

            $ins = $db->prepare("INSERT INTO purchase_orders
                (po_number, award_id, quotation_id, rfq_id, pr_id, vendor_id, company_id,
                 department_id, project_id, cost_center_id, requester_id, buyer_id,
                 po_date, expected_delivery_date, currency, payment_terms, delivery_terms,
                 delivery_location, incoterms, warranty_terms, shipping_method,
                 special_instructions, vendor_notes, internal_notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $number, (int)$awardId, (int)$award['quotation_id'], (int)$award['rfq_id'],
                $award['pr_id'] ? (int)$award['pr_id'] : null, (int)$award['vendor_id'],
                (int)$award['company_id'],        $award['department_id'] ?? null,
                $award['project_id'] ?? null, $award['cost_center_id'] ?? null,
                (int)$award['requester_id'], $userId,
                $overrides['po_date'] ?? date('Y-m-d'),
                $overrides['expected_delivery_date'] ?? null,
                $overrides['currency'] ?? $award['currency'],
                $overrides['payment_terms'] ?? $award['payment_terms'],
                $overrides['delivery_terms'] ?? $award['delivery_terms'],
                $overrides['delivery_location'] ?? null,
                $overrides['incoterms'] ?? null,
                $overrides['warranty_terms'] ?? null,
                $overrides['shipping_method'] ?? null,
                trim($overrides['special_instructions'] ?? '') ?: null,
                trim($overrides['vendor_notes'] ?? '') ?: null,
                trim($overrides['internal_notes'] ?? '') ?: null,
                $userId,
            ]);
            $poId = (int)$db->lastInsertId();

            // Save items
            $li = $db->prepare("INSERT INTO purchase_order_items
                (po_id, po_item_number, award_item_id, rfq_item_id, quotation_item_id, pr_item_id,
                 item_type, item_name, description, quantity, unit, unit_price, discount_amount,
                 tax_percent, line_subtotal, line_tax, line_total, required_date, delivery_location,
                 project_id, cost_center_id, awarded_quantity, ordered_quantity, remaining_quantity, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $n = 0;
            foreach ($items as $it) {
                $n++;
                $qty = (float)$it['quantity'];
                $price = (float)$it['unit_price'];
                $disc = (float)($it['discount_amount'] ?? 0);
                $tax = (float)($it['tax_percent'] ?? 0);
                list($ls, $lt, $ld, $ltot) = self::calcLine([
                    'quantity' => $qty, 'unit_price' => $price,
                    'discount_amount' => $disc, 'tax_percent' => $tax,
                ]);
                $li->execute([
                    $poId, $n, null, $it['rfq_item_id'] ?? null, $it['id'] ?? null, $it['pr_item_id'] ?? null,
                    'non_stock', $it['item_name'], $it['description'] ?? null,
                    $qty, $it['unit'] ?? 'pcs', $price, $disc, $tax,
                    $ls, $lt, $ltot,
                    $it['required_date'] ?? null, $overrides['delivery_location'] ?? null,
                    $award['project_id'] ?: null, $award['cost_center_id'] ?: null,
                    $qty, $qty, 0, $it['notes'] ?? null,
                ]);
            }

            // Copy other charges from quotation
            $charges = $db->query("SELECT * FROM quotation_other_charges WHERE quotation_id=" . (int)$award['quotation_id'])->fetchAll();
            $chSt = $db->prepare("INSERT INTO purchase_order_charges (po_id, charge_name, amount) VALUES (?,?,?)");
            foreach ($charges as $c) {
                if (trim($c['charge_name']) !== '' && (float)$c['amount'] > 0)
                    $chSt->execute([$poId, $c['charge_name'], (float)$c['amount']]);
            }

            self::recalcHeader($db, $poId);
            self::log($db, 'po_created', $poId, "$number from award #$awardId", null, $userId);
            logAudit($userId, 'Created purchase order ' . $number, 'procurement');
            $db->commit();
            return ['success' => true, 'id' => $poId, 'number' => $number];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Create failed: ' . $e->getMessage()]];
        }
    }

    // ─── Update PO (STEP 10) ────────────────────────────────
    public static function updatePO($db, $poId, array $header, array $lines, array $charges, $userId)
    {
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'errors' => ['PO not found.']];
        if (!self::canEdit($po)) return ['success' => false, 'errors' => ['Status "' . $po['status'] . '" cannot be edited.']];

        $errs = array_merge(self::validateHeader($db, $header), self::validateLines($db, $lines));
        if ($errs) return ['success' => false, 'errors' => $errs];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET vendor_id=?, company_id=?, department_id=?, project_id=?,
                          cost_center_id=?, po_date=?, expected_delivery_date=?, currency=?, payment_terms=?,
                          delivery_terms=?, delivery_location=?, incoterms=?, warranty_terms=?, shipping_method=?,
                          special_instructions=?, vendor_notes=?, internal_notes=? WHERE id=?")->execute([
                (int)$header['vendor_id'], (int)$header['company_id'],
                $header['department_id'] ?? null, $header['project_id'] ?? null,
                $header['cost_center_id'] ?? null, $header['po_date'],
                $header['expected_delivery_date'] ?? null, $header['currency'] ?? 'AED',
                $header['payment_terms'] ?? null, $header['delivery_terms'] ?? null,
                $header['delivery_location'] ?? null, $header['incoterms'] ?? null,
                $header['warranty_terms'] ?? null, $header['shipping_method'] ?? null,
                trim($header['special_instructions'] ?? '') ?: null,
                trim($header['vendor_notes'] ?? '') ?: null,
                trim($header['internal_notes'] ?? '') ?: null,
                (int)$poId,
            ]);

            // Replace items
            $db->prepare("DELETE FROM purchase_order_items WHERE po_id=?")->execute([(int)$poId]);
            $li = $db->prepare("INSERT INTO purchase_order_items
                (po_id, po_item_number, rfq_item_id, pr_item_id, item_type, item_name, description,
                 quantity, unit, unit_price, discount_amount, tax_percent,
                 line_subtotal, line_tax, line_total, required_date, delivery_location,
                 project_id, cost_center_id, awarded_quantity, ordered_quantity, remaining_quantity, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $n = 0;
            foreach ($lines as $l) {
                if (trim((string)($l['item_name'] ?? '')) === '') continue;
                $n++;
                list($ls, $lt, $ld, $ltot) = self::calcLine($l);
                $awarded = (float)($l['awarded_quantity'] ?? 0);
                $ordered = (float)$l['quantity'];
                $li->execute([
                    (int)$poId, $n, $l['rfq_item_id'] ?? null, $l['pr_item_id'] ?? null,
                    in_array($l['item_type'] ?? '', self::ITEM_TYPES, true) ? $l['item_type'] : 'non_stock',
                    trim($l['item_name']), $l['description'] ?? null,
                    $ordered, $l['unit'] ?? 'pcs', (float)$l['unit_price'],
                    (float)($l['discount_amount'] ?? 0), (float)($l['tax_percent'] ?? 0),
                    $ls, $lt, $ltot,
                    $l['required_date'] ?? null, $l['delivery_location'] ?? null,
                    $l['project_id'] ?? null, $l['cost_center_id'] ?? null,
                    $awarded, $ordered, max(0, $awarded - $ordered),
                    $l['notes'] ?? null,
                ]);
            }

            // Replace charges
            $db->prepare("DELETE FROM purchase_order_charges WHERE po_id=?")->execute([(int)$poId]);
            $chSt = $db->prepare("INSERT INTO purchase_order_charges (po_id, charge_name, amount) VALUES (?,?,?)");
            foreach ($charges as $c) {
                if (trim((string)($c['charge_name'] ?? '')) !== '' && (float)($c['amount'] ?? 0) > 0)
                    $chSt->execute([(int)$poId, $c['charge_name'], round((float)$c['amount'], 2)]);
            }

            self::recalcHeader($db, $poId);
            self::log($db, 'po_edited', $poId, null, null, $userId);
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Update failed: ' . $e->getMessage()]];
        }
    }

    // ─── Submit (STEP 8) ─────────────────────────────────────
    public static function submit($db, $poId, $userId)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.po.submit'))
            return ['success' => false, 'message' => 'Missing permission: procurement.po.submit'];
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (!in_array($po['status'], ['draft', 'rejected'], true))
            return ['success' => false, 'message' => 'Only Draft or Rejected POs can be submitted.'];

        $prev = $po['status'];
        try {
            $db->beginTransaction();
            self::recalcHeader($db, $poId);
            $db->prepare("UPDATE purchase_orders SET status='pending_approval', submitted_at=NOW(), rejection_reason=NULL WHERE id=?")
               ->execute([(int)$poId]);
            $db->prepare("INSERT INTO purchase_order_approvals (po_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$poId, $userId, $prev === 'rejected' ? 'resubmitted' : 'submitted',
                          $prev === 'rejected' ? 'Resubmitted after revision' : null, $prev, 'pending_approval']);
            self::log($db, 'po_submitted', $poId, null, null, $userId);
            logAudit($userId, 'Submitted PO ' . $po['po_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Approve (STEP 8) ────────────────────────────────────
    public static function approve($db, $poId, $userId, $comments = null)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.po.approve'))
            return ['success' => false, 'message' => 'Missing permission: procurement.po.approve'];
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (!in_array($po['status'], ['pending_approval'], true))
            return ['success' => false, 'message' => 'Only pending POs can be approved.'];
        if ((int)$po['requester_id'] === (int)$userId && !CompanyService::isSuperAdmin($db, $userId))
            return ['success' => false, 'message' => 'Separation of duties: requester cannot approve their own PO.'];

        $prev = $po['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
               ->execute([$userId, (int)$poId]);
            $db->prepare("INSERT INTO purchase_order_approvals (po_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$poId, $userId, 'approved', trim((string)$comments) ?: null, $prev, 'approved']);
            self::log($db, 'po_approved', $poId, null, null, $userId);
            logAudit($userId, 'Approved PO ' . $po['po_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Reject (STEP 8) ─────────────────────────────────────
    public static function reject($db, $poId, $userId, $reason)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.po.reject'))
            return ['success' => false, 'message' => 'Missing permission: procurement.po.reject'];
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (!in_array($po['status'], ['pending_approval'], true))
            return ['success' => false, 'message' => 'Only pending POs can be rejected.'];
        if (trim((string)$reason) === '') return ['success' => false, 'message' => 'Rejection reason is REQUIRED.'];

        $prev = $po['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET status='rejected', rejection_reason=? WHERE id=?")
               ->execute([trim($reason), (int)$poId]);
            $db->prepare("INSERT INTO purchase_order_approvals (po_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$poId, $userId, 'rejected', trim($reason), $prev, 'rejected']);
            self::log($db, 'po_rejected', $poId, null, $reason, $userId);
            logAudit($userId, 'Rejected PO ' . $po['po_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Send to Vendor (STEP 14) ────────────────────────────
    public static function send($db, $poId, $userId, $method = 'email', $recipient = null)
    {
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (!in_array($po['status'], ['approved'], true))
            return ['success' => false, 'message' => 'Only approved POs can be sent.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET status='sent', sent_at=NOW(), sent_by=? WHERE id=?")
               ->execute([$userId, (int)$poId]);
            $db->prepare("INSERT INTO purchase_order_communications (po_id,method,recipient,status,sent_by)
                          VALUES (?,?,?,'sent',?)")
               ->execute([(int)$poId, $method, $recipient ?: $po['vendor_name'], $userId]);
            self::log($db, 'po_sent', $poId, "via $method", null, $userId);
            logAudit($userId, 'Sent PO ' . $po['po_number'] . ' to vendor', 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Vendor Acknowledgement (STEP 15) ────────────────────
    public static function acknowledge($db, $poId, $userId, $vendorRef = null, $expectedDate = null, $comments = null)
    {
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (!in_array($po['status'], ['sent'], true))
            return ['success' => false, 'message' => 'Only sent POs can be acknowledged.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET status='acknowledged', acknowledged_at=NOW(), acknowledged_by=?,
                          vendor_reference=?, expected_delivery_date_ack=?, vendor_comments=? WHERE id=?")
               ->execute([$userId, $vendorRef, $expectedDate, $comments, (int)$poId]);
            $db->prepare("INSERT INTO purchase_order_approvals (po_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,'acknowledged',?,?,?)")
               ->execute([(int)$poId, $userId, $comments, 'sent', 'acknowledged']);
            self::log($db, 'po_acknowledged', $poId, "vendor ref: $vendorRef", null, $userId);
            logAudit($userId, 'PO ' . $po['po_number'] . ' acknowledged by vendor', 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Cancel (STEP 16) ────────────────────────────────────
    public static function cancel($db, $poId, $userId, $reason)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.po.cancel'))
            return ['success' => false, 'message' => 'Missing permission: procurement.po.cancel'];
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (in_array($po['status'], ['cancelled', 'closed'])) return ['success' => false, 'message' => 'Already ' . $po['status'] . '.'];
        if (trim((string)$reason) === '') return ['success' => false, 'message' => 'Cancellation reason is REQUIRED.'];

        $prev = $po['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET status='cancelled', cancel_reason=?, cancelled_by=?, cancelled_at=NOW() WHERE id=?")
               ->execute([trim($reason), (int)$userId, (int)$poId]);
            $db->prepare("INSERT INTO purchase_order_approvals (po_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$poId, $userId, 'cancelled', trim($reason), $prev, 'cancelled']);
            self::log($db, 'po_cancelled', $poId, null, $reason, $userId);
            logAudit($userId, 'Cancelled PO ' . $po['po_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Close (STEP 17) ─────────────────────────────────────
    public static function close($db, $poId, $userId, $reason = null)
    {
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (in_array($po['status'], ['cancelled', 'closed']))
            return ['success' => false, 'message' => 'Already ' . $po['status'] . '.'];
        if (!in_array($po['status'], ['approved', 'sent', 'acknowledged']))
            return ['success' => false, 'message' => 'PO must be approved/sent/acknowledged to close.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE purchase_orders SET status='closed', closed_by=?, closed_at=NOW(), close_reason=? WHERE id=?")
               ->execute([$userId, trim((string)$reason) ?: null, (int)$poId]);
            $db->prepare("INSERT INTO purchase_order_approvals (po_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$poId, $userId, 'closed', $reason, $po['status'], 'closed']);
            self::log($db, 'po_closed', $poId, null, $reason, $userId);
            logAudit($userId, 'Closed PO ' . $po['po_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Revision (STEP 11-12) ──────────────────────────────
    public static function createRevision($db, $poId, array $newLines, array $newCharges, $reason, $userId)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.po.revise'))
            return ['success' => false, 'message' => 'Missing permission: procurement.po.revise'];
        $po = self::getPO($db, $poId);
        if (!$po) return ['success' => false, 'message' => 'Not found.'];
        if (!in_array($po['status'], ['approved', 'rejected']))
            return ['success' => false, 'message' => 'PO must be approved or rejected to revise.'];
        if (trim((string)$reason) === '') return ['success' => false, 'message' => 'Revision reason is REQUIRED.'];

        $oldItems = self::getItems($db, $poId);
        $oldCharges = self::getCharges($db, $poId);
        $newRev = ($po['revision_number'] ?? 0) + 1;

        // Build diff
        $changes = [];
        foreach ($newLines as $i => $nl) {
            $old = $oldItems[$i] ?? null;
            if (!$old) { $changes["line_" . ($i + 1)] = "new line added"; continue; }
            if (abs((float)$nl['quantity'] - (float)$old['quantity']) > 0.001)
                $changes[$old['item_name'] . ' qty'] = $old['quantity'] . ' → ' . $nl['quantity'];
            if (abs((float)$nl['unit_price'] - (float)$old['unit_price']) > 0.001)
                $changes[$old['item_name'] . ' price'] = $old['unit_price'] . ' → ' . $nl['unit_price'];
        }

        try {
            $db->beginTransaction();

            // Save revision snapshot
            $revIns = $db->prepare("INSERT INTO purchase_order_revisions (po_id, revision_number, changed_fields, reason, changed_by)
                                    VALUES (?,?,?,?,?)");
            $revIns->execute([(int)$poId, $newRev, json_encode($changes), trim($reason), $userId]);
            $revId = (int)$db->lastInsertId();

            // Snapshot old items
            $snapIns = $db->prepare("INSERT INTO purchase_order_revision_items
                (revision_id, po_item_id, item_name, quantity, unit_price, discount_amount, tax_percent, line_total)
                VALUES (?,?,?,?,?,?,?,?)");
            foreach ($oldItems as $oi) {
                $snapIns->execute([$revId, (int)$oi['id'], $oi['item_name'], $oi['quantity'],
                                   $oi['unit_price'], $oi['discount_amount'], $oi['tax_percent'], $oi['line_total']]);
            }

            // Update PO with new data
            $db->prepare("UPDATE purchase_orders SET revision_number=?, status='draft',
                          rejection_reason=NULL WHERE id=?")->execute([$newRev, (int)$poId]);

            // Replace items
            $db->prepare("DELETE FROM purchase_order_items WHERE po_id=?")->execute([(int)$poId]);
            $li = $db->prepare("INSERT INTO purchase_order_items
                (po_id, po_item_number, rfq_item_id, pr_item_id, item_type, item_name, description,
                 quantity, unit, unit_price, discount_amount, tax_percent,
                 line_subtotal, line_tax, line_total, required_date, delivery_location,
                 project_id, cost_center_id, awarded_quantity, ordered_quantity, remaining_quantity, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $n = 0;
            foreach ($newLines as $l) {
                if (trim((string)($l['item_name'] ?? '')) === '') continue;
                $n++;
                list($ls, $lt, $ld, $ltot) = self::calcLine($l);
                $awarded = (float)($l['awarded_quantity'] ?? 0);
                $ordered = (float)$l['quantity'];
                $li->execute([
                    (int)$poId, $n, $l['rfq_item_id'] ?? null, $l['pr_item_id'] ?? null,
                    in_array($l['item_type'] ?? '', self::ITEM_TYPES, true) ? $l['item_type'] : 'non_stock',
                    trim($l['item_name']), $l['description'] ?? null,
                    $ordered, $l['unit'] ?? 'pcs', (float)$l['unit_price'],
                    (float)($l['discount_amount'] ?? 0), (float)($l['tax_percent'] ?? 0),
                    $ls, $lt, $ltot,
                    $l['required_date'] ?? null, $l['delivery_location'] ?? null,
                    $l['project_id'] ?? null, $l['cost_center_id'] ?? null,
                    $awarded, $ordered, max(0, $awarded - $ordered),
                    $l['notes'] ?? null,
                ]);
            }

            // Replace charges
            $db->prepare("DELETE FROM purchase_order_charges WHERE po_id=?")->execute([(int)$poId]);
            $chSt = $db->prepare("INSERT INTO purchase_order_charges (po_id, charge_name, amount) VALUES (?,?,?)");
            foreach ($newCharges as $c) {
                if (trim((string)($c['charge_name'] ?? '')) !== '' && (float)($c['amount'] ?? 0) > 0)
                    $chSt->execute([(int)$poId, $c['charge_name'], round((float)$c['amount'], 2)]);
            }

            self::recalcHeader($db, $poId);
            self::log($db, 'po_revised', $poId, "revision #$newRev", $reason, $userId);
            logAudit($userId, 'Revised PO ' . $po['po_number'] . " to revision #$newRev", 'procurement');
            $db->commit();
            return ['success' => true, 'revision' => $newRev, 'changes' => $changes];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Revision failed: ' . $e->getMessage()]];
        }
    }

    // ─── Commitment Tracking (STEP 18) ──────────────────────
    public static function commitmentSummary($db, $poId): array
    {
        $po = self::getPO($db, $poId);
        if (!$po) return [];
        $items = self::getItems($db, $poId);
        $totalAwarded = 0;
        $totalOrdered = 0;
        foreach ($items as $it) {
            $totalAwarded += (float)$it['awarded_quantity'] * (float)$it['unit_price'];
            $totalOrdered += (float)$it['ordered_quantity'] * (float)$it['unit_price'];
        }
        $cancelled = in_array($po['status'], ['cancelled']) ? (float)$po['grand_total'] : 0;
        return [
            'po_total' => (float)$po['grand_total'],
            'committed_amount' => round($totalOrdered, 2),
            'cancelled_amount' => round($cancelled, 2),
            'remaining_commitment' => round(max(0, (float)$po['grand_total'] - $cancelled), 2),
        ];
    }

    // ─── Dashboard stats (STEP 19) ──────────────────────────
    public static function dashboardStats($db): array
    {
        $s = $db->query("SELECT
            SUM(status='draft') draft,
            SUM(status='pending_approval') pending,
            SUM(status='approved') approved,
            SUM(status='sent') sent,
            SUM(status='acknowledged') ack,
            SUM(status='cancelled') cancelled,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','closed') THEN grand_total END),0) open_value,
            COALESCE(SUM(CASE WHEN po_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND status NOT IN ('cancelled') THEN grand_total END),0) month_value
        FROM purchase_orders")->fetch();
        return $s ?: [];
    }

    // ─── List with filters (STEP 20) ────────────────────────
    public static function listPOs($db, array $filters = [], $limit = 50, $offset = 0): array
    {
        $where = ["1=1"];
        $params = [];
        if (!empty($filters['status']))    { $where[] = "po.status=?"; $params[] = $filters['status']; }
        if (!empty($filters['vendor_id'])) { $where[] = "po.vendor_id=?"; $params[] = (int)$filters['vendor_id']; }
        if (!empty($filters['company_id'])){ $where[] = "po.company_id=?"; $params[] = (int)$filters['company_id']; }
        if (!empty($filters['search']))    { $where[] = "po.po_number LIKE ?"; $params[] = '%' . $filters['search'] . '%'; }

        $sql = "SELECT po.*, v.vendor_name, c.company_name, d.department_name
                FROM purchase_orders po
                LEFT JOIN vendors v ON v.id=po.vendor_id
                LEFT JOIN companies c ON c.id=po.company_id
                LEFT JOIN departments d ON d.id=po.department_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY po.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $s = $db->prepare($sql);
        $s->execute($params);
        $rows = $s->fetchAll();

        $cnt = $db->prepare("SELECT COUNT(*) FROM purchase_orders po WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }
}
