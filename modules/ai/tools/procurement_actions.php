<?php
/**
 * AI F9 — Procurement Action Handlers.
 *
 * Implements Create Supplier and Create Purchase Requisition actions.
 * Reuses existing ERP business logic where possible.
 */

if (!class_exists('RfqService')) {
    require_once __DIR__ . '/../../procurement/RfqService.php';
}

// ═══ Action Definitions ═══

AIActionRegistry::register([
    'id'                    => 'procurement.create_supplier',
    'name'                  => 'Create Supplier',
    'description'           => 'Create a new supplier/vendor in the system. Requires name and at least one contact method.',
    'module'                => 'procurement',
    'category'              => 'create',
    'risk_level'            => AI_ACTION_RISK_MEDIUM,
    'required_permissions'  => ['vendors.create'],
    'requires_confirmation' => true,
    'required_fields'       => ['vendor_name'],
    'input_schema'          => [
        'type'       => 'object',
        'properties' => [
            'vendor_name'    => ['type' => 'string', 'description' => 'Name of the supplier/vendor'],
            'contact_person' => ['type' => 'string', 'description' => 'Primary contact person name'],
            'email'          => ['type' => 'string', 'description' => 'Contact email address'],
            'phone'          => ['type' => 'string', 'description' => 'Contact phone number'],
            'address'        => ['type' => 'string', 'description' => 'Physical address'],
            'website'        => ['type' => 'string', 'description' => 'Company website'],
            'notes'          => ['type' => 'string', 'description' => 'Additional notes'],
        ],
        'required' => ['vendor_name'],
    ],
    'handler_class'         => 'AIActionCreateSupplier',
]);

AIActionRegistry::register([
    'id'                    => 'procurement.create_purchase_requisition',
    'name'                  => 'Create Purchase Requisition',
    'description'           => 'Create a new purchase requisition. Requires requester, dates, and items.',
    'module'                => 'procurement',
    'category'              => 'create',
    'risk_level'            => AI_ACTION_RISK_MEDIUM,
    'required_permissions'  => ['procurement.pr.create'],
    'requires_confirmation' => true,
    'required_fields'       => ['requester_id', 'request_date', 'required_date'],
    'input_schema'          => [
        'type'       => 'object',
        'properties' => [
            'requester_id'  => ['type' => 'integer', 'description' => 'ID of the requester user'],
            'department_id' => ['type' => 'integer', 'description' => 'Department ID'],
            'request_date'  => ['type' => 'string', 'description' => 'Date of request (YYYY-MM-DD)'],
            'required_date' => ['type' => 'string', 'description' => 'Date required by (YYYY-MM-DD)'],
            'priority'      => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent'], 'description' => 'Priority level'],
            'purpose'       => ['type' => 'string', 'description' => 'Purpose/justification for the requisition'],
            'description'   => ['type' => 'string', 'description' => 'Detailed description of items needed'],
            'currency'      => ['type' => 'string', 'description' => 'Currency code (default: AED)'],
        ],
        'required' => ['requester_id', 'request_date', 'required_date'],
    ],
    'handler_class'         => 'AIActionCreatePurchaseRequisition',
]);

AIActionRegistry::register([
    'id'                    => 'procurement.create_quotation_draft',
    'name'                  => 'Create Quotation Draft',
    'description'           => 'Create a draft vendor quotation from an RFQ. The draft copies RFQ items and can be edited before submission.',
    'module'                => 'procurement',
    'category'              => 'create',
    'risk_level'            => AI_ACTION_RISK_MEDIUM,
    'required_permissions'  => ['procurement.quotation.create'],
    'requires_confirmation' => true,
    'required_fields'       => ['rfq_id'],
    'input_schema'          => [
        'type'       => 'object',
        'properties' => [
            'rfq_id'           => ['type' => 'integer', 'description' => 'RFQ ID to create the quotation from'],
            'vendor_id'        => ['type' => 'integer', 'description' => 'Vendor ID for the quotation (optional if only one vendor is attached to the RFQ)'],
            'quotation_date'   => ['type' => 'string', 'description' => 'Quotation date (YYYY-MM-DD)'],
            'valid_until'      => ['type' => 'string', 'description' => 'Quote expiry date (YYYY-MM-DD)'],
            'currency'         => ['type' => 'string', 'description' => 'Currency code'],
            'payment_terms'    => ['type' => 'string', 'description' => 'Payment terms'],
            'delivery_terms'   => ['type' => 'string', 'description' => 'Delivery terms'],
            'incoterms'        => ['type' => 'string', 'description' => 'Incoterms'],
            'warranty_terms'   => ['type' => 'string', 'description' => 'Warranty terms'],
            'vendor_reference' => ['type' => 'string', 'description' => 'Vendor reference number'],
            'lead_time_days'   => ['type' => 'integer', 'description' => 'Lead time in days'],
            'notes'            => ['type' => 'string', 'description' => 'Additional notes'],
        ],
        'required' => ['rfq_id'],
    ],
    'handler_class'         => 'AIActionCreateQuotationDraft',
]);


// ═══ Action Implementations ═══

/**
 * Create Supplier action handler.
 */
class AIActionCreateSupplier extends AIActionHandlerBase
{
    public function validateInput(array $params): array
    {
        $vendorName = $this->sanitizeInput($params['vendor_name'] ?? '', 200);
        if ($vendorName === '') {
            return ['valid' => false, 'error' => 'Supplier name is required.', 'cleaned' => []];
        }

        if (strlen($vendorName) < 2) {
            return ['valid' => false, 'error' => 'Supplier name must be at least 2 characters.', 'cleaned' => []];
        }

        // Check for duplicate name
        $stmt = $this->db->prepare("SELECT id FROM vendors WHERE vendor_name = ? LIMIT 1");
        $stmt->execute([$vendorName]);
        if ($stmt->fetch()) {
            return ['valid' => false, 'error' => 'A supplier with this name already exists.', 'cleaned' => []];
        }

        $cleaned = [
            'vendor_name'    => $vendorName,
            'contact_person' => $this->sanitizeInput($params['contact_person'] ?? '', 100),
            'email'          => $this->sanitizeInput($params['email'] ?? '', 100),
            'phone'          => $this->sanitizeInput($params['phone'] ?? '', 20),
            'address'        => $this->sanitizeInput($params['address'] ?? '', 500),
            'website'        => $this->sanitizeInput($params['website'] ?? '', 200),
            'notes'          => $this->sanitizeInput($params['notes'] ?? '', 500),
        ];

        // Validate email if provided
        if ($cleaned['email'] !== '' && !filter_var($cleaned['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email address format.', 'cleaned' => []];
        }

        return ['valid' => true, 'error' => null, 'cleaned' => $cleaned];
    }

    public function buildPreview(array $params): array
    {
        $fields = [
            ['label' => 'Supplier Name', 'value' => $params['vendor_name']],
        ];

        if (!empty($params['contact_person'])) {
            $fields[] = ['label' => 'Contact Person', 'value' => $params['contact_person']];
        }
        if (!empty($params['email'])) {
            $fields[] = ['label' => 'Email', 'value' => $params['email']];
        }
        if (!empty($params['phone'])) {
            $fields[] = ['label' => 'Phone', 'value' => $params['phone']];
        }
        if (!empty($params['address'])) {
            $fields[] = ['label' => 'Address', 'value' => $params['address']];
        }
        if (!empty($params['website'])) {
            $fields[] = ['label' => 'Website', 'value' => $params['website']];
        }

        $fields[] = ['label' => 'Status', 'value' => 'Active'];

        return [
            'title'    => 'Create New Supplier',
            'fields'   => $fields,
            'warnings' => [],
        ];
    }

    public function execute(array $params): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO vendors (vendor_name, contact_person, email, phone, address, website, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $params['vendor_name'],
                $params['contact_person'] ?: null,
                $params['email'] ?: null,
                $params['phone'] ?: null,
                $params['address'] ?: null,
                $params['website'] ?: null,
                $params['notes'] ?: null,
            ]);

            $vendorId = (int) $this->db->lastInsertId();

            // Log audit
            logAudit($this->userId, "Created supplier: {$params['vendor_name']}", 'procurement');

            return [
                'success'   => true,
                'data'      => "Supplier created successfully.\n\nName: {$params['vendor_name']}\nID: {$vendorId}",
                'error'     => null,
                'record_id' => $vendorId,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'data' => null, 'error' => 'Failed to create supplier: ' . $e->getMessage(), 'record_id' => null];
        }
    }
}

/**
 * Create Purchase Requisition action handler.
 */
class AIActionCreatePurchaseRequisition extends AIActionHandlerBase
{
    public function validateInput(array $params): array
    {
        $requesterId = (int) ($params['requester_id'] ?? 0);
        if ($requesterId <= 0) {
            return ['valid' => false, 'error' => 'Requester is required.', 'cleaned' => []];
        }

        // Verify requester exists
        $stmt = $this->db->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$requesterId]);
        $requester = $stmt->fetch();
        if (!$requester) {
            return ['valid' => false, 'error' => 'Invalid requester.', 'cleaned' => []];
        }

        $requestDate = trim($params['request_date'] ?? '');
        $requiredDate = trim($params['required_date'] ?? '');

        if ($requestDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
            return ['valid' => false, 'error' => 'Request date must be in YYYY-MM-DD format.', 'cleaned' => []];
        }

        if ($requiredDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requiredDate)) {
            return ['valid' => false, 'error' => 'Required date must be in YYYY-MM-DD format.', 'cleaned' => []];
        }

        $priority = $params['priority'] ?? 'normal';
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
            $priority = 'normal';
        }

        $cleaned = [
            'requester_id'  => $requesterId,
            'department_id' => (int) ($params['department_id'] ?? 0) ?: null,
            'request_date'  => $requestDate,
            'required_date' => $requiredDate,
            'priority'      => $priority,
            'purpose'       => $this->sanitizeInput($params['purpose'] ?? '', 255),
            'description'   => $this->sanitizeInput($params['description'] ?? '', 1000),
            'currency'      => $this->sanitizeInput($params['currency'] ?? 'AED', 3),
        ];

        return ['valid' => true, 'error' => null, 'cleaned' => $cleaned];
    }

    public function buildPreview(array $params): array
    {
        // Get requester name
        $stmt = $this->db->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$params['requester_id']]);
        $requester = $stmt->fetch();
        $requesterName = $requester['name'] ?? 'Unknown';

        $fields = [
            ['label' => 'Requester', 'value' => $requesterName],
            ['label' => 'Request Date', 'value' => $params['request_date']],
            ['label' => 'Required By', 'value' => $params['required_date']],
            ['label' => 'Priority', 'value' => ucfirst($params['priority'])],
            ['label' => 'Currency', 'value' => $params['currency']],
        ];

        if (!empty($params['purpose'])) {
            $fields[] = ['label' => 'Purpose', 'value' => $params['purpose']];
        }
        if (!empty($params['description'])) {
            $fields[] = ['label' => 'Description', 'value' => $params['description']];
        }

        $fields[] = ['label' => 'Status', 'value' => 'Draft'];

        $warnings = [];
        if ($params['priority'] === 'urgent') {
            $warnings[] = 'This is marked as urgent.';
        }

        return [
            'title'    => 'Create Purchase Requisition',
            'fields'   => $fields,
            'warnings' => $warnings,
        ];
    }

    public function execute(array $params): array
    {
        try {
            $companyId = (int) ($_SESSION['company_id'] ?? 1);
            $this->db->beginTransaction();

            // Generate PR number
            $settings = ['pr_prefix' => 'PR'];
            $number = 'PR-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $this->db->prepare("
                INSERT INTO purchase_requisitions
                    (pr_number, company_id, department_id, requester_id,
                     request_date, required_date, priority, purpose, description,
                     currency, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $number,
                $companyId,
                $params['department_id'],
                $params['requester_id'],
                $params['request_date'],
                $params['required_date'],
                $params['priority'],
                $params['purpose'] ?: null,
                $params['description'] ?: null,
                $params['currency'],
                $this->userId,
            ]);

            $prId = (int) $this->db->lastInsertId();

            logAudit($this->userId, "Created purchase requisition {$number}", 'procurement');

            $this->db->commit();

            return [
                'success'   => true,
                'data'      => "Purchase Requisition created successfully.\n\nPR Number: {$number}\nRequester: " . ($params['requester_id']) . "\nStatus: Draft",
                'error'     => null,
                'record_id' => $prId,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'data' => null, 'error' => 'Failed to create requisition: ' . $e->getMessage(), 'record_id' => null];
        }
    }
}

/**
 * Create Quotation Draft action handler.
 */
class AIActionCreateQuotationDraft extends AIActionHandlerBase
{
    private function getRfq(int $rfqId): ?array
    {
        if (!class_exists('RfqService')) {
            return null;
        }

        $rfq = RfqService::getRFQ($this->db, $rfqId);
        return $rfq ?: null;
    }

    private function getVendorOptions(int $rfqId): array
    {
        if (!class_exists('RfqService')) {
            return [];
        }

        return RfqService::getVendors($this->db, $rfqId) ?: [];
    }

    private function buildLinesFromRfq(array $rfqItems, array $providedLines = []): array
    {
        $clean = [];

        foreach ($providedLines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $clean[] = [
                'rfq_item_id' => !empty($line['rfq_item_id']) ? (int) $line['rfq_item_id'] : null,
                'is_alternative' => !empty($line['is_alternative']) ? 1 : 0,
                'item_name' => $this->sanitizeInput($line['item_name'] ?? '', 200) ?: '(quoted)',
                'description' => $this->sanitizeInput($line['description'] ?? '', 500),
                'quantity' => $quantity,
                'unit' => $this->sanitizeInput($line['unit'] ?? 'pcs', 20) ?: 'pcs',
                'unit_price' => max(0.0, (float) ($line['unit_price'] ?? 0)),
                'discount_amount' => max(0.0, (float) ($line['discount_amount'] ?? 0)),
                'tax_percent' => max(0.0, (float) ($line['tax_percent'] ?? 0)),
                'delivery_days' => isset($line['delivery_days']) && $line['delivery_days'] !== '' ? (int) $line['delivery_days'] : null,
                'warranty_months' => isset($line['warranty_months']) && $line['warranty_months'] !== '' ? (int) $line['warranty_months'] : null,
                'notes' => $this->sanitizeInput($line['notes'] ?? '', 500),
            ];
        }

        if (!empty($clean)) {
            return $clean;
        }

        foreach ($rfqItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $clean[] = [
                'rfq_item_id' => (int) ($item['id'] ?? 0) ?: null,
                'is_alternative' => 0,
                'item_name' => $this->sanitizeInput($item['item_name'] ?? '', 200) ?: '(quoted)',
                'description' => $this->sanitizeInput($item['description'] ?? '', 500),
                'quantity' => $quantity,
                'unit' => $this->sanitizeInput($item['unit'] ?? 'pcs', 20) ?: 'pcs',
                'unit_price' => 0.0,
                'discount_amount' => 0.0,
                'tax_percent' => (float) ($item['tax_percent'] ?? 0),
                'delivery_days' => null,
                'warranty_months' => null,
                'notes' => '',
            ];
        }

        return $clean;
    }

    private function normalizeCharges(array $charges): array
    {
        $clean = [];

        foreach ($charges as $charge) {
            if (!is_array($charge)) {
                continue;
            }

            $name = $this->sanitizeInput($charge['charge_name'] ?? '', 120);
            $amount = round(max(0.0, (float) ($charge['amount'] ?? 0)), 2);
            if ($name === '' || $amount == 0.0) {
                continue;
            }

            $clean[] = [
                'charge_name' => $name,
                'amount' => $amount,
            ];
        }

        return $clean;
    }

    public function validateInput(array $params): array
    {
        $rfqId = (int) ($params['rfq_id'] ?? 0);
        if ($rfqId <= 0) {
            return ['valid' => false, 'error' => 'RFQ is required.', 'cleaned' => []];
        }

        $rfq = $this->getRfq($rfqId);
        if (!$rfq) {
            return ['valid' => false, 'error' => 'RFQ not found.', 'cleaned' => []];
        }

        $vendorOptions = $this->getVendorOptions($rfqId);
        $vendorId = (int) ($params['vendor_id'] ?? 0);
        if ($vendorId <= 0 && count($vendorOptions) === 1) {
            $vendorId = (int) ($vendorOptions[0]['vendor_id'] ?? 0);
        }

        if ($vendorId <= 0) {
            return ['valid' => false, 'error' => 'Vendor is required for this quotation. Select a vendor attached to the RFQ.', 'cleaned' => []];
        }

        $vendorName = null;
        $allowedVendorIds = array_map(static fn($row) => (int) ($row['vendor_id'] ?? 0), $vendorOptions);
        if (!empty($allowedVendorIds) && !in_array($vendorId, $allowedVendorIds, true)) {
            return ['valid' => false, 'error' => 'Selected vendor is not attached to this RFQ.', 'cleaned' => []];
        }
        foreach ($vendorOptions as $vendor) {
            if ((int) ($vendor['vendor_id'] ?? 0) === $vendorId) {
                $vendorName = $vendor['vendor_name'] ?? null;
                break;
            }
        }

        $rfqItems = class_exists('RfqService') ? (RfqService::getItems($this->db, $rfqId) ?: []) : [];
        $lines = $this->buildLinesFromRfq($rfqItems, $params['lines'] ?? []);
        if (empty($lines)) {
            return ['valid' => false, 'error' => 'The RFQ has no items to quote.', 'cleaned' => []];
        }

        $cleaned = [
            'rfq_id' => $rfqId,
            'rfq_number' => $rfq['rfq_number'] ?? ('RFQ-' . $rfqId),
            'vendor_id' => $vendorId,
            'vendor_name' => $vendorName,
            'quotation_date' => $this->sanitizeInput($params['quotation_date'] ?? date('Y-m-d'), 10) ?: date('Y-m-d'),
            'valid_until' => $this->sanitizeInput($params['valid_until'] ?? date('Y-m-d', strtotime('+30 days')), 10),
            'currency' => $this->sanitizeInput($params['currency'] ?? ($rfq['currency'] ?? 'AED'), 3) ?: 'AED',
            'payment_terms' => $this->sanitizeInput($params['payment_terms'] ?? ($rfq['payment_terms'] ?? ''), 255),
            'delivery_terms' => $this->sanitizeInput($params['delivery_terms'] ?? ($rfq['delivery_terms'] ?? ''), 255),
            'incoterms' => $this->sanitizeInput($params['incoterms'] ?? '', 120),
            'warranty_terms' => $this->sanitizeInput($params['warranty_terms'] ?? '', 255),
            'vendor_reference' => $this->sanitizeInput($params['vendor_reference'] ?? '', 120),
            'lead_time_days' => isset($params['lead_time_days']) && $params['lead_time_days'] !== '' ? (int) $params['lead_time_days'] : null,
            'notes' => $this->sanitizeInput($params['notes'] ?? '', 1000),
            'lines' => $lines,
            'charges' => $this->normalizeCharges($params['charges'] ?? []),
            'draft_mode' => true,
        ];

        return ['valid' => true, 'error' => null, 'cleaned' => $cleaned];
    }

    public function buildPreview(array $params): array
    {
        $lineCount = count($params['lines'] ?? []);
        $fields = [
            ['label' => 'RFQ', 'value' => ($params['rfq_number'] ?? 'RFQ') . ' (#' . (int) ($params['rfq_id'] ?? 0) . ')'],
            ['label' => 'Vendor', 'value' => $params['vendor_name'] ?? ('Vendor #' . (int) ($params['vendor_id'] ?? 0))],
            ['label' => 'Quotation Date', 'value' => $params['quotation_date'] ?? date('Y-m-d')],
            ['label' => 'Valid Until', 'value' => $params['valid_until'] ?? date('Y-m-d', strtotime('+30 days'))],
            ['label' => 'Currency', 'value' => $params['currency'] ?? 'AED'],
            ['label' => 'Line Items', 'value' => (string) $lineCount],
        ];

        $warnings = [
            'This will create a draft quotation shell from the RFQ items.',
            'Draft lines are initialized from the RFQ and can be edited before submission.',
        ];

        return [
            'title' => 'Create Quotation Draft',
            'fields' => $fields,
            'warnings' => $warnings,
        ];
    }

    public function execute(array $params): array
    {
        if (!class_exists('RfqService')) {
            return ['success' => false, 'data' => null, 'error' => 'RFQ service is unavailable.', 'record_id' => null];
        }

        try {
            $header = [
                'vendor_id' => $params['vendor_id'],
                'quotation_date' => $params['quotation_date'] ?? date('Y-m-d'),
                'valid_until' => $params['valid_until'] ?: null,
                'currency' => $params['currency'] ?? 'AED',
                'payment_terms' => $params['payment_terms'] ?: null,
                'delivery_terms' => $params['delivery_terms'] ?: null,
                'incoterms' => $params['incoterms'] ?: null,
                'warranty_terms' => $params['warranty_terms'] ?: null,
                'vendor_reference' => $params['vendor_reference'] ?: null,
                'lead_time_days' => $params['lead_time_days'] ?? null,
                'notes' => $params['notes'] ?: null,
            ];

            $result = RfqService::saveQuotation(
                $this->db,
                (int) $params['rfq_id'],
                null,
                $header,
                $params['lines'] ?? [],
                $params['charges'] ?? [],
                $this->userId
            );

            if (empty($result['success'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => implode("\n", $result['errors'] ?? [$result['message'] ?? 'Quotation draft could not be created.']),
                    'record_id' => null,
                ];
            }

            $quotationId = (int) ($result['id'] ?? 0);
            $openUrl = function_exists('resolveAppUrl')
                ? resolveAppUrl('modules/procurement/quotations/form.php?id=' . $quotationId)
                : (function_exists('url') ? url('modules/procurement/quotations/form.php?id=' . $quotationId) : '/modules/procurement/quotations/form.php?id=' . $quotationId);

            return [
                'success' => true,
                'data' => "Quotation draft created successfully.\n\nRFQ: " . ($params['rfq_number'] ?? ('#' . (int) $params['rfq_id'])) . "\nQuotation ID: {$quotationId}\nOpen: {$openUrl}",
                'error' => null,
                'record_id' => $quotationId,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to create quotation draft: ' . $e->getMessage(),
                'record_id' => null,
            ];
        }
    }
}
