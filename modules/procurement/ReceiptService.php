<?php
/**
 * P4 — Receipt Service
 * Goods Receipt + Service Receipt lifecycle, quantity validation,
 * over-receipt control, inspection, rejection, damage, batch/serial,
 * PO receiving status updates, server-side calculations.
 *
 * GOLDEN RULES:
 *  - ALL quantity math computed SERVER-SIDE from stored line inputs.
 *  - Finalization uses DB transactions; any failure ROLLS BACK.
 *  - Never directly UPDATE inventory stock from here.
 *  - A Goods Receipt is NOT a Vendor Bill — no AP liability created.
 */

require_once __DIR__ . '/../finance/group/CompanyService.php';

class ReceiptService
{
    const GR_STATUSES = ['draft','pending_inspection','partially_accepted','accepted','rejected','cancelled'];
    const SR_STATUSES = ['draft','pending_acceptance','partially_accepted','accepted','rejected','cancelled'];
    const ITEM_TYPES = ['stock','non_stock','service','asset','expense'];
    const INSPECTION_STATUSES = ['pending','passed','partially_passed','failed','not_required'];

    public static function log($db, $action, $grId = null, $details = null, $reason = null, $userId = null)
    {
        $db->prepare("INSERT INTO procurement_audit (action, pr_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$action, $grId, $details, $reason, $_SERVER['REMOTE_ADDR'] ?? null,
                       $userId ?? ($_SESSION['user_id'] ?? null)]);
    }

    public static function getSettings($db): array
    {
        $rows = $db->query("SELECT setting_key, setting_value FROM procurement_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge([
            'pr_prefix'=>'PR','rfq_prefix'=>'RFQ','quote_prefix'=>'QUO','po_prefix'=>'PO','grn_prefix'=>'GR',
            'default_currency'=>'AED','over_receipt_tolerance'=>'0',
        ], $rows ?: []);
    }

    public static function nextGRNumber($db): string
    {
        $settings = self::getSettings($db);
        $prefix = $settings['grn_prefix'] ?? 'GR';
        $year = (int)date('Y');
        $db->prepare("INSERT INTO procurement_sequences (seq_type, seq_year, last_number) VALUES ('gr', ?, 0)
                      ON DUPLICATE KEY UPDATE last_number = last_number")
           ->execute([$year]);
        $db->prepare("UPDATE procurement_sequences SET last_number = LAST_INSERT_ID(last_number + 1)
                      WHERE seq_type = 'gr' AND seq_year = ?")->execute([$year]);
        $n = (int)$db->lastInsertId();
        return sprintf('%s-%d-%06d', $prefix, $year, $n);
    }

    public static function nextSRNumber($db): string
    {
        $settings = self::getSettings($db);
        $prefix = $settings['grn_prefix'] ?? 'GR';
        $year = (int)date('Y');
        $db->prepare("INSERT INTO procurement_sequences (seq_type, seq_year, last_number) VALUES ('sr', ?, 0)
                      ON DUPLICATE KEY UPDATE last_number = last_number")
           ->execute([$year]);
        $db->prepare("UPDATE procurement_sequences SET last_number = LAST_INSERT_ID(last_number + 1)
                      WHERE seq_type = 'sr' AND seq_year = ?")->execute([$year]);
        $n = (int)$db->lastInsertId();
        return sprintf('%s-%d-%06d', $prefix, $year, $n);
    }

    private static function validateGRHeader($db, array $h): array
    {
        $errs = [];
        if (empty($h['po_id']))          $errs[] = 'Purchase Order is required.';
        if (empty($h['vendor_id']))      $errs[] = 'Vendor is required.';
        if (empty($h['company_id']))     $errs[] = 'Company is required.';
        if (empty($h['receipt_date']))   $errs[] = 'Receipt date is required.';
        if (empty($h['received_by']))    $errs[] = 'Received By is required.';
        if (!empty($h['po_id'])) {
            $s = $db->prepare("SELECT status FROM purchase_orders WHERE id=?");
            $s->execute([(int)$h['po_id']]);
            $po = $s->fetch();
            if (!$po) $errs[] = 'Purchase Order not found.';
            elseif (!in_array($po['status'], ['approved','sent','acknowledged','partially_received'], true))
                $errs[] = 'PO is not eligible for receiving (status: ' . $po['status'] . ').';
        }
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

    private static function validateGRLines($db, array $lines, int $poId): array
    {
        $errs = [];
        if (!count($lines)) $errs[] = 'At least one item with received quantity is required.';

        $poItems = [];
        if ($poId) {
            $stmt = $db->prepare("SELECT id, quantity, received_quantity FROM purchase_order_items WHERE po_id=?");
            $stmt->execute([$poId]);
            $poItems = $stmt->fetchAll(PDO::FETCH_UNIQUE);
        }

        $settings = self::getSettings($db);
        $tolerance = (float)($settings['over_receipt_tolerance'] ?? 0);

        $row = 0;
        foreach ($lines as $l) {
            $row++;
            $received = max(0.0, (float)($l['received_quantity'] ?? 0));
            $accepted = max(0.0, (float)($l['accepted_quantity'] ?? 0));
            $rejected = max(0.0, (float)($l['rejected_quantity'] ?? 0));
            $damaged  = max(0.0, (float)($l['damaged_quantity'] ?? 0));
            $short    = max(0.0, (float)($l['short_quantity'] ?? 0));
            $backorder = max(0.0, (float)($l['backorder_quantity'] ?? 0));

            if ($received <= 0 && $accepted <= 0 && $rejected <= 0 && $damaged <= 0)
                continue;

            if ($accepted + $rejected + $damaged > $received + 0.0001)
                $errs[] = "Line $row: accepted + rejected + damaged ($accepted + $rejected + $damaged) exceeds received ($received).";

            if (!empty($poItems[$l['po_item_id']] ?? null)) {
                $poItem = $poItems[$l['po_item_id']];
                $ordered = (float)$poItem['quantity'];
                $prevReceived = (float)$poItem['received_quantity'];
                $remaining = max(0, $ordered - $prevReceived);
                $maxAllowed = $remaining + ($remaining * $tolerance / 100);
                if ($received > $maxAllowed + 0.0001)
                    $errs[] = "Line $row: received qty ($received) exceeds remaining PO qty ($remaining) + tolerance ($tolerance%). Max allowed: " . round($maxAllowed, 3);
            }
        }
        return $errs;
    }

    public static function updatePOReceivingStatus($db, int $poId): void
    {
        $agg = $db->prepare("
            SELECT
                SUM(received_quantity) as total_received,
                SUM(accepted_quantity) as total_accepted,
                SUM(rejected_quantity) as total_rejected,
                SUM(damaged_quantity) as total_damaged
            FROM goods_receipt_items
            WHERE po_id = ?
        ");
        $agg->execute([$poId]);
        $sum = $agg->fetch();

        $totalReceived = (float)($sum['total_received'] ?? 0);
        $totalAccepted = (float)($sum['total_accepted'] ?? 0);

        $items = $db->prepare("SELECT SUM(quantity) as total_ordered FROM purchase_order_items WHERE po_id=?");
        $items->execute([$poId]);
        $ordered = (float)$items->fetchColumn();

        $newStatus = 'sent';
        if ($ordered > 0 && $totalReceived >= $ordered - 0.0001) {
            $newStatus = 'fully_received';
        } elseif ($totalReceived > 0) {
            $newStatus = 'partially_received';
        }

        $db->prepare("UPDATE purchase_orders SET status=? WHERE id=?")->execute([$newStatus, $poId]);

        $summaryStmt = $db->prepare("
            INSERT INTO po_receiving_summary
                (po_id, po_item_id, total_received, total_accepted, total_rejected, total_damaged, remaining_quantity, outstanding_quantity, last_receipt_date)
            SELECT ?, po_item_id, SUM(received_quantity), SUM(accepted_quantity), SUM(rejected_quantity), SUM(damaged_quantity),
                   GREATEST(0, SUM(ordered_quantity) - SUM(received_quantity)),
                   GREATEST(0, SUM(ordered_quantity) - SUM(accepted_quantity)),
                   MAX(created_at)
            FROM goods_receipt_items
            WHERE po_id = ?
            GROUP BY po_item_id
            ON DUPLICATE KEY UPDATE
                total_received = VALUES(total_received),
                total_accepted = VALUES(total_accepted),
                total_rejected = VALUES(total_rejected),
                total_damaged = VALUES(total_damaged),
                remaining_quantity = VALUES(remaining_quantity),
                outstanding_quantity = VALUES(outstanding_quantity),
                last_receipt_date = VALUES(last_receipt_date)
        ");
        $summaryStmt->execute([$poId, $poId]);
    }

    public static function createGR($db, array $header, array $lines, $userId)
    {
        $errs = array_merge(self::validateGRHeader($db, $header), self::validateGRLines($db, $lines, (int)($header['po_id'] ?? 0)));
        if (!empty($header['company_id']) && !CompanyService::canAccessCompany($db, $userId, (int)$header['company_id'])) {
            $errs[] = 'Access denied for the selected company.';
        }
        if ($errs) return ['success' => false, 'errors' => $errs];

        try {
            $db->beginTransaction();
            $number = self::nextGRNumber($db);

            $ins = $db->prepare("INSERT INTO goods_receipts
                (gr_number, po_id, vendor_id, company_id, warehouse_id, location_id,
                 receipt_date, delivery_note_number, vendor_delivery_date, received_by,
                 inspection_required, notes, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?)");
            $ins->execute([
                $number, (int)$header['po_id'], (int)$header['vendor_id'], (int)$header['company_id'],
                $header['warehouse_id'] ?? null, $header['location_id'] ?? null,
                $header['receipt_date'], $header['delivery_note_number'] ?: null,
                $header['vendor_delivery_date'] ?: null, (int)$header['received_by'],
                !empty($header['inspection_required']) ? 1 : 0,
                trim($header['notes'] ?? '') ?: null, $userId,
            ]);
            $grId = (int)$db->lastInsertId();

            self::saveGRLines($db, $grId, (int)$header['po_id'], $lines);

            self::log($db, 'receipt_created', $grId, $number, null, $userId);
            logAudit($userId, 'Created goods receipt ' . $number, 'procurement');
            $db->commit();
            return ['success' => true, 'id' => $grId, 'number' => $number];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Create failed: ' . $e->getMessage()]];
        }
    }

    private static function saveGRLines($db, int $grId, int $poId, array $lines): void
    {
        $li = $db->prepare("INSERT INTO goods_receipt_items
            (gr_id, po_id, po_item_id, item_id, item_name, description, item_type,
             ordered_quantity, previously_received_quantity, remaining_quantity,
             received_quantity, accepted_quantity, rejected_quantity, damaged_quantity,
             short_quantity, backorder_quantity, unit, unit_price,
             warehouse_id, location_id, batch_number, serial_number, expiry_date, manufacturing_date,
             rejection_reason, damage_reason, damage_type, notes, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $n = 0;
        foreach ($lines as $l) {
            $n++;
            $poItemId = (int)($l['po_item_id'] ?? 0);
            if (!$poItemId) continue;

            $poItem = $db->prepare("SELECT * FROM purchase_order_items WHERE id=?");
            $poItem->execute([$poItemId]);
            $poItemData = $poItem->fetch();

            $ordered = $poItemData ? (float)$poItemData['quantity'] : 0;
            $prevReceived = $poItemData ? (float)($poItemData['received_quantity'] ?? 0) : 0;
            $remaining = max(0, $ordered - $prevReceived);

            $received = max(0.0, (float)($l['received_quantity'] ?? 0));
            $accepted = max(0.0, (float)($l['accepted_quantity'] ?? 0));
            $rejected = max(0.0, (float)($l['rejected_quantity'] ?? 0));
            $damaged  = max(0.0, (float)($l['damaged_quantity'] ?? 0));
            $short    = max(0.0, (float)($l['short_quantity'] ?? 0));
            $backorder = max(0.0, (float)($l['backorder_quantity'] ?? 0));

            $li->execute([
                $grId, $poId, $poItemId,
                $l['item_id'] ?? null,
                trim($l['item_name'] ?? $poItemData['item_name'] ?? ''),
                trim($l['description'] ?? '') ?: null,
                in_array($l['item_type'] ?? '', self::ITEM_TYPES, true) ? $l['item_type'] : ($poItemData['item_type'] ?? 'non_stock'),
                $ordered, $prevReceived, $remaining,
                $received, $accepted, $rejected, $damaged, $short, $backorder,
                trim($l['unit'] ?? $poItemData['unit'] ?? 'pcs') ?: 'pcs',
                $poItemData ? (float)$poItemData['unit_price'] : 0,
                $l['warehouse_id'] ?? null, $l['location_id'] ?? null,
                trim($l['batch_number'] ?? '') ?: null,
                trim($l['serial_number'] ?? '') ?: null,
                $l['expiry_date'] ?? null, $l['manufacturing_date'] ?? null,
                trim($l['rejection_reason'] ?? '') ?: null,
                trim($l['damage_reason'] ?? '') ?: null,
                trim($l['damage_type'] ?? '') ?: null,
                trim($l['notes'] ?? '') ?: null,
                $n,
            ]);

            if (!empty($l['batches']) && is_array($l['batches'])) {
                $bi = $db->prepare("INSERT INTO goods_receipt_batches
                    (gr_id, gr_item_id, batch_number, quantity, expiry_date, manufacturing_date, notes)
                    VALUES (?,?,?,?,?,?,?)");
                foreach ($l['batches'] as $b) {
                    if (trim((string)($b['batch_number'] ?? '')) === '') continue;
                    $bi->execute([
                        $grId, (int)$db->lastInsertId(),
                        trim($b['batch_number']), max(0, (float)($b['quantity'] ?? 0)),
                        $b['expiry_date'] ?? null, $b['manufacturing_date'] ?? null,
                        trim($b['notes'] ?? '') ?: null,
                    ]);
                }
            }

            if (!empty($l['serials']) && is_array($l['serials'])) {
                $si = $db->prepare("INSERT INTO goods_receipt_serials
                    (gr_id, gr_item_id, serial_number, batch_number, expiry_date, status, notes)
                    VALUES (?,?,?,?,?,?,?)");
                foreach ($l['serials'] as $s) {
                    if (trim((string)($s['serial_number'] ?? '')) === '') continue;
                    $si->execute([
                        $grId, (int)$db->lastInsertId(),
                        trim($s['serial_number']), trim($s['batch_number'] ?? '') ?: null,
                        $s['expiry_date'] ?? null, 'received',
                        trim($s['notes'] ?? '') ?: null,
                    ]);
                }
            }
        }

        $agg = $db->prepare("
            SELECT SUM(received_quantity), SUM(accepted_quantity), SUM(rejected_quantity),
                   SUM(damaged_quantity), SUM(short_quantity), SUM(backorder_quantity)
            FROM goods_receipt_items WHERE gr_id=?
        ");
        $agg->execute([$grId]);
        $a = $agg->fetch();
        $db->prepare("UPDATE goods_receipts SET
            received_quantity_total=?, accepted_quantity_total=?, rejected_quantity_total=?,
            damaged_quantity_total=?, short_quantity_total=?, backorder_quantity_total=?
            WHERE id=?")
           ->execute([
               $a[0] ?? 0, $a[1] ?? 0, $a[2] ?? 0, $a[3] ?? 0, $a[4] ?? 0, $a[5] ?? 0, $grId
           ]);
    }

    public static function finalizeGR($db, int $grId, $userId): array
    {
        $gr = self::getGR($db, $grId);
        if (!$gr) return ['success' => false, 'message' => 'Receipt not found.'];

        if (!in_array($gr['status'], ['draft', 'pending_inspection'], true))
            return ['success' => false, 'message' => 'Only draft or pending inspection receipts can be finalized.'];

        $items = self::getGRItems($db, $grId);
        $totalAccepted = 0;
        $totalReceived = 0;
        foreach ($items as $it) {
            $totalAccepted += (float)$it['accepted_quantity'];
            $totalReceived += (float)$it['received_quantity'];
        }

        if ($totalAccepted <= 0 && $totalReceived > 0) {
            $finalStatus = 'rejected';
        } elseif ($totalAccepted >= $totalReceived - 0.0001) {
            $finalStatus = 'accepted';
        } else {
            $finalStatus = 'partially_accepted';
        }

        try {
            $db->beginTransaction();

            $now = date('Y-m-d H:i:s');
            $db->prepare("UPDATE goods_receipts SET status=?, submitted_at=?, submitted_by=? WHERE id=?")
               ->execute([$finalStatus, $now, $userId, $grId]);

            foreach ($items as $it) {
                $poItemId = (int)$it['po_item_id'];
                $received = (float)$it['received_quantity'];
                $accepted = (float)$it['accepted_quantity'];
                $db->prepare("UPDATE purchase_order_items
                              SET received_quantity = received_quantity + ?
                              WHERE id=?")
                   ->execute([$received, $poItemId]);

                if (in_array($it['item_type'] ?? 'non_stock', ['stock'], true) && $accepted > 0) {
                    $db->prepare("UPDATE goods_receipts SET inventory_movement_id = 0 WHERE id=?")
                       ->execute([$grId]);
                }
            }

            // ===== Inventory Core I1 integration =====
            // Post accepted stock lines into the central inventory ledger.
            // Idempotent: InventoryEngine checks for existing movements for this GR.
            if (class_exists('InventoryEngine')) {
                try {
                    $invResult = InventoryEngine::postFromGoodsReceipt($db, $grId, $userId);
                    if ($invResult['success'] && !empty($invResult['movements'])) {
                        $firstMovementId = (int)$invResult['movements'][0]['movement_id'];
                        $db->prepare("UPDATE goods_receipts SET inventory_movement_id = ? WHERE id=?")
                           ->execute([$firstMovementId, $grId]);
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    return ['success' => false, 'message' => 'Inventory posting failed: ' . $e->getMessage()];
                }
            }
            // ===== End I1 integration =====

            self::updatePOReceivingStatus($db, (int)$gr['po_id']);

            self::log($db, 'receipt_submitted', $grId, "Finalized as $finalStatus", null, $userId);
            logAudit($userId, 'Finalized goods receipt ' . $gr['gr_number'] . ' as ' . $finalStatus, 'procurement');

            $db->commit();
            return ['success' => true, 'status' => $finalStatus];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => 'Finalization failed: ' . $e->getMessage()];
        }
    }

    public static function cancelGR($db, int $grId, $userId, string $reason): array
    {
        $gr = self::getGR($db, $grId);
        if (!$gr) return ['success' => false, 'message' => 'Receipt not found.'];
        if (in_array($gr['status'], ['cancelled'], true))
            return ['success' => false, 'message' => 'Already cancelled.'];
        if (trim($reason) === '') return ['success' => false, 'message' => 'Cancellation reason is REQUIRED.'];

        if (!self::userHasPermission($db, $userId, 'procurement.receipt.cancel'))
            return ['success' => false, 'message' => 'Missing permission: procurement.receipt.cancel'];

        try {
            $db->beginTransaction();

            if (!empty($gr['inventory_movement_id'])) {
                $db->prepare("UPDATE goods_receipts SET inventory_movement_id = -inventory_movement_id WHERE id=?")
                   ->execute([$grId]);
            }

            $db->prepare("UPDATE goods_receipts SET status='cancelled', cancelled_by=?, cancelled_at=NOW(), cancel_reason=? WHERE id=?")
               ->execute([$userId, trim($reason), $grId]);

            self::log($db, 'receipt_cancelled', $grId, null, $reason, $userId);
            logAudit($userId, 'Cancelled goods receipt ' . $gr['gr_number'], 'procurement');

            self::updatePOReceivingStatus($db, (int)$gr['po_id']);

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function updateInspection($db, int $grId, array $data, $userId): array
    {
        $gr = self::getGR($db, $grId);
        if (!$gr) return ['success' => false, 'message' => 'Receipt not found.'];

        if (!self::userHasPermission($db, $userId, 'procurement.receipt.inspect'))
            return ['success' => false, 'message' => 'Missing permission: procurement.receipt.inspect'];

        try {
            $db->beginTransaction();

            $status = $data['inspection_status'] ?? 'not_required';
            $db->prepare("UPDATE goods_receipts SET
                inspection_status=?, inspection_date=NOW(), inspected_by=?
                WHERE id=?")
               ->execute([$status, $userId, $grId]);

            if (!empty($data['inspections']) && is_array($data['inspections'])) {
                $ins = $db->prepare("INSERT INTO goods_receipt_inspections
                    (gr_id, gr_item_id, inspector_id, inspection_status, accepted_quantity, rejected_quantity,
                     reason, comments, supporting_document)
                    VALUES (?,?,?,?,?,?,?,?,?)");
                foreach ($data['inspections'] as $insp) {
                    $ins->execute([
                        $grId, $insp['gr_item_id'] ?? null, $userId,
                        $insp['inspection_status'] ?? 'pending',
                        max(0, (float)($insp['accepted_quantity'] ?? 0)),
                        max(0, (float)($insp['rejected_quantity'] ?? 0)),
                        trim($insp['reason'] ?? '') ?: null,
                        trim($insp['comments'] ?? '') ?: null,
                        trim($insp['supporting_document'] ?? '') ?: null,
                    ]);
                }
            }

            self::log($db, 'inspection_completed', $grId, "Status: $status", null, $userId);
            logAudit($userId, 'Inspection completed for receipt ' . $gr['gr_number'], 'procurement');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function acceptGR($db, int $grId, $userId): array
    {
        if (!self::userHasPermission($db, $userId, 'procurement.receipt.accept'))
            return ['success' => false, 'message' => 'Missing permission: procurement.receipt.accept'];

        $gr = self::getGR($db, $grId);
        if (!$gr) return ['success' => false, 'message' => 'Receipt not found.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE goods_receipts SET status='accepted', accepted_at=NOW(), accepted_by=? WHERE id=?")
               ->execute([$userId, $grId]);

            self::log($db, 'receipt_accepted', $grId, null, null, $userId);
            logAudit($userId, 'Accepted goods receipt ' . $gr['gr_number'], 'procurement');

            self::updatePOReceivingStatus($db, (int)$gr['po_id']);
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function rejectGR($db, int $grId, $userId, string $reason): array
    {
        if (!self::userHasPermission($db, $userId, 'procurement.receipt.reject'))
            return ['success' => false, 'message' => 'Missing permission: procurement.receipt.reject'];

        $gr = self::getGR($db, $grId);
        if (!$gr) return ['success' => false, 'message' => 'Receipt not found.'];
        if (trim($reason) === '') return ['success' => false, 'message' => 'Rejection reason is REQUIRED.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE goods_receipts SET status='rejected', rejected_at=NOW(), rejected_by=?, cancel_reason=? WHERE id=?")
               ->execute([$userId, trim($reason), $grId]);

            self::log($db, 'receipt_rejected', $grId, null, $reason, $userId);
            logAudit($userId, 'Rejected goods receipt ' . $gr['gr_number'], 'procurement');

            self::updatePOReceivingStatus($db, (int)$gr['po_id']);
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function createSR($db, array $header, array $lines, $userId): array
    {
        $errs = [];
        if (empty($header['po_id'])) $errs[] = 'Purchase Order is required.';
        if (empty($header['vendor_id'])) $errs[] = 'Vendor is required.';
        if (empty($header['company_id'])) $errs[] = 'Company is required.';
        if (empty($header['receipt_date'])) $errs[] = 'Receipt date is required.';
        if (!count($lines)) $errs[] = 'At least one item is required.';
        if (!empty($header['company_id']) && !CompanyService::canAccessCompany($db, $userId, (int)$header['company_id']))
            $errs[] = 'Access denied for the selected company.';
        if ($errs) return ['success' => false, 'errors' => $errs];

        try {
            $db->beginTransaction();
            $number = self::nextSRNumber($db);

            $ins = $db->prepare("INSERT INTO service_receipts
                (sr_number, po_id, vendor_id, company_id, receipt_date,
                 service_description, service_period_start, service_period_end, completion_date,
                 ordered_quantity, completed_quantity, accepted_quantity, rejected_quantity,
                 project_id, cost_center_id, received_by, notes, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?)");
            $ins->execute([
                $number, (int)$header['po_id'], (int)$header['vendor_id'], (int)$header['company_id'],
                $header['receipt_date'],
                trim($header['service_description'] ?? '') ?: null,
                $header['service_period_start'] ?? null, $header['service_period_end'] ?? null,
                $header['completion_date'] ?? null,
                (float)($header['ordered_quantity'] ?? 0),
                (float)($header['completed_quantity'] ?? 0),
                (float)($header['accepted_quantity'] ?? 0),
                (float)($header['rejected_quantity'] ?? 0),
                $header['project_id'] ?? null, $header['cost_center_id'] ?? null,
                (int)$header['received_by'],
                trim($header['notes'] ?? '') ?: null, $userId,
            ]);
            $srId = (int)$db->lastInsertId();

            $li = $db->prepare("INSERT INTO service_receipt_items
                (sr_id, po_item_id, item_name, description, ordered_quantity, completed_quantity,
                 accepted_quantity, rejected_quantity, unit, unit_price, project_id, cost_center_id, notes, sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $n = 0;
            foreach ($lines as $l) {
                if (trim((string)($l['item_name'] ?? '')) === '') continue;
                $n++;
                $li->execute([
                    $srId, (int)$l['po_item_id'], trim($l['item_name']), trim($l['description'] ?? '') ?: null,
                    max(0, (float)($l['ordered_quantity'] ?? 0)),
                    max(0, (float)($l['completed_quantity'] ?? 0)),
                    max(0, (float)($l['accepted_quantity'] ?? 0)),
                    max(0, (float)($l['rejected_quantity'] ?? 0)),
                    trim($l['unit'] ?? 'pcs') ?: 'pcs',
                    (float)($l['unit_price'] ?? 0),
                    $l['project_id'] ?? null, $l['cost_center_id'] ?? null,
                    trim($l['notes'] ?? '') ?: null, $n,
                ]);
            }

            self::log($db, 'service_receipt_created', $srId, $number, null, $userId);
            logAudit($userId, 'Created service receipt ' . $number, 'procurement');
            $db->commit();
            return ['success' => true, 'id' => $srId, 'number' => $number];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Create failed: ' . $e->getMessage()]];
        }
    }

    public static function finalizeSR($db, int $srId, $userId): array
    {
        $sr = self::getSR($db, $srId);
        if (!$sr) return ['success' => false, 'message' => 'Service receipt not found.'];
        if (!in_array($sr['status'], ['draft'], true))
            return ['success' => false, 'message' => 'Only draft service receipts can be finalized.'];

        try {
            $db->beginTransaction();
            $now = date('Y-m-d H:i:s');
            $db->prepare("UPDATE service_receipts SET status='pending_acceptance', submitted_at=?, submitted_by=? WHERE id=?")
               ->execute([$now, $userId, $srId]);

            self::log($db, 'service_receipt_submitted', $srId, null, null, $userId);
            logAudit($userId, 'Submitted service receipt ' . $sr['sr_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function acceptSR($db, int $srId, $userId): array
    {
        if (!self::userHasPermission($db, $userId, 'procurement.receipt.accept'))
            return ['success' => false, 'message' => 'Missing permission: procurement.receipt.accept'];

        $sr = self::getSR($db, $srId);
        if (!$sr) return ['success' => false, 'message' => 'Service receipt not found.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE service_receipts SET status='accepted', accepted_at=NOW(), accepted_by=? WHERE id=?")
               ->execute([$userId, $srId]);

            self::log($db, 'service_receipt_accepted', $srId, null, null, $userId);
            logAudit($userId, 'Accepted service receipt ' . $sr['sr_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function rejectSR($db, int $srId, $userId, string $reason): array
    {
        if (!self::userHasPermission($db, $userId, 'procurement.receipt.reject'))
            return ['success' => false, 'message' => 'Missing permission: procurement.receipt.reject'];

        $sr = self::getSR($db, $srId);
        if (!$sr) return ['success' => false, 'message' => 'Service receipt not found.'];
        if (trim($reason) === '') return ['success' => false, 'message' => 'Rejection reason is REQUIRED.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE service_receipts SET status='rejected', rejected_at=NOW(), rejected_by=?, cancel_reason=? WHERE id=?")
               ->execute([$userId, trim($reason), $srId]);

            self::log($db, 'service_receipt_rejected', $srId, null, $reason, $userId);
            logAudit($userId, 'Rejected service receipt ' . $sr['sr_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function cancelSR($db, int $srId, $userId, string $reason): array
    {
        $sr = self::getSR($db, $srId);
        if (!$sr) return ['success' => false, 'message' => 'Service receipt not found.'];
        if (in_array($sr['status'], ['cancelled'], true))
            return ['success' => false, 'message' => 'Already cancelled.'];
        if (trim($reason) === '') return ['success' => false, 'message' => 'Cancellation reason is REQUIRED.'];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE service_receipts SET status='cancelled', cancelled_by=?, cancelled_at=NOW(), cancel_reason=? WHERE id=?")
               ->execute([$userId, trim($reason), $srId]);

            self::log($db, 'service_receipt_cancelled', $srId, null, $reason, $userId);
            logAudit($userId, 'Cancelled service receipt ' . $sr['sr_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function userHasPermission($db, $userId, $perm): bool
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

    public static function getGR($db, int $grId): ?array
    {
        $s = $db->prepare("
            SELECT gr.*, v.vendor_name, u.name received_by_name, c.company_name,
                   po.po_number, po.status as po_status
            FROM goods_receipts gr
            LEFT JOIN vendors v ON v.id=gr.vendor_id
            LEFT JOIN users u ON u.id=gr.received_by
            LEFT JOIN companies c ON c.id=gr.company_id
            LEFT JOIN purchase_orders po ON po.id=gr.po_id
            WHERE gr.id=?");
        $s->execute([$grId]);
        $r = $s->fetch();
        return $r ?: null;
    }

    public static function getGRItems($db, int $grId): array
    {
        $s = $db->prepare("SELECT * FROM goods_receipt_items WHERE gr_id=? ORDER BY sort_order, id");
        $s->execute([$grId]);
        return $s->fetchAll();
    }

    public static function getSR($db, int $srId): ?array
    {
        $s = $db->prepare("
            SELECT sr.*, v.vendor_name, u.name received_by_name, c.company_name,
                   po.po_number
            FROM service_receipts sr
            LEFT JOIN vendors v ON v.id=sr.vendor_id
            LEFT JOIN users u ON u.id=sr.received_by
            LEFT JOIN companies c ON c.id=sr.company_id
            LEFT JOIN purchase_orders po ON po.id=sr.po_id
            WHERE sr.id=?");
        $s->execute([$srId]);
        $r = $s->fetch();
        return $r ?: null;
    }

    public static function getSRItems($db, int $srId): array
    {
        $s = $db->prepare("SELECT * FROM service_receipt_items WHERE sr_id=? ORDER BY sort_order, id");
        $s->execute([$srId]);
        return $s->fetchAll();
    }

    public static function getPOItemsForReceiving($db, int $poId): array
    {
        $s = $db->prepare("
            SELECT poi.*, i.item_code, i.item_name as inventory_name
            FROM purchase_order_items poi
            LEFT JOIN inventory_items i ON i.id=poi.item_id
            WHERE poi.po_id=?
            ORDER BY poi.po_item_number, poi.id
        ");
        $s->execute([$poId]);
        return $s->fetchAll();
    }

    public static function getReceivingHistory($db, int $poId): array
    {
        $s = $db->prepare("
            SELECT gr.gr_number, gr.receipt_date, gr.status,
                   SUM(gri.received_quantity) as total_received,
                   SUM(gri.accepted_quantity) as total_accepted,
                   SUM(gri.rejected_quantity) as total_rejected,
                   SUM(gri.damaged_quantity) as total_damaged,
                   SUM(gri.remaining_quantity) as total_remaining
            FROM goods_receipts gr
            JOIN goods_receipt_items gri ON gri.gr_id = gr.id
            WHERE gr.po_id = ?
            GROUP BY gr.id
            ORDER BY gr.receipt_date DESC, gr.id DESC
        ");
        $s->execute([$poId]);
        return $s->fetchAll();
    }

    public static function dashboardStats($db): array
    {
        $s = $db->query("SELECT
            SUM(status='draft') draft,
            SUM(status='pending_inspection') pending_inspection,
            SUM(status='partially_accepted') partially_accepted,
            SUM(status='accepted') accepted,
            SUM(status='rejected') rejected,
            SUM(status='cancelled') cancelled,
            SUM(received_quantity_total) as total_received,
            SUM(accepted_quantity_total) as total_accepted,
            SUM(rejected_quantity_total) as total_rejected,
            SUM(damaged_quantity_total) as total_damaged
        FROM goods_receipts")->fetch();
        return $s ?: [];
    }

    public static function listReceipts($db, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ["1=1"];
        $params = [];
        if (!empty($filters['status']))    { $where[] = "gr.status=?"; $params[] = $filters['status']; }
        if (!empty($filters['po_id']))     { $where[] = "gr.po_id=?"; $params[] = (int)$filters['po_id']; }
        if (!empty($filters['vendor_id'])) { $where[] = "gr.vendor_id=?"; $params[] = (int)$filters['vendor_id']; }
        if (!empty($filters['company_id'])){ $where[] = "gr.company_id=?"; $params[] = (int)$filters['company_id']; }
        if (!empty($filters['search']))    { $where[] = "(gr.gr_number LIKE ? OR po.po_number LIKE ?)"; $params[] = '%' . $filters['search'] . '%'; $params[] = '%' . $filters['search'] . '%'; }
        if (!empty($filters['from_date'])) { $where[] = "gr.receipt_date >= ?"; $params[] = $filters['from_date']; }
        if (!empty($filters['to_date']))   { $where[] = "gr.receipt_date <= ?"; $params[] = $filters['to_date']; }

        $sql = "SELECT gr.*, v.vendor_name, c.company_name, po.po_number, u.name received_by_name
                FROM goods_receipts gr
                LEFT JOIN vendors v ON v.id=gr.vendor_id
                LEFT JOIN companies c ON c.id=gr.company_id
                LEFT JOIN purchase_orders po ON po.id=gr.po_id
                LEFT JOIN users u ON u.id=gr.received_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY gr.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $s = $db->prepare($sql);
        $s->execute($params);
        $rows = $s->fetchAll();

        $cnt = $db->prepare("SELECT COUNT(*) FROM goods_receipts gr WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }
}
