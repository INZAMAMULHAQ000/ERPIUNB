<?php
/**
 * AI Document Comparison Service
 * 
 * F20: Multi-Document Comparison — quotation, invoice, contract comparisons.
 * All methods are static and take $db as first parameter.
 */

class AIDocumentComparisonService {

    /**
     * Create a comparison between documents.
     */
    public static function create($db, $data) {
        $companyId = (int)($data['company_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $docIds = $data['document_ids'] ?? [];
        $compType = $data['comparison_type'] ?? 'general';
        $createdBy = (int)($data['created_by'] ?? 0);

        if (class_exists('AIKillSwitchService') && !AIKillSwitchService::isEnabled($db, 'document_comparison')) {
            return ['success' => false, 'error' => 'Document comparison is currently disabled.'];
        }

        if ($companyId <= 0 || $title === '' || empty($docIds)) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        $stmt = $db->prepare("INSERT INTO ai_document_comparisons 
            (company_id, title, comparison_type, document_ids, created_by, status)
            VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$companyId, $title, $compType, json_encode($docIds), $createdBy]);

        $compId = (int)$db->lastInsertId();

        // Log audit
        AIDocumentService::logAudit($db, $companyId, null, 'comparison_generated', $createdBy, ['comparison_id' => $compId]);

        return ['success' => true, 'comparison_id' => $compId];
    }

    /**
     * Get a comparison by ID.
     */
    public static function getById($db, $comparisonId) {
        $stmt = $db->prepare("SELECT c.*, u.name as created_by_name
            FROM ai_document_comparisons c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.comparison_id = ?");
        $stmt->execute([(int)$comparisonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['document_ids'] = json_decode($row['document_ids'], true);
            $row['comparison_result'] = $row['comparison_result'] ? json_decode($row['comparison_result'], true) : null;
        }
        return $row ?: null;
    }

    /**
     * Complete a comparison with results.
     */
    public static function complete($db, $comparisonId, $result, $summary = null) {
        $db->prepare("UPDATE ai_document_comparisons 
            SET status = 'completed', comparison_result = ?, summary = ?
            WHERE comparison_id = ?")
            ->execute([json_encode($result), $summary, (int)$comparisonId]);
        return ['success' => true];
    }

    /**
     * Generate quotation comparison structure.
     */
    public static function generateQuotationComparison($db, $documentIds) {
        $documents = [];
        foreach ($documentIds as $docId) {
            $doc = AIDocumentService::getById($db, (int)$docId);
            if (!$doc) continue;

            $extStmt = $db->prepare("SELECT extracted_data FROM ai_document_extractions 
                WHERE document_id = ? ORDER BY created_at DESC LIMIT 1");
            $extStmt->execute([(int)$docId]);
            $ext = $extStmt->fetch(PDO::FETCH_ASSOC);
            $data = $ext ? json_decode($ext['extracted_data'], true) : [];

            $documents[] = [
                'document_id' => $doc['document_id'],
                'file_name' => $doc['file_name'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'quotation_number' => $data['quotation_number'] ?? null,
                'currency' => $data['currency'] ?? null,
                'total' => $data['total_amount'] ?? $data['total'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'items' => $data['items'] ?? [],
                'quotation_date' => $data['quotation_date'] ?? null,
            ];
        }

        // Build comparison fields
        $fields = [
            'supplier_name' => 'Supplier',
            'currency' => 'Currency',
            'total' => 'Total Amount',
            'payment_terms' => 'Payment Terms',
            'delivery_terms' => 'Delivery Terms',
            'valid_until' => 'Valid Until',
            'quotation_date' => 'Quotation Date',
        ];

        $comparison = [
            'documents' => $documents,
            'fields' => [],
        ];

        foreach ($fields as $key => $label) {
            $values = [];
            $allNull = true;
            foreach ($documents as $doc) {
                $val = $doc[$key] ?? null;
                $values[] = ['document_id' => $doc['document_id'], 'value' => $val];
                if ($val !== null) $allNull = false;
            }
            $comparison['fields'][] = [
                'key' => $key,
                'label' => $label,
                'values' => $values,
                'available' => !$allNull,
            ];
        }

        // Item-level comparison
        if (count($documents) >= 2) {
            $allItems = [];
            foreach ($documents as $doc) {
                foreach (($doc['items'] ?? []) as $item) {
                    $itemName = $item['description'] ?? $item['name'] ?? '';
                    if (!isset($allItems[$itemName])) $allItems[$itemName] = [];
                    $allItems[$itemName][$doc['document_id']] = $item;
                }
            }
            $comparison['items'] = $allItems;
        }

        return $comparison;
    }

    /**
     * List comparisons for a company.
     */
    public static function listByCompany($db, $companyId) {
        $stmt = $db->prepare("SELECT c.*, u.name as created_by_name
            FROM ai_document_comparisons c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.company_id = ?
            ORDER BY c.created_at DESC");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['document_ids'] = json_decode($r['document_ids'], true);
        }
        return $rows;
    }
}
