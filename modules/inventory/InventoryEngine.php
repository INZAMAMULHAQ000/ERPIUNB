<?php
/**
 * I1 — Inventory Engine
 * Centralized service for ALL inventory stock mutations.
 *
 * GOLDEN RULES:
 *  - ALL stock mutations MUST flow through this class.
 *  - Never directly UPDATE inventory_items.quantity from a page.
 *  - Ledger rows are immutable once posted (status='posted').
 *  - Transaction-safe: caller opens a transaction when atomicity matters.
 *  - Negative stock blocked unless allow_negative_stock = 1.
 */

class InsufficientStockException extends Exception {}

class InventoryEngine
{
    // Movement type constants
    public const MT_PURCHASE_RECEIPT = 'purchase_receipt';
    public const MT_SALES_ISSUE       = 'sales_issue';
    public const MT_ADJUSTMENT_IN    = 'adjustment_in';
    public const MT_ADJUSTMENT_OUT   = 'adjustment_out';
    public const MT_TRANSFER_OUT     = 'transfer_out';
    public const MT_TRANSFER_IN      = 'transfer_in';
    public const MT_OPENING_STOCK    = 'opening_stock';
    public const MT_REVERSAL         = 'reversal';

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    /** @return array<string,mixed> */
    public static function getSettings($db): array
    {
        $row = $db->query("SELECT * FROM inventory_settings WHERE id = 1 LIMIT 1")->fetch();
        if (!$row) {
            return [
                'allow_negative_stock' => 0,
                'default_costing_method' => 'AVG',
                'default_warehouse_id'   => null,
                'default_location_id'    => null,
                'stock_movement_prefix'  => 'SM',
                'require_adj_approval'   => 0,
                'require_transfer_approval' => 0,
            ];
        }
        return $row;
    }

    public static function getSetting($db, string $key, $default = null)
    {
        $s = self::getSettings($db);
        return $s[$key] ?? $default;
    }

    /**
     * Concurrency-safe numbering (mirrors procurement pattern).
     */
    public static function nextMovementNumber($db): string
    {
        $settings = self::getSettings($db);
        $prefix = (string)($settings['stock_movement_prefix'] ?? 'SM');
        $year   = (int)date('Y');
        $db->prepare("
            INSERT INTO inventory_sequences (seq_type, seq_year, last_number)
            VALUES ('movement', ?, 0)
            ON DUPLICATE KEY UPDATE last_number = last_number
        ")->execute([$year]);
        $db->prepare("
            UPDATE inventory_sequences
            SET last_number = LAST_INSERT_ID(last_number + 1)
            WHERE seq_type = 'movement' AND seq_year = ?
        ")->execute([$year]);
        $n = (int)$db->lastInsertId();
        return sprintf('%s-%d-%06d', $prefix, $year, $n);
    }

    public static function nextAdjustmentNumber($db): string
    {
        $year = (int)date('Y');
        $db->prepare("
            INSERT INTO inventory_sequences (seq_type, seq_year, last_number)
            VALUES ('adjustment', ?, 0)
            ON DUPLICATE KEY UPDATE last_number = last_number
        ")->execute([$year]);
        $db->prepare("
            UPDATE inventory_sequences
            SET last_number = LAST_INSERT_ID(last_number + 1)
            WHERE seq_type = 'adjustment' AND seq_year = ?
        ")->execute([$year]);
        $n = (int)$db->lastInsertId();
        return sprintf('ADJ-%d-%06d', $year, $n);
    }

    public static function nextTransferNumber($db): string
    {
        $year = (int)date('Y');
        $db->prepare("
            INSERT INTO inventory_sequences (seq_type, seq_year, last_number)
            VALUES ('transfer', ?, 0)
            ON DUPLICATE KEY UPDATE last_number = last_number
        ")->execute([$year]);
        $db->prepare("
            UPDATE inventory_sequences
            SET last_number = LAST_INSERT_ID(last_number + 1)
            WHERE seq_type = 'transfer' AND seq_year = ?
        ")->execute([$year]);
        $n = (int)$db->lastInsertId();
        return sprintf('TRF-%d-%06d', $year, $n);
    }

    /**
     * Get current stock from materialized balance table.
     * Returns row with qty_on_hand, qty_reserved, avg_unit_cost, stock_value
     * or zeros if no balance row exists yet.
     */
    public static function getCurrentStock($db, int $itemId, ?int $warehouseId = null, ?int $locationId = null): array
    {
        $sql = "SELECT * FROM inventory_stock_balances
                WHERE item_id = ?
                  AND (? IS NULL OR warehouse_id = ?)
                  AND (? IS NULL OR location_id = ?)
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$itemId, $warehouseId, $warehouseId, $locationId, $locationId]);
        $row = $stmt->fetch();
        if (!$row) {
            return [
                'item_id'       => $itemId,
                'warehouse_id'  => $warehouseId ?? 0,
                'location_id'   => $locationId ?? 0,
                'qty_on_hand'   => 0,
                'qty_reserved'  => 0,
                'avg_unit_cost' => 0,
                'stock_value'   => 0,
            ];
        }
        return $row;
    }

    /**
     * Check negative stock constraint.
     * @throws InsufficientStockException
     */
    private static function enforceNegativeStock($db, int $itemId, int $warehouseId, ?int $locationId, float $qtyOut): void
    {
        $allow = (int)self::getSetting($db, 'allow_negative_stock', 0);
        if ($allow === 1) {
            return;
        }
        $current = self::getCurrentStock($db, $itemId, $warehouseId, $locationId);
        $available = (float)($current['qty_on_hand'] ?? 0) - (float)($current['qty_reserved'] ?? 0);
        if ((float)$qtyOut > $available + 0.0001) {
            throw new InsufficientStockException(sprintf(
                'Insufficient stock for item #%d in warehouse %d. Available: %.3f, Requested: %.3f',
                $itemId, $warehouseId, $available, $qtyOut
            ));
        }
    }

    /**
     * Create or update a balance row atomically within caller's transaction.
     */
    private static function upsertBalance($db, array $data): void
    {
        $loc = $data['location_id'] ?? null;

        $qtyOnHand   = (float)($data['qty_on_hand'] ?? 0);
        $qtyReserved = (float)($data['qty_reserved'] ?? 0);
        $avgUnitCost = (float)($data['avg_unit_cost'] ?? 0);
        $stockValue  = (float)($data['stock_value'] ?? 0);

        $sql = "
            INSERT INTO inventory_stock_balances
                (company_id, item_id, warehouse_id, location_id, qty_on_hand, qty_reserved, avg_unit_cost, stock_value)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                avg_unit_cost = IF((qty_on_hand + VALUES(qty_on_hand)) > 0,
                                (stock_value + VALUES(stock_value)) / (qty_on_hand + VALUES(qty_on_hand)),
                                0),
                qty_on_hand   = qty_on_hand + VALUES(qty_on_hand),
                stock_value   = stock_value + VALUES(stock_value),
                updated_at    = NOW()
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            (int)$data['company_id'],
            (int)$data['item_id'],
            (int)$data['warehouse_id'],
            $loc,
            $qtyOnHand,
            $qtyReserved,
            $avgUnitCost,
            $stockValue,
        ]);
    }

    /**
     * Core movement recorder.
     *
     * Caller is responsible for transaction management when atomicity is required.
     * Does NOT open/commit/rollback a transaction itself.
     *
     * @param array $data keys: company_id, item_id, warehouse_id, location_id (nullable),
     *                     batch_number, serial_number, movement_type, qty_in, qty_out,
     *                     unit_cost (for in movements), reference_type, reference_id,
     *                     reference_number, movement_datetime, created_by, notes, status
     * @return array movement row
     * @throws InsufficientStockException
     */
    public static function createMovement($db, array $data): array
    {
        $companyId   = (int)($data['company_id'] ?? 0);
        $itemId      = (int)($data['item_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locationId  = $data['location_id'] ?? null;
        $movementType = (string)($data['movement_type'] ?? '');
        $qtyIn       = max(0.0, (float)($data['qty_in'] ?? 0));
        $qtyOut      = max(0.0, (float)($data['qty_out'] ?? 0));
        $unitCost    = (float)($data['unit_cost'] ?? 0);
        $userId      = (int)($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
        $refType     = $data['reference_type'] ?? null;
        $refId       = isset($data['reference_id']) ? (int)$data['reference_id'] : null;
        $refNumber   = $data['reference_number'] ?? null;
        $movementDt  = $data['movement_datetime'] ?? date('Y-m-d H:i:s');
        $notes       = $data['notes'] ?? null;
        $status      = $data['status'] ?? self::STATUS_POSTED;

        if (!$companyId || !$itemId || !$warehouseId || !$movementType) {
            throw new InvalidArgumentException('company_id, item_id, warehouse_id, movement_type required');
        }

        $isOutflow = in_array($movementType, [
            self::MT_SALES_ISSUE, self::MT_ADJUSTMENT_OUT,
            self::MT_TRANSFER_OUT, self::MT_REVERSAL
        ], true);

        if ($isOutflow && $qtyOut > 0) {
            self::enforceNegativeStock($db, $itemId, $warehouseId, $locationId, $qtyOut);
        }

        $preBalance = self::getCurrentStock($db, $itemId, $warehouseId, $locationId);
        $costBefore = (float)($preBalance['avg_unit_cost'] ?? 0);
        $valueBefore = (float)($preBalance['stock_value'] ?? 0);

        if ($isOutflow && $qtyOut > 0 && $unitCost <= 0) {
            $unitCost = $costBefore;
        }

        $totalValue = 0;
        if ($qtyIn > 0) {
            $totalValue = round($qtyIn * $unitCost, 4);
        } elseif ($qtyOut > 0) {
            $totalValue = round($qtyOut * $unitCost, 4);
        }

        $stmt = $db->prepare("
            INSERT INTO inventory_stock_ledger
                (company_id, item_id, warehouse_id, location_id, batch_number, serial_number,
                 movement_type, qty_in, qty_out, unit_cost, total_value,
                 reference_type, reference_id, reference_number,
                 movement_datetime, created_by, notes, status, posted_at)
            VALUES
                (:company_id, :item_id, :warehouse_id, :location_id, :batch_number, :serial_number,
                 :movement_type, :qty_in, :qty_out, :unit_cost, :total_value,
                 :reference_type, :reference_id, :reference_number,
                 :movement_datetime, :created_by, :notes, :status, :posted_at)
        ");
        $stmt->execute([
            ':company_id'     => $companyId,
            ':item_id'        => $itemId,
            ':warehouse_id'   => $warehouseId,
            ':location_id'    => $locationId,
            ':batch_number'   => $data['batch_number'] ?? null,
            ':serial_number'  => $data['serial_number'] ?? null,
            ':movement_type'  => $movementType,
            ':qty_in'         => $qtyIn,
            ':qty_out'        => $qtyOut,
            ':unit_cost'      => $unitCost,
            ':total_value'    => $totalValue,
            ':reference_type' => $refType,
            ':reference_id'   => $refId,
            ':reference_number'=> $refNumber,
            ':movement_datetime' => $movementDt,
            ':created_by'     => $userId,
            ':notes'          => $notes,
            ':status'         => $status,
            ':posted_at'      => $status === self::STATUS_POSTED ? date('Y-m-d H:i:s') : null,
        ]);
        $movementId = (int)$db->lastInsertId();

        $balQtyDelta  = $qtyIn - $qtyOut;
        $balValueDelta = $status === self::STATUS_POSTED ? $totalValue : 0;
        if ($qtyOut > 0) {
            $balValueDelta = -$totalValue;
        }

        self::upsertBalance($db, [
            'company_id'    => $companyId,
            'item_id'       => $itemId,
            'warehouse_id'  => $warehouseId,
            'location_id'   => $locationId,
            'qty_on_hand'   => $balQtyDelta,
            'qty_reserved'  => 0,
            'avg_unit_cost' => $unitCost > 0 && $qtyIn > 0 ? $unitCost : 0,
            'stock_value'   => $balValueDelta,
        ]);

        $postBalance = self::getCurrentStock($db, $itemId, $warehouseId, $locationId);
        $costAfter = (float)($postBalance['avg_unit_cost'] ?? 0);
        $valueAfter = (float)($postBalance['stock_value'] ?? 0);

        $db->prepare("
            UPDATE inventory_stock_ledger
            SET cost_before = ?, cost_after = ?, value_before = ?, value_after = ?
            WHERE id = ?
        ")->execute([$costBefore, $costAfter, $valueBefore, $valueAfter, $movementId]);

        self::postInventoryAccounting($db, $movementId, $userId);

        $db->prepare("
            UPDATE inventory_items i
            SET i.quantity = (
                SELECT COALESCE(SUM(qty_on_hand), 0)
                FROM inventory_stock_balances
                WHERE item_id = i.id
            )
            WHERE i.id = :item_id
        ")->execute([':item_id' => $itemId]);

        return [
            'success'        => true,
            'movement_id'    => $movementId,
            'movement_type'  => $movementType,
            'qty_in'         => $qtyIn,
            'qty_out'        => $qtyOut,
            'unit_cost'      => $unitCost,
            'total_value'    => $totalValue,
            'status'         => $status,
        ];
    }

    /**
     * List ledger with filters — read-only.
     */
    public static function listLedger($db, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'isl.company_id = ?'; $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = 'isl.item_id = ?'; $params[] = (int)$filters['item_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'isl.warehouse_id = ?'; $params[] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['movement_type'])) {
            $where[] = 'isl.movement_type = ?'; $params[] = $filters['movement_type'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'DATE(isl.movement_datetime) >= ?'; $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'DATE(isl.movement_datetime) <= ?'; $params[] = $filters['to_date'];
        }
        if (!empty($filters['reference_type'])) {
            $where[] = 'isl.reference_type = ?'; $params[] = $filters['reference_type'];
        }
        if (!empty($filters['reference_id'])) {
            $where[] = 'isl.reference_id = ?'; $params[] = (int)$filters['reference_id'];
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT isl.*, i.item_name, i.item_code,
                   w.warehouse_name, l.location_name, u.name AS created_by_name
            FROM inventory_stock_ledger isl
            JOIN inventory_items i ON i.id = isl.item_id
            JOIN warehouses w ON w.id = isl.warehouse_id
            LEFT JOIN locations l ON l.id = isl.location_id
            LEFT JOIN users u ON u.id = isl.created_by
            WHERE $whereSql
            ORDER BY isl.movement_datetime DESC, isl.id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $cnt = $db->prepare("
            SELECT COUNT(*) FROM inventory_stock_ledger isl WHERE $whereSql
        ");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Create opening stock movement.
     */
    public static function createOpeningStock($db, array $data): array
    {
        return self::createMovement($db, array_merge($data, [
            'movement_type' => self::MT_OPENING_STOCK,
            'qty_in'        => $data['quantity'] ?? 0,
            'qty_out'       => 0,
            'unit_cost'     => $data['unit_cost'] ?? 0,
            'status'        => self::STATUS_POSTED,
            'movement_datetime' => $data['movement_datetime'] ?? date('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Finalize an inventory adjustment (called after approval if required).
     */
    public static function finalizeAdjustment($db, array $adj, int $userId): array
    {
        $variance = (float)($adj['variance_qty'] ?? 0);
        if ($variance === 0.0) {
            return ['success' => false, 'message' => 'Variance quantity is zero.'];
        }

        $movementType = $variance > 0 ? self::MT_ADJUSTMENT_IN : self::MT_ADJUSTMENT_OUT;
        $qtyIn  = $variance > 0 ? abs($variance) : 0;
        $qtyOut = $variance < 0 ? abs($variance) : 0;

        $unitCost = (float)($adj['unit_cost'] ?? 0);
        if ($qtyOut > 0 && $unitCost <= 0) {
            $bal = self::getCurrentStock($db, (int)$adj['item_id'], (int)$adj['warehouse_id'], $adj['location_id'] ?? null);
            $unitCost = (float)($bal['avg_unit_cost'] ?? 0);
        }

        return self::createMovement($db, [
            'company_id'      => (int)$adj['company_id'],
            'item_id'         => (int)$adj['item_id'],
            'warehouse_id'    => (int)$adj['warehouse_id'],
            'location_id'     => $adj['location_id'] ?? null,
            'batch_number'    => $adj['batch_number'] ?? null,
            'serial_number'   => $adj['serial_number'] ?? null,
            'movement_type'   => $movementType,
            'qty_in'          => $qtyIn,
            'qty_out'         => $qtyOut,
            'unit_cost'       => $unitCost,
            'reference_type'  => 'inventory_adjustment',
            'reference_id'    => (int)$adj['id'],
            'reference_number'=> $adj['adjustment_number'] ?? null,
            'movement_datetime'=> date('Y-m-d H:i:s'),
            'created_by'      => $userId,
            'notes'           => ($adj['reason'] ?? '') . ($adj['notes'] ? ' | ' . $adj['notes'] : ''),
            'status'          => self::STATUS_POSTED,
        ]);
    }

    /**
     * Atomic stock transfer: creates both out + in movements.
     * Caller is expected to wrap in a transaction.
     */
    public static function executeTransfer($db, array $transfer, int $userId): array
    {
        $companyId      = (int)$transfer['company_id'];
        $itemId         = (int)$transfer['item_id'];
        $srcWh          = (int)$transfer['source_warehouse_id'];
        $srcLoc         = $transfer['source_location_id'] ?? null;
        $destWh         = (int)$transfer['dest_warehouse_id'];
        $destLoc        = $transfer['dest_location_id'] ?? null;
        $qty            = (float)$transfer['quantity'];

        if ($qty <= 0) {
            throw new InvalidArgumentException('Transfer quantity must be > 0');
        }
        if ($srcWh === $destWh && ($srcLoc ?? 0) === ($destLoc ?? 0)) {
            throw new InvalidArgumentException('Source and destination cannot be identical');
        }

        $srcBal = self::getCurrentStock($db, $itemId, $srcWh, $srcLoc);
        $unitCost = (float)($srcBal['avg_unit_cost'] ?? 0);
        if ($unitCost <= 0 && !empty($transfer['unit_cost'])) {
            $unitCost = (float)$transfer['unit_cost'];
        }

        self::enforceNegativeStock($db, $itemId, $srcWh, $srcLoc, $qty);

        $now = date('Y-m-d H:i:s');

        $out = self::createMovement($db, [
            'company_id'       => $companyId,
            'item_id'          => $itemId,
            'warehouse_id'     => $srcWh,
            'location_id'      => $srcLoc,
            'movement_type'    => self::MT_TRANSFER_OUT,
            'qty_in'           => 0,
            'qty_out'          => $qty,
            'unit_cost'        => $unitCost,
            'reference_type'   => 'stock_transfer',
            'reference_id'     => (int)$transfer['id'],
            'reference_number' => $transfer['transfer_number'] ?? null,
            'movement_datetime'=> $now,
            'created_by'       => $userId,
            'notes'            => $transfer['notes'] ?? null,
            'status'           => self::STATUS_POSTED,
        ]);

        $in = self::createMovement($db, [
            'company_id'       => $companyId,
            'item_id'          => $itemId,
            'warehouse_id'     => $destWh,
            'location_id'      => $destLoc,
            'movement_type'    => self::MT_TRANSFER_IN,
            'qty_in'           => $qty,
            'qty_out'          => 0,
            'unit_cost'        => $unitCost,
            'reference_type'   => 'stock_transfer',
            'reference_id'     => (int)$transfer['id'],
            'reference_number' => $transfer['transfer_number'] ?? null,
            'movement_datetime'=> $now,
            'created_by'       => $userId,
            'notes'            => $transfer['notes'] ?? null,
            'status'           => self::STATUS_POSTED,
        ]);

        return [
            'success'        => true,
            'movement_out_id'=> $out['movement_id'],
            'movement_in_id' => $in['movement_id'],
        ];
    }

    /**
     * Procurement integration: post accepted stock lines from a finalized Goods Receipt.
     * Caller must already have GR in 'accepted'/'partially_accepted'/'rejected'.
     * Idempotent: if a GR already has movements, returns existing instead of creating duplicates.
     */
    public static function postFromGoodsReceipt($db, int $grId, int $userId): array
    {
        $gr = $db->prepare("
            SELECT gr.*, gr.gr_number
            FROM goods_receipts gr
            WHERE gr.id = ?
        ");
        $gr->execute([$grId]);
        $gr = $gr->fetch();
        if (!$gr) {
            return ['success' => false, 'message' => 'Goods receipt not found.'];
        }

        // Idempotency: check if we already posted movements for this GR
        $existing = $db->prepare("
            SELECT id FROM inventory_stock_ledger
            WHERE reference_type = 'goods_receipt' AND reference_id = ?
            LIMIT 1
        ");
        $existing->execute([$grId]);
        if ($existing->fetch()) {
            return ['success' => true, 'message' => 'Already posted', 'movements' => []];
        }

        $items = $db->prepare("
            SELECT gri.*, poi.item_type, poi.unit_price AS po_unit_price
            FROM goods_receipt_items gri
            LEFT JOIN purchase_order_items poi ON poi.id = gri.po_item_id
            WHERE gri.gr_id = ?
            AND gri.item_type = 'stock'
            AND gri.accepted_quantity > 0
        ");
        $items->execute([$grId]);
        $lines = $items->fetchAll();

        if (empty($lines)) {
            return ['success' => true, 'message' => 'No stock lines to post', 'movements' => []];
        }

        $movements = [];
        foreach ($lines as $line) {
            $acceptedQty = (float)$line['accepted_quantity'];
            if ($acceptedQty <= 0) {
                continue;
            }

            $result = self::createMovement($db, [
                'company_id'       => (int)$gr['company_id'],
                'item_id'          => (int)($line['item_id'] ?? 0),
                'warehouse_id'     => (int)($line['warehouse_id'] ?? $gr['warehouse_id'] ?? 1),
                'location_id'      => $line['location_id'] ?? $gr['location_id'] ?? 1,
                'batch_number'     => $line['batch_number'] ?? null,
                'serial_number'    => $line['serial_number'] ?? null,
                'movement_type'    => self::MT_PURCHASE_RECEIPT,
                'qty_in'           => $acceptedQty,
                'qty_out'          => 0,
                'unit_cost'        => (float)($line['po_unit_price'] ?? $line['unit_price'] ?? 0),
                'reference_type'   => 'goods_receipt',
                'reference_id'     => $grId,
                'reference_number' => $gr['gr_number'] ?? null,
                'movement_datetime'=> $gr['receipt_date'] ?? date('Y-m-d H:i:s'),
                'created_by'       => $userId,
                'notes'            => 'PO: ' . ($gr['po_number'] ?? '') . ' GR: ' . ($gr['gr_number'] ?? ''),
                'status'           => self::STATUS_POSTED,
            ]);
            $movements[] = $result;
        }

        return ['success' => true, 'movements' => $movements];
    }

    /**
     * Reverse a posted movement (creates a reversal movement).
     */
    public static function reverseMovement($db, int $movementId, int $userId, string $reason = ''): array
    {
        $orig = $db->prepare("SELECT * FROM inventory_stock_ledger WHERE id = ? FOR UPDATE");
        $orig->execute([$movementId]);
        $orig = $orig->fetch();
        if (!$orig) {
            return ['success' => false, 'message' => 'Movement not found.'];
        }
        if ($orig['status'] !== self::STATUS_POSTED) {
            return ['success' => false, 'message' => 'Only posted movements can be reversed.'];
        }

        $isIn  = (float)$orig['qty_in'] > 0;
        $isOut = (float)$orig['qty_out'] > 0;

        if ($isIn) {
            $result = self::createMovement($db, [
                'company_id'      => (int)$orig['company_id'],
                'item_id'         => (int)$orig['item_id'],
                'warehouse_id'    => (int)$orig['warehouse_id'],
                'location_id'     => $orig['location_id'],
                'batch_number'    => $orig['batch_number'],
                'serial_number'   => $orig['serial_number'],
                'movement_type'   => self::MT_REVERSAL,
                'qty_in'          => 0,
                'qty_out'         => $orig['qty_in'],
                'unit_cost'       => (float)$orig['unit_cost'],
                'reference_type'  => 'inventory_movement',
                'reference_id'    => $movementId,
                'reference_number'=> 'REV-' . ($orig['reference_number'] ?? $movementId),
                'movement_datetime'=> date('Y-m-d H:i:s'),
                'created_by'      => $userId,
                'notes'           => 'Reversal of #' . $movementId . ($reason ? ': ' . $reason : ''),
                'status'          => self::STATUS_REVERSED,
            ]);
        } elseif ($isOut) {
            $result = self::createMovement($db, [
                'company_id'      => (int)$orig['company_id'],
                'item_id'         => (int)$orig['item_id'],
                'warehouse_id'    => (int)$orig['warehouse_id'],
                'location_id'     => $orig['location_id'],
                'batch_number'    => $orig['batch_number'],
                'serial_number'   => $orig['serial_number'],
                'movement_type'   => self::MT_REVERSAL,
                'qty_in'          => $orig['qty_out'],
                'qty_out'         => 0,
                'unit_cost'       => (float)$orig['unit_cost'],
                'reference_type'  => 'inventory_movement',
                'reference_id'    => $movementId,
                'reference_number'=> 'REV-' . ($orig['reference_number'] ?? $movementId),
                'movement_datetime'=> date('Y-m-d H:i:s'),
                'created_by'      => $userId,
                'notes'           => 'Reversal of #' . $movementId . ($reason ? ': ' . $reason : ''),
                'status'          => self::STATUS_REVERSED,
            ]);
        } else {
            return ['success' => false, 'message' => 'Movement has zero quantity — cannot reverse.'];
        }

        $db->prepare("UPDATE inventory_stock_ledger SET status = ?, notes = CONCAT(IFNULL(notes,''), ' [REVERSED]') WHERE id = ?")
           ->execute([self::STATUS_REVERSED, $movementId]);

        logAudit($userId, 'Reversed inventory movement #' . $movementId, 'inventory');

        return ['success' => true, 'reversal_movement_id' => $result['movement_id']];
    }

    public static function getStatusBadge(string $status): string
    {
        $map = [
            'draft'            => 'bg-gray-100 text-gray-700',
            'posted'           => 'bg-green-100 text-green-800',
            'cancelled'        => 'bg-red-100 text-red-800',
            'reversed'         => 'bg-purple-100 text-purple-800',
            'pending_approval' => 'bg-yellow-100 text-yellow-700',
            'approved'         => 'bg-blue-100 text-blue-700',
            'rejected'         => 'bg-red-100 text-red-800',
            'finalised'        => 'bg-green-100 text-green-800',
            'accepted'         => 'bg-green-100 text-green-800',
            'partially_accepted' => 'bg-orange-100 text-orange-700',
        ];
        $badge = $map[$status] ?? 'bg-gray-100 text-gray-700';
        return '<span class="px-2 py-1 text-xs rounded font-medium ' . $badge . '">' . ucfirst($status) . '</span>';
    }

    /**
     * Inventory Valuation Report.
     *
     * @return array ['rows' => [], 'total_value' => float]
     */
    public static function getValuationReport($db, array $filters = []): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'b.company_id = ?'; $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'b.warehouse_id = ?'; $params[] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = 'b.item_id = ?'; $params[] = (int)$filters['item_id'];
        }
        if (!empty($filters['item_name'])) {
            $where[] = 'i.item_name LIKE ?'; $params[] = '%' . $filters['item_name'] . '%';
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT b.company_id, b.item_id, b.warehouse_id, b.location_id,
                   i.item_code, i.item_name, uom.code AS uom_code, uom.name AS uom_name,
                   w.warehouse_code, w.warehouse_name,
                   l.location_code, l.location_name,
                   b.qty_on_hand, b.avg_unit_cost, b.stock_value
            FROM inventory_stock_balances b
            JOIN inventory_items i ON i.id = b.item_id
            JOIN warehouses w ON w.id = b.warehouse_id
            LEFT JOIN locations l ON l.id = b.location_id
            LEFT JOIN units_of_measure uom ON uom.id = i.uom_id
            WHERE $whereSql
            ORDER BY i.item_name, w.warehouse_name
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalValue = 0.0;
        foreach ($rows as $row) {
            $totalValue += (float)($row['stock_value'] ?? 0);
        }

        return ['rows' => $rows, 'total_value' => $totalValue];
    }

    /**
     * Cost Audit — valuation history per movement.
     */
    public static function getCostAudit($db, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'isl.company_id = ?'; $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = 'isl.item_id = ?'; $params[] = (int)$filters['item_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'isl.warehouse_id = ?'; $params[] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['movement_type'])) {
            $where[] = 'isl.movement_type = ?'; $params[] = $filters['movement_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'isl.movement_datetime >= ?'; $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'isl.movement_datetime <= ?'; $params[] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT isl.*, i.item_code, i.item_name,
                   w.warehouse_name, l.location_name,
                   u.name AS created_by_name
            FROM inventory_stock_ledger isl
            JOIN inventory_items i ON i.id = isl.item_id
            JOIN warehouses w ON w.id = isl.warehouse_id
            LEFT JOIN locations l ON l.id = isl.location_id
            LEFT JOIN users u ON u.id = isl.created_by
            WHERE $whereSql
            ORDER BY isl.movement_datetime DESC, isl.id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $cnt = $db->prepare("SELECT COUNT(*) FROM inventory_stock_ledger isl WHERE $whereSql");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Finance Integration: post inventory movement to GL via PostingEngine.
     * Only fires when inventory_account_id is configured in inventory_settings.
     */
    public static function postInventoryAccounting($db, int $movementId, int $userId): void
    {
        $settings = self::getSettings($db);
        $inventoryAcctId = (int)($settings['inventory_account_id'] ?? 0);
        $cogsAcctId = (int)($settings['cogs_account_id'] ?? 0);
        $adjAcctId = (int)($settings['adjustment_account_id'] ?? 0);

        if (!$inventoryAcctId && !$cogsAcctId && !$adjAcctId) {
            return;
        }

        $movement = $db->prepare("SELECT * FROM inventory_stock_ledger WHERE id = ?");
        $movement->execute([$movementId]);
        $movement = $movement->fetch();
        if (!$movement || $movement['status'] !== self::STATUS_POSTED) {
            return;
        }

        $companyId = (int)$movement['company_id'];
        $movementType = $movement['movement_type'];
        $qtyIn = (float)$movement['qty_in'];
        $qtyOut = (float)$movement['qty_out'];
        $totalValue = (float)$movement['total_value'];

        if ($totalValue <= 0) {
            return;
        }

        $debitAcct = null;
        $creditAcct = null;

        if ($qtyIn > 0 && $inventoryAcctId > 0) {
            $debitAcct = $inventoryAcctId;
            if ($movementType === self::MT_ADJUSTMENT_IN && $adjAcctId > 0) {
                $creditAcct = $adjAcctId;
            } elseif ($movementType === self::MT_PURCHASE_RECEIPT) {
                $creditAcct = $inventoryAcctId;
            }
        } elseif ($qtyOut > 0 && $inventoryAcctId > 0) {
            $creditAcct = $inventoryAcctId;
            if ($movementType === self::MT_ADJUSTMENT_OUT && $adjAcctId > 0) {
                $debitAcct = $adjAcctId;
            } elseif ($movementType === self::MT_SALES_ISSUE && $cogsAcctId > 0) {
                $debitAcct = $cogsAcctId;
            }
        }

        if (!$debitAcct || !$creditAcct || $debitAcct === $creditAcct) {
            return;
        }

        try {
            require_once __DIR__ . '/../finance/posting_engine/PostingEngine.php';
            $vtId = (int)$db->query("SELECT id FROM voucher_types WHERE voucher_name='Journal Voucher' LIMIT 1")->fetchColumn();
            if (!$vtId) return;

            $voucherNo = 'INV-' . date('Ymd') . '-' . str_pad($movementId, 6, '0', STR_PAD_LEFT);
            $ins = $db->prepare("
                INSERT INTO journal_entries (company_id, voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $ins->execute([$companyId, $voucherNo, $vtId, date('Y-m-d'), 'INV-' . $movementId, 'Inventory: ' . $movementType, $userId]);
            $jid = (int)$db->lastInsertId();

            $li = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
            $li->execute([$jid, $debitAcct, $totalValue, 0, 'Inventory ' . $movementType]);
            $li->execute([$jid, $creditAcct, 0, $totalValue, 'Inventory ' . $movementType]);

            PostingEngine::post($jid, $userId);
        } catch (Exception $e) {
            error_log('Inventory accounting posting failed: ' . $e->getMessage());
        }
    }

    // ============================================================
    // I3 — RESERVATIONS
    // ============================================================

    /**
     * Get available stock = on-hand - reserved.
     */
    public static function getAvailableStock($db, int $itemId, ?int $warehouseId = null, ?int $locationId = null): float
    {
        $bal = self::getCurrentStock($db, $itemId, $warehouseId, $locationId);
        $onHand = (float)($bal['qty_on_hand'] ?? 0);
        $reserved = (float)($bal['qty_reserved'] ?? 0);
        return max(0.0, $onHand - $reserved);
    }

    /**
     * Create a stock reservation.
     */
    public static function createReservation($db, array $data): array
    {
        $companyId   = (int)($data['company_id'] ?? 0);
        $itemId      = (int)($data['item_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locationId  = $data['location_id'] ?? null;
        $qty         = (float)($data['quantity'] ?? 0);
        $refType     = $data['reference_type'] ?? null;
        $refId       = isset($data['reference_id']) ? (int)$data['reference_id'] : null;
        $refNumber   = $data['reference_number'] ?? null;
        $notes       = $data['notes'] ?? null;
        $userId      = (int)($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));

        if (!$companyId || !$itemId || !$warehouseId || $qty <= 0) {
            throw new InvalidArgumentException('company_id, item_id, warehouse_id, quantity > 0 required');
        }

        $available = self::getAvailableStock($db, $itemId, $warehouseId, $locationId);
        $allowNegative = (int)self::getSetting($db, 'allow_negative_stock', 0);
        if ($qty > $available + 0.0001 && $allowNegative !== 1) {
            throw new InsufficientStockException(sprintf(
                'Insufficient available stock for item #%d. Available: %.3f, Requested: %.3f',
                $itemId, $available, $qty
            ));
        }

        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }
        try {
            $stmt = $db->prepare("
                INSERT INTO inventory_reservations
                    (company_id, item_id, warehouse_id, location_id, quantity,
                     reference_type, reference_id, reference_number, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'reserved', ?, ?)
            ");
            $stmt->execute([
                $companyId, $itemId, $warehouseId, $locationId, $qty,
                $refType, $refId, $refNumber, $notes, $userId
            ]);
            $reservationId = (int)$db->lastInsertId();

            $db->prepare("
                UPDATE inventory_stock_balances
                SET qty_reserved = qty_reserved + ?, updated_at = NOW()
                WHERE company_id = ? AND item_id = ? AND warehouse_id = ?
                  AND (location_id = ? OR (location_id IS NULL AND ? IS NULL))
            ")->execute([$qty, $companyId, $itemId, $warehouseId, $locationId, $locationId]);

            if (!$inTransaction) {
                $db->commit();
            }
            return ['success' => true, 'reservation_id' => $reservationId];
        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Release a reservation back to available.
     */
    public static function releaseReservation($db, int $reservationId, int $userId, string $reason = ''): array
    {
        $res = $db->prepare("SELECT * FROM inventory_reservations WHERE id = ? FOR UPDATE");
        $res->execute([$reservationId]);
        $res = $res->fetch();
        if (!$res) {
            return ['success' => false, 'message' => 'Reservation not found.'];
        }
        if ($res['status'] !== 'reserved') {
            return ['success' => false, 'message' => 'Only reserved reservations can be released. Current status: ' . $res['status']];
        }

        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("
                UPDATE inventory_reservations
                SET status = 'released', released_by = ?, released_at = NOW(), notes = CONCAT(IFNULL(notes,''), ?)
                WHERE id = ?
            ")->execute([$userId, $reason ? ' [Released: ' . $reason . ']' : '', $reservationId]);

            $db->prepare("
                UPDATE inventory_stock_balances
                SET qty_reserved = qty_reserved - ?, updated_at = NOW()
                WHERE company_id = ? AND item_id = ? AND warehouse_id = ?
                  AND (location_id = ? OR (location_id IS NULL AND ? IS NULL))
            ")->execute([
                (float)$res['quantity'], $res['company_id'], $res['item_id'],
                $res['warehouse_id'], $res['location_id'], $res['location_id']
            ]);

            if (!$inTransaction) {
                $db->commit();
            }
            return ['success' => true];
        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Consume a reservation — creates actual stock issue and marks reservation consumed.
     */
    public static function consumeReservation($db, int $reservationId, int $userId, string $reason = ''): array
    {
        $res = $db->prepare("SELECT * FROM inventory_reservations WHERE id = ? FOR UPDATE");
        $res->execute([$reservationId]);
        $res = $res->fetch();
        if (!$res) {
            return ['success' => false, 'message' => 'Reservation not found.'];
        }
        if ($res['status'] !== 'reserved') {
            return ['success' => false, 'message' => 'Only reserved reservations can be consumed. Current status: ' . $res['status']];
        }

        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }
        try {
            $movement = self::createMovement($db, [
                'company_id'       => $res['company_id'],
                'item_id'          => $res['item_id'],
                'warehouse_id'     => $res['warehouse_id'],
                'location_id'      => $res['location_id'],
                'movement_type'    => self::MT_SALES_ISSUE,
                'qty_in'           => 0,
                'qty_out'          => (float)$res['quantity'],
                'reference_type'   => $res['reference_type'] ?: 'reservation',
                'reference_id'     => $res['reference_id'] ?: $reservationId,
                'reference_number' => $res['reference_number'],
                'movement_datetime'=> date('Y-m-d H:i:s'),
                'created_by'       => $userId,
                'notes'            => 'Consumed reservation #' . $reservationId . ($reason ? ': ' . $reason : ''),
                'status'           => self::STATUS_POSTED,
            ]);

            $db->prepare("
                UPDATE inventory_reservations
                SET status = 'consumed', released_by = ?, released_at = NOW(),
                    notes = CONCAT(IFNULL(notes,''), ?)
                WHERE id = ?
            ")->execute([$userId, ' [Consumed]', $reservationId]);

            $db->prepare("
                UPDATE inventory_stock_balances
                SET qty_reserved = qty_reserved - ?, updated_at = NOW()
                WHERE company_id = ? AND item_id = ? AND warehouse_id = ?
                  AND (location_id = ? OR (location_id IS NULL AND ? IS NULL))
            ")->execute([
                (float)$res['quantity'], $res['company_id'], $res['item_id'],
                $res['warehouse_id'], $res['location_id'], $res['location_id']
            ]);

            if (!$inTransaction) {
                $db->commit();
            }
            return ['success' => true, 'movement_id' => $movement['movement_id']];
        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancel a reservation and release stock.
     */
    public static function cancelReservation($db, int $reservationId, int $userId, string $reason = ''): array
    {
        $res = $db->prepare("SELECT * FROM inventory_reservations WHERE id = ? FOR UPDATE");
        $res->execute([$reservationId]);
        $res = $res->fetch();
        if (!$res) {
            return ['success' => false, 'message' => 'Reservation not found.'];
        }
        if (!in_array($res['status'], ['reserved'], true)) {
            return ['success' => false, 'message' => 'Only reserved reservations can be cancelled. Current status: ' . $res['status']];
        }

        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("
                UPDATE inventory_reservations
                SET status = 'cancelled', released_by = ?, released_at = NOW(),
                    notes = CONCAT(IFNULL(notes,''), ?)
                WHERE id = ?
            ")->execute([$userId, $reason ? ' [Cancelled: ' . $reason . ']' : '', $reservationId]);

            $db->prepare("
                UPDATE inventory_stock_balances
                SET qty_reserved = qty_reserved - ?, updated_at = NOW()
                WHERE company_id = ? AND item_id = ? AND warehouse_id = ?
                  AND (location_id = ? OR (location_id IS NULL AND ? IS NULL))
            ")->execute([
                (float)$res['quantity'], $res['company_id'], $res['item_id'],
                $res['warehouse_id'], $res['location_id'], $res['location_id']
            ]);

            if (!$inTransaction) {
                $db->commit();
            }
            return ['success' => true];
        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * List reservations with filters.
     */
    public static function listReservations($db, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'r.company_id = ?'; $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = 'r.item_id = ?'; $params[] = (int)$filters['item_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'r.warehouse_id = ?'; $params[] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'r.status = ?'; $params[] = $filters['status'];
        }
        if (!empty($filters['reference_type'])) {
            $where[] = 'r.reference_type = ?'; $params[] = $filters['reference_type'];
        }
        $whereSql = implode(' AND ', $where);

        $sql = 'SELECT r.*, i.item_code, i.item_name, w.warehouse_code, w.warehouse_name, u.name AS created_by_name
                FROM inventory_reservations r
                JOIN inventory_items i ON i.id = r.item_id
                JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE ' . $whereSql . '
                ORDER BY r.created_at DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $cntSql = 'SELECT COUNT(*) FROM inventory_reservations r WHERE ' . $whereSql;
        $cnt = $db->prepare($cntSql);
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }

    // ============================================================
    // I3 — RECONCILIATION
    // ============================================================

    /**
     * Create a reconciliation run.
     */
    public static function createReconciliation($db, array $data): array
    {
        $companyId   = (int)($data['company_id'] ?? 0);
        $warehouseId = isset($data['warehouse_id']) && $data['warehouse_id'] !== '' ? (int)$data['warehouse_id'] : null;
        $date        = $data['reconciliation_date'] ?? date('Y-m-d');
        $notes       = $data['notes'] ?? null;
        $userId      = (int)($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));

        if (!$companyId) {
            throw new InvalidArgumentException('company_id required');
        }

        $stmt = $db->prepare("
            INSERT INTO inventory_reconciliations
                (company_id, warehouse_id, reconciliation_date, status, notes, created_by)
            VALUES (?, ?, ?, 'draft', ?, ?)
        ");
        $stmt->execute([$companyId, $warehouseId, $date, $notes, $userId]);
        return ['success' => true, 'reconciliation_id' => (int)$db->lastInsertId()];
    }

    /**
     * Add items to reconciliation from current stock balances.
     */
    public static function addReconciliationItems($db, int $reconciliationId, array $filters = []): int
    {
        $recon = $db->prepare("SELECT * FROM inventory_reconciliations WHERE id = ?");
        $recon->execute([$reconciliationId]);
        $recon = $recon->fetch();
        if (!$recon) {
            throw new InvalidArgumentException('Reconciliation not found.');
        }
        if ($recon['status'] !== 'draft') {
            throw new InvalidArgumentException('Cannot add items to a reconciliation that is not in draft status.');
        }

        $where = ['b.company_id = ?']; $params = [(int)$recon['company_id']];
        if (!empty($recon['warehouse_id'])) {
            $where[] = 'b.warehouse_id = ?'; $params[] = (int)$recon['warehouse_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = 'b.item_id = ?'; $params[] = (int)$filters['item_id'];
        }
        $whereSql = implode(' AND ', $where);

        $items = $db->prepare("
            SELECT b.*, i.item_code, i.item_name
            FROM inventory_stock_balances b
            JOIN inventory_items i ON i.id = b.item_id
            WHERE $whereSql
        ");
        $items->execute($params);
        $balances = $items->fetchAll();

        $count = 0;
        foreach ($balances as $bal) {
            $db->prepare("
                INSERT INTO inventory_reconciliation_items
                    (reconciliation_id, item_id, warehouse_id, location_id,
                     ledger_qty, physical_qty, reserved_qty, available_qty,
                     ledger_value, physical_value, unit_cost, discrepancy_qty, discrepancy_value)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
            ")->execute([
                $reconciliationId, $bal['item_id'], $bal['warehouse_id'], $bal['location_id'],
                $bal['qty_on_hand'], $bal['qty_on_hand'], $bal['qty_reserved'],
                max(0, (float)$bal['qty_on_hand'] - (float)$bal['qty_reserved']),
                $bal['stock_value'], $bal['stock_value'], $bal['avg_unit_cost']
            ]);
            $count++;
        }

        $db->prepare("UPDATE inventory_reconciliations SET total_items = total_items + ? WHERE id = ?")
           ->execute([$count, $reconciliationId]);

        return $count;
    }

    /**
     * Record physical count for a reconciliation item.
     */
    public static function recordPhysicalCount($db, int $reconciliationItemId, float $physicalQty, ?string $notes = null): array
    {
        $item = $db->prepare("SELECT * FROM inventory_reconciliation_items WHERE id = ? FOR UPDATE");
        $item->execute([$reconciliationItemId]);
        $item = $item->fetch();
        if (!$item) {
            return ['success' => false, 'message' => 'Reconciliation item not found.'];
        }

        $recon = $db->prepare("SELECT * FROM inventory_reconciliations WHERE id = ?");
        $recon->execute([$item['reconciliation_id']]);
        $recon = $recon->fetch();
        if (!$recon || !in_array($recon['status'], ['draft', 'in_progress'], true)) {
            return ['success' => false, 'message' => 'Reconciliation is not editable.'];
        }

        $unitCost = (float)$item['unit_cost'];
        $physicalValue = round($physicalQty * $unitCost, 4);
        $discrepancyQty = round($physicalQty - (float)$item['ledger_qty'], 3);
        $discrepancyValue = round($physicalQty * $unitCost - (float)$item['ledger_value'], 4);

        $db->prepare("
            UPDATE inventory_reconciliation_items
            SET physical_qty = ?, physical_value = ?, discrepancy_qty = ?, discrepancy_value = ?,
                notes = CONCAT(IFNULL(notes,''), ?)
            WHERE id = ?
        ")->execute([
            $physicalQty, $physicalValue, $discrepancyQty, $discrepancyValue,
            $notes ? ' [' . $notes . ']' : '', $reconciliationItemId
        ]);

        $db->prepare("
            UPDATE inventory_reconciliations
            SET discrepancy_items = (SELECT COUNT(*) FROM inventory_reconciliation_items WHERE reconciliation_id = ? AND discrepancy_qty != 0),
                difference_value = (SELECT COALESCE(SUM(discrepancy_value),0) FROM inventory_reconciliation_items WHERE reconciliation_id = ?)
            WHERE id = ?
        ")->execute([$item['reconciliation_id'], $item['reconciliation_id'], $item['reconciliation_id']]);

        return ['success' => true, 'discrepancy_qty' => $discrepancyQty, 'discrepancy_value' => $discrepancyValue];
    }

    /**
     * Complete reconciliation and create adjustment for discrepancies.
     */
    public static function completeReconciliation($db, int $reconciliationId, int $userId, string $reason = ''): array
    {
        $recon = $db->prepare("SELECT * FROM inventory_reconciliations WHERE id = ? FOR UPDATE");
        $recon->execute([$reconciliationId]);
        $recon = $recon->fetch();
        if (!$recon) {
            return ['success' => false, 'message' => 'Reconciliation not found.'];
        }
        if (!in_array($recon['status'], ['draft', 'in_progress'], true)) {
            return ['success' => false, 'message' => 'Reconciliation is not in progress.'];
        }

        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("UPDATE inventory_reconciliations SET status = 'in_progress' WHERE id = ?")->execute([$reconciliationId]);

            $items = $db->prepare("SELECT * FROM inventory_reconciliation_items WHERE reconciliation_id = ? AND discrepancy_qty != 0");
            $items->execute([$reconciliationId]);
            $discrepancies = $items->fetchAll();

            $adjustmentIds = [];
            foreach ($discrepancies as $item) {
                $varianceQty = (float)$item['discrepancy_qty'];
                $unitCost = (float)$item['unit_cost'];

                $adjNumber = 'ADJ-RECON-' . date('Ymd') . '-' . str_pad($reconciliationId, 6, '0', STR_PAD_LEFT);
                $db->prepare("
                    INSERT INTO inventory_adjustments
                        (adjustment_number, company_id, warehouse_id, location_id, item_id,
                         current_qty, counted_qty, variance_qty, adjustment_type,
                         unit_cost, variance_value, reason, notes, status, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
                ")->execute([
                    $adjNumber, $recon['company_id'], $recon['warehouse_id'], $item['location_id'],
                    $item['item_id'], $item['ledger_qty'],
                    $item['ledger_qty'] + $varianceQty, $varianceQty,
                    $varianceQty > 0 ? 'gain' : 'loss',
                    $unitCost, abs($item['discrepancy_value']),
                    $reason ?: 'Reconciliation adjustment',
                    $item['notes'] ?? '',
                    $userId
                ]);
                $adjId = (int)$db->lastInsertId();
                $adjustmentIds[] = $adjId;

                $adj = $db->query("SELECT * FROM inventory_adjustments WHERE id=$adjId")->fetch();
                self::finalizeAdjustment($db, $adj, $userId);

                $db->prepare("UPDATE inventory_reconciliation_items SET adjustment_id = ? WHERE id = ?")
                   ->execute([$adjId, $item['id']]);
            }

            $db->prepare("
                UPDATE inventory_reconciliations
                SET status = 'completed', completed_by = ?, completed_at = NOW(),
                    matched_items = total_items - discrepancy_items
                WHERE id = ?
            ")->execute([$userId, $reconciliationId]);

            if (!$inTransaction) {
                $db->commit();
            }
            return ['success' => true, 'adjustments_created' => $adjustmentIds];
        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get reconciliation details with items.
     */
    public static function getReconciliation($db, int $reconciliationId): array
    {
        $recon = $db->prepare("SELECT * FROM inventory_reconciliations WHERE id = ?");
        $recon->execute([$reconciliationId]);
        $recon = $recon->fetch();
        if (!$recon) {
            return [];
        }

        $items = $db->prepare("
            SELECT ri.*, i.item_code, i.item_name, w.warehouse_name
            FROM inventory_reconciliation_items ri
            JOIN inventory_items i ON i.id = ri.item_id
            JOIN warehouses w ON w.id = ri.warehouse_id
            WHERE ri.reconciliation_id = ?
            ORDER BY i.item_name
        ");
        $items->execute([$reconciliationId]);
        $recon['items'] = $items->fetchAll();

        return $recon;
    }

    /**
     * List reconciliations with filters.
     */
    public static function listReconciliations($db, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?'; $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'warehouse_id = ?'; $params[] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?'; $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'reconciliation_date >= ?'; $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'reconciliation_date <= ?'; $params[] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $sql = 'SELECT r.*, c.company_name, w.warehouse_name, u.name AS created_by_name
                FROM inventory_reconciliations r
                JOIN companies c ON c.id = r.company_id
                LEFT JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE ' . $whereSql . '
                ORDER BY r.reconciliation_date DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $cntSql = 'SELECT COUNT(*) FROM inventory_reconciliations WHERE ' . $whereSql;
        $cnt = $db->prepare($cntSql);
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }
}
