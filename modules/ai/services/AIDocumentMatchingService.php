<?php
/**
 * AI F27 — Document Matching Service.
 *
 * Matches extracted document data against existing ERP records.
 * Covers Section 9: ERP Record Matching.
 *
 * Matching targets:
 * - supplier name → vendor master
 * - customer → customer master
 * - PO number → purchase order
 * - invoice number → invoice
 * - product description → product master
 * - contract number → contracts
 *
 * Never automatically overwrites ERP master records.
 */
class AIDocumentMatchingService
{
    /**
     * Match document fields against ERP records.
     * Returns matched records, alternatives, and unmatched fields.
     */
    public static function matchDocument(PDO $db, int $companyId, int $documentId): array
    {
        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found'];
        }

        // Get latest extraction data
        $stmt = $db->prepare("
            SELECT extracted_data FROM ai_document_extractions
            WHERE document_id = ? AND review_status IN ('accepted', 'partially_accepted', 'pending')
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$documentId]);
        $extraction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$extraction) {
            return ['success' => false, 'error' => 'No extraction data available'];
        }

        $data = json_decode($extraction['extracted_data'], true) ?: [];
        $matches = [];
        $unmatched = [];

        // Match supplier name → vendors
        if (!empty($data['supplier_name'])) {
            $result = self::matchSupplier($db, $companyId, $data['supplier_name']);
            $matches['supplier'] = $result;
            if (!$result['matched']) $unmatched[] = 'supplier_name';
        }

        // Match customer → customers
        if (!empty($data['customer_name'])) {
            $result = self::matchCustomer($db, $companyId, $data['customer_name']);
            $matches['customer'] = $result;
            if (!$result['matched']) $unmatched[] = 'customer_name';
        }

        // Match invoice number → invoices
        if (!empty($data['invoice_number'])) {
            $result = self::matchInvoiceNumber($db, $companyId, $data['invoice_number']);
            $matches['invoice'] = $result;
            if (!$result['matched']) $unmatched[] = 'invoice_number';
        }

        // Match PO number → purchase orders
        if (!empty($data['po_number']) || !empty($data['purchase_order_number'])) {
            $poNum = $data['po_number'] ?? $data['purchase_order_number'];
            $result = self::matchPONumber($db, $companyId, $poNum);
            $matches['purchase_order'] = $result;
            if (!$result['matched']) $unmatched[] = 'po_number';
        }

        // Match product descriptions → product master
        if (!empty($data['items']) && is_array($data['items'])) {
            $productMatches = [];
            foreach ($data['items'] as $item) {
                $desc = $item['description'] ?? $item['name'] ?? '';
                if ($desc) {
                    $result = self::matchProduct($db, $companyId, $desc);
                    $productMatches[] = [
                        'description' => $desc,
                        'match' => $result,
                    ];
                }
            }
            $matches['products'] = $productMatches;
        }

        // Match contract number → contracts
        if (!empty($data['contract_number'])) {
            $result = self::matchContractNumber($db, $companyId, $data['contract_number']);
            $matches['contract'] = $result;
            if (!$result['matched']) $unmatched[] = 'contract_number';
        }

        // Store matches in database
        self::storeMatches($db, $companyId, $documentId, $matches);

        return [
            'success' => true,
            'matches' => $matches,
            'unmatched_fields' => $unmatched,
            'match_score' => self::calculateMatchScore($matches),
        ];
    }

    /**
     * Match supplier name against vendor master.
     */
    private static function matchSupplier(PDO $db, int $companyId, string $supplierName): array
    {
        if (!self::tableExists($db, 'vendors')) {
            return ['matched' => false, 'reason' => 'Vendor table not available'];
        }

        // Exact match
        $stmt = $db->prepare("
            SELECT id, name FROM vendors
            WHERE company_id = ? AND (name = ? OR LOWER(name) = LOWER(?))
            LIMIT 1
        ");
        $stmt->execute([$companyId, $supplierName, $supplierName]);
        $exact = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exact) {
            return [
                'matched' => true,
                'confidence' => 1.0,
                'target_module' => 'procurement',
                'target_table' => 'vendors',
                'target_record_id' => (int) $exact['id'],
                'target_record_label' => $exact['name'],
                'match_method' => 'exact_name',
            ];
        }

        // Fuzzy match — LIKE
        $stmt = $db->prepare("
            SELECT id, name FROM vendors
            WHERE company_id = ? AND name LIKE ?
            ORDER BY LENGTH(name) ASC
            LIMIT 3
        ");
        $stmt->execute([$companyId, '%' . $supplierName . '%']);
        $fuzzy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($fuzzy)) {
            // Calculate similarity
            $best = $fuzzy[0];
            $confidence = self::calculateSimilarity($supplierName, $best['name']);

            if ($confidence >= 0.6) {
                return [
                    'matched' => true,
                    'confidence' => $confidence,
                    'target_module' => 'procurement',
                    'target_table' => 'vendors',
                    'target_record_id' => (int) $best['id'],
                    'target_record_label' => $best['name'],
                    'match_method' => 'fuzzy_name',
                    'alternatives' => array_map(fn($v) => [
                        'id' => (int) $v['id'],
                        'label' => $v['name'],
                        'confidence' => self::calculateSimilarity($supplierName, $v['name']),
                    ], array_slice($fuzzy, 1)),
                ];
            }
        }

        return ['matched' => false, 'alternatives' => []];
    }

    /**
     * Match customer name against customer master.
     */
    private static function matchCustomer(PDO $db, int $companyId, string $customerName): array
    {
        if (!self::tableExists($db, 'customers')) {
            return ['matched' => false, 'reason' => 'Customer table not available'];
        }

        $stmt = $db->prepare("
            SELECT id, name FROM customers
            WHERE company_id = ? AND (name = ? OR LOWER(name) = LOWER(?))
            LIMIT 1
        ");
        $stmt->execute([$companyId, $customerName, $customerName]);
        $exact = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exact) {
            return [
                'matched' => true,
                'confidence' => 1.0,
                'target_module' => 'sales',
                'target_table' => 'customers',
                'target_record_id' => (int) $exact['id'],
                'target_record_label' => $exact['name'],
                'match_method' => 'exact_name',
            ];
        }

        // Fuzzy
        $stmt = $db->prepare("
            SELECT id, name FROM customers
            WHERE company_id = ? AND name LIKE ?
            ORDER BY LENGTH(name) ASC LIMIT 3
        ");
        $stmt->execute([$companyId, '%' . $customerName . '%']);
        $fuzzy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($fuzzy)) {
            $best = $fuzzy[0];
            $confidence = self::calculateSimilarity($customerName, $best['name']);
            if ($confidence >= 0.6) {
                return [
                    'matched' => true,
                    'confidence' => $confidence,
                    'target_module' => 'sales',
                    'target_table' => 'customers',
                    'target_record_id' => (int) $best['id'],
                    'target_record_label' => $best['name'],
                    'match_method' => 'fuzzy_name',
                ];
            }
        }

        return ['matched' => false];
    }

    /**
     * Match invoice number against invoices.
     */
    private static function matchInvoiceNumber(PDO $db, int $companyId, string $invoiceNumber): array
    {
        if (!self::tableExists($db, 'invoices')) {
            return ['matched' => false, 'reason' => 'Invoice table not available'];
        }

        $stmt = $db->prepare("
            SELECT id, invoice_number, vendor_name, amount FROM invoices
            WHERE company_id = ? AND (invoice_number = ? OR invoice_number LIKE ?)
            LIMIT 3
        ");
        $stmt->execute([$companyId, $invoiceNumber, '%' . $invoiceNumber . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($results)) {
            $best = $results[0];
            return [
                'matched' => true,
                'confidence' => ($best['invoice_number'] === $invoiceNumber) ? 1.0 : 0.8,
                'target_module' => 'finance',
                'target_table' => 'invoices',
                'target_record_id' => (int) $best['id'],
                'target_record_label' => ($best['invoice_number'] ?? '') . ' — ' . ($best['vendor_name'] ?? '') . ' ($' . number_format((float)($best['amount'] ?? 0), 2) . ')',
                'match_method' => 'invoice_number',
            ];
        }

        return ['matched' => false];
    }

    /**
     * Match PO number against purchase orders.
     */
    private static function matchPONumber(PDO $db, int $companyId, string $poNumber): array
    {
        if (!self::tableExists($db, 'purchase_orders')) {
            return ['matched' => false, 'reason' => 'PO table not available'];
        }

        $stmt = $db->prepare("
            SELECT id, po_number, supplier_name, total FROM purchase_orders
            WHERE company_id = ? AND (po_number = ? OR po_number LIKE ?)
            LIMIT 3
        ");
        $stmt->execute([$companyId, $poNumber, '%' . $poNumber . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($results)) {
            $best = $results[0];
            return [
                'matched' => true,
                'confidence' => ($best['po_number'] === $poNumber) ? 1.0 : 0.8,
                'target_module' => 'procurement',
                'target_table' => 'purchase_orders',
                'target_record_id' => (int) $best['id'],
                'target_record_label' => ($best['po_number'] ?? '') . ' — ' . ($best['supplier_name'] ?? '') . ' ($' . number_format((float)($best['total'] ?? 0), 2) . ')',
                'match_method' => 'po_number',
            ];
        }

        return ['matched' => false];
    }

    /**
     * Match product description against product master.
     */
    private static function matchProduct(PDO $db, int $companyId, string $description): array
    {
        if (!self::tableExists($db, 'products')) {
            return ['matched' => false, 'reason' => 'Product table not available'];
        }

        // Try exact name match
        $stmt = $db->prepare("
            SELECT id, name, sku FROM products
            WHERE company_id = ? AND (name = ? OR LOWER(name) = LOWER(?))
            LIMIT 1
        ");
        $stmt->execute([$companyId, $description, $description]);
        $exact = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exact) {
            return [
                'matched' => true,
                'confidence' => 1.0,
                'target_module' => 'inventory',
                'target_table' => 'products',
                'target_record_id' => (int) $exact['id'],
                'target_record_label' => $exact['name'] . ' (' . ($exact['sku'] ?? '') . ')',
                'match_method' => 'exact_name',
            ];
        }

        // Fuzzy match
        $stmt = $db->prepare("
            SELECT id, name, sku FROM products
            WHERE company_id = ? AND name LIKE ?
            ORDER BY LENGTH(name) ASC LIMIT 3
        ");
        $stmt->execute([$companyId, '%' . $description . '%']);
        $fuzzy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($fuzzy)) {
            $best = $fuzzy[0];
            $confidence = self::calculateSimilarity($description, $best['name']);
            if ($confidence >= 0.5) {
                return [
                    'matched' => true,
                    'confidence' => $confidence,
                    'target_module' => 'inventory',
                    'target_table' => 'products',
                    'target_record_id' => (int) $best['id'],
                    'target_record_label' => $best['name'] . ' (' . ($best['sku'] ?? '') . ')',
                    'match_method' => 'fuzzy_name',
                ];
            }
        }

        return ['matched' => false];
    }

    /**
     * Match contract number against contracts.
     */
    private static function matchContractNumber(PDO $db, int $companyId, string $contractNumber): array
    {
        $table = self::tableExists($db, 'contracts') ? 'contracts' : (self::tableExists($db, 'vendor_contracts') ? 'vendor_contracts' : null);
        if (!$table) {
            return ['matched' => false, 'reason' => 'Contract table not available'];
        }

        $stmt = $db->prepare("
            SELECT id, contract_number, name, vendor_name FROM {$table}
            WHERE company_id = ? AND (contract_number = ? OR contract_number LIKE ?)
            LIMIT 3
        ");
        $stmt->execute([$companyId, $contractNumber, '%' . $contractNumber . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($results)) {
            $best = $results[0];
            return [
                'matched' => true,
                'confidence' => ($best['contract_number'] === $contractNumber) ? 1.0 : 0.8,
                'target_module' => 'contracts',
                'target_table' => $table,
                'target_record_id' => (int) $best['id'],
                'target_record_label' => ($best['contract_number'] ?? '') . ' — ' . ($best['name'] ?? $best['vendor_name'] ?? ''),
                'match_method' => 'contract_number',
            ];
        }

        return ['matched' => false];
    }

    /**
     * Store matches in database.
     */
    private static function storeMatches(PDO $db, int $companyId, int $documentId, array $matches): void
    {
        // Clear old matches for this document
        $db->prepare("DELETE FROM ai_document_matches WHERE document_id = ?")
           ->execute([$documentId]);

        $stmt = $db->prepare("
            INSERT INTO ai_document_matches
                (company_id, document_id, field_name, field_value, target_module, target_table,
                 target_record_id, target_record_label, match_confidence, match_method, match_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($matches as $fieldName => $match) {
            if (is_array($match) && isset($match['matched']) && $match['matched']) {
                $stmt->execute([
                    $companyId,
                    $documentId,
                    $fieldName,
                    $match['field_value'] ?? null,
                    $match['target_module'] ?? '',
                    $match['target_table'] ?? '',
                    $match['target_record_id'] ?? 0,
                    $match['target_record_label'] ?? null,
                    $match['confidence'] ?? 0,
                    $match['match_method'] ?? 'unknown',
                    $match['confidence'] >= 0.9 ? 'auto_matched' : 'suggested',
                ]);
            }
        }
    }

    /**
     * Calculate overall match score.
     */
    private static function calculateMatchScore(array $matches): float
    {
        $total = 0;
        $matched = 0;

        foreach ($matches as $field => $match) {
            if ($field === 'products') {
                if (is_array($match)) {
                    foreach ($match as $pm) {
                        $total++;
                        if (!empty($pm['match']['matched'])) $matched++;
                    }
                }
            } elseif (is_array($match)) {
                $total++;
                if (!empty($match['matched'])) $matched++;
            }
        }

        return $total > 0 ? round($matched / $total, 2) : 0;
    }

    /**
     * Simple string similarity (Jaro-like).
     */
    private static function calculateSimilarity(string $a, string $b): float
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));

        if ($a === $b) return 1.0;
        if (empty($a) || empty($b)) return 0.0;

        // Simple token overlap
        $tokensA = array_unique(explode(' ', $a));
        $tokensB = array_unique(explode(' ', $b));
        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        return $union > 0 ? round($intersection / $union, 2) : 0;
    }

    /**
     * Get matches for a document.
     */
    public static function getMatches(PDO $db, int $documentId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_document_matches
            WHERE document_id = ?
            ORDER BY match_confidence DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Accept or reject a match.
     */
    public static function reviewMatch(PDO $db, int $matchId, int $userId, string $status): bool
    {
        if (!in_array($status, ['accepted', 'rejected'])) return false;

        $db->prepare("
            UPDATE ai_document_matches
            SET match_status = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ")->execute([$status, $userId, $matchId]);

        return true;
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
