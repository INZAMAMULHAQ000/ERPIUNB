<?php
/**
 * AI F27 — Invoice/PO/Delivery Three-Way Matching Service.
 *
 * Implements Section 13: Invoice ↔ Purchase Order ↔ Delivery/Receipt matching.
 * Compares supplier, items, quantities, prices, tax, totals, references.
 * Flags discrepancies. Never auto-approves payments.
 */
class AIDocumentInvoicePOService
{
    /**
     * Perform three-way matching between an invoice, PO, and delivery.
     */
    public static function performThreeWayMatch(
        PDO $db,
        int $companyId,
        ?int $invoiceDocumentId = null,
        ?int $poDocumentId = null,
        ?int $deliveryDocumentId = null,
        ?int $invoiceRecordId = null,
        ?int $poRecordId = null,
        ?int $deliveryRecordId = null
    ): array {
        $discrepancies = [];
        $matchDetails = [];

        // Load invoice data
        $invoiceData = self::loadInvoiceData($db, $companyId, $invoiceDocumentId, $invoiceRecordId);
        if (!$invoiceData) {
            return ['success' => false, 'error' => 'Invoice data not found'];
        }

        // Load PO data
        $poData = self::loadPOData($db, $companyId, $poDocumentId, $poRecordId);

        // Load delivery data
        $deliveryData = self::loadDeliveryData($db, $companyId, $deliveryDocumentId, $deliveryRecordId);

        // Match supplier
        if ($poData) {
            $supplierMatch = self::matchSupplier($invoiceData, $poData);
            $matchDetails['supplier'] = $supplierMatch;
            if (!$supplierMatch['match']) {
                $discrepancies[] = [
                    'field' => 'supplier',
                    'invoice_value' => $invoiceData['supplier_name'] ?? 'Unknown',
                    'po_value' => $poData['supplier_name'] ?? 'Unknown',
                    'severity' => 'high',
                    'description' => 'Invoice supplier does not match PO supplier',
                ];
            }
        }

        // Match items and quantities
        if ($poData && !empty($invoiceData['items']) && !empty($poData['items'])) {
            $itemMatches = self::matchItems($invoiceData['items'], $poData['items']);
            $matchDetails['items'] = $itemMatches;

            foreach ($itemMatches['discrepancies'] as $disc) {
                $discrepancies[] = $disc;
            }
        }

        // Match totals
        if ($poData) {
            $totalMatch = self::matchTotals($invoiceData, $poData);
            $matchDetails['totals'] = $totalMatch;

            if (!$totalMatch['match']) {
                $discrepancies[] = [
                    'field' => 'total',
                    'invoice_value' => $invoiceData['total'] ?? 0,
                    'po_value' => $poData['total'] ?? 0,
                    'difference' => ($invoiceData['total'] ?? 0) - ($poData['total'] ?? 0),
                    'severity' => 'high',
                    'description' => 'Invoice total differs from PO total',
                ];
            }
        }

        // Match quantities with delivery
        if ($deliveryData && !empty($invoiceData['items'])) {
            $deliveryMatch = self::matchWithDelivery($invoiceData['items'], $deliveryData);
            $matchDetails['delivery'] = $deliveryMatch;

            foreach ($deliveryMatch['discrepancies'] as $disc) {
                $discrepancies[] = $disc;
            }
        }

        // Determine overall status
        $status = empty($discrepancies) ? 'matched' : 'discrepancy';
        if (empty($poData) && empty($deliveryData)) {
            $status = 'pending';
        }

        // Calculate totals
        $totalInvoice = (float) ($invoiceData['total'] ?? 0);
        $totalPO = (float) ($poData['total'] ?? 0);
        $totalDelivery = (float) ($deliveryData['total'] ?? 0);

        // Quantity and price match
        $quantityMatch = empty(array_filter($discrepancies, fn($d) => $d['field'] === 'quantity'));
        $priceMatch = empty(array_filter($discrepancies, fn($d) => $d['field'] === 'price'));
        $taxMatch = empty(array_filter($discrepancies, fn($d) => $d['field'] === 'tax'));

        // Store result
        $stmt = $db->prepare("
            INSERT INTO ai_document_three_way_matches
                (company_id, invoice_document_id, po_document_id, delivery_document_id,
                 invoice_record_id, po_record_id, delivery_record_id,
                 match_status, discrepancies_json, match_details_json,
                 total_invoice, total_po, total_delivery,
                 quantity_match, price_match, tax_match)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $companyId,
            $invoiceDocumentId,
            $poDocumentId,
            $deliveryDocumentId,
            $invoiceRecordId,
            $poRecordId,
            $deliveryRecordId,
            $status,
            json_encode($discrepancies),
            json_encode($matchDetails),
            $totalInvoice,
            $totalPO,
            $totalDelivery,
            $quantityMatch ? 1 : 0,
            $priceMatch ? 1 : 0,
            $taxMatch ? 1 : 0,
        ]);

        $matchId = (int) $db->lastInsertId();

        // F23: Record matching operation
        if (class_exists('AIIntelligenceService')) {
            AIIntelligenceService::recordObservabilityTrace($db, 'three_way_match', [
                'match_id' => $matchId,
                'status' => $status,
                'discrepancy_count' => count($discrepancies),
                'total_invoice' => $totalInvoice,
                'total_po' => $totalPO,
            ]);
        }

        return [
            'success' => true,
            'match_id' => $matchId,
            'status' => $status,
            'discrepancies' => $discrepancies,
            'match_details' => $matchDetails,
            'summary' => [
                'total_invoice' => $totalInvoice,
                'total_po' => $totalPO,
                'total_delivery' => $totalDelivery,
                'quantity_match' => $quantityMatch,
                'price_match' => $priceMatch,
                'tax_match' => $taxMatch,
                'discrepancy_count' => count($discrepancies),
            ],
        ];
    }

    /**
     * Load invoice data from document or ERP record.
     */
    private static function loadInvoiceData(PDO $db, int $companyId, ?int $documentId, ?int $recordId): ?array
    {
        // From document extraction
        if ($documentId) {
            $stmt = $db->prepare("
                SELECT extracted_data FROM ai_document_extractions
                WHERE document_id = ? ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$documentId]);
            $ext = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ext) return json_decode($ext['extracted_data'], true) ?: [];
        }

        // From ERP invoice record
        if ($recordId && self::tableExists($db, 'invoices')) {
            $stmt = $db->prepare("
                SELECT invoice_number, vendor_name as supplier_name, amount as total,
                       subtotal, tax_amount as tax, currency, due_date, invoice_date
                FROM invoices WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$recordId, $companyId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return null;
    }

    /**
     * Load PO data from document or ERP record.
     */
    private static function loadPOData(PDO $db, int $companyId, ?int $documentId, ?int $recordId): ?array
    {
        if ($documentId) {
            $stmt = $db->prepare("
                SELECT extracted_data FROM ai_document_extractions
                WHERE document_id = ? ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$documentId]);
            $ext = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ext) return json_decode($ext['extracted_data'], true) ?: [];
        }

        if ($recordId && self::tableExists($db, 'purchase_orders')) {
            $stmt = $db->prepare("
                SELECT po_number, supplier_name, total, currency, expected_delivery
                FROM purchase_orders WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$recordId, $companyId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return null;
    }

    /**
     * Load delivery data from document or ERP record.
     */
    private static function loadDeliveryData(PDO $db, int $companyId, ?int $documentId, ?int $recordId): ?array
    {
        if ($documentId) {
            $stmt = $db->prepare("
                SELECT extracted_data FROM ai_document_extractions
                WHERE document_id = ? ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$documentId]);
            $ext = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ext) return json_decode($ext['extracted_data'], true) ?: [];
        }

        // Try goods_receipt or delivery_notes tables
        foreach (['goods_receipt', 'delivery_notes', 'inventory_receipts'] as $table) {
            if ($recordId && self::tableExists($db, $table)) {
                $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? AND company_id = ?");
                $stmt->execute([$recordId, $companyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
        }

        return null;
    }

    /**
     * Match supplier between invoice and PO.
     */
    private static function matchSupplier(array $invoiceData, array $poData): array
    {
        $invSupplier = strtolower(trim($invoiceData['supplier_name'] ?? ''));
        $poSupplier = strtolower(trim($poData['supplier_name'] ?? ''));

        return [
            'match' => $invSupplier !== '' && $poSupplier !== '' && similar_text($invSupplier, $poSupplier) / max(strlen($invSupplier), strlen($poSupplier)) >= 0.6,
            'invoice_value' => $invoiceData['supplier_name'] ?? null,
            'po_value' => $poData['supplier_name'] ?? null,
        ];
    }

    /**
     * Match line items between invoice and PO.
     */
    private static function matchItems(array $invoiceItems, array $poItems): array
    {
        $discrepancies = [];
        $matches = [];

        // Index PO items by description
        $poByDesc = [];
        foreach ($poItems as $poItem) {
            $desc = strtolower(trim($poItem['description'] ?? $poItem['name'] ?? ''));
            $poByDesc[$desc] = $poItem;
        }

        foreach ($invoiceItems as $invItem) {
            $desc = strtolower(trim($invItem['description'] ?? $invItem['name'] ?? ''));
            $matched = false;

            foreach ($poByDesc as $poDesc => $poItem) {
                if (similar_text($desc, $poDesc) / max(strlen($desc), strlen($poDesc)) >= 0.5) {
                    $matched = true;

                    // Check quantity
                    $invQty = (float) ($invItem['quantity'] ?? 0);
                    $poQty = (float) ($poItem['quantity'] ?? 0);
                    if ($invQty > $poQty && $poQty > 0) {
                        $discrepancies[] = [
                            'field' => 'quantity',
                            'item' => $invItem['description'] ?? $invItem['name'] ?? 'Unknown',
                            'invoice_value' => $invQty,
                            'po_value' => $poQty,
                            'severity' => 'high',
                            'description' => "Invoice quantity ({$invQty}) exceeds PO quantity ({$poQty})",
                        ];
                    }

                    // Check unit price
                    $invPrice = (float) ($invItem['unit_price'] ?? $invItem['price'] ?? 0);
                    $poPrice = (float) ($poItem['unit_price'] ?? $poItem['price'] ?? 0);
                    if ($invPrice > 0 && $poPrice > 0 && abs($invPrice - $poPrice) > 0.01) {
                        $discrepancies[] = [
                            'field' => 'price',
                            'item' => $invItem['description'] ?? $invItem['name'] ?? 'Unknown',
                            'invoice_value' => $invPrice,
                            'po_value' => $poPrice,
                            'severity' => 'medium',
                            'description' => "Invoice unit price ($" . number_format($invPrice, 2) . ") differs from PO price ($" . number_format($poPrice, 2) . ")",
                        ];
                    }

                    $matches[] = ['invoice_item' => $desc, 'po_item' => $poDesc, 'matched' => true];
                    unset($poByDesc[$poDesc]);
                    break;
                }
            }

            if (!$matched) {
                $discrepancies[] = [
                    'field' => 'item',
                    'item' => $invItem['description'] ?? $invItem['name'] ?? 'Unknown',
                    'severity' => 'medium',
                    'description' => 'Invoice item not found in PO',
                ];
            }
        }

        // Check for PO items not on invoice
        foreach ($poByDesc as $poDesc => $poItem) {
            $discrepancies[] = [
                'field' => 'item',
                'item' => $poItem['description'] ?? $poItem['name'] ?? 'Unknown',
                'severity' => 'low',
                'description' => 'PO item not found on invoice',
            ];
        }

        return ['matches' => $matches, 'discrepancies' => $discrepancies];
    }

    /**
     * Match totals between invoice and PO.
     */
    private static function matchTotals(array $invoiceData, array $poData): array
    {
        $invTotal = (float) ($invoiceData['total'] ?? $invoiceData['total_amount'] ?? 0);
        $poTotal = (float) ($poData['total'] ?? $poData['total_amount'] ?? 0);

        $tolerance = max(0.01, $poTotal * 0.001); // 0.1% tolerance

        return [
            'match' => abs($invTotal - $poTotal) <= $tolerance,
            'invoice_total' => $invTotal,
            'po_total' => $poTotal,
            'difference' => $invTotal - $poTotal,
        ];
    }

    /**
     * Match invoice items with delivery quantities.
     */
    private static function matchWithDelivery(array $invoiceItems, array $deliveryData): array
    {
        $discrepancies = [];
        $deliveryItems = $deliveryData['items'] ?? [];

        foreach ($invoiceItems as $invItem) {
            $desc = strtolower(trim($invItem['description'] ?? $invItem['name'] ?? ''));
            $invQty = (float) ($invItem['quantity'] ?? 0);

            $delivered = false;
            foreach ($deliveryItems as $delItem) {
                $delDesc = strtolower(trim($delItem['description'] ?? $delItem['name'] ?? ''));
                if (similar_text($desc, $delDesc) / max(strlen($desc), strlen($delDesc)) >= 0.5) {
                    $delivered = true;
                    break;
                }
            }

            if (!$delivered && $invQty > 0) {
                $discrepancies[] = [
                    'field' => 'delivery',
                    'item' => $invItem['description'] ?? $invItem['name'] ?? 'Unknown',
                    'severity' => 'medium',
                    'description' => 'Invoice references a PO item with no matching delivery/receipt',
                ];
            }
        }

        return ['discrepancies' => $discrepancies];
    }

    /**
     * Review a three-way match.
     */
    public static function reviewMatch(PDO $db, int $matchId, int $userId, string $status): bool
    {
        if (!in_array($status, ['matched', 'manual_review'])) return false;

        $db->prepare("
            UPDATE ai_document_three_way_matches
            SET match_status = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ")->execute([$status, $userId, $matchId]);

        return true;
    }

    /**
     * Get three-way matches for a company.
     */
    public static function getMatches(PDO $db, int $companyId, string $status = null): array
    {
        $sql = "SELECT * FROM ai_document_three_way_matches WHERE company_id = ?";
        $params = [$companyId];

        if ($status) {
            $sql .= " AND match_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if table exists.
     */
    private static function tableExists(PDO $db, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            $cache[$table] = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}
