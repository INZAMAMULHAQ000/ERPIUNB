<?php
/**
 * P5 — Vendor Bill + 3-Way Matching Service
 *
 * Traceability: PO → GR/SR → Vendor Bill → Finance Journal
 * GOLDEN RULES:
 *  - ALL monetary math computed SERVER-SIDE (never trust browser totals).
 *  - 3-way match: PO vs Receipt vs Bill.
 *  - Partial billing supported; over-billing protected.
 *  - Uses EXISTING Finance PostingEngine for GL entries.
 */

require_once __DIR__ . '/../finance/group/CompanyService.php';
require_once __DIR__ . '/../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../finance/posting_engine/PostingEngine.php';

class BillService
{
    const STATUSES = ['draft','pending_approval','matched','exception','approved','posted','rejected','cancelled'];
    const EDITABLE_STATUSES = ['draft','rejected'];
    const MATCH_STATUSES = ['pending','matched','exception','overridden'];

    // ─── Audit ────────────────────────────────────────────────
    public static function log($db, $action, $billId = null, $details = null, $reason = null, $userId = null)
    {
        $db->prepare("INSERT INTO procurement_audit (action, pr_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$action, null,
                       $details !== null ? ('Bill#' . $billId . ' ' . $details) : null,
                       $reason, $_SERVER['REMOTE_ADDR'] ?? null,
                       $userId ?? ($_SESSION['user_id'] ?? null)]);
    }

    // ─── Numbering ────────────────────────────────────────────
    public static function nextNumber($db)
    {
        $prefix = self::getSetting($db, 'bill_prefix', 'VB');
        $year = (int)date('Y');
        $db->prepare("INSERT INTO procurement_sequences (seq_type,seq_year,last_number) VALUES ('bill',?,0)
                      ON DUPLICATE KEY UPDATE last_number=last_number")->execute([$year]);
        $db->prepare("UPDATE procurement_sequences SET last_number=LAST_INSERT_ID(last_number+1)
                      WHERE seq_type='bill' AND seq_year=?")->execute([$year]);
        return sprintf($prefix . '-%d-%06d', $year, (int)$db->lastInsertId());
    }

    // ─── Settings helper ──────────────────────────────────────
    public static function getSetting($db, $key, $default = '')
    {
        $s = $db->prepare("SELECT setting_value FROM procurement_settings WHERE setting_key = ?");
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $v !== false ? $v : $default;
    }

    // ─── Server-side calculation ──────────────────────────────
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

    public static function recalcHeader($db, $billId): array
    {
        $lines = $db->query("SELECT quantity, unit_price, discount_amount, tax_amount, line_total FROM vendor_bill_items WHERE bill_id=" . (int)$billId)->fetchAll();
        $sub = $dc = $tx = $gr = 0.0;
        foreach ($lines as $l) {
            $sub += (float)$l['quantity'] * (float)$l['unit_price'];
            $dc  += (float)$l['discount_amount'];
            $tx  += (float)$l['tax_amount'];
            $gr  += (float)$l['line_total'];
        }
        $sub = round($sub, 2);
        $dc  = round($dc, 2);
        $tx  = round($tx, 2);
        $gr  = round($gr, 2);
        $db->prepare("UPDATE vendor_bills SET subtotal=?, discount_amount=?, tax_amount=?, total_amount=? WHERE id=?")
           ->execute([$sub, $dc, $tx, $gr, (int)$billId]);
        return ['subtotal' => $sub, 'tax_total' => $tx, 'discount_total' => $dc, 'grand_total' => $gr];
    }

    // ─── Reads ────────────────────────────────────────────────
    public static function getBill($db, $billId)
    {
        $s = $db->prepare("SELECT b.*, v.vendor_name, v.vendor_code, v.payment_terms AS vendor_payment_terms,
                           u.name AS created_by_name, c.company_name, d.department_name, p.project_name,
                           cc.cost_center_name,
                           po.po_number, gr.gr_number, sr.sr_number
                           FROM vendor_bills b
                           LEFT JOIN vendors v ON v.id=b.vendor_id
                           LEFT JOIN users u ON u.id=b.created_by
                           LEFT JOIN companies c ON c.id=b.company_id
                           LEFT JOIN departments d ON d.id=b.department_id
                           LEFT JOIN projects p ON p.id=b.project_id
                           LEFT JOIN cost_centers cc ON cc.id=b.cost_center_id
                           LEFT JOIN purchase_orders po ON po.id=b.procurement_po_id
                           LEFT JOIN goods_receipts gr ON gr.id=b.procurement_gr_id
                           LEFT JOIN service_receipts sr ON sr.id=b.procurement_sr_id
                           WHERE b.id=?");
        $s->execute([(int)$billId]);
        return $s->fetch();
    }

    public static function getItems($db, $billId)
    {
        $s = $db->prepare("SELECT * FROM vendor_bill_items WHERE bill_id=? ORDER BY sort_order, id");
        $s->execute([(int)$billId]);
        return $s->fetchAll();
    }

    public static function getMatches($db, $billId)
    {
        $s = $db->prepare("SELECT m.*, u.name AS matched_by_name FROM vendor_bill_matches m
                           LEFT JOIN users u ON u.id=m.matched_by WHERE m.bill_id=? ORDER BY m.id");
        $s->execute([(int)$billId]);
        return $s->fetchAll();
    }

    public static function getExceptions($db, $billId)
    {
        $s = $db->prepare("SELECT e.*, u.name AS resolved_by_name FROM vendor_bill_exceptions e
                           LEFT JOIN users u ON u.id=e.resolved_by WHERE e.bill_id=? ORDER BY e.id");
        $s->execute([(int)$billId]);
        return $s->fetchAll();
    }

    public static function getApprovals($db, $billId)
    {
        $s = $db->prepare("SELECT a.*, u.name AS approver_name FROM vendor_bill_approvals a
                           JOIN users u ON u.id=a.approver_id WHERE a.bill_id=? ORDER BY a.created_at, a.id");
        $s->execute([(int)$billId]);
        return $s->fetchAll();
    }

    public static function getAttachments($db, $billId)
    {
        $s = $db->prepare("SELECT a.*, u.name AS uploaded_by_name FROM vendor_bill_attachments a
                           JOIN users u ON u.id=a.uploaded_by WHERE a.bill_id=? ORDER BY a.id");
        $s->execute([(int)$billId]);
        return $s->fetchAll();
    }

    public static function canEdit(array $bill): bool
    {
        return in_array($bill['status'], self::EDITABLE_STATUSES, true);
    }

    // ─── Validation ───────────────────────────────────────────
    private static function validateHeader($db, array $h): array
    {
        $errs = [];
        if (empty($h['vendor_id']))     $errs[] = 'Vendor is required.';
        if (empty($h['company_id']))    $errs[] = 'Company is required.';
        if (empty($h['bill_date']))     $errs[] = 'Bill date is required.';
        if (empty($h['due_date']))      $errs[] = 'Due date is required.';
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
        }
        return $errs;
    }

    // ─── Duplicate Invoice Check ──────────────────────────────
    public static function checkDuplicateInvoice($db, $vendorId, $vendorInvoiceNumber, $companyId, $excludeBillId = 0): ?string
    {
        if (empty($vendorInvoiceNumber)) return null;
        $enabled = self::getSetting($db, 'enable_duplicate_invoice_check', '1');
        if (!$enabled) return null;

        $sql = "SELECT id, bill_number FROM vendor_bills
                WHERE vendor_id = ? AND vendor_invoice_number = ? AND company_id = ? AND id != ?";
        $stmt = $db->prepare($sql . " LIMIT 1");
        $stmt->execute([(int)$vendorId, $vendorInvoiceNumber, (int)$companyId, (int)$excludeBillId]);
        $dup = $stmt->fetch();
        if ($dup) return "Duplicate vendor invoice number '$vendorInvoiceNumber' found on bill #" . $dup['bill_number'];
        return null;
    }

    // ─── Over-billing Protection ──────────────────────────────
    public static function checkOverBilling($db, array $lines, $poId = null): array
    {
        $errs = [];
        $allowOverBilling = self::getSetting($db, 'allow_over_billing', '0');

        if ($poId) {
            $poItems = $db->prepare("SELECT id, ordered_quantity FROM purchase_order_items WHERE po_id=?");
            $poItems->execute([(int)$poId]);
            $poItemMap = [];
            foreach ($poItems->fetchAll() as $pi) {
                $poItemMap[$pi['id']] = $pi;
            }

            foreach ($lines as $i => $l) {
                $poItemId = (int)($l['procurement_po_item_id'] ?? 0);
                if (!$poItemId || !isset($poItemMap[$poItemId])) continue;

                $ordered = (float)$poItemMap[$poItemId]['ordered_quantity'];
                $newQty = (float)($l['quantity'] ?? 0);

                $alreadyBilled = (float)$db->query("SELECT COALESCE(SUM(quantity),0) FROM vendor_bill_items WHERE procurement_po_item_id=" . (int)$poItemId . " AND bill_id NOT IN (SELECT id FROM vendor_bills WHERE status='cancelled')")->fetchColumn();

                $totalAfter = $alreadyBilled + $newQty;

                if ($totalAfter > $ordered && !$allowOverBilling) {
                    $errs[] = "Line " . ($i + 1) . ": total billed qty ($totalAfter) exceeds PO ordered qty ($ordered). Over-billing not allowed.";
                }
            }
        }
        return $errs;
    }

    public static function recalculateBillableQuantities($db, $poId)
    {
        $poItems = $db->prepare("SELECT id, ordered_quantity FROM purchase_order_items WHERE po_id=?");
        $poItems->execute([(int)$poId]);
        foreach ($poItems->fetchAll() as $pi) {
            $poItemId = (int)$pi['id'];
            $ordered = (float)$pi['ordered_quantity'];
            $billed = (float)$db->query("SELECT COALESCE(SUM(quantity),0) FROM vendor_bill_items WHERE procurement_po_item_id=" . $poItemId . " AND bill_id NOT IN (SELECT id FROM vendor_bills WHERE status='cancelled')")->fetchColumn();
            $remaining = max(0, $ordered - $billed);
            $db->prepare("UPDATE vendor_bill_items SET remaining_billable_quantity=? WHERE procurement_po_item_id=? AND bill_id NOT IN (SELECT id FROM vendor_bills WHERE status='cancelled')")
               ->execute([$remaining, $poItemId]);
        }
    }

    // ─── 3-Way Match ──────────────────────────────────────────
    public static function run3WayMatch($db, $billId, $userId = null): array
    {
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];

        $poId = (int)$bill['procurement_po_id'];
        $grId = $bill['procurement_gr_id'] ? (int)$bill['procurement_gr_id'] : null;
        $srId = $bill['procurement_sr_id'] ? (int)$bill['procurement_sr_id'] : null;

        if (!$poId) return ['success' => false, 'message' => 'Bill is not linked to a Purchase Order.'];

        $toleranceQty = (float)($bill['tolerance_quantity'] ?? self::getSetting($db, 'bill_quantity_tolerance', 5));
        $tolerancePrice = (float)($bill['tolerance_price'] ?? self::getSetting($db, 'bill_price_tolerance', 5));
        $toleranceAmount = (float)($bill['tolerance_amount'] ?? self::getSetting($db, 'bill_amount_tolerance', 5));

        $poItems = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=? ORDER BY po_item_number, id");
        $poItems->execute([$poId]);
        $poItems = $poItems->fetchAll();

        $billItems = self::getItems($db, $billId);

        $grItemMap = [];
        if ($grId) {
            $grItems = $db->prepare("SELECT * FROM goods_receipt_items WHERE gr_id=? ORDER BY sort_order, id");
            $grItems->execute([$grId]);
            foreach ($grItems->fetchAll() as $gi) {
                $grItemMap[$gi['po_item_id']] = $gi;
            }
        }

        $srItemMap = [];
        if ($srId) {
            $srItems = $db->prepare("SELECT * FROM service_receipt_items WHERE sr_id=? ORDER BY sort_order, id");
            $srItems->execute([$srId]);
            foreach ($srItems->fetchAll() as $si) {
                $srItemMap[$si['po_item_id']] = $si;
            }
        }

        $poItemMap = [];
        foreach ($poItems as $pi) { $poItemMap[$pi['id']] = $pi; }

        $billItemMap = [];
        foreach ($billItems as $bi) {
            $billItemMap[$bi['procurement_po_item_id'] ?? 0] = $bi;
        }

        $matchResult = 'matched';
        $exceptions = [];
        $totalPoAmount = 0;
        $totalGrAmount = 0;
        $totalBillAmount = 0;

        foreach ($poItems as $poItem) {
            $poItemId = (int)$poItem['id'];
            $poQty = (float)$poItem['ordered_quantity'];
            $poPrice = (float)$poItem['unit_price'];
            $poLineTotal = (float)$poItem['line_total'];

            $grItem = $grItemMap[$poItemId] ?? null;
            $srItem = $srItemMap[$poItemId] ?? null;
            $billItem = $billItemMap[$poItemId] ?? null;

            $receivedQty = 0.0;
            $receivedPrice = $poPrice;
            $receivedLineTotal = 0.0;

            if ($grItem) {
                $receivedQty = (float)$grItem['accepted_quantity'];
                $receivedPrice = (float)$grItem['unit_price'];
                $receivedLineTotal = (float)$grItem['unit_price'] * $receivedQty;
            } elseif ($srItem) {
                $receivedQty = (float)$srItem['accepted_quantity'];
                $receivedPrice = (float)$srItem['unit_price'];
                $receivedLineTotal = (float)$srItem['unit_price'] * $receivedQty;
            }

            $billQty = $billItem ? (float)$billItem['quantity'] : 0.0;
            $billPrice = $billItem ? (float)$billItem['unit_price'] : 0.0;
            $billLineTotal = $billItem ? (float)$billItem['line_total'] : 0.0;

            $totalPoAmount += $poLineTotal;
            $totalGrAmount += $receivedLineTotal;
            $totalBillAmount += $billLineTotal;

            $qtyDiff = $billQty - $receivedQty;
            $priceDiffAbs = abs($billPrice - $receivedPrice);
            $priceDiffPct = $receivedPrice > 0 ? ($priceDiffAbs / $receivedPrice) * 100 : 0;
            $amountDiff = $billLineTotal - $receivedLineTotal;

            $qtyMatchStatus = 'matched';
            $priceMatchStatus = 'matched';

            if ($billItem) {
                if (abs($qtyDiff) > $toleranceQty) {
                    $qtyMatchStatus = $qtyDiff > 0 ? 'over' : 'short';
                    $matchResult = 'exception';
                    $exceptions[] = [
                        'type' => 'quantity_mismatch',
                        'po_item_id' => $poItemId,
                        'item_name' => $poItem['item_name'],
                        'po_value' => $poLineTotal,
                        'received_value' => $receivedLineTotal,
                        'bill_value' => $billLineTotal,
                        'difference' => $amountDiff,
                        'reason' => "Qty: PO=$poQty, Received=$receivedQty, Billed=$billQty (diff: $qtyDiff)"
                    ];
                }

                if ($priceDiffPct > $tolerancePrice) {
                    $priceMatchStatus = 'variance';
                    if ($matchResult === 'matched') $matchResult = 'exception';
                    $exceptions[] = [
                        'type' => 'price_mismatch',
                        'po_item_id' => $poItemId,
                        'item_name' => $poItem['item_name'],
                        'po_value' => $poLineTotal,
                        'received_value' => $receivedLineTotal,
                        'bill_value' => $billLineTotal,
                        'difference' => $amountDiff,
                        'reason' => "Price: PO=$poPrice, Received=$receivedPrice, Billed=$billPrice ({$priceDiffPct}% variance)"
                    ];
                }
            } else {
                if ($receivedQty > 0) {
                    $qtyMatchStatus = 'short';
                    $matchResult = 'exception';
                    $exceptions[] = [
                        'type' => 'missing_receipt',
                        'po_item_id' => $poItemId,
                        'item_name' => $poItem['item_name'],
                        'po_value' => $poLineTotal,
                        'received_value' => $receivedLineTotal,
                        'bill_value' => 0,
                        'difference' => -$receivedLineTotal,
                        'reason' => 'Bill item missing for received quantity.'
                    ];
                }
            }

            if ($billItem) {
                $db->prepare("UPDATE vendor_bill_items SET
                    procurement_po_item_id=?, procurement_gr_item_id=?,
                    match_quantity_status=?, match_price_status=?,
                    quantity_difference=?, price_difference=?, amount_difference=?
                    WHERE id=?")
                   ->execute([
                       $poItemId,
                       $grItem ? (int)$grItem['id'] : ($srItem ? (int)$srItem['id'] : null),
                       $qtyMatchStatus, $priceMatchStatus,
                       round($qtyDiff, 3), round($priceDiffAbs, 4), round($amountDiff, 2),
                       (int)$billItem['id']
                   ]);
            }
        }

        $varianceAmount = abs($totalBillAmount - $totalGrAmount);
        $variancePct = $totalGrAmount > 0 ? round(($varianceAmount / $totalGrAmount) * 100, 3) : 0;

        if ($matchResult === 'matched' && $variancePct > $toleranceAmount) {
            $matchResult = 'exception';
            $exceptions[] = [
                'type' => 'price_mismatch',
                'po_item_id' => null,
                'item_name' => 'Overall',
                'po_value' => $totalPoAmount,
                'received_value' => $totalGrAmount,
                'bill_value' => $totalBillAmount,
                'difference' => $totalBillAmount - $totalGrAmount,
                'reason' => "Total variance {$variancePct}% exceeds tolerance {$toleranceAmount}%"
            ];
        }

        $matchId = null;
        try {
            $db->beginTransaction();

            $ins = $db->prepare("INSERT INTO vendor_bill_matches
                (bill_id, po_id, gr_id, sr_id, match_status, total_po_amount, total_gr_amount, total_bill_amount,
                 variance_amount, variance_percent, exception_details, matched_by, matched_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $ins->execute([
                (int)$billId, $poId, $grId, $srId,
                $matchResult,
                round($totalPoAmount, 2), round($totalGrAmount, 2), round($totalBillAmount, 2),
                round($varianceAmount, 2), $variancePct,
                json_encode($exceptions),
                $userId ?? ($_SESSION['user_id'] ?? null)
            ]);
            $matchId = (int)$db->lastInsertId();

            foreach ($exceptions as $ex) {
                $db->prepare("INSERT INTO vendor_bill_exceptions
                    (bill_id, match_id, exception_type, severity, po_value, received_value, bill_value,
                     difference, difference_percent, reason, affected_items, required_action, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([
                       (int)$billId, $matchId, $ex['type'], 'medium',
                       round($ex['po_value'], 2), round($ex['received_value'], 2), round($ex['bill_value'], 2),
                       round($ex['difference'], 2),
                       $ex['difference'] != 0 && $ex['received_value'] != 0 ? round(abs($ex['difference'] / $ex['received_value']) * 100, 3) : 0,
                       $ex['reason'],
                       json_encode(['po_item_id' => $ex['po_item_id'], 'item_name' => $ex['item_name']]),
                       $matchResult === 'matched' ? 'review' : 'adjust',
                       $userId ?? ($_SESSION['user_id'] ?? null)
                   ]);
            }

            $db->prepare("UPDATE vendor_bills SET match_status=? WHERE id=?")
               ->execute([$matchResult, (int)$billId]);

            self::log($db, 'match_executed', $billId, "Result: $matchResult, Variance: {$variancePct}%", null, $userId);
            logAudit($userId ?? ($_SESSION['user_id'] ?? null), "3-way match executed for bill #" . $bill['bill_number'], 'procurement');

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return [
            'success' => true,
            'match_id' => $matchId,
            'match_status' => $matchResult,
            'variance_amount' => $varianceAmount,
            'variance_percent' => $variancePct,
            'exceptions' => $exceptions
        ];
    }

    // ─── Exception Resolution ─────────────────────────────────
    public static function resolveException($db, $billId, $userId, $action, $reason = null, $overrideReason = null): array
    {
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];

        if (!in_array($bill['match_status'], ['exception'], true))
            return ['success' => false, 'message' => 'Bill is not in exception status.'];

        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.bill.override'))
            return ['success' => false, 'message' => 'Missing permission: procurement.bill.override'];

        try {
            $db->beginTransaction();

            $newStatus = $action === 'override' ? 'overridden' : ($action === 'reject' ? 'rejected' : 'exception');
            $db->prepare("UPDATE vendor_bills SET match_status=? WHERE id=?")
               ->execute([$newStatus, (int)$billId]);

            $db->prepare("UPDATE vendor_bill_exceptions SET status=?, resolved_by=?, resolved_at=NOW(), override_reason=? WHERE bill_id=? AND status='open'")
               ->execute([$newStatus === 'overridden' ? 'overridden' : 'rejected', $userId, $overrideReason ?? $reason, (int)$billId]);

            $db->prepare("INSERT INTO vendor_bill_approvals
                (bill_id, approver_id, action, comments, override_reason, previous_status, new_status)
                VALUES (?,?,?,?,?,?,?)")
               ->execute([(int)$billId, $userId, $action, $reason, $overrideReason, 'exception', $newStatus]);

            self::log($db, 'exception_' . $action, $billId, $reason, $overrideReason, $userId);
            logAudit($userId, ucfirst($action) . " exception for bill #" . $bill['bill_number'], 'procurement');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Create from PO ───────────────────────────────────────
    public static function createFromPO($db, $poId, $grId = null, $srId = null, $overrides = [], $userId = null)
    {
        $po = null;
        if ($poId) {
            $stmt = $db->prepare("SELECT po.*, v.vendor_name, v.payment_terms AS vendor_payment_terms,
                                  c.company_name, c.id AS company_id,
                                  d.department_name, p.project_name, cc.cost_center_name
                                  FROM purchase_orders po
                                  JOIN vendors v ON v.id=po.vendor_id
                                  LEFT JOIN companies c ON c.id=po.company_id
                                  LEFT JOIN departments d ON d.id=po.department_id
                                  LEFT JOIN projects p ON p.id=po.project_id
                                  LEFT JOIN cost_centers cc ON cc.id=po.cost_center_id
                                  WHERE po.id=?");
            $stmt->execute([(int)$poId]);
            $po = $stmt->fetch();
        }

        if (!$po && !$grId && !$srId)
            return ['success' => false, 'message' => 'Source document not found.'];

        $vendorId = $po['vendor_id'] ?? ($overrides['vendor_id'] ?? 0);
        $companyId = $po['company_id'] ?? ($overrides['company_id'] ?? 0);

        $lines = [];
        if ($poId) {
            $poItems = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=? ORDER BY po_item_number, id");
            $poItems->execute([(int)$poId]);
            foreach ($poItems->fetchAll() as $pi) {
                $lines[] = [
                    'procurement_po_item_id' => (int)$pi['id'],
                    'item_name' => $pi['item_name'],
                    'description' => $pi['description'],
                    'quantity' => (float)($pi['ordered_quantity'] ?? 0),
                    'unit' => $pi['unit'],
                    'unit_price' => $pi['unit_price'],
                    'discount_amount' => $pi['discount_amount'],
                    'tax_percent' => $pi['tax_percent'],
                ];
            }
        }

        return ['success' => true, 'source' => $po, 'lines' => $lines, 'gr_id' => $grId, 'sr_id' => $srId];
    }

    // ─── Create Bill ──────────────────────────────────────────
    public static function createBill($db, array $header, array $lines, $userId)
    {
        $errs = array_merge(self::validateHeader($db, $header), self::validateLines($db, $lines));
        if ($errs) return ['success' => false, 'errors' => $errs];

        $dupCheck = self::checkDuplicateInvoice($db, $header['vendor_id'], $header['vendor_invoice_number'] ?? null, $header['company_id']);
        if ($dupCheck) return ['success' => false, 'errors' => [$dupCheck]];

        $overBilling = self::checkOverBilling($db, $lines, $header['procurement_po_id'] ?? null);
        if ($overBilling) return ['success' => false, 'errors' => $overBilling];

        try {
            $db->beginTransaction();
            $number = self::nextNumber($db);

            $ins = $db->prepare("INSERT INTO vendor_bills
                (bill_number, vendor_invoice_number, vendor_id, procurement_po_id, procurement_gr_id, procurement_sr_id,
                 company_id, department_id, project_id, cost_center_id, bill_date, due_date, currency,
                 payment_terms, notes, is_procurement_bill, tolerance_quantity, tolerance_price, tolerance_amount,
                 match_status, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $number,
                trim((string)($header['vendor_invoice_number'] ?? '')),
                (int)$header['vendor_id'],
                $header['procurement_po_id'] ?? null,
                $header['procurement_gr_id'] ?? null,
                $header['procurement_sr_id'] ?? null,
                (int)$header['company_id'],
                $header['department_id'] ?? null,
                $header['project_id'] ?? null,
                $header['cost_center_id'] ?? null,
                $header['bill_date'],
                $header['due_date'],
                $header['currency'] ?? 'AED',
                $header['payment_terms'] ?? null,
                trim((string)($header['notes'] ?? '')) ?: null,
                1,
                (float)($header['tolerance_quantity'] ?? self::getSetting($db, 'bill_quantity_tolerance', 5)),
                (float)($header['tolerance_price'] ?? self::getSetting($db, 'bill_price_tolerance', 5)),
                (float)($header['tolerance_amount'] ?? self::getSetting($db, 'bill_amount_tolerance', 5)),
                'pending',
                'draft',
                $userId
            ]);
            $billId = (int)$db->lastInsertId();

            $li = $db->prepare("INSERT INTO vendor_bill_items
                (bill_id, procurement_po_item_id, procurement_gr_item_id, procurement_sr_item_id,
                 description, quantity, billed_quantity, remaining_billable_quantity,
                 unit, unit_price, discount_amount, discount_percent, tax_percent,
                 tax_type_id, tax_rate_id, tax_group_id, tax_status, taxable_amount, tax_exemption_id, tax_amount,
                 line_total, expense_account_id,
                 match_quantity_status, match_price_status, sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $n = 0;
            foreach ($lines as $l) {
                if (trim((string)($l['item_name'] ?? '')) === '') continue;
                $n++;
                list($ls, $lt, $ld, $ltot) = self::calcLine($l);
                $li->execute([
                    $billId,
                    $l['procurement_po_item_id'] ?? null,
                    $l['procurement_gr_item_id'] ?? null,
                    $l['procurement_sr_item_id'] ?? null,
                    trim($l['item_name']),
                    (float)$l['quantity'],
                    (float)$l['quantity'],
                    0,
                    $l['unit'] ?? 'pcs',
                    (float)$l['unit_price'],
                    (float)($l['discount_amount'] ?? 0),
                    (float)($l['discount_percent'] ?? 0),
                    (float)($l['tax_percent'] ?? 0),
                    $l['tax_type_id'] ?? null,
                    $l['tax_rate_id'] ?? null,
                    $l['tax_group_id'] ?? null,
                    $l['tax_status'] ?? 'taxable',
                    (float)($l['taxable_amount'] ?? 0),
                    $l['tax_exemption_id'] ?? null,
                    (float)($l['tax_amount'] ?? 0),
                    $ltot,
                    $l['expense_account_id'] ?? null,
                    'pending', 'pending', $n
                ]);
            }

            self::recalcHeader($db, $billId);
            self::log($db, 'bill_created', $billId, $number, null, $userId);
            logAudit($userId, 'Created vendor bill ' . $number, 'procurement');

            if (!empty($header['procurement_po_id'])) {
                self::updatePOBillingStatus($db, $header['procurement_po_id']);
                self::recalculateBillableQuantities($db, $header['procurement_po_id']);
            }

            $db->commit();
            return ['success' => true, 'id' => $billId, 'number' => $number];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Create failed: ' . $e->getMessage()]];
        }
    }

    // ─── Update Bill ──────────────────────────────────────────
    public static function updateBill($db, $billId, array $header, array $lines, $userId)
    {
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'errors' => ['Bill not found.']];
        if (!self::canEdit($bill)) return ['success' => false, 'errors' => ['Status "' . $bill['status'] . '" cannot be edited.']];

        $errs = array_merge(self::validateHeader($db, $header), self::validateLines($db, $lines));
        if ($errs) return ['success' => false, 'errors' => $errs];

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE vendor_bills SET vendor_id=?, vendor_invoice_number=?, company_id=?, department_id=?,
                          project_id=?, cost_center_id=?, bill_date=?, due_date=?, currency=?, payment_terms=?, notes=?
                          WHERE id=?")->execute([
                (int)$header['vendor_id'],
                trim((string)($header['vendor_invoice_number'] ?? '')),
                (int)$header['company_id'],
                $header['department_id'] ?? null,
                $header['project_id'] ?? null,
                $header['cost_center_id'] ?? null,
                $header['bill_date'],
                $header['due_date'],
                $header['currency'] ?? 'AED',
                $header['payment_terms'] ?? null,
                trim((string)($header['notes'] ?? '')) ?: null,
                (int)$billId,
            ]);

            $db->prepare("DELETE FROM vendor_bill_items WHERE bill_id=?")->execute([(int)$billId]);
            $li = $db->prepare("INSERT INTO vendor_bill_items
                (bill_id, procurement_po_item_id, procurement_gr_item_id, procurement_sr_item_id,
                 description, quantity, billed_quantity, remaining_billable_quantity,
                 unit, unit_price, discount_amount, discount_percent, tax_percent,
                 tax_type_id, tax_rate_id, tax_group_id, tax_status, taxable_amount, tax_exemption_id, tax_amount,
                 line_total, expense_account_id,
                 match_quantity_status, match_price_status, sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
             $n = 0;
             foreach ($lines as $l) {
                 if (trim((string)($l['item_name'] ?? '')) === '') continue;
                 $n++;
                 list($ls, $lt, $ld, $ltot) = self::calcLine($l);
                 $li->execute([
                     $billId,
                     $l['procurement_po_item_id'] ?? null,
                     $l['procurement_gr_item_id'] ?? null,
                     $l['procurement_sr_item_id'] ?? null,
                     trim($l['item_name']),
                     (float)$l['quantity'],
                     (float)$l['quantity'],
                     0,
                     $l['unit'] ?? 'pcs',
                     (float)$l['unit_price'],
                     (float)($l['discount_amount'] ?? 0),
                     (float)($l['discount_percent'] ?? 0),
                     (float)($l['tax_percent'] ?? 0),
                     $l['tax_type_id'] ?? null,
                     $l['tax_rate_id'] ?? null,
                     $l['tax_group_id'] ?? null,
                     $l['tax_status'] ?? 'taxable',
                     (float)($l['taxable_amount'] ?? 0),
                     $l['tax_exemption_id'] ?? null,
                     (float)($l['tax_amount'] ?? 0),
                     $ltot,
                     $l['expense_account_id'] ?? null,
                     'pending', 'pending', $n
                 ]);
             }

            self::recalcHeader($db, $billId);
            $db->prepare("UPDATE vendor_bills SET match_status='pending' WHERE id=?")->execute([(int)$billId]);
            self::log($db, 'bill_edited', $billId, null, null, $userId);

            if (!empty($header['procurement_po_id'])) {
                self::updatePOBillingStatus($db, $header['procurement_po_id']);
                self::recalculateBillableQuantities($db, $header['procurement_po_id']);
            }

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Update failed: ' . $e->getMessage()]];
        }
    }

    // ─── Submit for Approval ──────────────────────────────────
    public static function submit($db, $billId, $userId)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.bill.create'))
            return ['success' => false, 'message' => 'Missing permission: procurement.bill.create'];
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];
        if (!in_array($bill['status'], ['draft', 'rejected'], true))
            return ['success' => false, 'message' => 'Only Draft or Rejected bills can be submitted.'];

        $prev = $bill['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE vendor_bills SET status='pending_approval' WHERE id=?")
               ->execute([(int)$billId]);
            $db->prepare("INSERT INTO vendor_bill_approvals (bill_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$billId, $userId, $prev === 'rejected' ? 'resubmitted' : 'submitted',
                          $prev === 'rejected' ? 'Resubmitted after revision' : null, $prev, 'pending_approval']);
            self::log($db, 'bill_submitted', $billId, null, null, $userId);
            logAudit($userId, 'Submitted vendor bill ' . $bill['bill_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Approve ──────────────────────────────────────────────
    public static function approve($db, $billId, $userId, $comments = null)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.bill.approve'))
            return ['success' => false, 'message' => 'Missing permission: procurement.bill.approve'];
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];
        if (!in_array($bill['status'], ['pending_approval', 'matched'], true))
            return ['success' => false, 'message' => 'Only pending or matched bills can be approved.'];

        $prev = $bill['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE vendor_bills SET status='approved' WHERE id=?")
               ->execute([(int)$billId]);
            $db->prepare("INSERT INTO vendor_bill_approvals (bill_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$billId, $userId, 'approved', trim((string)$comments) ?: null, $prev, 'approved']);
            self::log($db, 'bill_approved', $billId, null, null, $userId);
            logAudit($userId, 'Approved vendor bill ' . $bill['bill_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Reject ───────────────────────────────────────────────
    public static function reject($db, $billId, $userId, $reason)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.bill.approve'))
            return ['success' => false, 'message' => 'Missing permission: procurement.bill.approve'];
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];
        if (!in_array($bill['status'], ['pending_approval'], true))
            return ['success' => false, 'message' => 'Only pending bills can be rejected.'];
        if (trim((string)$reason) === '') return ['success' => false, 'message' => 'Rejection reason is REQUIRED.'];

        $prev = $bill['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE vendor_bills SET status='rejected' WHERE id=?")
               ->execute([(int)$billId]);
            $db->prepare("INSERT INTO vendor_bill_approvals (bill_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$billId, $userId, 'rejected', trim($reason), $prev, 'rejected']);
            self::log($db, 'bill_rejected', $billId, null, $reason, $userId);
            logAudit($userId, 'Rejected vendor bill ' . $bill['bill_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Post to Finance ──────────────────────────────────────
    public static function postToFinance($db, $billId, $userId)
    {
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];
        if (!in_array($bill['status'], ['approved', 'matched'], true))
            return ['success' => false, 'message' => 'Only approved or matched bills can be posted.'];

        try {
            $db->beginTransaction();

            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('finance_ap_account_id', 'finance_expense_account_id')");
            $settings = [];
            foreach ($stmt->fetchAll() as $row) { $settings[$row['setting_key']] = (int)$row['setting_value']; }

            $apAccountId = $settings['finance_ap_account_id'] ?? 0;
            $defaultExpenseAccountId = $settings['finance_expense_account_id'] ?? 0;

            if ($apAccountId <= 0) throw new Exception('AP account not configured in Finance Settings.');
            if ($defaultExpenseAccountId <= 0) throw new Exception('Default expense account not configured in Finance Settings.');

            $stmt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Purchase Voucher' LIMIT 1");
            $stmt->execute();
            $vt = $stmt->fetch();
            if (!$vt) throw new Exception('Purchase Voucher voucher type not found.');
            $voucherTypeId = (int)$vt['id'];

            $voucherNo = generateVoucherNo($voucherTypeId, $db);
            if (!$voucherNo) throw new Exception('Failed to generate voucher number.');

            $items = $db->prepare("SELECT * FROM vendor_bill_items WHERE bill_id = ? ORDER BY sort_order");
            $items->execute([$billId]);
            $billItems = $items->fetchAll();
            if (empty($billItems)) throw new Exception('Bill has no items to post.');

            $stmt = $db->prepare("
                INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
            ");
            $stmt->execute([$voucherNo, $voucherTypeId, $bill['bill_date'], 'Vendor Bill #' . $bill['bill_number'], $userId]);
            $journalId = (int)$db->lastInsertId();

            $detailStmt = $db->prepare("
                INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
                VALUES (?, ?, ?, ?, ?)
            ");

            $totalAmount = (float)$bill['total_amount'];
            $taxAmount = (float)$bill['tax_amount'];
            $itemTotalDr = 0;

            foreach ($billItems as $item) {
                $expenseAccountId = (int)$item['expense_account_id'];
                if ($expenseAccountId <= 0) $expenseAccountId = $defaultExpenseAccountId;
                if ($expenseAccountId <= 0) throw new Exception('No expense account configured for item "' . sanitize($item['description']) . '".');

                $lineNet = (float)$item['line_total'] - (float)$item['tax_amount'];
                $detailStmt->execute([$journalId, $expenseAccountId, $lineNet > 0 ? $lineNet : 0, 0,
                    'Bill #' . $bill['bill_number'] . ' - ' . $item['description']]);
                $itemTotalDr += $lineNet > 0 ? $lineNet : 0;
            }

            if ($taxAmount > 0) {
                $inputTaxAccountId = (int)TaxService::config('input_tax_account_id', 0);
                if ($inputTaxAccountId <= 0) throw new Exception('Input Tax account not configured in Tax Settings.');
                $detailStmt->execute([$journalId, $inputTaxAccountId, $taxAmount, 0, 'Input Tax #' . $bill['bill_number']]);
                $itemTotalDr += $taxAmount;
            }

            $detailStmt->execute([$journalId, $apAccountId, 0, $totalAmount, 'Bill #' . $bill['bill_number']]);

            $typeGroups = [];
            foreach ($billItems as $it) {
                $tid = (int)($it['tax_type_id'] ?? 0);
                if (!$tid) continue;
                $typeGroups[$tid][] = [
                    'taxable_amount' => (float)$it['taxable_amount'],
                    'tax_amount'     => (float)$it['tax_amount'],
                    'tax_rate'       => (float)($it['tax_percent'] ?? 0),
                    'tax_rate_id'    => $it['tax_rate_id'] ? (int)$it['tax_rate_id'] : null,
                    'tax_group_id'   => $it['tax_group_id'] ? (int)$it['tax_group_id'] : null,
                    'tax_status'     => $it['tax_status'] ?? 'taxable',
                    'exemption_id'   => $it['tax_exemption_id'] ? (int)$it['tax_exemption_id'] : null,
                ];
            }
            foreach ($typeGroups as $tid => $lines) {
                TaxService::recordDocumentTax([
                    'source_module'      => 'vendor_bill',
                    'source_record_id'   => $billId,
                    'source_document_no' => $bill['bill_number'],
                    'transaction_date'   => $bill['bill_date'],
                    'tax_type_id'        => $tid,
                    'direction'          => 'input',
                    'counterparty_type'  => 'vendor',
                    'counterparty_id'    => (int)$bill['vendor_id'],
                    'created_by'         => $userId,
                    'lines'              => $lines,
                ]);
            }

            $result = PostingEngine::post($journalId, $userId);
            if (!$result['success']) throw new Exception($result['message']);

            $db->prepare("UPDATE vendor_bills SET status='posted', journal_entry_id=?, posted_by=?, posted_at=NOW() WHERE id=?")
               ->execute([$journalId, $userId, (int)$billId]);

            $db->prepare("INSERT INTO bill_status_history (bill_id, from_status, to_status, changed_by, change_note)
                          VALUES (?, 'approved', 'posted', ?, ?)")
               ->execute([$billId, $userId, 'Posted to GL. Voucher: ' . $voucherNo]);

            self::log($db, 'bill_posted', $billId, "Voucher: $voucherNo", null, $userId);
            logAudit($userId, 'Posted vendor bill ' . $bill['bill_number'] . ' (Voucher: ' . $voucherNo . ')', 'procurement');

            $db->commit();
            return ['success' => true, 'voucher_no' => $voucherNo];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Cancel ───────────────────────────────────────────────
    public static function cancel($db, $billId, $userId, $reason)
    {
        if (!ProcurementService::userHasPermission($db, $userId, 'procurement.bill.cancel'))
            return ['success' => false, 'message' => 'Missing permission: procurement.bill.cancel'];
        $bill = self::getBill($db, $billId);
        if (!$bill) return ['success' => false, 'message' => 'Bill not found.'];
        if (in_array($bill['status'], ['cancelled', 'posted'])) return ['success' => false, 'message' => 'Cannot cancel in status: ' . $bill['status']];
        if (trim((string)$reason) === '') return ['success' => false, 'message' => 'Cancellation reason is REQUIRED.'];

        $prev = $bill['status'];
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE vendor_bills SET status='cancelled' WHERE id=?")
               ->execute([(int)$billId]);
            $db->prepare("INSERT INTO vendor_bill_approvals (bill_id,approver_id,action,comments,previous_status,new_status)
                          VALUES (?,?,?,?,?,?)")
               ->execute([(int)$billId, $userId, 'cancelled', trim($reason), $prev, 'cancelled']);
            self::log($db, 'bill_cancelled', $billId, null, $reason, $userId);
            logAudit($userId, 'Cancelled vendor bill ' . $bill['bill_number'], 'procurement');
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Update PO Billing Status ─────────────────────────────
    public static function updatePOBillingStatus($db, $poId)
    {
        $items = $db->prepare("SELECT ordered_quantity, remaining_quantity FROM purchase_order_items WHERE po_id=?");
        $items->execute([(int)$poId]);
        $poItems = $items->fetchAll();

        $billedItems = $db->prepare("SELECT COALESCE(SUM(quantity),0) AS total_billed FROM vendor_bill_items
                                     WHERE procurement_po_item_id IN (SELECT id FROM purchase_order_items WHERE po_id=?)");
        $billedItems->execute([(int)$poId]);
        $totalBilled = (float)$billedItems->fetchColumn();

        $totalOrdered = 0.0;
        foreach ($poItems as $pi) { $totalOrdered += (float)$pi['ordered_quantity']; }

        $newStatus = 'approved';
        if ($totalBilled >= $totalOrdered && $totalOrdered > 0) {
            $newStatus = 'fully_billed';
        } elseif ($totalBilled > 0) {
            $newStatus = 'partially_billed';
        }

        $db->prepare("UPDATE purchase_orders SET status=? WHERE id=?")
           ->execute([$newStatus, (int)$poId]);
    }

    // ─── Dashboard Stats ──────────────────────────────────────
    public static function dashboardStats($db): array
    {
        $s = $db->query("SELECT
            SUM(status='draft') draft,
            SUM(status='pending_approval') pending_approval,
            SUM(status='matched') matched,
            SUM(status='exception') exception,
            SUM(status='approved') approved,
            SUM(status='posted') posted,
            SUM(status='cancelled') cancelled,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled') THEN total_amount END),0) open_value
        FROM vendor_bills WHERE is_procurement_bill=1")->fetch();
        return $s ?: [];
    }

    // ─── List with Filters ────────────────────────────────────
    public static function listBills($db, array $filters = [], $limit = 50, $offset = 0): array
    {
        $where = ["b.is_procurement_bill=1"];
        $params = [];
        if (!empty($filters['status']))    { $where[] = "b.status=?"; $params[] = $filters['status']; }
        if (!empty($filters['vendor_id'])) { $where[] = "b.vendor_id=?"; $params[] = (int)$filters['vendor_id']; }
        if (!empty($filters['company_id'])){ $where[] = "b.company_id=?"; $params[] = (int)$filters['company_id']; }
        if (!empty($filters['match_status'])) { $where[] = "b.match_status=?"; $params[] = $filters['match_status']; }
        if (!empty($filters['search']))    { $where[] = "b.bill_number LIKE ?"; $params[] = '%' . $filters['search'] . '%'; }

        $sql = "SELECT b.*, v.vendor_name, c.company_name, po.po_number, gr.gr_number
                FROM vendor_bills b
                LEFT JOIN vendors v ON v.id=b.vendor_id
                LEFT JOIN companies c ON c.id=b.company_id
                LEFT JOIN purchase_orders po ON po.id=b.procurement_po_id
                LEFT JOIN goods_receipts gr ON gr.id=b.procurement_gr_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY b.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $s = $db->prepare($sql);
        $s->execute($params);
        $rows = $s->fetchAll();

        $cnt = $db->prepare("SELECT COUNT(*) FROM vendor_bills b WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }

    // ─── Reports ──────────────────────────────────────────────
    public static function procurementOutstanding($db): array
    {
        $sql = "SELECT b.*, v.vendor_name, c.company_name, po.po_number,
                       COALESCE(b.total_amount,0) - COALESCE(b.paid_amount,0) AS outstanding
                FROM vendor_bills b
                JOIN vendors v ON v.id=b.vendor_id
                LEFT JOIN companies c ON c.id=b.company_id
                LEFT JOIN purchase_orders po ON po.id=b.procurement_po_id
                WHERE b.is_procurement_bill=1 AND b.status NOT IN ('cancelled','paid')
                ORDER BY b.due_date ASC";
        return $db->query($sql)->fetchAll();
    }

    public static function unbilledReceipts($db): array
    {
        $sql = "SELECT gr.*, po.po_number, v.vendor_name,
                       COALESCE(SUM(vbi.quantity),0) AS billed_qty,
                       gr.accepted_quantity_total - COALESCE(SUM(vbi.quantity),0) AS unbilled_qty
                FROM goods_receipts gr
                JOIN purchase_orders po ON po.id=gr.po_id
                JOIN vendors v ON v.id=gr.vendor_id
                LEFT JOIN vendor_bill_items vbi ON vbi.procurement_gr_item_id IN
                    (SELECT id FROM goods_receipt_items WHERE gr_id=gr.id)
                WHERE gr.status='accepted'
                GROUP BY gr.id
                HAVING unbilled_qty > 0
                ORDER BY gr.receipt_date DESC";
        return $db->query($sql)->fetchAll();
    }

    public static function matchExceptions($db): array
    {
        $sql = "SELECT b.*, v.vendor_name, c.company_name, po.po_number,
                       m.match_status, m.variance_amount, m.variance_percent,
                       e.exception_type, e.severity, e.status AS exception_status
                FROM vendor_bills b
                JOIN vendors v ON v.id=b.vendor_id
                LEFT JOIN companies c ON c.id=b.company_id
                LEFT JOIN purchase_orders po ON po.id=b.procurement_po_id
                LEFT JOIN vendor_bill_matches m ON m.bill_id=b.id
                LEFT JOIN vendor_bill_exceptions e ON e.bill_id=b.id AND e.status='open'
                WHERE b.is_procurement_bill=1 AND b.match_status IN ('exception','overridden')
                ORDER BY b.id DESC";
        return $db->query($sql)->fetchAll();
    }
}
